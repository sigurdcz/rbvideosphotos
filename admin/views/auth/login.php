<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Přihlášení - SEO Mini CMS</title>
  <link rel="stylesheet" href="/admin/assets/admin.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">SEO Mini CMS</div>
    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="/admin/login">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrfToken) ?>">
      <div class="form-group">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" required autofocus autocomplete="email">
      </div>
      <div class="form-group">
        <label for="password">Heslo</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Přihlásit se</button>
    </form>
    <div style="display:flex;justify-content:center;margin-top:20px">
      <a href="/" class="btn btn-secondary">&#8592; Zpět na homepage</a>
    </div>
  </div>
</div>
</body>
</html>
