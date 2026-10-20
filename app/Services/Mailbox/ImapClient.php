<?php
namespace App\Services\Mailbox;

/**
 * Small IMAP4rev1 client: just what the shared mailbox needs (login, select, uid search/fetch, append, list).
 * Messages are always fetched with BODY.PEEK so reading never changes flags on the server.
 */
final class ImapClient
{
    private const MAX_LITERAL = 40_000_000;

    private int $tagCounter = 0;
    private bool $connected = false;

    public function __construct(private MailTransport $transport) {}

    public function connect(string $username, string $password): void
    {
        $greeting = $this->transport->readLine();
        if ($greeting === null || !preg_match('/^\* (OK|PREAUTH)\b/i', $greeting)) {
            throw new ImapException('imap_greeting');
        }
        $this->connected = true;
        $this->command('LOGIN ' . self::quote($username) . ' ' . self::quote($password), 'imap_login');
    }

    /** @return array{uidvalidity:?int, exists:int} */
    public function select(string $folder): array
    {
        $lines = $this->command('SELECT ' . self::quote($folder), 'imap_select');
        $uidvalidity = null;
        $exists = 0;
        foreach ($lines as $line) {
            if (preg_match('/\[UIDVALIDITY (\d+)\]/i', $line['text'], $m)) {
                $uidvalidity = (int) $m[1];
            }
            if (preg_match('/^\* (\d+) EXISTS/i', $line['text'], $m)) {
                $exists = (int) $m[1];
            }
        }
        return ['uidvalidity' => $uidvalidity, 'exists' => $exists];
    }

    /** @return list<int> ascending UIDs strictly greater than $afterUid */
    public function uidsAfter(int $afterUid): array
    {
        $lines = $this->command('UID SEARCH UID ' . ($afterUid + 1) . ':*', 'imap_search');
        return $this->parseSearch($lines, $afterUid);
    }

    /** @return list<int> UIDs of messages since the given date (used for the first import) */
    public function uidsSince(\DateTimeInterface $since): array
    {
        $lines = $this->command('UID SEARCH SINCE ' . $since->format('j-M-Y'), 'imap_search');
        return $this->parseSearch($lines, 0);
    }

    /** @return list<int> */
    private function parseSearch(array $lines, int $afterUid): array
    {
        $uids = [];
        foreach ($lines as $line) {
            if (preg_match('/^\* SEARCH ?(.*)$/i', $line['text'], $m)) {
                foreach (preg_split('/\s+/', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $uid) {
                    // "n:*" always yields the last message even when it is lower than n - filter it out.
                    if (ctype_digit($uid) && (int) $uid > $afterUid) {
                        $uids[(int) $uid] = true;
                    }
                }
            }
        }
        $uids = array_keys($uids);
        sort($uids);
        return $uids;
    }

    public function fetchRaw(int $uid): string
    {
        $lines = $this->command('UID FETCH ' . $uid . ' (BODY.PEEK[])', 'imap_fetch');
        foreach ($lines as $line) {
            if (isset($line['literals'][0]) && preg_match('/^\* \d+ FETCH/i', $line['text'])) {
                return $line['literals'][0];
            }
        }
        throw new ImapException('imap_fetch_empty');
    }

    /** @return list<array{name:string,flags:list<string>}> */
    public function listFolders(): array
    {
        $lines = $this->command('LIST "" "*"', 'imap_list');
        $folders = [];
        foreach ($lines as $line) {
            if (preg_match('/^\* LIST \(([^)]*)\) (?:"(?:[^"\\\\]|\\\\.)*"|NIL) (.+)$/i', $line['text'], $m)) {
                $name = trim($m[2]);
                if (str_starts_with($name, '"') && str_ends_with($name, '"')) {
                    $name = stripcslashes(substr($name, 1, -1));
                }
                $folders[] = ['name' => $name, 'flags' => preg_split('/\s+/', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY) ?: []];
            }
        }
        return $folders;
    }

    public function append(string $folder, string $raw, string $flags = '\\Seen'): void
    {
        $raw = preg_replace('/\r\n|\r|\n/', "\r\n", $raw) ?? $raw;
        $tag = $this->nextTag();
        $this->transport->write($tag . ' APPEND ' . self::quote($folder) . ' (' . $flags . ') {' . strlen($raw) . "}\r\n");
        $line = $this->transport->readLine();
        if ($line === null || !str_starts_with($line, '+')) {
            throw new ImapException('imap_append');
        }
        $this->transport->write($raw . "\r\n");
        $this->readUntilTagged($tag, 'imap_append');
    }

    public function logout(): void
    {
        if (!$this->connected) {
            return;
        }
        $this->connected = false;
        try {
            $this->command('LOGOUT', 'imap_logout');
        } catch (\Throwable $e) {
            // closing anyway
        }
        $this->transport->close();
    }

    public function close(): void
    {
        $this->connected = false;
        $this->transport->close();
    }

    /** @return list<array{text:string, literals:list<string>}> untagged responses */
    private function command(string $command, string $errorCode): array
    {
        $tag = $this->nextTag();
        $this->transport->write($tag . ' ' . $command . "\r\n");
        return $this->readUntilTagged($tag, $errorCode);
    }

    private function nextTag(): string
    {
        return sprintf('A%04d', ++$this->tagCounter);
    }

    /** @return list<array{text:string, literals:list<string>}> */
    private function readUntilTagged(string $tag, string $errorCode): array
    {
        $responses = [];
        for ($guard = 0; $guard < 200000; $guard++) {
            $line = $this->transport->readLine();
            if ($line === null) {
                throw new ImapException($errorCode . '_eof');
            }
            if (str_starts_with($line, $tag . ' ')) {
                if (!preg_match('/^' . preg_quote($tag, '/') . ' OK\b/i', $line)) {
                    throw new ImapException($errorCode);
                }
                return $responses;
            }
            $text = $line;
            $literals = [];
            while (preg_match('/\{(\d+)\}$/', $text, $m)) {
                $size = (int) $m[1];
                if ($size > self::MAX_LITERAL) {
                    throw new ImapException('imap_literal_too_large');
                }
                $literal = $size === 0 ? '' : $this->transport->readBytes($size);
                if ($literal === null) {
                    throw new ImapException($errorCode . '_eof');
                }
                $literals[] = $literal;
                $rest = $this->transport->readLine();
                if ($rest === null) {
                    throw new ImapException($errorCode . '_eof');
                }
                $text = preg_replace('/\{\d+\}$/', '', $text) . "\0" . $rest;
            }
            $responses[] = ['text' => $text, 'literals' => $literals];
        }
        throw new ImapException($errorCode . '_runaway');
    }

    private static function quote(string $value): string
    {
        if (preg_match('/[\r\n\0]/', $value)) {
            throw new ImapException('imap_invalid_argument');
        }
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
