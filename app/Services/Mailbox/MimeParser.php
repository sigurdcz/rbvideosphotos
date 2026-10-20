<?php
namespace App\Services\Mailbox;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Minimal RFC 5322 / MIME parser for the shared mailbox. Pure PHP (no ext-imap / mailparse),
 * defensive against hostile input: bounded depth and part count, no recursion on attachments.
 */
final class MimeParser
{
    private const MAX_DEPTH = 8;
    private const MAX_PARTS = 120;

    /**
     * @return array{
     *   message_id:string, in_reply_to:string, references:list<string>,
     *   from:array{name:string,addr:string}, to:list<array{name:string,addr:string}>, cc:list<array{name:string,addr:string}>, reply_to:list<array{name:string,addr:string}>,
     *   subject:string, date:DateTimeImmutable, text:string, html:string,
     *   attachments:list<array{filename:string,mime:string,content:string,content_id:string,inline:bool}>
     * }
     */
    public static function parse(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        [$headerBlock, $body] = self::splitHeaderBody($raw);
        $headers = self::parseHeaders($headerBlock);

        $state = ['text' => [], 'html' => [], 'attachments' => [], 'parts' => 0];
        self::walkPart($headers, $body, 0, $state);

        $text = trim(implode("\n\n", $state['text']));
        $html = trim(implode("\n", $state['html']));
        if ($text === '' && $html !== '') {
            $text = self::htmlToText($html);
        }

        $from = self::parseAddressList(self::headerValue($headers, 'from'))[0] ?? ['name' => '', 'addr' => ''];
        $messageId = self::firstMessageId(self::headerValue($headers, 'message-id'));

        return [
            'message_id' => $messageId !== '' ? $messageId : '<generated-' . sha1($raw) . '@mailbox.local>',
            'in_reply_to' => self::firstMessageId(self::headerValue($headers, 'in-reply-to')),
            'references' => self::allMessageIds(self::headerValue($headers, 'references')),
            'from' => $from,
            'to' => self::parseAddressList(self::headerValue($headers, 'to')),
            'cc' => self::parseAddressList(self::headerValue($headers, 'cc')),
            'reply_to' => self::parseAddressList(self::headerValue($headers, 'reply-to')),
            'subject' => trim(self::decodeWords(self::headerValue($headers, 'subject'))),
            'date' => self::parseDate(self::headerValue($headers, 'date')),
            'text' => $text,
            'html' => $html,
            'attachments' => $state['attachments'],
        ];
    }

    /** @return array{0:string,1:string} */
    public static function splitHeaderBody(string $raw): array
    {
        $pos = strpos($raw, "\n\n");
        if ($pos === false) {
            return [$raw, ''];
        }
        return [substr($raw, 0, $pos), substr($raw, $pos + 2)];
    }

