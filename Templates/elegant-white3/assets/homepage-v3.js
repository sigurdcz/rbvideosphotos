(function () {
  'use strict';

  var body = document.body;
  if (!body || !body.classList.contains('theme-elegant-white3')) return;
  if (body.getAttribute('data-cms-page-key') !== 'homepage') return;
  if (body.classList.contains('ew3-enhanced')) return;
  body.classList.add('ew3-enhanced');

  function qs(selector, scope) {
    return (scope || document).querySelector(selector);
  }

  function qsa(selector, scope) {
    return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
  }

  function cleanText(node) {
    return node ? (node.textContent || '').replace(/\s+/g, ' ').trim() : '';
  }

  function ensureSectionHeading(section) {
    if (!section) return;
    var title = qs('.section-title, h2', section);
    var label = qs('.section-label', section);
    var labelText = cleanText(label);

    if (!title && labelText) {
      title = document.createElement('h2');
      title.className = 'section-title ew3-derived-title';
      title.textContent = labelText;
      label.insertAdjacentElement('afterend', title);
    } else if (title && !cleanText(title) && labelText) {
      title.textContent = labelText;
      title.classList.add('ew3-derived-title');
    }

    if (title && label && cleanText(title).toLocaleLowerCase() === labelText.toLocaleLowerCase()) {
      label.classList.add('ew3-label-echo');
    }
  }

  function enhanceHero() {
    var hero = qs('#uvod.hp-hero');
    if (!hero) return;

    var label = qs('.section-label', hero);
    var heading = qs('h1', hero);
    if (heading && label && !qs('.ew3-seo-line', heading)) {
      var seoLine = document.createElement('span');
      seoLine.className = 'ew3-seo-line';
      var seoText = cleanText(label).replace(/\s+-\s+/, ' · ');
      var heroSub = cleanText(qs('.hero-sub', hero));
      var weddingWord = heroSub.match(/svatební/i);
      if (weddingWord && !/svatební/i.test(seoText)) seoText = weddingWord[0] + ' ' + seoText.toLocaleLowerCase();
      seoLine.textContent = seoText;

      var storyLine = document.createElement('span');
      storyLine.className = 'ew3-story-line';
      while (heading.firstChild) storyLine.appendChild(heading.firstChild);
      heading.appendChild(seoLine);
      heading.appendChild(storyLine);
      label.classList.add('ew3-original-hero-label');
    }

    var ctaWrap = qs('.hero-cta-wrap', hero);
    var portfolioCta = qs('#nase-prace .hp-section-cta a');
    if (ctaWrap && !qs('.ew3-secondary-cta', ctaWrap)) {
      var secondary = portfolioCta ? portfolioCta.cloneNode(true) : document.createElement('a');
      secondary.className = 'btn-secondary ew3-secondary-cta';
      if (!portfolioCta) {
        secondary.href = '#nase-prace';
        secondary.textContent = 'Naše práce';
      }
      ctaWrap.appendChild(secondary);
    }

    var heroImage = qs('.hero-bg', hero);
    if (heroImage) {
      heroImage.loading = 'eager';
      heroImage.fetchPriority = 'high';
      heroImage.decoding = 'async';
      if (!heroImage.alt && heading) heroImage.alt = cleanText(heading);
    }
  }

  function enhanceHeaderCta() {
    var contactLink = qs('.site-nav .nav-cta[href="#kontakt"], .theme-nav-wrap a[href="#kontakt"]');
    if (!contactLink) return;
    contactLink.textContent = 'Ověřit volný termín';
    contactLink.setAttribute('aria-label', 'Ověřit volný termín');
  }

  function enhancePricing() {
    var section = qs('#cenik');
    if (!section) return;
    var sourceCta = qs('.hp-section-cta a', section);

    qsa('.hp-cenik-card', section).forEach(function (card) {
      if (sourceCta && !qs('.ew3-price-cta', card)) {
        var cta = sourceCta.cloneNode(true);
        cta.className = 'ew3-price-cta';
        card.appendChild(cta);
      }
    });

    var featured = qs('.hp-cenik-card--featured', section);
    var description = featured ? qs('.hp-cenik-card-desc, .hp-cenik-card__desc', featured) : null;
    if (featured && description && !qs('.ew3-price-badge', featured)) {
      var firstWord = cleanText(description).split(/\s+/)[0].replace(/[.,;:!?]+$/, '');
      if (firstWord) {
        var badge = document.createElement('span');
        badge.className = 'ew3-price-badge';
        badge.textContent = firstWord;
        featured.insertBefore(badge, featured.firstChild);
      }
    }
  }

  function optimizeImages() {
    qsa('main img').forEach(function (image) {
      if (image.classList.contains('hero-bg')) return;
      var priorityImage = image.closest('#sluzby, #nase-prace, #blog, #kontakt');
      image.loading = priorityImage ? 'eager' : 'lazy';
      if (priorityImage) image.fetchPriority = 'high';
      image.decoding = 'async';
      if (!image.alt) {
        var section = image.closest('section');
        var heading = section ? qs('h2, h3', section) : null;
        if (heading) image.alt = cleanText(heading);
      }
    });
  }

  function revealContent() {
    qsa('.ew-reveal, .jb-reveal').forEach(function (element) {
      element.classList.add('is-visible');
      element.style.transitionDelay = '0ms';
    });
  }

  function reorderSections() {
    var hero = qs('#uvod');
    var services = qs('#sluzby');
    var portfolio = qs('#nase-prace');
    var reviews = qs('#hodnoceni');
    var pricing = qs('#cenik');
    var process = qs('#proces');
    var blog = qs('#blog');
    var contact = qs('#kontakt');
    var faq = qs('#faq');
    var main = hero ? hero.parentNode : qs('.theme-main');
    if (!main) return;

    // The existing #sluzby section is the site's "Co nabízíme / O nás" block.
    // Content remains DB-driven; the theme only changes its visual order.
    [hero, services, portfolio, reviews, pricing, process, blog, contact, faq].forEach(function (section) {
      if (section && section.parentNode === main) main.appendChild(section);
    });
  }

  enhanceHeaderCta();
  enhanceHero();
  ['#sluzby', '#nase-prace', '#hodnoceni', '#cenik', '#proces', '#blog', '#kontakt', '#faq'].forEach(function (selector) {
    ensureSectionHeading(qs(selector));
  });
  enhancePricing();
  optimizeImages();
  revealContent();
  reorderSections();
  // Portfolio CTA is guaranteed to exist only after the homepage order is normalized.
  enhanceHero();
})();
