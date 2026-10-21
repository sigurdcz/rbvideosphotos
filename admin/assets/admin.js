/* SEO Mini CMS - Admin JS */

// Tabs
document.addEventListener('DOMContentLoaded', () => {
  const loadingBar = document.querySelector('[data-admin-loading-bar]');
  const loadingProgress = document.querySelector('[data-admin-loading-bar-progress]');
  let loadingTimer = null;
  let currentProgress = 0;

  const setLoadingProgress = value => {
    if (!loadingProgress) return;
    currentProgress = Math.max(0, Math.min(100, value));
    loadingProgress.style.width = currentProgress + '%';
  };

  const stopLoading = () => {
    if (!loadingBar || !loadingProgress) return;
    if (loadingTimer) {
      window.clearInterval(loadingTimer);
      loadingTimer = null;
    }
    setLoadingProgress(100);
    window.setTimeout(() => {
      loadingBar.classList.remove('is-active');
      setLoadingProgress(0);
    }, 180);
  };

  const startLoading = () => {
    if (!loadingBar || !loadingProgress) return;
    if (loadingTimer) {
      window.clearInterval(loadingTimer);
    }
    loadingBar.classList.add('is-active');
    setLoadingProgress(12);
    loadingTimer = window.setInterval(() => {
      if (currentProgress < 82) {
        setLoadingProgress(currentProgress + (currentProgress < 40 ? 10 : 4));
      }
    }, 180);
  };

  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', e => {
      window.setTimeout(() => {
        if (!e.defaultPrevented) startLoading();
      }, 0);
    });
  });

  document.querySelectorAll('a[href]').forEach(link => {
    link.addEventListener('click', e => {
      const href = link.getAttribute('href') || '';
      if (
        e.defaultPrevented ||
        href === '' ||
        href.startsWith('#') ||
        link.hasAttribute('download') ||
        link.getAttribute('target') === '_blank' ||
        e.metaKey || e.ctrlKey || e.shiftKey || e.altKey
      ) {
        return;
      }
      startLoading();
    });
  });

  window.addEventListener('pageshow', stopLoading);
  window.addEventListener('load', stopLoading);

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

  const syncAutofillPanel = panel => {
    if (!panel) return;
    const toggle = panel.querySelector('[data-autofill-toggle]');
    const enabled = !!(toggle && toggle.checked);
    panel.classList.toggle('is-autofill-enabled', enabled);
    const fields = panel.querySelector('.autofill-panel__fields');
    if (fields) {
      fields.hidden = enabled;
      fields.setAttribute('aria-hidden', enabled ? 'true' : 'false');
    }
    panel.querySelectorAll('.autofill-panel__fields input:not([type=hidden]), .autofill-panel__fields textarea, .autofill-panel__fields select').forEach(field => {
      if (field === toggle) return;
      field.disabled = enabled;
    });
  };

  document.querySelectorAll('[data-autofill-panel]').forEach(panel => {
    const toggle = panel.querySelector('[data-autofill-toggle]');
    if (toggle) {
      toggle.addEventListener('change', () => syncAutofillPanel(panel));
    }
    syncAutofillPanel(panel);
  });

  // FAQ repeater in page editor
  const faqList = document.getElementById('faq-items-list');
  const faqTemplate = document.getElementById('faq-item-template');
  const faqAddButton = document.querySelector('[data-add-faq-item]');
  if (faqList && faqTemplate && faqAddButton) {
    const syncFaqIndices = () => {
      faqList.querySelectorAll('[data-faq-item]').forEach((item, index) => {
        item.querySelectorAll('[data-faq-name]').forEach(field => {
          field.name = `faq_items[${index}][${field.dataset.faqName}]`;
        });
        const hiddenActive = item.querySelector('[data-faq-hidden-active]');
        if (hiddenActive) hiddenActive.name = `faq_items[${index}][is_active]`;
      });

      const emptyState = document.getElementById('faq-empty-state');
      if (emptyState) emptyState.style.display = faqList.children.length ? 'none' : '';
    };

    faqAddButton.addEventListener('click', () => {
      const fragment = faqTemplate.content.cloneNode(true);
      faqList.appendChild(fragment);
      syncFaqIndices();
    });

    faqList.addEventListener('click', e => {
      const removeBtn = e.target.closest('[data-remove-faq-item]');
      if (!removeBtn) return;
      const item = removeBtn.closest('[data-faq-item]');
      if (item) item.remove();
      syncFaqIndices();
    });

    syncFaqIndices();
  }

  const serviceList = document.getElementById('service-items-list');
  const serviceTemplate = document.getElementById('service-item-template');
  const serviceAddButton = document.querySelector('[data-add-service-item]');
  if (serviceList && serviceTemplate && serviceAddButton) {
    const syncServiceIndices = () => {
      serviceList.querySelectorAll('[data-service-item]').forEach((item, index) => {
        item.querySelectorAll('[data-service-name]').forEach(field => {
          const name = field.dataset.serviceName;
          field.name = `service_items[${index}][${name}]`;
        });
        const hiddenActive = item.querySelector('[data-service-hidden-active]');
        if (hiddenActive) hiddenActive.name = `service_items[${index}][is_active]`;
      });

      const emptyState = document.getElementById('service-empty-state');
      if (emptyState) emptyState.style.display = serviceList.children.length ? 'none' : '';
    };

    serviceAddButton.addEventListener('click', () => {
      const fragment = serviceTemplate.content.cloneNode(true);
      serviceList.appendChild(fragment);
      syncServiceIndices();
    });

    serviceList.addEventListener('click', e => {
      const removeBtn = e.target.closest('[data-remove-service-item]');
      if (!removeBtn) return;
      const item = removeBtn.closest('[data-service-item]');
      if (item) item.remove();
      syncServiceIndices();
    });

    syncServiceIndices();
  }
});

