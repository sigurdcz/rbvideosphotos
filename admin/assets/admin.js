/* SEO Mini CMS — Admin JS */

// Tabs
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.tabs').forEach(tabs => {
    tabs.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = btn.dataset.tab;
        const container = tabs.closest('[data-tabs-container]') || document.body;
        container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        container.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        const pane = container.querySelector('#tab-' + target);
        if (pane) pane.classList.add('active');
      });
    });
  });

  // Activate first tab pane
  document.querySelectorAll('[data-tabs-container]').forEach(container => {
    const first = container.querySelector('.tab-btn');
    if (first) first.click();
  });

  // Drop zone upload
  document.querySelectorAll('.drop-zone').forEach(zone => {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.classList.remove('drag-over');
      const files = e.dataTransfer.files;
      if (files.length && zone.dataset.uploadInput) {
        const input = document.querySelector(zone.dataset.uploadInput);
        if (input) {
          const dt = new DataTransfer();
          for (const f of files) dt.items.add(f);
          input.files = dt.files;
          input.dispatchEvent(new Event('change'));
        }
      }
    });
    zone.addEventListener('click', () => {
      const input = document.querySelector(zone.dataset.uploadInput);
      if (input) input.click();
    });
  });

  // Confirm delete forms
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', e => {
      if (!confirm(form.dataset.confirm || 'Opravdu smazat?')) e.preventDefault();
    });
  });

  // Template card picker
  document.querySelectorAll('.tpl-picker input[type=radio]').forEach(radio => {
    radio.addEventListener('change', () => {
      document.querySelectorAll('.tpl-picker .tpl-card').forEach(c => c.classList.remove('tpl-card--active'));
      radio.closest('.tpl-card').classList.add('tpl-card--active');
    });
  });

  // Auto-generate slug from title
  const titleInput = document.getElementById('page-title');
  const slugInput  = document.getElementById('page-slug');
  const urlInput   = document.getElementById('page-url');
  if (titleInput && slugInput && !slugInput.value) {
    titleInput.addEventListener('input', () => {
      const slug = slugify(titleInput.value);
      slugInput.value = slug;
      if (urlInput && !urlInput.value) urlInput.value = slug;
    });
  }

  // Media picker modal
  document.querySelectorAll('[data-media-pick]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.mediaTarget;
      const modal  = document.getElementById('media-modal');
      if (!modal) return;
      modal.classList.add('open');
      modal.dataset.target = target;
    });
  });
  const mediaModal = document.getElementById('media-modal');
  if (mediaModal) {
    mediaModal.addEventListener('click', e => {
      if (e.target === mediaModal) mediaModal.classList.remove('open');
    });
    mediaModal.querySelectorAll('.media-card').forEach(card => {
      card.addEventListener('click', () => {
        const targetId = mediaModal.dataset.target;
        const input    = document.getElementById(targetId);
        if (input) {
          input.value = card.dataset.mediaId;
          const preview = document.getElementById(targetId + '-preview');
          if (preview) preview.src = card.dataset.mediaSrc;
        }
        mediaModal.classList.remove('open');
      });
    });
  }
});

function slugify(str) {
  const map = {á:'a',č:'c',ď:'d',é:'e',ě:'e',í:'i',ň:'n',ó:'o',ř:'r',š:'s',ť:'t',ú:'u',ů:'u',ý:'y',ž:'z'};
  return str.toLowerCase()
    .replace(/[áčďéěíňóřšťúůýž]/g, c => map[c] || c)
    .replace(/[^a-z0-9\s-]/g, '')
    .trim().replace(/\s+/g, '-')
    .replace(/-+/g, '-');
}
