<?php
$pageTitle = 'SEO - ' . htmlspecialchars($page['title'] ?? '');
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
$mediaList = $mediaList ?? [];

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
$mediaUrl = fn(array $m): string => $publicMediaUrl((string)($m['storage_path'] ?? ''));
$mediaThumb = function(array $m) use ($mediaUrl, $publicMediaUrl): string {
    foreach (($m['variants'] ?? []) as $v) {
        if (($v['format'] ?? '') === 'thumb' && !empty($v['path'])) {
            return $publicMediaUrl((string)$v['path']);
        }
    }
    return $mediaUrl($m);
};

$imageMedia = array_values(array_filter($mediaList, static function(array $m): bool {
    return str_starts_with((string)($m['mime_type'] ?? ''), 'image/');
}));

$mediaById = [];
foreach ($imageMedia as $media) {
    $mediaById[(int)$media['id']] = $media;
}

$selectedOg = $mediaById[(int)($seo['og_image_id'] ?? 0)] ?? null;
$selectedTwitter = $mediaById[(int)($seo['twitter_image_id'] ?? 0)] ?? null;

$renderSocialPicker = function(string $label, string $fieldName, string $inputId, ?array $selectedMedia, string $emptyLabel) use ($h, $mediaUrl, $mediaThumb): void {
    $selectedId = (int)($selectedMedia['id'] ?? 0);
    $previewUrl = $selectedMedia ? $mediaThumb($selectedMedia) : '';
    $fullUrl = $selectedMedia ? $mediaUrl($selectedMedia) : '';
    $fileName = $selectedMedia['original_filename'] ?? '';
    ?>
    <div class="form-group">
      <label><?= $h($label) ?></label>
      <input type="hidden" name="<?= $h($fieldName) ?>" id="<?= $h($inputId) ?>" value="<?= $selectedId > 0 ? $selectedId : '' ?>">
      <div class="seo-media-picker" data-seo-picker data-input-id="<?= $h($inputId) ?>">
        <div class="seo-media-preview" data-seo-preview>
          <?php if ($selectedMedia): ?>
            <img src="<?= $h($previewUrl) ?>" alt="<?= $h($fileName) ?>">
          <?php else: ?>
            <span><?= $h($emptyLabel) ?></span>
          <?php endif; ?>
        </div>
        <div class="seo-media-meta">
          <div class="seo-media-name" data-seo-name><?= $selectedMedia ? $h($fileName) : $h($emptyLabel) ?></div>
          <div class="seo-media-id" data-seo-id><?= $selectedId > 0 ? 'Media ID: ' . $selectedId : 'Zatim neni vybran obrazek' ?></div>
          <div class="seo-media-path" data-seo-path><?= $selectedMedia ? $h($fullUrl) : '' ?></div>
          <div class="seo-media-actions">
            <button type="button" class="btn btn-secondary" data-open-seo-media data-target="<?= $h($inputId) ?>">Vybrat z medii</button>
            <button type="button" class="btn btn-secondary" data-upload-seo-media data-target="<?= $h($inputId) ?>">Nahrat fotku</button>
            <button type="button" class="btn btn-secondary" data-clear-seo-media data-target="<?= $h($inputId) ?>">Odebrat</button>
          </div>
        </div>
      </div>
      <div class="form-hint">Pouzij obrazek z media knihovny nebo ho rovnou nahraj z pocitace. System ulozi spravne ID automaticky.</div>
    </div>
    <?php
};

ob_start();
?>
<div class="page-header">
  <h1>SEO: <?= $h($page['title']) ?></h1>
  <a href="/admin/pages/edit?id=<?= (int)$page['id'] ?>" class="btn btn-secondary">&larr; Zpet na stranku</a>
</div>

