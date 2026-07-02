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
$selectedSet = array_flip(array_map('intval', $selected ?? []));
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
        <input type="number" name="sort_order" value="<?= (int)($entry['sort_order'] ?? 0) ?>" min="0" style="max-width:80px">
        <div class="form-hint">Nižší = dříve</div>
      </div>
    </div>
  </div>

  <!-- Video URL -->
  <div class="card">
    <div class="card-title">Video</div>
    <div class="form-group">
      <label>YouTube nebo Vimeo URL</label>
      <input type="url" id="video-url" name="video_url" value="<?= $h($entry['video_url'] ?? '') ?>"
             placeholder="https://www.youtube.com/watch?v=... nebo https://vimeo.com/...">
      <div class="form-hint">Zadejte odkaz na video — automaticky se zobrazí náhled níže</div>
    </div>
    <div id="video-preview" style="display:none;max-width:640px;margin-top:8px">
      <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:8px;background:#000">
        <iframe id="video-frame" src="" frameborder="0" allow="autoplay; fullscreen" style="position:absolute;top:0;left:0;width:100%;height:100%"></iframe>
      </div>
    </div>
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
    <?php if (empty($media)): ?>
      <p style="color:var(--c-muted)">Nejsou žádná média. <a href="/admin/media">Nahrát fotky</a>.</p>
    <?php else: ?>
      <p style="font-size:13px;color:var(--c-muted);margin-bottom:12px">
        Klikněte na fotky pro výběr/odznačení. Pořadí odpovídá pořadí kliknutí.
        <span id="pm-count" style="font-weight:600;color:var(--c-primary)">
          <?= count($selectedSet) ?> vybráno
        </span>
      </p>
      <div class="media-grid" id="pm-grid">
        <?php foreach ($media as $m): ?>
        <div class="media-card pm-item <?= isset($selectedSet[(int)$m['id']]) ? 'pm-selected' : '' ?>"
             data-media-id="<?= (int)$m['id'] ?>"
             data-order="<?= isset($selectedSet[(int)$m['id']]) ? array_search((int)$m['id'], array_keys($selectedSet)) : '' ?>"
             style="cursor:pointer">
          <img src="<?= $h($publicMediaUrl((string)($m['storage_path'] ?? ''))) ?>" alt="<?= $h($m['alt_text']) ?>" loading="lazy" style="height:90px;object-fit:cover;display:block;width:100%">
          <div class="media-card-info" style="display:flex;justify-content:space-between;padding:5px 8px">
            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $h($m['original_filename']) ?></span>
            <span class="pm-check" style="flex-shrink:0;font-size:14px;color:#16a34a">✓</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <!-- Hidden inputs for selected media IDs (in order) -->
    <div id="pm-hidden">
      <?php foreach (array_keys($selectedSet) as $mid): ?>
        <input type="hidden" name="media_ids[]" value="<?= (int)$mid ?>">
      <?php endforeach; ?>
    </div>
  </div>

  <div style="margin-top:16px;display:flex;gap:8px">
    <button type="submit" class="btn btn-primary">Uložit práci</button>
    <a href="/admin/portfolio" class="btn btn-secondary">Zrušit</a>
  </div>
</form>

<style>
.pm-item { transition: box-shadow .15s, outline .1s; outline: 3px solid transparent; }
.pm-item .pm-check { display: none; }
.pm-item.pm-selected { outline-color: #6366f1; }
.pm-item.pm-selected .pm-check { display: inline; }
</style>

<script>
// Video URL → embed preview
(function() {
  const input   = document.getElementById('video-url');
  const preview = document.getElementById('video-preview');
  const frame   = document.getElementById('video-frame');
  if (!input) return;

  function getEmbed(url) {
    let m = url.match(/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/)([A-Za-z0-9_-]{11})/);
    if (m) return 'https://www.youtube.com/embed/' + m[1];
    m = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
    if (m) return 'https://player.vimeo.com/video/' + m[1] + '?dnt=1';
    return null;
  }

  function refresh() {
    const embed = getEmbed(input.value.trim());
    if (embed) { frame.src = embed; preview.style.display = 'block'; }
    else        { frame.src = ''; preview.style.display = 'none'; }
  }
  input.addEventListener('input', refresh);
  refresh();
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

// Gallery multi-select picker
(function() {
  const grid    = document.getElementById('pm-grid');
  const hidden  = document.getElementById('pm-hidden');
  const counter = document.getElementById('pm-count');
  if (!grid) return;

  const order = [];
  // Pre-populate from already-selected items
  grid.querySelectorAll('.pm-item.pm-selected').forEach(el => order.push(parseInt(el.dataset.mediaId)));

  grid.querySelectorAll('.pm-item').forEach(item => {
    item.addEventListener('click', () => {
      const id = parseInt(item.dataset.mediaId);
      const idx = order.indexOf(id);
      if (idx === -1) {
        order.push(id);
        item.classList.add('pm-selected');
      } else {
        order.splice(idx, 1);
        item.classList.remove('pm-selected');
      }
      syncHidden();
    });
  });

  function syncHidden() {
    hidden.innerHTML = '';
    for (const id of order) {
      const inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'media_ids[]'; inp.value = id;
      hidden.appendChild(inp);
    }
    if (counter) counter.textContent = order.length + ' vybráno';
  }
})();
</script>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
