<?php
$isEdit    = $entry !== null;
$pageTitle = $isEdit ? 'Upravit práci' : 'Nová práce';
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
$selectedMedia = $selectedMedia ?? [];
$videos = array_values(array_filter((array)($videos ?? []), static fn($v): bool => is_array($v) && trim((string)($v['url'] ?? '')) !== ''));
ob_start();
?>
<div class="page-header">
  <h1><?= $pageTitle ?></h1>
  <a href="/admin/portfolio" class="btn btn-secondary">← Zpět</a>
</div>

<form method="POST" action="<?= $isEdit ? '/admin/portfolio/update' : '/admin/portfolio/store' ?>" id="pf-form">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$entry['id'] ?>"><?php endif; ?>

  <div class="card">
    <div class="form-group">
      <label>Název *</label>
      <input type="text" id="pf-title" name="title" value="<?= $h($entry['title'] ?? '') ?>" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Slug</label>
        <input type="text" id="pf-slug" name="slug" value="<?= $h($entry['slug'] ?? '') ?>">
        <div class="form-hint">Vyplní se z názvu</div>
      </div>
      <div class="form-group">
        <label>Stav</label>
        <select name="status">
          <option value="draft"     <?= ($entry['status'] ?? 'draft') === 'draft'     ? 'selected' : '' ?>>Koncept</option>
          <option value="published" <?= ($entry['status'] ?? '') === 'published' ? 'selected' : '' ?>>Publikováno</option>
        </select>
      </div>
      <div class="form-group">
        <label>Pořadí</label>
        <input type="number" name="sort_order" value="<?= (int)($entry['sort_order'] ?? 0) ?>" min="0" max="1000000" style="max-width:80px">
        <div class="form-hint">1 = první. 0 znamená bez ručního pořadí - taková práce zůstane za očíslovanými. Na úvodní stránce se fotky a videa střídají; číslo tam určuje pořadí uvnitř fotek a uvnitř videí.</div>
      </div>
      <div class="form-group">
        <label>Typ obsahu</label>
        <select name="content_type">
          <option value="photo" <?= ($entry['content_type'] ?? $initialContentType ?? 'photo') === 'photo' ? 'selected' : '' ?>>Fotogalerie</option>
          <option value="video" <?= ($entry['content_type'] ?? $initialContentType ?? 'photo') === 'video' ? 'selected' : '' ?>>Videogalerie</option>
        </select>
      </div>
    </div>
  </div>

  <!-- Video URLs -->
  <div class="card">
    <div class="card-title">Videa</div>
    <p style="font-size:13px;color:var(--c-muted);margin-bottom:12px">
      Libovolný počet odkazů na YouTube nebo Vimeo. Číslo vlevo určuje pořadí, 1 = první;
      při stejném čísle rozhodne pořadí řádků. Video, které vyjde první, je hlavní - zobrazí se
      na dlaždici ve výpisu a v náhledovém přehrávači; ostatní se přidají pod něj na detailu.
    </p>
    <div id="pf-video-rows">
      <?php foreach (($videos ?: [['url' => '', 'sort_order' => 0]]) as $videoRowIndex => $video): ?>
      <div class="pf-video-row">
        <div class="pf-video-row__head">
          <?php // Rows arrive ordered; the position is the number, so "1" always means first. ?>
          <input type="number" name="video_sort[]" value="<?= $videoRowIndex + 1 ?>" min="1" max="1000000" step="1"
                 class="pf-video-sort" title="Pořadí (1 = první)" aria-label="Pořadí videa">
          <input type="url" name="video_urls[]" value="<?= $h($video['url'] ?? '') ?>" class="pf-video-input"
                 placeholder="https://www.youtube.com/watch?v=... nebo https://vimeo.com/...">
          <button type="button" class="btn btn-secondary pf-video-up" title="Posunout nahoru" aria-label="Posunout nahoru">&uarr;</button>
          <button type="button" class="btn btn-secondary pf-video-down" title="Posunout dolů" aria-label="Posunout dolů">&darr;</button>
          <button type="button" class="btn btn-secondary pf-video-remove" title="Odebrat" aria-label="Odebrat video">&times;</button>
        </div>
        <div class="pf-video-preview" hidden>
          <iframe src="" frameborder="0" allow="autoplay; fullscreen"></iframe>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-secondary" id="pf-video-add">+ Přidat video</button>
  </div>

  <!-- Description editor -->
  <div class="card">
    <div class="card-title">Popis zakázky</div>
    <div class="article-editor-wrap">
      <div class="article-editor-toolbar">
        <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<h2>">H2</button>
        <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<h3>">H3</button>
        <span class="ae-sep"></span>
        <button type="button" class="ae-btn" data-cmd="bold"><strong>B</strong></button>
        <button type="button" class="ae-btn" data-cmd="italic"><em>I</em></button>
        <span class="ae-sep"></span>
        <button type="button" class="ae-btn" data-cmd="insertUnorderedList">&#8226; ul</button>
        <button type="button" class="ae-btn" data-cmd="insertOrderedList">1. ol</button>
        <span class="ae-sep"></span>
        <button type="button" class="ae-btn ae-source-btn" data-cmd="source">&lt;/&gt;</button>
      </div>
      <div id="article-editor" contenteditable="true" style="min-height:180px"><?= $entry['description'] ?? '' ?></div>
      <textarea id="article-source" style="display:none" class="code" spellcheck="false"></textarea>
    </div>
    <input type="hidden" name="description" id="article-content-hidden" value="<?= $h($entry['description'] ?? '') ?>">
  </div>

  <!-- SEO -->
  <div class="card">
    <div class="card-title">SEO</div>
    <div class="form-row">
      <div class="form-group">
        <label>Meta title</label>
        <input type="text" name="meta_title" value="<?= $h($entry['meta_title'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Meta description</label>
        <input type="text" name="meta_description" value="<?= $h($entry['meta_description'] ?? '') ?>">
      </div>
    </div>
  </div>

  <!-- Gallery picker -->
  <div class="card">
    <div class="card-title">Fotogalerie</div>
    <p style="font-size:13px;color:var(--c-muted);margin-bottom:12px">
      Číslo u fotky určuje pořadí v galerii, 1 = první. Karty jsou seřazené tak, jak fotky uvidí
      návštěvník (první je i náhled práce), a po každé změně se přečíslují od jedničky.
      <span id="pm-count" style="font-weight:600;color:var(--c-primary)">
        <?= count($selectedMedia) ?> vybráno
      </span>
    </p>
    <button type="button" class="btn btn-secondary" id="pm-modal-open"><?= $selectedMedia ? 'Změnit obrázky' : 'Vybrat obrázky' ?></button>
    <div id="pm-hidden"></div>
  </div>

  <div style="margin-top:16px;display:flex;gap:8px">
    <button type="submit" class="btn btn-primary">Uložit práci</button>
    <a href="/admin/portfolio" class="btn btn-secondary">Zrušit</a>
  </div>
