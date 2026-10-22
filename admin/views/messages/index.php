<?php
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$labels = ['stored' => 'Uloženo - výsledek odeslání nepotvrzen', 'accepted' => 'Převzato poštovní službou - doručení neověřeno', 'failed' => 'Odeslání selhalo', 'spam' => 'Zachyceno statickým antispamem'];
$pageTitle = 'E-maily a zprávy';
$pageUrl = static fn(int $number): string => '?' . http_build_query(['status' => $status, 'kind' => $kind, 'page' => $number]);
ob_start(); ?>
<div class="page-header"><h1>E-maily a zprávy</h1></div>
<p>Kontaktní zprávy se ukládají před odesíláním. Přijetí poštovní službou nepotvrzuje doručení do schránky. Obsah obnovy hesla se z bezpečnostních důvodů neukládá.</p>
<p>Nalezeno: <?= $h($total) ?>. Archiv obsahuje osobní údaje; přístup má pouze administrátor. Zálohujte databázi.</p>
<p>Všechny pokusy: <strong><?= $h(array_sum($counts)) ?></strong> · Předáno poštovní službě: <strong><?= $h($counts['accepted']) ?></strong> · Zachycený spam: <strong><?= $h($counts['spam']) ?></strong> · Selhalo: <strong><?= $h($counts['failed']) ?></strong> · Výsledek nepotvrzen: <strong><?= $h($counts['stored']) ?></strong></p>
<form method="get" action="/admin/messages" style="display:flex;gap:12px;flex-wrap:wrap;margin:20px 0">
<label>Stav <select name="status"><option value="">Všechny stavy</option><?php foreach ($labels as $value => $label): ?><option value="<?= $h($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?></select></label>
<label>Typ <select name="kind"><option value="">Všechny typy</option><option value="contact" <?= $kind === 'contact' ? 'selected' : '' ?>>Kontaktní formulář</option><option value="password_reset" <?= $kind === 'password_reset' ? 'selected' : '' ?>>Obnova hesla</option></select></label>
<button type="submit" class="btn btn-primary">Filtrovat</button><a href="/admin/messages" class="btn btn-secondary">Zrušit filtry</a>
</form>
<?php foreach ($rows as $row): ?>
<details class="card" style="padding:16px;margin-bottom:12px">
<summary>#<?= $h($row['id']) ?> · <?= $h($row['created_at']) ?> UTC · <?= $h($row['subject']) ?> · <?= $h($labels[$row['status']] ?? $row['status']) ?></summary>
<p>Typ: <?= $h(['contact' => 'Kontaktní formulář', 'password_reset' => 'Obnova hesla'][$row['kind']] ?? $row['kind']) ?> · Příjemce: <?= $h($row['recipient']) ?> · Chyba: <?= $h($row['error_code'] ?? '-') ?></p>
<p>Poslední pokus: <?= $h($row['attempted_at'] ?? 'Nepotvrzen') ?> UTC</p>
<p>Kopie (CC): <?= $h($row['cc_recipient'] ?? 'Bez kopie') ?></p>
<pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?= $h($row['body'] ?? 'Citlivý obsah obnovy hesla se nearchivuje.') ?></pre>
</details>
<?php endforeach; ?>
<?php if ($rows === []): ?><p>Pro zvolené filtry a stránku nebyly nalezeny žádné zprávy.</p><?php endif; ?>
<p><?php if ($page > 1): ?><a href="<?= $h($pageUrl($page - 1)) ?>">Novější</a><?php endif; ?>
<?php if ($page * 50 < $total): ?><a href="<?= $h($pageUrl($page + 1)) ?>">Starší</a><?php endif; ?></p>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
