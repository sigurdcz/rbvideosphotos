<?php

namespace App\Services;

use App\Repositories\ContactRepository;

class SeoRenderer
{
    private const AREA_SERVED = ['Olomouc', 'Olomoucký kraj', 'Morava', 'Česká republika'];

    public function renderHead(array $seo, array $site, string $pageUrl, ?string $ogImageUrl, ?string $twImageUrl, ?string $schemaImageUrl = null): string
    {
        $robots = [];
        if (!empty($seo['robots_index'])) $robots[] = 'index'; else $robots[] = 'noindex';
        if (!empty($seo['robots_follow'])) $robots[] = 'follow'; else $robots[] = 'nofollow';

        $lines = [];

        if (!empty($seo['meta_title'])) {
            $lines[] = '<title>' . $this->e($seo['meta_title']) . '</title>';
        }
        if (!empty($seo['meta_description'])) {
            $lines[] = '<meta name="description" content="' . $this->e($seo['meta_description']) . '">';
        }
        $lines[] = '<meta name="robots" content="' . implode(',', $robots) . '">';
        $canonical = $this->normalizeUrl((string)($seo['canonical_url'] ?? ''), $site, $pageUrl);
        $ogImageUrl = $ogImageUrl !== null && trim($ogImageUrl) !== ''
            ? $this->normalizeUrl($ogImageUrl, $site, $pageUrl)
            : null;
        $twImageUrl = $twImageUrl !== null && trim($twImageUrl) !== ''
            ? $this->normalizeUrl($twImageUrl, $site, $pageUrl)
            : null;
        $lines[] = '<link rel="canonical" href="' . $this->e($canonical) . '">';

        if (!empty($site['favicon_ico_url'])) {
            $lines[] = '<link rel="icon" type="image/x-icon" href="' . $this->e($site['favicon_ico_url']) . '">';
        }
        if (!empty($site['favicon_png_url'])) {
            $favicon = (string)$site['favicon_png_url'];
            $type = str_ends_with(strtolower(parse_url($favicon, PHP_URL_PATH) ?: $favicon), '.svg') ? 'image/svg+xml' : 'image/png';
            $lines[] = '<link rel="icon" type="' . $type . '" href="' . $this->e($favicon) . '">';
        }
        if (!empty($site['apple_touch_icon_url'])) {
            $lines[] = '<link rel="apple-touch-icon" href="' . $this->e($site['apple_touch_icon_url']) . '">';
        }

        // Open Graph
        $ogTitle = $seo['og_title'] ?? ($seo['meta_title'] ?? '');
        $ogDesc  = $seo['og_description'] ?? ($seo['meta_description'] ?? '');
        if ($ogTitle) $lines[] = '<meta property="og:title" content="' . $this->e($ogTitle) . '">';
        if ($ogDesc)  $lines[] = '<meta property="og:description" content="' . $this->e($ogDesc) . '">';
        $lines[] = '<meta property="og:type" content="website">';
        $lines[] = '<meta property="og:site_name" content="' . $this->e((string)($site['name'] ?? '')) . '">';
        $lines[] = '<meta property="og:url" content="' . $this->e($canonical) . '">';
        if ($ogImageUrl) $lines[] = '<meta property="og:image" content="' . $this->e($ogImageUrl) . '">';

        // Twitter Card
        $twTitle = $seo['twitter_title'] ?? $ogTitle;
        $twDesc  = $seo['twitter_description'] ?? $ogDesc;
        $lines[] = '<meta name="twitter:card" content="summary_large_image">';
        if ($twTitle) $lines[] = '<meta name="twitter:title" content="' . $this->e($twTitle) . '">';
        if ($twDesc)  $lines[] = '<meta name="twitter:description" content="' . $this->e($twDesc) . '">';
        if ($twImageUrl) $lines[] = '<meta name="twitter:image" content="' . $this->e($twImageUrl) . '">';

        // JSON-LD
        $schemaImageUrlNormalized = $schemaImageUrl !== null && trim($schemaImageUrl) !== ''
            ? $this->normalizeUrl($schemaImageUrl, $site, $pageUrl)
            : null;
        $schemaPayload = $this->buildSchemaPayload($seo, $site, $canonical, $ogImageUrl, $schemaImageUrlNormalized);
        if ($schemaPayload !== null) {
            $encoded = json_encode(
                $schemaPayload,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRETTY_PRINT
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            );
            $schemaJson = is_string($encoded) ? $encoded : '{}';
            $lines[] = '<script type="application/ld+json">' . "\n" . $schemaJson . "\n</script>";
        }
        $lines[] = $this->renderOrganizationSchema($site, $ogImageUrl);

        $lines[] = $this->renderConsentRuntime($site);
        $lines[] = $this->renderTrackingRuntime($site);
        if (!empty($site['head_scripts'])) {
            $lines[] = $site['head_scripts'];
        }
        $lines[] = $this->renderVersionComment();

        return implode("\n", $lines);
    }