</form>

<style>
.pf-video-row { margin-bottom: 12px; }
.pf-video-row__head { display: flex; gap: 6px; align-items: center; }
.pf-video-row__head .pf-video-input { flex: 1 1 auto; min-width: 0; }
.pf-video-row__head .btn { flex: 0 0 auto; padding: 6px 10px; }
.pf-video-preview { position: relative; width: min(100%, 480px); margin-top: 8px; aspect-ratio: 16 / 9; overflow: hidden; border-radius: 8px; background: #000; }
.pf-video-preview[hidden] { display: none; }
.pf-video-preview iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
.pf-video-row__head .pf-video-sort { flex: 0 0 70px; width: 70px; }
</style>

<script>
let portfolioSelection = null;
// admin.js is loaded at the end of the layout, after this script, so the shared
// renderer only exists once the document is parsed.
document.addEventListener('DOMContentLoaded', () => {
portfolioSelection = window.AdminMediaSelection.mount({
  container: document.getElementById('pm-hidden'),
  idsName: 'media_ids',
  sortName: 'media_sort',
  items: <?= json_encode(array_map(static fn($m) => [
      'id' => (int)$m['id'],
      'name' => (string)($m['original_filename'] ?? ''),
      'thumb' => $publicMediaUrl((string)($m['thumb_path'] ?? $m['storage_path'] ?? '')),
      'sort' => (int)($m['pm_sort'] ?? 0),
  ], $selectedMedia)) ?>,
  onChange: items => {
    document.getElementById('pm-count').textContent = items.length + ' vybráno';
    document.getElementById('pm-modal-open').textContent = items.length ? 'Změnit obrázky' : 'Vybrat obrázky';
  },
});
});
document.getElementById('pm-modal-open').addEventListener('click', function () {
  if (!portfolioSelection) return;
  AdminMediaPicker.open({
    mode: 'multiple',
    imagesOnly: true,
    selected: portfolioSelection.getItems(),
    onConfirm: list => portfolioSelection.setItems(list),
  });
});

// Video URLs → repeatable rows with an embed preview each
(function() {
  const rows = document.getElementById('pf-video-rows');
  const add  = document.getElementById('pf-video-add');
  if (!rows || !add) return;

  function getEmbed(url) {
    let m = url.match(/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/)([A-Za-z0-9_-]{11})/);
    if (m) return 'https://www.youtube.com/embed/' + m[1];
    m = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
    if (m) return 'https://player.vimeo.com/video/' + m[1] + '?dnt=1';
    return null;
  }

  function refresh(row) {
    const input   = row.querySelector('.pf-video-input');
    const preview = row.querySelector('.pf-video-preview');
    const frame   = preview.querySelector('iframe');
    const embed   = getEmbed(input.value.trim());
    // The src is cleared as well as hidden, otherwise a removed player keeps loading.
    if (embed) { if (frame.src !== embed) frame.src = embed; preview.hidden = false; }
    else       { frame.src = ''; preview.hidden = true; }
  }

  function refreshAll() {
    rows.querySelectorAll('.pf-video-row').forEach(refresh);
  }

  rows.addEventListener('input', function (event) {
    const row = event.target.closest('.pf-video-row');
    if (row) refresh(row);
  });

  rows.addEventListener('click', function (event) {
    const button = event.target.closest('button');
    const row = event.target.closest('.pf-video-row');
    if (!button || !row) return;
    if (button.classList.contains('pf-video-remove')) {
      // The last row stays as an empty input so there is always somewhere to type.
      if (rows.querySelectorAll('.pf-video-row').length > 1) row.remove();
      else { row.querySelector('.pf-video-input').value = ''; refresh(row); }
    } else if (button.classList.contains('pf-video-up') && row.previousElementSibling) {
      row.parentNode.insertBefore(row, row.previousElementSibling);
    } else if (button.classList.contains('pf-video-down') && row.nextElementSibling) {
      row.parentNode.insertBefore(row.nextElementSibling, row);
    }
  });

  add.addEventListener('click', function () {
    const row = rows.querySelector('.pf-video-row').cloneNode(true);
    row.querySelector('.pf-video-input').value = '';
    row.querySelector('.pf-video-sort').value = String(rows.querySelectorAll('.pf-video-row').length + 1);
    const preview = row.querySelector('.pf-video-preview');
    preview.hidden = true;
    preview.querySelector('iframe').src = '';
    rows.appendChild(row);
    row.querySelector('.pf-video-input').focus();
  });

  refreshAll();
})();

// Auto-slug
(function() {
  const title = document.getElementById('pf-title');
  const slug  = document.getElementById('pf-slug');
  if (!title || !slug || slug.value) return;
  title.addEventListener('input', () => {
    const map = {á:'a',č:'c',ď:'d',é:'e',ě:'e',í:'i',ň:'n',ó:'o',ř:'r',š:'s',ť:'t',ú:'u',ů:'u',ý:'y',ž:'z'};
    slug.value = title.value.toLowerCase().replace(/[áčďéěíňóřšťúůýž]/g, c => map[c]||c)
      .replace(/[^a-z0-9\s-]/g,'').trim().replace(/\s+/g,'-').replace(/-+/g,'-');
  });
})();

</script>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
