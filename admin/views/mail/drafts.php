<?php
use App\Services\Mailbox\MailboxView;

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageTitle = 'Koncepty';
$activeTab = 'drafts';
ob_start(); ?>
<div class="page-header"><h1>Koncepty</h1></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if ($drafts === []): ?>
  <div class="card"><p>Žádné rozepsané zprávy.</p></div>
<?php else: ?>
<div class="mail-list">
  <?php foreach ($drafts as $d): ?>
    <?php $href = $d['thread_id'] ? '/admin/mail/thread?id=' . (int) $d['thread_id'] : '/admin/mail/compose?draft=' . (int) $d['id']; ?>
    <a class="mail-row" href="<?= $h($href) ?>">
      <span class="mail-row__who"><?= $h($d['to_addrs'] ?: '(bez příjemce)') ?></span>
      <span class="mail-row__main"><strong><?= $h($d['subject'] ?: ($d['thread_subject'] ?? '(bez předmětu)')) ?></strong>
        <span class="mail-row__snippet"><?= $h(mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $d['body']) ?? ''), 0, 140, 'UTF-8')) ?></span></span>
      <span class="mail-row__flags"><span class="badge badge-draft"><?= $d['thread_id'] ? 'Odpověď' : 'Nová zpráva' ?></span><?= $d['author_label'] ? ' <small>' . $h($d['author_label']) . '</small>' : '' ?></span>
      <time class="mail-row__date"><?= $h(MailboxView::date($d['updated_at'], 'j. n. H:i')) ?></time>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
