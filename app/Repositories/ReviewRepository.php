<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ReviewRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allBySite(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM reviews WHERE site_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function allActive(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM reviews WHERE site_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM reviews WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO reviews (site_id, author_name, context_label, rating, review_text, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['site_id'],
            $data['author_name'] ?? '',
            $data['context_label'] ?? null,
            $this->normalizeRating((int)($data['rating'] ?? 5)),
            $data['review_text'] ?? '',
            $data['sort_order'] ?? 0,
            $data['is_active'] ?? 1,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE reviews
             SET author_name = ?, context_label = ?, rating = ?, review_text = ?, sort_order = ?, is_active = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['author_name'] ?? '',
            $data['context_label'] ?? null,
            $this->normalizeRating((int)($data['rating'] ?? 5)),
            $data['review_text'] ?? '',
            $data['sort_order'] ?? 0,
            $data['is_active'] ?? 1,
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
    }

    public function replaceAllBySite(int $siteId, array $items): void
    {
        $this->db->beginTransaction();

        try {
            $this->db->prepare('DELETE FROM reviews WHERE site_id = ?')->execute([$siteId]);

            $stmt = $this->db->prepare(
                'INSERT INTO reviews (site_id, author_name, context_label, rating, review_text, sort_order, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($items as $item) {
                $author = trim((string)($item['author_name'] ?? ''));
                $text = trim((string)($item['review_text'] ?? ''));

                if ($author === '' && $text === '') {
                    continue;
                }

                $stmt->execute([
                    $siteId,
                    $author,
                    trim((string)($item['context_label'] ?? '')) ?: null,
                    $this->normalizeRating((int)($item['rating'] ?? 5)),
                    $text,
                    (int)($item['sort_order'] ?? 0),
                    !empty($item['is_active']) ? 1 : 0,
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function normalizeRating(int $rating): int
    {
        return max(1, min(5, $rating));
    }
}
