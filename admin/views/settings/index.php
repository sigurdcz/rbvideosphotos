<?php
$pageTitle = 'Nastaveni webu';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));

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
      Vloz URL nebo cestu z Media knihovny, napr. <code>/storage/media/2026/05/logo.webp</code>.
      Logo se pouzije ve verejne hlavicce webu, favicony se vlozi do <code>&lt;head&gt;</code>.
    </p>
    <div class="form-row">
      <div class="form-group">
        <label>Logo webu</label>
        <input type="text" name="logo_url" value="<?= $h($site['logo_url'] ?? '') ?>" placeholder="/storage/media/.../logo.svg">
        <?php if (!empty($site['logo_url'])): ?>
          <div style="margin-top:10px;display:inline-flex;align-items:center;padding:10px 12px;border:1px solid var(--c-border);border-radius:10px;background:var(--c-bg)">
            <img src="<?= $h($site['logo_url']) ?>" alt="Logo" style="max-height:44px;max-width:220px;display:block">
          </div>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>Favicon ICO</label>
        <input type="text" name="favicon_ico_url" value="<?= $h($site['favicon_ico_url'] ?? '') ?>" placeholder="/favicon.ico nebo /storage/media/.../favicon.ico">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Favicon PNG/SVG</label>
        <input type="text" name="favicon_png_url" value="<?= $h($site['favicon_png_url'] ?? '') ?>" placeholder="/storage/media/.../favicon-32x32.png">
      </div>
      <div class="form-group">
        <label>Apple touch icon</label>
        <input type="text" name="apple_touch_icon_url" value="<?= $h($site['apple_touch_icon_url'] ?? '') ?>" placeholder="/storage/media/.../apple-touch-icon.png">
      </div>
    </div>
    <p class="help-text">Pokud pole nechas prazdne, system zustane u vychoziho faviconu / sablony.</p>
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
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
