<?php
namespace App\Services\Mailbox;

use App\Core\Logger;
use App\Repositories\MailboxRepository;
use App\Services\SmtpMailer;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends a reply/new mail through the Active24 SMTP account and keeps the thread consistent.
 *
 * Double-reply protection lives here (server side, serialised per thread by a MySQL named lock):
 * if the thread was answered after its last inbound message - or a new inbound message arrived after the
 * user opened the form - nothing is sent until the caller echoes back the *signature* of the exact state
 * the user was warned about. A confirmation never covers a later change.
 */
final class MailboxSender
{
    public const MAX_RECIPIENTS = 50;

    /**
     * @param \Closure():PHPMailer|null $mailerFactory test seam
     * @param \Closure():ImapClient|null $imapFactory  connection used to file the Sent copy and to refresh Sent before sending
     * @param \Closure|null $sentRefresh                best-effort Sent-folder refresh, called under the thread lock
     */
    public function __construct(
        private MailboxRepository $repo,
        private AttachmentStore $store,
        private ?\Closure $mailerFactory = null,
        private ?\Closure $imapFactory = null,
        private ?\Closure $sentRefresh = null,
    ) {}

    /**
     * @param array{draft_id?:?int,thread_id?:?int,to:string,cc:string,bcc:string,subject:string,body:string,known_inbound_id?:int,confirm_signature?:string,author_label?:string} $in
     * @return array<string,mixed>
     */
    public function send(array $in): array
    {
        $threadId = !empty($in['thread_id']) ? (int) $in['thread_id'] : null;
        $draftId = !empty($in['draft_id']) ? (int) $in['draft_id'] : null;
        $to = self::validAddresses($in['to'] ?? '', 'Komu');
        $cc = self::validAddresses($in['cc'] ?? '', 'Kopie (Cc)');
        $bcc = self::validAddresses($in['bcc'] ?? '', 'Skrytá kopie (Bcc)');
        if ($to === []) {
            throw new MailboxValidationException('Vyplňte alespoň jednoho příjemce.');
        }
        if (count($to) + count($cc) + count($bcc) > self::MAX_RECIPIENTS) {
            throw new MailboxValidationException('Zpráva může mít nejvýše ' . self::MAX_RECIPIENTS . ' příjemců.');
        }
        $subject = trim((string) ($in['subject'] ?? ''));
        if ($subject === '' || preg_match('/[\r\n]/', $subject) || mb_strlen($subject, 'UTF-8') > 500) {
            throw new MailboxValidationException('Vyplňte předmět (nejvýše 500 znaků, bez zalomení řádku).');
        }
        $body = (string) ($in['body'] ?? '');
        if (trim($body) === '') {
            throw new MailboxValidationException('Zpráva je prázdná.');
        }
        if ($threadId !== null && $this->repo->findThread($threadId) === null) {
            throw new MailboxValidationException('Konverzace neexistuje.');
        }
        $attachments = $draftId !== null ? $this->repo->draftAttachments($draftId) : [];
        $total = 0;
        foreach ($attachments as $attachment) {
            $total += (int) $attachment['size'];
            if (!is_file($this->store->path($attachment['storage_key']))) {
                throw new MailboxValidationException('Příloha "' . $attachment['filename'] . '" již není dostupná, nahrajte ji znovu.');
            }
        }
        if ($total > MailboxConfig::maxTotalAttachmentBytes()) {
            throw new MailboxValidationException('Přílohy jsou dohromady příliš velké.');
        }

        $smtp = SmtpMailer::readConfig(); // throws SmtpTransportException('smtp_configuration') before anything is stored
        $messageIdHeader = sprintf('<%s@%s>', bin2hex(random_bytes(16)), substr(strrchr($smtp['from'], '@'), 1));
        $inserted = null;
        $replyTo = null;

        if ($threadId !== null) {
            $lock = 'rbvp_mailbox_thread_' . $threadId;
            $db = $this->repo->db();
            if ((int) $db->query("SELECT GET_LOCK('" . $lock . "', 10)")->fetchColumn() !== 1) {
                return ['status' => 'busy', 'message' => 'Konverzaci právě odesílá někdo jiný. Zkuste to za chvíli.'];
            }
            try {
                if ($this->sentRefresh !== null) {
                    try {
                        ($this->sentRefresh)();
                    } catch (\Throwable $e) {
                        Logger::warning('mailbox_send', 'Sent folder refresh before sending failed', ['class' => $e::class]);
                    }
                }
                $state = $this->repo->replyState($threadId);
                $reasons = [];
                if ($state['answered']) {
                    $reasons[] = 'already_replied';
                }
                if ((int) ($in['known_inbound_id'] ?? 0) > 0 && $state['last_inbound_id'] > (int) $in['known_inbound_id']) {
                    $reasons[] = 'new_inbound';
                }
                if ($reasons !== [] && !hash_equals($state['signature'], (string) ($in['confirm_signature'] ?? ''))) {
                    return $this->conflictResponse($threadId, $state, $reasons);
                }
                $replyTo = $state['last_inbound_id'] > 0 ? $this->repo->findMessage($state['last_inbound_id']) : null;
                [$inReplyTo, $refs] = self::replyHeaders($replyTo);
                $inserted = $this->repo->insertOutbound($threadId, [
                    'message_id' => $messageIdHeader, 'in_reply_to' => $inReplyTo, 'refs' => $refs,
                    'from_addr' => $smtp['from'], 'from_name' => MailboxConfig::fromName(),
                    'to' => implode(', ', $to), 'cc' => implode(', ', $cc), 'bcc' => implode(', ', $bcc),
                    'subject' => $subject, 'body' => $body, 'has_attachments' => $attachments !== [],
                    'author_label' => (string) ($in['author_label'] ?? ''),
                ]);
            } finally {
                $db->query("SELECT RELEASE_LOCK('" . $lock . "')")->fetchColumn();
            }
        } else {
            [$inReplyTo, $refs] = ['', ''];
            $inserted = $this->repo->insertOutbound(null, [
                'message_id' => $messageIdHeader, 'in_reply_to' => '', 'refs' => '',
                'from_addr' => $smtp['from'], 'from_name' => MailboxConfig::fromName(),
                'to' => implode(', ', $to), 'cc' => implode(', ', $cc), 'bcc' => implode(', ', $bcc),
                'subject' => $subject, 'body' => $body, 'has_attachments' => $attachments !== [],
                'author_label' => (string) ($in['author_label'] ?? ''),
            ]);
        }

        $mail = $this->mailerFactory ? ($this->mailerFactory)() : new PHPMailer(true);
        try {
            SmtpMailer::applyTransport($mail, $smtp);
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
            $mail->clearAllRecipients();
            $mail->clearReplyTos();
            $mail->clearCustomHeaders();
            $mail->clearAttachments();
            $mail->setFrom($smtp['from'], MailboxConfig::fromName());
            foreach ($to as $address) {
                $mail->addAddress($address);
            }
            foreach ($cc as $address) {
                $mail->addCC($address);
            }
            foreach ($bcc as $address) {
                $mail->addBCC($address);
            }
            $mail->MessageID = $messageIdHeader;
            if ($inReplyTo !== '') {
                $mail->addCustomHeader('In-Reply-To', $inReplyTo);
                $mail->addCustomHeader('References', $refs);
            }
            $mail->Subject = $subject;
            $mail->isHTML(false);
            $mail->Body = $body;
            foreach ($attachments as $attachment) {
                $mail->addAttachment($this->store->path($attachment['storage_key']), $attachment['filename'], PHPMailer::ENCODING_BASE64, $attachment['mime']);
            }
            if (!$mail->send()) {
                throw new \RuntimeException('smtp_transport');
            }
            $raw = $mail->getSentMIMEMessage();
        } catch (\Throwable $e) {
            // PHPMailer error text may contain recipients; log only the class.
            Logger::error('mailbox_send', 'SMTP send failed', ['class' => $e::class]);
            $this->repo->finishOutbound($inserted['message_id'], false);
            return ['status' => 'failed', 'message' => 'Zprávu se nepodařilo odeslat. Koncept zůstal zachován, zkuste to znovu.'];
        } finally {
            $mail->smtpClose();
        }

        $this->repo->finishOutbound($inserted['message_id'], true);
        if ($draftId !== null) {
            $this->repo->moveDraftAttachmentsToMessage($draftId, $inserted['message_id']);
            $this->repo->deleteDraft($draftId);
        }
        if ($threadId !== null) {
            // An autosave that was still in flight when the user pressed Send may have created another draft of this thread.
            $leftover = $this->repo->findDraftByThread($threadId);
            if ($leftover !== null) {
                foreach ($this->repo->deleteDraft((int) $leftover['id']) as $attachment) {
                    $this->store->delete($attachment['storage_key']);
                }
            }
        }
        $this->repo->markUnread($inserted['thread_id'], false);

        return [
            'status' => 'sent',
            'thread_id' => $inserted['thread_id'],
            'message_id' => $inserted['message_id'],
            'sent_copy_saved' => $this->fileSentCopy($raw),
        ];
    }

