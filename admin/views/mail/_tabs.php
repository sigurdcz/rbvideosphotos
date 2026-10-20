<?php
/** @var array $counters @var string $activeTab */
$counters = $counters ?? ['unread' => 0, 'awaiting' => 0, 'drafts' => 0];
$activeTab = $activeTab ?? 'inbox';
?>
<nav class="mail-tabs" aria-label="Pošta">
  <a href="/admin/mail" class="<?= $activeTab === 'inbox' ? 'active' : '' ?>">Konverzace<?php if ($counters['awaiting'] > 0): ?> <span class="mail-count mail-count--alert" title="Čeká na odpověď"><?= (int) $counters['awaiting'] ?></span><?php endif; ?></a>
  <a href="/admin/mail/drafts" class="<?= $activeTab === 'drafts' ? 'active' : '' ?>">Koncepty<?php if ($counters['drafts'] > 0): ?> <span class="mail-count"><?= (int) $counters['drafts'] ?></span><?php endif; ?></a>
  <a href="/admin/mail/templates" class="<?= $activeTab === 'templates' ? 'active' : '' ?>">Šablony odpovědí</a>
  <a href="/admin/mail/compose" class="btn btn-primary btn-sm mail-tabs__new">Nová zpráva</a>
</nav>