    /**
     * HTML comment stamping the deployed build and how many migrations the
     * production database has actually run, so a mismatch between "code I
     * pushed" and "what's live" is visible in view-source instead of only
     * discoverable by re-diagnosing from scratch (see the deploy drift found
     * 2026-09-22, where a demo-content replay silently reverted SEO fields
     * a later migration didn't know to re-check).
     */
    private function renderVersionComment(): string
    {
        $parts = [];

        $buildFile = dirname(__DIR__, 2) . '/public/assets/build-info.json';
        $build = is_file($buildFile) ? json_decode((string)file_get_contents($buildFile), true) : null;
        if (is_array($build) && !empty($build['short_sha'])) {
            $parts[] = 'build ' . $build['short_sha'] . (!empty($build['built_at']) ? ' (' . $build['built_at'] . ')' : '');
        } else {
            $parts[] = 'build local/dev';
        }

        try {
            $db = \App\Core\Database::getInstance();
            $count = (int)$db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
            if ($count > 0) {
                $latest = $db->query(
                    'SELECT migration FROM schema_migrations ORDER BY applied_at DESC, id DESC LIMIT 1'
                )->fetchColumn();
                $parts[] = 'migrations: ' . $count . ' applied, latest ' . $latest;
            } else {
                $parts[] = 'migrations: none applied';
            }
        } catch (\Throwable $e) {
            $parts[] = 'migrations: unavailable';
        }

        return '<!-- ' . implode(' | ', $parts) . ' -->';
    }

    public function renderBodyScripts(array $site): string
    {
        if (empty($site['body_scripts'])) return '';
        return $site['body_scripts'];
    }

