<!DOCTYPE html>
<html lang="cs"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#172033">
<title>Obnova hesla - CMS</title><link rel="icon" href="/admin/assets/admin-favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/admin/assets/admin.css"></head>
<body><div class="login-wrap"><div class="login-box">
<h1><?= $reset ? 'Nové heslo' : 'Zapomenuté heslo' ?></h1>
<?php foreach (['error', 'success'] as $kind): if (!empty($$kind)): ?>
<div class="alert alert-<?= $kind ?>"><?= htmlspecialchars($$kind, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; endforeach; ?>
<form method="post" action="<?= $reset ? '/admin/reset-password' : '/admin/forgot-password' ?>">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrfToken, ENT_QUOTES, 'UTF-8') ?>">
<?php if ($reset): ?>
<p>Zadejte nové heslo (12 až 72 bajtů; diakritika zabírá více bajtů).</p>
<div class="form-group"><label for="password">Nové heslo</label><input id="password" name="password" type="password" minlength="12" required autocomplete="new-password"></div>
<div class="form-group"><label for="confirmation">Heslo znovu</label><input id="confirmation" name="password_confirmation" type="password" minlength="12" required autocomplete="new-password"></div>
<?php else: ?>
<p>Odkaz pošleme na e-mail pro příjem formulářů nastavený v administraci. Přihlašovací jméno pouze určuje účet.</p>
<div class="form-group"><label for="email">Přihlašovací jméno</label><input id="email" name="email" type="text" maxlength="255" required autocomplete="username"></div>
<?php endif; ?>
<button class="btn btn-primary" type="submit"><?= $reset ? 'Uložit nové heslo' : 'Poslat odkaz' ?></button>
</form><p><a href="/admin/login">Zpět na přihlášení</a></p>
</div></div></body></html>
