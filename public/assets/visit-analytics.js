/*
 * First-party visit measurement - a fallback next to GA4/GTM, independent of both.
 *
 * Sends one beacon per page load to POST /api/visit. Without explicit analytics
 * consent nothing is stored in the browser (no cookie, no web storage); the server
 * keeps only a daily rotating hash. Only after explicit consent in the cookie bar
 * (window.cmsCookieConsent.enabled && canLoadAnalytics()) a random id is kept in the
 * cms_vid cookie. When the bar is disabled canLoadAnalytics() returns true without
 * any user choice, so cc.enabled is required as well. Never throws, never blocks.
 */
(function(){
  var COOKIE = 'cms_vid';
  var MAX_AGE = 34128000; // ~13 months
  var ENDPOINT = '/api/visit';

  function consentApi(){
    var cc = window.cmsCookieConsent;
    return cc && typeof cc.canLoadAnalytics === 'function' ? cc : null;
  }

  function explicitConsent(){
    try {
      var cc = consentApi();
      return !!(cc && cc.enabled === true && cc.canLoadAnalytics());
    } catch (e) {
      return false;
    }
  }

  function cookieSuffix(){
    return '; Path=/; SameSite=Lax' + (window.location.protocol === 'https:' ? '; Secure' : '');
  }

  function readVisitorId(){
    try {
      var match = document.cookie.match(/(?:^|;\s*)cms_vid=([a-f0-9]{32})(?:;|$)/);
      return match ? match[1] : null;
    } catch (e) {
      return null;
    }
  }

  function randomId(){
    var hex = '';
    var i;
    try {
      var bytes = new Uint8Array(16);
      (window.crypto || window.msCrypto).getRandomValues(bytes);
      for (i = 0; i < bytes.length; i++) {
        hex += (bytes[i] < 16 ? '0' : '') + bytes[i].toString(16);
      }
      return hex;
    } catch (e) {
      hex = '';
      for (i = 0; i < 32; i++) {
        hex += Math.floor(Math.random() * 16).toString(16);
      }
      return hex;
    }
  }

  function setVisitorCookie(){
    try {
      var id = readVisitorId() || randomId();
      document.cookie = COOKIE + '=' + id + '; Max-Age=' + MAX_AGE + cookieSuffix();
      return readVisitorId();
    } catch (e) {
      return null;
    }
  }

  function deleteVisitorCookie(){
    try {
      if (readVisitorId() !== null || document.cookie.indexOf(COOKIE + '=') !== -1) {
        document.cookie = COOKIE + '=; Max-Age=0' + cookieSuffix();
      }
    } catch (e) {}
  }

  function queryParam(search, name){
    var pattern = new RegExp('[?&]' + name + '=([^&#]*)');
    var match = pattern.exec(search || '');
    if (!match) return null;
    try {
      var value = decodeURIComponent(match[1].replace(/\+/g, ' '));
      return value ? value.slice(0, 100) : null;
    } catch (e) {
      return null;
    }
  }

  function send(payload){
    var body = JSON.stringify(payload);
    try {
      if (navigator.sendBeacon && window.Blob) {
        if (navigator.sendBeacon(ENDPOINT, new Blob([body], {type: 'application/json'}))) return;
      }
    } catch (e) {}
    try {
      if (window.fetch) {
        window.fetch(ENDPOINT, {
          method: 'POST',
          body: body,
          headers: {'Content-Type': 'application/json'},
          credentials: 'same-origin',
          keepalive: true
        })['catch'](function(){});
      }
    } catch (e) {}
  }

  function record(){
    try {
      var consent = explicitConsent();
      var vid = null;
      if (consent) {
        vid = setVisitorCookie();
      } else {
        deleteVisitorCookie();
      }
      var search = window.location.search || '';
      send({
        p: String(window.location.pathname || '/').slice(0, 255),
        r: String(document.referrer || '').slice(0, 2048),
        s: queryParam(search, 'utm_source'),
        m: queryParam(search, 'utm_medium'),
        c: queryParam(search, 'utm_campaign'),
        w: window.screen && window.screen.width ? Math.round(window.screen.width) : null,
        v: consent ? vid : null,
        a: consent ? 1 : 0
      });
    } catch (e) {}
  }

  try {
    // Automated browsers and signed-in editors (inline admin bar) are not visitors.
    if (navigator.webdriver) return;
    if (document.getElementById('cms-adminbar')) return;

    // Consent changes only adjust the cookie; they never send a second beacon.
    document.addEventListener('cms-cookie-consent:updated', function(event){
      try {
        var detail = event && event.detail;
        var cc = consentApi();
        if (detail && detail.analytics === true && cc && cc.enabled === true) {
          setVisitorCookie();
        } else if (detail && detail.analytics === false) {
          deleteVisitorCookie();
        }
      } catch (e) {}
    });

    // One beacon per real page load. A back/forward-cache restore (pageshow with
    // persisted=true) does not re-run this script, so it is deliberately not counted.
    // A prerendered page is counted only once the visitor actually opens it.
    if (document.prerendering) {
      document.addEventListener('prerenderingchange', record, {once: true});
    } else {
      record();
    }
  } catch (e) {}
})();
