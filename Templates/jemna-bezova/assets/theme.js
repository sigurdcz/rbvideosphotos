(function () {
  if (!document.body.classList.contains('theme-jemna-bezova')) return;

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

  function enhanceHero() {
    var hero = qs('.hp-hero');
    if (!hero) return;
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

    if (!qs('.jb-scroll-cue', hero)) {
      var cue = document.createElement('div');
      cue.className = 'jb-scroll-cue';
      cue.innerHTML = '<span>Scroll</span>';
      hero.appendChild(cue);
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

    var visual = document.createElement('div');
    visual.className = 'jb-about-visual';
    visual.innerHTML =
      '<div class="jb-about-visual__frame"></div>' +
      '<div class="jb-about-visual__panel"><span>[ Vase fotografie ]</span></div>' +
      '<div class="jb-about-badge"><strong>10+</strong><span>let zkusenosti</span></div>';

    var copy = document.createElement('div');
    copy.className = 'jb-about-copy';
    copy.appendChild(label);
    copy.appendChild(title);
    copy.appendChild(intro);

    var highlights = document.createElement('div');
    highlights.className = 'jb-about-highlights';
    qsa('.hp-svc-card, .svc-card', grid).slice(0, 4).forEach(function (card) {
      var item = document.createElement('div');
      item.className = 'jb-about-highlight';
      var cardTitle = qs('.hp-svc-card__title, h3', card);
      var cardDesc = qs('.hp-svc-card__desc, p', card);
      item.innerHTML =
        '<span class="jb-about-highlight__icon"></span>' +
        '<div><strong>' + ((cardTitle && cardTitle.textContent) ? cardTitle.textContent.trim() : 'Sluzba') + '</strong>' +
        '<p>' + ((cardDesc && cardDesc.textContent) ? cardDesc.textContent.trim() : '') + '</p></div>';
      highlights.appendChild(item);
    });

    copy.appendChild(highlights);
    shell.appendChild(visual);
    shell.appendChild(copy);
    container.insertBefore(shell, grid);
  }

  function enhancePortfolio() {
    var section = qs('#nase-prace');
    if (!section) return;
    var title = qs('.section-title', section);
    if (title) accentLastWord(title);

    qsa('.pt-tile', section).forEach(function (tile, index) {
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

    var icons = ['sun', 'heart', 'layers'];
    qsa('.hp-cenik-card', section).forEach(function (card, index) {
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

    qsa('.hp-faq-item', section).forEach(function (item, index) {
      if (index > 3) {
        item.classList.add('jb-process-step--hidden');
        return;
      }
      if (!qs('.jb-process-step__num', item)) {
        var num = document.createElement('div');
        num.className = 'jb-process-step__num';
        num.textContent = String(index + 1).padStart(2, '0');
        item.insertBefore(num, item.firstChild);
      }
    });
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
    meta.className = 'jb-contact-meta';

    qsa('.hp-contact-row', infoBox).forEach(function (row) {
      var label = qs('.hp-contact-label', row);
      var link = qs('a', row);
      var labelText = ((label && label.textContent) || '').toLowerCase();
      if (!link || /sit|site|social|instagram|facebook|youtube|tiktok|linkedin|pinterest|telegram|whatsapp/.test(labelText)) {
        return;
      }
      var item = document.createElement('div');
      item.className = 'jb-contact-meta__item';
      item.innerHTML =
        '<span class="jb-contact-meta__label">' + (label ? label.textContent.trim() : '') + '</span>' +
        '<a href="' + link.getAttribute('href') + '">' + link.textContent.trim() + '</a>';
      meta.appendChild(item);
    });

    formBox.appendChild(meta);
    infoBox.classList.add('jb-contact-box--hidden');
  }

  enhanceHero();
  enhanceServices();
  enhancePortfolio();
  enhancePricing();
  enhanceReviews();
  enhanceFaq();
  enhanceContact();
})();
