(function () {
  'use strict';

  var body = document.body;
  if (!body || !body.classList.contains('theme-bezova-lens-tale')) return;
  if (body.dataset.bltInitialized === 'true') return;
  body.dataset.bltInitialized = 'true';

  var brandName = document.querySelector('.theme-brand > span:last-child');
  var brandMark = document.querySelector('.theme-brand__mark');
  if (brandName && brandMark) {
    var initials = brandName.textContent.trim().split(/\s+/).slice(0, 2).map(function (word) {
      return word.charAt(0);
    }).join('');
    brandMark.textContent = initials || '•';
  }

  var toggle = document.querySelector('.blt-nav-toggle');
  var navigation = document.querySelector('.theme-nav-wrap');
  if (toggle && navigation) {
    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', String(!open));
      navigation.classList.toggle('is-open', !open);
      body.classList.toggle('blt-menu-open', !open);
    });
  }

  var currentPath = window.location.pathname.replace(/\/$/, '') || '/';
  document.querySelectorAll('.theme-nav-wrap a[href]').forEach(function (link) {
    var href = new URL(link.href, window.location.origin).pathname.replace(/\/$/, '') || '/';
    if (href === currentPath) link.setAttribute('aria-current', 'page');
  });

  document.querySelectorAll('.ew-faq-question, .faq-q').forEach(function (button) {
    button.addEventListener('click', function () {
      var item = button.closest('.ew-faq-item, .faq-item');
      if (!item) return;
      var open = item.classList.toggle('is-open');
      button.setAttribute('aria-expanded', String(open));
    });
  });

  var revealTargets = document.querySelectorAll(
    '.hp-section, .services-section, .pf-section, .bl-section, .fq-section, .kt-section, .cenik-section, .bp-section, .pf-detail-section, .service-card, .portfolio-card, .blog-card, .price-card'
  );
  revealTargets.forEach(function (element) { element.classList.add('blt-reveal'); });

  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -36px' });
    revealTargets.forEach(function (element) { observer.observe(element); });
  } else {
    revealTargets.forEach(function (element) { element.classList.add('is-visible'); });
  }

  var film = document.querySelector('.blt-film__track');
  if (film) {
    var pageImages = Array.prototype.filter.call(document.querySelectorAll('.blt-page-content img'), function (image) {
      return image.currentSrc || image.src;
    });
    film.querySelectorAll('i').forEach(function (frame, index) {
      var source = pageImages[index % Math.max(pageImages.length, 1)];
      if (source) {
        frame.style.backgroundImage = 'linear-gradient(rgba(28,24,19,.18),rgba(28,24,19,.18)),url("' + (source.currentSrc || source.src).replace(/"/g, '%22') + '")';
      }
    });

    var queued = false;
    window.addEventListener('scroll', function () {
      if (queued) return;
      queued = true;
      window.requestAnimationFrame(function () {
        film.style.setProperty('--film-shift', ((window.scrollY * -0.08) % 120) + 'px');
        queued = false;
      });
    }, { passive: true });
  }

  document.querySelectorAll('.blt-page-content > section, .blt-page-content .section').forEach(function (section, index) {
    if (!section.dataset.bltChapter) section.dataset.bltChapter = String(index + 1).padStart(2, '0');
  });

  var aboutSection = document.querySelector('#proc-my');
  if (aboutSection) aboutSection.classList.add('blt-about-section');

  var portfolioSection = document.querySelector('#nase-prace');
  if (portfolioSection) portfolioSection.classList.add('blt-portfolio-section');

  var duplicateReviews = document.querySelector('#reference');
  var primaryReviews = document.querySelector('#hodnoceni');
  if (duplicateReviews && primaryReviews) duplicateReviews.hidden = true;

  var contactSection = document.querySelector('#kontakt');
  var faqSection = document.querySelector('.hp-faq-section, #faq, .faq-section');
  if (contactSection && faqSection && faqSection !== contactSection) {
    var contactGrid = contactSection.querySelector('.cta-2col, .hp-contact-grid, .kt-grid');
    var faqContent = faqSection.querySelector('.faq-list, .ew-faq-list, .hp-faq-list') || faqSection;
    if (contactGrid && faqContent) {
      var faqPanel = document.createElement('aside');
      faqPanel.className = 'blt-contact-faq';
      faqPanel.appendChild(faqContent);
      contactGrid.appendChild(faqPanel);
      faqSection.hidden = true;
      contactSection.classList.add('blt-contact-with-faq');
    }
  }
}());
