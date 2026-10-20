/* Shared mailbox UI: menu badge, reply form (autosave, attachments, templates), "already answered" banner and confirm modal. */
(function () {
  'use strict';

  const POLL_BADGE_MS = 60000;
  const POLL_THREAD_MS = 20000;
  const AUTOSAVE_MS = 1500;

  const store = {
    get(key) { try { return window.localStorage.getItem(key) || ''; } catch (e) { return ''; } },
    set(key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* storage may be blocked */ } },
  };

  const formatUtc = value => {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T') + 'Z');
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('cs-CZ', { dateStyle: 'medium', timeStyle: 'short' });
  };

  // ---------------------------------------------------------------- menu badge + polling

  const badge = document.querySelector('[data-mail-badge]');
  const applyCounters = counters => {
    if (!badge || !counters) return;
    const awaiting = Number(counters.awaiting) || 0;
    const unread = Number(counters.unread) || 0;
    const value = awaiting > 0 ? awaiting : unread;
    badge.hidden = value <= 0;
    badge.textContent = value > 0 ? String(value) : '';
    badge.classList.toggle('nav-badge--alert', awaiting > 0);
    badge.title = 'Čeká na odpověď: ' + awaiting + ', nepřečtené: ' + unread;
  };

  const poll = async (params) => {
    try {
      const response = await fetch('/admin/mail/poll?' + new URLSearchParams(params).toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (!response.ok) return null;
      return await response.json();
    } catch (e) {
      return null;
    }
  };

  const form = document.getElementById('mail-form');

  if (badge && !form) {
    const tick = async () => {
      if (document.hidden) return;
      const data = await poll({});
      if (data && data.ok) applyCounters(data.counters);
    };
    tick();
    window.setInterval(tick, POLL_BADGE_MS);
  }

  if (!form) return;

  // ---------------------------------------------------------------- reply / compose form

  const q = selector => form.querySelector(selector);
  const fields = { to: q('[name=to]'), cc: q('[name=cc]'), bcc: q('[name=bcc]'), subject: q('[name=subject]'), body: q('[name=body]'), author: q('[name=author]') };
  const statusEl = q('[data-mail-status]');
  const noticeEl = q('[data-mail-notice]');
  const sendBtn = q('[data-mail-send]');
  const saveBtn = q('[data-mail-save]');
  const discardBtn = q('[data-mail-discard]');
  const attachmentList = q('[data-mail-attachments]');
  const modal = document.querySelector('[data-mail-modal]');
  const csrf = form.dataset.csrf || '';

  const state = {
    draftId: form.dataset.draftId || '',
    version: form.dataset.draftVersion || '',
    threadId: form.dataset.threadId || '',
    knownInbound: form.dataset.knownInbound || '0',
    dirty: false,
    saving: false,
    resave: false,
    sending: false,
    timer: null,
    lastSignature: '',
  };

  fields.author.value = store.get('mailAuthor');
  fields.author.addEventListener('change', () => store.set('mailAuthor', fields.author.value.trim()));

  const setStatus = text => { if (statusEl) statusEl.textContent = text; };
  const showNotice = (text, kind) => {
    noticeEl.hidden = !text;
    noticeEl.className = 'mail-form__notice' + (kind ? ' mail-form__notice--' + kind : '');
    noticeEl.textContent = text || '';
  };

  const post = async (url, data) => {
    const response = await fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' },
      body: data,
    });
    let json = null;
    try { json = await response.json(); } catch (e) { /* non-JSON error page */ }
    return { status: response.status, ok: response.ok, json: json || {} };
  };

  const collect = () => {
    const data = new URLSearchParams();
    Object.keys(fields).forEach(key => data.set(key, fields[key].value));
    data.set('thread_id', state.threadId);
    data.set('draft_id', state.draftId);
    data.set('version', state.version);
    return data;
  };

  const hasContent = () => ['to', 'subject', 'body'].some(key => fields[key].value.trim() !== '');

  const markDirty = () => {
    state.dirty = true;
    window.clearTimeout(state.timer);
    state.timer = window.setTimeout(() => saveDraft(), AUTOSAVE_MS);
  };
  ['to', 'cc', 'bcc', 'subject', 'body'].forEach(key => fields[key].addEventListener('input', markDirty));

  const showConflict = remote => {
    noticeEl.hidden = false;
    noticeEl.className = 'mail-form__notice mail-form__notice--warn';
    noticeEl.textContent = '';
    const text = document.createElement('span');
    text.textContent = 'Koncept mezitím upravil(a) ' + (remote.author || 'někdo jiný') + (remote.updated_at ? ' (' + remote.updated_at + ')' : '') + '. ';
    const load = document.createElement('button');
    load.type = 'button'; load.className = 'btn btn-sm btn-secondary'; load.textContent = 'Načíst jeho verzi';
    load.addEventListener('click', () => {
      fields.to.value = remote.to; fields.cc.value = remote.cc; fields.bcc.value = remote.bcc;
      fields.subject.value = remote.subject; fields.body.value = remote.body;
      state.draftId = String(remote.id); state.version = String(remote.version); state.dirty = false;
      showNotice('');
    });
    const overwrite = document.createElement('button');
    overwrite.type = 'button'; overwrite.className = 'btn btn-sm btn-danger'; overwrite.textContent = 'Přepsat mojí verzí';
    overwrite.addEventListener('click', () => { state.draftId = String(remote.id); state.version = String(remote.version); showNotice(''); saveDraft({ force: true }); });
    noticeEl.append(text, load, ' ', overwrite);
  };

  async function saveDraft(options) {
    const force = !!(options && options.force);
    window.clearTimeout(state.timer);
    if (state.sending || (!force && !hasContent())) return state.draftId || null;
    if (state.saving) { state.resave = true; return state.draftId || null; }
    state.saving = true;
    setStatus('Ukládám koncept…');
    try {
      const data = collect();
      if (force) data.set('force', '1');
      const result = await post('/admin/mail/draft/save', data);
      if (result.ok && result.json.ok) {
        state.draftId = String(result.json.id);
        state.version = String(result.json.version);
        state.dirty = false;
        showNotice('');
        setStatus('Koncept uložen ' + result.json.saved_at);
        if (discardBtn) discardBtn.hidden = false;
      } else if (result.status === 409 && result.json.draft) {
        setStatus('Koncept nebyl uložen - konflikt verzí.');
        showConflict(result.json.draft);
        return null;
      } else if (result.status === 410) {
        state.draftId = ''; state.version = '';
        if (attachmentList) attachmentList.innerHTML = '';
        setStatus('Koncept byl mezitím odeslán nebo smazán.');
        return null;
      } else {
        setStatus('');
        showNotice(result.json.error || 'Koncept se nepodařilo uložit.', 'error');
        return null;
      }
    } catch (e) {
      setStatus('Koncept se nepodařilo uložit (výpadek spojení).');
      return null;
    } finally {
      state.saving = false;
      if (state.resave) { state.resave = false; markDirty(); }
    }
    return state.draftId;
  }

  if (saveBtn) saveBtn.addEventListener('click', () => saveDraft());

  if (discardBtn) {
    discardBtn.addEventListener('click', async () => {
      if (!window.confirm('Opravdu zahodit koncept včetně příloh?')) return;
      if (state.draftId) {
        const data = new URLSearchParams({ draft_id: state.draftId });
        await post('/admin/mail/draft/delete', data);
      }
      state.dirty = false;
      window.location.href = state.threadId ? '/admin/mail/thread?id=' + encodeURIComponent(state.threadId) : '/admin/mail';
    });
  }

  // ---------------------------------------------------------------- template + cc toggle

  const templateSelect = q('[data-mail-template]');
  if (templateSelect) {
    let templates = {};
    try { templates = JSON.parse(document.querySelector('[data-mail-templates]').textContent || '{}'); } catch (e) { templates = {}; }
    templateSelect.addEventListener('change', () => {
      const text = templates[templateSelect.value];
      templateSelect.value = '';
      if (typeof text !== 'string') return;
      const area = fields.body;
      const start = area.selectionStart || 0;
      area.value = area.value.slice(0, start) + text + area.value.slice(area.selectionEnd || start);
      area.focus();
      area.setSelectionRange(start + text.length, start + text.length);
      markDirty();
    });
  }

  const toggle = q('[data-mail-toggle]');
  if (toggle) {
    toggle.addEventListener('click', () => {
      form.querySelectorAll('[data-mail-row=' + toggle.dataset.mailToggle + ']').forEach(row => { row.hidden = false; });
      toggle.hidden = true;
      fields.cc.focus();
    });
  }

  // ---------------------------------------------------------------- attachments

  const addAttachmentItem = attachment => {
    const item = document.createElement('li');
    item.dataset.attachmentId = String(attachment.id);
    const label = document.createElement('span');
    label.textContent = attachment.filename + ' ';
    const size = document.createElement('small');
    size.textContent = '(' + attachment.size_label + ')';
    label.appendChild(size);
    const remove = document.createElement('button');
    remove.type = 'button'; remove.className = 'btn-link'; remove.textContent = 'Odebrat';
    remove.setAttribute('data-attachment-remove', '');
    item.append(label, remove);
    attachmentList.appendChild(item);
  };

  if (attachmentList) {
    attachmentList.addEventListener('click', async event => {
      const button = event.target.closest('[data-attachment-remove]');
      if (!button) return;
      const item = button.closest('li');
      const result = await post('/admin/mail/attachment/delete', new URLSearchParams({ id: item.dataset.attachmentId }));
      if (result.ok) item.remove(); else showNotice('Přílohu se nepodařilo odebrat.', 'error');
    });
  }

  const fileInput = q('[data-mail-file]');
  if (fileInput) {
    fileInput.addEventListener('change', async () => {
      const files = Array.from(fileInput.files || []);
      fileInput.value = '';
      if (files.length === 0) return;
      await saveDraft(); // persist what has been typed so far, then attach to that draft
      const draftId = state.draftId || await ensureEmptyDraft();
      if (!draftId) { showNotice('Přílohu nelze přidat, dokud se nepodaří uložit koncept.', 'error'); return; }
      const max = Number(form.dataset.maxAttachment) || 0;
      for (const file of files) {
        if (max && file.size > max) { showNotice('Soubor ' + file.name + ' je větší než povolený limit.', 'error'); continue; }
        setStatus('Nahrávám ' + file.name + '…');
        const data = new FormData();
        data.set('file', file);
        data.set('draft_id', state.draftId);
        const result = await post('/admin/mail/attachment/upload', data);
        if (result.ok && result.json.ok) { addAttachmentItem(result.json.attachment); showNotice(''); setStatus('Příloha přidána.'); }
        else showNotice(result.json.error || 'Přílohu se nepodařilo nahrát.', 'error');
      }
    });
  }

  async function ensureEmptyDraft() {
    // A draft needs some content to be stored; attaching to an empty form creates a placeholder body-less draft.
    const data = collect();
    data.set('subject', fields.subject.value || '(bez předmětu)');
    const result = await post('/admin/mail/draft/save', data);
    if (result.ok && result.json.ok) { state.draftId = String(result.json.id); state.version = String(result.json.version); return state.draftId; }
    if (result.status === 409 && result.json.draft) showConflict(result.json.draft);
    return null;
  }

  // ---------------------------------------------------------------- sending + confirm modal

  const closeModal = () => { modal.hidden = true; sendBtn.disabled = false; sendBtn.focus(); };

  const showConflictModal = data => {
    const body = modal.querySelector('[data-mail-modal-body]');
    body.textContent = '';
    (data.reasons || []).forEach(reason => {
      const p = document.createElement('p');
      if (reason === 'already_replied') {
        const o = data.outbound || {};
        p.textContent = (o.in_progress ? 'Právě se odesílá odpověď od: ' : 'Odpověď už odeslal(a): ') + (o.author || 'někdo z týmu') + (o.sent_at ? ' (' + formatUtc(o.sent_at) + ')' : '') + '.';
        body.appendChild(p);
        if (o.excerpt) {
          const quote = document.createElement('blockquote');
          quote.textContent = o.excerpt;
          body.appendChild(quote);
        }
      } else if (reason === 'new_inbound') {
        p.textContent = 'V konverzaci mezitím přišla nová zpráva od klienta - možná na ni reagujete jinak, než jste psal(a).';
        body.appendChild(p);
      }
    });
    const ask = document.createElement('p');
    ask.className = 'mail-modal__ask';
    ask.textContent = 'Opravdu chcete přesto odeslat svoji zprávu?';
    body.appendChild(ask);
    state.lastSignature = data.signature || '';
    modal.hidden = false;
    modal.querySelector('[data-mail-modal-cancel]').focus();
  };

  async function doSend(confirmSignature) {
    state.sending = true;
    window.clearTimeout(state.timer);
    sendBtn.disabled = true;
    setStatus('Odesílám…');
    const data = collect();
    data.set('known_inbound_id', state.knownInbound);
    if (confirmSignature) data.set('confirm_signature', confirmSignature);
    let result;
    try {
      result = await post('/admin/mail/send', data);
    } catch (e) {
      state.sending = false; sendBtn.disabled = false; setStatus('');
      showNotice('Odeslání se nepodařilo (výpadek spojení). Zpráva nebyla odeslána, koncept zůstává.', 'error');
      return;
    }
    if (result.ok && result.json.ok) {
      state.dirty = false;
      window.location.href = result.json.redirect || '/admin/mail';
      return;
    }
    state.sending = false;
    setStatus('');
    if (result.status === 409 && result.json.status === 'conflict') {
      showConflictModal(result.json);
      if (result.json.last_inbound_id) state.knownInbound = String(result.json.last_inbound_id);
      return;
    }
    sendBtn.disabled = false;
    showNotice(result.json.error || 'Zprávu se nepodařilo odeslat.', 'error');
  }

  form.addEventListener('submit', event => {
    event.preventDefault();
    if (state.sending) return;
    if (!form.reportValidity()) return;
    if (!fields.author.value.trim()) {
      const name = window.prompt('Jak se jmenujete? Jméno se zobrazí kolegům u vaší odpovědi.', store.get('mailAuthor'));
      if (name && name.trim()) { fields.author.value = name.trim(); store.set('mailAuthor', fields.author.value); }
    }
    showNotice('');
    doSend('');
  });

  modal.querySelector('[data-mail-modal-cancel]').addEventListener('click', closeModal);
  modal.querySelector('[data-mail-modal-confirm]').addEventListener('click', () => { modal.hidden = true; doSend(state.lastSignature); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.hidden) closeModal(); });

  // ---------------------------------------------------------------- live banner

  const banner = document.querySelector('[data-reply-banner]');
  const newBanner = document.querySelector('[data-new-inbound-banner]');
  const renderBanner = thread => {
    if (!banner || !thread) return;
    banner.hidden = !thread.answered;
    const detail = banner.querySelector('[data-banner-detail]');
    if (thread.answered && thread.outbound && detail) {
      detail.textContent = '';
      const who = document.createElement('span');
      who.textContent = thread.outbound.author + ' odpověděl(a) ' + thread.outbound.sent_at + (thread.outbound.in_progress ? ' (právě se odesílá)' : '') + ':';
      const quote = document.createElement('blockquote');
      quote.textContent = thread.outbound.excerpt;
      detail.append(who, quote);
    }
    if (newBanner) newBanner.hidden = !thread.new_inbound;
  };

  if (state.threadId) {
    const tick = async () => {
      if (document.hidden || state.sending) return;
      const data = await poll({ thread: state.threadId, known_inbound: state.knownInbound });
      if (data && data.ok) { applyCounters(data.counters); renderBanner(data.thread); }
    };
    window.setInterval(tick, POLL_THREAD_MS);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
  } else if (badge) {
    const tick = async () => { if (!document.hidden) { const data = await poll({}); if (data && data.ok) applyCounters(data.counters); } };
    tick();
    window.setInterval(tick, POLL_BADGE_MS);
  }

  // ---------------------------------------------------------------- leaving the page

  window.addEventListener('beforeunload', event => {
    if (state.dirty && !state.sending) { event.preventDefault(); event.returnValue = ''; }
  });
  window.addEventListener('pagehide', () => {
    if (state.dirty && !state.sending && hasContent() && navigator.sendBeacon) {
      const data = collect();
      data.set('_csrf', csrf);
      navigator.sendBeacon('/admin/mail/draft/save', data);
    }
  });
})();
