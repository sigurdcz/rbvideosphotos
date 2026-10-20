<?php
namespace App\Services\Mailbox;

use App\Core\Logger;
use App\Repositories\MailboxRepository;

/**
 * Pulls INBOX and the Sent folder from IMAP into the database. Idempotent (Message-ID), resumable
 * (last UID per folder, bounded batch) and single-flight (MySQL named lock) so cron and the
 * on-demand refresh never import the same mail twice.
 *
 * A message that cannot be imported (too large, unparsable, crashed the previous run) is never dropped
 * silently: a placeholder message pointing to the webmail takes its place in the thread list.
 */
final class MailboxSyncService
{
    private const LOCK = 'rbvp_mailbox_sync';
    private const PROCESSING = 'processing:';

    /** @param \Closure():ImapClient $clientFactory */
    public function __construct(
        private MailboxRepository $repo,
        private AttachmentStore $store,
        private \Closure $clientFactory,
        private string $inboxFolder = 'INBOX',
        private string $sentFolder = '',
        private int $batch = 150,
        private int $initialDays = 90,
        private int $maxFetchBytes = 15_000_000,
        private string $ownAddress = '',
    ) {}

    /** @return array{imported:int,errors:list<string>,locked:bool} */
    public function sync(int $timeBudgetSeconds = 25, bool $sentOnly = false): array
    {
        $result = ['imported' => 0, 'errors' => [], 'locked' => false];
        $db = $this->repo->db();
        if ((int) $db->query("SELECT GET_LOCK('" . self::LOCK . "', 0)")->fetchColumn() !== 1) {
            $result['locked'] = true;
            return $result;
        }
        $client = null;
        try {
            $deadline = microtime(true) + $timeBudgetSeconds;
            $client = ($this->clientFactory)();
            $sentName = $this->resolveSentFolder($client);
            $jobs = [];
            if (!$sentOnly) {
                $jobs[] = [$this->inboxFolder, 'in'];
            }
            if ($sentName !== null) {
                $jobs[] = [$sentName, 'out'];
            } else {
                $result['errors'][] = 'sent_folder_missing';
            }
            foreach ($jobs as [$folder, $direction]) {
                try {
                    $result['imported'] += $this->syncFolder($client, $folder, $direction, $deadline);
                } catch (ImapException $e) {
                    $result['errors'][] = $e->errorCode;
                    $this->recordError($folder, $e->errorCode);
                    Logger::error('mailbox_sync', 'Folder sync failed', ['folder' => $folder, 'code' => $e->errorCode]);
                }
            }
        } catch (ImapException $e) {
            $result['errors'][] = $e->errorCode;
            Logger::error('mailbox_sync', 'IMAP connection failed', ['code' => $e->errorCode]);
            $this->recordError($this->inboxFolder, $e->errorCode);
        } finally {
            $client?->logout();
            $db->query("SELECT RELEASE_LOCK('" . self::LOCK . "')")->fetchColumn();
        }
        return $result;
    }

    private function recordError(string $folder, string $code): void
    {
        $state = $this->repo->syncState($folder);
        $this->repo->saveSyncState($folder, $state['uidvalidity'] !== null ? (int) $state['uidvalidity'] : null, (int) $state['last_uid'], $code);
    }

    private function resolveSentFolder(ImapClient $client): ?string
    {
        if ($this->sentFolder !== '') {
            return $this->sentFolder;
        }
        $folders = $client->listFolders();
        foreach ($folders as $folder) {
            if (in_array('\\sent', array_map('strtolower', $folder['flags']), true)) {
                return $folder['name'];
            }
        }
        $names = array_column($folders, 'name');
        foreach (['Sent', 'INBOX.Sent', 'Sent Items', 'INBOX.Sent Items', 'Sent Messages'] as $candidate) {
            if (in_array($candidate, $names, true)) {
                return $candidate;
            }
        }
        return null;
    }

    /** Folder name used for APPEND of messages sent from the CMS. */
    public function sentFolderName(ImapClient $client): ?string
    {
        return $this->resolveSentFolder($client);
    }

    private function syncFolder(ImapClient $client, string $folder, string $direction, float $deadline): int
    {
        $state = $this->repo->syncState($folder);
        $selected = $client->select($folder);
        $validity = $selected['uidvalidity'];
        $lastUid = (int) $state['last_uid'];
        if ($state['uidvalidity'] !== null && $validity !== null && (int) $state['uidvalidity'] !== $validity) {
            // UIDs were renumbered: forget the stored coordinates (they would collide with new mail) and rescan;
            // Message-ID de-duplication re-attaches the existing rows without duplicating them.
            $lastUid = 0;
            $this->repo->clearImapCoordinates($folder);
        }

        $imported = 0;
        // The previous run died (fatal/timeout) while processing this UID: make it visible instead of retrying forever.
        $error = null;
        if (str_starts_with((string) $state['last_error'], self::PROCESSING)) {
            $crashed = (int) substr((string) $state['last_error'], strlen(self::PROCESSING));
            if ($crashed > 0 && $crashed <= $lastUid) {
                $this->importStub($client, $folder, $direction, $crashed, 'crashed');
                $error = 'import_crashed';
            }
        }

        $uids = $lastUid === 0
            ? $client->uidsSince(new \DateTimeImmutable('-' . $this->initialDays . ' days'))
            : $client->uidsAfter($lastUid);

        $processed = 0;
        foreach ($uids as $uid) {
            if ($processed >= $this->batch || microtime(true) > $deadline) {
                break;
            }
            $processed++;
            $previousUid = $lastUid;
            // Cursor first (at most once) plus a marker: a crash inside the import can then never wedge the sync.
            $this->repo->saveSyncState($folder, $validity, $uid, self::PROCESSING . $uid);
            try {
                $size = $client->messageSize($uid);
                if ($size > $this->maxFetchBytes) {
                    $this->importStub($client, $folder, $direction, $uid, 'too_large', $size);
                    $imported++;
                } else {
                    $message = $client->fetchMessage($uid);
                    if ($this->importRaw($message['raw'], $direction, $folder, $uid, $message['internaldate'])) {
                        $imported++;
                    }
                }
            } catch (ImapException $e) {
                // A tagged NO/BAD for one UID is a problem with that message; a dead connection is not.
                $connectionLost = str_ends_with($e->errorCode, '_eof') || str_ends_with($e->errorCode, '_runaway')
                    || in_array($e->errorCode, ['imap_write', 'imap_connect', 'imap_greeting'], true);
                if (!$connectionLost) {
                    $this->importStub($client, $folder, $direction, $uid, 'unreadable');
                    $error = $e->errorCode;
                } else {
                    // Connection-level failure: give the UID back so the next run retries it.
                    $this->repo->saveSyncState($folder, $validity, $previousUid, $e->errorCode);
                    throw $e;
                }
            } catch (\Throwable $e) {
                $error = 'import_failed';
                Logger::error('mailbox_sync', 'Message import failed', ['folder' => $folder, 'uid' => $uid, 'class' => $e::class]);
                $this->importStub($client, $folder, $direction, $uid, 'failed');
            }
            $lastUid = $uid;
        }
        $this->repo->saveSyncState($folder, $validity, $lastUid, $error);
        return $imported;
    }

