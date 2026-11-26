<?php $pageTitle = 'Změna přihlašovacího jména'; ob_start(); ?>
<div class="page-header"><h1>Změna přihlašovacího jména</h1></div>
<div class="card" style="padding:24px;max-width:620px">
<p>Nové jméno použijete při příštím přihlášení i při obnově hesla.</p>
<form method="post" action="/admin/account/username">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrfToken, ENT_QUOTES, 'UTF-8') ?>">
<div class="form-group">
    <label for="account-username">Přihlašovací jméno</label>
    <input id="account-username" type="text" name="username" value="<?= htmlspecialchars($_user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required maxlength="255" autocomplete="username">
</div>
<div class="form-group">
    <label for="username-current-password">Současné heslo</label>
    <input id="username-current-password" type="password" name="current_password" required autocomplete="current-password">
</div>
<button class="btn btn-primary" type="submit">Uložit přihlašovací jméno</button>
</form>
</div>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
