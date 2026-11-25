<?php
namespace App\Services;

use InvalidArgumentException;
use PDO;
use PDOException;

final class AccountUsernameService
{
    public function __construct(private PDO $db) {}

    public function change(int $userId, string $currentPassword, string $username): void
    {
        if (preg_match('/\p{Cc}/u', $username) !== 0) {
            throw new InvalidArgumentException('Přihlašovací jméno obsahuje nepovolené znaky.');
        }
        $username = trim($username);
        if ($username === '' || mb_strlen($username, 'UTF-8') > 255) {
            throw new InvalidArgumentException('Přihlašovací jméno musí mít 1 až 255 znaků.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $hash = $stmt->fetchColumn();
            if (!$hash || !password_verify($currentPassword, $hash)) {
                throw new InvalidArgumentException('Současné heslo není správné.');
            }

            $stmt = $this->db->prepare('UPDATE users SET email = ? WHERE id = ?');
            $stmt->execute([$username, $userId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new InvalidArgumentException('Toto přihlašovací jméno už někdo používá.', 0, $e);
            }
            throw $e;
        }
    }
}
