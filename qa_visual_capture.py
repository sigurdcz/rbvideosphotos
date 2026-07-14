from playwright.sync_api import sync_playwright


SELECTORS = {
    "header": ".theme-header",
    "header_inner": ".theme-header__inner",
    "brand": ".theme-brand",
    "hero_body": ".hero .hero-body",
    "hero_title": ".hero h1",
    "hero_script": ".hero h1 > span",
    "hero_sub": ".hero .hero-sub",
    "hero_actions": ".hero .hero-btns",
    "about_title": ".blt-about .sec-hdr h2",
    "about_cta": ".blt-about .sec-hdr .blt-button",
    "portfolio_title": ".blt-portfolio .sec-hdr h2",
    "process_title": ".blt-process .section-title",
    "process_intro": ".blt-process .section-intro",
    "process_cta": ".blt-process .blt-chapter-cta",
    "pricing_title": ".blt-pricing .section-title",
    "reviews_title": ".blt-reviews .section-title",
    "reviews_intro": ".blt-reviews .section-intro",
    "reviews_cta": ".blt-reviews .hp-section-cta",
    "blog_title": ".blt-blog .section-title",
    "blog_intro": ".blt-blog .section-intro",
    "blog_cta": ".blt-blog .hp-section-cta",
    "about_visual": ".blt-about__visual",
    "offer": ".blt-offer-rail",
    "portfolio_copy": ".blt-portfolio .sec-hdr",
    "portfolio_grid": ".blt-portfolio .hp-portfolio-placeholder",
    "process_grid": ".blt-process .ew-process-list",
    "pricing_grid": ".blt-pricing .hp-cenik-grid",
    "reviews_grid": ".blt-reviews .hp-reviews-grid",
    "blog_grid": ".blt-blog .hp-blog-grid",
    "contact_intro": "#kontakt .blt-contact__intro",
    "contact_form": "#kontakt .blt-contact__form",
    "contact_faq": "#kontakt .blt-contact-faq",
    "footer": ".theme-footer",
}

TARGET_SELECTORS = {
    "header": ".site-header",
    "brand": ".brand",
    "hero_body": ".hero-copy",
    "hero_title": ".hero h1",
    "hero_script": ".hero .script",
    "hero_sub": ".hero .lead",
    "hero_actions": ".hero .hero-actions",
    "about_title": ".about-copy h2",
    "about_cta": ".about-copy .pill",
    "portfolio_title": ".portfolio-copy h2",
    "process_title": ".process .section-title-side h2",
    "pricing_title": ".pricing-intro h2",
    "reviews_title": ".testimonials-title h2",
    "reviews_cta": ".testimonials-title .pill",
    "blog_title": ".blog-intro h2",
    "blog_intro": ".blog-intro > p:not(.kicker)",
    "blog_cta": ".blog-intro .pill",
}


def report(page, selectors):
    for name, selector in selectors.items():
        element = page.locator(selector).first
        if not element.count():
            print(name, None)
            continue
        details = element.evaluate(
            """element => {
                const box = element.getBoundingClientRect();
                const style = getComputedStyle(element);
                return {x:box.x,y:box.y,width:box.width,height:box.height,
                    fontSize:style.fontSize,lineHeight:style.lineHeight,
                    cssWidth:style.width,minHeight:style.minHeight,
                    display:style.display,transform:style.transform};
            }"""
        )
        print(name, details)


