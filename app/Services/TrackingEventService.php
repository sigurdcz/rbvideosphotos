<?php

declare(strict_types=1);

namespace App\Services;

/** Validation and privacy-safe row building for first-party frontend events. */
final class TrackingEventService
{
    public const MAX_BODY_BYTES = 1024;
    public const THROTTLE_SCOPE = 'tracking_event_beacon';
    public const THROTTLE_STALE_SECONDS = 3600;

    /** @return list<string> */
    public static function allowedEventNames(): array
    {
        return array_values(array_map(
            static fn(array $event): string => (string) $event['event_key'],
            (new GtmEventCatalog())->all()
        ));
    }

    /** @return array{e:string,p:string,a:bool}|null */
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

        $eventName = $data['e'] ?? null;
        if (!is_string($eventName) || !in_array($eventName, self::allowedEventNames(), true)) {
            return null;
        }
        $path = VisitAnalyticsService::sanitizePath($data['p'] ?? null);
        if ($path === null) {
            return null;
        }
        $explicitConsent = $data['a'] ?? false;
        if (!is_bool($explicitConsent) && $explicitConsent !== 0 && $explicitConsent !== 1) {
            return null;
        }

        return ['e' => $eventName, 'p' => $path, 'a' => (bool) $explicitConsent];
    }

    /** @param array{e:string,p:string,a:bool} $payload */
    public static function buildRecord(
        int $siteId,
        array $payload,
        string $ip,
        string $userAgent,
        string $secret,
        \DateTimeImmutable $now
    ): array {
        $day = $now->format('Y-m-d');

        return [
            'site_id' => $siteId,
            'occurred_at' => $now->format('Y-m-d H:i:s'),
            'occurred_on' => $day,
            'event_name' => $payload['e'],
            'path' => $payload['p'],
            'visitor_hash' => VisitAnalyticsService::visitorHash($ip, $userAgent, $day, $secret),
            'consent_analytics' => $payload['a'] ? 1 : 0,
            'is_bot' => VisitAnalyticsService::isBot($userAgent) ? 1 : 0,
        ];
    }
}
