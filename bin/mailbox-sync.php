<?php
declare(strict_types=1);

/**
 * Cron entry point of the shared mailbox: imports new mail from INBOX and the Sent folder.
 * Run every 2-5 minutes, e.g. every 3 minutes:  star-slash-3 * * * *  php /path/to/bin/mailbox-sync.php
 * Exit code 0 = ok (even when nothing was new), 1 = IMAP/configuration error, 2 = another run holds the lock.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Repositories\MailboxRepository;
use App\Services\Mailbox\AttachmentStore;
use App\Services\Mailbox\MailboxConfig;
use App\Services\Mailbox\MailboxSyncService;

Env::load(BASE_PATH . '/.env');
date_default_timezone_set(\App\Core\Config::get('app', 'timezone', 'Europe/Prague'));

if (!MailboxConfig::isConfigured()) {
    fwrite(STDERR, "Mailbox is not configured (SMTP_*/IMAP_* in .env).\n");
    exit(1);
}

$service = new MailboxSyncService(
    new MailboxRepository(Database::getInstance()),
    new AttachmentStore(),
    static fn() => MailboxConfig::connectImap(),
    MailboxConfig::inboxFolder(),
    MailboxConfig::sentFolder(),
    MailboxConfig::syncBatch(),
    MailboxConfig::initialImportDays(),
    MailboxConfig::maxFetchBytes(),
    MailboxConfig::address()
);
$result = $service->sync(240);

if ($result['locked']) {
    echo "Another sync is running.\n";
    exit(2);
}
echo sprintf("Imported %d message(s)%s\n", $result['imported'], $result['errors'] ? '; errors: ' . implode(', ', $result['errors']) : '');
exit($result['errors'] ? 1 : 0);
