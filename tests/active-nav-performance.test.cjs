const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../public/assets/frontend-runtime.js'), 'utf8');
const start = source.indexOf('  function initActiveNav()');
const end = source.indexOf('  function initPricingModal()', start);
assert(start >= 0 && end > start, 'Navigation function boundaries must exist.');

function setup(pathname = '/', hash = '') {
  let writes = 0;
  let callback;
  const sections = { one: { id: 'one' }, two: { id: 'two' } };
  // Deliberately no getBoundingClientRect: navigation must use observer snapshots.
  const links = ['/', '/#one', '/#two', '/pricing'].map(href => {
    const classes = new Set();
    return {
      href,
      getAttribute: () => href,
      classList: {
        toggle(name, enabled) { writes++; if (enabled) classes.add(name); else classes.delete(name); },
        contains: name => classes.has(name),
      },
    };
  });
  const observed = [];
  const context = {
    URL, Map,
    window: { location: { pathname, hash, origin: 'https://example.test' } },
    document: {
      querySelectorAll: () => links,
      getElementById: id => sections[id],
      querySelector: selector => links.find(link => link.href.endsWith(hash) && selector.includes(hash)),
    },
    IntersectionObserver: function (handler) {
      callback = handler;
      this.observe = element => observed.push(element);
    },
  };
  vm.runInNewContext(source.slice(start, end) + '\ninitActiveNav();', context);
  return { links, sections, observed, emit: entries => callback(entries), writes: () => writes };
}
function snapshot(target, isIntersecting, top = 390) {
  return { target, isIntersecting, boundingClientRect: { top }, rootBounds: { top: 400, height: 200 } };
}
const home = setup();
assert.equal(home.observed.length, 2);
assert(home.links[0].classList.contains('nav-active'));
home.emit([snapshot(home.sections.one, true)]);
assert(home.links[1].classList.contains('nav-active'));
const unchangedWrites = home.writes();
home.emit([snapshot(home.sections.one, true)]);
assert.equal(home.writes(), unchangedWrites, 'Same active section must not rewrite classes.');
home.emit([snapshot(home.sections.one, false), snapshot(home.sections.two, true)]);
assert(home.links[2].classList.contains('nav-active'));
assert(!home.links[1].classList.contains('nav-active'));
home.emit([snapshot(home.sections.two, false)]);
assert(home.links[2].classList.contains('nav-active'), 'Gaps keep the last active section.');
const deepLink = setup('/', '#two');
assert(deepLink.links[2].classList.contains('nav-active'), 'Initial hash must activate its section without layout reads.');
const innerPage = setup('/pricing');
assert(innerPage.links[3].classList.contains('nav-active'));
assert.equal(innerPage.observed.length, 0);
console.log('PASS: active navigation uses observer snapshots, preserves hashes/routes and skips unchanged class writes.');
