<?php

namespace App\Services;

use App\Core\Env;

/**
 * Pure logic of the first-party visit measurement (a fallback next to GA4).
 *
 * Everything here works without a database so it can be tested directly: the
 * beacon payload validation, the same-origin check, the daily visitor hash, bot
 * and device classification and the sanitizing of path / referrer / UTM values.
 * SQL lives in App\Repositories\VisitAnalyticsRepository, the HTTP glue in
 * tryRecordVisitBeacon() in public/index.php.
 *
 * Privacy model: without analytics consent the browser stores nothing and the
 * server keeps only a salted HMAC of IP + User-Agent + day, truncated to 32 hex
 * characters. The day is part of the input, so the same visitor gets a new hash
 * every day and visits cannot be linked across days. The IP is never stored.
 * With explicit analytics consent the browser also sends its own random cms_vid
 * cookie value, which is stored as visitor_id.
 */
final class VisitAnalyticsService
{
    public const MAX_BODY_BYTES = 4096;
    public const RETENTION_MONTHS = 14;
    public const MAX_REFERRER_LENGTH = 2048;
    public const MAX_UTM_LENGTH = 100;
    public const BOT_PATTERN = '~bot|crawl|spider|slurp|headless|lighthouse|preview|python|curl|wget|facebookexternalhit~i';
    /** Stored path: "/" followed by RFC 3986 pchar / "/" (unreserved, pct-encoded, sub-delims, ":", "@"). */
    public const PATH_PATTERN = '~^/[A-Za-z0-9\-._\~%!$&\'()*+,;=:@/]{0,254}$~';
    public const MAX_PATH_LENGTH = 255;
    public const VISITOR_ID_PATTERN = '~^[a-f0-9]{32}$~';
    public const THROTTLE_SCOPE = 'visit_beacon';
    /** Throttle files older than this are stale: window 60 s + lock 300 s, with a wide margin. */
    public const THROTTLE_STALE_SECONDS = 3600;
    public const REJECTION_REASONS = ['foreign_origin', 'invalid_payload', 'missing_secret', 'throttled', 'other'];

    /**
     * Salted, daily rotating visitor hash; the raw IP never leaves this function.
     * An empty key is refused: an unkeyed hash of an IPv4 address is reversible.
     */
    public static function visitorHash(string $ip, string $userAgent, string $day, string $secret): string
    {
        if (trim($secret) === '') {
            throw new \InvalidArgumentException('Visit hash requires a non-empty server secret.');
        }

        return substr(hash_hmac('sha256', $ip . '|' . $userAgent . '|' . $day, $secret), 0, 32);
    }

