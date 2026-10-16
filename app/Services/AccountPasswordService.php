<?php
namespace App\Services;

use InvalidArgumentException;
use PDO;

final class AccountPasswordService
{
    public function __construct(private PDO $db) {}

    public function change(int $userId, string $currentPassword, string $newPassword): void
    {
        if ($userId < 1 || strlen($newPassword) < 12 || strlen($newPassword) > 72 || str_contains($newPassword, "\0")) {
            throw new InvalidArgumentException('Nové heslo musí mít 12 až 72 bajtů (diakritika zabírá více bajtů).');
        }
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $currentHash = $stmt->fetchColumn();
            if (!$currentHash || !password_verify($currentPassword, $currentHash)) {
                throw new InvalidArgumentException('Současné heslo není správné.');
            }
            if (password_verify($newPassword, $currentHash)) {
                throw new InvalidArgumentException('Nové heslo se musí lišit od současného.');
            }
            $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $userId]);
            $this->db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }
}