<?php if (!empty($audit)): ?>
<div class="card">
  <div class="card-title">SEO Audit</div>
  <ul class="audit-list">
    <?php foreach ($audit as $item): ?>
    <li class="audit-<?= $item['status'] ?>">
      <span class="audit-icon"><?= $item['status'] === 'ok' ? '+' : ($item['status'] === 'error' ? 'x' : '!') ?></span>
      <span><?= $h($item['label']) ?></span>
      <?php if (!empty($item['note'])): ?><span style="color:var(--c-muted);font-size:12px">(<?= $h($item['note']) ?>)</span><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form method="POST" action="/admin/seo/update">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
  <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">

  <div data-tabs-container>
    <div class="tabs">
      <button type="button" class="tab-btn" data-tab="basic-seo">Zakladni SEO</button>
      <button type="button" class="tab-btn" data-tab="og">Open Graph</button>
      <button type="button" class="tab-btn" data-tab="twitter">Twitter Card</button>
      <button type="button" class="tab-btn" data-tab="schema">Schema.org</button>
    </div>

    <div class="tab-pane" id="tab-basic-seo">
      <div class="card">
        <div class="form-group">
          <label>Meta title</label>
          <input type="text" name="meta_title" value="<?= $h($seo['meta_title'] ?? '') ?>" maxlength="255">
          <div class="form-hint">Doporucena delka: 50-60 znaku</div>
        </div>
        <div class="form-group">
          <label>Meta description</label>
          <textarea name="meta_description" rows="3"><?= $h($seo['meta_description'] ?? '') ?></textarea>
          <div class="form-hint">Doporucena delka: 120-160 znaku</div>
        </div>
        <div class="form-group">
          <label>Meta keywords</label>
          <input type="text" name="meta_keywords" value="<?= $h($seo['meta_keywords'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Canonical URL</label>
          <input type="url" name="canonical_url" value="<?= $h($seo['canonical_url'] ?? '') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <div class="checkbox-row">
              <input type="checkbox" id="robots_index" name="robots_index" value="1" <?= !empty($seo['robots_index']) ? 'checked' : '' ?>>
              <label for="robots_index" style="margin-bottom:0">Indexovat (robots: index)</label>
            </div>
          </div>
          <div class="form-group">
            <div class="checkbox-row">
              <input type="checkbox" id="robots_follow" name="robots_follow" value="1" <?= !empty($seo['robots_follow']) ? 'checked' : '' ?>>
              <label for="robots_follow" style="margin-bottom:0">Sledovat odkazy (robots: follow)</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="tab-pane" id="tab-og">
      <div class="card">
        <div class="form-group">
          <label>OG Title</label>
          <input type="text" name="og_title" value="<?= $h($seo['og_title'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>OG Description</label>
          <textarea name="og_description" rows="3"><?= $h($seo['og_description'] ?? '') ?></textarea>
        </div>
        <?php $renderSocialPicker('OG Image', 'og_image_id', 'og_image_id', $selectedOg, 'Neni vybran zadny OG obrazek'); ?>
      </div>
    </div>

    <div class="tab-pane" id="tab-twitter">
      <div class="card">
        <div class="form-group">
          <label>Twitter Title</label>
          <input type="text" name="twitter_title" value="<?= $h($seo['twitter_title'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Twitter Description</label>
          <textarea name="twitter_description" rows="3"><?= $h($seo['twitter_description'] ?? '') ?></textarea>
        </div>
        <?php $renderSocialPicker('Twitter Image', 'twitter_image_id', 'twitter_image_id', $selectedTwitter, 'Neni vybran zadny Twitter obrazek'); ?>
      </div>
    </div>

    <div class="tab-pane" id="tab-schema">
      <div class="card">
        <div class="form-group">
          <label>Schema type</label>
          <select name="schema_type">
            <option value="">- nevyplneno -</option>
            <option value="LocalBusiness" <?= ($seo['schema_type'] ?? '') === 'LocalBusiness' ? 'selected' : '' ?>>LocalBusiness</option>
            <option value="Person" <?= ($seo['schema_type'] ?? '') === 'Person' ? 'selected' : '' ?>>Person</option>
            <option value="Article" <?= ($seo['schema_type'] ?? '') === 'Article' ? 'selected' : '' ?>>Article</option>
            <option value="WebPage" <?= ($seo['schema_type'] ?? '') === 'WebPage' ? 'selected' : '' ?>>WebPage</option>
          </select>
        </div>
        <div class="form-group">
          <label>Schema JSON-LD</label>
          <textarea class="code" name="schema_json" rows="12"><?= $h($seo['schema_json'] ?? '') ?></textarea>
          <div class="form-hint">Cely objekt vcetne &lt;script type="application/ld+json"&gt; nebo jen JSON</div>
        </div>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:10px;margin-top:4px">
    <button type="submit" class="btn btn-primary">Ulozit SEO</button>
  </div>
</form>

<form method="POST" action="/admin/publish/page" style="display:inline;margin-top:0" data-confirm="Publikovat stranku?">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
  <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
  <button type="submit" class="btn btn-success" style="margin-top:10px">Publikovat</button>
</form>