// ── Article WYSIWYG editor ───────────────────────────────────────────────────
(function () {
  const wrappers = Array.from(document.querySelectorAll('[data-richtext-editor]'));
  const legacyEditor = document.getElementById('article-editor');
  if (legacyEditor && !legacyEditor.closest('[data-richtext-editor]')) {
    wrappers.push(legacyEditor.closest('.article-editor-wrap') || legacyEditor.parentElement);
  }
  if (wrappers.length === 0) return;

  wrappers.forEach(initEditor);

  function initEditor(wrapper) {
    if (!wrapper) return;
    const editor = wrapper.querySelector('[data-richtext-content]') || wrapper.querySelector('#article-editor');
    const hidden = wrapper.querySelector('[data-richtext-hidden]') || document.getElementById('article-content-hidden');
    const source = wrapper.querySelector('[data-richtext-html-source]') || wrapper.querySelector('#article-source');
    if (!editor || !hidden) return;

    let sourceMode = false;
    let savedRange = null;

    editor.closest('form')?.addEventListener('submit', () => {
      if (sourceMode) syncEditorFromSource();
      hidden.value = editor.innerHTML;
    });

    wrapper.querySelectorAll('[data-cmd]').forEach(btn => {
      btn.addEventListener('mousedown', e => {
        e.preventDefault();
        const cmd = btn.dataset.cmd;
        const val = btn.dataset.val || null;

        if (cmd === 'source') {
          toggleSource(btn);
          return;
        }
        if (sourceMode) return;

        editor.focus();
        if (cmd === 'createLink') {
          const url = prompt('URL odkazu:', 'https://');
          if (url) document.execCommand('createLink', false, url);
        } else if (cmd === 'insertMediaImage') {
          requestMediaImage();
        } else if (val) {
          document.execCommand('formatBlock', false, val);
        } else {
          document.execCommand(cmd, false, null);
        }
        hidden.value = editor.innerHTML;
        updateActiveStates();
      });
    });

    editor.addEventListener('keyup', () => {
      hidden.value = editor.innerHTML;
      updateActiveStates();
    });
    editor.addEventListener('mouseup', updateActiveStates);
    editor.addEventListener('focus', updateActiveStates);

    function updateActiveStates() {
      wrapper.querySelectorAll('[data-cmd]').forEach(btn => {
        const cmd = btn.dataset.cmd;
        if (['bold','italic','underline'].includes(cmd)) {
          btn.classList.toggle('ae-active', document.queryCommandState(cmd));
        }
      });
    }

    function toggleSource(button) {
      if (!source) return;
      sourceMode = !sourceMode;
      if (sourceMode) {
        source.value = editor.innerHTML;
        editor.style.display = 'none';
        source.style.display = 'block';
        button?.classList.add('ae-active');
      } else {
        syncEditorFromSource();
        source.style.display = 'none';
        editor.style.display = 'block';
        button?.classList.remove('ae-active');
        hidden.value = editor.innerHTML;
      }
    }

    function syncEditorFromSource() {
      if (source) editor.innerHTML = source.value;
    }

    function requestMediaImage() {
      const selection = window.getSelection();
      savedRange = selection && selection.rangeCount > 0 ? selection.getRangeAt(0).cloneRange() : null;
      let handled = false;
      const event = new CustomEvent('admin:media-picker:open', {
        detail: {
          select(url) {
            if (!url) return;
            handled = true;
            editor.focus();
            if (savedRange) {
              const sel = window.getSelection();
              sel.removeAllRanges();
              sel.addRange(savedRange);
            }
            document.execCommand('insertImage', false, url);
            hidden.value = editor.innerHTML;
          }
        }
      });
      document.dispatchEvent(event);
      if (!handled && !document.getElementById('service-media-modal')) {
        alert('Media picker neni na teto strance dostupny. Obrazek nelze vlozit.');
      }
    }
  }
})();

