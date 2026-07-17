(() => {
  'use strict';
  if (document.body.dataset.ethernaHomepageReady === '1') return;
  document.body.dataset.ethernaHomepageReady = '1';
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const elements = [...document.querySelectorAll('.theme-etherna [data-parallax]')];
  if (!elements.length || reduced.matches) return;
  let frame = 0;
  const paint = () => { frame = 0; const middle = innerHeight / 2; elements.forEach(el => { const rect = el.getBoundingClientRect(); const speed = Number(el.dataset.parallax) || 0; const offset = Math.max(-120, Math.min(120, (middle - (rect.top + rect.height / 2)) * speed)); el.style.setProperty('--parallax-y', `${offset.toFixed(1)}px`); }); };
  const request = () => { if (!frame) frame = requestAnimationFrame(paint); };
  request(); addEventListener('scroll', request, {passive:true}); addEventListener('resize', request, {passive:true});
})();