    /**
     * HMAC key for visitorHash(). VISIT_ANALYTICS_SALT is preferred; API_KEY is an
     * existing server-only secret, so it is a safe fallback. Without either the
     * measurement is off (null): a public key would let anyone recompute the hash
     * and recover an IPv4 address by brute force, so there is deliberately no
     * built-in default.
     */
    public static function secret(): ?string
    {
        foreach (['VISIT_ANALYTICS_SALT', 'API_KEY'] as $name) {
            $value = trim((string) Env::get($name, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Throttle bucket for the beacon: the IPv4 /24 or IPv6 /48 network instead of
     * the single address, so one network (notably an IPv6 /64 with endless host
     * addresses) can neither bypass the limit nor create one state file per address.
     */
    public static function throttleKey(string $ip): string
    {
        $packed = @inet_pton(trim($ip));
        if ($packed === false) {
            return 'unknown';
        }
        // IPv4-mapped IPv6 (::ffff:a.b.c.d, dual-stack sockets) is an IPv4 client;
        // a /48 of it would put every IPv4 visitor into one shared bucket.
        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
            $packed = substr($packed, 12);
        }
        if (strlen($packed) === 4) {
            return 'v4:' . (string) inet_ntop(substr($packed, 0, 3) . "\0") . '/24';
        }

        return 'v6:' . (string) inet_ntop(substr($packed, 0, 6) . str_repeat("\0", 10)) . '/48';
    }

    /**
     * Aggregated, rate-limited logging of dropped beacons. Each call counts one
     * rejection of $reason in a small per-reason state file (a fixed set of files,
     * no per-client data, no IP). At most once per $intervalSeconds the accumulated
     * count is returned so the caller logs one line; otherwise null.
     */
    public static function countRejection(string $reason, string $stateDirectory, int $now, int $intervalSeconds = 3600): ?int
    {
        if (!in_array($reason, self::REJECTION_REASONS, true)) {
            $reason = 'other';
        }
        if (!is_dir($stateDirectory) && !@mkdir($stateDirectory, 0775, true) && !is_dir($stateDirectory)) {
            return null;
        }
        $handle = @fopen($stateDirectory . '/visit_reject-' . $reason . '.json', 'c+');
        if ($handle === false) {
            return null;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return null;
            }
            $state = json_decode((string) stream_get_contents($handle), true);
            $count = (int) (is_array($state) ? ($state['count'] ?? 0) : 0) + 1;
            $since = (int) (is_array($state) ? ($state['since'] ?? 0) : 0);
            // The first rejection (no state yet) is logged at once, then at most once per interval.
            $flush = $now - $since >= $intervalSeconds;
            $next = $flush ? ['count' => 0, 'since' => $now] : ['count' => $count, 'since' => $since];
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($next));
            fflush($handle);
            flock($handle, LOCK_UN);

            return $flush ? $count : null;
        } finally {
            fclose($handle);
        }
    }

    public static function isBot(string $userAgent): bool
    {
        return trim($userAgent) === '' || preg_match(self::BOT_PATTERN, $userAgent) === 1;
    }

    /** @return 'desktop'|'mobile'|'tablet'|'unknown' */
    public static function deviceType(string $userAgent, ?int $screenWidth = null): string
    {
        $ua = trim($userAgent);
        if ($ua !== '') {
            if (preg_match('~ipad|tablet|playbook|silk|kindle|nexus (?:7|9|10)~i', $ua) === 1
                || (stripos($ua, 'android') !== false && stripos($ua, 'mobile') === false)) {
                return 'tablet';
            }
            if (preg_match('~mobi|iphone|ipod|android|windows phone|blackberry|opera mini~i', $ua) === 1) {
                return 'mobile';
            }

            return 'desktop';
        }

        if ($screenWidth !== null && $screenWidth > 0) {
            return $screenWidth < 768 ? 'mobile' : ($screenWidth < 1024 ? 'tablet' : 'desktop');
        }

        return 'unknown';
    }

    /**
     * Normalizes the beacon path instead of dropping the whole visit: query and
     * fragment are cut off, every byte outside RFC 3986 pchar (and a "%" that does
     * not start a %XX escape) is percent-encoded, which is lossless, and the result
     * is cut to 255 bytes without splitting an escape. A string that is not an
     * absolute path is stored as "/". Returns null only for a non-string value and
     * for the admin area ("/admin" and "/admin/..."; "/administrativa" is public).
     */
    public static function sanitizePath(mixed $path): ?string
    {
        if (!is_string($path)) {
            return null;
        }
        $path = (string) preg_replace('~[?#].*$~s', '', $path);
        if (!str_starts_with($path, '/')) {
            return '/';
        }
        $path = (string) preg_replace_callback(
            '~%(?![0-9A-Fa-f]{2})|[^A-Za-z0-9\-._\~%!$&\'()*+,;=:@/]~',
            static fn(array $match): string => sprintf('%%%02X', ord($match[0])),
            $path
        );
        if (strlen($path) > self::MAX_PATH_LENGTH) {
            $path = (string) preg_replace('~%[0-9A-Fa-f]?$~', '', substr($path, 0, self::MAX_PATH_LENGTH));
        }

        $decoded = strtolower(rawurldecode($path));
        if ($decoded === '/admin' || str_starts_with($decoded, '/admin/')) {
            return null;
        }

        return preg_match(self::PATH_PATTERN, $path) === 1 ? $path : '/';
    }

    /**
     * Reduces a referrer URL to its lowercase host. Internal navigation (own host,
     * with or without www) and non-http(s) referrers return null.
     *
     * @param list<string> $ownHosts
     */
    public static function referrerHost(mixed $referrer, array $ownHosts): ?string
    {
        if (!is_string($referrer) || $referrer === '' || strlen($referrer) > self::MAX_REFERRER_LENGTH) {
            return null;
        }
        $scheme = strtolower((string) parse_url($referrer, PHP_URL_SCHEME));
        $host = strtolower(trim((string) parse_url($referrer, PHP_URL_HOST), '.'));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || strlen($host) > 255
            || preg_match('~^[a-z0-9.\-]+$~', $host) !== 1) {
            return null;
        }

        $bare = static fn(string $value): string => preg_replace('~^www\.~', '', strtolower(trim($value, '.'))) ?? $value;
        foreach ($ownHosts as $own) {
            $own = self::hostOnly((string) $own);
            if ($own !== '' && $bare($own) === $bare($host)) {
                return null;
            }
        }

        return $host;
    }

