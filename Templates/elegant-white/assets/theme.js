(function () {
  var body = document.body;
  if (!body || (!body.classList.contains('theme-elegant-white') && !document.querySelector('.theme-elegant-white'))) return;

  function qs(selector, scope) {
    return (scope || document).querySelector(selector);
  }

  function qsa(selector, scope) {
    return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
  }

  function text(node) {
    return node ? (node.textContent || '').replace(/\s+/g, ' ').trim() : '';
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function isHomepage() {
    return body.getAttribute('data-cms-page-key') === 'homepage' || !!qs('.hp-hero');
  }

  function initHeaderScroll() {
    var header = qs('.theme-header');
    if (!header) return;

    function sync() {
      header.classList.toggle('is-scrolled', window.scrollY > 18);
    }

    window.addEventListener('scroll', sync, { passive: true });
    sync();
  }

  function initMobileNav() {
    var nav = qs('.theme-nav-wrap .site-nav');
    if (!nav) return;
    var toggle = qs('.nav-toggle-input', nav);
    var label = qs('.nav-toggle-label', nav);
    var links = qs('.nav-links', nav);
    if (!toggle || !label || !links) return;

    if (!qs('a[href="/"]', links) && !qs('a[href="/#uvod"]', links)) {
      var home = document.createElement('a');
      home.href = '/';
      home.textContent = 'Uvod';
      home.className = 'nav-home-link';
      links.insertBefore(home, links.firstChild);
    }

    function syncState() {
      var open = toggle.checked === true;
      label.classList.toggle('is-active', open);
      label.setAttribute('aria-expanded', open ? 'true' : 'false');
      body.classList.toggle('ew-nav-open', open);
    }

    toggle.addEventListener('change', syncState);
    qsa('a', links).forEach(function (link) {
      link.addEventListener('click', function () {
        toggle.checked = false;
        syncState();
      });
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 768 && toggle.checked) {
        toggle.checked = false;
        syncState();
      }
    });

    syncState();
  }

  function getImageSrc(img) {
    if (!img) return '';
    return img.getAttribute('data-src') || img.getAttribute('src') || '';
  }

  function pickAboutVisual() {
    var source = qs('.jb-about-visual__source');
    var sourceSrc = getImageSrc(source);
    if (sourceSrc) return sourceSrc;

    var service = qs('#sluzby .hp-svc-card__media img, #sluzby .svc-card img');
    var serviceSrc = getImageSrc(service);
    if (serviceSrc) return serviceSrc;

    var portfolio = qs('#nase-prace .pt-thumb img');
    return getImageSrc(portfolio);
  }

  function pickContactVisual() {
    var heroBg = qs('.theme-hero-video');
    var style = heroBg ? (heroBg.getAttribute('style') || '') : '';
    var match = style.match(/url\(['"]?([^'")]+)['"]?\)/i);
    if (match && match[1]) return match[1];

    var portfolio = qsa('#nase-prace .pt-thumb img')[3] || qs('#nase-prace .pt-thumb img');
    return getImageSrc(portfolio);
  }

  function ensureNavCta() {
    var links = qs('.theme-nav-wrap .nav-links');
    if (!links) return;
    qsa('a', links).forEach(function (link) {
      var label = text(link).toLowerCase();
      if (label === 'blog') link.setAttribute('href', '/blog');
      if (label === 'ceník' || label === 'cenik') link.setAttribute('href', '/cenik');
    });

    var existing = qs('.nav-cta', links);
    if (existing) {
      existing.classList.add('ew-nav-cta');
      existing.href = '#kontakt';
      existing.textContent = 'Kontaktujte nas';
      return;
    }
    if (qs('.ew-nav-cta', links)) return;
    var cta = document.createElement('a');
    cta.className = 'ew-nav-cta nav-cta';
    cta.href = '#kontakt';
    cta.textContent = 'Kontaktujte nas';
    links.appendChild(cta);
  }

  function enhanceHero() {
    var hero = qs('.hp-hero');
    if (!hero) return;
    hero.classList.add('ew-hero');

    var label = qs('.section-label', hero);
    if (label) label.textContent = 'Fotograf a kameraman';

    var heading = qs('h1', hero);
    if (heading) {
      heading.innerHTML = 'Zachytíme vaše nejkrásnější chvíle <span>fotografií a videem</span>';
    }

    var intro = qs('.hero-sub', hero);
    if (intro) {
      intro.textContent = 'Svatební, rodinné, portrétní i firemní focení a video s důrazem na emoce, detaily a profesionální výsledek.';
    }

    var cta = qs('.hero-cta-wrap', hero);
    if (cta) {
      cta.innerHTML =
        '<a href="#nase-prace" class="btn-primary">Podívat se na práce</a>' +
        '<a href="#kontakt" class="btn-secondary ew-hero-secondary">Domluvit termín</a>';
    }

    var cue = qs('.jb-scroll-cue', hero);
    if (cue) cue.remove();
  }

  function collectServices() {
    return qsa('#sluzby .hp-svc-card, #sluzby .svc-card').map(function (card, index) {
      var img = qs('.hp-svc-card__media img, img', card);
      var title = qs('.hp-svc-card__title, h3', card);
      var desc = qs('.hp-svc-card__desc, p', card);
      var link = qs('.hp-svc-card__btn, a', card);
      return {
        num: String(index + 1).padStart(2, '0'),
        title: text(title) || 'Sluzba',
        desc: text(desc),
        href: link ? (link.getAttribute('href') || '#kontakt') : '#kontakt',
        image: getImageSrc(img)
      };
    });
  }

  function enhanceAboutAndServices() {
    var section = qs('#sluzby');
    if (!section || section.classList.contains('ew-about-ready')) return;
    var container = qs('.container', section);
    if (!container) return;

    var services = collectServices();
    var aboutImage = pickAboutVisual();

    section.classList.add('ew-about-ready', 'ew-about-section');
    container.innerHTML =
      '<div class="ew-about-grid">' +
      '  <div class="ew-about-media">' +
      (aboutImage ? '    <img src="' + escapeHtml(aboutImage) + '" alt="RB Videos & Photos" loading="lazy">' : '') +
      '  </div>' +
      '  <div class="ew-about-copy">' +
      '    <p class="section-label">O nás</p>' +
      '    <h2 class="section-title">Příběhy, které stojí za to vyprávět</h2>' +
      '    <p class="section-intro">Jsme RB Videos & Photos - tým fotografů a kameramanů z Olomouce. Milujeme přirozené emoce, poctivou práci a moderní vizuální styl.</p>' +
      '    <div class="ew-about-points">' +
      '      <span>Profesionální přístup</span><span>Rychlá komunikace</span><span>Kvalitní technika</span><span>Přirozené momenty</span><span>Individuální přístup</span><span>Nadčasový výsledek</span>' +
      '    </div>' +
      '  </div>' +
      '</div>';

    buildServicesOverview(services, section);
  }

  function buildServicesOverview(services, afterSection) {
    if (!services.length || qs('.ew-services-overview')) return;

    var icons = ['camera', 'people', 'briefcase', 'calendar'];
    var section = document.createElement('section');
    section.className = 'section ew-services-overview';
    section.innerHTML =
      '<div class="container">' +
      '  <div class="ew-section-center">' +
      '    <p class="section-label">Naše služby</p>' +
      '    <h2 class="section-title">Nabízíme kompletní vizuální služby</h2>' +
      '  </div>' +
      '  <div class="ew-services-list">' +
      services.slice(0, 4).map(function (item, index) {
        return '<article class="ew-service-card">' +
          '<div class="ew-service-icon ew-service-icon--' + icons[index % icons.length] + '" aria-hidden="true"></div>' +
          '<h3>' + escapeHtml(item.title) + '</h3>' +
          '<p>' + escapeHtml(item.desc) + '</p>' +
          '</article>';
      }).join('') +
      '  </div>' +
      '  <div class="ew-section-cta"><a href="#kontakt" class="btn-primary">Kontaktujte nás</a></div>' +
      '</div>';

    var portfolio = qs('#nase-prace');
    if (portfolio && portfolio.parentNode) {
      portfolio.parentNode.insertBefore(section, portfolio.nextSibling);
      return;
    }
    afterSection.parentNode.insertBefore(section, afterSection.nextSibling);
  }

  function enhancePortfolio() {
    var section = qs('#nase-prace');
    if (!section) return;
    section.classList.add('ew-portfolio');

    var label = qs('.section-label', section);
    if (label) label.textContent = 'Naše práce';
    var title = qs('.section-title', section);
    if (title) title.textContent = 'Vybrané momenty';
    var intro = qs('.section-intro', section);
    if (intro) intro.textContent = 'Ukázka projektů a realizací.';

    qsa('.pt-tile', section).forEach(function (tile) {
      tile.classList.remove('jb-pt-tile--tall', 'jb-pt-tile--wide');
      var caption = qs('.pt-caption', tile);
      if (caption) caption.remove();
      var labelNode = qs('.jb-pt-label', tile);
      if (labelNode) labelNode.remove();
    });

    var cta = qs('.hp-section-cta a, .hp-section-cta .btn-secondary', section);
    if (cta) cta.textContent = 'Prohlédnout další práce';
  }

  function hideNonTargetHomeSections() {
    ['blog', 'cenik'].forEach(function (id) {
      var section = qs('#' + id);
      if (section) section.classList.add('ew-home-hidden');
    });
  }

  function initialsFromName(name) {
    var parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'RB';
    return parts.slice(0, 2).map(function (part) {
      return part.charAt(0).toUpperCase();
    }).join('');
  }

  function enhanceReviews() {
    var section = qs('#hodnoceni');
    if (!section) return;
    section.classList.add('ew-reviews');

    var label = qs('.section-label', section);
    if (label) label.textContent = 'Co o nás říkají klienti';
    var title = qs('.section-title', section);
    if (title) title.textContent = 'Vaše spokojenost je pro nás důležitá';

    qsa('.hp-review-card', section).forEach(function (card, index) {
      if (index > 2) {
        card.classList.add('ew-hidden-review');
        return;
      }

      if (!qs('.jb-review-meta', card)) {
        var strong = qs('strong', card);
        var span = qs('span', card);
        var meta = document.createElement('div');
        meta.className = 'jb-review-meta';
        meta.innerHTML =
          '<div class="jb-review-avatar">' + escapeHtml(initialsFromName(text(strong))) + '</div>' +
          '<div class="jb-review-person"><strong>' + escapeHtml(text(strong)) + '</strong><span>' + escapeHtml(text(span) || 'Klient') + '</span></div>';
        card.appendChild(meta);
      }
    });
  }

  function enhanceFaq() {
    var section = qs('#faq');
    if (!section || section.classList.contains('ew-faq-ready')) return;
    section.classList.add('ew-faq-ready');

    var label = qs('.section-label', section);
    if (label) label.textContent = 'Časté otázky';
    var title = qs('.section-title', section);
    if (title) title.textContent = 'Máte otázky? Máme odpovědi.';

    var list = qs('.hp-faq', section);
    if (!list) return;

    var existing = qsa('.hp-faq-item', list).map(function (item) {
      return {
        question: text(qs('.hp-faq-question, strong', item)),
        answer: text(qs('.hp-faq-answer, p', item))
      };
    }).filter(function (item) {
      return item.question;
    });

    var defaults = [
      { question: 'Dostaneme i neupravené fotky (RAW)?', answer: 'Neupravené RAW soubory standardně nepředáváme. Dodáváme hotový výběr upravený v jednotném stylu.' },
      { question: 'Fotíte i video?', answer: 'Ano, focení i video umíme spojit do jednoho výstupu podle rozsahu akce.' },
      { question: 'Za jak dlouho dostaneme hotové fotografie?', answer: 'Termín záleží na rozsahu zakázky. Přesný termín si potvrdíme při domluvě.' }
    ];
    var items = existing.concat(defaults).slice(0, 6);

    list.innerHTML = items.map(function (item, index) {
      return '<article class="hp-faq-item ew-faq-item' + (index === 0 ? ' is-open' : '') + '">' +
        '<button class="ew-faq-question" type="button" aria-expanded="' + (index === 0 ? 'true' : 'false') + '">' +
        '<span>' + escapeHtml(item.question) + '</span><span class="ew-faq-plus" aria-hidden="true"></span>' +
        '</button>' +
        '<div class="ew-faq-answer"><p>' + escapeHtml(item.answer || 'Odpověď najdete v kompletní sekci FAQ.') + '</p></div>' +
        '</article>';
    }).join('');

    qsa('.ew-faq-question', list).forEach(function (button) {
      button.addEventListener('click', function () {
        var item = button.closest('.ew-faq-item');
        var open = !item.classList.contains('is-open');
        item.classList.toggle('is-open', open);
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });

    var ctaWrap = qs('.hp-section-cta', section);
    if (!ctaWrap) {
      ctaWrap = document.createElement('div');
      ctaWrap.className = 'hp-section-cta ew-section-cta';
      list.parentNode.appendChild(ctaWrap);
    }
    ctaWrap.innerHTML = '<a href="/faq" class="btn-secondary">Všechny FAQ</a>';
  }

  function buildProcess() {
    var contact = qs('#kontakt');
    if (!contact || qs('.ew-process')) return;

    var section = document.createElement('section');
    section.className = 'section ew-process';
    section.innerHTML =
      '<div class="container">' +
      '  <div class="ew-section-center">' +
      '    <p class="section-label">Jak to probíhá</p>' +
      '    <h2 class="section-title">Jednoduchý proces, skvělý výsledek</h2>' +
      '  </div>' +
      '  <div class="ew-process-list">' +
      '    <article><span>01</span><i class="ew-process-icon ew-process-icon--date"></i><h3>Domluva termínu</h3><p>Ozvěte se nám a společně si domluvíme detaily.</p></article>' +
      '    <article><span>02</span><i class="ew-process-icon ew-process-icon--camera"></i><h3>Focení / natáčení</h3><p>Přirozeně zachytíme váš příběh a emoce.</p></article>' +
      '    <article><span>03</span><i class="ew-process-icon ew-process-icon--edit"></i><h3>Úprava</h3><p>Pečlivě vybereme a upravíme nejlepší záběry.</p></article>' +
      '    <article><span>04</span><i class="ew-process-icon ew-process-icon--send"></i><h3>Předání</h3><p>Dostanete hotové fotografie a video v nejvyšší kvalitě.</p></article>' +
      '  </div>' +
      '</div>';
    contact.parentNode.insertBefore(section, contact);
  }

  function reorderHomeSections() {
    var faq = qs('#faq');
    var reviews = qs('#hodnoceni');
    var process = qs('.ew-process');
    var contact = qs('#kontakt');

    if (faq && reviews && faq.parentNode === reviews.parentNode) {
      faq.parentNode.insertBefore(reviews, faq);
    }

    if (process && contact && process.parentNode === contact.parentNode) {
      contact.parentNode.insertBefore(process, contact);
    }
  }

  function normalizeContactForm(form) {
    if (!form || form.classList.contains('ew-form-ready')) return;
    form.classList.add('ew-form-ready');

    var hidden = qsa('input[type="hidden"]', form);
    var submit = qs('button[type="submit"], button:not([type])', form);
    var fields = [];
    var nodes = qsa('label, input:not([type="hidden"]), textarea, select', form);

    for (var i = 0; i < nodes.length; i += 1) {
      var node = nodes[i];
      if (!node || node.tagName.toLowerCase() !== 'label') continue;
      var control = nodes[i + 1];
      if (!control || control.tagName.toLowerCase() === 'label') continue;
      var field = document.createElement('div');
      field.className = 'ew-field';
      if (control.tagName.toLowerCase() === 'textarea') field.classList.add('ew-field--wide');
      field.appendChild(node.cloneNode(true));
      field.appendChild(control.cloneNode(true));
      fields.push(field);
      i += 1;
    }

    form.innerHTML = '';
    hidden.forEach(function (input) {
      form.appendChild(input.cloneNode(true));
    });
    fields.forEach(function (field) {
      form.appendChild(field);
    });
    if (submit) {
      form.appendChild(submit.cloneNode(true));
    }
  }

  function enhanceContact() {
    var section = qs('#kontakt');
    if (!section || section.classList.contains('ew-contact-ready')) return;
    section.classList.add('ew-contact-ready');

    var container = qs('.container', section);
    var title = qs('.section-title', section);
    var label = qs('.section-label', section);
    var intro = qs('.section-intro', section);
    var form = qs('form', section);
    if (!container || !form) return;

    if (label) label.textContent = 'Kontakt';
    if (title) title.textContent = 'Pojďme si říct o vašem focení nebo videu.';
    if (intro) intro.textContent = 'Ozvěte se nám. Odpovídáme rychle.';
    normalizeContactForm(form);

    var visual = pickContactVisual();
    var header = document.createElement('div');
    header.className = 'ew-contact-header';
    if (label) header.appendChild(label);
    if (title) header.appendChild(title);
    if (intro) header.appendChild(intro);

    var formCard = form.closest('.hp-contact-box') || form.parentNode;
    var layout = document.createElement('div');
    layout.className = 'ew-contact-panel';
    layout.innerHTML = '<div class="ew-contact-image">' + (visual ? '<img src="' + escapeHtml(visual) + '" alt="Kontakt RB Videos & Photos" loading="lazy">' : '') + '</div>';

    var right = document.createElement('div');
    right.className = 'ew-contact-form-wrap';
    right.appendChild(header);
    right.appendChild(form);
    layout.appendChild(right);

    container.innerHTML = '';
    container.appendChild(layout);
  }

  function syncFooterContactFromSection() {
    var section = qs('#kontakt');
    var footer = qs('.site-footer-contact');
    if (!section || !footer) return;

    var rows = qsa('a[href^="tel:"], a[href^="mailto:"]', document).map(function (link) {
      var href = link.getAttribute('href') || '';
      var value = text(link);
      if (!value) return null;
      return {
        label: /^tel:/i.test(href) ? 'Telefon' : 'E-mail',
        href: href,
        value: value
      };
    }).filter(Boolean);

    var unique = [];
    rows.forEach(function (row) {
      if (!unique.some(function (item) { return item.href === row.href; })) unique.push(row);
    });
    unique = unique.slice(0, 3);
    if (!unique.length) return;

    var title = qs('.site-footer-title', footer);
    var titleHtml = title ? title.outerHTML : '<div class="site-footer-title">Kontakt</div>';
    footer.innerHTML = titleHtml + unique.map(function (item) {
      return '<a class="site-footer-contact__item" href="' + escapeHtml(item.href) + '">' +
        '<span>' + escapeHtml(item.label) + '</span>' +
        '<strong>' + escapeHtml(item.value) + '</strong>' +
      '</a>';
    }).join('');
  }

  function initReveals() {
    var elements = qsa('.ew-about-grid, .pt-tile, .ew-service-card, .hp-review-card, .ew-faq-item, .ew-process-list article, .ew-contact-panel');
    if (!elements.length) return;

    if (!('IntersectionObserver' in window)) {
      elements.forEach(function (element) {
        element.classList.add('is-visible');
      });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -48px 0px' });

    elements.forEach(function (element, index) {
      element.classList.add('ew-reveal');
      element.style.transitionDelay = Math.min(index * 45, 180) + 'ms';
      observer.observe(element);
    });
  }

  initHeaderScroll();
  initMobileNav();
  ensureNavCta();

  if (isHomepage()) {
    enhanceHero();
    enhanceAboutAndServices();
    enhancePortfolio();
    hideNonTargetHomeSections();
    enhanceReviews();
    enhanceFaq();
    buildProcess();
    reorderHomeSections();
    enhanceContact();
  }

  syncFooterContactFromSection();
  initReveals();
})();
