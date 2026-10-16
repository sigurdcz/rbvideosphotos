/* Inline editor - front-end script */
(function () {
  'use strict';

  const pageId   = document.querySelector('meta[name="inline-page-id"]')?.content || '';
  const csrfToken= document.querySelector('meta[name="inline-csrf"]')?.content     || '';
  if (!pageId || !csrfToken) return;

  // ── Toolbar ───────────────────────────────────────────────────────────────
  const toolbar = document.createElement('div');
  toolbar.id = 'ie-toolbar';
  toolbar.innerHTML = `
    <span class="ie-label">Editor</span>
    <span id="ie-status"></span>
    <button id="ie-save" disabled>Uložit</button>
    <button id="ie-cancel" style="display:none">Zrušit</button>
  `;
  document.body.appendChild(toolbar);

  const btnSave   = document.getElementById('ie-save');
  const btnCancel = document.getElementById('ie-cancel');
  const statusEl  = document.getElementById('ie-status');

  // ── Modal ─────────────────────────────────────────────────────────────────
  const modalOverlay = document.createElement('div');
  modalOverlay.id = 'ie-modal-overlay';
  modalOverlay.innerHTML = `
    <div id="ie-modal">
      <div id="ie-modal-head">
        <span id="ie-modal-title">Editace</span>
        <button id="ie-modal-x" title="Zavřít">✕</button>
      </div>
      <div id="ie-modal-body">
        <!-- html mode -->
        <textarea id="ie-modal-textarea" style="display:none"></textarea>
        <!-- image mode -->
        <img id="ie-img-preview" src="" alt="">
        <label id="ie-img-upload-label">
          <span>&#128247; Klikni nebo přetáhni obrázek</span>
          <input type="file" id="ie-img-file" accept="image/*">
        </label>
      </div>
      <div id="ie-modal-foot">
        <button id="ie-modal-cancel">Zrušit</button>
        <button id="ie-modal-save">Uložit</button>
      </div>
    </div>
  `;
  document.body.appendChild(modalOverlay);

  const modalTitle   = document.getElementById('ie-modal-title');
  const modalTextarea= document.getElementById('ie-modal-textarea');
  const imgPreview   = document.getElementById('ie-img-preview');
  const imgFileInput = document.getElementById('ie-img-file');
  const imgUpLabel   = document.getElementById('ie-img-upload-label');
  const btnModalSave = document.getElementById('ie-modal-save');
  const btnModalCancel=document.getElementById('ie-modal-cancel');
  const btnModalX    = document.getElementById('ie-modal-x');

  // ── State ─────────────────────────────────────────────────────────────────
  let activeNode    = null;   // currently active [data-inline-key] element
  let activeKey     = null;
  let activeType    = null;
  let originalText  = null;   // for text/html revert
  let pendingFile   = null;   // File object selected in modal

  // ── Helpers ───────────────────────────────────────────────────────────────
  function setStatus(msg, isError = false) {
    statusEl.textContent = msg;
    statusEl.className   = isError ? 'ie-error' : '';
  }

  function clearStatus() { statusEl.textContent = ''; statusEl.className = ''; }

  function enableSave(on) {
    btnSave.disabled = !on;
  }

  function openModal(type, key, node) {
    modalTitle.textContent = key;
    modalTextarea.style.display = 'none';
    imgPreview.style.display    = 'none';
    imgUpLabel.style.display    = 'none';
    pendingFile = null;
    btnModalSave.disabled = false;

    if (type === 'image') {
      imgPreview.src = node.tagName === 'IMG' ? (node.src || '') : '';
      imgPreview.className = imgPreview.src ? 'ie-has-src' : '';
      imgUpLabel.style.display = 'flex';
    } else {
      // html type
      modalTextarea.value = node.innerHTML;
      modalTextarea.style.display = 'block';
    }

    modalOverlay.classList.add('ie-open');
  }

  function closeModal() {
    modalOverlay.classList.remove('ie-open');
    imgFileInput.value = '';
    pendingFile = null;
  }

  // ── Click on editable elements ────────────────────────────────────────────
  document.addEventListener('click', function (e) {
    // Ignore toolbar / modal clicks
    if (toolbar.contains(e.target) || modalOverlay.contains(e.target)) return;

    const el = e.target.closest('[data-inline-key]');
    if (!el) {
      // Click outside - if we had an active text block, commit it
      if (activeNode && activeType === 'text' && activeNode.isContentEditable) {
        commitTextEdit();
      }
      return;
    }

    e.preventDefault();

    const key  = el.dataset.inlineKey;
    const type = el.dataset.inlineType || 'text';

    if (activeNode && activeNode !== el && activeNode.isContentEditable) {
      commitTextEdit();
    }

    activeNode = el;
    activeKey  = key;
    activeType = type;

    el.classList.add('ie-active');

    if (type === 'text') {
      originalText = el.textContent;
      el.setAttribute('contenteditable', 'true');
      el.focus();
      enableSave(true);
      btnCancel.style.display = '';
    } else {
      // html or image - open modal
      openModal(type, key, el);
    }

    e.stopPropagation();
  }, true);

  // ── Text contenteditable change ───────────────────────────────────────────
  document.addEventListener('input', function (e) {
    if (activeNode && activeNode.contains(e.target) && activeType === 'text') {
      enableSave(true);
    }
  });

  function commitTextEdit() {
    if (!activeNode) return;
    activeNode.removeAttribute('contenteditable');
    activeNode.classList.remove('ie-active');
  }

  // ── Image file picker ─────────────────────────────────────────────────────
  imgFileInput.addEventListener('change', function () {
    const file = imgFileInput.files[0];
    if (!file) return;
    pendingFile = file;
    imgPreview.src = URL.createObjectURL(file);
    imgPreview.className = 'ie-has-src';
    imgPreview.style.display = 'block';
    btnModalSave.disabled = false;
  });

  // Drag & drop on the label
  imgUpLabel.addEventListener('dragover', e => { e.preventDefault(); });
  imgUpLabel.addEventListener('drop', e => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file) {
      imgFileInput.files = e.dataTransfer.files;
      imgFileInput.dispatchEvent(new Event('change'));
    }
  });

  // ── Modal save ────────────────────────────────────────────────────────────
  btnModalSave.addEventListener('click', function () {
    if (!activeNode) return;
    btnModalSave.disabled = true;

    if (activeType === 'image') {
      if (!pendingFile) { closeModal(); return; }
      uploadImage(activeKey, pendingFile, activeNode);
    } else {
      // html type
      const content = modalTextarea.value;
      saveBlock(activeKey, content, function (ok) {
        if (ok) {
          activeNode.innerHTML = content;
          closeModal();
          clearActiveNode();
        } else {
          btnModalSave.disabled = false;
        }
      });
    }
  });

  btnModalCancel.addEventListener('click', closeModal);
  btnModalX.addEventListener('click', closeModal);
  modalOverlay.addEventListener('click', function (e) {
    if (e.target === modalOverlay) closeModal();
  });

  // ── Toolbar save ──────────────────────────────────────────────────────────
  btnSave.addEventListener('click', function () {
    if (!activeNode || activeType !== 'text') return;
    commitTextEdit();
    const content = activeNode.textContent;
    btnSave.disabled = true;
    setStatus('Ukládám…');
    saveBlock(activeKey, content, function (ok) {
      if (ok) {
        setStatus('Uloženo ✓');
        clearActiveNode();
        setTimeout(clearStatus, 2500);
      } else {
        enableSave(true);
      }
    });
  });

  btnCancel.addEventListener('click', function () {
    if (!activeNode) return;
    if (activeType === 'text' && originalText !== null) {
      activeNode.textContent = originalText;
    }
    commitTextEdit();
    clearActiveNode();
    clearStatus();
  });

  function clearActiveNode() {
    if (activeNode) {
      activeNode.removeAttribute('contenteditable');
      activeNode.classList.remove('ie-active');
    }
    activeNode   = null;
    activeKey    = null;
    activeType   = null;
    originalText = null;
    enableSave(false);
    btnCancel.style.display = 'none';
  }

  // ── API calls ─────────────────────────────────────────────────────────────
  function saveBlock(key, content, cb) {
    const body = new FormData();
    body.append('_csrf',     csrfToken);
    body.append('page_id',   pageId);
    body.append('block_key', key);
    body.append('content',   content);

    fetch('/admin/inline/update', { method: 'POST', body })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          cb(true);
        } else {
          setStatus(data.error || 'Chyba.', true);
          cb(false);
        }
      })
      .catch(() => { setStatus('Síťová chyba.', true); cb(false); });
  }

  function uploadImage(key, file, imgNode) {
    const body = new FormData();
    body.append('_csrf',     csrfToken);
    body.append('page_id',   pageId);
    body.append('block_key', key);
    body.append('file',      file);

    setStatus('Nahrávám…');
    fetch('/admin/inline/upload', { method: 'POST', body })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          if (imgNode.tagName === 'IMG') imgNode.src = data.url;
          closeModal();
          clearActiveNode();
          setStatus('Uloženo ✓');
          setTimeout(clearStatus, 2500);
        } else {
          setStatus(data.error || 'Chyba.', true);
          btnModalSave.disabled = false;
        }
      })
      .catch(() => { setStatus('Síťová chyba.', true); btnModalSave.disabled = false; });
  }

  // ── ESC closes modal / cancels text edit ──────────────────────────────────
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (modalOverlay.classList.contains('ie-open')) {
        closeModal();
        return;
      }
      if (activeNode) {
        btnCancel.click();
      }
    }
  });
})();
