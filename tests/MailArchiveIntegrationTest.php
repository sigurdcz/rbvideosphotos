<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Core\Env;
use App\Services\MailArchiveService;
use App\Repositories\MailArchiveRepository;
Env::load(dirname(__DIR__) . '/.env');
$db = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Env::get('DB_HOST'), Env::get('DB_PORT', '3306')), Env::get('DB_USER'), Env::get('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$name = 'rbvp_mail_test_' . bin2hex(random_bytes(6));
$db->exec('CREATE DATABASE `' . $name . '`');
$checks = 0;
function check(bool $ok, string $message): void { global $checks; if (!$ok) { throw new RuntimeException($message); } $checks++; }
try {
 $db->exec('USE `' . $name . '`');
 $db->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/104_add_mail_archive.sql'));
 $db->exec('ALTER TABLE mail_archive ADD COLUMN cc_recipient TEXT NULL AFTER recipient');
 $_ENV['APP_URL'] = 'https://example.test'; $_ENV['MAIL_FROM'] = 'team@example.test';
 $calls = 0;
 $body = "Jméno: Test\nKontakt: test@example.test\nZpráva: <script>alert(1)</script>";
 $sender = function ($recipient, $subject, $text, $headers) use ($db, &$calls, $body): bool {
   $calls++;
   check($db->query('SELECT body FROM mail_archive ORDER BY id DESC LIMIT 1')->fetchColumn() === $body, 'Body durable before sender');
   check(str_contains($headers, 'From: team@example.test'), 'Configured sender used');
   return false;
 };
 $service = new MailArchiveService($db, $sender);
 $result = $service->send('contact', 'team@example.test', 'Poptávka', $body);
 check($result['status'] === 'failed', 'Rejected transport is failure');
 check($db->query('SELECT body FROM mail_archive WHERE id = 1')->fetchColumn() === $body, 'Failure preserves full body');
 $ok = new MailArchiveService($db, fn() => true);
 $result = $ok->send('contact', 'team@example.test', 'Poptávka', $body);
 check($result['status'] === 'accepted', 'Acceptance is not delivery');
 $spamCalls = 0;
 $spamArchive = new MailArchiveService($db, function () use (&$spamCalls): bool { $spamCalls++; return true; });
 $spamResult = $spamArchive->archiveSpam('team@example.test', 'Spam', $body, ['gambling-promotion', 'link-in-message']);
 check($spamResult['status'] === 'spam' && $spamResult['notification_status'] === 'disabled' && $spamCalls === 0, 'Spam is archived without SMTP when notification is disabled');
 $spamRow = $db->query('SELECT status, error_code, body FROM mail_archive WHERE id = ' . (int) $spamResult['id'])->fetch();
 check($spamRow['status'] === 'spam' && str_starts_with($spamRow['error_code'], 'static_spam:') && $spamRow['body'] === $body, 'Spam body and bounded reason are durable');
 $notificationCalls = [];
 $spamNotify = new MailArchiveService($db, function ($to, $subject, $text, $headers, $cc) use (&$notificationCalls): bool {
   $notificationCalls[] = compact('to', 'subject', 'text', 'headers', 'cc');
   return true;
 });
 $notified = $spamNotify->archiveSpam('team@example.test', 'Untrusted https://bad.example', $body, ['link-in-message'], 'copy@example.test', true);
 check($notified['status'] === 'spam' && $notified['notification_status'] === 'accepted', 'Enabled spam notification reports SMTP acceptance separately from spam status');
 check(count($notificationCalls) === 1 && $notificationCalls[0]['to'] === 'team@example.test' && $notificationCalls[0]['cc'] === 'copy@example.test', 'Spam notification reaches primary and configured copies');
 check(!str_contains($notificationCalls[0]['text'], 'bad.example') && !str_contains($notificationCalls[0]['text'], '<script>') && str_contains($notificationCalls[0]['text'], 'ID záznamu:'), 'Spam notification excludes untrusted content and identifies the archive row');
 $failedNotification = (new MailArchiveService($db, fn() => false))->archiveSpam('team@example.test', 'Spam', $body, ['link-in-message'], '', true);
 check($failedNotification['status'] === 'spam' && $failedNotification['notification_status'] === 'failed', 'Notification failure remains visible while spam stays archived');
 $spamOverview = (new MailArchiveRepository($db))->overview('spam', 'contact', 1);
 check($spamOverview['total'] === 3 && $spamOverview['counts']['spam'] === 3, 'Admin repository filters and counts all spam notification outcomes');
 $exception = new MailArchiveService($db, fn() => throw new RuntimeException('SECRET transport credentials'));
 $exception->send('contact', 'team@example.test', 'Poptávka', $body);
 check($db->query('SELECT error_code FROM mail_archive ORDER BY id DESC LIMIT 1')->fetchColumn() === 'transport_exception', 'Safe exception code');
 $secret = 'reset secret must not persist';
 $ok->send('password_reset', 'team@example.test', 'Obnova hesla - CMS', $secret);
 $row = $db->query('SELECT * FROM mail_archive ORDER BY id DESC LIMIT 1')->fetch();
 check($row['body'] === null && !str_contains(json_encode($row), $secret), 'Reset secret never archived');
 $service->send('contact', 'invalid', 'Poptávka', $body);
 check($calls === 1, 'Invalid recipient never reaches sender');
 check($db->query('SELECT error_code FROM mail_archive ORDER BY id DESC LIMIT 1')->fetchColumn() === 'invalid_recipient', 'Invalid recipient recorded');
 $_ENV['MAIL_FROM'] = 'bad sender';
 $service->send('contact', 'team@example.test', 'Poptávka', $body);
 check($calls === 1, 'Bad sender never reaches transport');
 check($db->query('SELECT body FROM mail_archive ORDER BY id DESC LIMIT 1')->fetchColumn() === $body, 'Configuration failure retains message');
 $_ENV['MAIL_FROM'] = 'team@example.test';
 $statusFailure = new MailArchiveService($db, function () use ($db): bool {
   $db->exec("ALTER TABLE mail_archive CHANGE status status_unavailable VARCHAR(32) NOT NULL DEFAULT 'stored'"); return true;
 });
 $result = $statusFailure->send('contact', 'team@example.test', 'Poptávka', $body);
 check($result['status'] === 'unconfirmed', 'Failed status write is visible');
 $db->exec("ALTER TABLE mail_archive CHANGE status_unavailable status VARCHAR(32) NOT NULL DEFAULT 'stored'");
 $row = $db->query('SELECT body, status FROM mail_archive ORDER BY id DESC LIMIT 1')->fetch();
 check($row['body'] === $body && $row['status'] === 'stored', 'Ambiguous delivery retains body');
 $db->exec('DROP TABLE mail_archive');
 try { $service->send('contact', 'team@example.test', 'Poptávka', $body); throw new Exception('Expected DB failure'); }
 catch (PDOException $e) { check($calls === 1, 'DB failure prevents send'); }
 // The admin archive escapes untrusted contact content.
 $rows = [['id'=>1,'created_at'=>'2026-10-16','subject'=>'<img src=x onerror=alert(1)>','status'=>'spam','kind'=>'contact','recipient'=>'team@example.test','cc_recipient'=>null,'error_code'=>'static_spam:test','attempted_at'=>null,'body'=>$body]];
 $counts=['stored'=>0,'accepted'=>0,'failed'=>0,'spam'=>1];$status='';$kind='';$total=1;$page=1;$_user=['role'=>'admin'];$_csrfToken='test';
 ob_start();require dirname(__DIR__) . '/admin/views/messages/index.php';$html=ob_get_clean();
 check(!str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&lt;script&gt;'), 'Admin body escaped');
 check(!str_contains($html, '<img src=x onerror=alert(1)>'), 'Admin subject escaped');
 check(str_contains($html, 'Zachyceno statickým antispamem'), 'Admin labels archived spam explicitly');
 echo "PASS: {$checks} archive, failure, privacy and escaping assertions.\n";
} finally { $db->exec('DROP DATABASE `' . $name . '`'); }
