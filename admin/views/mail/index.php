<?php
use App\Services\Mailbox\MailboxView;

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageTitle = 'Pošta';
$activeTab = 'inbox';
$filters = ['all' => 'Vše', 'unanswered' => 'Čeká na odpověď', 'unread' => 'Nepřečtené', 'answered' => 'Zodpovězené'];
$filterCount = ['unanswered' => $counters['awaiting'], 'unread' => $counters['unread']];
$pageUrl = static fn(int $n): string => '?' . http_build_query(['filter' => $filter, 'q' => $query, 'page' => $n]);
$lastError = null;
$lastOk = null;
foreach ($syncStates as $state) {
    if (!empty($state['last_error']) && !str_starts_with((string) $state['last_error'], 'processing:')) { $lastError = $state['last_error']; }
    if (!empty($state['last_success_at']) && ($lastOk === null || $state['last_success_at'] > $lastOk)) { $lastOk = $state['last_success_at']; }
}
$initial = static function (string $name): string {
    $name = trim($name);
    return $name === '' ? '?' : mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
};
ob_start(); ?>
<?php require __DIR__ . '/_tabs.php'; ?>

<?php if (!$configured): ?>
  <div class="alert alert-warning">Pošta ještě není nastavená. Doplňte do <code>.env</code> údaje <code>SMTP_*</code> (případně <code>IMAP_HOST</code>), viz <code>.env.example</code>.</div>
<?php elseif ($lastError): ?>
  <div class="alert alert-error">Poslední synchronizace schránky selhala (<?= $h($lastError) ?>). Seznam nemusí být aktuální a upozornění na dvojitou odpověď také ne.<?php if ($lastOk): ?> Naposledy se to povedlo <?= $h(MailboxView::date($lastOk)) ?>.<?php endif; ?></div>
<?php endif; ?>

<div class="mail-panel">
  <div class="mail-filterbar">
    <form method="get" action="/admin/mail" class="mail-search">
      <input type="hidden" name="filter" value="<?= $h($filter) ?>">
      <span class="mail-search__icon"><?= mailIcon('search', 15) ?></span>
      <input type="search" name="q" value="<?= $h($query) ?>" placeholder="Hledat v poště" aria-label="Hledat v poště">
    </form>
    <div class="mail-seg" role="tablist" aria-label="Filtr konverzací">
      <?php foreach ($filters as $key => $label): ?>
        <a href="?<?= $h(http_build_query(['filter' => $key, 'q' => $query])) ?>" class="<?= $filter === $key ? 'active' : '' ?>"><?= $h($label) ?><?php if (!empty($filterCount[$key])): ?><span class="mail-pill <?= $key === 'unanswered' ? 'mail-pill--alert' : '' ?>"><?= (int) $filterCount[$key] ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($rows === []): ?>
    <div class="mail-empty">
      <?= mailIcon('check', 28) ?>
      <p><?= $query !== '' || $filter !== 'all' ? 'Nic neodpovídá zvolenému filtru.' : 'Zatím tu nejsou žádné konverzace. Zkuste „Načíst novou poštu“.' ?></p>
    </div>
  <?php else: ?>
    <div class="mail-list">
      <?php foreach ($rows as $row): ?>
        <a class="mail-row <?= $row['is_unread'] ? 'is-unread' : '' ?> <?= $row['awaiting_reply'] ? 'is-awaiting' : '' ?>" href="/admin/mail/thread?id=<?= (int) $row['id'] ?>">
          <span class="mail-avatar" aria-hidden="true"><?= $h($initial((string) $row['counterparty'])) ?></span>
          <span class="mail-row__who"><?= $h($row['counterparty'] ?: 'neznámý odesílatel') ?><?php if ($row['message_count'] > 1): ?> <small><?= (int) $row['message_count'] ?></small><?php endif; ?></span>
          <span class="mail-row__main"><strong><?= $h($row['subject']) ?></strong><span class="mail-row__snippet"><?= $h(mb_substr((string) $row['snippet'], 0, 120, 'UTF-8')) ?></span></span>
          <span class="mail-row__flags">
            <?php if ($row['draft_id']): ?><span class="mail-chip mail-chip--draft" title="Rozepsaná odpověď"><?= mailIcon('draft', 12) ?> Koncept</span><?php endif; ?>
            <?php if ($row['awaiting_reply']): ?><span class="mail-chip mail-chip--alert">Čeká na odpověď</span>
            <?php elseif (!empty($row['last_outbound_at'])): ?><span class="mail-chip mail-chip--ok"><?= mailIcon('check', 12) ?> Zodpovězeno</span><?php endif; ?>
          </span>
          <time class="mail-row__date"><?= $h(MailboxView::shortDate($row['last_message_at'])) ?></time>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="mail-listfoot">
      <span><?= $h($address) ?> · <?= (int) $total ?> konverzací<?php if ($lastOk): ?> · synchronizováno <?= $h(MailboxView::date($lastOk, 'j. n. H:i')) ?><?php endif; ?></span>
      <span class="mail-pager">
        <?php if ($page > 1): ?><a href="<?= $h($pageUrl($page - 1)) ?>">Novější</a><?php endif; ?>
        <?= (int) $page ?> / <?= (int) $pages ?>
        <?php if ($page < $pages): ?><a href="<?= $h($pageUrl($page + 1)) ?>">Starší</a><?php endif; ?>
      </span>
    </div>
  <?php endif; ?>
</div>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
