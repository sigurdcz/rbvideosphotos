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

  function html(node) {
    return node ? (node.innerHTML || '').trim() : '';
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
    return body.getAttribute('data-cms-page-key') === 'homepage' || !!qs('.hp-hero, header.hero, .hero#hero');
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

  function firstNode(selectors, scope) {
    for (var i = 0; i < selectors.length; i += 1) {
      var node = qs(selectors[i], scope);
      if (node) return node;
    }
    return null;
  }

  function sectionContent(section) {
    if (!section) return { label: '', title: '', intro: '', cta: null };
    var cta = firstNode(['.hp-section-cta a', '.hero-cta-wrap a', '.btn-primary', '.btn-secondary'], section);
    return {
      label: html(firstNode(['.section-label', '.hero-tag', '.hero__eyebrow'], section)),
      title: html(firstNode(['.section-title', 'h1', 'h2'], section)),
      intro: html(firstNode(['.section-intro', '.hero-sub', '.lead'], section)),
      cta: cta ? { href: cta.getAttribute('href') || '', label: text(cta) } : null
    };
  }

  function renderSectionHeader(content, center) {
    var label = content && content.label ? '<p class="section-label">' + content.label + '</p>' : '';
    var title = content && content.title ? '<h2 class="section-title">' + content.title + '</h2>' : '';
    var intro = content && content.intro ? '<p class="section-intro">' + content.intro + '</p>' : '';
    var cls = center ? ' class="ew-section-center"' : '';
    return label || title || intro ? '<div' + cls + '>' + label + title + intro + '</div>' : '';
  }

  function pickAboutVisual() {
    var candidates = [
      '.jb-about-visual__source',
      '.ew-about-visual-source',
      '[data-ew-about-visual] img',
      '#sluzby .hp-svc-card__media img',
      '#sluzby .svc-card img'
    ];
    for (var i = 0; i < candidates.length; i += 1) {
      var src = getImageSrc(qs(candidates[i]));
      if (src) return src;
    }
    return '';
  }

  function pickContactVisual() {
    var candidates = [
      '.ew-contact-visual-source',
      '[data-ew-contact-visual] img',
      '#kontakt .hp-contact-visual img',
      '#kontakt img'
    ];
    for (var i = 0; i < candidates.length; i += 1) {
      var src = getImageSrc(qs(candidates[i]));
      if (src) return src;
    }
    return '';
  }

  function ensureNavCta() {
    var links = qs('.theme-nav-wrap .nav-links');
    if (!links) return;
    var existing = qs('.nav-cta', links);
    if (existing) existing.classList.add('ew-nav-cta');
  }

  function enhanceNavigation() {
    var links = qs('.theme-nav-wrap .nav-links');
    var inner = qs('.theme-header__inner');
    var navBrand = qs('.theme-nav-wrap .brand');

    if (inner && navBrand) inner.classList.add('ew-has-nav-brand');

    qsa('.theme-brand, .theme-nav-wrap .brand').forEach(function (brand) {
      if (brand.classList.contains('ew-brand-ready')) return;
      var label = text(brand);
      var parts = label.split(/\s+/);
      if (parts.length < 2) return;
      var mark = parts.shift();
      brand.textContent = '';
      brand.classList.add('ew-brand-ready');
      var markNode = document.createElement('span');
      markNode.className = 'ew-brand-mark';
      markNode.textContent = mark;
      var nameNode = document.createElement('span');
      nameNode.className = 'ew-brand-name';
      nameNode.textContent = parts.join(' ');
      brand.appendChild(markNode);
      brand.appendChild(nameNode);
    });

    if (!links) return;

    var hasHome = qsa('a', links).some(function (link) {
      var href = (link.getAttribute('href') || '').replace(/https?:\/\/[^/]+/i, '');
      return href === '/' || href === '' || href.indexOf('/#uvod') === 0;
    });

    if (!hasHome) {
      var home = document.createElement('a');
      home.href = '/';
      home.textContent = 'Úvod';
      home.className = 'ew-home-link';
      links.insertBefore(home, links.firstChild);
    }

  }

  function enhanceServicesPage() {
    var section = qs('.services-wrap');
    if (!section || section.classList.contains('ew-services-page-ready')) return;
    section.classList.add('ew-services-page-ready');

    qsa('.service-card', section).forEach(function (card, index) {
      var bodyNode = qs('.service-card__body', card);
      if (!bodyNode || qs('.ew-service-page-icon', bodyNode)) return;
      var titleNode = qs('h2, h3', bodyNode);
      var iconKey = iconClassFromTitle(text(titleNode), index);
      var icon = document.createElement('div');
      icon.className = 'ew-service-page-icon ew-service-page-icon--' + iconKey;
      icon.setAttribute('aria-hidden', 'true');
      icon.innerHTML = serviceIconSvg(iconKey);
      bodyNode.insertBefore(icon, bodyNode.firstChild);
    });
  }

  function enhanceHero() {
    var hero = qs('.hp-hero, header.hero, .hero#hero');
    if (!hero) return;
    hero.classList.add('ew-hero');
    qsa('.hero-cta-wrap a, .hp-hero a.btn, .hp-hero .btn-primary, .hp-hero .btn-secondary', hero).forEach(function (link) {
      link.classList.add('ew-hero-action');
    });
    var cue = qs('.jb-scroll-cue', hero);
    if (cue) cue.classList.add('ew-scroll-cue');
  }

  function collectServices() {
    return qsa('#sluzby .hp-svc-card, #sluzby .svc-card').map(function (card, index) {
      var img = qs('.hp-svc-card__media img, img', card);
      var title = firstNode(['.hp-svc-card__title', 'h3', 'h2'], card);
      var desc = firstNode(['.hp-svc-card__desc', 'p'], card);
      var link = firstNode(['.hp-svc-card__btn', 'a'], card);
      return {
        num: String(index + 1).padStart(2, '0'),
        title: text(title),
        desc: text(desc),
        href: link ? (link.getAttribute('href') || '') : '',
        buttonText: link ? text(link) : '',
        image: getImageSrc(img)
      };
    }).filter(function (item) {
      return item.title || item.desc || item.image || item.href;
    });
  }

  function iconClassFromTitle(title, index) {
    var normalized = (title || '').toLowerCase();
    if (normalized.indexOf('rodin') !== -1 || normalized.indexOf('portr') !== -1) return 'people';
    if (normalized.indexOf('reklam') !== -1 || normalized.indexOf('firm') !== -1) return 'briefcase';
    if (normalized.indexOf('hud') !== -1 || normalized.indexOf('klip') !== -1 || normalized.indexOf('music') !== -1) return 'music';
    if (normalized.indexOf('akce') !== -1 || normalized.indexOf('udal') !== -1 || normalized.indexOf('udál') !== -1) return 'calendar';
    if (normalized.indexOf('video') !== -1 || normalized.indexOf('foto') !== -1 || normalized.indexOf('svat') !== -1) return 'camera';
    return ['camera', 'people', 'briefcase', 'calendar'][index % 4];
  }

  function serviceIconSvg(key) {
    var paths = {
      camera: '<path d="M4 8.5h3l1.4-2h7.2l1.4 2h3v9H4z"/><circle cx="12" cy="13" r="3.2"/>',
      people: '<circle cx="9" cy="9" r="3"/><circle cx="16.5" cy="10" r="2.4"/><path d="M3.5 19c.4-3.1 2.3-4.8 5.5-4.8s5.1 1.7 5.5 4.8M14 15.2c.8-.6 1.8-.9 3-.9 2.4 0 3.8 1.5 4 4.2"/>',
      briefcase: '<rect x="3.5" y="7.5" width="17" height="11" rx="2"/><path d="M8.5 7.5V5.8h7v1.7M3.5 12.2c4.8 2.2 12.2 2.2 17 0M10 12.8h4"/>',
      calendar: '<rect x="4" y="5.5" width="16" height="14" rx="2"/><path d="M8 3.8v3.5M16 3.8v3.5M4 9.5h16M12 12l.8 1.7 1.9.2-1.4 1.3.4 1.9-1.7-.9-1.7.9.4-1.9-1.4-1.3 1.9-.2z"/>',
      music: '<path d="M9 18V7l10-2v10M9 9l10-2"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="15" r="2.5"/>'
    };
    return '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">' + (paths[key] || paths.camera) + '</svg>';
  }

  function enhanceServicesSection() {
    var section = qs('#sluzby');
    if (!section || section.classList.contains('ew-about-ready')) return;
    var container = qs('.container', section);
    if (!container) return;

    var services = collectServices();
    var sourceContent = sectionContent(section);
    var aboutImage = pickAboutVisual();
    var cta = sourceContent.cta;

    section.classList.add('ew-about-ready', 'ew-about-section');
    container.innerHTML =
      '<div class="ew-about-grid">' +
      '  <div class="ew-about-media">' +
      (aboutImage ? '    <img src="' + escapeHtml(aboutImage) + '" alt="" loading="lazy">' : '') +
      '  </div>' +
      '  <div class="ew-about-copy">' +
      renderSectionHeader(sourceContent, false) +
      (services.length ? '    <div class="ew-about-points">' + services.slice(0, 6).map(function (item, index) {
        return '<span class="ew-about-point ew-about-point--' + iconClassFromTitle(item.title, index) + '">' + escapeHtml(item.title || item.desc) + '</span>';
      }).join('') + '</div>' : '') +
      (cta && cta.href && cta.label ? '<div class="hp-section-cta"><a href="' + escapeHtml(cta.href) + '" class="btn-secondary">' + escapeHtml(cta.label) + '</a></div>' : '') +
      '  </div>' +
      '</div>';

    section.classList.add('ew-services-single');
  }

  function buildServicesOverview(services, afterSection, sourceContent) {
    if (!services.length || qs('.ew-services-overview')) return;

    var section = document.createElement('section');
    section.className = 'section ew-services-overview';
    section.innerHTML =
      '<div class="container">' +
      renderSectionHeader(sourceContent, true) +
      '  <div class="ew-services-list">' +
      services.slice(0, 4).map(function (item, index) {
        var href = item.href || '';
        var title = item.title || item.desc || '';
        var body = item.desc || '';
        var iconKey = iconClassFromTitle(title, index);
        return '<article class="ew-service-card">' +
          '<div class="ew-service-icon ew-service-icon--' + iconKey + '" aria-hidden="true">' +
          serviceIconSvg(iconKey) +
          '</div>' +
          (title ? '<h3>' + escapeHtml(title) + '</h3>' : '') +
          (body ? '<p>' + escapeHtml(body) + '</p>' : '') +
          (href && item.buttonText ? '<a href="' + escapeHtml(href) + '">' + escapeHtml(item.buttonText) + '</a>' : '') +
          '</article>';
      }).join('') +
      '  </div>' +
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

    qsa('.pt-tile', section).forEach(function (tile) {
      tile.classList.remove('jb-pt-tile--tall', 'jb-pt-tile--wide');
    });

    var cta = qs('.hp-section-cta a, .hp-section-cta .btn-secondary', section);
    if (cta) cta.classList.add('ew-section-link');
  }

  function hideNonTargetHomeSections() {
    qsa('#blog, #cenik').forEach(function (section) {
      section.classList.remove('ew-home-hidden');
    });
  }

  function initialsFromName(name) {
    var parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '';
    return parts.slice(0, 2).map(function (part) {
      return part.charAt(0).toUpperCase();
    }).join('');
  }

  function enhanceReviews() {
    var section = qs('#hodnoceni');
    if (!section) return;
    section.classList.add('ew-reviews');

    qsa('.hp-review-card', section).forEach(function (card, index) {
      if (index > 2) {
        card.classList.add('ew-hidden-review');
        return;
      }

      if (!qs('.jb-review-meta', card)) {
        var strong = qs('strong', card);
        var span = qs('span', card);
        var name = text(strong);
        var meta = document.createElement('div');
        meta.className = 'jb-review-meta';
        meta.innerHTML =
          (name ? '<div class="jb-review-avatar">' + escapeHtml(initialsFromName(name)) + '</div>' : '') +
          '<div class="jb-review-person">' +
          (name ? '<strong>' + escapeHtml(name) + '</strong>' : '') +
          (text(span) ? '<span>' + escapeHtml(text(span)) + '</span>' : '') +
          '</div>';
        card.appendChild(meta);
      }
    });
  }

  function enhanceFaq() {
    var section = qs('#faq');
    if (!section || section.classList.contains('ew-faq-ready')) return;
    var list = qs('.hp-faq', section);
    if (!list) return;

    var items = qsa('.hp-faq-item', list).map(function (item) {
      return {
        question: html(firstNode(['.hp-faq-question', 'strong', 'h3', 'button'], item)),
        answer: html(firstNode(['.hp-faq-answer', 'p', '.answer'], item))
      };
    }).filter(function (item) {
      return item.question;
    });

    if (!items.length) return;
    section.classList.add('ew-faq-ready');
    list.innerHTML = items.slice(0, 6).map(function (item) {
      return '<article class="hp-faq-item ew-faq-item">' +
        '<button class="ew-faq-question" type="button" aria-expanded="false">' +
        '<span>' + item.question + '</span><span class="ew-faq-plus" aria-hidden="true"></span>' +
        '</button>' +
        '<div class="ew-faq-answer">' + (item.answer ? '<p>' + item.answer + '</p>' : '') + '</div>' +
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
    if (ctaWrap) ctaWrap.classList.add('ew-section-cta');
  }

  function enhanceProcess() {
    var section = qs('#proces, .hp-process, .jb-process, [data-ew-process-source]');
    if (!section) return;
    section.classList.add('ew-process');
    qsa('.section-intro', section).forEach(function (intro) {
      if (!text(intro)) intro.remove();
    });

    var list = qs('.ew-process-list, .hp-process-list, .jb-process-list', section);
    if (!list) {
      var steps = qsa('.hp-process-step, .jb-process-step, article, li', section);
      if (!steps.length) return;
      list = document.createElement('div');
      list.className = 'ew-process-list';
      steps[0].parentNode.insertBefore(list, steps[0]);
      steps.forEach(function (step) {
        list.appendChild(step);
      });
    }
    list.classList.add('ew-process-list');
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
    var pairs = [];
    var nodes = qsa('label, input:not([type="hidden"]), textarea, select', form);

    for (var i = 0; i < nodes.length; i += 1) {
      var node = nodes[i];
      if (!node || node.tagName.toLowerCase() !== 'label') continue;
      var control = nodes[i + 1];
      if (!control || control.tagName.toLowerCase() === 'label') continue;
      var field = document.createElement('div');
      field.className = 'ew-field';
      if (control.tagName.toLowerCase() === 'textarea') field.classList.add('ew-field--wide');
      field.appendChild(node);
      field.appendChild(control);
      pairs.push(field);
      i += 1;
    }

    form.innerHTML = '';
    hidden.forEach(function (input) {
      form.appendChild(input);
    });
    pairs.forEach(function (field) {
      form.appendChild(field);
    });
    if (submit) form.appendChild(submit);
  }

  function enhanceContact() {
    var section = qs('#kontakt');
    if (!section || section.classList.contains('ew-contact-ready')) return;
    var container = qs('.container', section);
    var form = qs('form', section);
    if (!container || !form) return;

    section.classList.add('ew-contact-ready');
    normalizeContactForm(form);

    var header = document.createElement('div');
    header.className = 'ew-contact-header';
    var label = firstNode(['.section-label', '.hero-tag'], section);
    var title = firstNode(['.section-title', 'h2'], section);
    var intro = firstNode(['.section-intro', '.lead'], section);
    [label, title, intro].forEach(function (node) {
      if (node) header.appendChild(node);
    });

    var visual = pickContactVisual();
    var layout = document.createElement('div');
    layout.className = 'ew-contact-panel' + (visual ? '' : ' ew-contact-panel--no-image');
    if (visual) {
      layout.innerHTML = '<div class="ew-contact-image"><img src="' + escapeHtml(visual) + '" alt="" loading="lazy"></div>';
    }

    var right = document.createElement('div');
    right.className = 'ew-contact-form-wrap';
    if (header.childNodes.length) right.appendChild(header);
    right.appendChild(form);
    layout.appendChild(right);

    container.innerHTML = '';
    container.appendChild(layout);
  }

  function syncFooterContactFromSection() {
    var footer = qs('.site-footer-contact');
    if (!footer) return;

    function formatPhone(value) {
      var normalized = (value || '').replace(/[^\d+]/g, '');
      if (!normalized) return '';
      if (normalized.indexOf('00420') === 0) normalized = '+420' + normalized.slice(5);
      if (normalized.charAt(0) !== '+' && normalized.length === 9) {
        return normalized.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3');
      }
      if (normalized.indexOf('+420') === 0) {
        var rest = normalized.slice(4).replace(/\D/g, '');
        if (rest.length === 9) return '+420 ' + rest.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3');
      }
      return value;
    }

    function cleanContactValue(label, href, value) {
      var cleaned = (value || '').replace(/\s+/g, ' ').trim();
      cleaned = cleaned.replace(/^(telefon|tel\.?|e-?mail|email|adresa)\s*[:\-]?/i, '').trim();
      if (/^tel:/i.test(href)) {
        var fromHref = href.replace(/^tel:/i, '');
        var formatted = formatPhone(fromHref);
        return formatted || formatPhone(cleaned) || cleaned;
      }
      if (/^mailto:/i.test(href) && (!cleaned || cleaned.indexOf('@') === -1)) {
        return href.replace(/^mailto:/i, '').trim();
      }
      return cleaned;
    }

    var rows = qsa('.site-footer-contact__item', footer).map(function (item) {
      var link = item.matches && item.matches('a') ? item : qs('a', item);
      var href = link ? (link.getAttribute('href') || '') : '';
      var labelNode = qs('span', item);
      var strongNode = qs('strong', item);
      var label = text(labelNode) || (/^tel:/i.test(href) ? 'Telefon' : (/^mailto:/i.test(href) ? 'E-mail' : 'Adresa'));
      var value = text(strongNode) || (link ? text(link) : text(item).replace(text(labelNode), ''));
      value = cleanContactValue(label, href, value);
      if (!value) return null;
      return { label: label, href: href, value: value };
    }).filter(Boolean);

    if (!rows.length) {
      rows = qsa('a[href^="tel:"], a[href^="mailto:"]', document).map(function (link) {
        var href = link.getAttribute('href') || '';
        var label = /^tel:/i.test(href) ? 'Telefon' : 'E-mail';
        var value = cleanContactValue(label, href, text(link));
        if (!value) return null;
        return {
          label: label,
          href: href,
          value: value
        };
      }).filter(Boolean);
    }

    var unique = [];
    rows.forEach(function (row) {
      var key = row.href || (row.label + ':' + row.value);
      if (!unique.some(function (item) { return (item.href || (item.label + ':' + item.value)) === key; })) unique.push(row);
    });
    unique = unique.slice(0, 4);
    if (!unique.length) return;

    var title = qs('.site-footer-title', footer);
    var titleHtml = title ? title.outerHTML : '';
    footer.innerHTML = titleHtml + unique.map(function (item) {
      var tag = item.href ? 'a' : 'div';
      var href = item.href ? ' href="' + escapeHtml(item.href) + '"' : '';
      return '<' + tag + ' class="site-footer-contact__item"' + href + '>' +
        '<span>' + escapeHtml(item.label) + '</span>' +
        '<strong>' + escapeHtml(item.value) + '</strong>' +
      '</' + tag + '>';
    }).join('');
  }

  function initHeroParallax() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var heroes = qsa('.hp-hero, .ew-hero, .hero, .page-hero, .pf-hero, .bl-hero, .kt-hero, .fq-hero, .bp-hero, .pf-detail-hero, .bp-detail-hero')
      .filter(function (hero) {
        return !!qs('.theme-hero-video, .hero-video', hero);
      });
    if (!heroes.length) return;

    var ticking = false;
    function sync() {
      ticking = false;
      var viewport = window.innerHeight || document.documentElement.clientHeight || 1;
      heroes.forEach(function (hero) {
        var rect = hero.getBoundingClientRect();
        if (rect.bottom < 0 || rect.top > viewport) return;
        var centerOffset = (rect.top + rect.height / 2) - viewport / 2;
        var shift = Math.max(-52, Math.min(52, centerOffset * -0.08));
        hero.style.setProperty('--ew-hero-parallax-y', shift.toFixed(2) + 'px');
      });
    }

    function requestSync() {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(sync);
    }

    window.addEventListener('scroll', requestSync, { passive: true });
    window.addEventListener('resize', requestSync);
    sync();
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
  enhanceNavigation();
  enhanceServicesPage();

  if (isHomepage()) {
    enhanceHero();
    enhanceServicesSection();
    enhancePortfolio();
    hideNonTargetHomeSections();
    enhanceReviews();
    enhanceFaq();
    enhanceProcess();
    reorderHomeSections();
    enhanceContact();
  }

  syncFooterContactFromSection();
  initHeroParallax();
  initReveals();
})();
