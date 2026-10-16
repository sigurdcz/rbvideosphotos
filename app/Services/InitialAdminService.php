<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class InitialAdminService
{
    public function __construct(private PDO $db, private string $expectedToken)
    {
    }

    public function create(string $token, string $email, string $password): void
    {
        if ($this->expectedToken === '' || !hash_equals($this->expectedToken, $token)) {
            throw new RuntimeException('Neplatný MIGRATE_TOKEN.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            throw new RuntimeException('Zadej platný e-mail.');
        }
        if (strlen($password) < 12 || strlen($password) > 72) {
            throw new RuntimeException('Heslo musí mít 12 až 72 bajtů.');
        }

        // Serialize bootstrap requests for this database, including across PHP workers.
        $lock = 'initial-admin:' . hash('sha256', (string) $this->db->query('SELECT DATABASE()')->fetchColumn());
        $lock = substr($lock, 0, 64);
        $statement = $this->db->prepare('SELECT GET_LOCK(?, 10)');
        $statement->execute([$lock]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException('Inicializaci právě provádí jiný požadavek. Zkus to později.');
        }
        try {
            if ((int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
                throw new RuntimeException('Inicializace je uzavřená: databáze už obsahuje uživatele. Žádný účet nebyl změněn.');
            }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $insert = $this->db->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'admin')");
            $insert->execute([$email, $hash]);
        } finally {
            $release = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lock]);
        }
    }
}
