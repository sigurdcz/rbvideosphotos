<?php
$pageTitle = 'Nastaveni webu';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
$media = $media ?? [];
$mediaUrl = fn(array $m): string => '/' . ltrim((string)($m['storage_path'] ?? ''), '/');
$mediaThumb = function(array $m) use ($mediaUrl): string {
    foreach (($m['variants'] ?? []) as $v) {
        if (($v['format'] ?? '') === 'thumb' && !empty($v['path'])) {
            return '/' . ltrim((string)$v['path'], '/');
        }
    }
    return $mediaUrl($m);
};

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$requestHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestUrl = $requestHost !== '' ? $scheme . '://' . $requestHost : '';

$storedDomain = trim((string)($site['domain'] ?? ''));
$isLocalDomain = $storedDomain === '' || str_contains($storedDomain, 'localhost');
$isRequestLocal = $requestUrl === '' || str_contains($requestUrl, 'localhost');
$domainValue = ($isLocalDomain && !$isRequestLocal) ? $requestUrl : $storedDomain;

ob_start();
?>
<div class="page-header">
  <h1>Nastaveni webu</h1>
</div>

<?php if ($isLocalDomain && !$isRequestLocal && $requestUrl !== ''): ?>
<div class="alert alert-warning" style="margin-bottom:16px">
  Domena je nastavena na <strong><?= $h($storedDomain ?: '(prazdna)') ?></strong>.
  Automaticky jsem predvyplnil spravnou URL z aktualniho requestu, zkontroluj ji a uloz.
</div>
<?php endif; ?>