    /** UTM value: trimmed text without control characters, at most 100 characters. */
    public static function sanitizeUtm(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $clean = trim((string) preg_replace('~[\x00-\x1F\x7F]+~u', '', $value));
        if ($clean === '' || mb_strlen($clean, 'UTF-8') > self::MAX_UTM_LENGTH || !mb_check_encoding($clean, 'UTF-8')) {
            return null;
        }

        return $clean;
    }

    /**
     * Same-origin guard against cross-site beacons, deliberately conservative:
     * - any Sec-Fetch-Site other than "same-origin" rejects ("same-site" is a sibling
     *   subdomain such as staging, "cross-site"/"none" never come from our script);
     * - Origin (or, without it, Referer) must name exactly the requested authority.
     *   Default ports are ignored on both sides (Host "example.com:443" equals Origin
     *   "https://example.com"), any other port must match, and www / bare host are
     *   different origins (the www redirect makes only one of them serve pages);
     * - "Origin: null" (opaque origin: sandboxed frame, no-referrer policy) is not a
     *   proof of anything, so it is accepted only with "Sec-Fetch-Site: same-origin";
     * - without Origin and Referer a bare "Sec-Fetch-Site: same-origin" is accepted.
     */
    public static function isSameOrigin(array $server): bool
    {
        $request = self::splitAuthority((string) ($server['HTTP_HOST'] ?? ''));
        if ($request === null) {
            return false;
        }
        $fetchSite = strtolower(trim((string) ($server['HTTP_SEC_FETCH_SITE'] ?? '')));
        if ($fetchSite !== '' && $fetchSite !== 'same-origin') {
            return false;
        }

        $origin = trim((string) ($server['HTTP_ORIGIN'] ?? ''));
        if (strtolower($origin) === 'null') {
            return $fetchSite === 'same-origin';
        }

        foreach ([$origin, trim((string) ($server['HTTP_REFERER'] ?? ''))] as $value) {
            if ($value === '') {
                continue;
            }
            $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
            $host = strtolower(trim((string) parse_url($value, PHP_URL_HOST), '.'));
            $port = parse_url($value, PHP_URL_PORT);
            if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
                return false;
            }
            $port = is_int($port) ? $port : null;
            if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
                $port = null;
            }