    /** @return array<string,list<string>> lowercase header name => unfolded values */
    public static function parseHeaders(string $block): array
    {
        $block = preg_replace("/\n[ \t]+/", ' ', $block) ?? $block;
        $headers = [];
        foreach (explode("\n", $block) as $line) {
            $colon = strpos($line, ':');
            if ($colon === false || $colon === 0) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $colon)));
            $value = trim(substr($line, $colon + 1));
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = self::toUtf8($value, ''); // legacy raw 8-bit header (not RFC 2047): assume Windows-1250
            }
            $headers[$name][] = $value;
        }
        return $headers;
    }

    private static function headerValue(array $headers, string $name): string
    {
        return (string) ($headers[$name][0] ?? '');
    }

    private static function walkPart(array $headers, string $body, int $depth, array &$state): void
    {
        if (++$state['parts'] > self::MAX_PARTS || $depth > self::MAX_DEPTH) {
            return;
        }
        $contentType = self::headerValue($headers, 'content-type');
        [$type, $params] = self::parseContentType($contentType !== '' ? $contentType : 'text/plain; charset=us-ascii');

        if (str_starts_with($type, 'multipart/') && ($params['boundary'] ?? '') !== '') {
            foreach (self::splitMultipart($body, $params['boundary']) as $part) {
                [$partHeaderBlock, $partBody] = self::splitHeaderBody($part);
                self::walkPart(self::parseHeaders($partHeaderBlock), $partBody, $depth + 1, $state);
            }
            return;
        }

        $disposition = strtolower(self::headerValue($headers, 'content-disposition'));
        $dispParams = self::parseParams(self::headerValue($headers, 'content-disposition'));
        $filename = $dispParams['filename'] ?? ($params['name'] ?? '');
        $filename = trim(self::decodeWords($filename));
        $isAttachmentDisposition = str_starts_with($disposition, 'attachment');
        $contentId = trim(self::headerValue($headers, 'content-id'), " <>\t");
        $decoded = self::decodeTransfer($body, strtolower(trim(self::headerValue($headers, 'content-transfer-encoding'))));

        $isText = $type === 'text/plain' || $type === 'text/html';
        if ($isText && !$isAttachmentDisposition && $filename === '') {
            $text = self::toUtf8($decoded, $params['charset'] ?? '');
            if ($type === 'text/html') {
                $state['html'][] = $text;
            } else {
                $state['text'][] = rtrim($text);
            }
            return;
        }

        if (count($state['attachments']) >= 60) {
            return;
        }
        if ($filename === '') {
            $filename = $contentId !== '' ? preg_replace('/[^A-Za-z0-9._-]+/', '_', $contentId) : 'priloha';
            if ($type === 'message/rfc822') {
                $filename .= '.eml';
            }
        }
        $state['attachments'][] = [
            'filename' => $filename,
            'mime' => $type !== '' ? $type : 'application/octet-stream',
            'content' => $decoded,
            'content_id' => $contentId,
            'inline' => $contentId !== '' && !$isAttachmentDisposition,
        ];
    }

    /** @return list<string> */
    private static function splitMultipart(string $body, string $boundary): array
    {
        $delimiter = '--' . $boundary;
        $parts = [];
        $offset = 0;
        $length = strlen($body);
        $start = null;
        while ($offset <= $length) {
            $pos = strpos($body, $delimiter, $offset);
            if ($pos === false) {
                break;
            }
            $lineStart = $pos === 0 || $body[$pos - 1] === "\n";
            $afterDelimiter = $pos + strlen($delimiter);
            if (!$lineStart) {
                $offset = $afterDelimiter;
                continue;
            }
            if ($start !== null) {
                $parts[] = substr($body, $start, max(0, $pos - 1 - $start));
            }
            if (substr($body, $afterDelimiter, 2) === '--') {
                $start = null;
                break;
            }
            $eol = strpos($body, "\n", $afterDelimiter);
            if ($eol === false) {
                $start = null;
                break;
            }
            $start = $eol + 1;
            $offset = $start;
        }
        if ($start !== null && $start < $length) {
            $parts[] = substr($body, $start);
        }
        return $parts;
    }

    /** @return array{0:string,1:array<string,string>} */
    public static function parseContentType(string $value): array
    {
        $type = strtolower(trim(explode(';', $value, 2)[0]));
        return [$type, self::parseParams($value)];
    }

    /** Parses ;key=value parameters incl. RFC 2231 continuations and extended values. @return array<string,string> */
    public static function parseParams(string $value): array
    {
        $segments = [];
        $length = strlen($value);
        $current = '';
        $inQuotes = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '\\' && $inQuotes && $i + 1 < $length) {
                $current .= $value[++$i];
                continue;
            }
            if ($char === '"') {
                $inQuotes = !$inQuotes;
                $current .= $char;
                continue;
            }
            if ($char === ';' && !$inQuotes) {
                $segments[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $segments[] = $current;
        array_shift($segments);

        $plain = [];
        $extended = [];
        foreach ($segments as $segment) {
            $eq = strpos($segment, '=');
            if ($eq === false) {
                continue;
            }
            $key = strtolower(trim(substr($segment, 0, $eq)));
            $val = trim(substr($segment, $eq + 1));
            if (strlen($val) >= 2 && $val[0] === '"' && str_ends_with($val, '"')) {
                $val = substr($val, 1, -1);
            }
            if (preg_match('/^([^*]+)\*(?:(\d+)(\*)?)?$/', $key, $m)) {
                // name*=charset''value is encoded; name*N=part is a plain continuation; name*N*=part is an encoded one.
                $extended[$m[1]][(int) ($m[2] ?? 0)] = [$val, !isset($m[2]) || $m[2] === '' || ($m[3] ?? '') === '*'];
                continue;
            }
            $plain[$key] = $val;
        }
        foreach ($extended as $name => $chunks) {
            ksort($chunks);
            $charset = '';
            $result = '';
            foreach ($chunks as $index => [$chunk, $encoded]) {
                if ($encoded) {
                    if ($index === 0 && preg_match("/^([^']*)'[^']*'(.*)$/s", $chunk, $m)) {
                        $charset = $m[1];
                        $chunk = $m[2];
                    }
                    $chunk = rawurldecode($chunk);
                }
                $result .= $chunk;
            }
            $plain[$name] = $charset !== '' ? self::toUtf8($result, $charset) : $result;
        }
        return $plain;
    }

    public static function decodeTransfer(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => (string) base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $body) ?? '', false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    public static function toUtf8(string $data, string $charset): string
    {
        $charset = strtolower(trim($charset, " 	\"'"));
        if ($charset === '' || $charset === 'utf-8' || $charset === 'utf8' || $charset === 'us-ascii') {
            // Declared (or implied) UTF-8 that is not valid: Czech legacy mail is almost always Windows-1250.
            return mb_check_encoding($data, 'UTF-8') ? $data : self::convert($data, 'CP1250');
        }
        if (preg_match('/^(cp|windows-?)1250$/', $charset)) {
            $charset = 'CP1250';
        }
        return self::convert($data, $charset);
    }

    private static function convert(string $data, string $from): string
    {
        try {
            $converted = @mb_convert_encoding($data, 'UTF-8', $from);
            if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        } catch (\Throwable $e) {
            // unknown charset name for mbstring - try iconv
        }
        $converted = @iconv($from, 'UTF-8//IGNORE', $data);
        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
        return mb_scrub($data, 'UTF-8');
    }

    /** Decodes RFC 2047 encoded words. */
    public static function decodeWords(string $value): string
    {
        if (!str_contains($value, '=?')) {
            return $value;
        }
        $value = preg_replace('/(\?=)\s+(=\?)/', '$1$2', $value) ?? $value;
        return (string) preg_replace_callback('/=\?([^?\s]+)\?([bBqQ])\?([^?]*)\?=/', static function (array $m): string {
            $charset = preg_replace('/\*.*$/', '', $m[1]) ?? $m[1];
            $data = strtolower($m[2]) === 'b'
                ? (string) base64_decode($m[3])
                : quoted_printable_decode(str_replace('_', ' ', $m[3]));
            return self::toUtf8($data, $charset);
        }, $value);
    }

    /** @return list<array{name:string,addr:string}> */
    public static function parseAddressList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        $items = [];
        $current = '';
        $inQuotes = false;
        $angle = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '\\' && $inQuotes && $i + 1 < $length) {
                $current .= $char . $value[++$i];
                continue;
            }
            if ($char === '"') {
                $inQuotes = !$inQuotes;
            } elseif (!$inQuotes && $char === '<') {
                $angle++;
            } elseif (!$inQuotes && $char === '>') {
                $angle = max(0, $angle - 1);
            }
            if (($char === ',' || $char === ';') && !$inQuotes && $angle === 0) {
                $items[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $items[] = $current;

        $result = [];
        foreach ($items as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $name = '';
            $addr = '';
            if (preg_match('/^(.*)<([^<>]*)>\s*$/s', $item, $m)) {
                $name = trim($m[1]);
                $addr = trim($m[2]);
            } else {
                $addr = trim(preg_replace('/\(.*?\)/', '', $item) ?? $item);
            }
            if (strlen($name) >= 2 && $name[0] === '"' && str_ends_with($name, '"')) {
                $name = stripcslashes(substr($name, 1, -1));
            }
            $name = trim(self::decodeWords($name));
            $addr = strtolower(trim($addr, " \t\"'"));
            $addr = preg_replace('/[\r\n\0]/', '', $addr) ?? '';
            if ($addr === '') {
                continue;
            }
            $result[] = ['name' => $name, 'addr' => $addr];
        }
        return $result;
    }

    private static function firstMessageId(string $value): string
    {
        $all = self::allMessageIds($value);
        return $all[0] ?? '';
    }

    /** @return list<string> */
    private static function allMessageIds(string $value): array
    {
        if (!preg_match_all('/<[^<>\s]{1,500}>/', $value, $m)) {
            return [];
        }
        return array_slice(array_values(array_unique($m[0])), 0, 50);
    }

    private static function parseDate(string $value): DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $utc);
        $value = trim(preg_replace('/\([^)]*\)/', '', $value) ?? $value);
        if ($value === '') {
            return $now;
        }
        try {
            $date = new DateTimeImmutable($value);
        } catch (\Throwable $e) {
            return $now;
        }
        $date = $date->setTimezone($utc);
        // A forged far-future Date header must not pin a thread to the top of the list forever.
        return $date > $now->modify('+1 day') ? $now : $date;
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<br\s*/?>|</(p|div|tr|li|h[1-6])>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
