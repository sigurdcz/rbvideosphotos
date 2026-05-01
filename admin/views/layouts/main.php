<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>SEO Mini CMS</title>
  <link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<div class="layout">
  <nav class="sidebar">
    <div class="sidebar-logo">SEO CMS</div>
    <ul class="nav-list">
      <li><a href="/admin" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin') && $_SERVER['REQUEST_URI'] === '/admin' ? 'active' : '' ?>">Dashboard</a></li>
      <li><a href="/admin/pages" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin/pages') ? 'active' : '' ?>">Stránky</a></li>
      <li><a href="/admin/templates" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin/templates') ? 'active' : '' ?>">Šablony</a></li>
      <li><a href="/admin/media" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin/media') ? 'active' : '' ?>">Média</a></li>
      <li><a href="/admin/redirects" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin/redirects') ? 'active' : '' ?>">Redirecty</a></li>
      <li><a href="/admin/publish" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin/publish') ? 'active' : '' ?>">Publikování</a></li>
      <li><a href="/admin/settings" class="<?= str_starts_with($_SERVER['REQUEST_URI'], '/admin/settings') ? 'active' : '' ?>">Nastavení</a></li>
    </ul>
    <div class="sidebar-footer">
      <span><?= htmlspecialchars($_user['email'] ?? '') ?></span>
      <form method="POST" action="/admin/logout" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrfToken ?? '') ?>">
        <button type="submit" class="btn-link">Odhlásit</button>
      </form>
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
