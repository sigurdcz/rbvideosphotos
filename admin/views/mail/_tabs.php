<?php
/** Shared top bar: section tabs on the left, refresh + compose on the right. @var array $counters @var string $activeTab */
require_once __DIR__ . '/_icons.php';
$counters = $counters ?? ['unread' => 0, 'awaiting' => 0, 'drafts' => 0];
$activeTab = $activeTab ?? 'inbox';
$csrf = htmlspecialchars((string) ($_csrfToken ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="mail-bar">
  <nav class="mail-tabs" aria-label="Pošta">
    <a href="/admin/mail" class="<?= $activeTab === 'inbox' ? 'active' : '' ?>">Konverzace<?php if ($counters['awaiting'] > 0): ?><span class="mail-pill mail-pill--alert" title="Čeká na odpověď"><?= (int) $counters['awaiting'] ?></span><?php endif; ?></a>
    <a href="/admin/mail/drafts" class="<?= $activeTab === 'drafts' ? 'active' : '' ?>">Koncepty<?php if ($counters['drafts'] > 0): ?><span class="mail-pill"><?= (int) $counters['drafts'] ?></span><?php endif; ?></a>
    <a href="/admin/mail/templates" class="<?= $activeTab === 'templates' ? 'active' : '' ?>">Šablony odpovědí</a>
  </nav>
  <div class="mail-bar__actions">
    <form method="post" action="/admin/mail/sync">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <button class="mail-iconbtn" type="submit" title="Načíst novou poštu" aria-label="Načíst novou poštu"><?= mailIcon('refresh') ?></button>
    </form>
    <a href="/admin/mail/compose" class="btn btn-primary btn-sm"><?= mailIcon('plus', 14) ?> Nová zpráva</a>
  </div>
</div>
