<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/**
 * Integration test of the shared mailbox against a throw-away MySQL database (same approach as
 * MailPersistenceIntegrationTest): threading, reply-state, double-reply protection, drafts, sending and sync.
 * Run: php tests/MailboxIntegrationTest.php
 */
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/MailboxTest.php'; // FakeMailTransport

use App\Core\Env;
use App\Repositories\MailboxRepository;
use App\Services\Mailbox\AttachmentStore;
use App\Services\Mailbox\ImapClient;
use App\Services\Mailbox\MailboxSender;
use App\Services\Mailbox\MailboxSyncService;
use App\Services\Mailbox\MailboxValidationException;
use App\Services\Mailbox\MimeParser;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

final class MailboxTestSmtp extends SMTP
{
    public array $events = ['recipients' => []];
    public bool $open = false;
    public bool $dataAccepted = true;
    public function connect($host, $port = null, $timeout = 30, $options = []) { $this->open = true; return true; }
    public function connected() { return $this->open; }
    public function hello($host = '') { return true; }
    public function startTLS() { return true; }
    public function authenticate($username, $password, $authtype = null, $OAuth = null) { return true; }
    public function getServerExt($name) { return false; }
    public function mail($from) { $this->events['from'] = $from; return true; }
    public function recipient($address, $dsn = '') { $this->events['recipients'][] = $address; return true; }
    public function data($msg_data) { $this->events['data'] = $msg_data; return $this->dataAccepted; }
    public function reset() { return true; }
    public function quit($close_on_error = true) { $this->open = false; return true; }
    public function close() { $this->open = false; }
}

