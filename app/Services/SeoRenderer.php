<?php

namespace App\Services;

class SeoRenderer
{
    public function renderHead(array $seo, array $site, string $pageUrl, ?string $ogImageUrl, ?string $twImageUrl): string
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
        if (!empty($seo['meta_keywords'])) {
            $lines[] = '<meta name="keywords" content="' . $this->e($seo['meta_keywords']) . '">';
        }
        $lines[] = '<meta name="robots" content="' . implode(',', $robots) . '">';
        $canonical = $this->normalizeUrl((string)($seo['canonical_url'] ?? ''), $site, $pageUrl);
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
        if (!empty($seo['schema_json'])) {
            $lines[] = '<script type="application/ld+json">' . "\n" . $seo['schema_json'] . "\n</script>";
        }

        $lines[] = $this->renderConsentRuntime($site);
        $lines[] = $this->renderTrackingRuntime($site);
        if (!empty($site['head_scripts'])) {
            $lines[] = $site['head_scripts'];
        }

        return implode("\n", $lines);
    }

    public function renderBodyScripts(array $site): string
    {
        if (empty($site['body_scripts'])) return '';
        return $site['body_scripts'];
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
            'phone_click'        => true,
            'email_click'        => true,
            'cta_click'          => true,
            'social_click'       => true,
            'gallery_open'       => true,
            'video_play'         => true,
            'pricing_click'      => true,
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
        return <<<HTML
<meta name="cms-tracking-config" content="{$encoded}">
<script src="/assets/tracking.js" defer></script>
HTML;
    }

    private function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
        $settings = [
            'cookieConsentEnabled'     => (int)($site['cookie_consent_enabled'] ?? 1) === 1,
            'analyticsCategoryEnabled' => (int)($site['cookie_consent_analytics_enabled'] ?? 1) === 1,
            'marketingCategoryEnabled' => (int)($site['cookie_consent_marketing_enabled'] ?? 1) === 1,
            'googleAnalyticsId'        => trim((string)($site['google_analytics_id'] ?? '')),
            'googleTagManagerId'       => trim((string)($site['google_tag_manager_id'] ?? '')),
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
        return <<<HTML
<meta name="cms-cookie-consent-settings" content="{$encoded}">
<script src="/assets/cookie-consent-runtime.js"></script>
HTML;
    }
}
