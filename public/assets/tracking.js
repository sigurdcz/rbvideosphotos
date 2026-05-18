(function () {
  'use strict';

  // ── Polyfill Element.closest for IE11 ──────────────────────────────────────
  if (typeof Element !== 'undefined' && !Element.prototype.closest) {
    Element.prototype.closest = function (sel) {
      var el = this;
      while (el && el.nodeType === 1) {
        if (el.matches ? el.matches(sel) : el.msMatchesSelector(sel)) return el;
        el = el.parentElement || el.parentNode;
      }
      return null;
    };
  }

  // ── Read tracking config from meta tag ────────────────────────────────────
  var configMeta = document.querySelector('meta[name="cms-tracking-config"]');
  var config = {};
  if (configMeta && configMeta.content) {
    try { config = JSON.parse(configMeta.content); } catch (e) {}
  }

  var enabled         = config.trackingEnabled !== false;
  var requireConsent  = config.trackingRequireConsent !== false;
  var debug           = config.trackingDebug === true;
  var events          = config.trackingEvents || {};

  // ── Helpers ────────────────────────────────────────────────────────────────
  function log() {
    if (!debug || !window.console) return;
    var args = ['[AppTracking]'].concat(Array.prototype.slice.call(arguments));
    console.log.apply(console, args);
  }

  function canTrack(eventName) {
    if (!enabled) {
      log('disabled globally');
      return false;
    }
    if (eventName && events[eventName] === false) {
      log('event disabled:', eventName);
      return false;
    }
    if (requireConsent) {
      var cc = window.cmsCookieConsent;
      if (!cc) {
        log('consent runtime not ready');
        return false;
      }
      if (!cc.canLoadAnalytics()) {
        log('consent not given for analytics');
        return false;
      }
    }
    return true;
  }

  function trackEvent(name, params) {
    if (!canTrack(name)) return;
    try {
      var extra = params || {};
      // gtag (GA4 direct)
      if (typeof window.gtag === 'function') {
        window.gtag('event', name, extra);
        log('gtag:', name, extra);
      }
      // dataLayer (GTM)
      if (Array.isArray(window.dataLayer)) {
        var dlEvent = { event: name };
        var k;
        for (k in extra) {
          if (Object.prototype.hasOwnProperty.call(extra, k)) dlEvent[k] = extra[k];
        }
        window.dataLayer.push(dlEvent);
        log('dataLayer:', name, dlEvent);
      }
      // Meta Pixel lead conversion
      if (name === 'lead_form_submit' || name === 'lead_thank_you_page') {
        if (typeof window.fbq === 'function') {
          window.fbq('track', 'Lead');
          log('fbq Lead');
        }
      }
    } catch (e) {
      // fail silently — never crash
    }
  }

  // ── Event: phone click ─────────────────────────────────────────────────────
  function setupPhoneClick() {
    document.addEventListener('click', function (e) {
      var target = e.target;
      var a = target && target.closest ? target.closest('a[href^="tel:"]') : null;
      if (!a) return;
      trackEvent('phone_click', { event_category: 'contact', event_label: a.href });
    });
  }

  // ── Event: email click ─────────────────────────────────────────────────────
  function setupEmailClick() {
    document.addEventListener('click', function (e) {
      var target = e.target;
      var a = target && target.closest ? target.closest('a[href^="mailto:"]') : null;
      if (!a) return;
      var addr = (a.href || '').replace(/^mailto:/i, '').split('?')[0];
      trackEvent('email_click', { event_category: 'contact', event_label: addr });
    });
  }

  // ── Event: CTA click via data attributes ──────────────────────────────────
  function setupCtaClick() {
    document.addEventListener('click', function (e) {
      var target = e.target;
      var el = target && target.closest ? target.closest('[data-track-event]') : null;
      if (!el) return;
      var evtName  = el.getAttribute('data-track-event') || 'cta_click';
      var evtLabel = el.getAttribute('data-track-label') || (el.textContent || '').trim().slice(0, 60);
      trackEvent(evtName, { event_category: 'cta', event_label: evtLabel });
    });
  }

  // ── Event: social click ────────────────────────────────────────────────────
  var socialDomains = [
    { domain: 'instagram.com',  name: 'instagram' },
    { domain: 'facebook.com',   name: 'facebook'  },
    { domain: 'youtube.com',    name: 'youtube'   },
    { domain: 'tiktok.com',     name: 'tiktok'    },
    { domain: 'linkedin.com',   name: 'linkedin'  },
    { domain: 'pinterest.com',  name: 'pinterest' },
    { domain: 'twitter.com',    name: 'twitter'   },
    { domain: 'x.com',          name: 'x'         },
  ];

  function setupSocialClick() {
    document.addEventListener('click', function (e) {
      var target = e.target;
      var a = target && target.closest ? target.closest('a[href]') : null;
      if (!a) return;
      var href = a.getAttribute('href') || '';
      for (var i = 0; i < socialDomains.length; i++) {
        if (href.indexOf(socialDomains[i].domain) !== -1) {
          trackEvent('social_click', { event_category: 'social', event_label: socialDomains[i].name });
          return;
        }
      }
    });
  }

  // ── Event: form submit ────────────────────────────────────────────────────
  function setupFormSubmit() {
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || form.tagName !== 'FORM') return;
      var action = (form.getAttribute('action') || '').toLowerCase();
      var isContact = action.indexOf('kontakt') !== -1 ||
                      action.indexOf('contact') !== -1 ||
                      action.indexOf('odeslat') !== -1 ||
                      (form.closest ? !!form.closest('#kontakt, .contact-form-wrap, .hp-contact-form, .kt-form-block') : false);
      if (!isContact) return;
      trackEvent('lead_form_submit', { event_category: 'lead', event_label: action });
    });
  }

  // ── Event: thank-you page ─────────────────────────────────────────────────
  function setupThankYouPage() {
    var path = window.location.pathname;
    if (path === '/dekujeme' || path.indexOf('/dekujeme') === 0) {
      trackEvent('lead_thank_you_page', { event_category: 'lead', event_label: 'thank_you_page' });
    }
  }

  // ── Event: gallery open ───────────────────────────────────────────────────
  function setupGalleryOpen() {
    var tracked = false;
    document.addEventListener('click', function (e) {
      if (tracked) return;
      var target = e.target;
      var el = target && target.closest
        ? target.closest('.pt-tile, .pf-tile, .gallery-item, [data-gallery], .portfolio-item, .pf-detail-hero')
        : null;
      if (!el) return;
      tracked = true;
      trackEvent('gallery_open', { event_category: 'engagement' });
    });
  }

  // ── Event: video play ─────────────────────────────────────────────────────
  function setupVideoPlay() {
    var tracked = false;

    function fireVideoPlay(label) {
      if (tracked) return;
      tracked = true;
      trackEvent('video_play', { event_category: 'video', event_label: label || 'hero' });
    }

    // Native <video>
    document.addEventListener('play', function (e) {
      if (e.target && e.target.tagName === 'VIDEO') fireVideoPlay('video_element');
    }, true);

    // Video toggle button (theme uses .eg-video-toggle)
    document.addEventListener('click', function (e) {
      var target = e.target;
      var btn = target && target.closest ? target.closest('.eg-video-toggle, [data-video-play]') : null;
      if (!btn) return;
      fireVideoPlay('video_toggle');
    });

    // YouTube iframe postMessage (YouTube API)
    window.addEventListener('message', function (e) {
      if (!e.data) return;
      try {
        var data = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
        if (data && (data.event === 'video-play' ||
            (data.info && typeof data.info.playerState === 'number' && data.info.playerState === 1))) {
          fireVideoPlay('youtube_iframe');
        }
      } catch (ex) {}
    });
  }

  // ── Event: pricing click ──────────────────────────────────────────────────
  function setupPricingClick() {
    document.addEventListener('click', function (e) {
      var target = e.target;
      var el = target && target.closest
        ? target.closest('.hp-cenik-card, .pricing-card, [data-pricing], #cenik a')
        : null;
      if (!el) return;
      var titleEl = el.querySelector('.hp-cenik-card-title, h3, h2');
      var label   = titleEl ? (titleEl.textContent || '').trim() : 'pricing';
      trackEvent('pricing_click', { event_category: 'engagement', event_label: label });
    });
  }

  // ── Event: scroll 90% ─────────────────────────────────────────────────────
  function setupScroll90() {
    var tracked = false;
    window.addEventListener('scroll', function () {
      if (tracked) return;
      var scrolled = window.scrollY + window.innerHeight;
      var total    = document.documentElement.scrollHeight;
      if (total > 0 && scrolled / total >= 0.9) {
        tracked = true;
        trackEvent('scroll_90', { event_category: 'engagement' });
      }
    }, { passive: true });
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  function init() {
    if (!enabled) {
      log('AppTracking: globally disabled, skipping init');
      return;
    }
    log('AppTracking init', { requireConsent: requireConsent, debug: debug, events: events });

    if (events.phone_click         !== false) setupPhoneClick();
    if (events.email_click         !== false) setupEmailClick();
    if (events.cta_click           !== false) setupCtaClick();
    if (events.social_click        !== false) setupSocialClick();
    if (events.lead_form_submit    !== false) setupFormSubmit();
    if (events.gallery_open        !== false) setupGalleryOpen();
    if (events.video_play          !== false) setupVideoPlay();
    if (events.pricing_click       !== false) setupPricingClick();
    if (events.scroll_90           !== false) setupScroll90();

    // Thank-you page fires immediately
    if (events.lead_thank_you_page !== false) setupThankYouPage();
  }

  // ── Public API ────────────────────────────────────────────────────────────
  window.AppTracking = {
    init:        init,
    canTrack:    canTrack,
    trackEvent:  trackEvent
  };

  // Re-check after consent granted
  document.addEventListener('cms-cookie-consent:analytics', function () {
    log('Analytics consent received');
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
