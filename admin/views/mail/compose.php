<?php
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageTitle = 'Nová zpráva';
$activeTab = 'compose';
$thread = null;
$state = null;
ob_start(); ?>
<div class="page-header"><h1>Nová zpráva</h1></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="card mail-reply">
  <?php require __DIR__ . '/_form.php'; ?>
</div>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
