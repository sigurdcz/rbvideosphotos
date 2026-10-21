const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/frontend-runtime.js', 'utf8');
const start = source.indexOf('  function initHeroVideoFacades()');
const end = source.indexOf('  initActiveNav();', start);
let width = 700, frame = null, clicks = {}, queries = [], players = 0, cleaned = 0;
const attrs = {
  'data-embed-url': 'https://player.vimeo.com/video/111',
  'data-embed-url-tablet': 'https://player.vimeo.com/video/222',
  'data-embed-url-mobile': 'https://player.vimeo.com/video/333',
};
const facade = {
  dataset: {}, classList: {add() {}}, setAttribute() {},
  getAttribute: key => attrs[key] ?? null,
  appendChild: value => { frame = value; players++; },
  querySelector: selector => selector === 'iframe' ? frame : selector === '.theme-hero-video__play' ? {
    addEventListener: (name, fn) => { clicks[name] = fn; }, remove() {},
  } : {textContent: ''},
};
const window = {matchMedia: query => {
  const max = Number(query.match(/(\d+)px/)[1]);
  const item = {max, get matches() { return width <= max; }, addEventListener: (_, fn) => { item.changed = fn; }};
  queries.push(item); return item;
}};
const document = {
  querySelectorAll: (selector) => selector === '[data-hero-video-facade]' ? [facade] : [],
  createElement: () => ({getAttribute(name) {return this[name];}, remove() {frame = null;}}),
};
const context = {window, document, initHeroVideoCover: () => () => {cleaned++;}};
vm.createContext(context);
vm.runInContext(source.slice(start, end), context);
context.initHeroVideoFacades();
clicks.click();
assert.equal(frame.src, attrs['data-embed-url-mobile']);
function resize(next) {
  const changes = queries.filter(q => q.changed && (width <= q.max) !== (next <= q.max));
  width = next; changes.forEach(q => q.changed());
}
resize(701); assert.equal(frame.src, attrs['data-embed-url-tablet']);
resize(1050); assert.equal(frame.src, attrs['data-embed-url-tablet']);
assert.equal(players, 2, 'same tier must not restart the player');
resize(1051); assert.equal(frame.src, attrs['data-embed-url']);
resize(390); assert.equal(frame.src, attrs['data-embed-url-mobile']);
assert.equal(players, 4, 'each source change creates exactly one new player');
assert.equal(cleaned, 3, 'old metadata listeners must be cleaned on every replacement');
console.log('HeroVideoBreakpoint: PASS 700/701/1050/1051, resize selection, no same-tier restart, old-player cleanup');
