(function () {
  'use strict';

  var body = document.body;
  if (!body || !body.classList.contains('theme-bezova-lens-tale')) return;
  if (body.dataset.bltInitialized === 'true') return;
  body.dataset.bltInitialized = 'true';

  var qs = function (selector, root) { return (root || document).querySelector(selector); };
  var qsa = function (selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); };
  var content = qs('.blt-page-content');
  var legacyFooter = content ? qs(':scope > .footer', content) : null;
  var promoteEmptyTitle = function (section) {
    if (!section) return;
    var title = qs('.section-title', section);
    var label = qs('.section-label', section);
    if (title && !title.textContent.trim() && label && label.textContent.trim()) {
      title.textContent = label.textContent.trim();
    }
  };

  /* Header: one navigation, stable DB-derived monogram. */
  var brandName = qs('.theme-brand > span:last-child');
  var brandMark = qs('.theme-brand__mark');
  if (brandName && brandMark) {
    var words = brandName.textContent.trim().split(/\s+/);
    brandMark.textContent = words[0] && words[0].length <= 3
      ? words[0].toUpperCase()
      : words.slice(0, 2).map(function (word) { return word.charAt(0); }).join('').toUpperCase();
  }

  qsa(':scope > .nav, :scope > nav', content).forEach(function (nav) { nav.hidden = true; });
  var navigation = qs('.theme-nav-wrap');
  var toggle = qs('.blt-nav-toggle');
  if (toggle && navigation) {
    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', String(!open));
      navigation.classList.toggle('is-open', !open);
      body.classList.toggle('blt-menu-open', !open);
    });
    qsa('a', navigation).forEach(function (link) {
      link.addEventListener('click', function () {
        toggle.setAttribute('aria-expanded', 'false');
        navigation.classList.remove('is-open');
        body.classList.remove('blt-menu-open');
      });
    });
  }

  var anchorLinks = qsa('.theme-nav-wrap .nav-links a[href*="#"]');
  var activateAnchor = function (id) {
    anchorLinks.forEach(function (link) {
      var active = new URL(link.href, window.location.origin).hash === '#' + id;
      link.classList.toggle('is-active', active);
      if (active) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
  };
  qsa('.theme-nav-wrap a[href]').forEach(function (link) {
    link.classList.remove('is-active');
    link.removeAttribute('aria-current');
  });
  qsa('.theme-nav-wrap .brand, .theme-nav-wrap input, .theme-nav-wrap label').forEach(function (element) {
    element.remove();
  });

  /* Reorder the legacy homepage chapters into the target story sequence. */
  var sections = {
    hero: qs('#hero, #uvod.hp-hero'),
    services: qs('#sluzby'),
    aboutLegacy: qs('#proc-my'),
    portfolio: qs('#nase-prace'),
    reviewsLegacy: qs('#reference'),
    reviews: qs('#hodnoceni'),
    pricing: qs('#cenik'),
    blog: qs('#blog'),
    faq: qs('#faq'),
    process: qs('#proces'),
    contact: qs('#kontakt')
  };
  if (sections.hero) {
    sections.hero.classList.add('hero');
    var heroContainer = qs(':scope > .container', sections.hero);
    if (heroContainer) heroContainer.classList.add('hero-body');
    var heroActions = qs('.hero-cta-wrap, .hero-btns', sections.hero);
    if (heroActions) {
      heroActions.classList.add('hero-btns');
      var primaryHeroCta = qs('a', heroActions);
      if (primaryHeroCta) primaryHeroCta.textContent = 'Zjistit více';
      if (!qs('.blt-showreel', heroActions)) {
        var showreelCta = document.createElement('a');
        showreelCta.className = 'blt-showreel';
        showreelCta.href = '#nase-prace';
        showreelCta.textContent = 'Přehrát showreel';
        heroActions.appendChild(showreelCta);
      }
    }
    if (!qs('.blt-hero-admin-overlay', sections.hero)) {
      var adminOverlay = document.createElement('span');
      adminOverlay.className = 'blt-hero-admin-overlay';
      adminOverlay.setAttribute('aria-hidden', 'true');
      sections.hero.insertBefore(adminOverlay, sections.hero.firstChild);
    }
    var heroTitle = qs('h1', sections.hero);
    if (heroTitle) heroTitle.innerHTML = 'PŘÍBĚHY,<br><span>které zůstanou</span>';
  }
  if (sections.aboutLegacy) sections.aboutLegacy.hidden = true;
  if (sections.reviewsLegacy) sections.reviewsLegacy.hidden = true;
  [sections.services, sections.portfolio, sections.process, sections.pricing, sections.reviews, sections.blog, sections.contact]
    .forEach(promoteEmptyTitle);
  [sections.services, sections.portfolio, sections.process, sections.pricing, sections.reviews, sections.blog, sections.contact]
    .filter(Boolean)
    .forEach(function (section) {
      if (legacyFooter) {
        content.insertBefore(section, legacyFooter);
      } else {
        content.appendChild(section);
      }
      section.classList.add('blt-chapter');
    });

  var setEditorialCopy = function (section, title, intro, ctaText) {
    if (!section) return;
    var titleNode = qs('.section-title', section);
    var introNode = qs('.section-intro', section);
    var cta = qs('.hp-section-cta a', section);
    if (titleNode) titleNode.textContent = title;
    if (introNode) introNode.textContent = intro;
    if (cta && ctaText) cta.textContent = ctaText;
  };
  setEditorialCopy(sections.services, 'Více než fotografie a video', 'Spojujeme cit pro detail, kreativitu a zkušenosti, abychom vytvořili upřímné příběhy, které přežijí generace.', 'Více o nás');
  setEditorialCopy(sections.portfolio, 'Vybrané příběhy', 'Každá svatba je jedinečná. Tady je pár našich oblíbených.', 'Prohlédnout portfolio');
  setEditorialCopy(sections.process, 'Jak spolupráce probíhá', 'Od první zprávy až po hotové vzpomínky - klidně, jasně a v tempu vašeho dne.', 'Kontaktovat nás');
  setEditorialCopy(sections.pricing, 'Balíčky na míru vašemu příběhu', 'Každý příběh je jedinečný, stejně jako naše balíčky.', 'Zjistit více');
  setEditorialCopy(sections.reviews, 'Co o nás říkají klienti', 'Důvěra a radost klientů jsou pro nás nejlepší vizitkou.', 'Další reference');
  setEditorialCopy(sections.blog, 'Rady pro den, který si užijete', 'Výběr dodavatelů i příprava dne bez zbytečného stresu.', 'Všechny články');
  setEditorialCopy(sections.contact, 'Pojďme tvořit váš příběh', 'Napište nám pár slov o vás a vašem dni. Odpovíme do 24 hodin.', 'Kontakt');

  var reviewTitle = sections.reviews ? qs('.section-title', sections.reviews) : null;
  var blogTitle = sections.blog ? qs('.section-title', sections.blog) : null;
  if (reviewTitle) reviewTitle.innerHTML = 'CO O N\u00c1S<br>\u0158\u00cdKAJ\u00cd<br>NA\u0160I KLIENTI';
  if (blogTitle) blogTitle.innerHTML = 'RADY PRO<br>DEN,<br>KTER\u00dd SI<br>U\u017dIJETE';
  var blogIntro = sections.blog ? qs('.section-intro', sections.blog) : null;
  if (blogIntro) blogIntro.textContent = 'Dv\u011b praktick\u00e9 inspirace, kter\u00e9 v\u00e1m pomohou s v\u00fdb\u011brem dodavatel\u016f i s p\u0159\u00edpravou klidn\u00e9ho svatebn\u00edho dne.';

  /* About/services: editorial copy, one DB image and a horizontal offer rail. */
  if (sections.services) {
    sections.services.classList.add('blt-about');
    var aboutContainer = qs(':scope > .container', sections.services);
    var aboutHeader = qs('.sec-hdr', sections.services);
    var servicesGrid = qs('.svc-grid, .hp-services-grid', sections.services);
    var firstServiceImage = qs('.hp-svc-card__media img', sections.services);
    var firstServiceLink = qs('.hp-svc-card__btn', sections.services);
    if (aboutContainer && !aboutHeader) {
      aboutHeader = document.createElement('div');
      aboutHeader.className = 'sec-hdr';
      ['.section-label', '.section-title', '.section-intro'].forEach(function (selector) {
        var node = qs(':scope > ' + selector, aboutContainer);
        if (node) aboutHeader.appendChild(node);
      });
      aboutContainer.insertBefore(aboutHeader, aboutContainer.firstChild);
    }
    if (aboutContainer && aboutHeader && servicesGrid) {
      var visual = document.createElement('div');
      visual.className = 'blt-about__visual';
      if (firstServiceImage) {
        var image = firstServiceImage.cloneNode(true);
        image.removeAttribute('loading');
        visual.appendChild(image);
      }
      aboutHeader.classList.add('blt-editorial-copy');
      if (firstServiceLink) {
        var aboutCta = firstServiceLink.cloneNode(true);
        aboutCta.className = 'blt-button blt-button--solid';
        aboutCta.textContent = 'V\u00edce o n\u00e1s';
        aboutHeader.appendChild(aboutCta);
      }
      aboutContainer.insertBefore(visual, servicesGrid);
      servicesGrid.classList.add('blt-offer-rail');
      qsa('.hp-svc-card', servicesGrid).forEach(function (card) {
        card.classList.add('blt-offer-item');
        var cardBody = qs('.hp-svc-card__body', card);
        if (cardBody && !qs('.blt-offer-icon', cardBody)) {
          var icon = document.createElement('span');
          icon.className = 'blt-offer-icon';
          icon.setAttribute('aria-hidden', 'true');
          cardBody.insertBefore(icon, cardBody.firstChild);
        }
        if (card.parentElement !== servicesGrid && card.parentElement.children.length === 1) {
          servicesGrid.appendChild(card);
        }
      });
      var offerIcons = [
        '<svg viewBox="0 0 32 32"><circle cx="12" cy="17" r="7"/><circle cx="20" cy="17" r="7"/><path d="M9 8l3-4 3 4M17 8l3-4 3 4"/></svg>',
        '<svg viewBox="0 0 32 32"><circle cx="16" cy="9" r="4"/><circle cx="8" cy="12" r="3"/><circle cx="24" cy="12" r="3"/><path d="M9 27v-5c0-5 3-8 7-8s7 3 7 8v5M3 27v-5c0-4 2-6 5-6M29 27v-5c0-4-2-6-5-6"/></svg>',
        '<svg viewBox="0 0 32 32"><rect x="4" y="8" width="18" height="16" rx="3"/><path d="M22 13l6-4v14l-6-4z"/></svg>',
        '<svg viewBox="0 0 32 32"><path d="M6 10h5l2-3h7l2 3h4a3 3 0 013 3v11a3 3 0 01-3 3H6a3 3 0 01-3-3V13a3 3 0 013-3z"/><circle cx="16" cy="18" r="5"/></svg>',
        '<svg viewBox="0 0 32 32"><path d="M3 14L29 3 19 29l-5-10zM14 19L29 3"/></svg>'
      ];
      qsa('.blt-offer-icon', servicesGrid).forEach(function (icon, index) {
        icon.innerHTML = offerIcons[index] || offerIcons[offerIcons.length - 1];
      });
    }
  }

  if (sections.portfolio) {
    sections.portfolio.classList.add('blt-portfolio');
    var portfolioContainer = qs(':scope > .container', sections.portfolio);
    if (portfolioContainer && !qs('.sec-hdr', portfolioContainer)) {
      var portfolioHeader = document.createElement('div');
      portfolioHeader.className = 'sec-hdr';
      ['.section-label', '.section-title', '.section-intro'].forEach(function (selector) {
        var node = qs(':scope > ' + selector, portfolioContainer);
        if (node) portfolioHeader.appendChild(node);
      });
      portfolioContainer.insertBefore(portfolioHeader, portfolioContainer.firstChild);
    }
    var portfolioCta = qs('.hp-section-cta', sections.portfolio);
    var activePortfolioHeader = qs('.sec-hdr', sections.portfolio);
    if (portfolioCta && activePortfolioHeader) {
      portfolioCta.classList.add('blt-portfolio-cta');
      activePortfolioHeader.appendChild(portfolioCta);
    }
  }
  if (sections.process) {
    sections.process.classList.add('blt-process');
    var processContainer = qs(':scope > .container', sections.process);
    var processList = qs('.ew-process-list', sections.process);
    if (processContainer && processList && processList.parentElement !== processContainer) processContainer.appendChild(processList);
    if (processContainer && !qs('.blt-chapter-cta', processContainer)) {
      var processCta = document.createElement('a');
      processCta.className = 'blt-button blt-button--solid blt-chapter-cta';
      processCta.href = '#kontakt';
      processCta.textContent = 'Kontaktovat nás';
      processContainer.appendChild(processCta);
    }
  }
  if (sections.pricing) {
    sections.pricing.classList.add('blt-pricing');
    var pricingContainer = qs(':scope > .container', sections.pricing);
    var pricingGrid = qs('.hp-cenik-grid', sections.pricing);
    if (pricingContainer && pricingGrid && pricingGrid.parentElement !== pricingContainer) pricingContainer.appendChild(pricingGrid);
    qsa('.hp-cenik-card-desc', sections.pricing).forEach(function (description) {
      var points = description.textContent.split(/\.\s+/).map(function (point) {
        return point.trim().replace(/\.$/, '');
      }).filter(Boolean);
      if (points.length) {
        var list = document.createElement('ul');
        list.className = 'blt-pricing-points';
        points.forEach(function (point) {
          var item = document.createElement('li');
          item.textContent = point;
          list.appendChild(item);
        });
        description.textContent = '';
        description.appendChild(list);
      }
    });
  }
  if (sections.blog) {
    sections.blog.classList.add('blt-blog');
    var blogGrid = qs('.hp-blog-grid', sections.blog);
    if (blogGrid) {
      qsa(':scope > .hp-blog-card > .hp-blog-card', blogGrid).forEach(function (innerCard) {
        var wrapper = innerCard.parentElement;
        blogGrid.insertBefore(innerCard, wrapper);
        wrapper.remove();
      });
    }
    qsa(':scope > .hp-blog-card', blogGrid).forEach(function (card, index) {
      card.hidden = index > 1;
    });
    qsa('.hp-blog-thumb img', blogGrid).forEach(function (image) {
      image.loading = 'eager';
      image.removeAttribute('loading');
      var blogSource = image.getAttribute('src');
      if (blogSource) image.src = blogSource;
    });
  }

  /* Reviews: three-card carousel with DB cards, arrows and indicators. */
  if (sections.reviews) {
    sections.reviews.classList.add('blt-reviews');
    var reviewGrid = qs('.hp-reviews-grid', sections.reviews);
    var reviewContainer = qs(':scope > .container', sections.reviews);
    if (reviewContainer && reviewGrid && reviewGrid.parentElement !== reviewContainer) reviewContainer.appendChild(reviewGrid);
    var reviewCards = reviewGrid ? qsa('.hp-review-card', reviewGrid) : [];
    if (reviewGrid && reviewCards.length) {
      var reviewPage = 0;
      var pageSize = 3;
      var pageCount = Math.ceil(reviewCards.length / pageSize);
      var controls = document.createElement('div');
      controls.className = 'blt-review-controls';
      controls.innerHTML = '<button type="button" data-review-prev aria-label="Předchozí recenze">&#8592;</button>'
        + '<div class="blt-review-dots" aria-label="Stránky recenzí"></div>'
        + '<button type="button" data-review-next aria-label="Další recenze">&#8594;</button>';
      reviewGrid.insertAdjacentElement('afterend', controls);
      var dots = qs('.blt-review-dots', controls);
      for (var dotIndex = 0; dotIndex < pageCount; dotIndex += 1) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.dataset.reviewPage = String(dotIndex);
        dot.setAttribute('aria-label', 'Zobrazit recenze ' + (dotIndex + 1));
        dots.appendChild(dot);
      }
      var renderReviews = function () {
        reviewCards.forEach(function (card, index) {
          card.hidden = index < reviewPage * pageSize || index >= (reviewPage + 1) * pageSize;
        });
        qsa('[data-review-page]', dots).forEach(function (dotButton, index) {
          dotButton.classList.toggle('is-active', index === reviewPage);
          dotButton.setAttribute('aria-current', index === reviewPage ? 'true' : 'false');
        });
      };
      qs('[data-review-prev]', controls).addEventListener('click', function () {
        reviewPage = (reviewPage - 1 + pageCount) % pageCount;
        renderReviews();
      });
      qs('[data-review-next]', controls).addEventListener('click', function () {
        reviewPage = (reviewPage + 1) % pageCount;
        renderReviews();
      });
      dots.addEventListener('click', function (event) {
        var target = event.target.closest('[data-review-page]');
        if (!target) return;
        reviewPage = Number(target.dataset.reviewPage);
        renderReviews();
      });
      renderReviews();
    }
  }

  /* Contact: DB intro + DB form + DB FAQ in three target panels. */
  var contactSource = qs('[data-blt-contact-source]');
  var faqSource = qs('[data-blt-faq-source]');
  if (sections.contact) {
    sections.contact.classList.add('blt-contact', 'blt-contact-with-faq');
    var contactContainer = qs(':scope > .container', sections.contact);
    var contactGrid = qs('.cta-2col', sections.contact);
    var legacyIntro = contactGrid ? qs('.cta-l', contactGrid) : null;
    var sourceGrid = qs('.hp-contact-grid', sections.contact) || (contactSource ? qs('.hp-contact-grid', contactSource) : null);
    var sourceBoxes = sourceGrid ? qsa(':scope > .hp-contact-box', sourceGrid) : [];
    if (!contactGrid && contactContainer) {
      contactGrid = document.createElement('div');
      contactGrid.className = 'cta-2col';
      legacyIntro = document.createElement('div');
      legacyIntro.className = 'cta-l';
      ['.section-label', '.section-title', '.section-intro'].forEach(function (selector) {
        var node = qs(':scope > ' + selector, contactContainer);
        if (node) legacyIntro.appendChild(node);
      });
      if (sourceBoxes[0]) legacyIntro.appendChild(sourceBoxes[0]);
      contactContainer.appendChild(contactGrid);
    }
    if (contactGrid && legacyIntro && sourceBoxes.length >= 2) {
      contactGrid.innerHTML = '';
      legacyIntro.classList.add('blt-contact__intro');
      contactGrid.appendChild(legacyIntro);
      sourceBoxes[1].classList.add('blt-contact__form');
      contactGrid.appendChild(sourceBoxes[1]);
      var faqPanel = document.createElement('aside');
      faqPanel.className = 'blt-contact-faq';
      faqPanel.innerHTML = '<span class="section-label">Časté dotazy</span><h3>Než nám napíšete</h3>';
      var activeFaqSource = sections.faq || faqSource;
      if (activeFaqSource) {
        var faqList = document.createElement('div');
        faqList.className = 'blt-faq-list';
        qsa('.hp-faq-item', activeFaqSource).slice(0, 4).forEach(function (item) {
          faqList.appendChild(item);
        });
        faqPanel.appendChild(faqList);
      }
      var faqLink = document.createElement('a');
      faqLink.href = '/faq';
      faqLink.className = 'blt-text-link';
      faqLink.textContent = 'Všechny dotazy →';
      faqPanel.appendChild(faqLink);
      contactGrid.appendChild(faqPanel);
      if (sections.faq) sections.faq.hidden = true;
    }
  }


  if (anchorLinks.length) {
    var anchorSections = anchorLinks.map(function (link) {
      var hash = new URL(link.href, window.location.origin).hash;
      return hash ? qs(hash) : null;
    }).filter(Boolean);
    var navFrame = 0;
    var syncAnchorNavigation = function () {
      navFrame = 0;
      var marker = Math.max(90, window.innerHeight * .24);
      var current = anchorSections.find(function (section) {
        var rect = section.getBoundingClientRect();
        return rect.top <= marker && rect.bottom > marker;
      });
      if (current) activateAnchor(current.id);
      else anchorLinks.forEach(function (link) {
        link.classList.remove('is-active');
        link.removeAttribute('aria-current');
      });
    };
    var requestNavSync = function () {
      if (!navFrame) navFrame = window.requestAnimationFrame(syncAnchorNavigation);
    };
    window.addEventListener('scroll', requestNavSync, { passive: true });
    window.addEventListener('resize', requestNavSync);
    if (window.location.hash) activateAnchor(window.location.hash.slice(1));
    else syncAnchorNavigation();
  }

  qsa('.hp-faq-item').forEach(function (item) {
    var question = qs('.hp-faq-question', item);
    var answer = qs('.hp-faq-answer', item);
    if (!question || !answer) return;
    question.setAttribute('role', 'button');
    question.setAttribute('tabindex', '0');
    question.setAttribute('aria-expanded', 'false');
    var toggleFaq = function () {
      var open = item.classList.toggle('is-open');
      question.setAttribute('aria-expanded', String(open));
    };
    question.addEventListener('click', toggleFaq);
    question.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleFaq();
      }
    });
  });

  /* Film: stable DB-image strip and accessible lightbox gallery. */
  var film = qs('.blt-film__track');
  if (film) {
    while (film.children.length < 16) film.appendChild(film.children[film.children.length % 9].cloneNode(true));
    var pageImages = qsa('.blt-page-content img').filter(function (image) { return image.currentSrc || image.src; });
    var uniqueSources = [];
    pageImages.forEach(function (image) {
      var source = image.currentSrc || image.src;
      if (uniqueSources.indexOf(source) === -1) uniqueSources.push(source);
    });
    qsa('i', film).forEach(function (frame, index) {
      var source = uniqueSources[index % Math.max(uniqueSources.length, 1)];
      if (!source) return;
      frame.style.backgroundImage = 'linear-gradient(rgba(28,24,19,.12),rgba(28,24,19,.12)),url("' + source.replace(/"/g, '%22') + '")';
      frame.dataset.galleryIndex = String(index % uniqueSources.length);
      frame.setAttribute('role', 'button');
      frame.setAttribute('tabindex', '0');
      frame.setAttribute('aria-label', 'Otevřít fotografii ' + (index + 1));
    });

    if (uniqueSources.length) {
      var lightbox = document.createElement('div');
      lightbox.className = 'blt-lightbox';
      lightbox.hidden = true;
      lightbox.innerHTML = '<button type="button" class="blt-lightbox__close" aria-label="Zavřít galerii">×</button>'
        + '<button type="button" class="blt-lightbox__prev" aria-label="Předchozí fotografie">&#8592;</button>'
        + '<img alt="">'
        + '<button type="button" class="blt-lightbox__next" aria-label="Další fotografie">&#8594;</button>';
      body.appendChild(lightbox);
      var lightboxImage = qs('img', lightbox);
      var galleryIndex = 0;
      var showImage = function () { lightboxImage.src = uniqueSources[galleryIndex]; };
      var openLightbox = function (index) {
        galleryIndex = index;
        showImage();
        lightbox.hidden = false;
        body.classList.add('blt-lightbox-open');
        qs('.blt-lightbox__close', lightbox).focus();
      };
      var closeLightbox = function () {
        lightbox.hidden = true;
        body.classList.remove('blt-lightbox-open');
      };
      film.addEventListener('click', function (event) {
        var frame = event.target.closest('[data-gallery-index]');
        if (frame) openLightbox(Number(frame.dataset.galleryIndex));
      });
      film.addEventListener('keydown', function (event) {
        var frame = event.target.closest('[data-gallery-index]');
        if (frame && (event.key === 'Enter' || event.key === ' ')) {
          event.preventDefault();
          openLightbox(Number(frame.dataset.galleryIndex));
        }
      });
      qs('.blt-lightbox__close', lightbox).addEventListener('click', closeLightbox);
      qs('.blt-lightbox__prev', lightbox).addEventListener('click', function () {
        galleryIndex = (galleryIndex - 1 + uniqueSources.length) % uniqueSources.length;
        showImage();
      });
      qs('.blt-lightbox__next', lightbox).addEventListener('click', function () {
        galleryIndex = (galleryIndex + 1) % uniqueSources.length;
        showImage();
      });
      lightbox.addEventListener('click', function (event) { if (event.target === lightbox) closeLightbox(); });
      document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !lightbox.hidden) closeLightbox(); });
    }
  }

  /* Gentle reveal without changing layout geometry. */
  var revealTargets = qsa('.blt-chapter');
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.06, rootMargin: '0px 0px -24px' });
    revealTargets.forEach(function (element) { observer.observe(element); });
  } else {
    revealTargets.forEach(function (element) { element.classList.add('is-visible'); });
  }

  body.dataset.bltReady = 'true';

}());
