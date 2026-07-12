(function () {
  'use strict';

  var body = document.body;
  if (!body || !body.classList.contains('theme-bezova-lens-tale')) return;

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
}());
