<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Repositories\MailboxRepository;
use App\Services\Mailbox\AttachmentStore;
use App\Services\Mailbox\MailboxConfig;
use App\Services\Mailbox\MailboxSender;
use App\Services\Mailbox\MailboxSyncService;
use App\Services\Mailbox\MailboxValidationException;
use App\Services\Mailbox\MailboxView;
use App\Services\Mailbox\MailHtmlSanitizer;
use App\Services\SmtpTransportException;

/**
 * Shared mailbox UI. Access = any logged-in CMS user (no per-user ACL yet); the "author" shown next to a
 * reply is a free-text label the browser remembers, not an identity.
 */
final class MailController extends Controller
{
    private ?MailboxRepository $repo = null;

    // ------------------------------------------------------------------ pages

    public function index(array $params): void
    {
        $this->requireAuth();
        header('Cache-Control: no-store');
        $filter = in_array($_GET['filter'] ?? '', ['unanswered', 'unread', 'answered'], true) ? (string) $_GET['filter'] : 'all';
        $query = trim((string) ($_GET['q'] ?? ''));
        $page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT);
        $repo = $this->repo();
        $list = $repo->listThreads($filter, $query, $page === false ? 1 : $page);
        $this->view('mail.index', $list + [
            'filter' => $filter, 'query' => $query,
            'counters' => $repo->counters(), 'syncStates' => $repo->allSyncStates(),
            'configured' => MailboxConfig::isConfigured(), 'address' => MailboxConfig::address(),
        ]);
    }

    public function thread(array $params): void
    {
        $this->requireAuth();
        header('Cache-Control: no-store');
        $repo = $this->repo();
        $thread = $repo->findThread((int) ($_GET['id'] ?? 0));
        if ($thread === null) {
            http_response_code(404);
            echo 'Konverzace nenalezena.';
            return;
        }
        $messages = $repo->threadMessages((int) $thread['id']);
        $allowImages = ($_GET['images'] ?? '') === '1';
        foreach ($messages as &$message) {
            $cidMap = [];
            foreach ($message['attachments'] as $attachment) {
                if ($attachment['content_id'] !== null && AttachmentStore::isInlineSafe($attachment['mime'])) {
                    $cidMap[$attachment['content_id']] = '/admin/mail/attachment?id=' . (int) $attachment['id'] . '&inline=1';
                }
            }
            $message['safe_html'] = !empty($message['body_html'])
                ? MailHtmlSanitizer::sanitize((string) $message['body_html'], $cidMap, $allowImages)
                : null;
            $message['has_remote_images'] = !$allowImages && !empty($message['body_html']) && preg_match('/<img\b[^>]+src\s*=\s*["\']?https?:/i', (string) $message['body_html']) === 1;
        }
        unset($message);

        $state = $repo->replyState((int) $thread['id']);
        $repo->markUnread((int) $thread['id'], false);
        $draft = $repo->findDraftByThread((int) $thread['id']);
        $mode = ($_GET['mode'] ?? '') === 'all' ? 'all' : 'reply';
        $form = $this->replyForm($repo, $thread, $messages, $draft, $mode);
        $this->view('mail.thread', [
            'thread' => $thread, 'messages' => $messages, 'state' => $state, 'form' => $form,
            'draft' => $draft, 'draftAttachments' => $draft ? $repo->draftAttachments((int) $draft['id']) : [],
            'templates' => $repo->templates(), 'allowImages' => $allowImages, 'mode' => $mode,
            'address' => MailboxConfig::address(), 'counters' => $repo->counters(),
            'maxAttachmentBytes' => MailboxConfig::maxAttachmentBytes(),
        ]);
    }

    public function compose(array $params): void
    {
        $this->requireAuth();
        header('Cache-Control: no-store');
        $repo = $this->repo();
        $draft = null;
        if (!empty($_GET['draft'])) {
            $draft = $repo->findDraft((int) $_GET['draft']);
            if ($draft !== null && $draft['thread_id'] !== null) {
                $this->redirect('/admin/mail/thread?id=' . (int) $draft['thread_id']);
            }
        }
        $form = $draft
            ? ['to' => (string) $draft['to_addrs'], 'cc' => (string) $draft['cc_addrs'], 'bcc' => (string) $draft['bcc_addrs'], 'subject' => (string) $draft['subject'], 'body' => (string) $draft['body'], 'note' => '']
            : ['to' => trim((string) ($_GET['to'] ?? '')), 'cc' => '', 'bcc' => '', 'subject' => '', 'body' => '', 'note' => ''];
        $this->view('mail.compose', [
            'form' => $form, 'draft' => $draft, 'draftAttachments' => $draft ? $repo->draftAttachments((int) $draft['id']) : [],
            'templates' => $repo->templates(), 'address' => MailboxConfig::address(), 'counters' => $repo->counters(),
            'maxAttachmentBytes' => MailboxConfig::maxAttachmentBytes(),
        ]);
    }

    public function drafts(array $params): void
    {
        $this->requireAuth();
        header('Cache-Control: no-store');
        $repo = $this->repo();
        $this->view('mail.drafts', ['drafts' => $repo->listDrafts(), 'counters' => $repo->counters()]);
    }

    // ------------------------------------------------------------------ JSON actions

    public function send(array $params): void
    {
        $this->jsonGuard();
        $repo = $this->repo();
        $in = $this->formFields();
        $in['draft_id'] = (int) ($_POST['draft_id'] ?? 0) ?: null;
        $in['thread_id'] = (int) ($_POST['thread_id'] ?? 0) ?: null;
        $in['known_inbound_id'] = (int) ($_POST['known_inbound_id'] ?? 0);
        $in['confirm_signature'] = (string) ($_POST['confirm_signature'] ?? '');
        session_write_close(); // do not hold the session lock during SMTP/IMAP
        try {
            $result = $this->sender($repo)->send($in);
        } catch (MailboxValidationException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (SmtpTransportException $e) {
            Logger::error('mailbox_send', 'SMTP configuration invalid', ['code' => $e->errorCode]);
            $this->json(['ok' => false, 'error' => 'Odesílání e-mailů není správně nastavené (SMTP).'], 500);
        }
        switch ($result['status']) {
            case 'sent':
                $this->json(['ok' => true, 'status' => 'sent', 'redirect' => '/admin/mail/thread?id=' . (int) $result['thread_id'], 'sent_copy_saved' => $result['sent_copy_saved']]);
            case 'conflict':
                $this->json(['ok' => false, 'status' => 'conflict'] + $result, 409);
            case 'busy':
                $this->json(['ok' => false, 'status' => 'busy', 'error' => $result['message']], 423);
            default:
                $this->json(['ok' => false, 'status' => 'failed', 'error' => $result['message'] ?? 'Odeslání selhalo.'], 502);
        }
    }

    public function draftSave(array $params): void
    {
        $this->jsonGuard();
        $repo = $this->repo();
        $fields = $this->formFields();
        $id = (int) ($_POST['draft_id'] ?? 0) ?: null;
        $threadId = (int) ($_POST['thread_id'] ?? 0) ?: null;
        if ($threadId !== null && $repo->findThread($threadId) === null) {
            $this->json(['ok' => false, 'error' => 'Konverzace neexistuje.'], 404);
        }
        $result = $repo->saveDraft($id, $threadId, $fields, isset($_POST['version']) ? (int) $_POST['version'] : null, ($_POST['force'] ?? '') === '1');
        if (!empty($result['gone'])) {
            $this->json(['ok' => false, 'gone' => true, 'error' => 'Koncept už neexistuje (byl odeslán nebo smazán).'], 410);
        }
        if (!$result['ok']) {
            $c = $result['conflict'];
            $this->json(['ok' => false, 'conflict' => true, 'draft' => [
                'id' => (int) $c['id'], 'version' => (int) $c['version'], 'to' => (string) $c['to_addrs'], 'cc' => (string) $c['cc_addrs'],
                'bcc' => (string) $c['bcc_addrs'], 'subject' => (string) $c['subject'], 'body' => (string) $c['body'],
                'author' => (string) $c['author_label'], 'updated_at' => MailboxView::date($c['updated_at']),
            ]], 409);
        }
        $this->json(['ok' => true, 'id' => $result['id'], 'version' => $result['version'], 'saved_at' => MailboxView::date(MailboxRepository::now(), 'H:i:s')]);
    }

    public function draftDelete(array $params): void
    {
        $this->jsonGuard();
        $repo = $this->repo();
        $draft = $repo->findDraft((int) ($_POST['draft_id'] ?? 0));
        if ($draft !== null) {
            $store = new AttachmentStore();
            foreach ($repo->deleteDraft((int) $draft['id']) as $attachment) {
                $store->delete($attachment['storage_key']);
            }
        }
        $this->json(['ok' => true]);
    }

    public function attachmentUpload(array $params): void
    {
        $this->jsonGuard();
        $repo = $this->repo();
        $draft = $repo->findDraft((int) ($_POST['draft_id'] ?? 0));
        if ($draft === null) {
            $this->json(['ok' => false, 'error' => 'Koncept neexistuje, zprávu nejdřív uložte.'], 404);
        }
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            $tooBig = in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            $this->json(['ok' => false, 'error' => $tooBig ? 'Soubor překračuje limit serveru pro nahrávání.' : 'Soubor se nepodařilo nahrát.'], 422);
        }
        $name = AttachmentStore::safeFilename((string) $file['name']);
        $size = (int) $file['size'];
        if (AttachmentStore::isBlockedFilename($name)) {
            $this->json(['ok' => false, 'error' => 'Tento typ souboru nelze odeslat jako přílohu: ' . $name], 422);
        }
        if ($size <= 0 || $size > MailboxConfig::maxAttachmentBytes()) {
            $this->json(['ok' => false, 'error' => 'Příloha je prázdná nebo větší než ' . MailboxView::size(MailboxConfig::maxAttachmentBytes()) . '.'], 422);
        }
        $existing = array_sum(array_map(static fn(array $a): int => (int) $a['size'], $repo->draftAttachments((int) $draft['id'])));
        if ($existing + $size > MailboxConfig::maxTotalAttachmentBytes()) {
            $this->json(['ok' => false, 'error' => 'Přílohy by dohromady přesáhly ' . MailboxView::size(MailboxConfig::maxTotalAttachmentBytes()) . '.'], 422);
        }
        $content = file_get_contents((string) $file['tmp_name']);
        if ($content === false) {
            $this->json(['ok' => false, 'error' => 'Soubor se nepodařilo přečíst.'], 422);
        }
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open') && ($finfo = finfo_open(FILEINFO_MIME_TYPE)) !== false) {
            $detected = finfo_file($finfo, (string) $file['tmp_name']);
            $mime = is_string($detected) && preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $detected) ? $detected : $mime;
        }
        $key = (new AttachmentStore())->save($content);
        $id = $repo->addAttachment(null, (int) $draft['id'], $name, $mime, strlen($content), $key);
        $this->json(['ok' => true, 'attachment' => ['id' => $id, 'filename' => $name, 'size' => strlen($content), 'size_label' => MailboxView::size(strlen($content))]]);
    }

    public function attachmentDelete(array $params): void
    {
        $this->jsonGuard();
        $repo = $this->repo();
        $attachment = $repo->findAttachment((int) ($_POST['id'] ?? 0));
        // Only attachments that still belong to a draft can be removed; sent mail keeps its files.
        if ($attachment !== null && $attachment['draft_id'] !== null) {
            $repo->deleteAttachment((int) $attachment['id']);
            (new AttachmentStore())->delete($attachment['storage_key']);
        }
        $this->json(['ok' => true]);
    }

    public function attachment(array $params): void
    {
        $this->requireAuth();
        session_write_close();
        $attachment = $this->repo()->findAttachment((int) ($_GET['id'] ?? 0));
        if ($attachment === null) {
            http_response_code(404);
            echo 'Příloha nenalezena.';
            return;
        }
        $path = (new AttachmentStore())->path($attachment['storage_key']);
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Soubor přílohy chybí na disku.';
            return;
        }
        $inline = ($_GET['inline'] ?? '') === '1' && AttachmentStore::isInlineSafe($attachment['mime']);
        $name = AttachmentStore::safeFilename($attachment['filename']);
        header('Content-Type: ' . ($inline ? $attachment['mime'] : 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"" . str_replace('"', '', preg_replace('/[^\x20-\x7E]/', '_', $name)) . "\"; filename*=UTF-8''" . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Cache-Control: private, max-age=3600');
        readfile($path);
    }

    /** Polled by the admin UI: counters for the menu badge and, for an open thread, the reply-guard state. */
    public function poll(array $params): void
    {
        if (!Auth::check()) {
            $this->json(['ok' => false], 401);
        }
        session_write_close();
        $repo = $this->repo();
        $sync = null;
        if (MailboxConfig::isConfigured() && MailboxConfig::lazySyncSeconds() > 0 && ($_GET['nosync'] ?? '') !== '1') {
            $service = $this->syncService($repo);
            if ($service->isStale(MailboxConfig::lazySyncSeconds())) {
                try {
                    $sync = $service->sync(8);
                } catch (\Throwable $e) {
                    Logger::error('mailbox_sync', 'On-demand sync failed', ['class' => $e::class]);
                }
            }
        }
        $payload = ['ok' => true, 'counters' => $repo->counters(), 'synced' => $sync !== null && !$sync['locked'], 'sync_error' => $this->syncError($repo)];
        $threadId = (int) ($_GET['thread'] ?? 0);
        if ($threadId > 0 && $repo->findThread($threadId) !== null) {
            $state = $repo->replyState($threadId);
            $out = $state['outbound'];
            $payload['thread'] = [
                'answered' => $state['answered'], 'signature' => $state['signature'], 'last_inbound_id' => $state['last_inbound_id'],
                'new_inbound' => (int) ($_GET['known_inbound'] ?? 0) > 0 && $state['last_inbound_id'] > (int) $_GET['known_inbound'],
                'outbound' => $out === null ? null : [
                    'sent_at' => MailboxView::date($out['sent_at']), 'author' => (string) ($out['author_label'] ?: 'někdo z týmu'),
                    'excerpt' => mb_substr(trim((string) $out['body_text']), 0, 400, 'UTF-8'), 'in_progress' => $out['status'] === 'sending',
                ],
            ];
        }
        $this->json($payload);
    }

    // ------------------------------------------------------------------ plain POST actions

    public function sync(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();
        session_write_close();
        $result = null;
        try {
            if (!MailboxConfig::isConfigured()) {
                throw new \RuntimeException('not_configured');
            }
            $result = $this->syncService($this->repo())->sync(25);
        } catch (\Throwable $e) {
            Logger::error('mailbox_sync', 'Manual sync failed', ['class' => $e::class]);
        }
        Auth::start();
        if ($result === null) {
            $this->flash('error', 'Synchronizace se nespustila - zkontrolujte nastavení IMAP v .env.');
        } elseif ($result['locked']) {
            $this->flash('success', 'Synchronizace už právě probíhá.');
        } elseif ($result['errors'] !== []) {
            $this->flash('error', 'Synchronizace skončila s chybou (' . implode(', ', $result['errors']) . '). Nově načteno: ' . $result['imported'] . '.');
        } else {
            $this->flash('success', 'Synchronizace hotová. Nově načteno zpráv: ' . $result['imported'] . '.');
        }
        $this->redirect('/admin/mail');
    }

    public function markUnread(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();
        $id = (int) ($_POST['id'] ?? 0);
        if ($this->repo()->findThread($id) !== null) {
            $this->repo()->markUnread($id, true);
        }
        $this->redirect('/admin/mail');
    }

    public function templates(array $params): void
    {
        $this->requireAuth();
        $repo = $this->repo();
        $edit = !empty($_GET['edit']) ? $repo->findTemplate((int) $_GET['edit']) : null;
        $this->view('mail.templates', ['templates' => $repo->templates(), 'edit' => $edit, 'counters' => $repo->counters()]);
    }

    public function templateSave(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();
        $name = trim((string) ($_POST['name'] ?? ''));
        $body = (string) ($_POST['body'] ?? '');
        if ($name === '' || trim($body) === '' || mb_strlen($body, 'UTF-8') > 20000) {
            $this->flash('error', 'Vyplňte název a text šablony (text nejvýše 20 000 znaků).');
            $this->redirect('/admin/mail/templates');
        }
        $this->repo()->saveTemplate((int) ($_POST['id'] ?? 0) ?: null, $name, $body);
        $this->flash('success', 'Šablona odpovědi uložena.');
        $this->redirect('/admin/mail/templates');
    }

    public function templateDelete(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();
        $this->repo()->deleteTemplate((int) ($_POST['id'] ?? 0));
        $this->flash('success', 'Šablona odstraněna.');
        $this->redirect('/admin/mail/templates');
    }

    // ------------------------------------------------------------------ helpers

    private function repo(): MailboxRepository
    {
        return $this->repo ??= new MailboxRepository(Database::getInstance());
    }

    private function syncService(MailboxRepository $repo): MailboxSyncService
    {
        return new MailboxSyncService(
            $repo, new AttachmentStore(), static fn() => MailboxConfig::connectImap(),
            MailboxConfig::inboxFolder(), MailboxConfig::sentFolder(), MailboxConfig::syncBatch(), MailboxConfig::initialImportDays()
        );
    }

    private function sender(MailboxRepository $repo): MailboxSender
    {
        $sync = $this->syncService($repo);
        return new MailboxSender(
            $repo, new AttachmentStore(), null,
            MailboxConfig::isConfigured() ? static fn() => MailboxConfig::connectImap() : null,
            MailboxConfig::isConfigured() ? static fn() => $sync->sync(6, true) : null
        );
    }

    /** JSON endpoints answer 401/403 as JSON instead of redirecting to the login form. */
    private function jsonGuard(): void
    {
        if (!Auth::check()) {
            $this->json(['ok' => false, 'error' => 'Platnost přihlášení vypršela, přihlaste se znovu (text zprávy zkopírujte).'], 401);
        }
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
        if (!Auth::csrfVerify($token)) {
            $this->json(['ok' => false, 'error' => 'Neplatný bezpečnostní token, načtěte stránku znovu.'], 403);
        }
    }

    /** @return array{to:string,cc:string,bcc:string,subject:string,body:string,author_label:string} */
    private function formFields(): array
    {
        return [
            'to' => trim((string) ($_POST['to'] ?? '')), 'cc' => trim((string) ($_POST['cc'] ?? '')), 'bcc' => trim((string) ($_POST['bcc'] ?? '')),
            'subject' => trim((string) ($_POST['subject'] ?? '')), 'body' => str_replace("\r\n", "\n", (string) ($_POST['body'] ?? '')),
            'author_label' => MailboxView::cleanAuthorLabel((string) ($_POST['author'] ?? '')),
        ];
    }

    private function syncError(MailboxRepository $repo): ?string
    {
        foreach ($repo->allSyncStates() as $state) {
            if (!empty($state['last_error'])) {
                return (string) $state['last_error'];
            }
        }
        return null;
    }

    /** @return array{to:string,cc:string,bcc:string,subject:string,body:string,note:string} */
    private function replyForm(MailboxRepository $repo, array $thread, array $messages, ?array $draft, string $mode): array
    {
        if ($draft !== null) {
            return ['to' => (string) $draft['to_addrs'], 'cc' => (string) $draft['cc_addrs'], 'bcc' => (string) $draft['bcc_addrs'], 'subject' => (string) $draft['subject'], 'body' => (string) $draft['body'], 'note' => ''];
        }
        $target = null;
        foreach (array_reverse($messages) as $candidate) {
            if ($candidate['direction'] === 'in') {
                $target = $candidate;
                break;
            }
        }
        $target ??= $messages !== [] ? end($messages) : null;
        $recipients = $target !== null
            ? MailboxView::replyRecipients($target, MailboxConfig::address(), $mode === 'all')
            : ['to' => '', 'cc' => '', 'note' => ''];
        return [
            'to' => $recipients['to'], 'cc' => $recipients['cc'], 'bcc' => '',
            'subject' => MailboxView::replySubject((string) $thread['subject']),
            'body' => $target !== null ? MailboxView::quote($target) : '',
            'note' => $recipients['note'],
        ];
    }
}
