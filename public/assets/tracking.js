(function () {
  'use strict';

  // Polyfill Element.closest for IE11
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

  // Read tracking config from meta tag
  var configMeta = document.querySelector('meta[name="cms-tracking-config"]');
  var config = {};
  if (configMeta && configMeta.content) {
    try { config = JSON.parse(configMeta.content); } catch (e) {}
  }

  var enabled         = config.trackingEnabled !== false;
  var requireConsent  = config.trackingRequireConsent !== false;
  var debug           = config.trackingDebug === true;
  var events          = config.trackingEvents || {};

  // Helpers
  function log() {
    if (!debug || !window.console) return;
    var args = ['[AppTracking]'].concat(Array.prototype.slice.call(arguments));
    console.log.apply(console, args);
  }

  function audit(level, message, payload) {
    if (!window.console) return;
    var method = typeof console[level] === 'function' ? console[level] : console.log;
    if (typeof payload === 'undefined') {
      method.call(console, '[GA4 Tracking] ' + message);
      return;
    }
    method.call(console, '[GA4 Tracking] ' + message, payload);
  }

  function getTrackBlockReason(eventName) {
    if (!enabled) {
      log('disabled globally');
      return 'tracking_disabled';
    }
    if (eventName && events[eventName] === false) {
      log('event disabled:', eventName);
      return 'event_disabled';
    }
    if (requireConsent) {
      var cc = window.cmsCookieConsent;
      if (!cc) {
        log('consent runtime not ready');
        return 'consent_runtime_not_ready';
      }
      if (!cc.canLoadAnalytics()) {
        log('consent not given for analytics');
        return 'analytics_consent_missing';
      }
      if (window.cmsTrackingRuntime && window.cmsTrackingRuntime.mode === 'gtm' && !cc.canLoadMarketing()) {
        log('consent not given for GTM');
        return 'marketing_consent_missing';
      }
    }
    return null;
  }

  function canTrack(eventName) {
    return getTrackBlockReason(eventName) === null;
  }

  function dispatchAnalyticsEvent(name, params) {
    var extra = params || {};
    var mode = window.cmsTrackingRuntime ? window.cmsTrackingRuntime.mode : 'none';

    if (mode === 'gtm') {
      window.dataLayer = window.dataLayer || [];
      var payload = {};
      Object.keys(extra).forEach(function (key) { payload[key] = extra[key]; });
      payload.event = name;
      window.dataLayer.push(payload);
      return { sent: true, transport: 'dataLayer' };
    }

    if (typeof window.gtag === 'function') {
      window.gtag('event', name, extra);
      return { sent: true, transport: 'gtag' };
    }

    return { sent: false, transport: 'unavailable' };
  }

  function hasExplicitAnalyticsConsent() {
    try {
      var cc = window.cmsCookieConsent;
      return !!(cc && cc.enabled === true && cc.canLoadAnalytics());
    } catch (e) {
      return false;
    }
  }

  function recordFirstPartyEvent(name) {
    if (typeof navigator !== 'undefined' && navigator.webdriver) return;
    if (typeof document.getElementById === 'function' && document.getElementById('cms-adminbar')) return;
    if (typeof window.fetch !== 'function') {
      audit('warn', 'Lokalni event neulozen', { eventName: name, reason: 'fetch_unavailable' });
      return;
    }
    var body = JSON.stringify({
      e: name,
      p: String(window.location.pathname || '/').slice(0, 255),
      a: hasExplicitAnalyticsConsent() ? 1 : 0
    });
    window.fetch('/api/tracking-event', {
      method: 'POST',
      body: body,
      headers: {'Content-Type': 'application/json'},
      credentials: 'same-origin',
      keepalive: true
    }).then(function (response) {
      if (!response.ok) {
        audit('warn', 'Lokalni event neulozen', { eventName: name, reason: 'http_' + response.status });
      }
    })['catch'](function () {
      audit('warn', 'Lokalni event neulozen', { eventName: name, reason: 'network_error' });
    });
  }

  function trackEvent(name, params) {
    var blockReason = getTrackBlockReason(name);
    var extra = params || {};

    if (blockReason !== null) {
      audit('warn', 'Event neodeslan', { eventName: name, reason: blockReason, params: extra });
      return false;
    }

    try {
      var result = dispatchAnalyticsEvent(name, extra);
      if (!result.sent) {
        audit('warn', 'Event neodeslan', { eventName: name, reason: 'analytics_transport_unavailable', params: extra });
        return false;
      }

      audit('info', 'Event odeslan', { eventName: name, transport: result.transport, params: extra });
      log(result.transport + ':', name, extra);
      recordFirstPartyEvent(name);

      // GA4 standard conversion events + Meta Pixel
      if (name === 'lead_form_submit' || name === 'thank_you_page_view') {
        if (typeof window.fbq === 'function') {
          window.fbq('track', 'Lead');
          log('fbq Lead');
        }
        dispatchAnalyticsEvent('generate_lead', { source_event: name });
        audit('info', 'Event odeslan', { eventName: 'generate_lead', sourceEvent: name, transport: result.transport });
        log(result.transport + ': generate_lead');
      }
      if (name === 'social_click') {
        if (typeof window.fbq === 'function') {
          window.fbq('track', 'Contact');
          log('fbq Contact');
        }
      }
      return true;
    } catch (e) {
      audit('error', 'Event selhal', {
        eventName: name,
        reason: 'exception',
        message: e && e.message ? e.message : String(e),
        params: extra
      });
      return false;
    }
  }

  // Event: phone click
  function setupPhoneClick() {
    document.addEventListener('click', function (e) {
      var target = e.target;
      var a = target && target.closest ? target.closest('a[href^="tel:"]') : null;
      if (!a) return;
      trackEvent('phone_click', { event_category: 'contact', event_label: a.href });
    });
  }

  // Event: email click
  function setupEmailClick() {
    document.addEventListener('click', function (e) {
      var target = e.target;
      var a = target && target.closest ? target.closest('a[href^="mailto:"]') : null;
      if (!a) return;
      var addr = (a.href || '').replace(/^mailto:/i, '').split('?')[0];
      trackEvent('email_click', { event_category: 'contact', event_label: addr });
    });
  }

  // Event: CTA click via data attributes
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

  // Event: social click
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

  // Event: form submit
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

  // Event: thank-you page / ?sent=1
  function setupThankYouPage() {
    var path   = window.location.pathname;
    var search = window.location.search;
    if (path === '/dekujeme' || path.indexOf('/dekujeme') === 0) {
      trackEvent('thank_you_page_view', { event_category: 'lead', event_label: 'thank_you_page' });
    } else if (search.indexOf('sent=1') !== -1) {
      trackEvent('lead_form_submit', { event_category: 'lead', event_label: 'sent_param' });
    }
  }

  // Event: gallery open
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
      var label = el.getAttribute('href') || el.getAttribute('data-gallery') || 'gallery';
      trackEvent('gallery_open', { event_category: 'engagement', event_label: label });
      trackEvent('gallery_image_view', { event_category: 'engagement', event_label: label, image_index: 1 });
    });
  }

  function setupGalleryInteractions() {
    document.addEventListener('click', function (e) {
      var target = e.target;
      var filter = target && target.closest
        ? target.closest('[data-portfolio-filter], [data-gallery-filter], [data-filter]')
        : null;
      if (filter) {
        trackEvent('gallery_category_click', {
          event_category: 'engagement',
          event_label: filter.getAttribute('data-portfolio-filter') || filter.getAttribute('data-gallery-filter') || filter.getAttribute('data-filter') || 'all'
        });
        return;
      }

      var imageControl = target && target.closest
        ? target.closest('.gb-work-lightbox__thumb, .gb-work-lightbox__arrow, .gb-work-lightbox__image-button, [data-gallery-index]')
        : null;
      if (!imageControl) return;
      trackEvent('gallery_image_view', {
        event_category: 'engagement',
        event_label: imageControl.getAttribute('data-gallery-index') || imageControl.getAttribute('aria-label') || 'gallery_navigation'
      });
    });
  }

  function setupContactSectionView() {
    var section = document.querySelector('#kontakt, .gb-contact, .contact-section, [data-contact-section]');
    if (!section || typeof window.IntersectionObserver !== 'function') return;
    var observer = new window.IntersectionObserver(function (entries) {
      for (var i = 0; i < entries.length; i++) {
        if (!entries[i].isIntersecting) continue;
        observer.disconnect();
        trackEvent('contact_section_view', { event_category: 'engagement' });
        return;
      }
    }, { threshold: 0.35 });
    observer.observe(section);
  }

  function setupServiceDetailView() {
    var servicePaths = [
      '/svatebni-foto-video',
      '/rodinne-a-portretni-foceni',
      '/reklamni-foto-video',
      '/rande-a-zasnuby'
    ];
    var path = (window.location.pathname || '').replace(/\/$/, '');
    if (servicePaths.indexOf(path) === -1) return;
    trackEvent('service_detail_view', { event_category: 'engagement', event_label: path });
  }

  function setupFileDownload() {
    document.addEventListener('click', function (e) {
      var target = e.target;
      var link = target && target.closest ? target.closest('a[href]') : null;
      if (!link) return;
      var href = link.getAttribute('href') || '';
      if (!/\.(?:pdf|docx?|xlsx?|zip)(?:[?#]|$)/i.test(href)) return;
      trackEvent('file_download', { event_category: 'outbound', event_label: href });
    });
  }

  function setupMapClick() {
    document.addEventListener('click', function (e) {
      var target = e.target;
      var link = target && target.closest
        ? target.closest('a[href*="maps.google"], a[href*="google.com/maps"], a[href*="maps.app.goo.gl"], a[href*="mapy.cz"], [data-map-link]')
        : null;
      if (!link) return;
      trackEvent('map_click', { event_category: 'outbound', event_label: link.href || 'map' });
    });
  }

  // Event: video play
  function setupVideoPlay() {
    var started = [];

    function fireVideoStart(label, element) {
      if (element && started.indexOf(element) !== -1) return;
      if (element) started.push(element);
      trackEvent('video_play', { event_category: 'video', event_label: label || 'hero' });
    }

    // Native <video>
    document.addEventListener('play', function (e) {
      if (e.target && e.target.tagName === 'VIDEO') fireVideoStart('video_element', e.target);
    }, true);
    document.addEventListener('ended', function (e) {
      if (e.target && e.target.tagName === 'VIDEO') {
        trackEvent('video_complete', { event_category: 'video', event_label: 'video_element' });
      }
    }, true);

    // Video toggle button (theme uses .eg-video-toggle)
    document.addEventListener('click', function (e) {
      var target = e.target;
      var btn = target && target.closest ? target.closest('.eg-video-toggle, [data-video-play]') : null;
      if (!btn) return;
      fireVideoStart('video_toggle', btn);
    });

    // YouTube iframe postMessage (YouTube API)
    window.addEventListener('message', function (e) {
      if (!e.data) return;
      try {
        var data = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
        if (data && (data.event === 'video-play' ||
            (data.info && typeof data.info.playerState === 'number' && data.info.playerState === 1))) {
          fireVideoStart('youtube_iframe');
        }
        if (data && (data.event === 'video-complete' ||
            (data.info && typeof data.info.playerState === 'number' && data.info.playerState === 0))) {
          trackEvent('video_complete', { event_category: 'video', event_label: 'youtube_iframe' });
        }
      } catch (ex) {}
    });
  }

  // Event: pricing click
  function setupPricingClick() {
    document.addEventListener('click', function (e) {
      var target = e.target;
      var el = target && target.closest
        ? target.closest('.hp-cenik-card, .pricing-card, [data-pricing], #cenik a')
        : null;
      if (!el) return;
      var titleEl = el.querySelector('.hp-cenik-card-title, h3, h2');
      var label   = titleEl ? (titleEl.textContent || '').trim() : 'pricing';
      trackEvent('select_package', { event_category: 'engagement', event_label: label });
    });
  }

  function setupGoogleReviewsClick() {
    document.addEventListener('click', function (e) {
      var target = e.target;
      var a = target && target.closest ? target.closest('a[href*="maps.app.goo.gl"], [data-google-reviews]') : null;
      if (!a) return;
      trackEvent('view_google_reviews', { event_category: 'outbound', event_label: a.href || 'google_reviews' });
    });
  }

  // Event: scroll 90%
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

  // Init
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
    if (events.gallery_image_view  !== false || events.gallery_category_click !== false) setupGalleryInteractions();
    if (events.video_play          !== false || events.video_complete !== false) setupVideoPlay();
    if (events.select_package      !== false) setupPricingClick();
    if (events.view_google_reviews !== false) setupGoogleReviewsClick();
    if (events.scroll_90           !== false) setupScroll90();
    if (events.contact_section_view !== false) setupContactSectionView();
    if (events.service_detail_view !== false) setupServiceDetailView();
    if (events.file_download       !== false) setupFileDownload();
    if (events.map_click           !== false) setupMapClick();

    // Thank-you page fires immediately
    if (events.thank_you_page_view !== false) setupThankYouPage();
  }

  // Public API
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