<div class="seo-media-modal" id="seo-media-modal" aria-hidden="true" data-csrf="<?= $h($_csrfToken) ?>">
  <div class="seo-media-dialog" role="dialog" aria-modal="true" aria-label="Vyber social image">
    <div class="seo-media-head">
      <div>
        <div class="card-title" style="margin:0">Vyber nebo nahraj obrazek</div>
        <div class="help-text">Obrazek se po vyberu ulozi jako media ID do SEO nastaveni.</div>
      </div>
      <button type="button" class="btn btn-secondary" data-seo-media-close>Zavrit</button>
    </div>

    <div class="seo-media-upload">
      <div class="form-row seo-media-upload-row">
        <div class="form-group">
          <label for="seo-media-alt">ALT text <span style="color:#dc2626">*</span></label>
          <input type="text" id="seo-media-alt" placeholder="Popis obrazku pro media knihovnu">
        </div>
        <div class="form-group">
          <label for="seo-media-title">Nazev</label>
          <input type="text" id="seo-media-title" placeholder="Nepovinne, jinak se pouzije ALT text">
        </div>
      </div>
      <div class="seo-media-upload-actions">
        <input type="file" id="seo-media-file" accept=".jpg,.jpeg,.png,.webp,.gif" hidden>
        <button type="button" class="btn btn-secondary" id="seo-media-browse">Vybrat fotku z pocitace</button>
        <span class="help-text" id="seo-media-file-name">Zatim neni vybran zadny soubor.</span>
      </div>
      <div class="seo-media-upload-actions" style="margin-top:10px">
        <button type="button" class="btn btn-primary" id="seo-media-upload-btn">Nahrat a vybrat</button>
        <span id="seo-media-upload-status" class="help-text"></span>
      </div>
    </div>

    <div class="seo-media-library">
      <p class="help-text" id="seo-media-empty"<?= empty($imageMedia) ? '' : ' hidden' ?>>Zatim nejsou nahrana zadna obrazkova media. Muzes je nahrat primo zde nebo v sekci <a href="/admin/media">Media</a>.</p>
      <div class="seo-media-list" id="seo-media-list"<?= empty($imageMedia) ? ' hidden' : '' ?>>
        <?php foreach ($imageMedia as $m): ?>
          <?php
            $url = $mediaUrl($m);
            $thumb = $mediaThumb($m);
          ?>
          <button
            type="button"
            class="seo-media-item"
            data-seo-media-item
            data-id="<?= (int)$m['id'] ?>"
            data-url="<?= $h($url) ?>"
            data-thumb="<?= $h($thumb) ?>"
            data-name="<?= $h($m['original_filename'] ?? '') ?>"
            data-mime="<?= $h($m['mime_type'] ?? '') ?>"
            title="<?= $h($m['original_filename'] ?? '') ?>"
          >
            <div class="seo-media-thumb">
              <img src="<?= $h($thumb) ?>" alt="<?= $h($m['alt_text'] ?? $m['original_filename'] ?? '') ?>" loading="lazy">
            </div>
            <div class="seo-media-caption">
              <span class="seo-media-caption-name"><?= $h($m['original_filename'] ?? $url) ?></span>
              <span class="seo-media-caption-id">ID <?= (int)$m['id'] ?></span>
            </div>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<style>
