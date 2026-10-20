<?php
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageTitle = 'Nová zpráva';
$activeTab = 'compose';
$thread = null;
$state = null;
ob_start(); ?>
<?php require __DIR__ . '/_tabs.php'; ?>
<section class="mail-compose mail-compose--new">
  <?php require __DIR__ . '/_form.php'; ?>
</section>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
