<?php
namespace App\Repositories;

use App\Services\Mailbox\MimeParser;
use PDO;

/**
 * Storage for the shared mailbox. All timestamps are UTC (Y-m-d H:i:s).
 *
 * "Answered" is decided from message order, not from who wrote: a thread is answered when an outbound
 * message (sending/sent) is newer than the newest inbound one. Ordering is (sent_at, id).
 */
final class MailboxRepository
{
    public const PAGE_SIZE = 30;

    public function __construct(private PDO $db) {}

    public function db(): PDO
    {
        return $this->db;
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function normalizeSubject(string $subject): string
    {
        $subject = mb_strtolower(trim($subject), 'UTF-8');
        // Strip reply/forward prefixes in several languages, repeatedly ("Re: Fwd: RE[2]: ...").
        $subject = preg_replace('/^(?:\s*(?:re|fw|fwd|odp|vs|aw|sv)(?:\[\d+\])?\s*:\s*)+/u', '', $subject) ?? $subject;
        return trim(preg_replace('/\s+/u', ' ', $subject) ?? $subject);
    }

    // ------------------------------------------------------------------ ingest / threading

    /**
     * Stores a parsed message (idempotent by Message-ID). Returns [messageId, threadId, created].
     *
     * @return array{0:int,1:int,2:bool}
     */
    public function ingest(array $parsed, string $direction, ?string $folder, ?int $uid, array $extra = []): array
    {
        $hash = sha1(strtolower($parsed['message_id']));
        $existing = $this->db->prepare('SELECT id, thread_id, folder, imap_uid FROM mailbox_messages WHERE message_id_hash = ?');
        $existing->execute([$hash]);
        if ($row = $existing->fetch()) {
            // Outbound mail created by the CMS gets its IMAP coordinates when the Sent folder is synced.
            if ($row['imap_uid'] === null && $folder !== null && $uid !== null) {
                $this->db->prepare('UPDATE mailbox_messages SET folder = ?, imap_uid = ? WHERE id = ? AND imap_uid IS NULL')
                    ->execute([$folder, $uid, $row['id']]);
            }
            return [(int) $row['id'], (int) $row['thread_id'], false];
        }

        $subject = mb_substr($parsed['subject'] !== '' ? $parsed['subject'] : '(bez předmětu)', 0, 500, 'UTF-8');
        $counterparty = $direction === 'in'
            ? $parsed['from']['addr']
            : ($parsed['to'][0]['addr'] ?? '');
        $sentAt = $parsed['date']->format('Y-m-d H:i:s');
        $threadId = $this->resolveThread($parsed, $subject, $counterparty, $sentAt);

        $status = $direction === 'in' ? 'received' : 'sent';
        $this->db->prepare(
            'INSERT INTO mailbox_messages (thread_id, direction, status, folder, imap_uid, message_id, message_id_hash, in_reply_to, refs,
                from_addr, from_name, reply_to, to_addrs, cc_addrs, bcc_addrs, subject, body_text, body_html, has_attachments,
                author_label, sent_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $threadId, $direction, $status, $folder, $uid,
            mb_substr($parsed['message_id'], 0, 998, 'UTF-8'), $hash,
            $parsed['in_reply_to'] !== '' ? $parsed['in_reply_to'] : null,
            $parsed['references'] !== [] ? implode(' ', $parsed['references']) : null,
            mb_substr($parsed['from']['addr'], 0, 255, 'UTF-8'), mb_substr($parsed['from']['name'], 0, 255, 'UTF-8'),
            mb_substr((string) self::formatAddresses($parsed['reply_to']), 0, 500, 'UTF-8') ?: null,
            self::formatAddresses($parsed['to']), self::formatAddresses($parsed['cc']), $extra['bcc'] ?? null,
            $subject, $parsed['text'], $parsed['html'] !== '' ? $parsed['html'] : null,
            $parsed['attachments'] !== [] ? 1 : 0,
            $extra['author_label'] ?? null, $sentAt, self::now(),
        ]);
        $messageId = (int) $this->db->lastInsertId();
        $this->refreshThread($threadId);
        return [$messageId, $threadId, true];
    }

