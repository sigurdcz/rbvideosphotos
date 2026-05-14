(function () {
  if (!document.body.classList.contains('theme-editorial-gold')) return;

  function qs(selector, scope) {
    return (scope || document).querySelector(selector);
  }

  function createStats() {
    var hero = qs('.hp-hero');
    if (qs('.eg-stats') || !hero) return;
    var stats = document.createElement('section');
    stats.className = 'eg-stats';
    stats.innerHTML =
      '<div><strong>150+</strong><span>spokojenych klientu</span></div>' +
      '<div><strong>8+</strong><span>let zkusenosti</span></div>' +
      '<div><strong>5 let</strong><span>zalohy fotek zdarma</span></div>' +
      '<div><strong>2</strong><span>fotograf & kameraman</span></div>';
    hero.insertAdjacentElement('afterend', stats);
  }

  function enhanceHero() {
    var hero = qs('.hp-hero');
    if (!hero) return;
    var cta = qs('.hero-cta-wrap', hero);
    if (cta && !qs('.eg-hero-secondary', cta)) {
      var firstPhone = qs('a[href^="tel:"]');
      var primary = qs('.btn-primary', cta) || cta.querySelector('a');
      if (primary) {
        primary.textContent = firstPhone ? 'Zavolat: ' + firstPhone.textContent.replace(/^Telefon\s*/i, '').trim() : 'Zavolejte nam';
        if (firstPhone) primary.href = firstPhone.href;
      }
      var services = document.createElement('a');
      services.className = 'btn-secondary eg-hero-secondary';
      services.href = '#sluzby';
      services.textContent = 'Nase sluzby';
      cta.appendChild(services);
    }
    if (!qs('.eg-play-story', hero)) {
      var play = document.createElement('a');
      play.className = 'eg-play-story';
      play.href = '/nase-prace';
      play.innerHTML = '<span class="eg-play-story__circle">&#9654;</span><span>Podivejte se<br>na nasi praci</span>';
      hero.appendChild(play);
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
    if (existing) {
      enhanceExistingReviews(existing);
      return;
    }
    var reviewItems = [
      ['Jan Jungling', 'Svatba M+H', 'Kluci jsou naprosti profesionalove. Jsou peclivi a dokazou zachytit skutecne emoce.'],
      ['Vaclav Konopka', 'Svatebni foceni', 'Super domluva, mily a profesionalni pristup k foceni. Velka spokojenost.'],
      ['Jana Dudkova', 'Rodinne foceni', 'Moc doporucuji. Vse profesionalne nafocene, krasne barvy a skvela domluva.'],
      ['Marketa a Tomas', 'Svatebni video', 'Video vystihlo atmosferu dne do posledniho detailu. Vracime se k nemu porad dokola.'],
      ['Lucie Novakova', 'Portretni foceni', 'Foceni bylo uvolnene a bez stresu. Vysledek pusobi prirozene a profesionalne.'],
      ['Petr Maly', 'Firemni akce', 'Rychla domluva, jistota na miste a vystupy pouzitelne rovnou na web i socialni site.']
    ];
    var cards = reviewItems.map(function (review) {
      return '<article class="eg-review-card"><div class="eg-stars">*****</div><p>' + review[2] + '</p><strong>' + review[0] + '</strong><span>' + review[1] + '</span></article>';
    }).join('');
    var reviews = document.createElement('section');
    reviews.className = 'section eg-reviews';
    reviews.innerHTML =
      '<div class="container eg-reviews__inner">' +
      '<div class="eg-reviews__title"><p class="section-label">Co o nas rikaji</p><h2 class="section-title">Hodnoceni<br>nasich paru</h2></div>' +
      '<div class="eg-reviews__body">' +
      '<button class="eg-review-arrow eg-review-arrow--prev" type="button" aria-label="Predchozi recenze">&lsaquo;</button>' +
      '<div class="eg-reviews__viewport"><div class="eg-reviews__track">' + cards + '</div></div>' +
      '<button class="eg-review-arrow eg-review-arrow--next" type="button" aria-label="Dalsi recenze">&rsaquo;</button>' +
      '</div>' +
      '<div class="eg-reviews__cta"><a class="btn-secondary" href="/hodnoceni">Vsechny recenze</a><a class="btn-secondary" href="/hodnoceni">O vsech hodnocenich</a></div>' +
      '</div>';
    qs('#kontakt').insertAdjacentElement('beforebegin', reviews);
    initReviewCarousel(reviews);
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
    if (!qs('.eg-reviews__cta', section)) {
      var cta = document.createElement('div');
      cta.className = 'eg-reviews__cta';
      cta.innerHTML = '<a class="btn-secondary" href="/hodnoceni">Vsechny recenze</a><a class="btn-secondary" href="/hodnoceni">O vsech hodnocenich</a>';
      container.appendChild(cta);
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
    band.innerHTML =
      '<div class="eg-contact-band__photo" aria-hidden="true"></div>' +
      '<div class="eg-contact-band__copy"><p class="section-label">Kontakt</p><h2>Pojdme vytvorit<br><span>neco krasneho</span></h2><p>Nevahajte se nam ozvat. Pripravime nabidku na miru.</p></div>' +
      '<div class="eg-contact-band__info">' + lines + '</div>' +
      '<div class="eg-contact-band__actions"><a class="btn-primary" href="' + (contactLink ? contactLink.href : '/kontakt') + '">Kontakt</a>' + socials + '</div>';
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
