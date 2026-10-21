(function () {
  if (!document.body.classList.contains('theme-golden-bash')) return;
  if (document.body.dataset.goldenBashReady === '1') return;
  document.body.dataset.goldenBashReady = '1';

  var systemSymbols = {
    favorite: '♥', movie: '▷', diamond: '◇', celebration: '✦', business_center: '▣', flight: '↑',
    calendar_month: '□', play_circle: '▶', photo_camera: '◎', group: '◉', star: '★', event: '□',
    assignment: '≡', description: '≡', receipt_long: '≡', verified_user: '✓', sell: '◇', schedule: '◷',
    event_available: '✓', call: '☎', mail: '@', location_on: '⌖', video_library: '▶', chat: '●'
  };
  var renderSystemSymbol = function (node, key) {
    if (!node) return;
    key = (key || node.textContent || '').trim();
    node.textContent = systemSymbols[key] || '✦';
    node.classList.add('gb-symbol-fallback');
    node.setAttribute('aria-hidden', 'true');
  };
  var serviceIconSvgs = [
    '<svg viewBox="0 0 32 32" role="img" aria-label="Fotoaparát a videokamera"><rect x="2.5" y="9" width="17" height="14" rx="2"/><path d="M7 9l2-3h5l2 3M19.5 13l9-4v14l-9-4"/><circle cx="11" cy="16" r="4"/></svg>',
    '<svg viewBox="0 0 32 32" role="img" aria-label="Filmový klip"><rect x="3" y="7" width="26" height="18" rx="2"/><path d="M3 12h26M8 7l4 5M16 7l4 5M24 7l4 5"/><path d="M13 16l7 4-7 4z"/></svg>',
    '<svg viewBox="0 0 32 32" role="img" aria-label="Zásnubní prsten"><ellipse cx="16" cy="20" rx="8" ry="8"/><path d="M11 10l5-6 5 6-5 4zM11 10h10M16 4v10"/></svg>',
    '<svg viewBox="0 0 32 32" role="img" aria-label="Oslava"><path d="M7 27l5-17 11 11zM10 18l5 5M13 12l7 7"/><path d="M19 7c0-2 2-3 4-2M24 13c2 0 4-1 4-3M14 5l1-3M26 20l3 1"/></svg>',
    '<svg viewBox="0 0 32 32" role="img" aria-label="Firemní natáčení"><rect x="3" y="9" width="18" height="14" rx="2"/><path d="M21 13l8-4v14l-8-4M7 9l2-4h6l2 4"/><circle cx="12" cy="16" r="3.5"/></svg>',
    '<svg viewBox="0 0 32 32" role="img" aria-label="Dron"><path d="M10 14h12l-2 7h-8zM16 14V9M9 10l7 4 7-4M6 10h6M20 10h6"/><ellipse cx="6" cy="8" rx="4" ry="2"/><ellipse cx="26" cy="8" rx="4" ry="2"/><path d="M13 21l-2 4M19 21l2 4M9 25h4M19 25h4"/></svg>'
  ];
  var preserveGoogleMaterialSymbols = document.querySelector('.gb-main>.kt-hero, .gb-main>.gb-about-hero') !== null;
  document.querySelectorAll('.material-symbols-outlined').forEach(function (node) {
    if (!preserveGoogleMaterialSymbols) renderSystemSymbol(node);
  });

  var welcomeLayer = document.querySelector('.gb-welcome-layer');
  var isHomepage = document.body.dataset.cmsPageKey === 'homepage';
  var skipWelcome = window.matchMedia('(max-width: 700px)').matches;
  if (welcomeLayer && (!isHomepage || skipWelcome)) {
    welcomeLayer.remove();
  } else if (welcomeLayer) {
    var reduceWelcomeMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var welcomeProgress = welcomeLayer.querySelector('[data-welcome-progress]');
    var welcomeStartedAt = performance.now();
    var welcomeDuration = reduceWelcomeMotion ? 100 : 375;
    var welcomePageReady = document.readyState === 'complete';
    var welcomeMaximumWait = reduceWelcomeMotion ? 100 : 450;
    var welcomeTimeoutReported = false;
    var welcomeFinishing = false;
    var welcomeFinishStartedAt = 0;
    var welcomeFinishFrom = 0;
    var welcomeValue = 0;
    var finishWelcome = function () {
      welcomeLayer.classList.add('is-leaving');
      window.setTimeout(function () {
        welcomeLayer.remove();
      }, reduceWelcomeMotion ? 20 : 130);
    };
    var renderWelcomeProgress = function (now) {
      var elapsed = now - welcomeStartedAt;
      var loadingRatio = Math.min(1, elapsed / welcomeDuration);
      var loadingValue = 94 * (1 - Math.pow(1 - loadingRatio, 3));
      var maximumWaitReached = elapsed >= welcomeMaximumWait;
      if (maximumWaitReached && !welcomePageReady && !welcomeTimeoutReported) {
        welcomeTimeoutReported = true;
        welcomeLayer.dataset.loadTimeout = 'true';
        console.warn('Golden Bash loader: window.load exceeded 450 ms; completing the visible transition.');
      }
      if (!welcomeFinishing && (welcomePageReady || maximumWaitReached) && loadingRatio >= 1) {
        welcomeFinishing = true;
        welcomeFinishStartedAt = now;
        welcomeFinishFrom = loadingValue;
      }
      if (welcomeFinishing) {
        var finishRatio = Math.min(1, (now - welcomeFinishStartedAt) / (reduceWelcomeMotion ? 1 : 90));
        welcomeValue = welcomeFinishFrom + ((100 - welcomeFinishFrom) * (1 - Math.pow(1 - finishRatio, 3)));
      } else {
        welcomeValue = loadingValue;
      }
      var value = Math.min(100, Math.round(welcomeValue));
      welcomeLayer.style.setProperty('--gb-load-progress', String(value));
      if (welcomeProgress) welcomeProgress.textContent = value + '%';
      if (value < 100) {
        window.requestAnimationFrame(renderWelcomeProgress);
      } else {
        window.setTimeout(finishWelcome, reduceWelcomeMotion ? 0 : 40);
      }
    };
    if (!welcomePageReady) {
      window.addEventListener('load', function () {
        welcomePageReady = true;
      }, { once: true });
    }
    window.requestAnimationFrame(renderWelcomeProgress);
  }

  var menu = document.querySelector('.gb-menu');
  var nav = document.querySelector('.gb-nav');
  if (menu && nav) {
    nav.querySelectorAll('a[href="#faq"]').forEach(function (link) {
      link.setAttribute('href', '/faq');
    });
    var setMenuOpen = function (open) {
      document.body.classList.toggle('gb-nav-open', open);
      menu.setAttribute('aria-expanded', open ? 'true' : 'false');
      menu.setAttribute('aria-label', open ? 'Zavřít menu' : 'Otevřít menu');
    };
    menu.addEventListener('click', function () {
      setMenuOpen(!document.body.classList.contains('gb-nav-open'));
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && document.body.classList.contains('gb-nav-open')) {
        setMenuOpen(false);
        menu.focus();
      }
    });
    nav.addEventListener('click', function (event) {
      if (event.target.closest('a')) setMenuOpen(false);
    });
    var headerAction = document.querySelector('.gb-header-cta');
    if (headerAction && !nav.querySelector('.gb-mobile-contact')) {
      var mobileAction = headerAction.cloneNode(true);
      mobileAction.className = 'gb-mobile-contact';
      nav.appendChild(mobileAction);
    }
  }

  var mobileHeaderQuery = window.matchMedia('(max-width: 700px)');
  var mobileHeaderTicking = false;
  var updateMobileHeader = function () {
    document.body.classList.toggle('gb-mobile-header-compact', mobileHeaderQuery.matches && window.scrollY > 8);
    mobileHeaderTicking = false;
  };
  window.addEventListener('scroll', function () {
    if (mobileHeaderTicking) return;
    mobileHeaderTicking = true;
    window.requestAnimationFrame(updateMobileHeader);
  }, { passive: true });
  mobileHeaderQuery.addEventListener('change', updateMobileHeader);
  window.requestAnimationFrame(updateMobileHeader);

  var scrollTopButton = document.createElement('button');
  scrollTopButton.className = 'gb-scroll-top';
  scrollTopButton.type = 'button';
  scrollTopButton.setAttribute('aria-label', 'Zpět nahoru');
  scrollTopButton.setAttribute('title', 'Zpět nahoru');
  scrollTopButton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 14 6-6 6 6"/></svg>';
  document.body.appendChild(scrollTopButton);
  var scrollTopTicking = false;
  var updateScrollTopButton = function () {
    scrollTopButton.classList.toggle('is-visible', window.scrollY > 320);
    scrollTopTicking = false;
  };
  window.addEventListener('scroll', function () {
    if (scrollTopTicking) return;
    scrollTopTicking = true;
    window.requestAnimationFrame(updateScrollTopButton);
  }, { passive: true });
  scrollTopButton.addEventListener('click', function () {
    var reduceScrollMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: reduceScrollMotion ? 'auto' : 'smooth' });
  });
  window.requestAnimationFrame(updateScrollTopButton);

  var packageQueryKey = 'balicek';
  var cleanPackageValue = function (value, maximumLength) {
    return String(value || '').replace(/\s+/g, ' ').trim().slice(0, maximumLength);
  };
  var packageFromCard = function (card) {
    if (!card) return null;
    var id = cleanPackageValue(card.dataset.packageId, 64);
    var title = card.querySelector('.hp-cenik-card-title, .ckp-title');
    var price = card.querySelector('.hp-cenik-card-price, .ckp-price');
    var name = cleanPackageValue(title && title.textContent, 120);
    if (!id || !/^[a-z0-9-]+$/.test(id) || !name) return null;
    return {id: id, name: name, price: cleanPackageValue(price && price.textContent, 80)};
  };
  var packageContactUrl = function (selectedPackage) {
    var params = new URLSearchParams();
    params.set(packageQueryKey, selectedPackage.id);
    return '/kontakt?' + params.toString() + '#kontakt-form';
  };
  var revealSelectedPackage = function (form, selectedPackage, changeHref) {
    if (!form || !selectedPackage) return null;
    var existing = form.querySelector('.gb-selected-package');
    if (existing) existing.remove();
    var field = document.createElement('div');
    field.className = 'gb-selected-package';
    field.tabIndex = -1;
    field.setAttribute('role', 'status');
    field.setAttribute('aria-live', 'polite');

    var label = document.createElement('span');
    label.className = 'gb-selected-package__label';
    label.textContent = 'Vybran\u00fd bal\u00ed\u010dek';
    var value = document.createElement('strong');
    value.className = 'gb-selected-package__value';
    value.textContent = selectedPackage.name;
    if (selectedPackage.price) {
      var price = document.createElement('small');
      price.textContent = selectedPackage.price;
      value.appendChild(price);
    }
    var change = document.createElement('a');
    change.className = 'gb-selected-package__change';
    change.href = changeHref;
    change.textContent = 'Zm\u011bnit';
    field.append(label, value, change);

    var packageInput = document.createElement('input');
    packageInput.type = 'hidden';
    packageInput.name = 'selected_package_id';
    packageInput.value = selectedPackage.id;
    field.append(packageInput);

    var grid = form.querySelector('.kt-form-grid');
    var message = form.querySelector('textarea[name="message"]');
    if (grid) {
      var messageField = message && message.closest('div');
      grid.insertBefore(field, messageField || null);
    } else {
      var messageLabel = message && message.previousElementSibling;
      form.insertBefore(field, messageLabel || message || null);
    }
    return field;
  };
  var markSelectedPackageCard = function (selectedCard) {
    var scope = selectedCard && selectedCard.closest('#cenik, .gb-cenik-packages');
    if (!scope) return;
    scope.querySelectorAll('.hp-cenik-card').forEach(function (card) {
      var selected = card === selectedCard;
      card.classList.toggle('is-selected', selected);
      card.setAttribute('aria-selected', selected ? 'true' : 'false');
      var button = card.querySelector('.gb-price-button');
      if (button) button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
  };
  var contactRouteForm = document.querySelector('.kt-form-block#kontakt-form form');
  var serverPackageField = document.querySelector('.kt-form-block#kontakt-form > .gb-selected-package');
  if (contactRouteForm && serverPackageField) {
    var contactGrid = contactRouteForm.querySelector('.kt-form-grid');
    var contactMessage = contactRouteForm.querySelector('textarea[name="message"]');
    if (contactGrid) {
      contactGrid.insertBefore(serverPackageField, contactMessage && contactMessage.closest('div'));
    } else {
      contactRouteForm.prepend(serverPackageField);
    }
  }
  document.addEventListener('click', function (event) {
    var button = event.target.closest('.gb-price-button');
    if (!button) return;
    var card = button.closest('.hp-cenik-card');
    var selectedPackage = packageFromCard(card);
    if (!selectedPackage) {
      console.error('Golden Bash package selection: package title is missing.');
      return;
    }
    var homepagePricing = card.closest('#cenik');
    if (!homepagePricing) {
      event.preventDefault();
      window.location.assign(packageContactUrl(selectedPackage));
      return;
    }
    var homepageContact = document.querySelector('#kontakt');
    var homepageForm = homepageContact && homepageContact.querySelector('.hp-contact-form');
    if (!homepageContact || !homepageForm) {
      console.error('Golden Bash package selection: homepage contact form is missing.');
      return;
    }
    event.preventDefault();
    markSelectedPackageCard(card);
    var selectedField = revealSelectedPackage(homepageForm, selectedPackage, '#cenik');
    homepageContact.scrollIntoView({
      behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
      block: 'start'
    });
    window.setTimeout(function () {
      if (selectedField) selectedField.focus({preventScroll: true});
    }, 450);
  });

  var applyAboutPhotography = function () {
    var aboutHeroImage = document.querySelector('.gb-about-hero .theme-hero-video img');
    var source = aboutHeroImage ? (aboutHeroImage.currentSrc || aboutHeroImage.src) : '';
    if (!source) {
      source = getComputedStyle(document.body).getPropertyValue('--theme-page-hero-image').trim()
        .replace(/^url\((['"]?)/, '')
        .replace(/(['"]?)\)$/, '');
    }
    if (!source) return false;
    document.body.style.setProperty('--gb-about-photo', 'url("' + source.replace(/"/g, '%22') + '")');
    document.querySelectorAll('[data-about-photo],[data-about-story-photo]').forEach(function (panel, index) {
      panel.style.backgroundImage = 'url("' + source.replace(/"/g, '%22') + '")';
      panel.style.backgroundPosition = (index % 2 === 0 ? '35%' : '70%') + ' center';
    });
    return true;
  };

  var home = document.querySelector('.hp-hero') ? document.querySelector('.gb-main') : null;
  if (!home) {
    var innerHero = document.querySelector('.pf-hero, .bl-hero, .fq-hero, .kt-hero, .page-hero, .pf-detail-hero');
    if (innerHero) innerHero.classList.add('gb-unified-hero');
    if (!applyAboutPhotography()) {
      document.addEventListener('DOMContentLoaded', applyAboutPhotography, { once: true });
      window.addEventListener('load', applyAboutPhotography, { once: true });
    }
    var serviceDetailHero = document.querySelector('.svc-hero');
    var serviceLeadImage = serviceDetailHero && serviceDetailHero.previousElementSibling && serviceDetailHero.previousElementSibling.matches('.hero-bg')
      ? serviceDetailHero.previousElementSibling
      : null;
    if (serviceDetailHero) {
      serviceDetailHero.classList.add('gb-service-detail-hero');
      if (serviceLeadImage) {
        serviceDetailHero.insertBefore(serviceLeadImage, serviceDetailHero.firstChild);
        var duplicateServiceImage = document.querySelector('.svc-detail__img');
        if (duplicateServiceImage && duplicateServiceImage.src === serviceLeadImage.src) {
          duplicateServiceImage.remove();
        }
      }
      serviceDetailHero.querySelectorAll('.btn, .btn-primary, .btn-secondary').forEach(function (button) {
        button.classList.add('gb-button');
      });
      var serviceDetailCta = document.querySelector('.gb-main > .section-dark');
      if (serviceDetailCta) {
        serviceDetailCta.classList.add('gb-route-cta', 'gb-service-detail-cta');
        serviceDetailCta.querySelectorAll('.btn, .btn-primary, .btn-secondary').forEach(function (button) {
          button.classList.add('gb-button');
        });
      }
    }
    initWorkGallery();
    initPortfolioTabs();
    initCenikAutoScroll();
    return;
  }

  var hero = home.querySelector('.hp-hero');
  if (hero && !document.getElementById('gb-hero-wave-clip')) {
    hero.insertAdjacentHTML('afterbegin', '<svg class="gb-hero-wave-defs" width="0" height="0" aria-hidden="true" focusable="false"><defs><clipPath id="gb-hero-wave-clip" clipPathUnits="objectBoundingBox"><path d="M .27 0 H 1 V 1 H .55 Z"/></clipPath></defs></svg>');
  }
  if (hero) {
    var mobileHeroVideo = hero.querySelector('.theme-hero-video--embed');
    var mobileHeroFrame = mobileHeroVideo && mobileHeroVideo.querySelector('iframe');
    if (mobileHeroVideo && mobileHeroFrame && !mobileHeroVideo.querySelector('.gb-mobile-video-play')) {
      var mobileVideoPlay = document.createElement('button');
      mobileVideoPlay.type = 'button';
      mobileVideoPlay.className = 'gb-mobile-video-play';
      mobileVideoPlay.setAttribute('aria-label', 'Prehrat uvodni video');
      mobileVideoPlay.innerHTML = '<span aria-hidden="true">&#9654;</span>';
      mobileVideoPlay.addEventListener('click', function () {
        mobileHeroVideo.classList.add('is-playing');
        mobileVideoPlay.remove();
      });
      mobileHeroVideo.appendChild(mobileVideoPlay);
    }
    var heroActions = hero.querySelector('.hero-cta-wrap');
    if (heroActions && !heroActions.querySelector('.gb-hero-work')) {
      var workAction = document.createElement('a');
      workAction.className = 'btn-secondary gb-hero-work';
      workAction.href = '/nase-prace';
      workAction.textContent = 'Ukázka práce';
      heroActions.appendChild(workAction);
    }
    if (!hero.querySelector('.gb-hero-benefits')) {
      var benefits = document.createElement('div');
      benefits.className = 'gb-hero-benefits';
      benefits.innerHTML = '<span>Diskrétní přístup</span><span>Moderní technika</span><span>Vyprávíme emoce</span><span>Foto i video v jednom týmu</span>';
      (hero.querySelector('.container') || hero).appendChild(benefits);
    }
  }
  home.querySelectorAll('.btn, .btn-primary, .btn-secondary, .btn-ghost').forEach(function (button) {
    button.classList.add('gb-button');
  });

  var accentTitleTail = function (title, wordCount) {
    if (!title) return;
    var words = title.textContent.trim().split(/\s+/);
    if (words.length <= wordCount) return;
    title.textContent = words.slice(0, -wordCount).join(' ') + ' ';
    var emphasis = document.createElement('em');
    emphasis.textContent = words.slice(-wordCount).join(' ');
    title.appendChild(emphasis);
  };

  var services = document.querySelector('#sluzby');
  function initHomepagePortfolioLoadMore(section) {
    var grid = section.querySelector('.hp-portfolio-placeholder .pt-grid');
    if (!grid) return;
    var cards = Array.prototype.slice.call(grid.querySelectorAll(':scope > .pt-tile'));
    var batchSize = 5;
    if (cards.length <= batchSize) return;

    grid.id = grid.id || 'homepage-portfolio-grid';
    cards.slice(batchSize).forEach(function (card) {
      card.classList.add('gb-portfolio-is-hidden');
      card.setAttribute('aria-hidden', 'true');
      card.tabIndex = -1;
    });

    var controls = document.createElement('div');
    controls.className = 'gb-portfolio-more';
    var button = document.createElement('button');
    button.className = 'gb-portfolio-more__button';
    button.type = 'button';
    button.setAttribute('aria-controls', grid.id);
    button.innerHTML = '<span>Další</span><i aria-hidden="true">↓</i>';
    var status = document.createElement('span');
    status.className = 'gb-portfolio-more__status sr-only';
    status.setAttribute('aria-live', 'polite');
    controls.appendChild(button);
    controls.appendChild(status);
    grid.after(controls);
    section.classList.add('gb-portfolio-load-more-ready');

    button.addEventListener('click', function () {
      var hiddenCards = cards.filter(function (card) { return card.classList.contains('gb-portfolio-is-hidden'); });
      var revealed = hiddenCards.slice(0, batchSize);
      if (!revealed.length) return;
      var oldHeight = grid.getBoundingClientRect().height;
      grid.style.setProperty('--gb-portfolio-grid-height', oldHeight + 'px');
      section.classList.add('gb-portfolio-load-more-active');
      revealed.forEach(function (card) {
        card.classList.remove('gb-portfolio-is-hidden');
        card.classList.add('gb-portfolio-is-entering');
        card.removeAttribute('aria-hidden');
        card.tabIndex = 0;
      });
      var nextHeight = grid.scrollHeight;
      window.requestAnimationFrame(function () {
        grid.style.setProperty('--gb-portfolio-grid-height', nextHeight + 'px');
        revealed.forEach(function (card) { card.classList.remove('gb-portfolio-is-entering'); });
      });

      var remaining = hiddenCards.length - revealed.length;
      status.textContent = 'Zobrazeno dalších ' + revealed.length + ' galerií. Zbývá ' + remaining + '.';
      if (remaining === 0) {
        window.setTimeout(function () {
          controls.hidden = true;
          revealed[0].focus({ preventScroll: true });
        }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 380);
      }
    });
  }

  var portfolio = document.querySelector('#nase-prace');
  var blog = document.querySelector('#blog');
  var pricing = document.querySelector('#cenik');
  var faq = document.querySelector('#faq');
  var reviews = document.querySelector('#hodnoceni');
  var process = document.querySelector('#proces');
  var contact = document.querySelector('#kontakt');

  if (services) {
    services.classList.add('gb-services', 'gb-services-profile');
    Array.prototype.slice.call(services.querySelectorAll('.hp-svc-card, .premium-service-card')).forEach(function (card, index) {
      if (index < 6) {
        card.style.setProperty('--gb-service-index', '"' + String(index + 1).padStart(2, '0') + '"');
        var icon = document.createElement('span');
        icon.className = 'gb-service-icon';
        icon.innerHTML = serviceIconSvgs[index];
        card.prepend(icon);
      }
      var sourceLink = card.querySelector('.hp-svc-card__btn, .t-more, a[href]');
      var cardTitle = card.querySelector('.hp-svc-card__title, h3');
      if (sourceLink && cardTitle && !card.querySelector('.gb-service-card-link')) {
        var cardLink = document.createElement('a');
        cardLink.className = 'gb-service-card-link';
        cardLink.href = sourceLink.href;
        cardLink.setAttribute('aria-label', cardTitle.textContent.trim());
        card.appendChild(cardLink);
      }
    });
    var servicesContainer = services.querySelector(':scope > .container');
    var servicesLabel = services.querySelector('.section-label');
    var servicesTitle = services.querySelector('.section-title');
    var servicesIntro = services.querySelector('.section-intro');
    var servicesGrid = services.querySelector('.hp-services-grid');
    var servicesCta = services.querySelector('.hp-section-cta');
    var aboutVisualSource = document.querySelector('.jb-about-visual__source[data-src]');
    if (servicesContainer && servicesLabel && servicesTitle && servicesIntro && servicesGrid && servicesCta && aboutVisualSource) {
      servicesLabel.textContent = 'Poznejte nás';
      servicesTitle.textContent = 'O nás';
      servicesIntro.textContent = 'Jsme dvojice fotografů a filmařů,\u00a0které spojuje cit pro přirozené světlo,\u00a0opravdové emoce a poctivou práci.';
      var servicesVisual = document.createElement('figure');
      servicesVisual.className = 'gb-services-visual';
      var servicesImage = aboutVisualSource.tagName === 'IMG'
        ? aboutVisualSource
        : document.createElement('img');
      if (!servicesImage.src) servicesImage.src = aboutVisualSource.dataset.src;
      servicesImage.hidden = false;
      servicesImage.removeAttribute('hidden');
      servicesImage.className = 'gb-services-visual__image';
      servicesImage.alt = 'RB Videos & Photos při práci';
      servicesVisual.appendChild(servicesImage);
      var servicesCopy = document.createElement('div');
      servicesCopy.className = 'gb-services-copy';
      [servicesLabel, servicesTitle, servicesIntro, servicesGrid, servicesCta].forEach(function (element) {
        servicesCopy.appendChild(element);
      });
      var servicesLink = servicesCta.querySelector('a');
      if (servicesLink) servicesLink.textContent = 'Více info o nás  →';
      servicesContainer.replaceChildren(servicesVisual, servicesCopy);
      Array.prototype.slice.call(services.querySelectorAll('a')).forEach(function (link) {
        link.setAttribute('href', '/#kontakt');
      });
      if (servicesLink) servicesLink.setAttribute('href', '/o-nas');
    } else {
      services.dataset.compositionError = 'missing-required-db-source';
    }
  }
  if (portfolio) {
    portfolio.classList.add('gb-portfolio', 'gb-portfolio-mosaic');
    var portfolioTitle = portfolio.querySelector('.section-title');
    var portfolioIntro = portfolio.querySelector('.section-intro');
    if (portfolioIntro) portfolioIntro.textContent = 'Každý okamžik má svůj příběh. Prohlédněte si výběr projektů,\u00a0kde světlo,\u00a0emoce a detail tvoří nezapomenutelné vzpomínky.';
    var portfolioAction = portfolio.querySelector('.hp-section-cta a');
    if (portfolioAction) portfolioAction.textContent = 'Zobrazit všechny práce  →';
    if (portfolioTitle) portfolioTitle.innerHTML = 'Příběhy, <em>které inspirují</em>';
    initHomepagePortfolioLoadMore(portfolio);
  }
  if (pricing) {
    pricing.classList.add('gb-pricing');
    var pricingTitle = pricing.querySelector('.section-title');
    var pricingIntro = pricing.querySelector('.section-intro');
    if (pricingTitle) pricingTitle.innerHTML = 'Vyberte si balíček <em>na míru</em>';
    if (pricingIntro) pricingIntro.textContent = 'Každý balíček přizpůsobíme individuálně podle vašeho dne a potřeb.';
    pricing.querySelectorAll('.hp-cenik-card').forEach(function (card) {
      var cardBody = card.querySelector('.hp-cenik-card-body') || card;
      var title = cardBody.querySelector('.hp-cenik-card-title');
      if (title && !cardBody.querySelector('.gb-price-type')) {
        var type = document.createElement('span');
        type.className = 'gb-price-type';
        type.textContent = 'Foto & video';
        title.after(type);
      }
      if (!cardBody.querySelector('.gb-price-button')) {
        var action = document.createElement('a');
        action.className = 'gb-button gb-price-button';
        action.href = '#kontakt';
        action.textContent = 'Vybrat balíček';
        cardBody.appendChild(action);
      }
      if (card.classList.contains('hp-cenik-card--featured') && !card.querySelector('.hp-cenik-card-badge')) {
        var badge = document.createElement('span');
        badge.className = 'hp-cenik-card-badge';
        badge.textContent = 'Nejoblíbenější';
        card.prepend(badge);
      }
    });
    var pricingDetailAction = pricing.querySelector('.hp-section-cta a');
    if (pricingDetailAction) pricingDetailAction.textContent = 'Zobrazit podrobný ceník';
  }
  if (reviews) {
    reviews.classList.add('gb-reviews');
    var reviewsTitle = reviews.querySelector('.section-title');
    if (reviewsTitle) {
      reviewsTitle.textContent = 'D\u011bkujeme za kr\u00e1sn\u00e9 vzpom\u00ednky';
      accentTitleTail(reviewsTitle, 2);
    }
    var reviewsGrid = reviews.querySelector('.hp-reviews-grid');
    if (reviewsGrid && reviewsGrid.parentElement) reviewsGrid.parentElement.classList.add('gb-review-list');
  }
  if (blog) {
    blog.classList.add('gb-blog');
    accentTitleTail(blog.querySelector('.section-title'), 1);
  }
  if (contact) contact.classList.add('gb-contact');
  if (process) process.hidden = true;

  if (portfolio && pricing && reviews && blog && contact) {
    portfolio.after(pricing);
    pricing.after(reviews);
    reviews.after(blog);
    blog.after(contact);
  }

  if (faq && contact) {
    var contactContainer = contact.querySelector('.container');
    var faqContainer = faq.querySelector('.container');
    if (contactContainer && faqContainer) {
      var intro = document.createElement('div');
      intro.className = 'gb-contact-intro';
      Array.prototype.slice.call(contactContainer.children).filter(function (child) {
        return child.matches('.section-label, .section-title, .section-intro');
      }).forEach(function (child) {
        intro.appendChild(child);
      });
      contactContainer.prepend(intro);
      var panel = document.createElement('div');
      panel.className = 'gb-contact-faq';
      while (faqContainer.firstChild) panel.appendChild(faqContainer.firstChild);
      contactContainer.appendChild(panel);
      faq.remove();
    }
  }

  if (contact) {
    var contactVisualSource = contact.querySelector('.ew-contact-visual-source');
    var contactIntro = contact.querySelector('.gb-contact-intro');
    var contactGrid = contact.querySelector('.hp-contact-grid');
    if (contactIntro && contactVisualSource) {
      contactIntro.classList.add('gb-contact-visual');
      // The hidden source <img> carries the ORIGINAL file in src (a PNG of 100+ kB) and the light
      // WebP variants in srcset. Use the largest variant up to 900 px, and set the background only
      // when the section is about to come into view: it sits at the bottom of the page, and
      // loading it at once competed with the hero for bandwidth.
      var pickContactImage = function (img) {
        var best = null;
        (img.getAttribute('srcset') || '').split(',').forEach(function (part) {
          var bits = part.trim().split(/\s+/);
          var width = parseInt(bits[1], 10);
          if (bits[0] && width && width <= 900 && (!best || width > best.width)) best = { url: bits[0], width: width };
        });
        return best ? best.url : img.getAttribute('src');
      };
      var applyContactImage = function () {
        contactIntro.style.setProperty('--gb-contact-image', 'url("' + pickContactImage(contactVisualSource) + '")');
      };
      if ('IntersectionObserver' in window) {
        var contactObserver = new IntersectionObserver(function (entries) {
          if (entries.some(function (entry) { return entry.isIntersecting; })) {
            contactObserver.disconnect();
            applyContactImage();
          }
        }, { rootMargin: '600px 0px' });
        contactObserver.observe(contactIntro);
      } else {
        applyContactImage();
      }
    }
    if (contactGrid) {
      contactGrid.classList.add('gb-contact-grid');
      var contactBoxes = contactGrid.querySelectorAll(':scope > .hp-contact-box');
      if (contactBoxes[0]) contactBoxes[0].classList.add('gb-contact-data');
      if (contactBoxes[1]) contactBoxes[1].classList.add('gb-contact-form');
      if (contactBoxes[1] && !contact.querySelector('.gb-contact-benefits')) {
        var benefits = document.createElement('div');
        benefits.className = 'gb-contact-benefits';
        [
          ['<svg viewBox="0 0 24 24"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2Zm0 14H5.17L4 17.17V4h16v12Z"/></svg>', 'Rychl\u00e1 odpov\u011b\u010f', 'Odpov\u00edme obvykle do 24 hodin'],
          ['<svg viewBox="0 0 24 24"><path d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4Zm0 19.93C8.05 19.71 5 15.66 5 11V6.3l7-3.11 7 3.11V11c0 4.66-3.05 8.71-7 9.93Zm-1.41-6.34L7.5 11.5 6.09 12.91 10.59 17l7.32-7.32-1.41-1.41-5.91 6.32Z"/></svg>', 'Bezpe\u010dn\u011b', 'Va\u0161e \u00fadaje jsou u n\u00e1s v bezpe\u010d\u00ed'],
          ['<svg viewBox="0 0 24 24"><path d="M16.5 3A5.49 5.49 0 0 0 12 5.5 5.49 5.49 0 0 0 7.5 3 5.45 5.45 0 0 0 2 8.5C2 12.28 5.4 15.36 10.55 20L12 21.35 13.45 20C18.6 15.36 22 12.28 22 8.5A5.45 5.45 0 0 0 16.5 3ZM12.1 18.55l-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5A3.41 3.41 0 0 1 7.5 5 3.91 3.91 0 0 1 11.07 7.1h1.87A3.91 3.91 0 0 1 16.5 5 3.41 3.41 0 0 1 20 8.5c0 2.89-3.14 5.74-7.9 10.05Z"/></svg>', 'Osobn\u00ed p\u0159\u00edstup', 'Ke ka\u017ed\u00e9mu projektu p\u0159istupujeme individu\u00e1ln\u011b']
        ].forEach(function (benefit) {
          var item = document.createElement('div');
          item.className = 'gb-contact-benefit';
          item.innerHTML = '<span class="gb-contact-benefit__icon" aria-hidden="true">' + benefit[0] + '</span><div><strong>' + benefit[1] + '</strong><p>' + benefit[2] + '</p></div>';
          benefits.appendChild(item);
        });
        contactBoxes[1].after(benefits);
      }
    }
  }

  document.querySelectorAll('.hp-faq-item').forEach(function (item) {
    var question = item.querySelector('.faq-q, h3, summary');
    if (!question) question = item.querySelector('.hp-faq-question');
    if (question && !question.querySelector('.gb-faq-plus')) {
      var plus = document.createElement('span');
      plus.className = 'gb-faq-plus';
      plus.textContent = '+';
      question.appendChild(plus);
    }
    var answer = item.querySelector('.hp-faq-answer');
    if (question && answer) {
      var answerId = answer.id || ('gb-home-faq-answer-' + Math.random().toString(36).slice(2, 9));
      answer.id = answerId;
      question.setAttribute('role', 'button');
      question.setAttribute('tabindex', '0');
      question.setAttribute('aria-controls', answerId);
      question.setAttribute('aria-expanded', 'false');
      answer.setAttribute('aria-hidden', 'true');
      answer.style.height = '0px';
      answer.addEventListener('transitionend', function (event) {
        if (event.propertyName === 'height' && item.classList.contains('is-open')) {
          answer.style.height = 'auto';
        }
      });
      var toggle = function () {
        var open = !item.classList.contains('is-open');
        if (open) {
          item.classList.add('is-open');
          answer.style.height = '0px';
          window.requestAnimationFrame(function () {
            answer.style.height = answer.scrollHeight + 'px';
          });
        } else {
          answer.style.height = answer.scrollHeight + 'px';
          window.requestAnimationFrame(function () {
            item.classList.remove('is-open');
            answer.style.height = '0px';
          });
        }
        question.setAttribute('aria-expanded', open ? 'true' : 'false');
        answer.setAttribute('aria-hidden', open ? 'false' : 'true');
      };
      question.addEventListener('click', toggle);
      question.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          toggle();
        }
      });
    }
  });

  function initCenikAutoScroll() {
    var section = document.querySelector('.gb-cenik-packages');
    if (!section || window.location.hash) return;
    var featured = section.querySelector('.ckp-card--featured');
    if (!featured) return;
    window.requestAnimationFrame(function () {
      featured.scrollIntoView({
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        block: 'center'
      });
    });
  }

  function initPortfolioTabs() {
    var tabList = document.querySelector('.gb-portfolio-tabs');
    var grid = document.querySelector('[data-portfolio-grid]');
    if (!tabList || !grid) return;
    var buttons = Array.prototype.slice.call(tabList.querySelectorAll('[data-portfolio-filter]'));
    var cards = Array.prototype.slice.call(grid.querySelectorAll(':scope > .pt-card'));
    if (!buttons.length || !cards.length) return;
    var activeFilter = 'all';
    var HIDE_MS = 180;

    var matchesFilter = function (card, filter) {
      return filter === 'all' || card.dataset.contentType === filter;
    };

    var hashForFilter = function (filter) {
      return filter === 'photo' ? 'fotogalerie' : filter === 'video' ? 'videogalerie' : 'nase-prace-grid';
    };

    // Non-matching cards fade out fast and drop out of flow once the
    // transition ends; newly matching cards start from that same hidden
    // state and are immediately released so both directions read as one
    // quick swap instead of a slow crossfade.
    var applyFilter = function (filter, updateHash, focusIndex) {
      if (filter !== activeFilter) {
        activeFilter = filter;
        buttons.forEach(function (button) {
          button.setAttribute('aria-pressed', button.dataset.portfolioFilter === filter ? 'true' : 'false');
        });
        if (updateHash) history.replaceState(null, '', '#' + hashForFilter(filter));
        cards.forEach(function (card) {
          var shouldShow = matchesFilter(card, filter);
          var isHidden = card.classList.contains('is-filtered-out');
          if (shouldShow && isHidden) {
            card.classList.add('is-entering');
            card.classList.remove('is-filtered-out');
            void card.offsetWidth;
            card.classList.remove('is-entering');
          } else if (!shouldShow && !isHidden) {
            card.classList.add('is-filtering-out');
            window.setTimeout(function () {
              card.classList.add('is-filtered-out');
              card.classList.remove('is-filtering-out');
            }, HIDE_MS);
          }
        });
      }
      if (typeof focusIndex === 'number') buttons[focusIndex].focus();
    };

    buttons.forEach(function (button, index) {
      button.addEventListener('click', function () {
        applyFilter(button.dataset.portfolioFilter, true);
      });
      button.addEventListener('keydown', function (event) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' && event.key !== 'Home' && event.key !== 'End') return;
        event.preventDefault();
        var next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length;
        applyFilter(buttons[next].dataset.portfolioFilter, true, next);
      });
    });

    var initialHash = window.location.hash.replace('#', '');
    if (initialHash === 'fotogalerie') applyFilter('photo', false);
    else if (initialHash === 'videogalerie') applyFilter('video', false);
  }

  function initWorkGallery() {
    var workCards = Array.prototype.slice.call(document.querySelectorAll('.pt-tile, .pt-card'));
    if (!workCards.length) return;
    var lightbox = document.createElement('div');
    lightbox.className = 'gb-work-lightbox';
    lightbox.hidden = true;
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-modal', 'true');
    lightbox.setAttribute('aria-labelledby', 'gb-work-gallery-title');
    lightbox.innerHTML =
      '<div class="gb-work-lightbox__dialog">' +
        '<header class="gb-work-lightbox__header">' +
          '<div><h2 id="gb-work-gallery-title"></h2><p class="gb-work-lightbox__date"></p></div>' +
          '<button class="gb-work-lightbox__close" type="button" aria-label="Zav\u0159\u00edt galerii"><span>ZAV\u0158\u00cdT</span><i aria-hidden="true"></i></button>' +
        '</header>' +
        '<div class="gb-work-lightbox__stage">' +
          // The frame keeps the arrows anchored to the picture itself. On phones the
          // caption sits below the picture in flow, so arrows centred on the whole
          // stage would land on the text instead.
          '<div class="gb-work-lightbox__frame">' +
            '<button class="gb-work-lightbox__arrow gb-work-lightbox__arrow--prev" type="button" aria-label="P\u0159edchoz\u00ed fotografie"></button>' +
            '<button class="gb-work-lightbox__image-button" type="button" aria-label="Dal\u0161\u00ed fotografie"><img src="" alt=""></button>' +
            '<div class="gb-work-lightbox__video"><iframe src="" title="" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" loading="lazy" frameborder="0"></iframe></div>' +
            '<button class="gb-work-lightbox__arrow gb-work-lightbox__arrow--next" type="button" aria-label="Dal\u0161\u00ed fotografie"></button>' +
          '</div>' +
          '<div class="gb-work-lightbox__caption"><a href="">V\u00cdCE INFO <span aria-hidden="true">\u2192</span></a></div>' +
        '</div>' +
        '<div class="gb-work-lightbox__thumbs" role="list" aria-label="N\u00e1hledy fotografi\u00ed"></div>' +
      '</div>';
    document.body.appendChild(lightbox);

    var galleryTitle = lightbox.querySelector('h2');
    var galleryDate = lightbox.querySelector('.gb-work-lightbox__date');
    var galleryImage = lightbox.querySelector('.gb-work-lightbox__image-button img');
    var galleryInfo = lightbox.querySelector('.gb-work-lightbox__caption a');
    var galleryThumbs = lightbox.querySelector('.gb-work-lightbox__thumbs');
    var galleryClose = lightbox.querySelector('.gb-work-lightbox__close');
    var galleryPrevious = lightbox.querySelector('.gb-work-lightbox__arrow--prev');
    var galleryNext = lightbox.querySelector('.gb-work-lightbox__arrow--next');
    var galleryImageButton = lightbox.querySelector('.gb-work-lightbox__image-button');
    var galleryVideoFrame = lightbox.querySelector('.gb-work-lightbox__video iframe');
    var galleryItems = [];
    var galleryIndex = 0;
    var galleryTrigger = null;

    var showGalleryItem = function (nextIndex) {
      if (!galleryItems.length) return;
      galleryIndex = (nextIndex + galleryItems.length) % galleryItems.length;
      var item = galleryItems[galleryIndex];
      galleryImage.src = item.src;
      galleryImage.alt = item.alt;
      Array.prototype.forEach.call(galleryThumbs.children, function (thumb, index) {
        var active = index === galleryIndex;
        thumb.classList.toggle('is-active', active);
        thumb.setAttribute('aria-current', active ? 'true' : 'false');
        if (active) thumb.scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'center'});
      });
    };

    var closeGallery = function () {
      if (lightbox.hidden) return;
      lightbox.classList.remove('is-open');
      document.body.classList.remove('gb-gallery-open');
      window.setTimeout(function () {
        lightbox.hidden = true;
        galleryImage.removeAttribute('src');
        galleryVideoFrame.removeAttribute('src'); // stop playback, not just hide it
        lightbox.classList.remove('is-video');
      }, 280);
      if (galleryTrigger) galleryTrigger.focus();
    };

    var openVideo = function (card, embedUrl) {
      galleryItems = [];
      galleryTrigger = card;
      lightbox.classList.add('is-video');
      galleryTitle.textContent = (card.querySelector('.pt-caption, .pt-title, .bl-title') || {}).textContent || '';
      galleryDate.textContent = card.dataset.galleryDate || '';
      galleryInfo.href = card.getAttribute('href');
      var separator = embedUrl.indexOf('?') === -1 ? '?' : '&';
      galleryVideoFrame.src = embedUrl + separator + 'autoplay=1&rel=0';
      galleryVideoFrame.title = galleryTitle.textContent;
      lightbox.hidden = false;
      document.body.classList.add('gb-gallery-open');
      window.requestAnimationFrame(function () {
        lightbox.classList.add('is-open');
        galleryClose.focus();
      });
    };

    var openGallery = function (card) {
      var videoEmbed = card.dataset.videoEmbed || '';
      if (videoEmbed) {
        openVideo(card, videoEmbed);
        return;
      }
      lightbox.classList.remove('is-video');
      var images = Array.prototype.slice.call(card.querySelectorAll('.pt-photos img'));
      if (!images.length) {
        var cover = card.querySelector('.pt-thumb img, .bl-thumb img');
        if (cover) images.push(cover);
      }
      galleryItems = images.map(function (image) {
        return {
          src: image.getAttribute('data-full') || image.currentSrc || image.getAttribute('src'),
          thumb: image.currentSrc || image.getAttribute('src'),
          alt: image.getAttribute('alt') || ''
        };
      }).filter(function (item, index, items) {
        return item.src && items.findIndex(function (candidate) { return candidate.src === item.src; }) === index;
      });
      if (!galleryItems.length) return;
      var clickedImage = card.querySelector('.pt-thumb img, .bl-thumb img');
      var clickedImageSrc = clickedImage ? (clickedImage.currentSrc || clickedImage.getAttribute('src')) : '';
      var initialGalleryIndex = galleryItems.findIndex(function (item) {
        return item.src === clickedImageSrc;
      });
      if (initialGalleryIndex < 0) initialGalleryIndex = 0;

      galleryTrigger = card;
      galleryTitle.textContent = (card.querySelector('.pt-caption, .pt-title, .bl-title') || {}).textContent || '';
      galleryDate.textContent = card.dataset.galleryDate || '';
      galleryInfo.href = card.getAttribute('href');
      galleryThumbs.innerHTML = '';
      galleryItems.forEach(function (item, index) {
        var thumb = document.createElement('button');
        thumb.type = 'button';
        thumb.className = 'gb-work-lightbox__thumb';
        thumb.setAttribute('role', 'listitem');
        thumb.setAttribute('aria-label', 'Fotografie ' + (index + 1) + ' z ' + galleryItems.length);
        var thumbImage = document.createElement('img');
        thumbImage.src = item.thumb || item.src;
        thumbImage.alt = '';
        thumb.appendChild(thumbImage);
        thumb.addEventListener('click', function () { showGalleryItem(index); });
        galleryThumbs.appendChild(thumb);
      });
      showGalleryItem(initialGalleryIndex);
      lightbox.hidden = false;
      document.body.classList.add('gb-gallery-open');
      window.requestAnimationFrame(function () {
        lightbox.classList.add('is-open');
        galleryClose.focus();
      });
    };

    workCards.forEach(function (card) {
      card.setAttribute('aria-haspopup', 'dialog');
      card.addEventListener('click', function (event) {
        event.preventDefault();
        openGallery(card);
      });
    });
    galleryPrevious.addEventListener('click', function () { showGalleryItem(galleryIndex - 1); });
    galleryNext.addEventListener('click', function () { showGalleryItem(galleryIndex + 1); });
    galleryImageButton.addEventListener('click', function () { showGalleryItem(galleryIndex + 1); });
    galleryClose.addEventListener('click', closeGallery);
    lightbox.addEventListener('click', function (event) {
      if (event.target === lightbox) closeGallery();
    });
    document.addEventListener('keydown', function (event) {
      if (lightbox.hidden) return;
      if (event.key === 'Escape') closeGallery();
      if (event.key === 'ArrowLeft') showGalleryItem(galleryIndex - 1);
      if (event.key === 'ArrowRight') showGalleryItem(galleryIndex + 1);
      if (event.key === 'Tab') {
        var focusable = Array.prototype.slice.call(lightbox.querySelectorAll('button, a[href]'));
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    });
  }
  initPortfolioTabs();
  initWorkGallery();
  initCenikAutoScroll();
})();