    private function resolveThread(array $parsed, string $subject, string $counterparty, string $sentAt): int
    {
        $refs = $parsed['references'];
        if ($parsed['in_reply_to'] !== '') {
            array_unshift($refs, $parsed['in_reply_to']);
        }
        $hashes = array_values(array_unique(array_map(static fn(string $id): string => sha1(strtolower($id)), $refs)));
        if ($hashes !== []) {
            $in = implode(',', array_fill(0, count($hashes), '?'));
            $stmt = $this->db->prepare("SELECT thread_id FROM mailbox_messages WHERE message_id_hash IN ($in) ORDER BY id DESC LIMIT 1");
            $stmt->execute($hashes);
            $threadId = $stmt->fetchColumn();
            if ($threadId !== false) {
                return (int) $threadId;
            }
        }

        $key = sha1(self::normalizeSubject($subject));
        if ($counterparty !== '') {
            $stmt = $this->db->prepare(
                'SELECT id FROM mailbox_threads WHERE subject_key = ? AND counterparty = ? AND last_message_at >= ? ORDER BY last_message_at DESC LIMIT 1'
            );
            $stmt->execute([$key, $counterparty, gmdate('Y-m-d H:i:s', strtotime($sentAt . ' UTC') - 30 * 86400)]);
            $threadId = $stmt->fetchColumn();
            if ($threadId !== false) {
                return (int) $threadId;
            }
        }

        $this->db->prepare(
            'INSERT INTO mailbox_threads (subject, subject_key, counterparty, last_message_at, created_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([$subject, $key, mb_substr($counterparty, 0, 255, 'UTF-8'), $sentAt, self::now()]);
        return (int) $this->db->lastInsertId();
    }

    /** Recomputes the denormalised columns of a thread from its messages. */
    public function refreshThread(int $threadId): void
    {
        $stmt = $this->db->prepare(
            "SELECT
               COUNT(*) AS total,
               MAX(sent_at) AS last_at,
               MAX(CASE WHEN direction = 'in' THEN sent_at END) AS last_in,
               MAX(CASE WHEN direction = 'out' AND status IN ('sending','sent') THEN sent_at END) AS last_out
             FROM mailbox_messages WHERE thread_id = ? AND (direction = 'in' OR status IN ('sending','sent'))"
        );
        $stmt->execute([$threadId]);
        $agg = $stmt->fetch();
        if ((int) ($agg['total'] ?? 0) === 0) {
            $this->db->prepare('DELETE FROM mailbox_threads WHERE id = ?')->execute([$threadId]);
            return;
        }
        $last = $this->db->prepare(
            "SELECT body_text FROM mailbox_messages WHERE thread_id = ? AND (direction = 'in' OR status IN ('sending','sent')) ORDER BY sent_at DESC, id DESC LIMIT 1"
        );
        $last->execute([$threadId]);
        $snippet = trim(preg_replace('/\s+/u', ' ', (string) $last->fetchColumn()) ?? '');
        $this->db->prepare(
            'UPDATE mailbox_threads SET message_count = ?, last_message_at = ?, last_inbound_at = ?, last_outbound_at = ?, snippet = ? WHERE id = ?'
        )->execute([(int) $agg['total'], $agg['last_at'], $agg['last_in'], $agg['last_out'], mb_substr($snippet, 0, 300, 'UTF-8'), $threadId]);
    }

    public function markUnread(int $threadId, bool $unread): void
    {
        $this->db->prepare('UPDATE mailbox_threads SET is_unread = ? WHERE id = ?')->execute([$unread ? 1 : 0, $threadId]);
    }

    /** New inbound mail flags the thread unread (called from sync, not from refreshThread). */
    public function flagUnread(int $threadId): void
    {
        $this->markUnread($threadId, true);
    }

    // ------------------------------------------------------------------ reading

    /** @return array{rows:list<array>,total:int,page:int,pages:int} */
    public function listThreads(string $filter = 'all', string $query = '', int $page = 1): array
    {
        $where = [];
        $values = [];
        if ($filter === 'unanswered') {
            $where[] = 't.last_inbound_at IS NOT NULL AND (t.last_outbound_at IS NULL OR t.last_outbound_at < t.last_inbound_at)';
        } elseif ($filter === 'unread') {
            $where[] = 't.is_unread = 1';
        } elseif ($filter === 'answered') {
            $where[] = 't.last_outbound_at IS NOT NULL AND (t.last_inbound_at IS NULL OR t.last_outbound_at >= t.last_inbound_at)';
        }
        $query = trim($query);
        if ($query !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($query, 0, 100, 'UTF-8')) . '%';
            $where[] = 'EXISTS (SELECT 1 FROM mailbox_messages m WHERE m.thread_id = t.id
                AND (m.subject LIKE ? OR m.from_addr LIKE ? OR m.from_name LIKE ? OR m.to_addrs LIKE ? OR m.body_text LIKE ?))';
            array_push($values, $like, $like, $like, $like, $like);
        }
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM mailbox_threads t' . $sqlWhere);
        $count->execute($values);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = max(1, min($page, $pages));
        $stmt = $this->db->prepare(
            'SELECT t.*, (SELECT d.id FROM mailbox_drafts d WHERE d.thread_id = t.id LIMIT 1) AS draft_id
             FROM mailbox_threads t' . $sqlWhere . ' ORDER BY t.last_message_at DESC, t.id DESC LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE)
        );
        $stmt->execute($values);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['awaiting_reply'] = self::isAwaiting($row);
        }
        unset($row);
        return compact('rows', 'total', 'page', 'pages');
    }

    public static function isAwaiting(array $thread): bool
    {
        return !empty($thread['last_inbound_at'])
            && (empty($thread['last_outbound_at']) || $thread['last_outbound_at'] < $thread['last_inbound_at']);
    }

    /** @return array{unread:int,awaiting:int,drafts:int} */
    public function counters(): array
    {
        $row = $this->db->query(
            "SELECT
               SUM(is_unread = 1) AS unread,
               SUM(last_inbound_at IS NOT NULL AND (last_outbound_at IS NULL OR last_outbound_at < last_inbound_at)) AS awaiting
             FROM mailbox_threads"
        )->fetch();
        $drafts = (int) $this->db->query('SELECT COUNT(*) FROM mailbox_drafts')->fetchColumn();
        return ['unread' => (int) ($row['unread'] ?? 0), 'awaiting' => (int) ($row['awaiting'] ?? 0), 'drafts' => $drafts];
    }

    public function findThread(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM mailbox_threads WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return list<array> visible messages (failed sends never get stored), oldest first, with attachments */
    public function threadMessages(int $threadId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM mailbox_messages WHERE thread_id = ? AND (direction = 'in' OR status IN ('sending','sent')) ORDER BY sent_at ASC, id ASC"
        );
        $stmt->execute([$threadId]);
        $messages = $stmt->fetchAll();
        if ($messages === []) {
            return [];
        }
        $ids = array_column($messages, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $att = $this->db->prepare("SELECT * FROM mailbox_attachments WHERE message_id IN ($in) ORDER BY id");
        $att->execute($ids);
        $byMessage = [];
        foreach ($att->fetchAll() as $a) {
            $byMessage[(int) $a['message_id']][] = $a;
        }
        foreach ($messages as &$m) {
            $m['attachments'] = $byMessage[(int) $m['id']] ?? [];
        }
        unset($m);
        return $messages;
    }

    public function findMessage(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM mailbox_messages WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // ------------------------------------------------------------------ reply guard

    /**
     * Snapshot used by the "already answered" protection. The signature changes whenever the thread
     * gets a new message in either direction, so a confirmation can only ever cover the state the
     * user actually saw.
     *
     * @return array{last_inbound_id:int,last_outbound_id:int,answered:bool,signature:string,outbound:?array}
     */
    public function replyState(int $threadId): array
    {
        $in = $this->db->prepare("SELECT id, sent_at FROM mailbox_messages WHERE thread_id = ? AND direction = 'in' ORDER BY sent_at DESC, id DESC LIMIT 1");
        $in->execute([$threadId]);
        $lastIn = $in->fetch() ?: null;

        $out = $this->db->prepare(
            "SELECT id, sent_at, status, from_addr, author_label, to_addrs, cc_addrs, subject, body_text FROM mailbox_messages
             WHERE thread_id = ? AND direction = 'out' AND status IN ('sending','sent') ORDER BY sent_at DESC, id DESC LIMIT 1"
        );
        $out->execute([$threadId]);
        $lastOut = $out->fetch() ?: null;

        $answered = $lastOut !== null && ($lastIn === null
            || $lastOut['sent_at'] > $lastIn['sent_at']
            || ($lastOut['sent_at'] === $lastIn['sent_at'] && (int) $lastOut['id'] > (int) $lastIn['id']));

        return [
            'last_inbound_id' => (int) ($lastIn['id'] ?? 0),
            'last_outbound_id' => (int) ($lastOut['id'] ?? 0),
            'answered' => $answered,
            'signature' => substr(sha1((int) ($lastIn['id'] ?? 0) . ':' . (int) ($lastOut['id'] ?? 0) . ':' . (string) ($lastOut['status'] ?? '')), 0, 16),
            'outbound' => $answered ? $lastOut : null,
        ];
    }

    // ------------------------------------------------------------------ outbound

    public function insertOutbound(?int $threadId, array $fields): array
    {
        $sentAt = self::now();
        $subject = mb_substr($fields['subject'], 0, 500, 'UTF-8');
        $hash = sha1(strtolower($fields['message_id']));
        if ($threadId === null) {
            $first = MimeParser::parseAddressList($fields['to'])[0]['addr'] ?? '';
            $this->db->prepare(
                'INSERT INTO mailbox_threads (subject, subject_key, counterparty, last_message_at, created_at) VALUES (?, ?, ?, ?, ?)'
            )->execute([$subject, sha1(self::normalizeSubject($subject)), $first, $sentAt, $sentAt]);
            $threadId = (int) $this->db->lastInsertId();
        }
        $this->db->prepare(
            "INSERT INTO mailbox_messages (thread_id, direction, status, message_id, message_id_hash, in_reply_to, refs, from_addr, from_name,
                to_addrs, cc_addrs, bcc_addrs, subject, body_text, has_attachments, author_label, sent_at, created_at)
             VALUES (?, 'out', 'sending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $threadId, $fields['message_id'], $hash, $fields['in_reply_to'] ?: null, $fields['refs'] ?: null,
            $fields['from_addr'], $fields['from_name'], $fields['to'], $fields['cc'] ?: null, $fields['bcc'] ?: null,
            $subject, $fields['body'], $fields['has_attachments'] ? 1 : 0, $fields['author_label'] ?: null, $sentAt, $sentAt,
        ]);
        $messageId = (int) $this->db->lastInsertId();
        $this->refreshThread($threadId);
        return ['message_id' => $messageId, 'thread_id' => $threadId];
    }

    public function finishOutbound(int $messageId, bool $sent): void
    {
        $message = $this->findMessage($messageId);
        if ($message === null) {
            return;
        }
        if ($sent) {
            $this->db->prepare("UPDATE mailbox_messages SET status = 'sent' WHERE id = ?")->execute([$messageId]);
        } else {
            $this->db->prepare('DELETE FROM mailbox_messages WHERE id = ?')->execute([$messageId]);
        }
        $this->refreshThread((int) $message['thread_id']);
    }

    // ------------------------------------------------------------------ drafts

    public function findDraft(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM mailbox_drafts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findDraftByThread(int $threadId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM mailbox_drafts WHERE thread_id = ?');
        $stmt->execute([$threadId]);
        return $stmt->fetch() ?: null;
    }

    /** @return list<array> */
    public function listDrafts(): array
    {
        return $this->db->query(
            'SELECT d.*, t.subject AS thread_subject FROM mailbox_drafts d LEFT JOIN mailbox_threads t ON t.id = d.thread_id ORDER BY d.updated_at DESC, d.id DESC LIMIT 200'
        )->fetchAll();
    }

    /**
     * Optimistic save: returns ['ok'=>true,'id'=>..,'version'=>..] or ['ok'=>false,'conflict'=>currentDraft].
     * $expectedVersion = null creates a new draft (or, for a thread that already has one, reports a conflict).
     */
    public function saveDraft(?int $id, ?int $threadId, array $fields, ?int $expectedVersion, bool $force = false): array
    {
        $now = self::now();
        $values = [
            mb_substr($fields['to'], 0, 20000, 'UTF-8'), mb_substr($fields['cc'], 0, 20000, 'UTF-8'), mb_substr($fields['bcc'], 0, 20000, 'UTF-8'),
            mb_substr($fields['subject'], 0, 500, 'UTF-8'), mb_substr($fields['body'], 0, 1_000_000, 'UTF-8'), $fields['author_label'] ?: null,
        ];
        if ($id === null) {
            if ($threadId !== null && ($existing = $this->findDraftByThread($threadId)) !== null) {
                if (!$force) {
                    return ['ok' => false, 'conflict' => $existing];
                }
                $id = (int) $existing['id'];
                $expectedVersion = (int) $existing['version'];
            } else {
                $this->db->prepare(
                    'INSERT INTO mailbox_drafts (thread_id, to_addrs, cc_addrs, bcc_addrs, subject, body, author_label, version, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)'
                )->execute([$threadId, ...$values, $now, $now]);
                return ['ok' => true, 'id' => (int) $this->db->lastInsertId(), 'version' => 1];
            }
        }
        $sql = 'UPDATE mailbox_drafts SET to_addrs = ?, cc_addrs = ?, bcc_addrs = ?, subject = ?, body = ?, author_label = ?, version = version + 1, updated_at = ? WHERE id = ?';
        $params = [...$values, $now, $id];
        if (!$force) {
            $sql .= ' AND version = ?';
            $params[] = (int) $expectedVersion;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $current = $this->findDraft($id);
        if ($current === null) {
            return ['ok' => false, 'gone' => true];
        }
        if ($stmt->rowCount() === 0 && !$force) {
            return ['ok' => false, 'conflict' => $current];
        }
        return ['ok' => true, 'id' => $id, 'version' => (int) $current['version']];
    }

    /** Removes the draft and its not-yet-sent attachments (files are removed by the caller). @return list<array> removed attachments */
    public function deleteDraft(int $id): array
    {
        $att = $this->db->prepare('SELECT * FROM mailbox_attachments WHERE draft_id = ?');
        $att->execute([$id]);
        $attachments = $att->fetchAll();
        $this->db->prepare('DELETE FROM mailbox_attachments WHERE draft_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM mailbox_drafts WHERE id = ?')->execute([$id]);
        return $attachments;
    }

    // ------------------------------------------------------------------ attachments

    public function addAttachment(?int $messageId, ?int $draftId, string $filename, string $mime, int $size, string $storageKey, string $contentId = '', bool $inline = false): int
    {
        $this->db->prepare(
            'INSERT INTO mailbox_attachments (message_id, draft_id, filename, mime, size, storage_key, content_id, is_inline, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $messageId, $draftId, mb_substr($filename, 0, 255, 'UTF-8'), mb_substr($mime, 0, 150, 'UTF-8'), $size, $storageKey,
            $contentId !== '' ? mb_substr($contentId, 0, 255, 'UTF-8') : null, $inline ? 1 : 0, self::now(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findAttachment(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM mailbox_attachments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return list<array> */
    public function draftAttachments(int $draftId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM mailbox_attachments WHERE draft_id = ? ORDER BY id');
        $stmt->execute([$draftId]);
        return $stmt->fetchAll();
    }

    public function deleteAttachment(int $id): void
    {
        $this->db->prepare('DELETE FROM mailbox_attachments WHERE id = ?')->execute([$id]);
    }

    public function moveDraftAttachmentsToMessage(int $draftId, int $messageId): void
    {
        $this->db->prepare('UPDATE mailbox_attachments SET message_id = ?, draft_id = NULL WHERE draft_id = ?')->execute([$messageId, $draftId]);
    }

    // ------------------------------------------------------------------ sync state

    public function syncState(string $folder): array
    {
        $stmt = $this->db->prepare('SELECT * FROM mailbox_sync_state WHERE folder = ?');
        $stmt->execute([$folder]);
        return $stmt->fetch() ?: ['folder' => $folder, 'uidvalidity' => null, 'last_uid' => 0, 'last_run_at' => null, 'last_success_at' => null, 'last_error' => null];
    }

    public function saveSyncState(string $folder, ?int $uidvalidity, int $lastUid, ?string $error): void
    {
        $now = self::now();
        $this->db->prepare(
            'INSERT INTO mailbox_sync_state (folder, uidvalidity, last_uid, last_run_at, last_success_at, last_error) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE uidvalidity = VALUES(uidvalidity), last_uid = VALUES(last_uid), last_run_at = VALUES(last_run_at),
               last_success_at = IF(VALUES(last_error) IS NULL, VALUES(last_success_at), last_success_at), last_error = VALUES(last_error)'
        )->execute([$folder, $uidvalidity, $lastUid, $now, $error === null ? $now : null, $error]);
    }

    /** @return list<array> */
    public function allSyncStates(): array
    {
        return $this->db->query('SELECT * FROM mailbox_sync_state ORDER BY folder')->fetchAll();
    }

    // ------------------------------------------------------------------ templates

    /** @return list<array> */
    public function templates(): array
    {
        return $this->db->query('SELECT * FROM mailbox_templates ORDER BY name')->fetchAll();
    }

    public function findTemplate(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM mailbox_templates WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function saveTemplate(?int $id, string $name, string $body): int
    {
        $now = self::now();
        if ($id === null) {
            $this->db->prepare('INSERT INTO mailbox_templates (name, body, created_at, updated_at) VALUES (?, ?, ?, ?)')
                ->execute([mb_substr($name, 0, 150, 'UTF-8'), $body, $now, $now]);
            return (int) $this->db->lastInsertId();
        }
        $this->db->prepare('UPDATE mailbox_templates SET name = ?, body = ?, updated_at = ? WHERE id = ?')
            ->execute([mb_substr($name, 0, 150, 'UTF-8'), $body, $now, $id]);
        return $id;
    }

    public function deleteTemplate(int $id): void
    {
        $this->db->prepare('DELETE FROM mailbox_templates WHERE id = ?')->execute([$id]);
    }

    // ------------------------------------------------------------------ helpers

    /** @param list<array{name:string,addr:string}> $list */
    public static function formatAddresses(array $list): ?string
    {
        if ($list === []) {
            return null;
        }
        $parts = [];
        foreach ($list as $item) {
            $name = trim(str_replace(['"', '<', '>', "\r", "\n"], '', $item['name']));
            if ($name !== '' && preg_match('/[,;]/', $name)) {
                $name = '"' . $name . '"';
            }
            $parts[] = $name !== '' ? $name . ' <' . $item['addr'] . '>' : $item['addr'];
        }
        return implode(', ', $parts);
    }
}