    /** Placeholder for a message that could not be imported; keeps the sender/subject when the header is readable. */
    private function importStub(ImapClient $client, string $folder, string $direction, int $uid, string $reason, int $size = 0): void
    {
        $note = $reason === 'too_large'
            ? 'Zpráva má ' . MailboxView::size($size) . ' a je příliš velká na stažení do CMS. Otevřete ji ve webmailu (složka ' . $folder . ').'
            : 'Zprávu se nepodařilo zpracovat. Otevřete ji ve webmailu (složka ' . $folder . ').';
        try {
            $parsed = MimeParser::parse($client->fetchHeader($uid));
        } catch (\Throwable $e) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $parsed = [
                'message_id' => '<stub-' . sha1($folder . ':' . $uid . ':' . microtime(true)) . '@mailbox.local>', 'in_reply_to' => '', 'references' => [],
                'from' => ['name' => '', 'addr' => ''], 'to' => [], 'cc' => [], 'reply_to' => [], 'subject' => '(zprávu se nepodařilo načíst)',
                'date' => $now, 'text' => '', 'html' => '', 'attachments' => [],
            ];
        }
        $parsed['text'] = $note;
        $parsed['html'] = '';
        $parsed['attachments'] = [];
        try {
            [, $threadId, $created] = $this->repo->transactional(fn(): array => $this->repo->ingest($parsed, $direction, $folder, $uid, ['own_address' => $this->ownAddress]));
            if ($created && $direction === 'in') {
                $this->repo->flagUnread($threadId);
            }
        } catch (\Throwable $e) {
            Logger::error('mailbox_sync', 'Placeholder for an unimportable message failed', ['folder' => $folder, 'uid' => $uid, 'class' => $e::class]);
        }
    }

    /** @return bool true when a new message row was created */
    public function importRaw(string $raw, string $direction, ?string $folder, ?int $uid, ?\DateTimeImmutable $arrived = null): bool
    {
        $parsed = MimeParser::parse($raw);
        $savedKeys = [];
        try {
            [$created, $threadId] = $this->repo->transactional(function () use ($parsed, $direction, $folder, $uid, $arrived, &$savedKeys): array {
                [$messageId, $threadId, $created] = $this->repo->ingest($parsed, $direction, $folder, $uid, ['arrived' => $arrived, 'own_address' => $this->ownAddress]);
                if (!$created) {
                    return [false, $threadId];
                }
                $hasFiles = false;
                foreach ($parsed['attachments'] as $attachment) {
                    if ($attachment['content'] === '' && $attachment['content_id'] === '') {
                        continue;
                    }
                    $key = $this->store->save($attachment['content']);
                    $savedKeys[] = $key;
                    $this->repo->addAttachment(
                        $messageId, null, AttachmentStore::safeFilename($attachment['filename']), $attachment['mime'],
                        strlen($attachment['content']), $key, $attachment['content_id'], $attachment['inline']
                    );
                    $hasFiles = true;
                }
                if (!$hasFiles && $parsed['attachments'] !== []) {
                    $this->repo->db()->prepare('UPDATE mailbox_messages SET has_attachments = 0 WHERE id = ?')->execute([$messageId]);
                }
                return [true, $threadId];
            });
        } catch (\Throwable $e) {
            foreach ($savedKeys as $key) {
                $this->store->delete($key); // the rolled-back rows no longer reference these files
            }
            throw $e;
        }
        if (!$created) {
            return false;
        }
        // Old mail pulled in by the first import is not "new" - only fresh inbound mail flags a thread unread.
        $when = $arrived ?? $parsed['date'];
        if ($direction === 'in' && $when > new \DateTimeImmutable('-3 days')) {
            $this->repo->flagUnread($threadId);
        }
        return true;
    }

    /** True when the last sync run is older than the configured lazy interval. */
    public function isStale(int $maxAgeSeconds): bool
    {
        $newest = 0;
        foreach ($this->repo->allSyncStates() as $state) {
            if (!empty($state['last_run_at'])) {
                $newest = max($newest, (int) strtotime($state['last_run_at'] . ' UTC'));
            }
        }
        return $newest === 0 || (time() - $newest) >= $maxAgeSeconds;
    }
}
