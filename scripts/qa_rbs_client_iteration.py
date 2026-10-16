import json
import sys
from pathlib import Path

from playwright.sync_api import sync_playwright


BASE_URL = "http://127.0.0.1:8766"
CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
OUT = Path("storage/qa/rbs-client-iteration")
VIEWPORTS = {
    "desktop": {"width": 1440, "height": 900},
    "tablet": {"width": 768, "height": 1024},
    "mobile": {"width": 390, "height": 844},
}


def inspect(page, path, settle_ms=800):
    response = page.goto(BASE_URL + path, wait_until="domcontentloaded", timeout=30_000)
    page.wait_for_timeout(settle_ms)
    return page.evaluate(
        """() => ({
          statusText: document.readyState,
          title: document.title,
          weddingStories: document.body.innerText.toLowerCase().includes('wedding stories'),
          brandText: [...document.querySelectorAll('.gb-brand strong,.site-footer-brand__copy strong')].map(n => n.textContent.trim()),
          logoSources: [...document.querySelectorAll('header img,footer img')].map(n => n.currentSrc || n.src),
          overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
          photoHeading: !!document.querySelector('#fotogalerie .portfolio-media-group__title'),
          videoHeading: !!document.querySelector('#videogalerie .portfolio-media-group__title'),
          photoCards: document.querySelectorAll('#fotogalerie .pt-card').length,
          videoCards: document.querySelectorAll('#videogalerie .pt-card').length,
          sharedProcessCount: document.querySelectorAll('.gb-shared-process').length,
          sharedProcessTitle: document.querySelector('.gb-shared-process h2')?.textContent.trim() || '',
          headerBrand: (() => {
            const header = document.querySelector('header');
            const brand = header?.querySelector('.gb-brand,.brand,.site-logo,.site-brand');
            const image = brand?.querySelector('img');
            const copy = brand?.querySelector('.gb-brand__copy strong,strong,.site-logo-text') || brand;
            if (!header || !brand) return null;
            const brandStyle = getComputedStyle(brand);
            const copyStyle = copy ? getComputedStyle(copy) : null;
            const imageStyle = image ? getComputedStyle(image) : null;
            return {
              headerClass: header.className || '',
              brandClass: brand.className || '',
              text: (copy?.textContent || '').trim(),
              html: brand.outerHTML,
              fontWeight: copyStyle?.fontWeight || '',
              fontFamily: copyStyle?.fontFamily || '',
              display: brandStyle.display,
              imageSrc: image?.currentSrc || image?.src || '',
              imageDisplay: imageStyle?.display || '',
              imageVisible: !!image && imageStyle.display !== 'none' && image.getBoundingClientRect().width > 0 && image.getBoundingClientRect().height > 0,
            };
          })(),
          overflowElements: [...document.querySelectorAll('body *')].map(n => { const r=n.getBoundingClientRect(); return {tag:n.tagName,cls:n.className||'',left:Math.round(r.left),right:Math.round(r.right),width:Math.round(r.width)}; }).filter(x => x.right > document.documentElement.clientWidth + 2 || x.left < -2).sort((a,b) => b.right-a.right).slice(0,12),
        })"""
    ) | {"httpStatus": response.status if response else 0, "path": path}


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    if "--header-only" in sys.argv:
        routes = ["/", "/o-nas", "/hodnoceni", "/kontakt", "/sluzby", "/svatebni-foto-video", "/nase-prace", "/kamila-a-matej", "/faq", "/blog", "/jak-vybrat-misto-na-foceni"]
        report = {"viewports": {}}
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True, executable_path=CHROME)
            for name, viewport in VIEWPORTS.items():
                context = browser.new_context(viewport=viewport, device_scale_factor=1)
                context.route("**/*", lambda route: route.continue_() if route.request.url.startswith(BASE_URL) else route.abort())
                page = context.new_page()
                report["viewports"][name] = [
                    {"path": route, "httpStatus": (result := inspect(page, route, settle_ms=100))["httpStatus"], "headerBrand": result["headerBrand"]}
                    for route in routes
                ]
                context.close()
            browser.close()
        OUT.joinpath("header-report.json").write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
        print(json.dumps(report, ensure_ascii=False, indent=2))
        return
    if "--portfolio-only" in sys.argv:
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True, executable_path=CHROME)
            page = browser.new_page(viewport=VIEWPORTS["desktop"], device_scale_factor=1)
            result = inspect(page, "/nase-prace")
            page.screenshot(path=str(OUT / "portfolio-desktop.png"), full_page=True)
            browser.close()
        print(json.dumps(result, ensure_ascii=False, indent=2))
        return
    if "--home-only" in sys.argv:
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True, executable_path=CHROME)
            page = browser.new_page(viewport=VIEWPORTS["desktop"], device_scale_factor=1)
            result = inspect(page, "/")
            page.wait_for_timeout(2500)
            result["video"] = page.evaluate("""() => { const n=document.querySelector('video.theme-hero-video'); return n ? {src:n.currentSrc||n.querySelector('source')?.src||'',paused:n.paused,muted:n.muted,autoplay:n.autoplay,loop:n.loop,playsInline:n.playsInline,readyState:n.readyState,error:n.error?.message||''} : null; }""")
            result["facadeBeforeClick"] = page.evaluate("""() => { const f=document.querySelector('[data-hero-video-facade]'); const b=f?.querySelector('.theme-hero-video__play'); if(!f||!b)return null; const r=b.getBoundingClientRect(); const hit=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2); return {embedUrl:f.dataset.embedUrl||'',loaded:f.dataset.videoLoaded||'',pending:f.dataset.pendingConsent||'',buttonVisible:!!(b.offsetWidth||b.offsetHeight),buttonPointerEvents:getComputedStyle(b).pointerEvents,facadePointerEvents:getComputedStyle(f).pointerEvents,hitTag:hit?.tagName||'',hitClass:hit?.className||''}; }""")
            button = page.locator("[data-hero-video-facade] .theme-hero-video__play")
            if button.count():
                button.click(timeout=5_000)
                page.wait_for_timeout(500)
                result["facadeAfterClick"] = page.evaluate("""() => { const f=document.querySelector('[data-hero-video-facade]'); return f ? {loaded:f.dataset.videoLoaded||'',pending:f.dataset.pendingConsent||'',iframe:!!f.querySelector('iframe'),status:f.querySelector('.theme-hero-video__status')?.textContent||'',settingsOpen:!!document.querySelector('[data-cookie-settings]:not([hidden]),.cookie-consent-modal.is-open')} : null; }""")
                accept = page.locator("[data-cookie-accept-all]:visible")
                if accept.count():
                    accept.last.click()
                    page.wait_for_timeout(1_000)
                    result["facadeAfterConsent"] = page.evaluate("""() => { const f=document.querySelector('[data-hero-video-facade]'); return f ? {loaded:f.dataset.videoLoaded||'',pending:f.dataset.pendingConsent||'',iframe:!!f.querySelector('iframe'),iframeSrc:f.querySelector('iframe')?.src||'',button:!!f.querySelector('.theme-hero-video__play'),status:f.querySelector('.theme-hero-video__status')?.textContent||''} : null; }""")
            browser.close()
        print(json.dumps(result, ensure_ascii=False, indent=2))
        return
    report = {"viewports": {}, "errors": []}
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True, executable_path=CHROME)
        for name, viewport in VIEWPORTS.items():
            page = browser.new_page(viewport=viewport, device_scale_factor=1)
            console_errors = []
            page.on("console", lambda msg: console_errors.append(msg.text) if msg.type == "error" else None)
            home = inspect(page, "/")
            home.update(page.evaluate("""() => ({
              serviceCards: document.querySelectorAll('.hp-services-grid .hp-svc-card,.hp-services-grid .premium-service-card').length,
              contactImage: (() => { const n=document.querySelector('.hp-contact-visual'); return n ? {src:n.currentSrc||n.src,complete:n.complete,naturalWidth:n.naturalWidth} : null; })(),
              heroNative: (() => { const n=document.querySelector('video.theme-hero-video'); return n ? {src:n.currentSrc||n.querySelector('source')?.src||'',paused:n.paused,muted:n.muted,autoplay:n.autoplay,loop:n.loop,playsInline:n.playsInline,readyState:n.readyState} : null; })(),
              heroEmbed: !!document.querySelector('[data-hero-video-facade]'),
              heroIframe: !!document.querySelector('[data-hero-video-facade] iframe'),
            })"""))
            accept = page.locator("[data-cookie-accept-all]")
            if accept.count() and accept.first.is_visible():
                accept.first.click()
                page.wait_for_timeout(1600)
                home["heroIframeAfterConsent"] = page.locator("[data-hero-video-facade] iframe").count() > 0
            if name == "mobile":
                menu = page.locator(".gb-menu")
                if menu.count():
                    menu.click()
                    page.wait_for_timeout(250)
                    home["mobileMenuExpanded"] = menu.get_attribute("aria-expanded") == "true"
                    home["mobileVisibleLinks"] = page.locator(".gb-nav a:visible").count()
            page.screenshot(path=str(OUT / f"home-{name}.png"), full_page=True)

            routes = ["/sluzby", "/svatebni-foto-video", "/nase-prace", "/kamila-a-matej", "/blog", "/cenik", "/o-nas", "/hodnoceni", "/kontakt"]
            route_results = []
            for route in routes:
                route_results.append(inspect(page, route))
                if route == "/nase-prace":
                    page.screenshot(path=str(OUT / f"portfolio-{name}.png"), full_page=True)
            work_link = page.locator("#fotogalerie .pt-card,#videogalerie .pt-card").first
            if page.url.endswith("/nase-prace") and work_link.count():
                route_results.append(inspect(page, work_link.get_attribute("href")))
            inspect(page, "/blog")
            blog_link = page.locator(".bl-card,.gb-article-card").first
            if blog_link.count():
                href = blog_link.get_attribute("href")
                if href:
                    route_results.append(inspect(page, href))
            report["viewports"][name] = {"home": home, "routes": route_results, "consoleErrors": console_errors}
            page.close()
        browser.close()

    OUT.joinpath("report.json").write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