            return $host === $request['host'] && $port === $request['port'];
        }

        return $fetchSite === 'same-origin';
    }

    /**
     * "Host" header -> lowercase host and non-default port (80/443 become null).
     *
     * @return array{host:string,port:?int}|null
     */
    private static function splitAuthority(string $authority): ?array
    {
        $authority = strtolower(trim($authority));
        if (preg_match('~^(\[[0-9a-f:.]+\]|[a-z0-9.\-]+?)\.?(?::(\d{1,5}))?$~', $authority, $match) !== 1 || $match[1] === '') {
            return null;
        }
        $port = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : null;
        if ($port === 80 || $port === 443) {
            $port = null;
        }

        return ['host' => $match[1], 'port' => $port];
    }

    /**
     * Validates the raw beacon body. Returns the normalized payload or null when the
     * request must be dropped (too large, not JSON, wrong types, invalid path / id).
     *
     * @return array{p:string,r:?string,s:?string,m:?string,c:?string,w:?int,v:?string,a:bool}|null
     */
    public static function parsePayload(string $body): ?array
    {
        if ($body === '' || strlen($body) > self::MAX_BODY_BYTES) {
            return null;
        }
        try {
            $data = json_decode($body, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($data) || array_is_list($data)) {
            return null;
        }

        $path = self::sanitizePath($data['p'] ?? null);
        if ($path === null) {
            return null;
        }

        $referrer = $data['r'] ?? null;
        if ($referrer !== null && (!is_string($referrer) || strlen($referrer) > self::MAX_REFERRER_LENGTH)) {
            return null;
        }

        $utm = [];
        foreach (['s', 'm', 'c'] as $key) {
            $value = $data[$key] ?? null;
            if ($value !== null && (!is_string($value) || mb_strlen($value, 'UTF-8') > self::MAX_UTM_LENGTH)) {
                return null;
            }
            $utm[$key] = self::sanitizeUtm($value);
        }

        $width = $data['w'] ?? null;
        if ($width !== null && (!is_int($width) || $width < 0 || $width > 100000)) {
            return null;
        }

        $visitorId = $data['v'] ?? null;
        if ($visitorId !== null && (!is_string($visitorId) || preg_match(self::VISITOR_ID_PATTERN, $visitorId) !== 1)) {
            return null;
        }

        $consent = $data['a'] ?? false;
        if (!is_bool($consent) && $consent !== 0 && $consent !== 1) {
            return null;
        }
        $consent = (bool) $consent;

        return [
            'p' => $path,
            'r' => $referrer === '' ? null : $referrer,
            's' => $utm['s'],
            'm' => $utm['m'],
            'c' => $utm['c'],
            'w' => $width,
            // A cookie id is only ever kept together with explicit analytics consent.
            'v' => $consent ? $visitorId : null,
            'a' => $consent,
        ];
    }

    /**
     * Builds the site_visits row. The IP is consumed by the hash only.
     *
     * @param array{p:string,r:?string,s:?string,m:?string,c:?string,w:?int,v:?string,a:bool} $payload
     * @param list<string> $ownHosts
     */
    public static function buildRecord(
        int $siteId,
        array $payload,
        string $ip,
        string $userAgent,
        array $ownHosts,
        string $secret,
        \DateTimeImmutable $now
    ): array {
        $day = $now->format('Y-m-d');

        return [
            'site_id' => $siteId,
            'visited_at' => $now->format('Y-m-d H:i:s'),
            'visited_on' => $day,
            'path' => $payload['p'],
            'referrer_host' => self::referrerHost($payload['r'], $ownHosts),
            'utm_source' => $payload['s'],
            'utm_medium' => $payload['m'],
            'utm_campaign' => $payload['c'],
            'device_type' => self::deviceType($userAgent, $payload['w']),
            'visitor_hash' => self::visitorHash($ip, $userAgent, $day, $secret),
            'visitor_id' => $payload['a'] ? $payload['v'] : null,
            'consent_analytics' => $payload['a'] ? 1 : 0,
            'is_bot' => self::isBot($userAgent) ? 1 : 0,
        ];
    }

    /**
     * First day that is still kept; older rows are purged. The cutoff is the first
     * day of the month 14 months back, so the 14 full previous months plus the
     * running one are kept. A plain "-14 months" overflows at month ends
     * (2026-12-31 -> "2025-10-31", but 2027-04-30 -> 2026-02-30 -> 2026-03-02).
     */
    public static function retentionCutoff(\DateTimeImmutable $today): string
    {
        return $today->modify('first day of -' . self::RETENTION_MONTHS . ' months')->format('Y-m-d');
    }

    /** @return 7|30|90|365 */
    public static function normalizeRangeDays(mixed $days): int
    {
        $value = filter_var($days, FILTER_VALIDATE_INT);
        return in_array($value, [7, 30, 90, 365], true) ? $value : 30;
    }

    private static function hostOnly(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        $host = str_contains($value, '://') ? (string) parse_url($value, PHP_URL_HOST) : (string) preg_replace('~[:/].*$~', '', $value);

        return trim($host, '.');
    }
}
