<?php
namespace App\Services\Mailbox;

use App\Core\Logger;
use App\Repositories\MailboxRepository;

/**
 * Pulls INBOX and the Sent folder from IMAP into the database. Idempotent (Message-ID), resumable
 * (last UID per folder, bounded batch) and single-flight (MySQL named lock) so cron and the
 * on-demand refresh never import the same mail twice.
 */
final class MailboxSyncService
{
    private const LOCK = 'rbvp_mailbox_sync';

    /** @param \Closure():ImapClient $clientFactory */
    public function __construct(
        private MailboxRepository $repo,
        private AttachmentStore $store,
        private \Closure $clientFactory,
        private string $inboxFolder = 'INBOX',
        private string $sentFolder = '',
        private int $batch = 150,
        private int $initialDays = 90,
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
        foreach (['Sent', 'INBOX.Sent', 'Sent Items', 'INBOX.Sent Items', 'Sent Messages', 'Odeslané', 'INBOX.Odeslané'] as $candidate) {
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
        $lastUid = (int) $state['last_uid'];
        if ($state['uidvalidity'] !== null && $selected['uidvalidity'] !== null && (int) $state['uidvalidity'] !== $selected['uidvalidity']) {
            $lastUid = 0; // UIDs were renumbered; Message-ID de-duplication makes a re-scan safe.
        }
        $uids = $lastUid === 0
            ? $client->uidsSince(new \DateTimeImmutable('-' . $this->initialDays . ' days'))
            : $client->uidsAfter($lastUid);

        $imported = 0;
        $processed = 0;
        $error = null;
        foreach ($uids as $uid) {
            if ($processed >= $this->batch || microtime(true) > $deadline) {
                break;
            }
            $processed++;
            try {
                $raw = $client->fetchRaw($uid);
                if ($this->importRaw($raw, $direction, $folder, $uid)) {
                    $imported++;
                }
            } catch (ImapException $e) {
                if (in_array($e->errorCode, ['imap_literal_too_large', 'imap_fetch_empty'], true)) {
                    $error = $e->errorCode; // skip this one oversized/empty message, keep going
                } else {
                    $this->repo->saveSyncState($folder, $selected['uidvalidity'], $lastUid, $e->errorCode);
                    throw $e;
                }
            } catch (\Throwable $e) {
                $error = 'import_failed';
                Logger::error('mailbox_sync', 'Message import failed', ['folder' => $folder, 'uid' => $uid]);
            }
            $lastUid = $uid;
        }
        $this->repo->saveSyncState($folder, $selected['uidvalidity'], $lastUid, $error);
        return $imported;
    }

    /** @return bool true when a new message row was created */
    public function importRaw(string $raw, string $direction, ?string $folder, ?int $uid): bool
    {
        $parsed = MimeParser::parse($raw);
        [$messageId, $threadId, $created] = $this->repo->ingest($parsed, $direction, $folder, $uid);
        if (!$created) {
            return false;
        }
        $hasFiles = false;
        foreach ($parsed['attachments'] as $attachment) {
            if ($attachment['content'] === '' && $attachment['content_id'] === '') {
                continue;
            }
            $key = $this->store->save($attachment['content']);
            $this->repo->addAttachment(
                $messageId, null, AttachmentStore::safeFilename($attachment['filename']), $attachment['mime'],
                strlen($attachment['content']), $key, $attachment['content_id'], $attachment['inline']
            );
            $hasFiles = true;
        }
        if (!$hasFiles && $parsed['attachments'] !== []) {
            $this->repo->db()->prepare('UPDATE mailbox_messages SET has_attachments = 0 WHERE id = ?')->execute([$messageId]);
        }
        // Old mail pulled in by the first import is not "new" - only fresh inbound mail flags a thread unread.
        if ($direction === 'in' && $parsed['date'] > new \DateTimeImmutable('-3 days')) {
            $this->repo->flagUnread($threadId);
        }
        return true;
    }

    /** True when the last successful sync is older than the configured lazy interval. */
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
