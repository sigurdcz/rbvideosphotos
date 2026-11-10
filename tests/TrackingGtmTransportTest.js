const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../public/assets/tracking.js'), 'utf8');

function runtime(mode, preferences = { analytics: true, marketing: true }) {
  const listeners = {};
  const context = {
    console: { log() {}, info() {}, warn() {}, error() {} },
    Element: undefined,
    Object,
    Array,
    JSON,
    String,
    document: {
      readyState: 'complete',
      documentElement: { scrollHeight: 1000 },
      querySelector(selector) {
        if (selector === 'meta[name="cms-tracking-config"]') {
          return { content: JSON.stringify({ trackingEnabled: true, trackingRequireConsent: true, trackingEvents: {} }) };
        }
        return null;
      },
      addEventListener(type, callback) { listeners[type] = callback; },
    },
    location: { pathname: '/', search: '' },
    innerHeight: 800,
    scrollY: 0,
    addEventListener(type, callback) { listeners['window:' + type] = callback; },
    removeEventListener() {},
    cmsTrackingRuntime: { mode },
    cmsCookieConsent: {
      canLoadAnalytics: () => preferences.analytics,
      canLoadMarketing: () => preferences.marketing,
    },
  };
  context.window = context;
  vm.runInNewContext(source, context, { filename: 'tracking.js' });
  return context;
}

const gtm = runtime('gtm');
assert.equal(gtm.AppTracking.trackEvent('phone_click', { event_label: 'tel:+420' }), true);
assert.deepEqual(
  JSON.parse(JSON.stringify(gtm.dataLayer.at(-1))),
  { event_label: 'tel:+420', event: 'phone_click' },
  'GTM mode must push a custom-event object into dataLayer.'
);

const direct = runtime('direct-ga');
const calls = [];
direct.gtag = (...args) => calls.push(args);
assert.equal(direct.AppTracking.trackEvent('email_click', { event_label: 'team@example.cz' }), true);
assert.deepEqual(calls[0], ['event', 'email_click', { event_label: 'team@example.cz' }]);

const blocked = runtime('gtm', { analytics: true, marketing: false });
assert.equal(blocked.AppTracking.trackEvent('cta_click'), false);
assert.equal(blocked.dataLayer, undefined, 'GTM events must not queue before marketing consent.');

console.log('PASS GTM dataLayer transport, direct GA transport and consent block');
