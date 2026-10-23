<?php

declare(strict_types=1);

use App\Services\MailArchiveService;

function register_contact_spam_notification_tests(callable $test, callable $assert): void
{
    $test('spam notification is opt in, sanitized, and preserves archived spam', function () use ($assert): void {
        $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('CREATE TABLE mail_archive (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kind TEXT NOT NULL,
            recipient TEXT NOT NULL,
            cc_recipient TEXT NULL,
            subject TEXT NOT NULL,
            body TEXT NULL,
            status TEXT NOT NULL DEFAULT "stored",
            error_code TEXT NULL,
            created_at TEXT NOT NULL,
            attempted_at TEXT NULL
        )');
        $_ENV['APP_URL'] = 'https://example.test';
        $_ENV['MAIL_FROM'] = 'team@example.test';

        $calls = [];
        $service = new MailArchiveService($db, function ($to, $subject, $body, $headers, $cc) use (&$calls): bool {
            $calls[] = compact('to', 'subject', 'body', 'headers', 'cc');
            return true;
        });
        $spamBody = "Zpráva: <a href=https://bad.example>casino</a>";

        $disabled = $service->archiveSpam('team@example.test', 'Bad https://bad.example', $spamBody, ['link-in-message']);
        $assert($disabled['notification_status'] === 'disabled' && $calls === [], 'Disabled notification must never reach SMTP.');

        $enabled = $service->archiveSpam(
            'team@example.test',
            'Bad https://bad.example',
            $spamBody,
            ['gambling-promotion', 'link-in-message'],
            "copy@example.test\nsecond@example.test",
            true
        );
        $assert($enabled['status'] === 'spam' && $enabled['notification_status'] === 'accepted');
        $assert(count($calls) === 1 && $calls[0]['to'] === 'team@example.test');
        $assert($calls[0]['cc'] === 'copy@example.test, second@example.test', 'All configured copies must receive the notification.');
        $assert(!str_contains($calls[0]['body'], 'bad.example') && !str_contains($calls[0]['body'], '<a'), 'Untrusted spam content must not enter the notification.');
        $assert(str_contains($calls[0]['body'], 'ID záznamu: ' . $enabled['id']), 'Notification must identify the durable archive row.');
        $row = $db->query('SELECT status, body, error_code FROM mail_archive WHERE id = ' . (int)$enabled['id'])->fetch(PDO::FETCH_ASSOC);
        $assert($row['status'] === 'spam' && $row['body'] === $spamBody && str_starts_with($row['error_code'], 'static_spam:'), 'Notification must not alter archived spam.');

        $failed = (new MailArchiveService($db, static fn(): bool => false))
            ->archiveSpam('team@example.test', 'Spam', $spamBody, ['link-in-message'], '', true);
        $assert($failed['status'] === 'spam' && $failed['notification_status'] === 'failed', 'SMTP rejection must be explicit while retaining spam.');
    });
}