<form method="POST" action="/admin/settings/update">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">

  <div class="card">
    <div class="card-title">Zakladni informace</div>
    <div class="form-row">
      <div class="form-group">
        <label>Nazev webu *</label>
        <input type="text" name="name" value="<?= $h($site['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Domena *</label>
        <input type="url" name="domain" value="<?= $h($domainValue) ?>" placeholder="https://example.cz" required>
      </div>
    </div>
    <div class="form-group">
      <label>Vychozi jazyk</label>
      <select name="default_language">
        <option value="cs" <?= ($site['default_language'] ?? 'cs') === 'cs' ? 'selected' : '' ?>>Cestina</option>
        <option value="en" <?= ($site['default_language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
        <option value="sk" <?= ($site['default_language'] ?? '') === 'sk' ? 'selected' : '' ?>>Slovencina</option>
      </select>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Logo a favicony</div>
    <p class="help-text" style="margin-bottom:16px">
      Vyber logo a ikony primo z knihovny medii. Logo se pouzije ve verejne hlavicce webu,
      favicony se vlozi do <code>&lt;head&gt;</code>.
    </p>
    <div class="form-row">
      <div class="form-group">
        <label>Logo webu</label>
        <div class="brand-asset-picker">
          <input type="hidden" name="logo_url" id="brand-logo-url" value="<?= $h($site['logo_url'] ?? '') ?>">
          <div class="brand-asset-preview" data-brand-preview for="brand-logo-url">
            <?php if (!empty($site['logo_url'])): ?><img src="<?= $h($site['logo_url']) ?>" alt="Logo"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
          </div>
          <div class="brand-asset-actions">
            <button type="button" class="btn btn-secondary" data-open-brand-media data-target="brand-logo-url">Vybrat z medii</button>
            <button type="button" class="btn btn-secondary" data-clear-brand-media data-target="brand-logo-url">Odebrat</button>
          </div>
          <div class="help-text brand-asset-path" data-brand-path for="brand-logo-url"><?= $h($site['logo_url'] ?? '') ?></div>
        </div>
      </div>
      <div class="form-group">
        <label>Favicon ICO</label>
        <div class="brand-asset-picker">
          <input type="hidden" name="favicon_ico_url" id="brand-favicon-ico-url" value="<?= $h($site['favicon_ico_url'] ?? '') ?>">
          <div class="brand-asset-preview brand-asset-preview--icon" data-brand-preview for="brand-favicon-ico-url">
            <?php if (!empty($site['favicon_ico_url'])): ?><img src="<?= $h($site['favicon_ico_url']) ?>" alt="Favicon ICO"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
          </div>
          <div class="brand-asset-actions">
            <button type="button" class="btn btn-secondary" data-open-brand-media data-target="brand-favicon-ico-url">Vybrat z medii</button>
            <button type="button" class="btn btn-secondary" data-clear-brand-media data-target="brand-favicon-ico-url">Odebrat</button>
          </div>
          <div class="help-text brand-asset-path" data-brand-path for="brand-favicon-ico-url"><?= $h($site['favicon_ico_url'] ?? '') ?></div>
        </div>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Favicon PNG/SVG</label>
        <div class="brand-asset-picker">
          <input type="hidden" name="favicon_png_url" id="brand-favicon-png-url" value="<?= $h($site['favicon_png_url'] ?? '') ?>">
          <div class="brand-asset-preview brand-asset-preview--icon" data-brand-preview for="brand-favicon-png-url">
            <?php if (!empty($site['favicon_png_url'])): ?><img src="<?= $h($site['favicon_png_url']) ?>" alt="Favicon PNG/SVG"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
          </div>
          <div class="brand-asset-actions">
            <button type="button" class="btn btn-secondary" data-open-brand-media data-target="brand-favicon-png-url">Vybrat z medii</button>
            <button type="button" class="btn btn-secondary" data-clear-brand-media data-target="brand-favicon-png-url">Odebrat</button>
          </div>
          <div class="help-text brand-asset-path" data-brand-path for="brand-favicon-png-url"><?= $h($site['favicon_png_url'] ?? '') ?></div>
        </div>
      </div>
      <div class="form-group">
        <label>Apple touch icon</label>
        <div class="brand-asset-picker">
          <input type="hidden" name="apple_touch_icon_url" id="brand-apple-touch-icon-url" value="<?= $h($site['apple_touch_icon_url'] ?? '') ?>">
          <div class="brand-asset-preview brand-asset-preview--icon" data-brand-preview for="brand-apple-touch-icon-url">
            <?php if (!empty($site['apple_touch_icon_url'])): ?><img src="<?= $h($site['apple_touch_icon_url']) ?>" alt="Apple touch icon"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
          </div>
          <div class="brand-asset-actions">
            <button type="button" class="btn btn-secondary" data-open-brand-media data-target="brand-apple-touch-icon-url">Vybrat z medii</button>
            <button type="button" class="btn btn-secondary" data-clear-brand-media data-target="brand-apple-touch-icon-url">Odebrat</button>
          </div>
          <div class="help-text brand-asset-path" data-brand-path for="brand-apple-touch-icon-url"><?= $h($site['apple_touch_icon_url'] ?? '') ?></div>
        </div>
      </div>
    </div>
    <p class="help-text">Pokud pole nechas prazdne, system zustane u vychoziho faviconu / sablony. Nova media nahrajes v sekci <a href="/admin/media">Media</a>.</p>
  </div>

  <div class="card">
    <div class="card-title">Analytics</div>
    <div class="form-row">
      <div class="form-group">
        <label>Google Analytics ID</label>
        <input type="text" name="google_analytics_id" value="<?= $h($site['google_analytics_id'] ?? '') ?>" placeholder="G-XXXXXXXXXX">
      </div>
      <div class="form-group">
        <label>Google Tag Manager ID</label>
        <input type="text" name="google_tag_manager_id" value="<?= $h($site['google_tag_manager_id'] ?? '') ?>" placeholder="GTM-XXXXXXX">
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Vlastni skripty</div>
    <div class="form-group">
      <label>Skript do &lt;head&gt;</label>
      <textarea class="code" name="head_scripts" rows="5"><?= $h($site['head_scripts'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label>Skript pred &lt;/body&gt;</label>
      <textarea class="code" name="body_scripts" rows="5"><?= $h($site['body_scripts'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Layout webu (spolecna hlavicka a paticka)</div>
    <p class="help-text" style="margin-bottom:16px">
      HTML layout obali kazdou stranku. Dostupne placeholdery:<br>
      <code>{page_content}</code> — obsah stranky &nbsp;|&nbsp;
      <code>{site_name}</code> — nazev webu &nbsp;|&nbsp;
      <code>{nav}</code> — navigace (viz pole nav nize). Ponech prazdne, pokud kazda sablona ma vlastni HTML.
    </p>
    <div class="form-group">
      <label>Layout HTML</label>
      <textarea class="code" name="layout_html" rows="14"><?= $h($site['layout_html'] ?? '') ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Layout CSS (spolecne styly — nav, paticka, zaklad)</label>
        <textarea class="code" name="layout_css" rows="10"><?= $h($site['layout_css'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>Layout JS (spolecny skript — scroll, nav efekty)</label>
        <textarea class="code" name="layout_js" rows="10"><?= $h($site['layout_js'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Nav — homepage (hash anchory pro One Page scroll)</label>
        <textarea class="code" name="layout_nav_home" rows="8"><?= $h($site['layout_nav_home'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>Nav — subpages (prime URL na jednotlive stranky)</label>
        <textarea class="code" name="layout_nav_sub" rows="8"><?= $h($site['layout_nav_sub'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">Ulozit nastaveni</button>
</form>

<div class="card" style="margin-top:24px">
  <div class="card-title">REST API</div>
  <?php if ($apiKey): ?>
  <div class="form-group">
    <label>API klic</label>
    <div style="display:flex;gap:8px;align-items:center">
      <input type="text" id="api-key-field" value="<?= $h($apiKey) ?>" readonly style="font-family:monospace;flex:1;background:var(--c-bg);color:var(--c-muted)">
      <button type="button" class="btn btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('api-key-field').value).then(()=>{this.textContent='Zkopirovano!';setTimeout(()=>this.textContent='Kopirovat',1500)})">Kopirovat</button>
    </div>
    <p class="help-text" style="margin-top:6px">Pouzij jako <code>X-Api-Key: {klic}</code> nebo <code>Authorization: Bearer {klic}</code>.</p>
  </div>
  <div class="form-group" style="margin-top:4px">
    <label>Dostupne endpointy</label>
    <ul style="margin:4px 0 0 18px;font-size:13px;color:var(--c-muted);line-height:1.8">
      <li><code>GET /api/v1/articles</code> - seznam clanku</li>
      <li><code>GET /api/v1/articles/{slug}</code> - detail clanku</li>
      <li><code>GET /api/v1/portfolio</code> - seznam portfolia</li>
      <li><code>GET /api/v1/portfolio/{slug}</code> - detail polozky</li>
    </ul>
  </div>
  <?php else: ?>
  <p class="help-text">API klic bude automaticky vygenerovan pri prvnim volani API endpointu.</p>
  <?php endif; ?>
</div>

<style>
.brand-asset-picker{display:grid;gap:10px}
.brand-asset-preview{min-height:76px;border:1px dashed var(--c-border);border-radius:10px;background:var(--c-bg);display:flex;align-items:center;justify-content:center;padding:12px;color:var(--c-muted);font-size:13px}
.brand-asset-preview img{max-width:100%;max-height:58px;object-fit:contain;display:block}
.brand-asset-preview--icon{width:96px;min-height:76px}
.brand-asset-preview--icon img{max-width:48px;max-height:48px}
.brand-asset-actions{display:flex;gap:8px;flex-wrap:wrap}
.brand-asset-path{min-height:17px;word-break:break-all}
.brand-media-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:flex-start;justify-content:center;padding:7vh 24px;background:rgba(15,23,42,.62)}
.brand-media-modal.is-open{display:flex}
.brand-media-dialog{width:min(980px,100%);max-height:86vh;overflow:auto;background:var(--c-surface);border-radius:14px;border:1px solid var(--c-border);box-shadow:0 24px 70px rgba(15,23,42,.25);padding:18px}
.brand-media-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:14px}
.brand-media-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
.brand-media-item{border:1px solid var(--c-border);border-radius:10px;background:var(--c-bg);overflow:hidden;text-align:left;cursor:pointer;padding:0}
.brand-media-item:hover{box-shadow:0 10px 24px rgba(15,23,42,.12);border-color:var(--c-primary)}
.brand-media-thumb{height:104px;display:flex;align-items:center;justify-content:center;background:#fff}
.brand-media-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.brand-media-file{font-size:24px;color:var(--c-muted)}
.brand-media-name{padding:8px;font-size:11px;color:var(--c-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
</style>

<div class="brand-media-modal" id="brand-media-modal" aria-hidden="true">
  <div class="brand-media-dialog" role="dialog" aria-modal="true" aria-label="Vyber media">
    <div class="brand-media-head">
      <div>
        <div class="card-title" style="margin:0">Vyber z medii</div>
        <div class="help-text">Kliknutim vyberes soubor pro aktualni pole.</div>
      </div>
      <button type="button" class="btn btn-secondary" data-brand-media-close>Zavrit</button>
    </div>
    <?php if (empty($media)): ?>
      <p class="help-text">Zatim nejsou nahrana zadna media. Nejdrive je nahraj v sekci <a href="/admin/media">Media</a>.</p>
    <?php else: ?>
      <div class="brand-media-list">
        <?php foreach ($media as $m): ?>
          <?php
            $url = $mediaUrl($m);
            $thumb = $mediaThumb($m);
            $mime = (string)($m['mime_type'] ?? '');
            $isImage = str_starts_with($mime, 'image/');
          ?>
          <button type="button" class="brand-media-item" data-brand-media-item data-url="<?= $h($url) ?>" title="<?= $h($m['original_filename'] ?? '') ?>">
            <div class="brand-media-thumb">
              <?php if ($isImage): ?>
                <img src="<?= $h($thumb) ?>" alt="<?= $h($m['alt_text'] ?? $m['original_filename'] ?? '') ?>" loading="lazy">
              <?php else: ?>
                <span class="brand-media-file">FILE</span>
              <?php endif; ?>
            </div>
            <div class="brand-media-name"><?= $h($m['original_filename'] ?? $url) ?></div>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function(){
  const modal = document.getElementById('brand-media-modal');
  if (!modal) return;
  let activeTarget = '';

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function previewFor(targetId) {
    return document.querySelector('[data-brand-preview][for="' + targetId + '"]');
  }

  function pathFor(targetId) {
    return document.querySelector('[data-brand-path][for="' + targetId + '"]');
  }

  function setAsset(targetId, url) {
    const input = document.getElementById(targetId);
    if (!input) return;
    input.value = url;
    const preview = previewFor(targetId);
    const path = pathFor(targetId);
    if (preview) {
      preview.textContent = '';
      if (url) {
        const img = document.createElement('img');
        img.src = url;
        img.alt = '';
        preview.appendChild(img);
      } else {
        const empty = document.createElement('span');
        empty.textContent = 'Neni vybrano';
        preview.appendChild(empty);
      }
    }
    if (path) path.textContent = url;
  }

  document.querySelectorAll('[data-open-brand-media]').forEach(btn => {
    btn.addEventListener('click', () => {
      activeTarget = btn.dataset.target || '';
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
    });
  });

  document.querySelectorAll('[data-clear-brand-media]').forEach(btn => {
    btn.addEventListener('click', () => setAsset(btn.dataset.target || '', ''));
  });

  modal.querySelectorAll('[data-brand-media-item]').forEach(item => {
    item.addEventListener('click', () => {
      if (activeTarget) setAsset(activeTarget, item.dataset.url || '');
      closeModal();
    });
  });

  modal.querySelectorAll('[data-brand-media-close]').forEach(btn => btn.addEventListener('click', closeModal));
  modal.addEventListener('click', e => {
    if (e.target === modal) closeModal();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });
})();
</script>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
