<?php
declare(strict_types=1);

function register_mail_non_spam_export_tests(callable $test, callable $assert): void
{
    $test('non-spam export includes all contact pages and excludes spam and password resets', static function () use ($assert): void {
        $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('CREATE TABLE mail_archive (id INTEGER PRIMARY KEY, kind TEXT, created_at TEXT, status TEXT, subject TEXT, body TEXT, recipient TEXT)');
        $insert = $db->prepare('INSERT INTO mail_archive VALUES (?,?,?,?,?,?,?)');
        for ($id = 1; $id <= 55; $id++) {
            $insert->execute([$id, 'contact', '2026-11-02 12:00:00', ['accepted', 'failed', 'stored'][$id % 3], 'Dotaz č. ' . $id, "Dobrý den, rodinné focení?\nhttps://rbvideosphotos.cz/", 'private@example.test']);
        }
        $insert->execute([56, 'contact', '', 'spam', 'Spam', 'Unwanted', 'private@example.test']);
        $insert->execute([57, 'password_reset', '', 'accepted', 'Reset', 'SECRET MUST NOT EXPORT', 'private@example.test']);
        $rows = (new App\Repositories\MailArchiveRepository($db))->nonSpamContacts();
        $assert(count($rows) === 55 && (int)$rows[0]['id'] === 55 && (int)$rows[54]['id'] === 1, 'Export must include more than the 50-row overview page.');
        $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $assert(!str_contains($json, 'SECRET MUST NOT EXPORT') && !str_contains($json, 'private@example.test'), 'Reset bodies and recipient metadata must not leak.');
        $assert(str_contains($json, 'Dobrý den') && count(json_decode($json, true, 32, JSON_THROW_ON_ERROR)) === 55, 'Czech text and JSON roundtrip must survive.');
        $db->exec('DELETE FROM mail_archive WHERE kind="contact" AND status<>"spam"');
        $assert((new App\Repositories\MailArchiveRepository($db))->nonSpamContacts() === [], 'Empty selection must be a valid empty list.');
    });
    $test('mail download remains admin-only with private attachment headers', static function () use ($assert): void {
        $source = file_get_contents(dirname(__DIR__) . '/app/Controllers/MessagesController.php');
        $method = substr($source, strpos($source, 'public function downloadNonSpam'), strpos($source, 'public function index') - strpos($source, 'public function downloadNonSpam'));
        $auth = strpos($method, '$this->requireAdmin();');
        $read = strpos($method, 'Database::getInstance()');
        $assert($auth !== false && $read !== false && $auth < $read, 'Authorization must precede reading personal data.');
        foreach (['private, no-store', 'Content-Disposition: attachment', 'JSON_THROW_ON_ERROR'] as $required) $assert(str_contains($method, $required));
    });
}
