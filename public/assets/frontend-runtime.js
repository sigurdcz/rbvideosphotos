(function(){
  function qs(selector, scope) {
    return (scope || document).querySelector(selector);
  }

  function wrapLegacySectionCtas() {
    document.querySelectorAll('.section > .container > div > a.btn-secondary').forEach(function(link){
      var parent = link.parentElement;
      if (!parent || parent.classList.contains('hp-section-cta') || parent.closest('.hp-section-cta')) {
        return;
      }
      if (parent.children.length !== 1) {
        return;
      }
      parent.classList.add('hp-section-cta');
    });
  }

  function initActiveNav() {
    var allLinks = Array.prototype.slice.call(document.querySelectorAll('.nav-links a[href]'));
    if (!allLinks.length) return;

    function normalizePath(path) {
      if (!path) return '/';
      path = path.replace(/\/+$/, '');
      return path === '' ? '/' : path;
    }

    var currentPath = normalizePath(window.location.pathname || '/');
    var currentHash = window.location.hash || '';
    var isHome = currentPath === '/';

    function activate(link) {
      allLinks.forEach(function(item){ item.classList.remove('nav-active'); });
      if (link) link.classList.add('nav-active');
    }

    function parseNavUrl(href) {
      if (!href) return null;
      try {
        return new URL(href, window.location.origin);
      } catch (error) {
        return null;
      }
    }

    function isSectionNavLink(link) {
      var href = link.getAttribute('href') || '';
      if (!href || href.charAt(0) === '#') return true;
      var url = parseNavUrl(href);
      return !!(url && url.hash);
    }

    function linkForSection(id) {
      return document.querySelector('.nav-links a[href="#' + id + '"], .nav-links a[href="/#' + id + '"]');
    }

    function sectionsFromNav() {
      return allLinks
        .map(function(link) {
          var url = parseNavUrl(link.getAttribute('href') || '');
          var id = url && url.hash ? url.hash.replace(/^#/, '') : '';
          if (!id) return null;
          var target = document.getElementById(id);
          return target ? { id: id, element: target, link: link } : null;
        })
        .filter(Boolean);
    }

    function visibleSectionLink(sectionEntries) {
      if (!sectionEntries.length) return null;
      var viewportCenter = window.innerHeight * 0.42;
      var best = null;

      sectionEntries.forEach(function(entry) {
        var rect = entry.element.getBoundingClientRect();
        if (rect.bottom <= 0 || rect.top >= window.innerHeight) {
          return;
        }

        var distance = Math.abs(rect.top - viewportCenter);
        if (!best || distance < best.distance) {
          best = {
            distance: distance,
            link: entry.link
          };
        }
      });

      return best ? best.link : null;
    }

    var directLink = null;

    allLinks.forEach(function(link) {
      var href = link.getAttribute('href') || '';
      if (!href || href.charAt(0) === '#') return;
      var url = parseNavUrl(href);
      if (!url) {
        return;
      }

      var linkPath = normalizePath(url.pathname);
      var linkHash = url.hash || '';
      var hashOnlyHomeLink = isHome && (href === '/' || href === '/#uvod' || href === '#uvod');

      if (hashOnlyHomeLink) {
        directLink = link;
        return;
      }

      if (isSectionNavLink(link) && !(linkHash && linkHash === currentHash && linkPath === currentPath)) {
        return;
      }

      if (linkPath === currentPath) {
        directLink = link;
      }
    });

    if (!isHome) {
      activate(directLink);
      return;
    }

    var sectionEntries = sectionsFromNav();
    if (!sectionEntries.length) {
      if (directLink) activate(directLink);
      return;
    }

    if (currentHash) {
      var hashLink = linkForSection(currentHash.replace(/^#/, ''));
      if (hashLink) activate(hashLink);
    } else {
      activate(visibleSectionLink(sectionEntries) || directLink);
    }

    var io = new IntersectionObserver(function(entries){
      var intersecting = entries.some(function(entry){ return entry.isIntersecting; });
      if (!intersecting) return;
      var active = visibleSectionLink(sectionEntries);
      if (active) activate(active);
    }, { rootMargin: '-40% 0px -40% 0px' });
    sectionEntries.forEach(function(entry){ io.observe(entry.element); });
  }

  function initPricingModal() {
    var modal = document.querySelector('[data-cenik-modal]');
    if (!modal || modal.dataset.bound === '1') return;
    modal.dataset.bound = '1';
    var planEl = modal.querySelector('[data-cenik-plan]');
    var message = modal.querySelector('textarea[name="message"]');
    var buttons = document.querySelectorAll('[data-cenik-open]');

    function openModal(plan) {
      if (planEl) planEl.textContent = plan || 'Nezavazne poptat';
      if (message) {
        message.value = plan ? ('Mam zajem o sluzbu: ' + plan + '\n\nProsim o nezavaznou nabidku.') : '';
      }
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    }

    buttons.forEach(function(button){
      button.addEventListener('click', function(){
        openModal(button.dataset.plan || '');
      });
    });
    modal.querySelectorAll('[data-cenik-close]').forEach(function(button){
      button.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', function(event){
      if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', function(event){
      if (event.key === 'Escape') closeModal();
    });
  }

  function initEditorialGoldEnhancements() {
    if (!document.body.classList.contains('theme-editorial-gold')) return;

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
        '<button class="eg-review-arrow eg-review-arrow--prev" type="button" aria-label="Předchozí recenze">&lsaquo;</button>' +
        '<div class="eg-reviews__viewport"></div>' +
        '<button class="eg-review-arrow eg-review-arrow--next" type="button" aria-label="Další recenze">&rsaquo;</button>';
      var viewport = qs('.eg-reviews__viewport', body);
      sourceGrid.parentNode.insertBefore(body, sourceGrid);
      viewport.appendChild(sourceGrid);
      var existingCta = qs('[data-block="reviews_cta"], .hp-section-cta, .eg-reviews__cta', section);
      if (existingCta) {
        existingCta.classList.add('eg-reviews__cta');
        Array.prototype.slice.call(existingCta.querySelectorAll('a')).forEach(function (link, index) {
          if (index > 0) link.remove();
        });
      }
      initReviewCarousel(section);
    }

    function createReviews() {
      if (qs('.eg-reviews') || !qs('#kontakt')) return;
      var existing = qs('.hp-reviews-demo');
      if (!existing) return;
      enhanceExistingReviews(existing);
    }

    function enhanceContactBand() {
      var section = qs('#kontakt');
      if (!section || qs('.eg-contact-band', section)) return;
      var infoBox = qs('.hp-contact-box', section);
      var rows = infoBox ? Array.prototype.slice.call(infoBox.querySelectorAll('.hp-contact-row')) : [];
      var lines = rows.filter(function (row) {
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

    wrapLegacySectionCtas();
    createStats();
    enhanceHero();
    createReviews();
    enhanceContactBand();
  }

  function syncAdminBarOffset() {
    var bar = document.getElementById('cms-adminbar');
    if (!bar || !document.documentElement) return;
    document.documentElement.style.setProperty('--cms-adminbar-offset', bar.offsetHeight + 'px');
  }

  function initHeroParallax() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (window.matchMedia && window.matchMedia('(max-width: 900px)').matches) return;
    if (navigator.connection && navigator.connection.saveData) return;
    var heroes = Array.prototype.slice.call(document.querySelectorAll('.hp-hero,.hero,.page-hero,.pf-hero,.bl-hero,.kt-hero,.fq-hero,.bp-hero,.pf-detail-hero,.bp-detail-hero,.services-hero'))
      .filter(function(hero) {
        return !!hero.querySelector('.theme-hero-video,.hero-bg');
      });
    if (!heroes.length) return;

    var ticking = false;
    function sync() {
      ticking = false;
      var viewport = window.innerHeight || document.documentElement.clientHeight || 1;
      var measurements = heroes.map(function(hero) {
        var rect = hero.getBoundingClientRect();
        if (rect.bottom < 0 || rect.top > viewport) return null;
        var centerOffset = (rect.top + rect.height / 2) - viewport / 2;
        var shift = Math.max(-54, Math.min(54, centerOffset * -0.08));
        return {hero: hero, value: shift.toFixed(2) + 'px'};
      }).filter(Boolean);
      measurements.forEach(function(item) {
        item.hero.style.setProperty('--ew-hero-parallax-y', item.value);
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

  // Fit the real Vimeo aspect ratio to hero height; horizontal gaps/cropping are allowed.
  function initHeroVideoCover(facade, iframe) {
    if (!facade.closest('.hp-hero') || iframe.src.indexOf('https://player.vimeo.com/') !== 0) return;
    var videoWidth = 0;
    var videoHeight = 0;
    var ratio = 16 / 9; // Explicit provisional size until Vimeo reports metadata.
    var timer;
    function resize() {
      var height = facade.clientHeight;
      facade.style.setProperty('--hero-video-cover-width', height * ratio + 'px');
      facade.style.setProperty('--hero-video-cover-height', height + 'px');
    }
    function requestDimensions() {
      ['getVideoWidth', 'getVideoHeight'].forEach(function (method) {
        iframe.contentWindow.postMessage(JSON.stringify({ method: method }), 'https://player.vimeo.com');
      });
    }
    function onMessage(event) {
      if (event.origin !== 'https://player.vimeo.com' || event.source !== iframe.contentWindow) return;
      var data = event.data;
      if (typeof data === 'string') {
        try { data = JSON.parse(data); } catch (_) { return; }
      }
      if (!data || typeof data !== 'object') return;
      if (data.event === 'ready') requestDimensions();
      if (typeof data.value !== 'number' || !Number.isFinite(data.value) || data.value <= 0) return;
      if (data.method === 'getVideoWidth') videoWidth = data.value;
      if (data.method === 'getVideoHeight') videoHeight = data.value;
      if (videoWidth && videoHeight) {
        ratio = videoWidth / videoHeight;
        facade.dataset.videoCoverRatio = String(ratio);
        delete facade.dataset.videoCoverError;
        window.clearTimeout(timer);
        resize();
      }
    }
    window.addEventListener('message', onMessage);
    iframe.addEventListener('load', requestDimensions);
    window.addEventListener('resize', resize);
    var observer = 'ResizeObserver' in window ? new window.ResizeObserver(resize) : null;
    if (observer) observer.observe(facade);
    resize();
    if (iframe.contentWindow) requestDimensions();
    timer = window.setTimeout(function () {
      if (videoWidth && videoHeight) return;
      facade.dataset.videoCoverError = 'metadata-unavailable';
      console.warn('Hero video: Vimeo dimensions unavailable; provisional 16:9 height-fit remains active.');
    }, 15000);
    return function () {
      window.clearTimeout(timer);
      window.removeEventListener('message', onMessage);
      window.removeEventListener('resize', resize);
      iframe.removeEventListener('load', requestDimensions);
      if (observer) observer.disconnect();
    };
  }

  function initHeroVideoFacades() {
    var facades = Array.prototype.slice.call(document.querySelectorAll('[data-hero-video-facade]'));
    if (!facades.length) return;
    var coverCleanup = new WeakMap();

    function status(facade, message) {
      var node = facade.querySelector('.theme-hero-video__status');
      if (node) node.textContent = message || '';
    }

    // The window width decides which of the three embedded videos plays; a hero video
    // served as a plain file lets the browser choose through <source media="...">.
    // The numbers match the
    // theme breakpoints in theme.css and critical-above-fold.css - a media query cannot be
    // shared with JavaScript without a build step, so they are kept equal by a test.
    var HERO_VIDEO_MOBILE_MAX = 700;
    var HERO_VIDEO_TABLET_MAX = 1050;

    // Each width has its own source and nothing is inherited: an empty attribute means
    // no video at that width, so the facade stays a poster there.
    function pickEmbedUrl(facade) {
      var desktop = facade.getAttribute('data-embed-url') || '';
      if (!window.matchMedia) return desktop;
      if (window.matchMedia('(max-width: ' + HERO_VIDEO_MOBILE_MAX + 'px)').matches) {
        return facade.getAttribute('data-embed-url-mobile') || '';
      }
      if (window.matchMedia('(max-width: ' + HERO_VIDEO_TABLET_MAX + 'px)').matches) {
        return facade.getAttribute('data-embed-url-tablet') || '';
      }
      return desktop;
    }

    // Hero video autoplay is intentionally exempt from marketing cookie
    // consent - the client wants the background video playing immediately
    // and permanently for every visitor, gated or not.
    function play(facade) {
      var embedUrl = pickEmbedUrl(facade);
      var previous = facade.querySelector('iframe');
      if (embedUrl === '') {
        // No video at this width: drop a player left over from another width.
        if (previous) {
          if (coverCleanup.has(facade)) coverCleanup.get(facade)();
          previous.remove();
          facade.classList.remove('is-playing');
        }
        var idle = facade.querySelector('.theme-hero-video__play');
        if (idle) idle.hidden = true;
        status(facade, '');
        return;
      }
      if (previous && previous.getAttribute('src') === embedUrl) return;
      if (!/^https:\/\/(?:www\.youtube(?:-nocookie)?\.com\/embed\/|player\.vimeo\.com\/video\/)/i.test(embedUrl)) {
        status(facade, 'Video nelze načíst: neplatná adresa přehrávače.');
        return;
      }

      if (coverCleanup.has(facade)) coverCleanup.get(facade)();
      if (previous) previous.remove();
      delete facade.dataset.videoCoverRatio;
      delete facade.dataset.videoCoverError;
      var iframe = document.createElement('iframe');
      iframe.src = embedUrl;
      iframe.title = 'Úvodní video';
      iframe.allow = 'autoplay; fullscreen; picture-in-picture';
      iframe.loading = 'eager';
      iframe.tabIndex = -1;
      facade.appendChild(iframe);
      coverCleanup.set(facade, initHeroVideoCover(facade, iframe) || function () {});
      facade.dataset.videoLoaded = '1';
      facade.classList.add('is-playing');
      facade.setAttribute('aria-hidden', 'true');
      var button = facade.querySelector('.theme-hero-video__play');
      if (button) button.remove();
      status(facade, '');
    }

    // The player used to be injected during the initial render, which pulled ~9 MB of
    // Vimeo into the critical path. Deferring it until after paint was measured and made
    // things worse (16 MB, because an idle page lets the player buffer freely), so the
    // hero now stays a poster until somebody asks for the video.
    //
    // Autoplay can be re-enabled per facade with data-hero-video-autoplay, which then
    // waits for paint and for the hero to be near the viewport.
    function scheduleAutoplay(facade) {
      if (facade.getAttribute('data-hero-video-autoplay') === null) return;

      var started = false;
      function start() { if (!started) { started = true; play(facade); } }
      function whenIdle() {
        if ('requestIdleCallback' in window) window.requestIdleCallback(start, { timeout: 2500 });
        else window.setTimeout(start, 900);
      }
      function afterPaint() {
        if (!('IntersectionObserver' in window)) { whenIdle(); return; }
        var observer = new IntersectionObserver(function (entries) {
          for (var i = 0; i < entries.length; i++) {
            if (entries[i].isIntersecting) { observer.disconnect(); whenIdle(); return; }
          }
        }, { rootMargin: '200px' });
        observer.observe(facade);
      }
      if (document.readyState === 'complete') afterPaint();
      else window.addEventListener('load', afterPaint, { once: true });
    }

    facades.forEach(function(facade) {
      var button = facade.querySelector('.theme-hero-video__play');
      if (button) button.addEventListener('click', function(){ play(facade); });
      if (button && pickEmbedUrl(facade) === '') button.hidden = true;
      scheduleAutoplay(facade);
      if (window.matchMedia) {
        [HERO_VIDEO_MOBILE_MAX, HERO_VIDEO_TABLET_MAX].forEach(function (max) {
          var query = window.matchMedia('(max-width: ' + max + 'px)');
          var change = function () {
            if (facade.dataset.videoLoaded === '1') { play(facade); return; }
            var idleButton = facade.querySelector('.theme-hero-video__play');
            var available = pickEmbedUrl(facade) !== '';
            if (idleButton) idleButton.hidden = !available;
            if (available && facade.getAttribute('data-hero-video-autoplay') !== null) play(facade);
          };
          if (query.addEventListener) query.addEventListener('change', change);
          else query.addListener(change);
        });
      }
    });
  }

  // A plain <video> picks its <source media> once at load; reload it when the width crosses a
  // breakpoint so "empty = no video" and the per-width source also hold after a resize.
  function initNativeHeroVideos() {
    var videos = Array.prototype.slice.call(document.querySelectorAll('video.theme-hero-video--native'));
    if (!videos.length) return;
    // With preload=metadata and a file whose bitrate is above the connection's speed, the browser
    // never decides it has "enough data" and the autoplay attribute never fires. The video is
    // muted, so starting it by hand is allowed; do it as soon as there is something to show.
    videos.forEach(function (video) {
      var kick = function () {
        if (!video.autoplay || !video.paused || video.readyState < 3 || video.offsetParent === null) return;
        var started = video.play && video.play();
        if (started && started.catch) started.catch(function () {});
      };
      ['canplay', 'loadeddata'].forEach(function (name) { video.addEventListener(name, kick); });
      kick();
    });
    if (!window.matchMedia) return;
    [700, 1050].forEach(function (max) {
      var query = window.matchMedia('(max-width: ' + max + 'px)');
      var change = function () { videos.forEach(function (video) { if (typeof video.load === 'function') video.load(); }); };
      if (query.addEventListener) query.addEventListener('change', change);
      else query.addListener(change);
    });
  }

  initNativeHeroVideos();
  initActiveNav();
  initPricingModal();
  initEditorialGoldEnhancements();
  initHeroVideoFacades();
  initHeroParallax();
  syncAdminBarOffset();
  window.addEventListener('resize', syncAdminBarOffset);
  window.addEventListener('load', syncAdminBarOffset);
})();
