<?php
use App\Services\Mailbox\MailboxView;

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageTitle = 'Pošta';
$activeTab = 'inbox';
$filters = ['all' => 'Vše', 'unanswered' => 'Čeká na odpověď', 'unread' => 'Nepřečtené', 'answered' => 'Zodpovězené'];
$pageUrl = static fn(int $n): string => '?' . http_build_query(['filter' => $filter, 'q' => $query, 'page' => $n]);
$lastError = null;
$lastOk = null;
foreach ($syncStates as $state) {
    if (!empty($state['last_error'])) { $lastError = $state['last_error']; }
    if (!empty($state['last_success_at']) && ($lastOk === null || $state['last_success_at'] > $lastOk)) { $lastOk = $state['last_success_at']; }
}
ob_start(); ?>
<div class="page-header"><h1>Pošta <small class="mail-address"><?= $h($address) ?></small></h1></div>
<?php require __DIR__ . '/_tabs.php'; ?>

<?php if (!$configured): ?>
  <div class="alert alert-warning">Pošta ještě není nastavená. Doplňte do <code>.env</code> údaje <code>SMTP_*</code> (a případně <code>IMAP_HOST</code>); viz <code>.env.example</code>.</div>
<?php elseif ($lastError): ?>
  <div class="alert alert-error">Poslední synchronizace schránky selhala (<?= $h($lastError) ?>). Zobrazená pošta nemusí být aktuální, ochrana proti dvojité odpovědi proto také ne. <?php if ($lastOk): ?>Naposledy se to povedlo <?= $h(MailboxView::date($lastOk)) ?>.<?php endif; ?></div>
<?php endif; ?>

<div class="mail-toolbar">
  <form method="get" action="/admin/mail" class="mail-search">
    <input type="hidden" name="filter" value="<?= $h($filter) ?>">
    <input type="search" name="q" value="<?= $h($query) ?>" placeholder="Hledat v předmětu, odesílateli, textu" aria-label="Hledat v poště">
    <button class="btn btn-secondary" type="submit">Hledat</button>
  </form>
  <form method="post" action="/admin/mail/sync">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <button class="btn btn-secondary" type="submit" <?= $configured ? '' : 'disabled' ?>>Načíst novou poštu</button>
    <?php if ($lastOk): ?><span class="form-hint">Naposledy <?= $h(MailboxView::date($lastOk, 'j. n. H:i')) ?></span><?php endif; ?>
  </form>
</div>

<div class="mail-filters" role="tablist">
  <?php foreach ($filters as $key => $label): ?>
    <a href="?<?= $h(http_build_query(['filter' => $key, 'q' => $query])) ?>" class="<?= $filter === $key ? 'active' : '' ?>"><?= $h($label) ?>
      <?php if ($key === 'unanswered' && $counters['awaiting'] > 0): ?><span class="mail-count mail-count--alert"><?= (int) $counters['awaiting'] ?></span><?php endif; ?>
      <?php if ($key === 'unread' && $counters['unread'] > 0): ?><span class="mail-count"><?= (int) $counters['unread'] ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($rows === []): ?>
  <div class="card"><p>Žádné konverzace<?= $query !== '' || $filter !== 'all' ? ' pro zvolený filtr' : ' - zkuste „Načíst novou poštu“' ?>.</p></div>
<?php else: ?>
<div class="mail-list">
  <?php foreach ($rows as $row): ?>
    <a class="mail-row <?= $row['is_unread'] ? 'is-unread' : '' ?> <?= $row['awaiting_reply'] ? 'is-awaiting' : '' ?>" href="/admin/mail/thread?id=<?= (int) $row['id'] ?>">
      <span class="mail-row__who"><?= $h($row['counterparty'] ?: '-') ?></span>
      <span class="mail-row__main">
        <strong><?= $h($row['subject']) ?></strong>
        <?php if ($row['message_count'] > 1): ?><span class="mail-count"><?= (int) $row['message_count'] ?></span><?php endif; ?>
        <span class="mail-row__snippet"><?= $h(mb_substr((string) $row['snippet'], 0, 140, 'UTF-8')) ?></span>
      </span>
      <span class="mail-row__flags">
        <?php if ($row['draft_id']): ?><span class="badge badge-draft">Koncept</span><?php endif; ?>
        <?php if ($row['awaiting_reply']): ?><span class="badge badge-error">Čeká na odpověď</span>
        <?php elseif (!empty($row['last_outbound_at'])): ?><span class="badge badge-success">Zodpovězeno</span><?php endif; ?>
      </span>
      <time class="mail-row__date"><?= $h(MailboxView::shortDate($row['last_message_at'])) ?></time>
    </a>
  <?php endforeach; ?>
</div>
<p class="mail-pager">
  <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="<?= $h($pageUrl($page - 1)) ?>">Novější</a><?php endif; ?>
  <span><?= (int) $page ?> / <?= (int) $pages ?> · <?= (int) $total ?> konverzací</span>
  <?php if ($page < $pages): ?><a class="btn btn-secondary btn-sm" href="<?= $h($pageUrl($page + 1)) ?>">Starší</a><?php endif; ?>
</p>
<?php endif; ?>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
