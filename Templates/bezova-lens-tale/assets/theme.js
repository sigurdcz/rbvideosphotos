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

  var currentPath = window.location.pathname.replace(/\/$/, '') || '/';
  qsa('.theme-nav-wrap a[href]').forEach(function (link) {
    var href = new URL(link.href, window.location.origin).pathname.replace(/\/$/, '') || '/';
    if (href === currentPath) link.setAttribute('aria-current', 'page');
  });

  /* Reorder the legacy homepage chapters into the target story sequence. */
  var sections = {
    services: qs('#sluzby'),
    aboutLegacy: qs('#proc-my'),
    portfolio: qs('#nase-prace'),
    reviewsLegacy: qs('#reference'),
    reviews: qs('#hodnoceni'),
    pricing: qs('#cenik'),
    blog: qs('#blog'),
    process: qs('#proces'),
    contact: qs('#kontakt')
  };
  if (sections.aboutLegacy) sections.aboutLegacy.hidden = true;
  if (sections.reviewsLegacy) sections.reviewsLegacy.hidden = true;
  [sections.services, sections.portfolio, sections.process, sections.pricing, sections.reviews, sections.blog, sections.contact]
    .filter(Boolean)
    .forEach(function (section) {
      if (legacyFooter) content.insertBefore(section, legacyFooter);
      section.classList.add('blt-chapter');
    });

  /* About/services: editorial copy, one DB image and a horizontal offer rail. */
  if (sections.services) {
    sections.services.classList.add('blt-about');
    var aboutContainer = qs(':scope > .container', sections.services);
    var aboutHeader = qs('.sec-hdr', sections.services);
    var servicesGrid = qs('.svc-grid', sections.services);
    var firstServiceImage = qs('.hp-svc-card__media img', sections.services);
    var firstServiceLink = qs('.hp-svc-card__btn', sections.services);
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
        aboutHeader.appendChild(aboutCta);
      }
      aboutContainer.insertBefore(visual, servicesGrid);
      servicesGrid.classList.add('blt-offer-rail');
      qsa('.hp-svc-card', servicesGrid).forEach(function (card) {
        card.classList.add('blt-offer-item');
      });
    }
  }

  if (sections.portfolio) sections.portfolio.classList.add('blt-portfolio');
  if (sections.process) sections.process.classList.add('blt-process');
  if (sections.pricing) sections.pricing.classList.add('blt-pricing');
  if (sections.blog) {
    sections.blog.classList.add('blt-blog');
    qsa('.hp-blog-card', sections.blog).forEach(function (card, index) {
      card.hidden = index > 1;
    });
  }

  /* Reviews: three-card carousel with DB cards, arrows and indicators. */
  if (sections.reviews) {
    sections.reviews.classList.add('blt-reviews');
    var reviewGrid = qs('.hp-reviews-grid', sections.reviews);
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
  if (sections.contact && contactSource) {
    sections.contact.classList.add('blt-contact');
    var contactGrid = qs('.cta-2col', sections.contact);
    var legacyIntro = contactGrid ? qs('.cta-l', contactGrid) : null;
    var sourceGrid = qs('.hp-contact-grid', contactSource);
    var sourceBoxes = sourceGrid ? qsa(':scope > .hp-contact-box', sourceGrid) : [];
    if (contactGrid && legacyIntro && sourceBoxes.length >= 2) {
      contactGrid.innerHTML = '';
      legacyIntro.classList.add('blt-contact__intro');
      contactGrid.appendChild(legacyIntro);
      sourceBoxes[1].classList.add('blt-contact__form');
      contactGrid.appendChild(sourceBoxes[1]);
      var faqPanel = document.createElement('aside');
      faqPanel.className = 'blt-contact-faq';
      faqPanel.innerHTML = '<span class="section-label">Časté dotazy</span><h3>Než nám napíšete</h3>';
      if (faqSource) {
        var faqList = document.createElement('div');
        faqList.className = 'blt-faq-list';
        qsa('.hp-faq-item', faqSource).slice(0, 4).forEach(function (item) {
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
    }
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

  /* Continuous chapter rail numbers after the final order is known. */
  qsa('.blt-chapter').forEach(function (section, index) {
    section.dataset.bltChapter = String(index + 2).padStart(2, '0');
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
