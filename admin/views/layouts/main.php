<?php $adminAssetVersion = static function (string $relative): string {
    $absolute = dirname(__DIR__, 2) . '/assets/' . ltrim($relative, '/');
    $stamp = is_file($absolute) ? (string) filemtime($absolute) : '0';
    return '/admin/assets/' . ltrim($relative, '/') . '?v=' . $stamp;
}; ?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>SEO Mini CMS</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($adminAssetVersion('admin.css')) ?>">
</head>
<body>
<?php $requestUri = $_SERVER['REQUEST_URI'] ?? ''; ?>
<?php $homepageEditUrl = '/'; ?>
<?php $homepagePreviewUrl = '/?cms_preview=1'; ?>
<div class="admin-loading-bar" data-admin-loading-bar aria-hidden="true">
  <div class="admin-loading-bar__progress" data-admin-loading-bar-progress></div>
</div>
<div class="layout">
  <nav class="sidebar">
    <div class="sidebar-logo">SEO CMS</div>
    <ul class="nav-list">
      <li><a href="/admin" class="<?= $requestUri === '/admin' ? 'active' : '' ?>">Dashboard</a></li>
      <li><a href="/admin/pages" class="<?= strpos($requestUri, '/admin/pages') === 0 ? 'active' : '' ?>">Stranky</a></li>
      <li><a href="/admin/layouts" class="<?= (strpos($requestUri, '/admin/layouts') === 0 || strpos($requestUri, '/admin/templates') === 0) ? 'active' : '' ?>">Layouty stranek</a></li>
      <li><a href="/admin/shablony" class="<?= (strpos($requestUri, '/admin/shablony') === 0 || strpos($requestUri, '/admin/themes') === 0) ? 'active' : '' ?>">Sablony</a></li>
      <li><a href="/admin/media" class="<?= strpos($requestUri, '/admin/media') === 0 ? 'active' : '' ?>">Media</a></li>
      <li><a href="/admin/blog" class="<?= strpos($requestUri, '/admin/blog') === 0 ? 'active' : '' ?>">Blog - clanky</a></li>
      <li><a href="/admin/portfolio" class="<?= strpos($requestUri, '/admin/portfolio') === 0 ? 'active' : '' ?>">Naše práce</a></li>
      <li><a href="/admin/shared-sections" class="<?= strpos($requestUri, '/admin/shared-sections') === 0 ? 'active' : '' ?>">Sdilene sekce</a></li>
      <li><a href="/admin/reviews" class="<?= strpos($requestUri, '/admin/reviews') === 0 ? 'active' : '' ?>">Recenze</a></li>
      <li><a href="/admin/redirects" class="<?= strpos($requestUri, '/admin/redirects') === 0 ? 'active' : '' ?>">Redirecty</a></li>
      <li><a href="/admin/publish" class="<?= strpos($requestUri, '/admin/publish') === 0 ? 'active' : '' ?>">Publikovani</a></li>
      <li><a href="/admin/security-txt" class="<?= strpos($requestUri, '/admin/security-txt') === 0 ? 'active' : '' ?>">Security.txt</a></li>
      <li><a href="/admin/settings" class="<?= strpos($requestUri, '/admin/settings') === 0 ? 'active' : '' ?>">Nastaveni</a></li>
      <li><a href="/admin/gtm" class="<?= strpos($requestUri, '/admin/gtm') === 0 ? 'active' : '' ?>">Google Tag Manager</a></li>
      <li><a href="/admin/setup" class="<?= strpos($requestUri, '/admin/setup') === 0 ? 'active' : '' ?>">Pocatecni data</a></li>
      <?php if (($_user['role'] ?? '') === 'admin'): ?><li><a href="/admin/messages" class="<?= str_starts_with($requestUri, '/admin/messages') ? 'active' : '' ?>">E-maily a zprávy</a></li><?php endif; ?>
    </ul>
    <div style="padding:12px 16px 0;display:flex;flex-direction:column;gap:8px">
      <a href="<?= htmlspecialchars($homepageEditUrl) ?>" target="_blank" class="btn btn-inline-edit" style="width:100%;justify-content:center;box-sizing:border-box">&#9998; Editovat inline</a>
      <a href="<?= htmlspecialchars($homepagePreviewUrl) ?>" target="_blank" class="btn btn-secondary" style="width:100%;justify-content:center;box-sizing:border-box">&#128065; Nahled homepage</a>
    </div>
    <div class="sidebar-footer">
      <a href="/admin/account/password">Změna hesla</a>
      <span><?= htmlspecialchars($_user['email'] ?? '') ?></span>
      <form method="POST" action="/admin/logout" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrfToken ?? '') ?>">
        <button type="submit" class="btn-link">Odhlásit</button>
      </form>
      <div class="sidebar-version"><?= \App\Core\Version::APP ?></div>
    </div>
  </nav>

  <div class="main-wrap">
    <header class="topbar">
      <div class="topbar-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard' ?></div>
      <div class="topbar-actions">
        <a href="<?= htmlspecialchars($homepagePreviewUrl) ?>" target="_blank" class="btn btn-sm btn-secondary">&#128065; Nahled</a>
        <a href="<?= htmlspecialchars($homepageEditUrl) ?>" target="_blank" class="btn btn-sm btn-inline-edit">&#9998; Editace inline</a>
      </div>
    </header>
    <main class="content">
      <div class="flash-stack">
        <?php if (!empty($success)): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
          <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
      </div>
      <?= $slot ?? '' ?>
    </main>
  </div>
</div>
<div class="admin-media-picker" id="admin-media-picker" aria-hidden="true">
  <div class="admin-media-picker__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-media-picker-title">
    <div class="admin-media-picker__head">
      <div><strong id="admin-media-picker-title">Vybrat z médií</strong><div class="form-hint">Knihovna se načítá až po otevření tohoto okna.</div></div>
      <button type="button" class="btn btn-secondary" data-picker-close>Zavřít</button>
    </div>
    <form class="admin-media-picker__search" data-picker-search><input type="search" placeholder="Hledat podle názvu nebo ALT textu" aria-label="Hledat média"><button class="btn btn-secondary" type="submit">Hledat</button></form>
    <div class="admin-media-picker__status" data-picker-status></div>
    <div class="admin-media-picker__grid" data-picker-grid></div>
    <div class="admin-media-picker__foot"><button type="button" class="btn btn-secondary" data-picker-prev>Předchozí</button><span data-picker-page></span><button type="button" class="btn btn-secondary" data-picker-next>Další</button><button type="button" class="btn btn-primary" data-picker-confirm>Potvrdit výběr</button></div>
  </div>
</div>
<script src="<?= htmlspecialchars($adminAssetVersion('admin.js')) ?>"></script>
</body>
</html>