function slugify(str) {
  const map = {á:'a',č:'c',ď:'d',é:'e',ě:'e',í:'i',ň:'n',ó:'o',ř:'r',š:'s',ť:'t',ú:'u',ů:'u',ý:'y',ž:'z'};
  return str.toLowerCase()
    .replace(/[áčďéěíňóřšťúůýž]/g, c => map[c] || c)
    .replace(/[^a-z0-9\s-]/g, '')
    .trim().replace(/\s+/g, '-')
    .replace(/-+/g, '-');
}

// Shared renderer for a picked gallery: one card per photo with the order number
// next to it. The picker itself only returns a list, so every form used to invent its
// own markup; this keeps the numbers, the hidden ids and the visible order in sync in
// one place. Cards are shown in the order the visitor will see them.
(function () {
  function normalize(items) {
    return (items || []).map((item, index) => ({
      id: parseInt(item.id, 10),
      name: item.name || '',
      thumb: item.thumb || item.url || '',
      sort: Math.max(0, parseInt(item.sort, 10) || 0),
      picked: index,
    })).filter(item => item.id > 0);
  }

  // The card the editor just typed into wins a tie only when it moved up, otherwise
  // typing "1" next to a photo that already has 1 would appear to do nothing - and
  // typing a higher number would stop one position short of where it was aimed.
  function effectiveOrder(items, justEdited, movedUp) {
    const bias = item => (item.id === justEdited ? (movedUp ? 0 : 2) : 1);
    return [...items].sort((a, b) => (a.sort - b.sort) || (bias(a) - bias(b)) || (a.picked - b.picked));
  }

  // Photos are always numbered 1..n, so the numbers read as positions and stay easy
  // to retype. Renumbering after every change is what makes "1" mean "first".
  function renumber(items, justEdited, movedUp) {
    const ordered = effectiveOrder(items, justEdited, movedUp);
    ordered.forEach((item, index) => {
      item.sort = index + 1;
      item.picked = index;
    });
    return ordered;
  }

  window.AdminMediaSelection = {
    mount(options) {
      const container = options.container;
      if (!container) return null;
      const idsName = options.idsName;
      const sortName = options.sortName;
      let items = normalize(options.items);

      function render(justEdited, movedUp) {
        items = renumber(items, justEdited, movedUp);
        container.innerHTML = '';
        if (!items.length) {
          container.classList.remove('admin-media-selection');
          if (options.onChange) options.onChange(items);
          return;
        }
        container.classList.add('admin-media-selection');

        items.forEach(item => {
          const card = document.createElement('div');
          card.className = 'admin-media-selection__item';
          card.dataset.mediaId = String(item.id);

          const img = document.createElement('img');
          img.src = item.thumb;
          img.alt = item.name;
          img.loading = 'lazy';
          card.appendChild(img);

          const name = document.createElement('span');
          name.textContent = item.name;
          name.title = item.name;
          card.appendChild(name);

          const row = document.createElement('div');
          row.className = 'admin-media-selection__order';

          const number = document.createElement('input');
          number.type = 'number';
          number.min = '0';
          number.step = '1';
          number.value = String(item.sort);
          number.setAttribute('value', String(item.sort));
          number.name = sortName + '[' + item.id + ']';
          number.title = 'Poradi (nizsi = driv)';
          number.addEventListener('change', () => {
            const typed = Math.min(Math.max(1, parseInt(number.value, 10) || 1), 1000000);
            const movedUp = typed < item.sort;
            item.sort = typed;
            render(item.id, movedUp);
          });
          row.appendChild(number);

          const remove = document.createElement('button');
          remove.type = 'button';
          remove.className = 'btn btn-secondary';
          remove.textContent = '\u00d7';
          remove.title = 'Odebrat';
          remove.addEventListener('click', () => {
            items = items.filter(other => other.id !== item.id);
            render();
          });
          row.appendChild(remove);
          card.appendChild(row);

          const hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = idsName + '[]';
          hidden.value = String(item.id);
          card.appendChild(hidden);

          container.appendChild(card);
        });

        if (options.onChange) options.onChange(items);
      }

      render();

      return {
        // Merging by id keeps the numbers already typed for photos that stay picked.
        setItems(list) {
          const previous = new Map(items.map(item => [item.id, item.sort]));
          // A freshly picked photo goes to the end, never in front of photos the
          // editor already numbered - the same rule the listings use for 0.
          let next = items.length;
          items = normalize(list).map(item => ({
            ...item,
            sort: previous.has(item.id) ? previous.get(item.id) : ++next,
          }));
          render();
        },
        getItems() {
          return items.map(item => ({id: item.id, name: item.name, thumb: item.thumb, sort: item.sort}));
        },
      };
    },
  };
})();