    public function renderOrganizationSchema(array $site, ?string $ogImageUrl = null): string
    {
        $domain = rtrim(trim((string)($site['domain'] ?? '')), '/');
        $contact = !empty($site['id'])
            ? (new ContactRepository())->findBySiteSafe((int)$site['id'])
            : [];
        $payload = [
            '@context' => 'https://schema.org',
            '@type' => ['Organization', 'LocalBusiness'],
            '@id' => ($domain !== '' ? $domain : '') . '/#organization',
            'name' => trim((string)($site['name'] ?? '')),
            'url' => ($domain !== '' ? $domain : '') . '/',
        ];
        $resolvedOgImageUrl = trim((string)$ogImageUrl);
        if ($resolvedOgImageUrl !== '') {
            $payload['image'] = $this->normalizeUrl($resolvedOgImageUrl, $site, ($domain !== '' ? $domain : '') . '/');
        }
        if (!empty($contact['phone_1'])) {
            $payload['telephone'] = trim((string)$contact['phone_1']);
        }
        if (!empty($contact['email'])) {
            $payload['email'] = trim((string)$contact['email']);
        }
        $street = trim((string)($contact['address_1'] ?? ''));
        $locality = trim((string)($contact['address_2'] ?? ''));
        if ($street !== '' || $locality !== '') {
            $payload['address'] = array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $street,
                'addressLocality' => $locality,
                'addressCountry' => 'CZ',
            ], static fn(string $value): bool => $value !== '');
        }
        $payload['areaServed'] = self::AREA_SERVED;
        $sameAs = [];
        for ($i = 1; $i <= 5; $i++) {
            $url = trim((string)($contact['social_' . $i . '_url'] ?? ''));
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                $sameAs[] = $url;
            }
        }
        if ($sameAs !== []) {
            $payload['sameAs'] = array_values(array_unique($sameAs));
        }
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        return '<script type="application/ld+json" data-cms-organization-schema>'
            . ($json !== false ? $json : '{}')
            . '</script>';
    }

    public function renderTrackingRuntime(array $site): string
    {
        $trackingEnabled = (int)($site['tracking_enabled'] ?? 1) === 1;
        if (!$trackingEnabled) {
            return '';
        }

        $defaultEvents = [
            'lead_form_submit'   => true,
            'lead_thank_you_page'=> true,
            'click_phone'        => true,
            'click_email'        => true,
            'cta_click'          => true,
            'social_click'       => true,
            'gallery_open'       => true,
            'video_start'        => true,
            'video_complete'     => true,
            'select_package'     => true,
            'view_google_reviews'=> true,
            'scroll_90'          => true,
        ];

        $eventsRaw = (string)($site['tracking_events_json'] ?? '');
        $events = $defaultEvents;
        if ($eventsRaw !== '') {
            $decoded = json_decode($eventsRaw, true);
            if (is_array($decoded)) {
                $events = array_merge($defaultEvents, $decoded);
            }
        }

        $config = [
            'trackingEnabled'       => true,
            'trackingRequireConsent'=> (int)($site['tracking_require_consent'] ?? 1) === 1,
            'trackingDebug'         => (int)($site['tracking_debug'] ?? 0) === 1,
            'trackingEvents'        => $events,
        ];

        $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($json === false) {
            $json = '{}';
        }

        $encoded = htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $src = self::hashedAssetSrc('tracking.js', 'tracking');
        return <<<HTML
<meta name="cms-tracking-config" content="{$encoded}">
<script src="{$src}" defer></script>
HTML;
    }

    private function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Builds the page-level JSON-LD payload for the "Schema.org" admin tab fields.
     * Returns null when no schema type is configured, so the caller can skip emitting
     * the <script> tag entirely.
     */
    private function buildSchemaPayload(array $seo, array $site, string $canonical, ?string $ogImageUrl, ?string $schemaImageUrl): ?array
    {
        $type = trim((string)($seo['schema_type'] ?? ''));
        if ($type === '') {
            return null;
        }

        $node = [
            '@context' => 'https://schema.org',
            '@type' => $type,
        ];

        $name = trim((string)($seo['schema_name'] ?? ''));
        if ($name !== '') {
            $node['name'] = $name;
        }

        $description = trim((string)($seo['schema_description'] ?? ''));
        if ($description !== '') {
            $node['description'] = $description;
        }

        $node['url'] = $canonical;

        $image = trim((string)$schemaImageUrl);
        if ($image === '') {
            $image = trim((string)$ogImageUrl);
        }
        if ($image !== '') {
            $node['image'] = $image;
        }

        $contactCapable = in_array($type, ['LocalBusiness', 'Person'], true);
        $profile = null;
        if ($contactCapable) {
            if ((int)($seo['schema_contact_auto'] ?? 1) === 1) {
                $profile = $this->contactProfile($site);
                if ($profile['phone'] !== '') {
                    $node['telephone'] = $profile['phone'];
                }
                if ($profile['email'] !== '') {
                    $node['email'] = $profile['email'];
                }
                $address = $this->addressNode($profile['street'], $profile['locality']);
                if ($address !== null) {
                    $node['address'] = $address;
                }
            } else {
                $phone = trim((string)($seo['schema_telephone'] ?? ''));
                $email = trim((string)($seo['schema_email'] ?? ''));
                if ($phone !== '') {
                    $node['telephone'] = $phone;
                }
                if ($email !== '') {
                    $node['email'] = $email;
                }
                $address = $this->addressNode(
                    (string)($seo['schema_address_street'] ?? ''),
                    (string)($seo['schema_address_locality'] ?? '')
                );
                if ($address !== null) {
                    $node['address'] = $address;
                }
            }
        }

        if ($type === 'LocalBusiness') {
            $domain = rtrim(trim((string)($site['domain'] ?? '')), '/');
            $organizationId = ($domain !== '' ? $domain : '') . '/#organization';
            $node['@id'] = $organizationId;
            $node['areaServed'] = self::AREA_SERVED;
            $profile ??= $this->contactProfile($site);
            if ($profile['sameAs'] !== []) {
                $node['sameAs'] = $profile['sameAs'];
            }
        }

        return $node;
    }

    /**
     * Contact data resolved from Kontakty settings, shaped for JSON-LD use.
     * Keeps the sameAs URL filtering of the former withLocalBusinessProfile()
     * verbatim; the postal-code/city split lives in addressNode().
     */
    private function contactProfile(array $site): array
    {
        $contact = !empty($site['id'])
            ? (new ContactRepository())->findBySiteSafe((int)$site['id'])
            : [];

        $sameAs = [];
        for ($i = 1; $i <= 5; $i++) {
            $url = trim((string)($contact['social_' . $i . '_url'] ?? ''));
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                $sameAs[] = $url;
            }
        }

        return [
            'phone' => trim((string)($contact['phone_1'] ?? '')),
            'email' => trim((string)($contact['email'] ?? '')),
            'street' => trim((string)($contact['address_1'] ?? '')),
            'locality' => trim((string)($contact['address_2'] ?? '')),
            'sameAs' => array_values(array_unique($sameAs)),
        ];
    }

    /**
     * Builds a schema.org PostalAddress node from a street + "PSC City" locality
     * string, shared by both the auto (Kontakty) and manual contact paths.
     * Returns null when both inputs are empty.
     */
    private function addressNode(string $street, string $locality): ?array
    {
        $street = trim($street);
        $locality = trim($locality);
        if ($street === '' && $locality === '') {
            return null;
        }

        $postalCode = '';
        $city = '';
        if ($locality !== '' && preg_match('~^(\d{3}\s?\d{2})\s+(.+)$~u', $locality, $matches)) {
            $postalCode = str_replace(' ', '', $matches[1]);
            $city = trim($matches[2]);
        }

        $address = ['@type' => 'PostalAddress'];
        if ($street !== '') {
            $address['streetAddress'] = $street;
        }
        if ($postalCode !== '') {
            $address['postalCode'] = $postalCode;
        }
        if ($city !== '') {
            $address['addressLocality'] = $city;
        } elseif ($locality !== '') {
            $address['addressLocality'] = $locality;
        }
        $address['addressCountry'] = 'CZ';

        return $address;
    }

    private function normalizeUrl(string $candidate, array $site, string $fallback): string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return $fallback;
        }

        if (preg_match('~^https?://~i', $candidate)) {
            return $candidate;
        }

        $domain = rtrim((string)($site['domain'] ?? ''), '/');
        if ($domain === '') {
            return $candidate;
        }

        if ($candidate === '/') {
            return $domain . '/';
        }

        return $domain . '/' . ltrim($candidate, '/');
    }

    public function renderConsentRuntime(array $site): string
    {
        $googleAnalyticsId = trim((string)($site['google_analytics_id'] ?? ''));
        $googleTagManagerId = trim((string)($site['google_tag_manager_id'] ?? ''));
        $settings = [
            'cookieConsentEnabled'     => (int)($site['cookie_consent_enabled'] ?? 1) === 1,
            'analyticsCategoryEnabled' => (int)($site['cookie_consent_analytics_enabled'] ?? 1) === 1,
            'marketingCategoryEnabled' => (int)($site['cookie_consent_marketing_enabled'] ?? 1) === 1,
            'googleAnalyticsId'        => $googleAnalyticsId,
            'googleTagManagerId'       => $googleTagManagerId,
            'trackingLoaderMode'       => $googleAnalyticsId !== '' ? 'direct-ga' : ($googleTagManagerId !== '' ? 'gtm' : 'none'),
            'analyticsHeadScripts'     => (string)($site['analytics_head_scripts'] ?? ''),
            'analyticsBodyScripts'     => (string)($site['analytics_body_scripts'] ?? ''),
            'marketingHeadScripts'     => (string)($site['marketing_head_scripts'] ?? ''),
            'marketingBodyScripts'     => (string)($site['marketing_body_scripts'] ?? ''),
            'clarityId'                => trim((string)($site['clarity_id'] ?? '')),
            'metaPixelId'              => trim((string)($site['meta_pixel_id'] ?? '')),
            'enableGtmTrackingLogs'    => (int)($site['enable_gtm_tracking_logs'] ?? 0) === 1,
        ];

        $json = json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($json === false) {
            $json = '{}';
        }

        $encoded = htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $src = self::hashedAssetSrc('cookie-consent-runtime.js', 'cookie-consent-runtime');
        return <<<HTML
<meta name="cms-cookie-consent-settings" content="{$encoded}">
<script src="{$src}" defer></script>
HTML;
    }

    /**
     * Content-hashed url for a public/assets script, so it can be cached for a year.
     *
     * Falls back to the plain path when the file cannot be read: a stale-but-cached script
     * is a smaller problem than a page that silently ships no tracking or consent runtime.
     */
    private static function hashedAssetSrc(string $name, string $prefix): string
    {
        $path = dirname(__DIR__, 2) . '/public/assets/' . $name;
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false || $contents === '') {
            return '/assets/' . $name;
        }

        return \App\Support\FrontendAssetManager::writeGeneratedAsset($prefix, 'js', $contents);
    }
}