Env::load(dirname(__DIR__) . '/.env');
$server = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Env::get('DB_HOST'), Env::get('DB_PORT', '3306')), Env::get('DB_USER'), Env::get('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$name = 'rbvp_mailbox_test_' . bin2hex(random_bytes(6));
$server->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$storage = sys_get_temp_dir() . '/mbx_int_' . bin2hex(random_bytes(4));
$checks = 0;
function check(bool $ok, string $message = "check failed"): void { global $checks; if (!$ok) { throw new RuntimeException($message); } $checks++; }

/** Builds a raw RFC 822 message for fixtures. */
function raw(array $h, string $body): string
{
    $lines = [];
    foreach ($h as $k => $v) { $lines[] = $k . ': ' . $v; }
    return implode("\r\n", $lines) . "\r\n\r\n" . $body . "\r\n";
}

try {
    $server->exec('USE `' . $name . '`');
    $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', Env::get('DB_HOST'), Env::get('DB_PORT', '3306'), $name), Env::get('DB_USER'), Env::get('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/110_create_mailbox.sql'));
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/110_create_mailbox.sql')); // idempotent
    $_ENV = array_merge($_ENV, ['SMTP_HOST' => 'smtp.example.test', 'SMTP_PORT' => '465', 'SMTP_ENCRYPTION' => 'ssl', 'SMTP_USERNAME' => 'team@example.test', 'SMTP_PASSWORD' => 'fixture-password', 'MAIL_FROM' => 'team@example.test']);

    $repo = new MailboxRepository($pdo);
    $store = new AttachmentStore($storage);
    $sync = new MailboxSyncService($repo, $store, static fn() => throw new RuntimeException('no imap'));

    // ---------------------------------------------------------------- threading + idempotence
    $in1 = raw(['From' => 'Jana <jana@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Svatba 2027', 'Message-ID' => '<c1@client.cz>', 'Date' => gmdate('r', time() - 3600), 'Content-Type' => 'text/plain; charset=utf-8'], 'Dobrý den, mám zájem.');
    check($sync->importRaw($in1, 'in', 'INBOX', 1) === true, 'First import creates a message');
    check($sync->importRaw($in1, 'in', 'INBOX', 1) === false, 'Re-importing the same Message-ID is a no-op');
    $thread = $pdo->query('SELECT * FROM mailbox_threads')->fetch();
    check((int) $thread['message_count'] === 1 && (int) $thread['is_unread'] === 1 && $thread['counterparty'] === 'jana@client.cz', 'Thread created, unread, counterparty set');
    $tid = (int) $thread['id'];
    $state = $repo->replyState($tid);
    check($state['answered'] === false && $state['last_inbound_id'] > 0, 'A lone inbound message is unanswered');
    check(MailboxRepository::isAwaiting($repo->findThread($tid)), 'Thread awaits a reply');

    $sentByWebmail = raw(['From' => 'team@example.test', 'To' => 'jana@client.cz', 'Subject' => 'Re: Svatba 2027', 'Message-ID' => '<o1@example.test>', 'In-Reply-To' => '<c1@client.cz>', 'References' => '<c1@client.cz>', 'Date' => gmdate('r', time() - 1800), 'Content-Type' => 'text/plain; charset=utf-8'], 'Odpověď z webmailu.');
    $sync->importRaw($sentByWebmail, 'out', 'Sent', 5);
    check((int) $pdo->query('SELECT COUNT(*) FROM mailbox_threads')->fetchColumn() === 1, 'Reply joins the thread through In-Reply-To');
    $state = $repo->replyState($tid);
    check($state['answered'] === true, 'A reply sent from webmail (found in the Sent folder) marks the thread answered');
    check(!MailboxRepository::isAwaiting($repo->findThread($tid)), 'Thread no longer awaits');

    $in2 = raw(['From' => 'Jana <jana@client.cz>', 'To' => 'team@example.test', 'Subject' => 'RE: re: Svatba 2027', 'Message-ID' => '<c2@client.cz>', 'Date' => gmdate('r', time() - 600)], 'A ještě dotaz.');
    $sync->importRaw($in2, 'in', 'INBOX', 2);
    check((int) $pdo->query('SELECT COUNT(*) FROM mailbox_threads')->fetchColumn() === 1, 'Subject + counterparty fallback keeps header-less replies together');
    check($repo->replyState($tid)['answered'] === false, 'A newer inbound message re-opens the thread');

    $other = raw(['From' => 'Petr <petr@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Svatba 2027', 'Message-ID' => '<p1@client.cz>', 'Date' => gmdate('r', time() - 100)], 'Jiný klient, stejný předmět.');
    $sync->importRaw($other, 'in', 'INBOX', 3);
    check((int) $pdo->query('SELECT COUNT(*) FROM mailbox_threads')->fetchColumn() === 2, 'Same subject from a different counterparty is a separate thread');

    // Old mail from the first import must not flag threads as unread.
    $old = raw(['From' => 'Starý <old@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Starý dotaz', 'Message-ID' => '<old1@client.cz>', 'Date' => gmdate('r', time() - 30 * 86400)], 'Dávno.');
    $sync->importRaw($old, 'in', 'INBOX', 4);
    check((int) $pdo->query("SELECT is_unread FROM mailbox_threads WHERE subject = 'Starý dotaz'")->fetchColumn() === 0, 'Old mail is not unread');

    // Attachments are stored outside the DB under random keys.
    $withFile = raw(['From' => 'Jana <jana@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Příloha', 'Message-ID' => '<f1@client.cz>', 'Date' => gmdate('r'), 'MIME-Version' => '1.0', 'Content-Type' => 'multipart/mixed; boundary=B'],
        "--B\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nPosílám.\r\n--B\r\nContent-Type: application/pdf; name=\"n.pdf\"\r\nContent-Disposition: attachment; filename=\"n.pdf\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . base64_encode('%PDF-fixture') . "\r\n--B--");
    $sync->importRaw($withFile, 'in', 'INBOX', 6);
    $att = $pdo->query('SELECT * FROM mailbox_attachments')->fetch();
    check($att && $att['filename'] === 'n.pdf' && file_get_contents($store->path($att['storage_key'])) === '%PDF-fixture', 'Attachment content is stored on disk');
    check((int) $pdo->query("SELECT has_attachments FROM mailbox_messages WHERE message_id = '<f1@client.cz>'")->fetchColumn() === 1);

    // ---------------------------------------------------------------- list / counters / search
    $list = $repo->listThreads('unanswered');
    check($list['total'] >= 2, 'Unanswered filter finds waiting threads');
    check($repo->listThreads('all', 'Jiný klient')['total'] === 1, 'Search looks into the body');
    check($repo->listThreads('all', '100%_x')['total'] === 0, 'LIKE wildcards in the query are escaped');
    $counters = $repo->counters();
    check($counters['awaiting'] >= 2 && $counters['unread'] >= 2, 'Counters reflect waiting and unread threads');

    // ---------------------------------------------------------------- drafts (optimistic versioning)
    $fields = ['to' => 'jana@client.cz', 'cc' => '', 'bcc' => '', 'subject' => 'Re: Svatba 2027', 'body' => 'Rozepsáno', 'author_label' => 'Petr'];
    $d1 = $repo->saveDraft(null, $tid, $fields, null);
    check($d1['ok'] && $d1['version'] === 1, 'Draft created');
    $dup = $repo->saveDraft(null, $tid, $fields, null);
    check(!$dup['ok'] && isset($dup['conflict']), 'A second draft for the same thread is reported as a conflict, not duplicated');
    $d2 = $repo->saveDraft($d1['id'], $tid, ['body' => 'Rozepsáno víc'] + $fields, 1);
    check($d2['ok'] && $d2['version'] === 2, 'Save with the current version succeeds');
    $stale = $repo->saveDraft($d1['id'], $tid, ['body' => 'Moje starší verze'] + $fields, 1);
    check(!$stale['ok'] && $stale['conflict']['body'] === 'Rozepsáno víc', 'Stale version never silently overwrites');
    $forced = $repo->saveDraft($d1['id'], $tid, ['body' => 'Přepsáno'] + $fields, 1, true);
    check($forced['ok'] && $forced['version'] === 3, 'Force overwrites on purpose');
    $draftId = $d1['id'];
    $attKey = $store->save('PRILOHA');
    $repo->addAttachment(null, $draftId, 'smlouva.pdf', 'application/pdf', 7, $attKey);

    // ---------------------------------------------------------------- sending + double-reply protection
    $mailers = [];
    $channel = new MailboxTestSmtp();
    $factory = function () use ($channel, &$mailers): PHPMailer {
        $m = new PHPMailer(true);
        $m->setSMTPInstance($channel);
        $channel->events = ['recipients' => []];
        $mailers[] = $m;
        return $m;
    };
    $refreshCalls = 0;
    $sender = new MailboxSender($repo, $store, $factory, null, function () use (&$refreshCalls): void { $refreshCalls++; });

    $stateBefore = $repo->replyState($tid);
    $base = ['thread_id' => $tid, 'draft_id' => $draftId, 'to' => 'jana@client.cz', 'cc' => 'kolega@client.cz', 'bcc' => 'skryta@client.cz', 'subject' => 'Re: Svatba 2027', 'body' => "Dobrý den,\nděkuji.", 'known_inbound_id' => $stateBefore['last_inbound_id'], 'author_label' => 'Petr'];
    $r = $sender->send($base);
    check($r['status'] === 'sent' && $refreshCalls === 1, 'Unanswered thread: message sent, Sent folder refreshed under the lock');
    check($channel->events['recipients'] === ['jana@client.cz', 'kolega@client.cz', 'skryta@client.cz'], 'To, Cc and Bcc all reach the SMTP envelope: ' . json_encode($channel->events['recipients']));
    $data = $channel->events['data'];
    check(!str_contains($data, 'skryta@client.cz'), 'Bcc address must not appear in the message headers');
    check(str_contains($data, 'In-Reply-To: <c2@client.cz>') && str_contains($data, 'References: <c2@client.cz>'), 'Reply carries In-Reply-To and References');
    check(str_contains($data, 'smlouva.pdf') && str_contains($data, 'Cc: kolega@client.cz'), 'Attachment and Cc present in the message');
    check($repo->replyState($tid)['answered'] === true, 'After sending, the thread is answered');
    check($repo->findDraft($draftId) === null && (int) $pdo->query('SELECT COUNT(*) FROM mailbox_attachments WHERE draft_id IS NOT NULL')->fetchColumn() === 0, 'Draft removed after sending');
    check((int) $pdo->query("SELECT COUNT(*) FROM mailbox_attachments WHERE message_id = {$r['message_id']}")->fetchColumn() === 1, 'Attachment moved to the sent message');

    // The same thread again: somebody else already answered -> nothing is sent without confirmation.
    $calls = count($mailers);
    $second = $sender->send(['draft_id' => null] + $base);
    check($second['status'] === 'conflict' && $second['reasons'] === ['already_replied'] && count($mailers) === $calls, 'Second reply is blocked, SMTP untouched');
    check($second['outbound']['author'] === 'Petr' && str_contains($second['outbound']['excerpt'], 'děkuji'), 'Conflict response carries the earlier reply for the modal');
    $wrong = $sender->send(['draft_id' => null, 'confirm_signature' => 'deadbeefdeadbeef'] + $base);
    check($wrong['status'] === 'conflict' && count($mailers) === $calls, 'A wrong confirmation token does not unlock sending');
    $confirmed = $sender->send(['draft_id' => null, 'confirm_signature' => $second['signature'], 'body' => 'Druhá odpověď, vědomě.'] + $base);
    check($confirmed['status'] === 'sent' && count($mailers) === $calls + 1, 'Confirming the exact warned state sends');
    $third = $sender->send(['draft_id' => null, 'confirm_signature' => $second['signature']] + $base);
    check($third['status'] === 'conflict' && $third['signature'] !== $second['signature'], 'A confirmation never covers a later change (new signature needed)');

    // New inbound mail after the form was opened, thread otherwise unanswered.
    $in3 = raw(['From' => 'Jana <jana@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Re: Svatba 2027', 'Message-ID' => '<c3@client.cz>', 'In-Reply-To' => '<o1@example.test>', 'Date' => gmdate('r')], 'Změna termínu!');
    $sync->importRaw($in3, 'in', 'INBOX', 7);
    $knownOld = $repo->replyState($tid)['last_inbound_id'];
    $in4 = raw(['From' => 'Jana <jana@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Re: Svatba 2027', 'Message-ID' => '<c4@client.cz>', 'In-Reply-To' => '<c3@client.cz>', 'Date' => gmdate('r', time() + 5)], 'Ještě jedna věc.');
    $sync->importRaw($in4, 'in', 'INBOX', 8);
    $newInbound = $sender->send(['draft_id' => null, 'known_inbound_id' => $knownOld] + $base);
    check($newInbound['status'] === 'conflict' && $newInbound['reasons'] === ['new_inbound'], 'New client mail since the form was opened triggers a warning: ' . json_encode($newInbound['reasons'] ?? $newInbound));
    $ok = $sender->send(['draft_id' => null, 'known_inbound_id' => $repo->replyState($tid)['last_inbound_id']] + $base);
    check($ok['status'] === 'sent', 'Up-to-date form on an open thread sends without any prompt');

    // In-flight send by someone else counts as an answer.
    $in5 = raw(['From' => 'Jana <jana@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Re: Svatba 2027', 'Message-ID' => '<c5@client.cz>', 'In-Reply-To' => '<c4@client.cz>', 'Date' => gmdate('r', time() + 20)], 'Dotaz 5.');
    $sync->importRaw($in5, 'in', 'INBOX', 9);
    $known = $repo->replyState($tid)['last_inbound_id'];
    $repo->insertOutbound($tid, ['message_id' => '<inflight@example.test>', 'in_reply_to' => '', 'refs' => '', 'from_addr' => 'team@example.test', 'from_name' => 'T', 'to' => 'jana@client.cz', 'cc' => '', 'bcc' => '', 'subject' => 'Re: Svatba 2027', 'body' => 'Kolega právě odpovídá', 'has_attachments' => false, 'author_label' => 'Marie']);
    $busy = $sender->send(['draft_id' => null, 'known_inbound_id' => $known] + $base);
    check($busy['status'] === 'conflict' && $busy['outbound']['in_progress'] === true && $busy['outbound']['author'] === 'Marie', 'A reply that is still being sent blocks a second one');
    $pdo->exec("DELETE FROM mailbox_messages WHERE message_id = '<inflight@example.test>'");
    $repo->refreshThread($tid);

    // SMTP rejection: nothing counted as an answer, draft kept for retry.
    $chan2 = $channel;
    $channel->dataAccepted = false;
    $failDraft = $repo->saveDraft(null, null, ['to' => 'x@client.cz', 'cc' => '', 'bcc' => '', 'subject' => 'Nová', 'body' => 'Text', 'author_label' => ''], null);
    $failed = $sender->send(['thread_id' => null, 'draft_id' => $failDraft['id'], 'to' => 'x@client.cz', 'cc' => '', 'bcc' => '', 'subject' => 'Nová', 'body' => 'Text']);
    check($failed['status'] === 'failed', 'SMTP rejection is reported');
    check($repo->findDraft($failDraft['id']) !== null, 'Draft survives a failed send');
    check((int) $pdo->query("SELECT COUNT(*) FROM mailbox_messages WHERE subject = 'Nová'")->fetchColumn() === 0 && (int) $pdo->query("SELECT COUNT(*) FROM mailbox_threads WHERE subject = 'Nová'")->fetchColumn() === 0, 'Failed send leaves no phantom message or thread');
    $channel->dataAccepted = true;

    // A brand new message creates its own thread.
    $fresh = $sender->send(['thread_id' => null, 'draft_id' => $failDraft['id'], 'to' => 'x@client.cz', 'cc' => '', 'bcc' => '', 'subject' => 'Nová', 'body' => 'Text', 'author_label' => 'Eva']);
    check($fresh['status'] === 'sent' && (int) $pdo->query("SELECT COUNT(*) FROM mailbox_threads WHERE subject = 'Nová'")->fetchColumn() === 1, 'New message starts a new thread');

    // Validation.
    foreach ([['to' => ''], ['to' => 'bad'], ['subject' => ''], ['subject' => "A\r\nBcc: x@y.cz"], ['body' => "  \n"], ['thread_id' => 999999]] as $patch) {
        try {
            $sender->send(array_merge(['thread_id' => null, 'draft_id' => null, 'to' => 'a@client.cz', 'cc' => '', 'bcc' => '', 'subject' => 'S', 'body' => 'B'], $patch));
            throw new LogicException('Expected validation failure for ' . json_encode($patch));
        } catch (MailboxValidationException $e) {
            check(true, '');
        }
    }

    // ---------------------------------------------------------------- IMAP sync end to end
    $pdo->exec('DELETE FROM mailbox_attachments'); $pdo->exec('DELETE FROM mailbox_messages'); $pdo->exec('DELETE FROM mailbox_threads');
    $mailA = raw(['From' => 'A <a@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Dotaz A', 'Message-ID' => '<sa@client.cz>', 'Date' => gmdate('r', time() - 100)], 'Text A');
    $mailB = raw(['From' => 'B <b@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Dotaz B', 'Message-ID' => '<sb@client.cz>', 'Date' => gmdate('r', time() - 50)], 'Text B');
    $sentX = raw(['From' => 'team@example.test', 'To' => 'a@client.cz', 'Subject' => 'Re: Dotaz A', 'Message-ID' => '<sx@example.test>', 'In-Reply-To' => '<sa@client.cz>', 'Date' => gmdate('r', time() - 40)], 'Odpověď A');
    $folders = ['INBOX' => [11 => $mailA, 12 => $mailB], 'Sent' => [3 => $sentX]];
    $uidValidity = 500;
    $selected = 'INBOX';
    $fetched = [];
    $makeClient = function () use (&$folders, &$uidValidity, &$selected, &$fetched): ImapClient {
        $transport = new FakeMailTransport("* OK ready\r\n", function (string $data) use (&$folders, &$uidValidity, &$selected, &$fetched): string {
            if (!preg_match('/^(A\d+) (.*)\r\n$/s', $data, $m)) { return ''; }
            [$tag, $cmd] = [$m[1], $m[2]];
            if (str_starts_with($cmd, 'LOGIN') || str_starts_with($cmd, 'LOGOUT')) { return "{$tag} OK\r\n"; }
            if (preg_match('/^LIST/', $cmd)) { return "* LIST (\\Sent) \"/\" \"Sent\"\r\n* LIST () \"/\" \"INBOX\"\r\n{$tag} OK\r\n"; }
            if (preg_match('/^SELECT "([^"]+)"/', $cmd, $f)) { $selected = $f[1]; return "* " . count($folders[$selected]) . " EXISTS\r\n* OK [UIDVALIDITY {$uidValidity}]\r\n{$tag} OK\r\n"; }
            if (preg_match('/^UID SEARCH UID (\d+):\*/', $cmd, $s)) {
                $uids = array_keys($folders[$selected]); sort($uids);
                $hit = array_filter($uids, static fn($u) => $u >= (int) $s[1]);
                if ($hit === [] && $uids !== []) { $hit = [end($uids)]; }
                return '* SEARCH ' . implode(' ', $hit) . "\r\n{$tag} OK\r\n";
            }
            if (str_starts_with($cmd, 'UID SEARCH SINCE')) { return '* SEARCH ' . implode(' ', array_keys($folders[$selected])) . "\r\n{$tag} OK\r\n"; }
            if (preg_match('/^UID FETCH (\d+) \(RFC822\.SIZE\)/', $cmd, $u)) {
                $size = $GLOBALS['fakeSizes'][$selected . ':' . $u[1]] ?? strlen($folders[$selected][(int) $u[1]]);
                return '* 1 FETCH (UID ' . $u[1] . ' RFC822.SIZE ' . $size . ")\r\n{$tag} OK\r\n";
            }
            if (preg_match('/^UID FETCH (\d+) \(BODY\.PEEK\[HEADER\]\)/', $cmd, $u)) {
                $raw = $folders[$selected][(int) $u[1]];
                $header = substr($raw, 0, (int) strpos($raw, "\r\n\r\n")) . "\r\n\r\n";
                return '* 1 FETCH (UID ' . $u[1] . ' BODY[HEADER] {' . strlen($header) . "}\r\n" . $header . ")\r\n{$tag} OK\r\n";
            }
            if (preg_match('/^UID FETCH (\d+)/', $cmd, $u)) {
                if (in_array($selected . ':' . $u[1], $GLOBALS['fakeBroken'] ?? [], true)) { return "{$tag} NO broken\r\n"; }
                $fetched[] = $selected . ':' . $u[1];
                $raw = $folders[$selected][(int) $u[1]];
                $internal = $GLOBALS['fakeInternal'][$selected . ':' . $u[1]] ?? null;
                $meta = $internal ? ' INTERNALDATE "' . $internal . '"' : '';
                return '* 1 FETCH (UID ' . $u[1] . $meta . ' BODY[] {' . strlen($raw) . "}\r\n" . $raw . ")\r\n{$tag} OK\r\n";
            }
            return "{$tag} BAD\r\n";
        });
        $client = new ImapClient($transport);
        $client->connect('u', 'p');
        return $client;
    };
    $svc = new MailboxSyncService($repo, $store, $makeClient, 'INBOX', '', 1, 30); // batch of 1 message per folder per run
    $res = $svc->sync(10);
    check($res['imported'] === 2 && $res['errors'] === [], 'First run imports one message per folder (batch=1): ' . json_encode($res));
    $res = $svc->sync(10);
    check($res['imported'] === 1, 'Second run continues from the stored UID cursor');
    check($repo->syncState('INBOX')['last_uid'] === 12 && (int) $repo->syncState('INBOX')['uidvalidity'] === 500 && $repo->syncState('INBOX')['last_error'] === null, 'Cursor and UIDVALIDITY persisted');
    $fetchedBefore = count($fetched);
    $res = $svc->sync(10);
    check($res['imported'] === 0 && count($fetched) === $fetchedBefore, 'Nothing new: no message is fetched again');
    check((int) $pdo->query('SELECT COUNT(*) FROM mailbox_threads')->fetchColumn() === 2, 'Sent reply joined the right thread through the full sync');
    $uidValidity = 501; // server renumbered the mailbox
    $res = $svc->sync(10);
    check($res['imported'] === 0 && (int) $pdo->query('SELECT COUNT(*) FROM mailbox_messages')->fetchColumn() === 3, 'UIDVALIDITY change re-scans but Message-ID de-duplication prevents duplicates');
    // Single-flight: another connection holding the lock makes the run skip instead of double-importing.
    $other = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s', Env::get('DB_HOST'), Env::get('DB_PORT', '3306'), $name), Env::get('DB_USER'), Env::get('DB_PASS'));
    $other->query("SELECT GET_LOCK('rbvp_mailbox_sync', 0)")->fetchColumn();
    check($svc->sync(10)['locked'] === true, 'A concurrent sync is refused by the named lock');
    $other->query("SELECT RELEASE_LOCK('rbvp_mailbox_sync')")->fetchColumn();
    // Connection failure is recorded and visible.
    $cursorBefore = $repo->syncState('INBOX')['last_uid'];
    $broken = new MailboxSyncService($repo, $store, static fn() => throw new \App\Services\Mailbox\ImapException('imap_connect'));
    $res = $broken->sync(5);
    check($res['errors'] === ['imap_connect'] && $repo->syncState('INBOX')['last_error'] === 'imap_connect', 'IMAP outage is stored for the UI banner');
    check($repo->syncState('INBOX')['last_uid'] === $cursorBefore, 'An outage must not move the cursor');

    // ---------------------------------------------------------------- regressions found in review
    $GLOBALS['fakeSizes'] = []; $GLOBALS['fakeBroken'] = []; $GLOBALS['fakeInternal'] = [];
    $reset = static function () use ($pdo, &$folders): void {
        $pdo->exec('DELETE FROM mailbox_attachments'); $pdo->exec('DELETE FROM mailbox_messages'); $pdo->exec('DELETE FROM mailbox_threads'); $pdo->exec('DELETE FROM mailbox_sync_state');
    };

    // (1) Client clocks must not decide "answered": ordering uses the server-trusted arrival time (INTERNALDATE).
    $reset();
    $folders = ['INBOX' => [1 => raw(['From' => 'Pozdní <late@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Hodiny', 'Message-ID' => '<k1@client.cz>', 'Date' => gmdate('r', time() + 900)], 'Moje hodiny předcházejí.')], 'Sent' => []];
    $GLOBALS['fakeInternal'] = ['INBOX:1' => gmdate('d-M-Y H:i:s', time() - 120) . ' +0000'];
    $svcSkew = new MailboxSyncService($repo, $store, $makeClient, 'INBOX', '', 50, 30, 15_000_000, 'team@example.test');
    $svcSkew->sync(10);
    $skewTid = (int) $pdo->query('SELECT id FROM mailbox_threads')->fetchColumn();
    $skewSender = new MailboxSender($repo, $store, $factory);
    $skewSent = $skewSender->send(['thread_id' => $skewTid, 'draft_id' => null, 'to' => 'late@client.cz', 'cc' => '', 'bcc' => '', 'subject' => 'Re: Hodiny', 'body' => 'Odpověď.', 'known_inbound_id' => $repo->replyState($skewTid)['last_inbound_id']]);
    check($skewSent['status'] === 'sent' && $repo->replyState($skewTid)['answered'] === true, 'Client clock 15 min ahead must not leave the thread unanswered');
    $folders['INBOX'][2] = raw(['From' => 'Pozdní <late@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Re: Hodiny', 'Message-ID' => '<k2@client.cz>', 'Date' => gmdate('r', time() - 3600)], 'Dotaz po odpovědi, ale mé hodiny jdou pozadu.');
    $GLOBALS['fakeInternal']['INBOX:2'] = gmdate('d-M-Y H:i:s', time() + 2) . ' +0000';
    $svcSkew->sync(10);
    check($repo->replyState($skewTid)['answered'] === false && MailboxRepository::isAwaiting($repo->findThread($skewTid)), 'A follow-up from a client whose clock is behind must re-open the thread');

    // (2) UIDVALIDITY change: new mail must not collide with stored (folder, uid) pairs.
    $reset();
    $uidValidity = 700;
    $folders = ['INBOX' => [7 => raw(['From' => 'A <a@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Stará', 'Message-ID' => '<u1@client.cz>', 'Date' => gmdate('r', time() - 500)], 'Stará.')], 'Sent' => []];
    $svcUid = new MailboxSyncService($repo, $store, $makeClient, 'INBOX', '', 50, 30);
    $svcUid->sync(10);
    $uidValidity = 701; // mailbox restored: UID 7 now belongs to a different message
    $folders['INBOX'] = [7 => raw(['From' => 'B <b@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Nová po obnově', 'Message-ID' => '<u2@client.cz>', 'Date' => gmdate('r', time() - 100)], 'Nová.')];
    $res = $svcUid->sync(10);
    check($res['errors'] === [] && (int) $pdo->query("SELECT COUNT(*) FROM mailbox_messages WHERE message_id = '<u2@client.cz>'")->fetchColumn() === 1, 'New mail that reuses an old UID after UIDVALIDITY change is imported, not lost');
    check((int) $pdo->query('SELECT COUNT(*) FROM mailbox_threads WHERE message_count = 0')->fetchColumn() === 0, 'No orphan empty threads');
    $uidValidity = 500;

    // (3) Oversized and unreadable messages become visible placeholders instead of wedging or silently vanishing.
    $reset();
    $folders = ['INBOX' => [
        1 => raw(['From' => 'Fotograf <foto@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Velké video', 'Message-ID' => '<big@client.cz>', 'Date' => gmdate('r', time() - 60)], 'x'),
        2 => raw(['From' => 'Rozbitý <broken@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Rozbitá zpráva', 'Message-ID' => '<brk@client.cz>', 'Date' => gmdate('r', time() - 50)], 'y'),
        3 => raw(['From' => 'OK <ok@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Normální', 'Message-ID' => '<okk@client.cz>', 'Date' => gmdate('r', time() - 40)], 'z'),
    ], 'Sent' => []];
    $GLOBALS['fakeSizes'] = ['INBOX:1' => 90_000_000];
    $GLOBALS['fakeBroken'] = ['INBOX:2'];
    $svcBig = new MailboxSyncService($repo, $store, $makeClient, 'INBOX', '', 50, 30, 15_000_000);
    $res = $svcBig->sync(10);
    $big = $pdo->query("SELECT * FROM mailbox_messages WHERE message_id = '<big@client.cz>'")->fetch();
    check($big && str_contains($big['body_text'], 'příliš velká') && $big['from_addr'] === 'foto@client.cz' && $big['subject'] === 'Velké video', 'Oversized mail is kept as a placeholder with its real sender and subject');
    $brk = $pdo->query("SELECT * FROM mailbox_messages WHERE message_id = '<brk@client.cz>'")->fetch();
    check($brk && str_contains($brk['body_text'], 'webmailu'), 'A message the server cannot deliver becomes a placeholder');
    check((int) $pdo->query("SELECT COUNT(*) FROM mailbox_messages WHERE message_id = '<okk@client.cz>'")->fetchColumn() === 1 && $repo->syncState('INBOX')['last_uid'] === 3, 'The messages after a bad one are still imported');
    check(!str_starts_with((string) $repo->syncState('INBOX')['last_error'], 'processing:'), 'The crash marker is cleared after a normal run');
    // A run that died mid-import leaves the marker; the next run turns that UID into a placeholder and moves on.
    $GLOBALS['fakeSizes'] = []; $GLOBALS['fakeBroken'] = [];
    $folders['INBOX'][4] = raw(['From' => 'Pád <crash@client.cz>', 'To' => 'team@example.test', 'Subject' => 'Shodila PHP', 'Message-ID' => '<crash@client.cz>', 'Date' => gmdate('r', time() - 30)], 'w');
    $repo->saveSyncState('INBOX', 500, 4, 'processing:4');
    $svcBig->sync(10);
    check($pdo->query("SELECT body_text FROM mailbox_messages WHERE message_id = '<crash@client.cz>'")->fetchColumn() !== false && $repo->syncState('INBOX')['last_error'] === 'import_crashed', 'A UID that crashed the previous run is placeholdered and reported');

    // (4) Contact-form mails (From = our own address) from different people must not share a thread.
    $reset();
    $own = 'team@example.test';
    $svcForm = new MailboxSyncService($repo, $store, $makeClient, 'INBOX', '', 50, 30, 15_000_000, $own);
    $form = static fn(string $id, string $contact) => raw(['From' => $own, 'To' => $own, 'Subject' => 'Nová zpráva z webu - Jana', 'Message-ID' => $id, 'Date' => gmdate('r', time() - 10), 'Content-Type' => 'text/plain; charset=utf-8'], "Jméno: Jana\nKontakt: {$contact}\nZpráva:\nAhoj");
    $svcForm->importRaw($form('<f1@site>', 'jana1@client.cz'), 'in', 'INBOX', 1);
    $svcForm->importRaw($form('<f2@site>', 'jana2@client.cz'), 'in', 'INBOX', 2);
    $svcForm->importRaw($form('<f3@site>', '+420 123 456 789'), 'in', 'INBOX', 3);
    $svcForm->importRaw($form('<f4@site>', '+420 999 999 999'), 'in', 'INBOX', 4);
    check((int) $pdo->query('SELECT COUNT(*) FROM mailbox_threads')->fetchColumn() === 4, 'Different people with the same first name stay in separate threads');

    // (5) A crashed 'sending' row stops counting as an answer after the TTL.
    $reset();
    $svcForm->importRaw(raw(['From' => 'K <k@client.cz>', 'To' => $own, 'Subject' => 'Visí', 'Message-ID' => '<h1@client.cz>', 'Date' => gmdate('r', time() - 5000)], 'Dotaz.'), 'in', 'INBOX', 1, new DateTimeImmutable('-2 hours'));
    $hangTid = (int) $pdo->query('SELECT id FROM mailbox_threads')->fetchColumn();
    $ins = $repo->insertOutbound($hangTid, ['message_id' => '<hang@example.test>', 'in_reply_to' => '', 'refs' => '', 'from_addr' => $own, 'from_name' => 'T', 'to' => 'k@client.cz', 'cc' => '', 'bcc' => '', 'subject' => 'Re: Visí', 'body' => 'x', 'has_attachments' => false, 'author_label' => 'Zombie']);
    check($repo->replyState($hangTid)['answered'] === true, 'A fresh in-flight send counts as an answer');
    $pdo->exec("UPDATE mailbox_messages SET created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 11 MINUTE) WHERE id = {$ins['message_id']}");
    check($repo->replyState($hangTid)['answered'] === false, 'A send that never finished expires and no longer blocks replying');

    // (6) Threads started by us are neutral (no "already answered" banner) and follow-ups go to the same recipients.
    $reset();
    $own1 = $sender->send(['thread_id' => null, 'draft_id' => null, 'to' => 'novy@client.cz', 'cc' => '', 'bcc' => '', 'subject' => 'Nabídka', 'body' => 'Dobrý den.']);
    $ownTid = $own1['thread_id'];
    check($repo->replyState($ownTid)['answered'] === false, 'A thread without inbound mail is not "answered"');
    $followUp = $sender->send(['thread_id' => $ownTid, 'draft_id' => null, 'to' => 'novy@client.cz', 'cc' => '', 'bcc' => '', 'subject' => 'Re: Nabídka', 'body' => 'Ještě doplnění.']);
    check($followUp['status'] === 'sent', 'Follow-up on our own thread needs no confirmation');
    $outMsg = $repo->findMessage($own1['message_id']);
    check(App\Services\Mailbox\MailboxView::replyRecipients($outMsg, $own, false)['to'] === 'novy@client.cz', 'Reply form for an outbound-only thread is addressed to the earlier recipients');

    // (7) Long References stay below the RFC 2047 threshold so threading survives at the recipient.
    $reset();
    $ids = [];
    for ($i = 0; $i < 30; $i++) { $ids[] = '<CAHk-' . str_repeat('x', 60) . $i . '@mail.gmail.example>'; }
    $svcForm->importRaw(raw(['From' => 'L <l@client.cz>', 'To' => $own, 'Subject' => 'Dlouhé vlákno', 'Message-ID' => '<long9@client.cz>', 'References' => implode(' ', $ids), 'Date' => gmdate('r', time() - 5)], 'Dotaz.'), 'in', 'INBOX', 1);
    $longTid = (int) $pdo->query('SELECT id FROM mailbox_threads')->fetchColumn();
    $sender->send(['thread_id' => $longTid, 'draft_id' => null, 'to' => 'l@client.cz', 'cc' => '', 'bcc' => '', 'subject' => 'Re: Dlouhé vlákno', 'body' => 'Odpověď.', 'known_inbound_id' => $repo->replyState($longTid)['last_inbound_id']]);
    $mime = preg_replace("/\r\n[ \t]+/", ' ', $channel->events['data']);
    check(preg_match('/^References: (.+)$/m', $mime, $rm) === 1 && !str_contains($rm[1], '=?') && strlen($rm[1]) < 1000 && str_contains($rm[1], '<long9@client.cz>') && str_contains($rm[1], $ids[0]), 'References is trimmed (root + newest) instead of being RFC 2047 encoded');

    // (8) Legacy raw 8-bit headers are converted to UTF-8.
    $legacy = "From: =?ISO-8859-2?Q?Jan?= <j@client.cz>\r\nSubject: P\x9Aili\x9D\r\nMessage-ID: <leg@client.cz>\r\n\r\nx";
    check(MimeParser::parse($legacy)['subject'] === 'Pšiliť', 'Raw Windows-1250 header bytes become valid UTF-8: ' . MimeParser::parse($legacy)['subject']);

    // ---------------------------------------------------------------- templates
    $tpl = $repo->saveTemplate(null, 'Děkujeme', 'Děkujeme za zprávu.');
    check($repo->findTemplate($tpl)['name'] === 'Děkujeme', 'Template saved');
    $repo->deleteTemplate($tpl);
    check($repo->findTemplate($tpl) === null, 'Template deleted');

    echo "Mailbox integration: {$checks} checks passed\n";
} catch (Throwable $e) {
    echo 'FAIL: ' . $e->getMessage() . "\n  at " . $e->getFile() . ':' . $e->getLine() . "\n";
    $failed = true;
} finally {
    $server->exec('DROP DATABASE IF EXISTS `' . $name . '`');
    if (is_dir($storage)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storage, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        @rmdir($storage);
    }
}
exit(empty($failed) ? 0 : 1);
