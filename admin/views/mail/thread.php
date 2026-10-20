<?php
use App\Services\Mailbox\MailboxView;

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageTitle = (string) $thread['subject'];
$activeTab = 'inbox';
$out = $state['outbound'];
ob_start(); ?>
<div class="page-header">
  <h1><?= $h($thread['subject']) ?></h1>
  <a class="btn btn-secondary btn-sm" href="/admin/mail">← Zpět na konverzace</a>
</div>
<?php require __DIR__ . '/_tabs.php'; ?>

<div class="mail-banner mail-banner--answered" data-reply-banner role="alert" <?= $state['answered'] ? '' : 'hidden' ?>>
  <strong data-banner-title>Na tuto zprávu už bylo odpovězeno.</strong>
  <div data-banner-detail>
    <?php if ($out): ?>
      <?= $h($out['author_label'] ?: 'Někdo z týmu') ?> odpověděl(a) <?= $h(MailboxView::date($out['sent_at'])) ?><?= $out['status'] === 'sending' ? ' (právě se odesílá)' : '' ?>:
      <blockquote><?= $h(mb_substr(trim((string) $out['body_text']), 0, 400, 'UTF-8')) ?></blockquote>
    <?php endif; ?>
  </div>
  <small>Než odešlete další odpověď, zkontrolujte, že to dává smysl. Při odeslání se vás systém ještě zeptá.</small>
</div>
<div class="mail-banner mail-banner--new" data-new-inbound-banner role="status" hidden>
  <strong>V konverzaci mezitím přišla nová zpráva.</strong> <a href="">Načíst znovu</a> (koncept se uloží automaticky).
</div>

<div class="mail-thread" data-mail-thread data-thread-id="<?= (int) $thread['id'] ?>">
  <?php foreach ($messages as $m): ?>
    <article class="mail-msg mail-msg--<?= $m['direction'] === 'out' ? 'out' : 'in' ?>" id="m<?= (int) $m['id'] ?>">
      <header>
        <div>
          <strong><?= $h($m['from_name'] ?: $m['from_addr']) ?></strong>
          <?php if ($m['from_name']): ?><span class="mail-msg__addr">&lt;<?= $h($m['from_addr']) ?>&gt;</span><?php endif; ?>
          <?php if ($m['direction'] === 'out'): ?><span class="badge badge-success">Odeslané<?= $m['author_label'] ? ' · ' . $h($m['author_label']) : '' ?></span><?php endif; ?>
        </div>
        <time><?= $h(MailboxView::date($m['sent_at'])) ?></time>
      </header>
      <div class="mail-msg__meta">Komu: <?= $h($m['to_addrs']) ?><?= $m['cc_addrs'] ? ' · Kopie: ' . $h($m['cc_addrs']) : '' ?><?= $m['bcc_addrs'] ? ' · Skrytá kopie: ' . $h($m['bcc_addrs']) : '' ?></div>
      <?php if ($m['safe_html'] !== null && $m['safe_html'] !== ''): ?>
        <div class="mail-html"><?= $m['safe_html'] /* sanitized by MailHtmlSanitizer */ ?></div>
        <?php if (!empty($m['has_remote_images'])): ?><p class="form-hint">Vzdálené obrázky jsou zablokované (ochrana soukromí). <a href="?id=<?= (int) $thread['id'] ?>&images=1#m<?= (int) $m['id'] ?>">Zobrazit obrázky</a></p><?php endif; ?>
      <?php else: ?>
        <pre class="mail-text"><?= $h($m['body_text']) ?></pre>
      <?php endif; ?>
      <?php // Hide only inline images the rendered HTML actually references; others (e.g. photos from Apple Mail) stay listed.
      $html = (string) ($m['body_html'] ?? '');
      $files = array_filter($m['attachments'], static fn($a) => !$a['is_inline'] || $a['content_id'] === null || $html === '' || stripos($html, 'cid:' . $a['content_id']) === false); ?>
      <?php if ($files): ?>
        <ul class="mail-attachments mail-attachments--read">
          <?php foreach ($files as $a): ?>
            <li><a href="/admin/mail/attachment?id=<?= (int) $a['id'] ?>"><?= $h($a['filename']) ?></a> <small>(<?= $h(MailboxView::size((int) $a['size'])) ?>)</small></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>

<div class="card mail-reply">
  <div class="card-title">Odpověď
    <?php if ($mode === 'all'): ?><a class="btn-link" href="?id=<?= (int) $thread['id'] ?>">jen odesílateli</a><?php else: ?><a class="btn-link" href="?id=<?= (int) $thread['id'] ?>&mode=all">odpovědět všem</a><?php endif; ?>
  </div>
  <?php require __DIR__ . '/_form.php'; ?>
  <form method="post" action="/admin/mail/thread/unread" class="mail-unread-form">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <input type="hidden" name="id" value="<?= (int) $thread['id'] ?>">
    <button type="submit" class="btn-link">Označit jako nepřečtené</button>
  </form>
</div>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
