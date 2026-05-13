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
    if (qs('.eg-reviews') || qs('.hp-reviews-demo') || !qs('#kontakt')) return;
    var reviews = document.createElement('section');
    reviews.className = 'section eg-reviews';
    reviews.innerHTML =
      '<div class="eg-reviews__title"><p class="section-label">Co o nas rikaji</p><h2 class="section-title">Hodnoceni<br>nasich paru</h2></div>' +
      '<div class="eg-reviews__grid">' +
      '<article><div class="eg-stars">*****</div><p>Kluci jsou naprosti profesionalove. Jsou peclivi a dokazou zachytit skutecne emoce.</p><strong>Jan Jungling</strong><span>Svatba M+H</span></article>' +
      '<article><div class="eg-stars">*****</div><p>Super domluva, mily a profesionalni pristup k foceni. Velka spokojenost.</p><strong>Vaclav Konopka</strong><span>Svatebni foceni</span></article>' +
      '<article><div class="eg-stars">*****</div><p>Moc doporucuji. Vse profesionalne nafocene, krasne barvy a skvela domluva.</p><strong>Jana Dudkova</strong><span>Rodinne foceni</span></article>' +
      '</div>';
    qs('#kontakt').insertAdjacentElement('beforebegin', reviews);
  }

  function enhanceContactBand() {
    var section = qs('#kontakt');
    if (!section || qs('.eg-contact-band', section)) return;
    var infoBox = qs('.hp-contact-box', section);
    var lines = infoBox ? infoBox.innerHTML : '';
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
        if (!child.classList || !child.classList.contains('section-label')) child.style.display = 'none';
      });
      container.appendChild(band);
    }
  }

  createStats();
  enhanceHero();
  createReviews();
  enhanceContactBand();
})();
