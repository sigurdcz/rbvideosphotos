<?php
namespace App\Repositories;

use InvalidArgumentException;
use PDO;

final class MailArchiveRepository
{
    public function __construct(private PDO $db) {}

    public function overview(string $status = '', string $kind = '', int $page = 1): array
    {
        if (!in_array($status, ['', 'stored', 'accepted', 'failed', 'spam'], true)
            || !in_array($kind, ['', 'contact', 'password_reset'], true) || $page < 1 || $page > 1000000) {
            throw new InvalidArgumentException('Neplatný filtr přehledu e-mailů.');
        }
        $conditions = []; $values = [];
        if ($status !== '') { $conditions[] = 'status = ?'; $values[] = $status; }
        if ($kind !== '') { $conditions[] = 'kind = ?'; $values[] = $kind; }
        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM mail_archive' . $where);
        $stmt->execute($values);
        $total = (int) $stmt->fetchColumn();
        $offset = ($page - 1) * 50;
        $stmt = $this->db->prepare('SELECT * FROM mail_archive' . $where . ' ORDER BY id DESC LIMIT 50 OFFSET ' . $offset);
        $stmt->execute($values);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $counts = ['stored' => 0, 'accepted' => 0, 'failed' => 0, 'spam' => 0];
        foreach ($this->db->query('SELECT status, COUNT(*) AS total FROM mail_archive GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        return compact('rows', 'page', 'total', 'counts', 'status', 'kind');
    }
}
