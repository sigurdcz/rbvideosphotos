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
        $canonical = !empty($seo['canonical_url']) ? $seo['canonical_url'] : $pageUrl;
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

    private function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function renderConsentRuntime(array $site): string
    {
        $settings = [
            'cookieConsentEnabled' => (int)($site['cookie_consent_enabled'] ?? 1) === 1,
            'googleAnalyticsId' => trim((string)($site['google_analytics_id'] ?? '')),
            'googleTagManagerId' => trim((string)($site['google_tag_manager_id'] ?? '')),
            'analyticsHeadScripts' => (string)($site['analytics_head_scripts'] ?? ''),
            'analyticsBodyScripts' => (string)($site['analytics_body_scripts'] ?? ''),
            'marketingHeadScripts' => (string)($site['marketing_head_scripts'] ?? ''),
            'marketingBodyScripts' => (string)($site['marketing_body_scripts'] ?? ''),
        ];

        $json = json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($json === false) {
            $json = '{}';
        }

        return <<<HTML
<script>
window.cmsCookieConsentSettings = {$json};
(function(){
  var storageKey = 'cookie_consent_preferences';
  var settings = window.cmsCookieConsentSettings || {};
  var consentEnabled = settings.cookieConsentEnabled !== false;
  var version = '1.0';

  function defaultPreferences(){
    return {necessary:true,analytics:false,marketing:false,timestamp:new Date().toISOString(),version:version};
  }

  function getPreferences(){
    if (!consentEnabled) {
      return {necessary:true,analytics:true,marketing:true,timestamp:new Date().toISOString(),version:version};
    }
    try {
      var raw = window.localStorage ? localStorage.getItem(storageKey) : '';
      if (!raw) return null;
      var parsed = JSON.parse(raw);
      if (!parsed || parsed.version !== version) return null;
      return {
        necessary:true,
        analytics: parsed.analytics === true,
        marketing: parsed.marketing === true,
        timestamp: parsed.timestamp || new Date().toISOString(),
        version: version
      };
    } catch(e) {
      return null;
    }
  }

  function savePreferences(preferences){
    var normalized = {
      necessary:true,
      analytics: preferences && preferences.analytics === true,
      marketing: preferences && preferences.marketing === true,
      timestamp:new Date().toISOString(),
      version:version
    };
    try {
      if (window.localStorage) localStorage.setItem(storageKey, JSON.stringify(normalized));
    } catch(e) {}
    window.cmsCookieConsentPreferences = normalized;
    document.dispatchEvent(new CustomEvent('cms-cookie-consent:updated', {detail:normalized}));
    if (normalized.analytics) document.dispatchEvent(new CustomEvent('cms-cookie-consent:analytics'));
    if (normalized.marketing) document.dispatchEvent(new CustomEvent('cms-cookie-consent:marketing'));
    return normalized;
  }

  function canLoadAnalytics(){
    if (!consentEnabled) return true;
    var preferences = getPreferences();
    return !!(preferences && preferences.analytics === true);
  }

  function canLoadMarketing(){
    if (!consentEnabled) return true;
    var preferences = getPreferences();
    return !!(preferences && preferences.marketing === true);
  }

  function appendHtml(html, targetName, key){
    if (!html || window[key]) return;
    var target = targetName === 'head' ? document.head : document.body;
    if (!target) {
      document.addEventListener('DOMContentLoaded', function(){ appendHtml(html, targetName, key); }, {once:true});
      return;
    }
    window[key] = true;
    var template = document.createElement('template');
    template.innerHTML = html;
    Array.prototype.slice.call(template.content.childNodes).forEach(function(node){
      if (node.nodeName && node.nodeName.toLowerCase() === 'script') {
        var script = document.createElement('script');
        Array.prototype.slice.call(node.attributes || []).forEach(function(attr){ script.setAttribute(attr.name, attr.value); });
        script.text = node.textContent || '';
        target.appendChild(script);
        return;
      }
      target.appendChild(node.cloneNode(true));
    });
  }

  function loadGoogleAnalytics(){
    var id = (settings.googleAnalyticsId || '').trim();
    if (!id || window.__analyticsLoaded || !canLoadAnalytics()) return;
    window.__analyticsLoaded = true;
    window['ga-disable-' + id] = false;
    var script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(script);
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function(){ dataLayer.push(arguments); };
    gtag('js', new Date());
    gtag('config', id);
  }

  function loadGoogleTagManager(){
    var id = (settings.googleTagManagerId || '').trim();
    if (!id || window.__tagManagerLoaded || !canLoadMarketing()) return;
    window.__tagManagerLoaded = true;
    (function(w,d,s,l,i){
      w[l]=w[l]||[];
      w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
      var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
      j.async=true;
      j.src='https://www.googletagmanager.com/gtm.js?id='+encodeURIComponent(i)+dl;
      f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer',id);
  }

  function loadAnalyticsScripts(){
    if (!canLoadAnalytics()) return;
    loadGoogleAnalytics();
    appendHtml(settings.analyticsHeadScripts || '', 'head', '__analyticsHeadScriptsLoaded');
    appendHtml(settings.analyticsBodyScripts || '', 'body', '__analyticsBodyScriptsLoaded');
  }

  function loadMarketingScripts(){
    if (!canLoadMarketing()) return;
    loadGoogleTagManager();
    appendHtml(settings.marketingHeadScripts || '', 'head', '__marketingHeadScriptsLoaded');
    appendHtml(settings.marketingBodyScripts || '', 'body', '__marketingBodyScriptsLoaded');
  }

  function reloadIfTrackingWasDisabled(preferences){
    if (!preferences) return;
    var id = (settings.googleAnalyticsId || '').trim();
    if (!preferences.analytics && id) window['ga-disable-' + id] = true;
    if (
      (!preferences.analytics && (window.__analyticsLoaded || window.__analyticsHeadScriptsLoaded || window.__analyticsBodyScriptsLoaded)) ||
      (!preferences.marketing && (window.__tagManagerLoaded || window.__marketingHeadScriptsLoaded || window.__marketingBodyScriptsLoaded))
    ) {
      window.location.reload();
    }
  }

  window.cmsCookieConsent = {
    enabled: consentEnabled,
    getPreferences: getPreferences,
    savePreferences: savePreferences,
    defaultPreferences: defaultPreferences,
    canLoadAnalytics: canLoadAnalytics,
    canLoadMarketing: canLoadMarketing,
    loadAnalyticsScripts: loadAnalyticsScripts,
    loadMarketingScripts: loadMarketingScripts
  };
  window.cmsCookieConsentPreferences = getPreferences();

  document.addEventListener('cms-cookie-consent:updated', function(event){
    reloadIfTrackingWasDisabled(event.detail);
    loadAnalyticsScripts();
    loadMarketingScripts();
  });

  loadAnalyticsScripts();
  loadMarketingScripts();
})();
</script>
HTML;
    }
}
