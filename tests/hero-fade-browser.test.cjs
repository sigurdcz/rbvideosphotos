const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const root = path.join(__dirname, '..');
const fixture = JSON.parse(execFileSync('php', [path.join(__dirname, 'hero-fade-fixture.php')], { encoding: 'utf8' }));

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true, args: ['--force-device-scale-factor=1'] });
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: 1 });
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('Network.enable');
    await cdp.send('Network.setCacheDisabled', { cacheDisabled: true });
    await cdp.send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 800, deviceScaleFactor: 1, mobile: false });
    // Styles are the real normalized/pruned asset bytes emitted by PHP.
    await page.setContent(fixture.html.replace(/<link[^>]*href="[^"]+\.css"[^>]*>/g, ''));
    await page.addStyleTag({ content: fixture.css });
    const state = () => page.evaluate(() => {
      const hero = document.querySelector('.hp-hero');
      const media = hero.querySelector('.theme-hero-video');
      const fade = getComputedStyle(hero, '::before');
      return { width: innerWidth, scrollWidth: document.documentElement.scrollWidth, clientWidth: document.documentElement.clientWidth,
        mask: getComputedStyle(media).maskImage, webkitMask: getComputedStyle(media).webkitMaskImage,
        filter: getComputedStyle(media).filter, fadeDisplay: fade.display, fadeBackground: fade.backgroundImage,
        fadePointerEvents: fade.pointerEvents, fadeZ: fade.zIndex, contentZ: getComputedStyle(hero.querySelector('.container')).zIndex };
    });
    const desktop = await state();
    assert.equal(desktop.mask, 'none');
    assert.equal(desktop.webkitMask, 'none');
    assert.equal(desktop.filter, 'none');
    assert.equal(desktop.fadeDisplay, 'block');
    assert(desktop.fadeBackground.includes('30%') && desktop.fadeBackground.includes('58%'));
    assert.equal(desktop.fadePointerEvents, 'none');
    assert(Number(desktop.contentZ) > Number(desktop.fadeZ));
    assert.equal(desktop.scrollWidth, desktop.clientWidth);
    const actions = page.locator('.hero-cta-wrap a');
    const navigation = page.locator('.nav-links a');
    for (let n = 0; n < 12; n++) {
      await actions.nth(n % 2).hover();
      await navigation.nth(n % 2).hover();
    }
    assert.deepEqual(await state(), desktop, 'Hover must not change media mask, fade or stacking geometry.');
    const output = path.join(root, '.codex-qa/hero-fade-repaint-fixture.png');
    fs.mkdirSync(path.dirname(output), { recursive: true });
    const capture = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
    fs.writeFileSync(output, Buffer.from(capture.data, 'base64'));
    for (const width of [700, 701]) {
      await page.setViewportSize({ width, height: 852 });
      const current = await state();
      assert.equal(current.fadeDisplay, width === 700 ? 'none' : 'block', 'Mobile must retain its existing separate design.');
    }
    console.log(JSON.stringify({ result: 'PASS', desktop, generatedAssets: fixture.assets, screenshot: output, note: 'Synthetic surface verifies CSS stacking; actual hardware video flicker and physical iPhone still require confirmation.' }));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
