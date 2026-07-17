(() => {
  'use strict';
  if (document.body.dataset.ethernaThemeReady === '1') return;
  document.body.dataset.ethernaThemeReady = '1';

  const header = document.querySelector('[data-etherna-header]');
  const toggle = document.querySelector('[data-etherna-menu]');
  const nav = document.querySelector('[data-etherna-nav]');
  const closeMenu = () => { nav?.classList.remove('is-open'); toggle?.setAttribute('aria-expanded', 'false'); };
  toggle?.addEventListener('click', () => { const open = !nav?.classList.contains('is-open'); nav?.classList.toggle('is-open', open); toggle.setAttribute('aria-expanded', String(open)); });
  nav?.addEventListener('click', event => { if (event.target.closest('a')) closeMenu(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
  const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 30);
  updateHeader(); window.addEventListener('scroll', updateHeader, {passive:true});

  document.querySelectorAll('.faq-item, .hp-faq-item').forEach(item => {
    const control = item.querySelector('button, .hp-faq-question');
    const answer = item.querySelector('.faq-answer, .hp-faq-answer');
    if (!control || !answer) return;
    if (!control.matches('button')) { control.setAttribute('role', 'button'); control.tabIndex = 0; }
    const change = () => { const open = !item.classList.contains('is-open'); item.parentElement?.querySelectorAll('.faq-item.is-open,.hp-faq-item.is-open').forEach(other => { other.classList.remove('is-open'); other.querySelector('button,.hp-faq-question')?.setAttribute('aria-expanded','false'); }); item.classList.toggle('is-open', open); control.setAttribute('aria-expanded', String(open)); };
    control.addEventListener('click', change); control.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); change(); } });
  });

  document.querySelectorAll('[data-filter-group]').forEach(group => group.addEventListener('click', event => {
    const button = event.target.closest('[data-filter]'); if (!button) return;
    const value = button.dataset.filter || 'all'; group.querySelectorAll('[data-filter]').forEach(x => x.classList.toggle('is-active', x === button));
    (group.closest('main') || document).querySelectorAll('[data-filter-item]').forEach(item => { item.hidden = value !== 'all' && !(item.dataset.category || '').split(/\s+/).includes(value); });
  }));
})();
