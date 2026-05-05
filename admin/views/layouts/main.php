<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>SEO Mini CMS</title>
  <link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<?php $requestUri = $_SERVER['REQUEST_URI'] ?? ''; ?>
<div class="layout">
  <nav class="sidebar">
    <div class="sidebar-logo">SEO CMS</div>
    <ul class="nav-list">
      <li><a href="/admin" class="<?= $requestUri === '/admin' ? 'active' : '' ?>">Dashboard</a></li>
      <li><a href="/admin/pages" class="<?= strpos($requestUri, '/admin/pages') === 0 ? 'active' : '' ?>">Stránky</a></li>
      <li><a href="/admin/templates" class="<?= strpos($requestUri, '/admin/templates') === 0 ? 'active' : '' ?>">Šablony</a></li>
      <li><a href="/admin/media" class="<?= strpos($requestUri, '/admin/media') === 0 ? 'active' : '' ?>">Média</a></li>
      <li><a href="/admin/blog" class="<?= strpos($requestUri, '/admin/blog') === 0 ? 'active' : '' ?>">Blog — články</a></li>
      <li><a href="/admin/portfolio" class="<?= strpos($requestUri, '/admin/portfolio') === 0 ? 'active' : '' ?>">Naše práce</a></li>
      <li><a href="/admin/redirects" class="<?= strpos($requestUri, '/admin/redirects') === 0 ? 'active' : '' ?>">Redirecty</a></li>
      <li><a href="/admin/publish" class="<?= strpos($requestUri, '/admin/publish') === 0 ? 'active' : '' ?>">Publikování</a></li>
      <li><a href="/admin/settings" class="<?= strpos($requestUri, '/admin/settings') === 0 ? 'active' : '' ?>">Nastavení</a></li>
      <li><a href="/admin/setup" class="<?= strpos($requestUri, '/admin/setup') === 0 ? 'active' : '' ?>">Počáteční data</a></li>
    </ul>
    <div style="padding: 12px 16px 0">
      <a href="/" target="_blank" class="btn btn-inline-edit" style="width:100%;justify-content:center;box-sizing:border-box">&#9998; Editovat web</a>
    </div>
    <div class="sidebar-footer">
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
    </header>
    <main class="content">
      <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?= $slot ?? '' ?>
    </main>
  </div>
</div>
<script src="/admin/assets/admin.js"></script>
</body>
</html>
