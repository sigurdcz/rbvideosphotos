<?php $pageTitle = 'Změna hesla'; ob_start(); ?>
<div class="page-header"><h1>Změna hesla</h1></div>
<div class="card" style="padding:24px;max-width:620px">
<p>Přihlášený účet: <strong><?= htmlspecialchars($_user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></p>
<p>Po změně hesla budete odhlášeni na všech zařízeních. Dřívější odkazy pro obnovu hesla přestanou platit.</p>
<form method="post" action="/admin/account/password">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrfToken, ENT_QUOTES, 'UTF-8') ?>">
<div class="form-group"><label for="current-password">Současné heslo</label><input id="current-password" type="password" name="current_password" required autocomplete="current-password"></div>
<div class="form-group"><label for="new-password">Nové heslo</label><input id="new-password" type="password" name="password" required minlength="12" autocomplete="new-password"><small>12 až 72 bajtů. Diakritika zabírá více bajtů.</small></div>
<div class="form-group"><label for="password-confirmation">Nové heslo znovu</label><input id="password-confirmation" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></div>
<button class="btn btn-primary" type="submit">Změnit heslo</button>
</form>
<p><a href="/admin/forgot-password">Nepamatuji si současné heslo</a></p>
</div>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
