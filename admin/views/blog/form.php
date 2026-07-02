<?php
$isEdit    = $post !== null;
$pageTitle = $isEdit ? 'Upravit clanek' : 'Novy clanek';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
$publicMediaUrl = static function (?string $path): string {
    $normalized = str_replace('\\', '/', trim((string)$path));
    if ($normalized === '') return '';
    if (preg_match('~https?://[^/]+(/storage/media/.*)$~i', $normalized, $m)) {
        return '/media/' . ltrim(substr($m[1], strlen('/storage/media/')), '/');
    }
    if (str_starts_with($normalized, '/media/')) return $normalized;
    if (str_starts_with($normalized, '/storage/media/')) return '/media/' . ltrim(substr($normalized, strlen('/storage/media/')), '/');
    if (str_starts_with($normalized, 'storage/media/')) return '/media/' . ltrim(substr($normalized, strlen('storage/media/')), '/');
    return '/' . ltrim($normalized, '/');
};
$selectedFeaturedId = (int)($post['featured_image_id'] ?? 0);
$selectedGallery    = array_map('intval', $gallery ?? []);
ob_start();
?>
<div class="page-header">
  <h1><?= $pageTitle ?></h1>
  <a href="/admin/blog" class="btn btn-secondary">&larr; Zpet</a>
</div>

