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

  initActiveNav();
  initPricingModal();
  initEditorialGoldEnhancements();
  syncAdminBarOffset();
  window.addEventListener('resize', syncAdminBarOffset);
  window.addEventListener('load', syncAdminBarOffset);
})();
