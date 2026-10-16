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
$galleryMedia       = $galleryMedia ?? [];
$selectedGallery    = array_map('intval', array_column($galleryMedia, 'id'));
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
          <div class="form-group">
            <label for="blog-sort">Poradi</label>
            <input type="number" id="blog-sort" name="sort_order" value="<?= (int)($post['sort_order'] ?? 0) ?>" min="0" max="1000000" step="1" style="max-width:90px">
            <div class="form-hint">1 = prvni. 0 znamena bez rucniho poradi - takovy clanek zustane razeny podle data, za ocislovanymi.</div>
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
      <?php if (true): ?>
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
        <button type="button" class="btn btn-secondary" id="fi-modal-open"><?= $selectedFeaturedId ? 'Změnit obrázek' : 'Vybrat obrázek' ?></button>
        <div class="media-grid" id="fi-grid" hidden>
          <?php foreach (array_filter([$featuredMedia ?? null]) as $m): ?>
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
          Cislo u fotky urcuje poradi v galerii (nizsi = driv); pri stejnem cisle rozhodne poradi vyberu.
          <span id="gl-status" style="font-weight:600;color:var(--c-primary)">
            <?= count($selectedGallery) > 0 ? count($selectedGallery) . ' vybrano' : 'zadne' ?>
          </span>
        </p>
        <div id="gallery-ids-wrap"></div>
        <button type="button" class="btn btn-secondary" id="gl-modal-open"><?= $selectedGallery ? 'Změnit obrázky' : 'Vybrat obrázky' ?></button>
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
let blogGallerySelection = null;
// admin.js is loaded at the end of the layout, after this script, so the shared
// renderer only exists once the document is parsed.
document.addEventListener('DOMContentLoaded', () => {
blogGallerySelection = window.AdminMediaSelection.mount({
  container: document.getElementById('gallery-ids-wrap'),
  idsName: 'gallery_ids',
  sortName: 'gallery_sort',
  items: <?= json_encode(array_map(static fn($m) => [
      'id' => (int)$m['id'],
      'name' => (string)($m['original_filename'] ?? ''),
      'thumb' => $publicMediaUrl((string)($m['thumb_path'] ?? $m['storage_path'] ?? '')),
      'sort' => (int)($m['bm_sort'] ?? 0),
  ], $galleryMedia)) ?>,
  onChange: items => {
    const status = document.getElementById('gl-status');
    if (status) status.textContent = items.length ? items.length + ' vybrano' : 'zadne';
    document.getElementById('gl-modal-open').textContent = items.length ? 'Změnit obrázky' : 'Vybrat obrázky';
  },
});
});
const blogPickerInitial = {
  featured: <?= json_encode($featuredMedia ? [['id'=>(int)$featuredMedia['id'],'name'=>(string)($featuredMedia['original_filename']??''),'thumb'=>$publicMediaUrl((string)($featuredMedia['thumb_path']??$featuredMedia['storage_path']??''))]] : []) ?>,
};
document.getElementById('fi-modal-open').addEventListener('click', function(){
  AdminMediaPicker.open({mode:'single',imagesOnly:true,selected:blogPickerInitial.featured,onConfirm:list=>{const item=list[0];document.getElementById('fi-input').value=item?item.id:'';blogPickerInitial.featured=list;this.textContent=item?'Změnit obrázek':'Vybrat obrázek';}});
});
document.getElementById('gl-modal-open').addEventListener('click', function(){
  if (!blogGallerySelection) return;
  AdminMediaPicker.open({mode:'multiple',imagesOnly:true,selected:blogGallerySelection.getItems(),onConfirm:list=>blogGallerySelection.setItems(list)});
});
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

// Slug z nazvu (jen dokud ho uzivatel sam nevyplnil)
(function () {
  const title = document.getElementById('blog-title');
  const slug  = document.getElementById('blog-slug');
  if (!title || !slug || slug.value) return;
  title.addEventListener('input', () => {
    const map = {'á':'a','č':'c','ď':'d','é':'e','ě':'e','í':'i','ň':'n','ó':'o','ř':'r','š':'s','ť':'t','ú':'u','ů':'u','ý':'y','ž':'z'};
    slug.value = title.value.toLowerCase()
      .replace(/[áčďéěíňóřšťúůýž]/g, c => map[c] || c)
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  });
})();
</script>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
