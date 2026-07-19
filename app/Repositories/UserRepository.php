<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Lowest existing user id, or null when the table is empty. Used as a
     * deterministic fallback "publisher" for system-triggered republishes
     * (e.g. portfolio CRUD regenerating the /nase-prace listing) where no admin
     * session user is available (API-key context).
     */
    public function firstId(): ?int
    {
        $stmt = $this->db->query('SELECT id FROM users ORDER BY id ASC LIMIT 1');
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }
}
