<?php
declare(strict_types=1);

use App\Repositories\MailboxRepository;
use App\Services\Mailbox\AttachmentStore;
use App\Services\Mailbox\ImapClient;
use App\Services\Mailbox\ImapException;
use App\Services\Mailbox\MailHtmlSanitizer;
use App\Services\Mailbox\MailTransport;
use App\Services\Mailbox\MailboxSender;
use App\Services\Mailbox\MailboxValidationException;
use App\Services\Mailbox\MailboxView;
use App\Services\Mailbox\MimeParser;

/** Scripted IMAP server: the responder receives each client write and returns the raw bytes to feed back. */
final class FakeMailTransport implements MailTransport
{
    public array $writes = [];
    private string $in;
    public bool $closed = false;

    public function __construct(string $greeting, private \Closure $responder)
    {
        $this->in = $greeting;
    }

    public function readLine(): ?string
    {
        $pos = strpos($this->in, "\r\n");
        if ($pos === false) {
            return null;
        }
        $line = substr($this->in, 0, $pos);
        $this->in = substr($this->in, $pos + 2);
        return $line;
    }

    public function readBytes(int $length): ?string
    {
        if (strlen($this->in) < $length) {
            return null;
        }
        $data = substr($this->in, 0, $length);
        $this->in = substr($this->in, $length);
        return $data;
    }