.seo-media-picker{display:grid;grid-template-columns:minmax(140px,180px) 1fr;gap:14px;align-items:start}
.seo-media-preview{min-height:112px;border:1px dashed var(--c-border);border-radius:10px;background:var(--c-bg);display:flex;align-items:center;justify-content:center;padding:10px;color:var(--c-muted);font-size:13px;text-align:center}
.seo-media-preview img{width:100%;height:112px;object-fit:cover;border-radius:8px;display:block}
.seo-media-meta{display:grid;gap:8px}
.seo-media-name{font-weight:600;color:var(--c-text)}
.seo-media-id,.seo-media-path{font-size:12px;color:var(--c-muted)}
.seo-media-path{word-break:break-all;min-height:16px}
.seo-media-actions{display:flex;gap:8px;flex-wrap:wrap}
.seo-media-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:flex-start;justify-content:center;padding:6vh 24px;background:rgba(15,23,42,.62)}
.seo-media-modal.is-open{display:flex}
.seo-media-dialog{width:min(1080px,100%);max-height:88vh;overflow:auto;background:var(--c-surface);border-radius:14px;border:1px solid var(--c-border);box-shadow:0 24px 70px rgba(15,23,42,.25);padding:18px}
.seo-media-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:14px}
.seo-media-upload{border:1px solid var(--c-border);border-radius:12px;background:var(--c-bg);padding:14px;margin-bottom:18px}
.seo-media-upload-row{margin-bottom:0}
.seo-media-upload-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.seo-media-library{display:grid;gap:12px}
.seo-media-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(156px,1fr));gap:12px}
.seo-media-item{border:1px solid var(--c-border);border-radius:10px;background:var(--c-bg);overflow:hidden;text-align:left;cursor:pointer;padding:0}
.seo-media-item:hover{box-shadow:0 10px 24px rgba(15,23,42,.12);border-color:var(--c-primary)}
.seo-media-thumb{height:108px;background:#fff}
.seo-media-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.seo-media-caption{padding:8px;display:grid;gap:4px}
.seo-media-caption-name{font-size:11px;color:var(--c-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.seo-media-caption-id{font-size:11px;color:var(--c-muted)}
@media (max-width: 720px){
  .seo-media-picker{grid-template-columns:1fr}
  .seo-media-preview img{height:180px}
}
</style>

<script>
(function () {
  const modal = document.getElementById('seo-media-modal');
  if (!modal) return;

  const fileInput = document.getElementById('seo-media-file');
  const browseBtn = document.getElementById('seo-media-browse');
  const uploadBtn = document.getElementById('seo-media-upload-btn');
  const altInput = document.getElementById('seo-media-alt');
  const titleInput = document.getElementById('seo-media-title');
  const fileName = document.getElementById('seo-media-file-name');
  const uploadStatus = document.getElementById('seo-media-upload-status');
  const mediaList = document.getElementById('seo-media-list');
  const emptyState = document.getElementById('seo-media-empty');
  const csrfToken = modal.dataset.csrf || '';
  let activeTarget = '';

  function pickerFor(targetId) {
    const input = document.getElementById(targetId);
    return input ? input.closest('[data-seo-picker]') : null;
  }

  function renderPicker(targetId, media) {
    const input = document.getElementById(targetId);
    const picker = pickerFor(targetId);
    if (!input || !picker) return;

    const preview = picker.querySelector('[data-seo-preview]');
    const name = picker.querySelector('[data-seo-name]');
    const idLabel = picker.querySelector('[data-seo-id]');
    const path = picker.querySelector('[data-seo-path]');

    if (!media) {
      input.value = '';
      if (preview) preview.innerHTML = '<span>Neni vybran zadny obrazek</span>';
      if (name) name.textContent = 'Neni vybran zadny obrazek';
      if (idLabel) idLabel.textContent = 'Zatim neni vybran obrazek';
      if (path) path.textContent = '';
      return;
    }

    input.value = String(media.id || '');
    if (preview) {
      preview.innerHTML = '';
      const img = document.createElement('img');
      img.src = media.thumb || media.url || '';
      img.alt = media.name || '';
      preview.appendChild(img);
    }
    if (name) name.textContent = media.name || '';
    if (idLabel) idLabel.textContent = media.id ? 'Media ID: ' + media.id : '';
    if (path) path.textContent = media.url || '';
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function openModal(targetId, autoBrowse) {
    activeTarget = targetId || '';
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    uploadStatus.textContent = '';
    if (autoBrowse && browseBtn) {
      setTimeout(() => browseBtn.click(), 0);
    }
  }

  function selectedMediaFromButton(button) {
    return {
      id: Number(button.dataset.id || 0),
      url: button.dataset.url || '',
      thumb: button.dataset.thumb || button.dataset.url || '',
      name: button.dataset.name || ''
    };
  }

  function appendLibraryItem(media) {
    if (!mediaList || !media || !media.id || !media.url) return;
    mediaList.hidden = false;
    if (emptyState) emptyState.hidden = true;

    const existing = mediaList.querySelector('[data-id="' + media.id + '"]');
    if (existing) {
      existing.dataset.url = media.url;
      existing.dataset.thumb = media.thumb || media.url;
      existing.dataset.name = media.name || '';
      existing.dataset.mime = media.mime || '';
      const img = existing.querySelector('img');
      if (img) {
        img.src = media.thumb || media.url;
        img.alt = media.name || '';
      }
      const name = existing.querySelector('.seo-media-caption-name');
      if (name) name.textContent = media.name || media.url;
      return;
    }

    const item = document.createElement('button');
    item.type = 'button';
    item.className = 'seo-media-item';
    item.dataset.seoMediaItem = '';
    item.dataset.id = String(media.id);
    item.dataset.url = media.url;
    item.dataset.thumb = media.thumb || media.url;
    item.dataset.name = media.name || '';
    item.dataset.mime = media.mime || '';
    item.title = media.name || media.url;
    item.innerHTML =
      '<div class="seo-media-thumb"><img loading="lazy" alt=""></div>' +
      '<div class="seo-media-caption">' +
        '<span class="seo-media-caption-name"></span>' +
        '<span class="seo-media-caption-id"></span>' +
      '</div>';

    const img = item.querySelector('img');
    img.src = media.thumb || media.url;
    img.alt = media.name || '';
    item.querySelector('.seo-media-caption-name').textContent = media.name || media.url;
    item.querySelector('.seo-media-caption-id').textContent = 'ID ' + media.id;
    item.addEventListener('click', () => {
      if (!activeTarget) return;
      renderPicker(activeTarget, selectedMediaFromButton(item));
      closeModal();
    });

    mediaList.prepend(item);
  }

  document.querySelectorAll('[data-open-seo-media]').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn.dataset.target || '', false));
  });

  document.querySelectorAll('[data-upload-seo-media]').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn.dataset.target || '', true));
  });

  document.querySelectorAll('[data-clear-seo-media]').forEach(btn => {
    btn.addEventListener('click', () => renderPicker(btn.dataset.target || '', null));
  });

  if (browseBtn && fileInput) {
    browseBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
      fileName.textContent = file ? file.name : 'Zatim neni vybran zadny soubor.';
      uploadStatus.textContent = '';
    });
  }

  if (uploadBtn) {
    uploadBtn.addEventListener('click', async () => {
      if (!activeTarget) {
        uploadStatus.textContent = 'Nejdriv otevri modal z konkretniho pole.';
        uploadStatus.style.color = '#dc2626';
        return;
      }

      const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
      if (!file) {
        uploadStatus.textContent = 'Vyber fotku z pocitace.';
        uploadStatus.style.color = '#dc2626';
        return;
      }

      const altText = altInput.value.trim();
      if (!altText) {
        altInput.focus();
        uploadStatus.textContent = 'ALT text je povinny.';
        uploadStatus.style.color = '#dc2626';
        return;
      }

      const formData = new FormData();
      formData.append('_csrf', csrfToken);
      formData.append('alt_text', altText);
      formData.append('title', titleInput.value.trim() || altText);
      formData.append('files[]', file);

      uploadBtn.disabled = true;
      uploadBtn.textContent = 'Nahravam...';
      uploadStatus.textContent = '';

      try {
        const response = await fetch('/admin/media/upload', { method: 'POST', body: formData });
        const raw = await response.text();
        let payload = {};
        try {
          payload = JSON.parse(raw);
        } catch (parseError) {
          uploadStatus.textContent = 'Server vratil neocekavanou odpoved (HTTP ' + response.status + ').';
          uploadStatus.style.color = '#dc2626';
          return;
        }
        const result = Array.isArray(payload.results) ? payload.results[0] : null;

        if (!response.ok || !result || !result.success) {
          const message = result && result.error ? result.error : (payload.error || 'Upload selhal.');
          uploadStatus.textContent = message;
          uploadStatus.style.color = '#dc2626';
          return;
        }

        const media = {
          id: Number(result.media_id || 0),
          url: result.url || '',
          thumb: result.thumb_url || result.url || '',
          name: result.name || file.name,
          mime: result.mime_type || ''
        };

        appendLibraryItem(media);
        renderPicker(activeTarget, media);
        fileInput.value = '';
        altInput.value = '';
        titleInput.value = '';
        fileName.textContent = 'Zatim neni vybran zadny soubor.';
        uploadStatus.textContent = result.warning || 'Fotka byla nahrana a vybrana.';
        uploadStatus.style.color = result.warning ? '#d97706' : '#16a34a';
        closeModal();
      } catch (error) {
        uploadStatus.textContent = 'Upload selhal. Zkus to znovu.';
        uploadStatus.style.color = '#dc2626';
      } finally {
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Nahrat a vybrat';
      }
    });
  }

  if (mediaList) {
    mediaList.querySelectorAll('[data-seo-media-item]').forEach(item => {
      item.addEventListener('click', () => {
        if (!activeTarget) return;
        renderPicker(activeTarget, selectedMediaFromButton(item));
        closeModal();
      });
    });
  }

  modal.querySelectorAll('[data-seo-media-close]').forEach(btn => btn.addEventListener('click', closeModal));
  modal.addEventListener('click', event => {
    if (event.target === modal) closeModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });
})();
</script>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