    /** @return array<string,mixed> */
    private function conflictResponse(int $threadId, array $state, array $reasons): array
    {
        $out = $state['outbound'];
        $preview = null;
        if ($out !== null) {
            $preview = [
                'sent_at' => $out['sent_at'],
                'author' => (string) ($out['author_label'] ?: 'někdo z týmu'),
                'to' => (string) $out['to_addrs'],
                'excerpt' => mb_substr(trim((string) $out['body_text']), 0, 600, 'UTF-8'),
                'in_progress' => $out['status'] === 'sending',
            ];
        }
        return [
            'status' => 'conflict',
            'reasons' => $reasons,
            'signature' => $state['signature'],
            'last_inbound_id' => $state['last_inbound_id'],
            'outbound' => $preview,
        ];
    }

    /** @return array{0:string,1:string} In-Reply-To and References of an answer to $message */
    private static function replyHeaders(?array $message): array
    {
        if ($message === null) {
            return ['', ''];
        }
        $refs = preg_split('/\s+/', trim((string) ($message['refs'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $refs[] = $message['message_id'];
        $refs = array_values(array_unique($refs));
        // PHPMailer RFC 2047-encodes header values longer than ~980 bytes, which breaks threading at the recipient:
        // keep the root id plus as many of the newest ids as fit into 900 bytes.
        while (count($refs) > 2 && strlen(implode(' ', $refs)) > 900) {
            array_splice($refs, 1, 1);
        }
        return [(string) $message['message_id'], implode(' ', $refs)];
    }

    private function fileSentCopy(string $raw): bool
    {
        if ($this->imapFactory === null) {
            return false;
        }
        $client = null;
        try {
            $client = ($this->imapFactory)();
            $folder = MailboxConfig::sentFolder();
            if ($folder === '') {
                foreach ($client->listFolders() as $candidate) {
                    if (in_array('\\sent', array_map('strtolower', $candidate['flags']), true)) {
                        $folder = $candidate['name'];
                        break;
                    }
                }
            }
            if ($folder === '') {
                $folder = 'Sent';
            }
            $client->append($folder, $raw);
            return true;
        } catch (\Throwable $e) {
            Logger::error('mailbox_send', 'Sent copy could not be filed', ['class' => $e::class, 'code' => $e instanceof ImapException ? $e->errorCode : '']);
            return false;
        } finally {
            $client?->logout();
        }
    }

    /** @return list<string> */
    public static function validAddresses(string $value, string $label): array
    {
        if (strlen($value) > 20000) {
            throw new MailboxValidationException('Pole "' . $label . '" je příliš dlouhé.');
        }
        $result = [];
        foreach (MimeParser::parseAddressList($value) as $item) {
            $addr = $item['addr'];
            if (strlen($addr) > 254 || !filter_var($addr, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $addr)) {
                throw new MailboxValidationException('Pole "' . $label . '" obsahuje neplatnou adresu: ' . mb_substr($addr, 0, 80, 'UTF-8'));
            }
            $result[strtolower($addr)] = $addr;
        }
        return array_values($result);
    }
}
