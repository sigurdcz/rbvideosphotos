<?php
use App\Services\Mailbox\MailboxView;

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageTitle = (string) $thread['subject'];
$activeTab = 'inbox';
$out = $state['outbound'];
$lastIndex = count($messages) - 1;
$initial = static function (string $name): string {
    $name = trim($name);
    return $name === '' ? '?' : mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
};
ob_start();
require_once __DIR__ . '/_icons.php'; ?>
<div class="mail-head">
  <a class="mail-iconbtn" href="/admin/mail" title="Zpět na konverzace" aria-label="Zpět na konverzace"><?= mailIcon('back') ?></a>
  <h1 class="mail-head__subject"><?= $h($thread['subject']) ?></h1>
  <?php if ($state['answered']): ?><span class="mail-chip mail-chip--ok"><?= mailIcon('check', 12) ?> Zodpovězeno</span>
  <?php elseif ($state['last_inbound_id'] > 0): ?><span class="mail-chip mail-chip--alert">Čeká na odpověď</span><?php endif; ?>
  <form method="post" action="/admin/mail/thread/unread" class="mail-head__action">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <input type="hidden" name="id" value="<?= (int) $thread['id'] ?>">
    <button type="submit" class="mail-linkbtn">Označit jako nepřečtené</button>
  </form>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>

<div class="mail-banner mail-banner--answered" data-reply-banner role="alert" <?= $state['answered'] ? '' : 'hidden' ?>>
  <span class="mail-banner__icon"><?= mailIcon('alert', 20) ?></span>
  <div class="mail-banner__text">
    <strong data-banner-title>Na tuto zprávu už bylo odpovězeno.</strong>
    <div data-banner-detail>
      <?php if ($out): ?>
        <?= $h($out['author_label'] ?: 'Někdo z týmu') ?> odpověděl(a) <?= $h(MailboxView::date($out['sent_at'])) ?><?= $out['status'] === 'sending' ? ' (právě se odesílá)' : '' ?>:
        <blockquote><?= $h(mb_substr(trim((string) $out['body_text']), 0, 400, 'UTF-8')) ?></blockquote>
      <?php endif; ?>
    </div>
    <small>Před odesláním další odpovědi se systém ještě zeptá.</small>
  </div>
</div>
<div class="mail-banner mail-banner--new" data-new-inbound-banner role="status" hidden>
  <span class="mail-banner__icon"><?= mailIcon('refresh', 18) ?></span>
  <div class="mail-banner__text"><strong>V konverzaci mezitím přišla nová zpráva.</strong> <a href="">Načíst znovu</a> (koncept se uloží automaticky).</div>
</div>

<div class="mail-thread" data-mail-thread data-thread-id="<?= (int) $thread['id'] ?>">
  <?php foreach ($messages as $index => $m): ?>
    <?php
    $name = (string) ($m['from_name'] ?: $m['from_addr']);
    $files = array_filter($m['attachments'], static function ($a) use ($m) {
        $html = (string) ($m['body_html'] ?? '');
        return !$a['is_inline'] || $a['content_id'] === null || $html === '' || stripos($html, 'cid:' . $a['content_id']) === false;
    });
    [$fresh, $quoted] = MailboxView::splitQuoted((string) $m['body_text']);
    $summary = trim(preg_replace('/\s+/u', ' ', $fresh) ?? '');
    ?>
    <details class="mail-msg mail-msg--<?= $m['direction'] === 'out' ? 'out' : 'in' ?>" id="m<?= (int) $m['id'] ?>" <?= $index === $lastIndex || $index === 0 ? 'open' : '' ?>>
      <summary>
        <span class="mail-avatar mail-avatar--<?= $m['direction'] === 'out' ? 'out' : 'in' ?>" aria-hidden="true"><?= $h($initial($name)) ?></span>
        <span class="mail-msg__from"><strong><?= $h($name) ?></strong><?php if ($m['direction'] === 'out' && $m['author_label']): ?><span class="mail-chip mail-chip--ok"><?= $h($m['author_label']) ?></span><?php endif; ?></span>
        <span class="mail-msg__peek"><?= $h(mb_substr($summary, 0, 140, 'UTF-8')) ?></span>
        <?php if ($files): ?><span class="mail-msg__clip" title="Přílohy"><?= mailIcon('clip', 14) ?></span><?php endif; ?>
        <time><?= $h(MailboxView::date($m['sent_at'], 'j. n. Y H:i')) ?></time>
      </summary>
      <div class="mail-msg__body">
        <div class="mail-msg__meta">
          <?= $m['from_name'] ? $h($m['from_addr']) . ' · ' : '' ?>Komu: <?= $h($m['to_addrs']) ?><?= $m['cc_addrs'] ? ' · Cc: ' . $h($m['cc_addrs']) : '' ?><?= $m['bcc_addrs'] ? ' · Bcc: ' . $h($m['bcc_addrs']) : '' ?>
        </div>
        <?php if ($m['safe_html'] !== null && $m['safe_html'] !== ''): ?>
          <div class="mail-html"><?= $m['safe_html'] /* sanitized by MailHtmlSanitizer */ ?></div>
          <?php if (!empty($m['has_remote_images'])): ?><p class="mail-note">Vzdálené obrázky jsou zablokované (ochrana soukromí). <a href="?id=<?= (int) $thread['id'] ?>&images=1#m<?= (int) $m['id'] ?>">Zobrazit obrázky</a></p><?php endif; ?>
        <?php else: ?>
          <pre class="mail-text"><?= $h($fresh) ?></pre>
          <?php if ($quoted !== ''): ?>
            <details class="mail-quote"><summary>Zobrazit citovaný text</summary><pre class="mail-text"><?= $h($quoted) ?></pre></details>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($files): ?>
          <ul class="mail-files">
            <?php foreach ($files as $a): ?>
              <li><a href="/admin/mail/attachment?id=<?= (int) $a['id'] ?>"><?= mailIcon('clip', 13) ?> <?= $h($a['filename']) ?> <small><?= $h(MailboxView::size((int) $a['size'])) ?></small></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </details>
  <?php endforeach; ?>
</div>

<section class="mail-compose">
  <header class="mail-compose__head">
    <strong>Odpověď</strong>
    <?php if ($mode === 'all'): ?><a class="mail-linkbtn" href="?id=<?= (int) $thread['id'] ?>">jen odesílateli</a><?php else: ?><a class="mail-linkbtn" href="?id=<?= (int) $thread['id'] ?>&mode=all">odpovědět všem</a><?php endif; ?>
  </header>
  <?php require __DIR__ . '/_form.php'; ?>
</section>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
