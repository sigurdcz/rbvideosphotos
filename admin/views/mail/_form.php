<?php
/**
 * Shared reply / compose form.
 * @var array       $form             to, cc, bcc, subject, body, note
 * @var array|null  $draft            existing draft row
 * @var array       $draftAttachments
 * @var array       $templates
 * @var array|null  $thread           null for a new message
 * @var array|null  $state            reply state (thread only)
 */
require_once __DIR__ . '/_icons.php';
$thread = $thread ?? null;
$state = $state ?? null;
$h = $h ?? static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$showBcc = trim((string) $form['bcc']) !== '';
$showCc = trim((string) $form['cc']) !== '' || $showBcc;
?>
<form class="mail-form" id="mail-form" autocomplete="off"
      data-csrf="<?= $h($_csrfToken) ?>"
      data-thread-id="<?= $thread ? (int) $thread['id'] : '' ?>"
      data-draft-id="<?= $draft ? (int) $draft['id'] : '' ?>"
      data-draft-version="<?= $draft ? (int) $draft['version'] : '' ?>"
      data-known-inbound="<?= $state ? (int) $state['last_inbound_id'] : '' ?>"
      data-max-attachment="<?= (int) $maxAttachmentBytes ?>">
  <?php if (!empty($form['note'])): ?><div class="alert alert-warning"><?= $h($form['note']) ?></div><?php endif; ?>
  <div class="mail-form__notice" data-mail-notice role="status" hidden></div>

  <div class="mail-field">
    <label for="mail-to">Komu</label>
    <input type="text" id="mail-to" name="to" value="<?= $h($form['to']) ?>" placeholder="jmeno@example.cz, dalsi@example.cz" required>
    <button type="button" class="mail-linkbtn" data-mail-toggle="cc-row" <?= $showCc ? 'hidden' : '' ?>>Cc / Bcc</button>
  </div>
  <div class="mail-field" data-mail-row="cc-row" <?= $showCc ? '' : 'hidden' ?>>
    <label for="mail-cc">Cc</label>
    <input type="text" id="mail-cc" name="cc" value="<?= $h($form['cc']) ?>">
  </div>
  <div class="mail-field" data-mail-row="cc-row" <?= $showCc ? '' : 'hidden' ?>>
    <label for="mail-bcc">Bcc</label>
    <input type="text" id="mail-bcc" name="bcc" value="<?= $h($form['bcc']) ?>">
  </div>
  <div class="mail-field">
    <label for="mail-subject">Předmět</label>
    <input type="text" id="mail-subject" name="subject" value="<?= $h($form['subject']) ?>" maxlength="500" required>
  </div>

  <textarea id="mail-body" name="body" rows="11" aria-label="Text zprávy" placeholder="Napište zprávu…" required><?= $h($form['body']) ?></textarea>

  <ul class="mail-attachments" data-mail-attachments>
    <?php foreach ($draftAttachments as $a): ?>
      <li data-attachment-id="<?= (int) $a['id'] ?>"><?= mailIcon('clip', 13) ?><span><?= $h($a['filename']) ?> <small><?= $h(\App\Services\Mailbox\MailboxView::size((int) $a['size'])) ?></small></span>
        <button type="button" class="mail-linkbtn" data-attachment-remove>Odebrat</button></li>
    <?php endforeach; ?>
  </ul>

  <div class="mail-form__footer">
    <div class="mail-form__buttons">
      <button type="submit" class="btn btn-primary" data-mail-send><?= mailIcon('send', 15) ?> Odeslat</button>
      <button type="button" class="btn btn-secondary" data-mail-save>Uložit koncept</button>
      <label class="btn btn-secondary mail-filebtn" title="Přidat přílohu (nejvýše <?= $h(\App\Services\Mailbox\MailboxView::size((int) $maxAttachmentBytes)) ?> na soubor)">
        <?= mailIcon('clip', 15) ?> Příloha<input type="file" data-mail-file multiple hidden>
      </label>
      <?php if ($templates): ?>
      <select class="mail-select" data-mail-template aria-label="Vložit šablonu odpovědi">
        <option value="">Šablona…</option>
        <?php foreach ($templates as $tpl): ?><option value="<?= (int) $tpl['id'] ?>"><?= $h($tpl['name']) ?></option><?php endforeach; ?>
      </select>
      <?php endif; ?>
      <button type="button" class="mail-linkbtn mail-linkbtn--danger" data-mail-discard <?= $draft ? '' : 'hidden' ?>>Zahodit koncept</button>
    </div>
    <div class="mail-form__meta">
      <span class="mail-form__status" data-mail-status aria-live="polite"><?= $draft ? 'Koncept uložen ' . $h(\App\Services\Mailbox\MailboxView::date($draft['updated_at'], 'j. n. H:i')) . ($draft['author_label'] ? ' (' . $h($draft['author_label']) . ')' : '') : '' ?></span>
      <label class="mail-form__as">Jako <input type="text" id="mail-author" name="author" maxlength="100" placeholder="vaše jméno" data-mail-author title="Zobrazí se kolegům u vaší odpovědi. Uloží se jen v tomto prohlížeči."></label>
    </div>
  </div>
</form>

<div class="mail-modal" data-mail-modal hidden>
  <div class="mail-modal__dialog" role="alertdialog" aria-modal="true" aria-labelledby="mail-modal-title">
    <div class="mail-modal__icon"><?= mailIcon('alert', 22) ?></div>
    <h2 id="mail-modal-title">Na zprávu už bylo odpovězeno</h2>
    <div class="mail-modal__body" data-mail-modal-body></div>
    <div class="mail-modal__actions">
      <button type="button" class="btn btn-secondary" data-mail-modal-cancel>Zrušit, neodesílat</button>
      <button type="button" class="btn btn-danger" data-mail-modal-confirm>Přesto odeslat</button>
    </div>
  </div>
</div>

<script type="application/json" data-mail-templates><?= json_encode(array_column($templates, 'body', 'id'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?></script>
