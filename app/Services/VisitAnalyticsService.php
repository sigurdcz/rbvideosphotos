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
    public const PATH_PATTERN = '~^/[A-Za-z0-9/_.\-%]{0,254}$~';
    public const VISITOR_ID_PATTERN = '~^[a-f0-9]{32}$~';

    /** Salted, daily rotating visitor hash; the raw IP never leaves this function. */
    public static function visitorHash(string $ip, string $userAgent, string $day, string $secret): string
    {
        return substr(hash_hmac('sha256', $ip . '|' . $userAgent . '|' . $day, $secret), 0, 32);
    }

    /**
     * HMAC key for visitorHash(). VISIT_ANALYTICS_SALT is preferred; API_KEY is an
     * existing server-only secret, so it is a safe fallback. The final constant only
     * keeps the feature working on an unconfigured install (hashes still rotate daily).
     */
    public static function secret(): string
    {
        foreach (['VISIT_ANALYTICS_SALT', 'API_KEY'] as $name) {
            $value = trim((string) Env::get($name, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'seo-cms-visit-analytics';
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

    /** Accepts only a plain public path; admin paths are never recorded. */
    public static function sanitizePath(mixed $path): ?string
    {
        if (!is_string($path) || preg_match(self::PATH_PATTERN, $path) !== 1) {
            return null;
        }
        if (stripos($path, '/admin') === 0) {
            return null;
        }

        return $path;
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
     * Same-origin guard against cross-site beacons. A cross-site Sec-Fetch-Site always
     * rejects; otherwise Origin (or, without it, Referer) must name the requested host,
     * and a bare "Sec-Fetch-Site: same-origin" is accepted when neither header is sent.
     */
    public static function isSameOrigin(array $server): bool
    {
        $requestHost = strtolower(trim((string) ($server['HTTP_HOST'] ?? '')));
        if ($requestHost === '') {
            return false;
        }
        $fetchSite = strtolower(trim((string) ($server['HTTP_SEC_FETCH_SITE'] ?? '')));
        if ($fetchSite !== '' && $fetchSite !== 'same-origin') {
            return false;
        }

        foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
            $value = trim((string) ($server[$header] ?? ''));
            if ($value === '' || $value === 'null') {
                continue;
            }
            $host = strtolower((string) parse_url($value, PHP_URL_HOST));
            $port = parse_url($value, PHP_URL_PORT);
            $authority = $host . ($port !== null && $port !== false ? ':' . $port : '');

            return $host !== '' && ($authority === $requestHost || $host === $requestHost);
        }

        return $fetchSite === 'same-origin';
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

    /** First day that is still kept; older rows are purged. */
    public static function retentionCutoff(\DateTimeImmutable $today): string
    {
        return $today->modify('-' . self::RETENTION_MONTHS . ' months')->format('Y-m-d');
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
