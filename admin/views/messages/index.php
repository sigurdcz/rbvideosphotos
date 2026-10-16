<?php
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$labels = ['stored' => 'Uloženo - výsledek odeslání nepotvrzen', 'accepted' => 'Převzato poštovní službou - doručení neověřeno', 'failed' => 'Odeslání selhalo'];
$pageTitle = 'Zprávy';
ob_start(); ?>
<div class="page-header"><h1>Zprávy</h1></div>
<p>Kontaktní zprávy se ukládají před odesíláním. Přijetí poštovní službou nepotvrzuje doručení do schránky. Obsah obnovy hesla se z bezpečnostních důvodů neukládá.</p>
<p>Celkem: <?= $h($total) ?>. Archiv obsahuje osobní údaje; přístup má pouze administrátor. Zálohujte databázi.</p>
<?php foreach ($rows as $row): ?>
<details class="card" style="padding:16px;margin-bottom:12px">
<summary>#<?= $h($row['id']) ?> · <?= $h($row['created_at']) ?> UTC · <?= $h($row['subject']) ?> · <?= $h($labels[$row['status']] ?? $row['status']) ?></summary>
<p>Typ: <?= $h($row['kind']) ?> · Příjemce: <?= $h($row['recipient']) ?> · Chyba: <?= $h($row['error_code'] ?? '-') ?></p>
<pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?= $h($row['body'] ?? 'Citlivý obsah obnovy hesla se nearchivuje.') ?></pre>
</details>
<?php endforeach; ?>
<?php if ($rows === []): ?><p>Archiv zatím neobsahuje žádné zprávy.</p><?php endif; ?>
<p><?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">Novější</a><?php endif; ?>
<?php if ($page * 50 < $total): ?><a href="?page=<?= $page + 1 ?>">Starší</a><?php endif; ?></p>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