<form method="POST" action="<?= $isEdit ? '/admin/blog/update' : '/admin/blog/store' ?>" id="blog-form">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$post['id'] ?>"><?php endif; ?>

  <div data-tabs-container>
    <div class="tabs">
      <button type="button" class="tab-btn" data-tab="content">Obsah</button>
      <button type="button" class="tab-btn" data-tab="media">Média</button>
      <button type="button" class="tab-btn" data-tab="meta">SEO &amp; Meta</button>
    </div>

    <div class="tab-pane" id="tab-content">
      <div class="card">
        <div class="form-group">
          <label for="blog-title">Nazev clanku *</label>
          <input type="text" id="blog-title" name="title" value="<?= $h($post['title'] ?? '') ?>" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="blog-slug">Slug</label>
            <input type="text" id="blog-slug" name="slug" value="<?= $h($post['slug'] ?? '') ?>">
            <div class="form-hint">Vyplni se automaticky z nazvu</div>
          </div>
          <div class="form-group">
            <label>Stav</label>
            <select name="status">
              <option value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Koncept</option>
              <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Publikovano</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="blog-excerpt">Perex <small style="font-weight:400;color:var(--c-muted)">(kratky popis pro vypis)</small></label>
          <textarea name="excerpt" id="blog-excerpt" style="min-height:80px"><?= $h($post['excerpt'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="card">
        <div class="card-title">Text clanku</div>
        <div class="article-editor-wrap">
          <div class="article-editor-toolbar" id="ae-toolbar">
            <button type="button" class="ae-btn ae-sep-after" data-cmd="formatBlock" data-val="<h2>" title="Nadpis 2">H2</button>
            <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<h3>" title="Nadpis 3">H3</button>
            <span class="ae-sep"></span>
            <button type="button" class="ae-btn" data-cmd="bold" title="Tucne"><strong>B</strong></button>
            <button type="button" class="ae-btn" data-cmd="italic" title="Kurziva"><em>I</em></button>
            <button type="button" class="ae-btn" data-cmd="underline" title="Podtrzeni"><u>U</u></button>
            <span class="ae-sep"></span>
            <button type="button" class="ae-btn" data-cmd="insertUnorderedList" title="Odrzky">&#8226; ul</button>
            <button type="button" class="ae-btn" data-cmd="insertOrderedList" title="Cislovany seznam">1. ol</button>
            <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<blockquote>" title="Citace">&#10077;</button>
            <span class="ae-sep"></span>
            <button type="button" class="ae-btn" data-cmd="createLink" title="Vlozit odkaz">&#128279; Link</button>
            <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<p>" title="Odstavec">&para;</button>
            <span class="ae-sep"></span>
            <button type="button" class="ae-btn ae-source-btn" data-cmd="source" title="HTML zdrojovy kod">&lt;/&gt; HTML</button>
          </div>
          <div id="article-editor" contenteditable="true"><?= $post['content'] ?? '' ?></div>
          <textarea id="article-source" style="display:none" class="code" spellcheck="false"></textarea>
        </div>
        <input type="hidden" name="content" id="article-content-hidden" value="<?= $h($post['content'] ?? '') ?>">
      </div>
    </div>

    <div class="tab-pane" id="tab-media">
      <?php if (!empty($media)): ?>
      <!-- ── Hlavní obrázek ─────────────────────────────── -->
      <div class="card">
        <div class="card-title">Hlavni obrazek</div>
        <p style="font-size:13px;color:var(--c-muted);margin-bottom:12px">
          Kliknete na obrazek pro vyber hlavniho obrazku clanku. Opakovanym kliknutim odeberete.
          <span id="fi-status" style="font-weight:600;color:var(--c-primary)">
            <?= $selectedFeaturedId > 0 ? '1 vybran' : 'bez obrazku' ?>
          </span>
        </p>
        <input type="hidden" name="featured_image_id" id="fi-input" value="<?= $selectedFeaturedId > 0 ? $selectedFeaturedId : '' ?>">
        <div class="media-grid" id="fi-grid">
          <?php foreach ($media as $m): ?>
          <?php $mediaId = (int)$m['id']; ?>
          <div class="media-card fi-item <?= $selectedFeaturedId === $mediaId ? 'fi-selected' : '' ?>"
               data-media-id="<?= $mediaId ?>"
               data-media-src="<?= $h($publicMediaUrl((string)($m['storage_path'] ?? ''))) ?>"
               data-media-name="<?= $h($m['original_filename']) ?>"
               style="cursor:pointer">
            <img src="<?= $h($publicMediaUrl((string)($m['storage_path'] ?? ''))) ?>" alt="<?= $h($m['alt_text']) ?>" loading="lazy" style="height:90px;object-fit:cover;display:block;width:100%">
            <div class="media-card-info" style="display:flex;justify-content:space-between;padding:5px 8px">
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $h($m['original_filename']) ?></span>
              <span class="fi-check" style="flex-shrink:0;font-size:14px;color:#16a34a">&#10003;</span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div id="fi-preview-wrap" style="display:<?= $selectedFeaturedId > 0 ? 'flex' : 'none' ?>;gap:12px;align-items:center;margin-top:12px">
          <img id="fi-preview" src="" alt="" style="height:64px;width:88px;object-fit:cover;border-radius:6px;display:block;border:1px solid var(--c-border)">
          <div style="display:flex;flex-direction:column;gap:6px">
            <div id="fi-preview-name" style="font-size:13px;color:var(--c-text);font-weight:600"></div>
            <button type="button" id="fi-clear" class="btn btn-secondary" style="width:max-content">Odebrat obrazek</button>
          </div>
        </div>
      </div>

      <!-- ── Galerie ─────────────────────────────────────── -->
      <div class="card">
        <div class="card-title">Galerie</div>
        <p style="font-size:13px;color:var(--c-muted);margin-bottom:12px">
          Vyberte dalsi obrazky pro galerii clanku. Kliknete pro vybrani/odebrání.
          <span id="gl-status" style="font-weight:600;color:var(--c-primary)">
            <?= count($selectedGallery) > 0 ? count($selectedGallery) . ' vybrano' : 'zadne' ?>
          </span>
        </p>
        <div id="gallery-ids-wrap"></div>
        <div class="media-grid" id="gl-grid">
          <?php foreach ($media as $m): ?>
          <?php $mediaId = (int)$m['id']; ?>
          <div class="media-card gl-item <?= in_array($mediaId, $selectedGallery, true) ? 'gl-selected' : '' ?>"
               data-media-id="<?= $mediaId ?>"
               style="cursor:pointer">
            <img src="<?= $h($publicMediaUrl((string)($m['storage_path'] ?? ''))) ?>" alt="<?= $h($m['alt_text']) ?>" loading="lazy" style="height:90px;object-fit:cover;display:block;width:100%">
            <div class="media-card-info" style="display:flex;justify-content:space-between;padding:5px 8px">
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $h($m['original_filename']) ?></span>
              <span class="gl-check" style="flex-shrink:0;font-size:14px;color:#16a34a">&#10003;</span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="card">
        <p style="color:var(--c-muted)">Nahrajte nejprve média v sekci <a href="/admin/media">Média</a>.</p>
      </div>
      <?php endif; ?>
    </div>

    <div class="tab-pane" id="tab-meta">
      <div class="card">
        <div class="form-group">
          <label>Meta title</label>
          <input type="text" name="meta_title" value="<?= $h($post['meta_title'] ?? '') ?>">
          <div class="form-hint">Pokud prazdne, pouzije se nazev clanku</div>
        </div>
        <div class="form-group">
          <label>Meta description</label>
          <textarea name="meta_description" style="min-height:80px"><?= $h($post['meta_description'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <div style="margin-top:16px;display:flex;gap:8px">
    <button type="submit" class="btn btn-primary">Ulozit clanek</button>
    <a href="/admin/blog" class="btn btn-secondary">Zrusit</a>
  </div>
</form>

<style>
.fi-item,.gl-item { transition: box-shadow .15s, outline .1s; outline: 3px solid transparent; }
.fi-item .fi-check,.gl-item .gl-check { display: none; }
.fi-item.fi-selected,.gl-item.gl-selected { outline-color: #6366f1; }
.fi-item.fi-selected .fi-check,.gl-item.gl-selected .gl-check { display: inline; }
</style>

<script>
// ── Hlavni obrazek ────────────────────────────────────────────────────────────
(function() {
  const grid = document.getElementById('fi-grid');
  const input = document.getElementById('fi-input');
  const status = document.getElementById('fi-status');
  const previewWrap = document.getElementById('fi-preview-wrap');
  const preview = document.getElementById('fi-preview');
  const previewName = document.getElementById('fi-preview-name');
  const clearBtn = document.getElementById('fi-clear');
  if (!grid || !input) return;

  let selectedId = input.value ? parseInt(input.value, 10) : null;

  function render() {
    grid.querySelectorAll('.fi-item').forEach(item => {
      item.classList.toggle('fi-selected', selectedId === parseInt(item.dataset.mediaId, 10));
    });
    const sel = selectedId ? grid.querySelector('.fi-item[data-media-id="' + selectedId + '"]') : null;
    input.value = selectedId ? String(selectedId) : '';
    if (sel) {
      if (status) status.textContent = '1 vybran';
      if (previewWrap) previewWrap.style.display = 'flex';
      if (preview) preview.src = sel.dataset.mediaSrc || '';
      if (previewName) previewName.textContent = sel.dataset.mediaName || '';
    } else {
      if (status) status.textContent = 'bez obrazku';
      if (previewWrap) previewWrap.style.display = 'none';
      if (preview) preview.src = '';
      if (previewName) previewName.textContent = '';
    }
  }

  grid.querySelectorAll('.fi-item').forEach(item => {
    item.addEventListener('click', () => {
      const id = parseInt(item.dataset.mediaId, 10);
      selectedId = selectedId === id ? null : id;
      render();
    });
  });
  if (clearBtn) clearBtn.addEventListener('click', () => { selectedId = null; render(); });
  render();
})();

// ── Galerie (multi-select) ────────────────────────────────────────────────────
(function() {
  const grid   = document.getElementById('gl-grid');
  const wrap   = document.getElementById('gallery-ids-wrap');
  const status = document.getElementById('gl-status');
  if (!grid || !wrap) return;

  const initial = <?= json_encode($selectedGallery) ?>;
  let selected  = [...initial];

  function syncInputs() {
    wrap.innerHTML = '';
    selected.forEach(id => {
      const inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'gallery_ids[]'; inp.value = id;
      wrap.appendChild(inp);
    });
    if (status) status.textContent = selected.length ? selected.length + ' vybrano' : 'zadne';
  }

  grid.querySelectorAll('.gl-item').forEach(item => {
    item.addEventListener('click', () => {
      const id = parseInt(item.dataset.mediaId, 10);
      const idx = selected.indexOf(id);
      if (idx === -1) { selected.push(id); item.classList.add('gl-selected'); }
      else            { selected.splice(idx, 1); item.classList.remove('gl-selected'); }
      syncInputs();
    });
  });

  syncInputs();
})();

// ── Slug z nazvu ──────────────────────────────────────────────────────────────
(function() {
  const title = document.getElementById('blog-title');
  const slug  = document.getElementById('blog-slug');
  if (!title || !slug || slug.value) return;
  title.addEventListener('input', () => { slug.value = slugify(title.value); });
  function slugify(str) {
    return str.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^a-z0-9\s-]/g,'').trim().replace(/\s+/g,'-').replace(/-+/g,'-');
  }
})();
</script>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
