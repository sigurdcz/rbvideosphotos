(function () {
  'use strict';

  if (!document.body.classList.contains('theme-elegant-white2')) return;

  var body = document.body;
  if (body.dataset.ew2Initialized === '1') return;
  body.dataset.ew2Initialized = '1';
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  function qs(selector, scope) {
    return (scope || document).querySelector(selector);
  }

  function qsa(selector, scope) {
    return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
  }

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function markLoaded() {
    body.classList.add('loaded');
  }

  function balancedLines(words) {
    if (words.length <= 4) return [words];
    var lineCount = words.length <= 8 ? 2 : 3;
    var lines = [];
    var cursor = 0;

    for (var lineIndex = 0; lineIndex < lineCount; lineIndex += 1) {
      var remainingWords = words.length - cursor;
      var remainingLines = lineCount - lineIndex;
      var take = Math.ceil(remainingWords / remainingLines);
      lines.push(words.slice(cursor, cursor + take));
      cursor += take;
    }

    return lines.filter(function (line) { return line.length; });
  }

  function appendWords(target, words, accentLast) {
    words.forEach(function (word, index) {
      if (index > 0) target.appendChild(document.createTextNode(' '));
      if (accentLast && index === words.length - 1) {
        var accent = document.createElement('span');
        accent.className = 'gold-i';
        accent.textContent = word;
        target.appendChild(accent);
        return;
      }
      target.appendChild(document.createTextNode(word));
    });
  }

  function prepareHeroTitle(hero) {
    var heading = qs('h1', hero);
    if (!heading || heading.classList.contains('ew2-hero-title-ready')) return;

    var label = (heading.textContent || '').trim().replace(/\s+/g, ' ');
    if (!label) return;

    var lines = balancedLines(label.split(' '));
    heading.classList.add('ew2-hero-title-ready');
    heading.setAttribute('aria-label', label);
    heading.textContent = '';

    lines.forEach(function (words, index) {
      var mask = document.createElement('span');
      var inner = document.createElement('span');
      mask.className = 'ew2-hero-line';
      inner.className = 'ew2-hero-line__inner';
      mask.setAttribute('aria-hidden', 'true');
      appendWords(inner, words, index === lines.length - 1);
      mask.appendChild(inner);
      heading.appendChild(mask);
    });
  }

  function prepareHero() {
    var hero = qs('.hp-hero, .hero, .services-hero, .page-hero, .pf-hero, .bl-hero, .fq-hero, .kt-hero, .bp-hero, .pf-detail-hero, .bp-detail-hero');
    if (!hero) return;

    hero.classList.add('ew2-hero');
    prepareHeroTitle(hero);

    var subtitle = qs('.hero-sub, p', hero);
    if (subtitle) subtitle.classList.add('ew2-hero-sub');

    var cta = qs('.hero-cta-wrap, .kt-hero-actions, .hp-section-cta', hero);
    if (cta) cta.classList.add('ew2-hero-cta');

    if (!qs('.ew2-hero-curtain', hero)) {
      var curtain = document.createElement('span');
      curtain.className = 'ew2-hero-curtain';
      curtain.setAttribute('aria-hidden', 'true');
      hero.appendChild(curtain);
    }
  }

  function accentHeading(heading) {
    if (!heading || heading.classList.contains('ew2-heading-ready') || heading.closest('.ew2-hero')) return;

    var existingEmphasis = qs('em, i', heading);
    if (existingEmphasis) {
      existingEmphasis.classList.add('gold-i');
      heading.classList.add('ew2-heading-ready');
      return;
    }

    if (heading.children.length) {
      heading.classList.add('ew2-heading-ready');
      return;
    }

    var label = (heading.textContent || '').trim().replace(/\s+/g, ' ');
    var words = label.split(' ');
    if (words.length < 2) {
      heading.classList.add('ew2-heading-ready');
      return;
    }

    heading.textContent = '';
    appendWords(heading, words, true);
    heading.classList.add('ew2-heading-ready');
  }

  function prepareHeadings() {
    qsa('.section-title, section h2, .services-wrap h2, .grid-wrap h2, .bl-grid-wrap h2, .fq-wrap h2, .kt-wrap h2, .cenik-packages h2, .cenik-cta h2').forEach(accentHeading);

    qsa('#faq .section-label, #hodnoceni .section-label, #proces .section-label, .fq-wrap .section-label, .cenik-cta .section-label').forEach(function (label) {
      label.classList.add('ew2-eyebrow--center');
    });

    qsa('#faq .section-title, #hodnoceni .section-title, #proces .section-title, .fq-wrap .section-title, .cenik-cta h2').forEach(function (heading) {
      heading.classList.add('ew2-centered-heading');
    });
  }

  function prepareTextLinks() {
    qsa('.hp-blog-link, .bl-more, .service-card a, .hp-svc-card__btn').forEach(function (link) {
      link.classList.add('ew2-text-link');
    });
  }

  function prepareNavigation() {
    var header = qs('.theme-header');
    var headerInner = qs('.theme-header__inner');
    var navWrap = qs('.theme-nav-wrap');
    if (!header || !headerInner || !navWrap) return;

    var defaultNav = qs('[data-ew2-default-nav]', navWrap);
    var suppliedNav = qsa('.nav', navWrap).filter(function (nav) {
      return nav !== defaultNav;
    })[0];
    if (defaultNav && suppliedNav) defaultNav.remove();

    var currentPath = window.location.pathname.replace(/\/$/, '') || '/';
    qsa('a[href]', navWrap).forEach(function (link) {
      var linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/$/, '') || '/';
      link.classList.toggle('nav-active', linkPath === currentPath);
    });

    var toggle = qs('.ew2-nav-toggle', headerInner);
    if (!toggle) {
      toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'ew2-nav-toggle';
      toggle.setAttribute('aria-label', 'Otevřít navigaci');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.innerHTML = '<span aria-hidden="true"></span>';
      headerInner.insertBefore(toggle, navWrap);
    }

    function closeMenu() {
      header.classList.remove('is-menu-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Otevřít navigaci');
    }

    toggle.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      var open = !header.classList.contains('is-menu-open');
      header.classList.toggle('is-menu-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Zavřít navigaci' : 'Otevřít navigaci');
    });

    qsa('a', navWrap).forEach(function (link) {
      link.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeMenu();
    });

    var scrollPending = false;
    function syncHeader() {
      header.classList.toggle('is-scrolled', window.scrollY > 40);
      scrollPending = false;
    }

    window.addEventListener('scroll', function () {
      if (scrollPending) return;
      scrollPending = true;
      window.requestAnimationFrame(syncHeader);
    }, { passive: true });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 760) closeMenu();
    });

    syncHeader();
  }

  function prepareFaq() {
    var source = qs('[data-ew-home-faq-source]');
    var list = qs('#faq .hp-faq');
    if (source && list) {
      var known = {};
      qsa('.hp-faq-item', list).forEach(function (item) {
        var question = qs('.hp-faq-question, strong, h3, button', item);
        var key = question ? (question.textContent || '').trim().toLocaleLowerCase() : '';
        if (key) known[key] = true;
      });

      qsa('.hp-faq-item', source).forEach(function (item) {
        var question = qs('.hp-faq-question, strong, h3, button', item);
        var key = question ? (question.textContent || '').trim().toLocaleLowerCase() : '';
        if (!key || known[key] || qsa('.hp-faq-item', list).length >= 4) return;
        known[key] = true;
        list.appendChild(item);
      });
      source.remove();
    }

    qsa('.ew-faq-question, .faq-q').forEach(function (button) {
      if (button.dataset.ew2Bound === '1') return;
      button.dataset.ew2Bound = '1';
      if (button.tagName === 'BUTTON') {
        button.setAttribute('aria-expanded', button.getAttribute('aria-expanded') || 'false');
      }

      button.addEventListener('click', function () {
        var item = button.closest('.hp-faq-item, .faq-item');
        if (!item) return;
        var open = !item.classList.contains('is-open');
        item.classList.toggle('is-open', open);
        if (button.tagName === 'BUTTON') button.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });
  }

  function revealGroups() {
    return [
      '.section-label, .section-title, .section-intro',
      '.hp-svc-card, .svc-card, .service-card',
      '.pt-tile',
      '.hp-blog-card, .bl-card',
      '.hp-cenik-card',
      '.hp-review-card',
      '.hp-faq-item, .faq-item, .faq-list details',
      '.ew-process-list article, .kt-step',
      '.ci-block, .kt-form-block, .hp-contact-box',
      '.pf-photo, .bg-item',
      '.bp-content > *, .pf-copy > *'
    ];
  }

  function prepareReveals() {
    var items = [];
    revealGroups().forEach(function (selector) {
      qsa(selector).forEach(function (element, index) {
        if (element.closest('.ew2-hero') || element.classList.contains('rv')) return;
        element.classList.add('rv');
        element.style.setProperty('--d', (Math.min(index % 4, 3) * .12) + 's');
        items.push(element);
      });
    });

    if (reducedMotion.matches || !('IntersectionObserver' in window)) {
      items.forEach(function (element) { element.classList.add('on'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('on');
        observer.unobserve(entry.target);
      });
    }, {
      threshold: .12,
      rootMargin: '0px 0px -8% 0px'
    });

    items.forEach(function (element) { observer.observe(element); });
  }

  function prepareThread() {
    var path = qs('.ew2-thread__path');
    if (!path) return;

    if (reducedMotion.matches) {
      path.style.strokeDashoffset = '0';
      return;
    }

    var drawn = 0;
    function drawFrame() {
      var pageHeight = Math.max(document.documentElement.scrollHeight, 1);
      var progress = clamp((window.scrollY + window.innerHeight * .82) / pageHeight, 0, 1);
      drawn += (progress - drawn) * .06;
      path.style.strokeDashoffset = String(1 - drawn);
      window.requestAnimationFrame(drawFrame);
    }

    window.requestAnimationFrame(drawFrame);
  }

  prepareNavigation();
  prepareHero();
  prepareHeadings();
  prepareTextLinks();
  prepareFaq();
  prepareReveals();
  prepareThread();

  window.addEventListener('load', markLoaded, { once: true });
  window.setTimeout(markLoaded, 900);
  if (document.readyState === 'complete') window.requestAnimationFrame(markLoaded);
})();