// Shared lazy media picker. No media records are embedded in edit-form HTML.
(function () {
  const modal = document.getElementById('admin-media-picker');
  if (!modal) return;
  const grid = modal.querySelector('[data-picker-grid]');
  const status = modal.querySelector('[data-picker-status]');
  const pageLabel = modal.querySelector('[data-picker-page]');
  const search = modal.querySelector('[data-picker-search] input');
  const tabs = modal.querySelector('[data-picker-tabs]');
  let options = null, items = [], selected = new Map(), page = 1, pages = 1, opener = null, kind = 'all';

  const close = () => {
    modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true');
    if (opener) opener.focus();
  };
  const render = () => {
    grid.innerHTML = '';
    items.forEach(item => {
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'admin-media-picker__item';
      button.classList.toggle('is-selected', selected.has(String(item.id)));
      let visual;
      if (item.thumb) {
        visual = document.createElement('img'); visual.src = item.thumb; visual.alt = item.alt || item.name; visual.loading = 'lazy';
      } else {
        // A video without a captured thumbnail: a placeholder instead of a broken image.
        visual = document.createElement('div'); visual.className = 'admin-media-picker__video-ph'; visual.textContent = '▶';
      }
      const name = document.createElement('span'); name.textContent = item.name;
      button.append(visual, name);
      if (item.video) {
        const info = document.createElement('small'); info.className = 'admin-media-picker__video-info';
        info.textContent = item.video.label || '';
        if (item.video.warnings && item.video.warnings.length) info.title = item.video.warnings.join(' ');
        button.appendChild(info);
        if (item.video.warnings && item.video.warnings.length) {
          const warn = document.createElement('small'); warn.className = 'admin-media-picker__video-warn';
          warn.textContent = '⚠ ' + item.video.warnings[0];
          button.appendChild(warn);
        }
      }
      button.addEventListener('click', () => {
        const key = String(item.id);
        if (options.mode === 'single') selected.clear();
        selected.has(key) ? selected.delete(key) : selected.set(key, item);
        render();
      });
      grid.appendChild(button);
    });
    status.textContent = items.length ? `${selected.size} vybráno` : 'Nebyla nalezena žádná média.';
    pageLabel.textContent = `Strana ${page} z ${pages}`;
    modal.querySelector('[data-picker-prev]').disabled = page <= 1;
    modal.querySelector('[data-picker-next]').disabled = page >= pages;
  };
  const load = async () => {
    status.textContent = 'Načítám média…'; grid.innerHTML = '';
    const qs = new URLSearchParams({page: String(page), q: search.value.trim(), images: options.imagesOnly ? '1' : '0', kind});
    try {
      const response = await fetch('/admin/media/picker?' + qs, {headers:{Accept:'application/json'}});
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json(); items = data.items || []; pages = data.pages || 1; render();
    } catch (error) {
      status.textContent = 'Média se nepodařilo načíst: ' + error.message;
    }
  };
  const syncTabs = () => {
    if (!tabs) return;
    // lockKind pins the picker to one kind (the hero video fields only take a video).
    tabs.querySelectorAll('[data-picker-tab]').forEach(tab => {
      const value = tab.dataset.pickerTab;
      const lock = options.lockKind || (options.imagesOnly ? 'image' : '');
      tab.hidden = !!lock && value !== lock;
      tab.classList.toggle('is-active', value === kind);
      tab.setAttribute('aria-selected', value === kind ? 'true' : 'false');
    });
  };
  if (tabs) tabs.addEventListener('click', e => {
    const tab = e.target.closest('[data-picker-tab]');
    if (!tab || tab.hidden || tab.dataset.pickerTab === kind) return;
    kind = tab.dataset.pickerTab; page = 1; syncTabs(); load();
  });
  window.AdminMediaPicker = {
    open(config) {
      options = Object.assign({mode:'single', imagesOnly:false, selected:[], kind:'', lockKind:''}, config || {});
      kind = options.lockKind || (options.imagesOnly ? 'image' : (options.kind || 'all'));
      syncTabs();
      selected = new Map((options.selected || []).map(item => [String(item.id), item]));
      opener = document.activeElement; page = 1; search.value = '';
      modal.classList.add('is-open'); modal.setAttribute('aria-hidden', 'false'); load(); search.focus();
    }
  };
  modal.querySelector('[data-picker-search]').addEventListener('submit', e => { e.preventDefault(); page = 1; load(); });
  modal.querySelector('[data-picker-prev]').addEventListener('click', () => { if (page > 1) { page--; load(); } });
  modal.querySelector('[data-picker-next]').addEventListener('click', () => { if (page < pages) { page++; load(); } });
  modal.querySelector('[data-picker-confirm]').addEventListener('click', () => { if (options && options.onConfirm) options.onConfirm([...selected.values()]); close(); });
  modal.querySelector('[data-picker-close]').addEventListener('click', close);
  modal.addEventListener('click', e => { if (e.target === modal) close(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('is-open')) close(); });
  document.addEventListener('admin:media-picker:open', e => {
    if (!e.detail || typeof e.detail.select !== 'function') return;
    e.stopImmediatePropagation();
    window.AdminMediaPicker.open({mode:'single', imagesOnly:!!e.detail.imagesOnly, kind: e.detail.kind || '', lockKind: e.detail.lockKind || '', onConfirm: list => { if (list[0]) e.detail.select(list[0].url, list[0]); }});
  }, true);
})();
