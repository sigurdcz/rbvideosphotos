(function () {
  if (!document.body.classList.contains('theme-jemna-bezova') && !document.body.classList.contains('theme-elegant-white')) return;

  function qs(selector, scope) {
    return (scope || document).querySelector(selector);
  }

  function qsa(selector, scope) {
    return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
  }

  function accentLastWord(element) {
    if (!element || element.querySelector('.jb-title-accent')) return;
    var text = (element.textContent || '').replace(/\s+/g, ' ').trim();
    if (!text) return;
    var words = text.split(' ');
    if (words.length < 2) return;
    var accent = words.pop();
    element.innerHTML = words.join(' ') + ' <span class="jb-title-accent">' + accent + '</span>';
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
      home.textContent = 'Úvod';
      home.className = 'nav-home-link';
      links.insertBefore(home, links.firstChild);
    }

    function syncState() {
      var open = toggle.checked === true;
      label.classList.toggle('is-active', open);
      label.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.classList.toggle('jb-nav-open', open);
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

  function markReveals() {
    qsa(
      '.jb-about-shell, .pt-tile, .hp-blog-card, .hp-cenik-card, .hp-review-card, .hp-faq-item, .jb-process-step, .hp-section-cta, .jb-contact-meta'
    ).forEach(function (element) {
      element.classList.add('jb-reveal');
    });
  }

  function initReveals() {
    markReveals();
    var elements = qsa('.jb-reveal');
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
      element.style.transitionDelay = Math.min(index * 70, 280) + 'ms';
      observer.observe(element);
    });
  }

  function enhanceHero() {
    var hero = qs('.hp-hero');
    if (!hero) return;
    var container = qs('.container', hero);
    if (!container) return;

    hero.classList.add('jb-hero');

    var cta = qs('.hero-cta-wrap', hero);
    if (cta && !qs('.jb-hero-secondary', cta)) {
      var secondary = document.createElement('a');
      secondary.className = 'btn-secondary jb-hero-secondary';
      secondary.href = '#nase-prace';
      secondary.textContent = 'Portfolio';
      cta.appendChild(secondary);
    }

    var heading = qs('h1', hero);
    if (heading && !heading.querySelector('span')) {
      accentLastWord(heading);
    }

    var cue = qs('.jb-scroll-cue', hero);
    if (!cue) {
      cue = document.createElement('div');
      cue.className = 'jb-scroll-cue';
      cue.innerHTML = '<span>Scroll</span>';
    }

    if (cue.parentNode !== container) {
      container.appendChild(cue);
    }
  }

  function enhanceServices() {
    var section = qs('#sluzby');
    if (!section || qs('.jb-about-shell', section)) return;
    var container = qs('.container', section);
    var label = qs('.section-label', section);
    var title = qs('.section-title', section);
    var intro = qs('.section-intro', section);
    var grid = qs('.hp-services-grid, .svc-grid', section);
    if (!container || !label || !title || !intro || !grid) return;

    accentLastWord(title);
    section.classList.add('jb-about-section');

    var shell = document.createElement('div');
    shell.className = 'jb-about-shell';

    function pickVisualImage() {
      var sourceImage = qs('.jb-about-visual__source');
      if (sourceImage) {
        var sourceSrc = sourceImage.getAttribute('data-src') || sourceImage.getAttribute('src') || '';
        if (sourceSrc) {
          return sourceSrc;
        }
      }
      var serviceImage = qs('.hp-svc-card__media img, .svc-card img', grid);
      if (serviceImage && serviceImage.getAttribute('src')) {
        return serviceImage.getAttribute('src');
      }
      var portfolioImage = qs('#nase-prace .pt-thumb img');
      if (portfolioImage && portfolioImage.getAttribute('src')) {
        return portfolioImage.getAttribute('src');
      }
      var blogImage = qs('.hp-blog-thumb img');
      return blogImage ? blogImage.getAttribute('src') : '';
    }

    var visualImage = pickVisualImage();
    var visualInner = visualImage
      ? '<img class="jb-about-visual__media" src="' + visualImage + '" alt="Nase sluzby" loading="lazy">'
      : '<span>[ Vase fotografie ]</span>';

    var visual = document.createElement('div');
    visual.className = 'jb-about-visual jb-reveal';
    visual.innerHTML =
      '<div class="jb-about-visual__frame"></div>' +
      '<div class="jb-about-visual__panel' + (visualImage ? ' has-image' : '') + '">' + visualInner + '</div>' +
      '<div class="jb-about-badge"><strong>10+</strong><span>let zkusenosti</span></div>';

    var copy = document.createElement('div');
    copy.className = 'jb-about-copy jb-reveal';
    copy.appendChild(label);
    copy.appendChild(title);
    copy.appendChild(intro);

    var highlights = document.createElement('div');
    highlights.className = 'jb-about-highlights';
    qsa('.hp-svc-card, .svc-card', grid).slice(0, 4).forEach(function (card) {
      card.classList.add('jb-panel-card');
      var item = document.createElement('div');
      item.className = 'jb-about-highlight';
      var cardTitle = qs('.hp-svc-card__title, h3', card);
      var cardDesc = qs('.hp-svc-card__desc, p', card);
      item.innerHTML =
        '<span class="jb-about-highlight__icon" aria-hidden="true"><span></span></span>' +
        '<div><strong>' + ((cardTitle && cardTitle.textContent) ? cardTitle.textContent.trim() : 'Sluzba') + '</strong>' +
        '<p>' + ((cardDesc && cardDesc.textContent) ? cardDesc.textContent.trim() : '') + '</p></div>';
      highlights.appendChild(item);
    });

    if (highlights.children.length < 4) {
      var extra = document.createElement('div');
      extra.className = 'jb-about-highlight jb-about-highlight--accent';
      extra.innerHTML =
        '<span class="jb-about-highlight__icon" aria-hidden="true"><span></span></span>' +
        '<div><strong>Akce & události</strong>' +
        '<p>Konference, kulturní akce, plesy i firemní večery. Zachytím důležité momenty, které se stanou součástí vaší značky.</p></div>';
      highlights.appendChild(extra);
    }

    copy.appendChild(highlights);
    shell.appendChild(visual);
    shell.appendChild(copy);
    container.insertBefore(shell, grid);

    if (qs('.hp-hero')) {
      grid.style.display = 'none';
    }
  }

  function enhancePortfolio() {
    var section = qs('#nase-prace');
    if (!section) return;
    var title = qs('.section-title', section);
    if (title) accentLastWord(title);

    qsa('.pt-tile', section).forEach(function (tile, index) {
      tile.classList.add('jb-panel-card');
      var thumb = qs('.pt-thumb', tile);
      var caption = qs('.pt-caption', tile);
      if (thumb && caption && !qs('.jb-pt-label', thumb)) {
        var label = document.createElement('div');
        label.className = 'jb-pt-label';
        label.textContent = (caption.textContent || '').trim();
        thumb.appendChild(label);
        caption.remove();
      }

      if (index === 0) tile.classList.add('jb-pt-tile--tall');
      if (index === 3) tile.classList.add('jb-pt-tile--wide');
    });
  }

  function enhancePricing() {
    var section = qs('#cenik');
    if (!section) return;
    var title = qs('.section-title', section);
    if (title) accentLastWord(title);

    var icons = ['basic', 'standard', 'premium'];
    qsa('.hp-cenik-card', section).forEach(function (card, index) {
      card.classList.add('jb-panel-card');
      if (!qs('.jb-price-icon', card)) {
        var icon = document.createElement('div');
        icon.className = 'jb-price-icon jb-price-icon--' + (icons[index] || 'spark');
        card.insertBefore(icon, card.firstChild);
      }

      if (card.classList.contains('hp-cenik-card--featured') && !qs('.jb-price-badge', card)) {
        var badge = document.createElement('div');
        badge.className = 'jb-price-badge';
        badge.textContent = 'Nejoblibenejsi';
        card.appendChild(badge);
      }
    });
  }

  function initialsFromName(name) {
    var parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'R';
    return parts.slice(0, 2).map(function (part) {
      return part.charAt(0).toUpperCase();
    }).join('&');
  }

  function enhanceReviews() {
    var section = qs('#hodnoceni');
    if (!section) return;
    var title = qs('.section-title', section);
    if (title) accentLastWord(title);

    qsa('.hp-review-card', section).forEach(function (card, index) {
      card.classList.add('jb-panel-card');
      if (index > 2) {
        card.classList.add('jb-review-card--hidden');
        return;
      }

      if (!qs('.jb-review-meta', card)) {
        var strong = qs('strong', card);
        var span = qs('span', card);
        var meta = document.createElement('div');
        meta.className = 'jb-review-meta';
        meta.innerHTML =
          '<div class="jb-review-avatar">' + initialsFromName(strong ? strong.textContent : '') + '</div>' +
          '<div class="jb-review-person"><strong>' + (strong ? strong.textContent : '') + '</strong><span>' + (span ? span.textContent : '') + '</span></div>';
        card.appendChild(meta);
      }
    });
  }

  function enhanceFaq() {
    var section = qs('#faq');
    if (!section) return;
    var title = qs('.section-title', section);
    if (title) accentLastWord(title);

    qsa('.hp-faq-item', section).forEach(function (item) {
      item.classList.add('jb-panel-card');

      var question = qs('.hp-faq-question, strong', item);
      if (question) {
        var text = (question.textContent || '').trim();
        if (text.length > 30) {
          question.textContent = text.slice(0, 30).trimEnd() + '…';
        }
      }

      var answer = qs('.hp-faq-answer, p', item);
      if (answer) {
        var answerText = (answer.textContent || '').trim();
        answer.textContent = answerText.length > 20
          ? answerText.slice(0, 20).trimEnd() + '…'
          : answerText;
        answer.className = 'hp-faq-excerpt';
        if (question) {
          var hr = document.createElement('hr');
          hr.className = 'hp-faq-hr';
          question.insertAdjacentElement('afterend', hr);
        }
      }

      item.style.cursor = 'pointer';
      item.addEventListener('click', function () {
        window.location.href = '/faq';
      });
    });
  }

  function buildStaticProcess() {
    var reviews = qs('#hodnoceni');
    var contact = qs('#kontakt');
    if (!contact || qs('.jb-process')) return;

    var section = document.createElement('section');
    section.className = 'section jb-process';
    section.innerHTML =
      '<div class="container">' +
      '  <div class="jb-process__header jb-reveal">' +
      '    <p class="section-label">Jak to probiha</p>' +
      '    <h2 class="section-title">Cesta k vasim <span>dokonalym fotkam</span></h2>' +
      '  </div>' +
      '  <div class="jb-process__steps">' +
      '    <article class="jb-process-step jb-reveal"><div class="jb-process-step__num">01</div><h3>Seznameni</h3><p>Nezavazna konzultace, kde probereme vase predstavy, styl a termin foceni nebo svatby.</p></article>' +
      '    <article class="jb-process-step jb-reveal"><div class="jb-process-step__num">02</div><h3>Planovani</h3><p>Spolecne nastavime harmonogram, lokace a dulezite momenty, ktere chceme zachytit.</p></article>' +
      '    <article class="jb-process-step jb-reveal"><div class="jb-process-step__num">03</div><h3>Vas den</h3><p>Diskretne zachytim emoce, detaily i spontanni okamziky tak, aby vse pusobilo prirozene.</p></article>' +
      '    <article class="jb-process-step jb-reveal"><div class="jb-process-step__num">04</div><h3>Predani</h3><p>Hotove vystupy dostanete v online galerii a ve finalni podobe pripravene pro dalsi sdileni i tisk.</p></article>' +
      '  </div>' +
      '</div>';

    if (reviews && reviews.parentNode) {
      reviews.parentNode.insertBefore(section, contact);
      return;
    }
    contact.parentNode.insertBefore(section, contact);
  }

  function detectSocialNetwork(name, href) {
    var text = ((name || '') + ' ' + (href || '')).toLowerCase();
    if (/instagram/.test(text)) return 'instagram';
    if (/facebook|fb\.com/.test(text)) return 'facebook';
    if (/youtube/.test(text)) return 'youtube';
    if (/tiktok/.test(text)) return 'tiktok';
    if (/linkedin/.test(text)) return 'linkedin';
    if (/pinterest/.test(text)) return 'pinterest';
    return 'link';
  }

  function enhanceContact() {
    var section = qs('#kontakt');
    if (!section || qs('.jb-contact-meta', section)) return;
    var title = qs('.section-title', section);
    if (title) accentLastWord(title);

    var infoBox = qsa('.hp-contact-box', section)[0];
    var formBox = qsa('.hp-contact-box', section)[1];
    if (!infoBox || !formBox) return;

    var meta = document.createElement('div');
    meta.className = 'jb-contact-meta jb-reveal';

    var socialLinks = [];

    qsa('.hp-contact-row', infoBox).forEach(function (row) {
      var label = qs('.hp-contact-label', row);
      var link = qs('a', row);
      var labelText = ((label && label.textContent) || '').toLowerCase();
      if (!link) return;

      var labelNorm = labelText.normalize('NFD').replace(/[̀-ͯ]/g, '');
      if (/sit|site|social|instagram|facebook|youtube|tiktok|linkedin|pinterest|telegram|whatsapp/.test(labelNorm)) {
        socialLinks.push({ href: link.getAttribute('href'), name: link.textContent.trim() });
        return;
      }

      var item = document.createElement('div');
      item.className = 'jb-contact-meta__item';
      item.innerHTML =
        '<span class="jb-contact-meta__label">' + (label ? label.textContent.trim() : '') + '</span>' +
        '<a href="' + link.getAttribute('href') + '">' + link.textContent.trim() + '</a>';
      meta.appendChild(item);
    });

    if (socialLinks.length) {
      var socialsWrap = document.createElement('div');
      socialsWrap.className = 'jb-contact-socials';
      socialLinks.forEach(function (s) {
        var network = detectSocialNetwork(s.name, s.href);
        var btn = document.createElement('a');
        btn.className = 'jb-contact-social-btn';
        btn.href = s.href;
        btn.target = '_blank';
        btn.rel = 'noopener';
        btn.innerHTML =
          '<span class="jb-contact-social-btn__icon jb-contact-social-btn__icon--' + network + '"></span>' +
          '<span class="jb-contact-social-btn__label">' + s.name + '</span>';
        socialsWrap.appendChild(btn);
      });
      meta.appendChild(socialsWrap);
    }

    formBox.appendChild(meta);
    formBox.classList.add('jb-panel-card');
    infoBox.classList.add('jb-contact-box--hidden');
  }

  function syncFooterContactFromSection() {
    var section = qs('#kontakt');
    var footer = qs('.site-footer-contact');
    if (!section || !footer) return;

    var rows = qsa('.hp-contact-row', section).map(function (row) {
      var label = qs('.hp-contact-label', row);
      var link = qs('a', row);
      var labelText = ((label && label.textContent) || '').trim();
      var labelKey = labelText.toLowerCase();
      var href = link ? (link.getAttribute('href') || '') : '';
      if (!link) return null;
      var labelKeyNorm = labelKey.normalize('NFD').replace(/[̀-ͯ]/g, '');
      if (/sit|site|social|instagram|facebook|youtube|tiktok|linkedin|pinterest|telegram|whatsapp/.test(labelKeyNorm)) {
        return null;
      }
      if (!/^(tel:|mailto:)/i.test(href)) {
        return null;
      }
      return {
        label: labelText,
        href: href,
        value: (link.textContent || '').trim()
      };
    }).filter(Boolean);

    if (!rows.length) return;

    var title = qs('.site-footer-title', footer);
    var titleHtml = title ? title.outerHTML : '<div class="site-footer-title">Kontakt</div>';
    footer.innerHTML = titleHtml + rows.map(function (item) {
      return '<a class="site-footer-contact__item" href="' + item.href + '">' +
        '<span>' + item.label + '</span>' +
        '<strong>' + item.value + '</strong>' +
      '</a>';
    }).join('');
  }

  initHeaderScroll();
  initMobileNav();
  enhanceHero();
  enhanceServices();
  enhancePortfolio();
  enhancePricing();
  enhanceReviews();
  enhanceFaq();
  buildStaticProcess();
  enhanceContact();
  syncFooterContactFromSection();
  initReveals();
})();