with sync_playwright() as playwright:
    browser = playwright.chromium.launch(
        headless=True,
        executable_path=r"C:\Program Files\Google\Chrome\Application\chrome.exe",
    )
    page = browser.new_page(viewport={"width": 1280, "height": 800}, device_scale_factor=1)
    errors = []
    request_failures = []
    page.on("pageerror", lambda error: errors.append(str(error)))
    page.on("requestfailed", lambda request: request_failures.append({"url": request.url, "failure": request.failure}))
    page.goto("http://127.0.0.1:8766/", wait_until="networkidle", timeout=120_000)
    page.evaluate(
        """async () => {
            for (let y = 0; y < document.documentElement.scrollHeight; y += 500) {
                window.scrollTo(0, y);
                await new Promise(resolve => setTimeout(resolve, 90));
            }
            window.scrollTo(0, 0);
            await new Promise(resolve => setTimeout(resolve, 250));
        }"""
    )
    page.evaluate(
        """async () => {
          const images = [...document.images].filter(image => image.offsetParent !== null);
          await Promise.race([
            Promise.all(images.map(image => image.decode().catch(() => null))),
            new Promise(resolve => setTimeout(resolve, 3000))
          ]);
          await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        }"""
    )
    page.screenshot(path="homepage-component-audit-final-clean-1280.png", full_page=True)
    print(
        "page",
        page.evaluate(
            "({h:document.documentElement.scrollHeight, "
            "overflow:document.documentElement.scrollWidth-innerWidth, "
            "ready:document.body.dataset.bltReady||null})"
        ),
    )
    report(page, SELECTORS)
    print(
        "markers",
        page.locator(".blt-chapter-marker:visible,.blt-storyline:visible").count(),
        "errors",
        errors,
    )
    print(
        "component-audit",
        page.evaluate(
            """() => {
              const box = selector => {
                const element = document.querySelector(selector);
                if (!element) return null;
                const rect = element.getBoundingClientRect();
                return {x:rect.x,y:rect.y,w:rect.width,h:rect.height};
              };
              const cards = [...document.querySelectorAll('.blt-offer-rail .hp-svc-card')];
              const contact = ['#kontakt .blt-contact__intro','#kontakt .blt-contact__form','#kontakt .blt-contact-faq'].map(box);
              return {
                serviceCards: cards.length,
                staticSupplements: document.querySelectorAll('.blt-offer-item--supplement').length,
                pricingLists: document.querySelectorAll('.blt-pricing-points').length,
                contactHeights: contact.map(item => item && item.h),
                aboutImageObjectPosition: getComputedStyle(document.querySelector('.blt-about__visual img')).objectPosition,
                aboutImage: (() => {
                  const image = document.querySelector('.blt-about__visual img');
                  return image ? {src:image.currentSrc||image.src,complete:image.complete,naturalWidth:image.naturalWidth,naturalHeight:image.naturalHeight} : null;
                })(),
                reviewControls: box('.blt-review-controls'),
                portfolioCta: (() => {
                  const element = document.querySelector('.blt-portfolio-cta a');
                  if (!element) return null;
                  const rect = element.getBoundingClientRect();
                  return {x:rect.x,w:rect.width,scrollWidth:element.scrollWidth,clientWidth:element.clientWidth,overflow:getComputedStyle(element).overflow};
                })(),
                ctaStyles: ['.blt-about .blt-button','.blt-process .blt-chapter-cta','#kontakt .hp-contact-form button','#kontakt .blt-text-link'].map(selector => {
                  const element = document.querySelector(selector);
                  if (!element) return null;
                  const style = getComputedStyle(element);
                  return {selector,color:style.color,background:style.backgroundImage,opacity:style.opacity,fontSize:style.fontSize};
                })
              };
            }"""
        ),
    )
    print("request-failures", request_failures)
    active_at_top = page.locator('.theme-nav-wrap .nav-links a.is-active').count()
    page.evaluate(
        """() => {
            const section = document.querySelector('#blog');
            window.scrollTo(0, section.offsetTop - Math.round(innerHeight * .2));
            window.dispatchEvent(new Event('scroll'));
        }"""
    )
    page.wait_for_timeout(350)
    active_blog = page.locator('.theme-nav-wrap .nav-links a.is-active[href="#blog"]').count()
    active_total = page.locator('.theme-nav-wrap .nav-links a.is-active').count()
    faq_question = page.locator('#kontakt .hp-faq-question').first
    faq_answer = page.locator('#kontakt .hp-faq-answer').first
    faq_before = faq_answer.is_visible()
    faq_question.click()
    faq_after = faq_answer.is_visible()
    visible_reviews_before = page.locator('.blt-reviews .hp-review-card:visible strong').all_text_contents()
    page.locator('.blt-reviews [data-review-next]').click()
    visible_reviews_after = page.locator('.blt-reviews .hp-review-card:visible strong').all_text_contents()
    hover_button = page.locator('.hero .hero-btns a').first
    hover_button.hover()
    hover_background = hover_button.evaluate('element => getComputedStyle(element).backgroundImage')
    print(
        "interactions",
        {
            "activeAtTop": active_at_top,
            "activeBlog": active_blog,
            "activeTotal": active_total,
            "faqBefore": faq_before,
            "faqAfter": faq_after,
            "reviewsChanged": visible_reviews_before != visible_reviews_after,
            "goldHover": "linear-gradient" in hover_background,
        },
    )
    browser.close()

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(
        headless=True,
        executable_path=r"C:\Program Files\Google\Chrome\Application\chrome.exe",
    )
    target = browser.new_page(viewport={"width": 1280, "height": 800}, device_scale_factor=1)
    target.goto("http://127.0.0.1:8777/index.html", wait_until="networkidle", timeout=120_000)
    print("target")
    report(target, TARGET_SELECTORS)
    browser.close()
