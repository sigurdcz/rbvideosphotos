(function(){
  var storageKey = 'cookie_consent_preferences';
  var settingsMeta = document.querySelector('meta[name="cms-cookie-consent-settings"]');
  var settings = {};
  if (settingsMeta && settingsMeta.content) {
    try {
      settings = JSON.parse(settingsMeta.content);
    } catch (e) {
      settings = {};
    }
  } else if (window.cmsCookieConsentSettings) {
    settings = window.cmsCookieConsentSettings;
  }
  var consentEnabled = settings.cookieConsentEnabled !== false;
  var analyticsCategoryEnabled = settings.analyticsCategoryEnabled !== false;
  var marketingCategoryEnabled = settings.marketingCategoryEnabled !== false;
  var enableGtmTrackingLogs = settings.enableGtmTrackingLogs === true;
  var version = '1.0';

  function defaultPreferences(){
    return {
      necessary:true,
      analytics:false,
      marketing:false,
      timestamp:new Date().toISOString(),
      version:version
    };
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
        analytics: analyticsCategoryEnabled && parsed.analytics === true,
        marketing: marketingCategoryEnabled && parsed.marketing === true,
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
      analytics: analyticsCategoryEnabled && preferences && preferences.analytics === true,
      marketing: marketingCategoryEnabled && preferences && preferences.marketing === true,
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
    if (!analyticsCategoryEnabled) return false;
    var preferences = getPreferences();
    return !!(preferences && preferences.analytics === true);
  }

  function canLoadMarketing(){
    if (!consentEnabled) return true;
    if (!marketingCategoryEnabled) return false;
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

  function loadClarity(){
    var id = (settings.clarityId || '').trim();
    if (!id || window.__clarityLoaded || !canLoadAnalytics()) return;
    window.__clarityLoaded = true;
    (function(c,l,a,r,i,t,y){
      c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
      t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+encodeURIComponent(i);
      y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window,document,'clarity','script',id);
  }

  function loadGoogleTagManager(){
    var id = (settings.googleTagManagerId || '').trim();
    if (!id || window.__gtmLoaded || !canLoadMarketing()) return;
    window.__gtmLoaded = true;
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({'gtm.start': new Date().getTime(), event: 'gtm.js'});
    var script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(id);
    document.head.appendChild(script);
  }

  function loadMetaPixel(){
    var id = (settings.metaPixelId || '').trim();
    if (!id || window.__metaPixelLoaded || !canLoadMarketing()) return;
    window.__metaPixelLoaded = true;
    !function(f,b,e,v,n,t,s){
      if(f.fbq)return;n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};
      if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
      n.queue=[];t=b.createElement(e);t.async=!0;
      t.src=v;s=b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t,s);
    }(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
    window.fbq('init', id);
    window.fbq('track', 'PageView');
  }

  function loadAnalyticsScripts(){
    if (!canLoadAnalytics()) return;
    loadGoogleAnalytics();
    loadClarity();
    appendHtml(settings.analyticsHeadScripts || '', 'head', '__analyticsHeadScriptsLoaded');
    appendHtml(settings.analyticsBodyScripts || '', 'body', '__analyticsBodyScriptsLoaded');
  }

  function loadMarketingScripts(){
    if (!canLoadMarketing()) return;
    loadGoogleTagManager();
    loadMetaPixel();
    appendHtml(settings.marketingHeadScripts || '', 'head', '__marketingHeadScriptsLoaded');
    appendHtml(settings.marketingBodyScripts || '', 'body', '__marketingBodyScriptsLoaded');
  }

  function reloadIfTrackingWasDisabled(preferences){
    if (!preferences) return;
    var id = (settings.googleAnalyticsId || '').trim();
    if (!preferences.analytics && id) window['ga-disable-' + id] = true;
    if (
      (!preferences.analytics && (window.__analyticsLoaded || window.__analyticsHeadScriptsLoaded || window.__analyticsBodyScriptsLoaded)) ||
      (!preferences.marketing && (window.__metaPixelLoaded || window.__gtmLoaded || window.__marketingHeadScriptsLoaded || window.__marketingBodyScriptsLoaded))
    ) {
      window.location.reload();
    }
  }

  function logTrackingEvent(eventName, details){
    if (enableGtmTrackingLogs && window.console && window.console.log) {
      console.log('[GA4 Tracking] Event odeslan: ' + eventName, details || '');
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
    loadMarketingScripts: loadMarketingScripts,
    logTrackingEvent: logTrackingEvent
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
