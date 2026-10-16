<?php

namespace App\Services;

use App\Core\Logger;
use App\Repositories\SecurityTxtRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class SecurityTxtService
{
    private SecurityTxtRepository $repository;
    private SecurityTxtValidator $validator;
    private SecurityTxtGenerator $generator;
    private SecurityTxtCache $cache;

    public function __construct(
        ?SecurityTxtRepository $repository = null,
        ?SecurityTxtValidator $validator = null,
        ?SecurityTxtGenerator $generator = null,
        ?SecurityTxtCache $cache = null
    ) {
        $this->repository = $repository ?? new SecurityTxtRepository();
        $this->validator = $validator ?? new SecurityTxtValidator();
        $this->generator = $generator ?? new SecurityTxtGenerator();
        $this->cache = $cache ?? new SecurityTxtCache();
    }

    public function adminState(array $site, ?DateTimeImmutable $now = null): array
    {
        $now = $this->utcNow($now);
        $stored = $this->repository->findBySite((int) $site['id']);
        $config = $stored;
        if (empty($stored['exists'])) {
            $config = array_merge($stored, $this->defaults($site, $now));
        } elseif (trim((string) ($config['canonical_url'] ?? '')) === '') {
            $config['canonical_url'] = $this->canonicalUrl($site);
        }

        $validation = $this->validator->validate($config, !empty($config['enabled']), $now);
        $completeValidation = $this->validator->validate($config, true, $now);
        $expiration = $this->validator->expirationStatus($config['expires_at'] ?? null, $now);
        $preview = '';
        if ($completeValidation['valid']) {
            $preview = $this->generator->generate($completeValidation['config']);
        }

        $payload = $this->publicPayload($site, $now);
        return [
            'config' => $config,
            'schema_available' => !empty($stored['schema_available']),
            'validation' => $validation,
            'complete_validation' => $completeValidation,
            'expiration' => $expiration,
            'preview' => $preview,
            'endpoint' => $this->endpointStatus($payload),
        ];
    }

    public function save(array $site, array $input, ?DateTimeImmutable $now = null): array
    {
        $now = $this->utcNow($now);
        if (trim((string) ($input['canonical_url'] ?? '')) === '') {
            $input['canonical_url'] = $this->canonicalUrl($site);
        }
        $enabled = !empty($input['enabled']);
        $validation = $this->validator->validate($input, $enabled, $now);
        if (!$validation['valid']) {
            return ['success' => false, 'validation' => $validation, 'config' => $input, 'cache_warning' => null];
        }

        $saved = $this->repository->save((int) $site['id'], $validation['config']);
        $cacheWarning = null;
        try {
            $this->cache->forget((int) $site['id']);
            $this->refreshCache($site, $now);
        } catch (Throwable $e) {
            $cacheWarning = 'Nastavení bylo uloženo, ale mezipaměť security.txt se nepodařilo obnovit.';
            Logger::warning('security_txt', $cacheWarning, [
                'site_id' => (int) $site['id'],
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'success' => true,
            'validation' => $validation,
            'config' => $saved,
            'cache_warning' => $cacheWarning,
        ];
    }

    public function publicPayload(array $site, ?DateTimeImmutable $now = null): array
    {
        $now = $this->utcNow($now);
        $siteId = (int) $site['id'];
        try {
            $cached = $this->cache->get($siteId);
        } catch (Throwable $e) {
            $cached = null;
            $this->logCacheFallback($siteId, 'Cache security.txt nelze precist; pouziva se cerstvy DB payload.', $e);
        }
        if ($cached !== null && (int) ($cached['refresh_timestamp'] ?? 0) > $now->getTimestamp()) {
            return $cached;
        }

        $config = $this->repository->findBySite($siteId);
        $payload = $this->buildPayload($site, $config, $now);
        if (!empty($config['schema_available'])) {
            try {
                $this->cache->put($siteId, $payload);
            } catch (Throwable $e) {
                $this->logCacheFallback($siteId, 'Cache security.txt nelze zapsat; pouziva se cerstvy DB payload.', $e);
            }
        }
        return $payload;
    }

    public function refreshCache(array $site, ?DateTimeImmutable $now = null): array
    {
        $now = $this->utcNow($now);
        $config = $this->repository->findBySite((int) $site['id']);
        $payload = $this->buildPayload($site, $config, $now);
        if (!empty($config['schema_available'])) {
            $this->cache->put((int) $site['id'], $payload);
        }
        return $payload;
    }

    public function defaults(array $site, ?DateTimeImmutable $now = null): array
    {
        $now = $this->utcNow($now);
        return [
            'enabled' => 0,
            'contacts' => [],
            'expires_at' => $now->modify('+180 days')->format('Y-m-d\TH:i:s\Z'),
            'canonical_url' => $this->canonicalUrl($site),
            'encryption_url' => '',
            'policy_url' => '',
            'acknowledgments_url' => '',
            'preferred_languages' => strtolower((string) ($site['default_language'] ?? 'cs')),
            'hiring_url' => '',
            'comments' => '',
        ];
    }

    private function buildPayload(array $site, array $config, DateTimeImmutable $now): array
    {
        $payload = [
            'version' => 1,
            'enabled' => !empty($config['enabled']),
            'valid' => false,
            'status' => empty($config['schema_available']) ? 'schema_missing' : 'disabled',
            'content' => '',
            'etag' => '',
            'last_modified' => '',
            'expires_timestamp' => null,
            'refresh_timestamp' => $now->modify('+5 minutes')->getTimestamp(),
        ];
        if (empty($config['schema_available']) || empty($config['enabled'])) {
            return $payload;
        }

        if (trim((string) ($config['canonical_url'] ?? '')) === '') {
            $config['canonical_url'] = $this->canonicalUrl($site);
        }
        $validation = $this->validator->validate($config, true, $now);
        if (!$validation['valid']) {
            $payload['status'] = isset($validation['errors']['expires']) ? 'invalid_or_expired' : 'invalid';
            return $payload;
        }

        $content = $this->generator->generate($validation['config']);
        $expires = new DateTimeImmutable((string) $validation['config']['expires_at'], new DateTimeZone('UTC'));

        return [
            'version' => 1,
            'enabled' => true,
            'valid' => true,
            'status' => 'active',
            'content' => $content,
            'etag' => '"' . hash('sha256', $content) . '"',
            'last_modified' => gmdate('D, d M Y H:i:s', $now->getTimestamp()) . ' GMT',
            'expires_timestamp' => $expires->getTimestamp(),
            'refresh_timestamp' => $now->modify('+5 minutes')->getTimestamp(),
        ];
    }

    private function logCacheFallback(int $siteId, string $message, Throwable $error): void
    {
        Logger::warning('security_txt', $message, [
            'site_id' => $siteId,
            'error' => $error->getMessage(),
        ]);
    }

    private function endpointStatus(array $payload): array
    {
        if (empty($payload['enabled'])) {
            return ['code' => 'disabled', 'label' => 'Vypnuto', 'content_type' => null];
        }
        if (empty($payload['valid'])) {
            return ['code' => $payload['status'] ?? 'invalid', 'label' => 'Endpoint neni dostupny: neplatna konfigurace', 'content_type' => null];
        }
        return ['code' => 'ok', 'label' => 'Aktivni a validni', 'content_type' => 'text/plain; charset=utf-8'];
    }

    private function canonicalUrl(array $site): string
    {
        $domain = trim((string) ($site['domain'] ?? ''));
        if ($domain === '') {
            return '';
        }
        if (!preg_match('~^https?://~i', $domain)) {
            $domain = 'https://' . $domain;
        }
        $parts = parse_url($domain);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }
        $authority = $parts['host'] . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
        return 'https://' . $authority . '/.well-known/security.txt';
    }

    private function utcNow(?DateTimeImmutable $now): DateTimeImmutable
    {
        return ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
    }
}