    public function write(string $data): void
    {
        $this->writes[] = $data;
        $this->in .= ($this->responder)($data);
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

function register_mailbox_tests(callable $test, callable $assert): void
{
    $crlf = static fn(string $s): string => str_replace("\n", "\r\n", $s);

    // ------------------------------------------------------------------ MIME parsing

    $test('mailbox MIME: decodes RFC 2047 subject, names, charsets and threading headers', function () use ($assert): void {
        $raw = "From: =?UTF-8?B?Sm9zZWYgTm92w6Fr?= <Josef@Example.cz>\r\n"
            . "To: \"Team, RB\" <team@rbvideosphotos.cz>, second@example.cz\r\n"
            . "Cc: =?ISO-8859-2?Q?P=F8=EDli=B9_=BElu=BBou=E8k=FD?= <cc@example.cz>\r\n"
            . "Reply-To: Klient <klient@example.cz>\r\n"
            . "Subject: =?UTF-8?Q?Pozn=C3=A1mka_=C4=8Desky?=\r\n"
            . "Message-ID: <abc.123@example.cz>\r\n"
            . "In-Reply-To: <prev@example.cz>\r\n"
            . "References: <root@example.cz> <prev@example.cz>\r\n"
            . "Date: Tue, 20 Oct 2026 10:15:00 +0200\r\n"
            . "Content-Type: text/plain; charset=windows-1250\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . "P=F8=EDli=9A =9Elu=9Dou=E8k=FD k=F9=F2\r\n";
        $p = MimeParser::parse($raw);
        $assert($p['from'] === ['name' => 'Josef Novák', 'addr' => 'josef@example.cz'], 'From: ' . json_encode($p['from']));
        $assert($p['to'][0] === ['name' => 'Team, RB', 'addr' => 'team@rbvideosphotos.cz'] && $p['to'][1]['addr'] === 'second@example.cz', 'To list');
        $assert($p['cc'][0]['name'] === 'Příliš žluťoučký', 'Cc name charset: ' . $p['cc'][0]['name']);
        $assert($p['reply_to'][0]['addr'] === 'klient@example.cz');
        $assert($p['subject'] === 'Poznámka česky', 'Subject: ' . $p['subject']);
        $assert($p['message_id'] === '<abc.123@example.cz>' && $p['in_reply_to'] === '<prev@example.cz>');
        $assert($p['references'] === ['<root@example.cz>', '<prev@example.cz>']);
        $assert($p['date']->format('Y-m-d H:i') === '2026-10-20 08:15', 'Date is converted to UTC.');
        $assert(str_contains($p['text'], 'Příliš žluťoučký kůň'), 'Body charset: ' . $p['text']);
    });

    $test('mailbox MIME: multipart/alternative with attachment, inline image and RFC 2231 filename', function () use ($assert): void {
        $png = base64_encode("\x89PNG\r\n\x1a\nfake");
        $file = base64_encode("PDFDATA\x00\x01binary");
        $raw = "From: a@example.cz\r\nTo: team@example.cz\r\nSubject: S\r\nMessage-ID: <m1@x>\r\nMIME-Version: 1.0\r\n"
            . "Content-Type: multipart/mixed; boundary=\"MIX\"\r\n\r\n"
            . "--MIX\r\nContent-Type: multipart/alternative; boundary=\"ALT\"\r\n\r\n"
            . "--ALT\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nPlain část\r\n"
            . "--ALT\r\nContent-Type: multipart/related; boundary=\"REL\"\r\n\r\n"
            . "--REL\r\nContent-Type: text/html; charset=utf-8\r\n\r\n<p>HTML <img src=\"cid:logo@x\"></p>\r\n"
            . "--REL\r\nContent-Type: image/png\r\nContent-ID: <logo@x>\r\nContent-Disposition: inline\r\nContent-Transfer-Encoding: base64\r\n\r\n{$png}\r\n--REL--\r\n"
            . "--ALT--\r\n"
            . "--MIX\r\nContent-Type: application/pdf; name=\"x.pdf\"\r\nContent-Disposition: attachment; filename*=UTF-8''Nab%C3%ADdka%20z%C3%A1%C5%99%C3%AD.pdf\r\nContent-Transfer-Encoding: base64\r\n\r\n{$file}\r\n"
            . "--MIX--\r\n";
        $p = MimeParser::parse($raw);
        $assert(trim($p['text']) === 'Plain část', 'Plain: ' . $p['text']);
        $assert(str_contains($p['html'], 'cid:logo@x'));
        $assert(count($p['attachments']) === 2, 'attachments: ' . count($p['attachments']));
        $logo = $p['attachments'][0];
        $assert($logo['content_id'] === 'logo@x' && $logo['inline'] === true && str_starts_with($logo['content'], "\x89PNG"));
        $pdf = $p['attachments'][1];
        $assert($pdf['filename'] === 'Nabídka září.pdf', 'RFC 2231 name: ' . $pdf['filename']);
        $assert($pdf['content'] === "PDFDATA\x00\x01binary" && $pdf['inline'] === false);
    });

    $test('mailbox MIME: missing Message-ID gets a stable synthetic id; future dates are clamped; HTML-only mail gets text', function () use ($assert): void {
        $raw = "From: a@example.cz\r\nDate: Fri, 01 Jan 2100 00:00:00 +0000\r\nContent-Type: text/html; charset=utf-8\r\n\r\n<div>Ahoj<br>světe</div>";
        $a = MimeParser::parse($raw);
        $b = MimeParser::parse($raw);
        $assert($a['message_id'] === $b['message_id'] && str_starts_with($a['message_id'], '<generated-'));
        $assert($a['date'] <= new DateTimeImmutable('+1 day'), 'Future date must be clamped.');
        $assert(str_contains($a['text'], 'Ahoj') && str_contains($a['text'], 'světe'));
    });

    $test('mailbox MIME: hostile nesting and part floods stay bounded', function () use ($assert): void {
        $body = '';
        for ($i = 0; $i < 400; $i++) {
            $body .= "--B\r\nContent-Type: text/plain\r\n\r\npart{$i}\r\n";
        }
        $raw = "From: a@example.cz\r\nContent-Type: multipart/mixed; boundary=B\r\n\r\n" . $body . "--B--\r\n";
        $p = MimeParser::parse($raw);
        $assert(substr_count($p['text'], 'part') <= 120, 'Part count must be capped.');
        $assert(MimeParser::parseAddressList('') === [] && MimeParser::parseAddressList('<>, ,') === []);
    });

    // ------------------------------------------------------------------ HTML sanitizer

    $test('mailbox sanitizer: strips scripts, handlers, javascript: URLs, css url(), iframes and forms', function () use ($assert): void {
        $html = '<p onclick="x()" style="color:red;background:url(http://evil/x);position:fixed">Ahoj <b>světe</b></p>'
            . '<script>alert(1)</script><iframe src="http://evil"></iframe><form action="/x"><input name="a"></form>'
            . '<a href="javascript:alert(1)">bad</a><a href=" jav&#x09;ascript:alert(1)">bad2</a><a href="https://ok.example/p">ok</a>'
            . '<svg onload="x()"><circle/></svg><object data="x"></object><style>body{display:none}</style><!-- c -->'
            . '<img src="x" onerror="alert(1)"><div style="expression(alert(1))">e</div>';
        $out = MailHtmlSanitizer::sanitize($html);
        foreach (['<script', 'onclick', 'onerror', 'onload', 'javascript', 'url(', '<iframe', '<form', '<input', '<svg', '<object', '<style', 'position', 'expression', '<!--'] as $needle) {
            $assert(stripos($out, $needle) === false, "Sanitized output still contains {$needle}: {$out}");
        }
        $assert(str_contains($out, '<b>světe</b>') && str_contains($out, 'color:red'), 'Safe formatting and Czech text must survive: ' . $out);
        $assert(str_contains($out, 'href="https://ok.example/p"') && str_contains($out, 'rel="noopener noreferrer nofollow"') && str_contains($out, 'target="_blank"'));
    });

    $test('mailbox sanitizer: remote images blocked unless allowed, cid images mapped to the attachment URL', function () use ($assert): void {
        $html = '<img src="https://tracker.example/p.gif"><img src="cid:logo@x"><img src="data:image/svg+xml;base64,PHN2Zz4=">';
        $blocked = MailHtmlSanitizer::sanitize($html, ['logo@x' => '/admin/mail/attachment?id=7&inline=1']);
        $assert(!str_contains($blocked, 'tracker.example') && !str_contains($blocked, 'data:'), 'Remote and data: images must be blocked: ' . $blocked);
        $assert(str_contains($blocked, '/admin/mail/attachment?id=7&amp;inline=1') || str_contains($blocked, '/admin/mail/attachment?id=7&inline=1'), 'cid image must be mapped: ' . $blocked);
        $allowed = MailHtmlSanitizer::sanitize('<img src="https://tracker.example/p.gif">', [], true);
        $assert(str_contains($allowed, 'https://tracker.example/p.gif') && str_contains($allowed, 'no-referrer'));
    });

    // ------------------------------------------------------------------ IMAP client

    $imapServer = static function (array &$log) use ($crlf): FakeMailTransport {
        return new FakeMailTransport("* OK IMAP ready\r\n", function (string $data) use (&$log, $crlf): string {
            $log[] = $data;
            if (!preg_match('/^(A\d+) (.*)\r\n$/s', $data, $m)) {
                return '';
            }
            [$tag, $cmd] = [$m[1], $m[2]];
            if (str_starts_with($cmd, 'LOGIN')) {
                return str_contains($cmd, 'wrong') ? "{$tag} NO [AUTHENTICATIONFAILED] bad\r\n" : "{$tag} OK logged in\r\n";
            }
            if (str_starts_with($cmd, 'SELECT')) {
                return "* 3 EXISTS\r\n* OK [UIDVALIDITY 777] ok\r\n{$tag} OK [READ-WRITE] done\r\n";
            }
            if (str_starts_with($cmd, 'UID SEARCH')) {
                return "* SEARCH 4 9 12\r\n{$tag} OK done\r\n";
            }
            if (str_starts_with($cmd, 'UID FETCH 9')) {
                $raw = "From: a@example.cz\r\nSubject: T\r\n\r\nTělo {literal} zprávy\r\n";
                return '* 2 FETCH (UID 9 BODY[] {' . strlen($raw) . "}\r\n" . $raw . ")\r\n{$tag} OK done\r\n";
            }
            if (str_starts_with($cmd, 'LIST')) {
                return "* LIST (\\HasNoChildren) \"/\" \"INBOX\"\r\n* LIST (\\HasNoChildren \\Sent) \"/\" \"Odeslan\\\"e\"\r\n{$tag} OK done\r\n";
            }
            if (str_starts_with($cmd, 'APPEND')) {
                return "+ Ready\r\n";
            }
            if (str_starts_with($cmd, 'LOGOUT')) {
                return "* BYE\r\n{$tag} OK bye\r\n";
            }
            return "{$tag} BAD unknown\r\n";
        });
    };

    $test('mailbox IMAP: login, select, UID search filtering, literal fetch, LIST special-use, logout', function () use ($assert, $imapServer): void {
        $log = [];
        $transport = $imapServer($log);
        $client = new ImapClient($transport);
        $client->connect('team@example.cz', 'p"ass\\word');
        $assert(str_contains($log[0], 'LOGIN "team@example.cz" "p\\"ass\\\\word"'), 'Credentials must be quoted/escaped: ' . $log[0]);
        $sel = $client->select('INBOX');
        $assert($sel === ['uidvalidity' => 777, 'exists' => 3], json_encode($sel));
        $assert($client->uidsAfter(8) === [9, 12], 'Only UIDs above the cursor, ascending.');
        $assert($client->uidsAfter(12) === [], 'The n:* quirk must not re-deliver the last message.');
        $raw = $client->fetchRaw(9);
        $assert(str_contains($raw, 'Tělo {literal} zprávy'), 'Literal content must be returned byte-exact.');
        $assert(str_contains(implode('', $log), 'BODY.PEEK[]'), 'Fetch must not set the \\Seen flag.');
        $folders = $client->listFolders();
        $assert($folders[1]['flags'] === ['\\HasNoChildren', '\\Sent'] && $folders[1]['name'] === 'Odeslan"e', json_encode($folders));
        $client->logout();
        $assert($transport->closed, 'Connection must be closed.');
    });

    $test('mailbox IMAP: failed login is a safe error code; CR/LF injection in arguments is refused', function () use ($assert, $imapServer): void {
        $log = [];
        $client = new ImapClient($imapServer($log));
        try {
            $client->connect('team@example.cz', 'wrong');
            throw new LogicException('Expected login failure');
        } catch (ImapException $e) {
            $assert($e->errorCode === 'imap_login' && !str_contains($e->getMessage(), 'wrong'), 'Error must be a bare code.');
        }
        $log = [];
        $client = new ImapClient($imapServer($log));
        $client->connect('team@example.cz', 'ok');
        try {
            $client->select("INBOX\r\nA9999 DELETE INBOX");
            throw new LogicException('Expected injection refusal');
        } catch (ImapException $e) {
            $assert($e->errorCode === 'imap_invalid_argument');
        }
        $assert(!str_contains(implode('', $log), 'DELETE'), 'Injected command must never reach the wire.');
    });

    $test('mailbox IMAP: APPEND waits for the continuation and sends a CRLF-normalised literal', function () use ($assert): void {
        $log = [];
        $transport = new FakeMailTransport("* OK ready\r\n", function (string $data) use (&$log): string {
            $log[] = $data;
            if (str_contains($data, ' APPEND ')) {
                return "+ go\r\n";
            }
            if (str_starts_with($data, 'From:')) {
                return "A0002 OK appended\r\n";
            }
            return preg_match('/^(A\d+) LOGIN/', $data, $m) ? "{$m[1]} OK\r\n" : '';
        });
        $client = new ImapClient($transport);
        $client->connect('u@example.cz', 'p');
        $client->append('Sent', "From: a@b.cz\nSubject: x\n\nbody");
        $assert(preg_match('/APPEND "Sent" \(\\\\Seen\) \{(\d+)\}\r\n$/', $log[1], $m) === 1, $log[1]);
        $assert((int) $m[1] === strlen("From: a@b.cz\r\nSubject: x\r\n\r\nbody"), 'Literal size must match the CRLF-normalised message.');
        $assert($log[2] === "From: a@b.cz\r\nSubject: x\r\n\r\nbody\r\n");
    });

    $test('mailbox IMAP: INTERNALDATE, RFC822.SIZE and header-only fetch', function () use ($assert): void {
        $raw = "From: a@b.cz\r\nSubject: T\r\n\r\nBody";
        $transport = new FakeMailTransport("* OK ready\r\n", function (string $data) use ($raw): string {
            if (!preg_match('/^(A\d+) (.*)\r\n$/s', $data, $m)) { return ''; }
            [$tag, $cmd] = [$m[1], $m[2]];
            if (str_starts_with($cmd, 'LOGIN')) { return "{$tag} OK\r\n"; }
            if (str_contains($cmd, 'RFC822.SIZE')) { return "* 1 FETCH (UID 5 RFC822.SIZE 123456)\r\n{$tag} OK\r\n"; }
            if (str_contains($cmd, 'BODY.PEEK[HEADER]')) { $h = "From: a@b.cz\r\n\r\n"; return '* 1 FETCH (UID 5 BODY[HEADER] {' . strlen($h) . "}\r\n{$h})\r\n{$tag} OK\r\n"; }
            if (str_contains($cmd, 'INTERNALDATE')) { return '* 1 FETCH (UID 5 INTERNALDATE "20-Oct-2026 12:30:05 +0200" BODY[] {' . strlen($raw) . "}\r\n{$raw})\r\n{$tag} OK\r\n"; }
            return "{$tag} BAD\r\n";
        });
        $client = new ImapClient($transport);
        $client->connect('u', 'p');
        $msg = $client->fetchMessage(5);
        $assert($msg['raw'] === $raw && $msg['internaldate'] !== null && $msg['internaldate']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') === '2026-10-20 10:30:05', 'INTERNALDATE must be parsed to the right instant.');
        $assert($client->messageSize(5) === 123456);
        $assert($client->fetchHeader(5) === "From: a@b.cz\r\n\r\n");
    });

    // ------------------------------------------------------------------ helpers

    $test('mailbox attachments: dangerous names blocked, path traversal impossible, files stored under random keys', function () use ($assert): void {
        foreach (['invoice.pdf.exe', 'x.PHP', 'a.php.jpg', 'run.bat', '.htaccess', 'evil.js', 'setup.msi'] as $name) {
            $assert(AttachmentStore::isBlockedFilename($name), "{$name} must be blocked.");
        }
        foreach (['nabidka.pdf', 'foto.jpeg', 'smlouva final.docx', 'archive.zip'] as $name) {
            $assert(!AttachmentStore::isBlockedFilename($name), "{$name} must be allowed.");
        }
        $assert(AttachmentStore::safeFilename('../../etc/passwd') === 'passwd');
        $assert(AttachmentStore::safeFilename("a\x00b\"c.pdf") === 'a_b_c.pdf');
        $dir = sys_get_temp_dir() . '/mbx_' . bin2hex(random_bytes(4));
        $store = new AttachmentStore($dir);
        $key = $store->save('obsah');
        $assert(preg_match('/^[a-f0-9]{40}$/', $key) === 1 && file_get_contents($store->path($key)) === 'obsah');
        foreach (['../x', 'abc', str_repeat('g', 40)] as $bad) {
            try {
                $store->path($bad);
                throw new LogicException("Key {$bad} must be rejected.");
            } catch (InvalidArgumentException $e) {
            }
        }
        $store->delete($key);
        $assert(!is_file($store->path($key)));
        @unlink($dir . '/.htaccess');
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $sub) { @rmdir($sub); }
        @rmdir($dir);
    });

    $test('mailbox helpers: subject normalisation, reply subject and reply recipients (incl. contact-form mails)', function () use ($assert): void {
        $assert(MailboxRepository::normalizeSubject('RE: Re[2]: Fwd: Svatba  2027 ') === 'svatba 2027');
        $assert(MailboxView::replySubject('Svatba') === 'Re: Svatba' && MailboxView::replySubject('Re: Svatba') === 'Re: Svatba' && MailboxView::replySubject('Odp: X') === 'Odp: X');
        $own = 'team@rbvideosphotos.cz';
        $client = ['direction' => 'in', 'from_addr' => 'klient@example.cz', 'reply_to' => null, 'to_addrs' => 'Team <team@rbvideosphotos.cz>, kolega@example.cz', 'cc_addrs' => 'jina@example.cz', 'body_text' => 'x'];
        $single = MailboxView::replyRecipients($client, $own, false);
        $assert($single['to'] === 'klient@example.cz' && $single['cc'] === '');
        $all = MailboxView::replyRecipients($client, $own, true);
        $assert($all['cc'] === 'kolega@example.cz, jina@example.cz', 'Reply-all must drop our own address: ' . $all['cc']);
        $replyTo = MailboxView::replyRecipients(array_merge($client, ['reply_to' => 'Jiny <jiny@example.cz>']), $own, false);
        $assert($replyTo['to'] === 'jiny@example.cz', 'Reply-To wins over From.');
        $form = ['direction' => 'in', 'from_addr' => $own, 'reply_to' => null, 'to_addrs' => $own, 'cc_addrs' => '', 'body_text' => "Jméno: Jana\nKontakt: jana@example.cz\nZpráva:\nAhoj"];
        $assert(MailboxView::replyRecipients($form, $own, false)['to'] === 'jana@example.cz', 'Contact-form mail must reply to the e-mail from the Kontakt line.');
        $phone = MailboxView::replyRecipients(array_merge($form, ['body_text' => "Kontakt: +420 123 456 789"]), $own, false);
        $assert($phone['to'] === '' && $phone['note'] !== '', 'Phone-only contact needs a manual recipient and a hint.');
        $assert(str_starts_with(MailboxView::quote(['sent_at' => '2026-10-20 08:00:00', 'from_name' => 'Jan', 'from_addr' => 'j@x.cz', 'body_text' => "a\nb"]), "\n\nDne 20. 10. 2026 10:00 Jan napsal(a):\n> a\n> b"));
    });

    $test('mailbox view: quoted history is split off only when it is a clean trailing block', function () use ($assert): void {
        [$fresh, $quoted] = MailboxView::splitQuoted("Díky!\n\nDne 17. 10. 2026 Jana napsal(a):\n> Ahoj\n>\n> Tým");
        $assert($fresh === 'Díky!' && str_starts_with($quoted, 'Dne 17. 10. 2026 Jana napsal(a):') && str_contains($quoted, '> Tým'), json_encode([$fresh, $quoted]));
        [$fresh, $quoted] = MailboxView::splitQuoted("> otázka 1\nodpověď 1\n> otázka 2\nodpověď 2");
        $assert($quoted === '' && str_contains($fresh, 'odpověď 2'), 'Interleaved quotes must stay inline so no reply text is hidden.');
        $assert(MailboxView::splitQuoted('Bez citace') === ['Bez citace', '']);
    });

    $test('mailbox sender: recipient validation rejects header injection, bad addresses and oversized lists', function () use ($assert): void {
        $assert(MailboxSender::validAddresses('Jan <a@example.cz>, A@example.cz; b@example.cz', 'Komu') === ['a@example.cz', 'b@example.cz'], 'De-duplicated, case-insensitive.');
        foreach (["a@example.cz\r\nBcc: x@evil.cz", 'neni-adresa', 'a@@example.cz'] as $bad) {
            try {
                MailboxSender::validAddresses($bad, 'Komu');
                // "a@example.cz\r\nBcc: x@evil.cz" parses as one bogus address and must fail the e-mail filter.
                throw new LogicException("{$bad} must be rejected.");
            } catch (MailboxValidationException $e) {
            }
        }
    });
}
