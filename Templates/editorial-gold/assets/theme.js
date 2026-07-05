(function () {
  if (!document.body.classList.contains('theme-editorial-gold')) return;

  function qs(selector, scope) {
    return (scope || document).querySelector(selector);
  }

  function createStats() {}

  function enhanceHero() {
    var hero = qs('.hp-hero');
    if (!hero) return;
    var cta = qs('.hero-cta-wrap', hero);
    if (cta && !qs('.eg-hero-secondary', cta)) {
      var firstPhone = qs('a[href^="tel:"]');
      var primary = qs('.btn-primary', cta) || cta.querySelector('a');
      if (primary && firstPhone) primary.href = firstPhone.href;
    }
    if (!qs('.eg-video-toggle', hero) && qs('.theme-hero-video', hero)) {
      var toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'eg-video-toggle';
      toggle.textContent = 'Pause';
      var iframePaused = false;
      toggle.addEventListener('click', function () {
        var video = qs('video.theme-hero-video', hero);
        if (video && video.paused) {
          video.play();
          toggle.textContent = 'Pause';
          return;
        }
        if (video) {
          video.pause();
          toggle.textContent = 'Play';
          return;
        }
        var iframe = qs('.theme-hero-video iframe', hero);
        if (!iframe || !iframe.contentWindow) return;
        iframePaused = !iframePaused;
        iframe.contentWindow.postMessage(JSON.stringify({ method: iframePaused ? 'pause' : 'play' }), '*');
        toggle.textContent = iframePaused ? 'Play' : 'Pause';
      });
      hero.appendChild(toggle);
    }
  }

  function createReviews() {
    if (qs('.eg-reviews') || !qs('#kontakt')) return;
    var existing = qs('.hp-reviews-demo');
    if (!existing) return;
    enhanceExistingReviews(existing);
  }

  function enhanceExistingReviews(section) {
    if (!section || qs('.eg-reviews__viewport', section)) return;
    var container = qs('.container', section);
    var sourceGrid = qs('.hp-reviews-grid', section);
    if (!container || !sourceGrid) return;
    section.classList.add('eg-reviews');
    sourceGrid.classList.add('eg-reviews__track');
    Array.prototype.slice.call(sourceGrid.children).forEach(function (card) {
      card.classList.add('eg-review-card');
    });
    var body = document.createElement('div');
    body.className = 'eg-reviews__body';
    body.innerHTML =
      '<button class="eg-review-arrow eg-review-arrow--prev" type="button" aria-label="Predchozi recenze">&lsaquo;</button>' +
      '<div class="eg-reviews__viewport"></div>' +
      '<button class="eg-review-arrow eg-review-arrow--next" type="button" aria-label="Dalsi recenze">&rsaquo;</button>';
    var viewport = qs('.eg-reviews__viewport', body);
    sourceGrid.parentNode.insertBefore(body, sourceGrid);
    viewport.appendChild(sourceGrid);
    var existingCta = qs('[data-block="reviews_cta"], .hp-section-cta, .eg-reviews__cta', section);
    if (existingCta) {
      existingCta.classList.add('eg-reviews__cta');
      Array.prototype.slice.call(existingCta.querySelectorAll('a')).forEach(function (link, index) {
        if (index > 0) {
          link.remove();
        }
      });
    }
    initReviewCarousel(section);
  }

  function initReviewCarousel(section) {
    var track = qs('.eg-reviews__track', section);
    var cards = Array.prototype.slice.call(section.querySelectorAll('.eg-review-card'));
    var prev = qs('.eg-review-arrow--prev', section);
    var next = qs('.eg-review-arrow--next', section);
    if (!track || !cards.length || !prev || !next) return;
    var index = 0;

    function visibleCount() {
      if (window.innerWidth >= 980) return 3;
      if (window.innerWidth >= 680) return 2;
      return 1;
    }

    function update() {
      var visible = visibleCount();
      var max = Math.max(0, cards.length - visible);
      index = Math.max(0, Math.min(index, max));
      track.style.transform = 'translateX(' + (-index * (100 / visible)) + '%)';
      cards.forEach(function (card) {
        card.style.flexBasis = 'calc(' + (100 / visible) + '% - 12px)';
      });
    }

    prev.addEventListener('click', function () {
      var visible = visibleCount();
      index = index <= 0 ? Math.max(0, cards.length - visible) : index - 1;
      update();
    });

    next.addEventListener('click', function () {
      var visible = visibleCount();
      var max = Math.max(0, cards.length - visible);
      index = index >= max ? 0 : index + 1;
      update();
    });

    window.addEventListener('resize', update);
    update();
  }

  function enhanceContactBand() {
    return;
    var section = qs('#kontakt');
    if (!section || qs('.eg-contact-band', section)) return;
    var infoBox = qs('.hp-contact-box', section);
    var rows = infoBox ? Array.prototype.slice.call(infoBox.querySelectorAll('.hp-contact-row')) : [];
    var lines = rows.filter(function (row) {
      var label = (qs('.hp-contact-label', row) || {}).textContent || '';
      return label.toLowerCase().indexOf('sit') === -1 && label.toLowerCase().indexOf('sí') === -1;
    }).map(function (row) {
      return row.outerHTML;
    }).join('');
    lines = rows.filter(function (row) {
      var label = (qs('.hp-contact-label', row) || {}).textContent || '';
      var href = (qs('a', row) || {}).href || '';
      var key = (label + ' ' + href).toLowerCase();
      return !/(sit|site|social|instagram|facebook|youtube|tiktok|linkedin|pinterest|whatsapp|telegram)/.test(key);
    }).map(function (row) {
      return row.outerHTML;
    }).join('');
    var contactLink = qs('a[href="/kontakt"]', section);
    var socials = Array.prototype.slice.call(document.querySelectorAll('.site-footer-social')).slice(0, 3).map(function (link) {
      return link.outerHTML;
    }).join('');
    var band = document.createElement('div');
    band.className = 'eg-contact-band';
    band.innerHTML = '';
    var container = qs('.container', section);
    if (container) {
      Array.prototype.slice.call(container.children).forEach(function (child) {
        child.style.display = 'none';
      });
      container.appendChild(band);
    }
  }

  createStats();
  enhanceHero();
  createReviews();
  enhanceContactBand();
})();
