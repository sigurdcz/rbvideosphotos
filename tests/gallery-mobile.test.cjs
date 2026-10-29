// Requires Playwright supplied by the development environment; no app dependency.
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium, webkit } = require('playwright');
const root = path.join(__dirname, '..');
const image = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="600" height="400" fill="#b77a20"/></svg>');
const second = image.replace('b77a20', '201d1b');
const photos = JSON.stringify([{ src: image, thumb: image, alt: 'First photo' }, { src: second, thumb: second, alt: 'Second photo' }]);
const fixture = `<html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="theme-golden-bash theme-golden-bash-diagonal" data-cms-page-key="nase-prace"><main class="gb-main"><section class="gb-work-listing">
<a class="pt-card" id="gallery" href="/first-detail"><div class="pt-card__media"><img src="${image}" alt="First cover"></div><h3 class="pt-title">Touch gallery</h3><script type="application/json" class="pt-photos-data">${photos}</script></a>
<a class="pt-card" id="legacy" href="/legacy-detail"><div class="pt-card__media"><img src="${image}" alt="Legacy cover"></div><h3 class="pt-title">Legacy single cover</h3></a>
<a class="pt-card" id="invalid" href="/invalid-detail"><div class="pt-card__media"><img src="${image}" alt="Invalid data cover"></div><h3 class="pt-title">Invalid data</h3><script type="application/json" class="pt-photos-data">{bad</script></a>
<a class="pt-card" id="video" href="/video-detail" data-video-embed="about:blank"><h3 class="pt-title">Explicit empty video layout fixture</h3></a>
</section></main></body></html>`;

(async () => {
  for (const [name, engine, options] of [
    ['chromium', chromium, { channel: 'chrome' }],
    ['webkit', webkit, process.env.TEST_WEBKIT_EXECUTABLE ? { executablePath: process.env.TEST_WEBKIT_EXECUTABLE } : {}],
  ]) {
    const browser = await engine.launch({ headless: true, ...options });
    try {
      const context = await browser.newContext({ viewport: { width: 393, height: 852 }, isMobile: true, hasTouch: true, deviceScaleFactor: 1 });
      const page = await context.newPage();
      page.setDefaultTimeout(5000);
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.setContent(fixture);
      await page.addStyleTag({ path: path.join(root, 'Templates/golden-bash-diagonal/assets/theme.css') });
      await page.addScriptTag({ path: path.join(root, 'Templates/golden-bash-diagonal/assets/theme.js') });
      await page.locator('#gallery').tap();
      await page.locator('.gb-work-lightbox.is-open').waitFor({ state: 'visible' });
      const bounds = await page.locator('.gb-work-lightbox__dialog').boundingBox();
      assert.deepEqual(bounds, { x: 0, y: 0, width: 393, height: 852 }, 'Photo viewer must fill the viewport without a framed margin.');
      assert.equal(await page.locator('.gb-work-lightbox__dialog').evaluate(e => getComputedStyle(e).borderRadius), '0px');
      assert.equal(await page.locator('.gb-work-lightbox__thumb').count(), 2);
      assert.equal(await page.locator('.gb-work-lightbox__caption a').getAttribute('href'), '/first-detail');
      await page.locator('.gb-work-lightbox__arrow--next').tap();
      assert.equal(await page.locator('.gb-work-lightbox__image-button img').getAttribute('alt'), 'Second photo');
      await page.locator('.gb-work-lightbox__close').tap();
      await page.locator('.gb-work-lightbox').waitFor({ state: 'hidden' });
      await page.locator('#legacy').tap();
      await page.locator('.gb-work-lightbox.is-open').waitFor({ state: 'visible' });
      assert.equal(await page.locator('.gb-work-lightbox__thumb').count(), 1, 'Old listing must recognize its .pt-card__media cover.');
      // A fast reopen before the close animation finishes must cancel its stale timer.
      await page.locator('.gb-work-lightbox__close').dispatchEvent('click');
      await page.locator('#gallery').dispatchEvent('click');
      await page.waitForTimeout(350);
      assert(await page.locator('.gb-work-lightbox').isVisible());
      await page.locator('.gb-work-lightbox__close').tap();
      await page.locator('.gb-work-lightbox').waitFor({ state: 'hidden' });
      await page.locator('#invalid').tap();
      assert.equal(await page.locator('#invalid').getAttribute('data-gallery-error'), 'invalid-data');
      assert(!(await page.locator('.gb-work-lightbox').isVisible()), 'Invalid primary data must not silently use its cover.');
      await page.locator('#video').tap();
      await page.locator('.gb-work-lightbox.is-video.is-open').waitFor({ state: 'visible' });
      assert.deepEqual(await page.locator('.gb-work-lightbox__dialog').boundingBox(), bounds, 'Video viewer must use the same edge-to-edge dialog.');
      const frame = await page.locator('.gb-work-lightbox__video iframe').boundingBox();
      assert.equal(frame.width, 393);
      assert(frame.height > 600, 'Video viewport must use available height. Fixture does not test provider playback.');
      await page.locator('.gb-work-lightbox__close').tap();
      await page.locator('.gb-work-lightbox').waitFor({ state: 'hidden' });
      assert.equal(await page.locator('.gb-work-lightbox__video iframe').getAttribute('src'), null, 'Closing must stop the video.');
      await page.setViewportSize({width:1280,height:800});
      await page.locator('#gallery').tap();
      await page.locator('.gb-work-lightbox.is-open').waitFor({state:'visible'});
      assert.deepEqual(await page.locator('.gb-work-lightbox__dialog').boundingBox(), {x:0,y:0,width:1280,height:800});
      assert((await page.locator('.gb-work-lightbox__close i').boundingBox()).width > 0, 'Desktop close icon must remain visible.');
      assert.deepEqual(errors, []);
      console.log(`PASS ${name}: fullscreen mobile photo/video and desktop, touch, next/close, stopped video, legacy cover, fast reopen and invalid-data diagnostics.`);
      await context.close();
    } finally { await browser.close(); }
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
