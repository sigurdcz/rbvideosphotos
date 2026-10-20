<?php
namespace App\Services\Mailbox;

/** Presentation helpers shared by the mailbox views and the reply pre-fill. */
final class MailboxView
{
    public static function date(?string $utc, string $format = 'j. n. Y H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '';
        }
        try {
            return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Prague'))->format($format);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function shortDate(?string $utc): string
    {
        if ($utc === null || $utc === '') {
            return '';
        }
        $local = (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Prague'));
        $today = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Prague'));
        return $local->format('Y-m-d') === $today->format('Y-m-d') ? $local->format('H:i') : $local->format('j. n. Y');
    }

    public static function sender(array $message): string
    {
        $name = trim((string) ($message['from_name'] ?? ''));
        return $name !== '' ? $name . ' <' . $message['from_addr'] . '>' : (string) $message['from_addr'];
    }

    /** "Re: " prefix without stacking (Re: Re:). */
    public static function replySubject(string $subject): string
    {
        $subject = trim($subject);
        return preg_match('/^(re|odp)\s*:/i', $subject) ? $subject : 'Re: ' . $subject;
    }

    public static function quote(array $message): string
    {
        $when = self::date($message['sent_at']);
        $who = trim((string) ($message['from_name'] ?? '')) ?: (string) $message['from_addr'];
        $text = trim((string) ($message['body_text'] ?? ''));
        $quoted = preg_replace('/^/m', '> ', mb_substr($text, 0, 20000, 'UTF-8')) ?? $text;
        return "\n\nDne {$when} {$who} napsal(a):\n{$quoted}\n";
    }

    /**
     * Pre-fills recipients of a reply. Contact-form mails are sent by the site from our own address, so there the
     * client's e-mail is taken from the "Kontakt:" line of the body.
     *
     * @return array{to:string,cc:string,note:string}
     */
    public static function replyRecipients(array $message, string $ownAddress, bool $all): array
    {
        $own = strtolower($ownAddress);
        $note = '';
        $primary = [];
        foreach (MimeParser::parseAddressList((string) ($message['reply_to'] ?? '')) as $item) {
            $primary[] = $item['addr'];
        }
        if ($primary === [] && strtolower((string) $message['from_addr']) !== $own) {
            $primary[] = (string) $message['from_addr'];
        }
        if ($message['direction'] === 'out') {
            // Following up on our own message: write to the same people again.
            $primary = array_column(MimeParser::parseAddressList((string) ($message['to_addrs'] ?? '')), 'addr');
        }
        if ($primary === [] && $message['direction'] === 'in') {
            $contact = self::contactEmail((string) $message['body_text']);
            if ($contact !== null) {
                $primary[] = $contact;
            } else {
                $note = 'Zpráva přišla z kontaktního formuláře webu bez e-mailu klienta - vyplňte adresu příjemce ručně.';
            }
        }
        $cc = [];
        if ($all) {
            foreach (array_merge(MimeParser::parseAddressList((string) ($message['to_addrs'] ?? '')), MimeParser::parseAddressList((string) ($message['cc_addrs'] ?? ''))) as $item) {
                if ($item['addr'] !== $own && !in_array($item['addr'], $primary, true)) {
                    $cc[$item['addr']] = $item['addr'];
                }
            }
        }
        return ['to' => implode(', ', $primary), 'cc' => implode(', ', $cc), 'note' => $note];
    }

    /** E-mail from the "Kontakt:" line of a contact-form message (null when it holds a phone number or is missing). */
    public static function contactEmail(string $body): ?string
    {
        if (preg_match('/^\s*Kontakt:\s*(\S+@\S+)/mi', $body, $m)) {
            $email = strtolower(rtrim($m[1], '.,;'));
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }
        return null;
    }

    /**
     * Splits a plain-text mail into the new text and the quoted history below it ("> ..." block, optionally
     * introduced by an "On ... wrote:" line), so threads read like a conversation instead of a wall of quotes.
     *
     * @return array{0:string,1:string}
     */
    public static function splitQuoted(string $text): array
    {
        $lines = preg_split('/\r\n|\n/', $text) ?: [];
        $first = null;
        foreach ($lines as $i => $line) {
            if (str_starts_with(ltrim($line), '>')) {
                $first = $i;
                break;
            }
        }
        if ($first === null) {
            return [$text, ''];
        }
        // The line right before the quote usually announces it ("Dne ... napsal(a):", "On ... wrote:").
        $start = $first;
        if ($start > 0 && preg_match('/(napsal|wrote|odpov)/iu', $lines[$start - 1])) {
            $start--;
        }
        $rest = array_slice($lines, $first);
        foreach ($rest as $line) {
            if (trim($line) !== '' && !str_starts_with(ltrim($line), '>')) {
                return [$text, '']; // the quote is interleaved with replies: leave it as is
            }
        }
        return [rtrim(implode("\n", array_slice($lines, 0, $start))), trim(implode("\n", array_slice($lines, $start)))];
    }

    public static function size(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', ' ') . ' MB';
        }
        return max(1, (int) round($bytes / 1024)) . ' kB';
    }

    public static function cleanAuthorLabel(string $label): string
    {
        $label = trim(preg_replace('/[\x00-\x1F\x7F<>]+/u', ' ', $label) ?? '');
        return mb_substr($label, 0, 100, 'UTF-8');
    }
}
