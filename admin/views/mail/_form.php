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

  <div class="mail-form__author">
    <label for="mail-author">Odpovídá</label>
    <input type="text" id="mail-author" name="author" maxlength="100" placeholder="Vaše jméno (uloží se jen v tomto prohlížeči)" data-mail-author>
  </div>
  <div class="form-group"><label for="mail-to">Komu</label>
    <input type="text" id="mail-to" name="to" value="<?= $h($form['to']) ?>" placeholder="jmeno@example.cz, druha@example.cz" required>
    <button type="button" class="btn-link mail-form__toggle" data-mail-toggle="cc-row" <?= $showCc ? 'hidden' : '' ?>>Přidat kopii / skrytou kopii</button>
  </div>
  <div class="form-group" data-mail-row="cc-row" <?= $showCc ? '' : 'hidden' ?>><label for="mail-cc">Kopie (Cc)</label>
    <input type="text" id="mail-cc" name="cc" value="<?= $h($form['cc']) ?>">
  </div>
  <div class="form-group" data-mail-row="cc-row" <?= $showCc ? '' : 'hidden' ?>><label for="mail-bcc">Skrytá kopie (Bcc)</label>
    <input type="text" id="mail-bcc" name="bcc" value="<?= $h($form['bcc']) ?>">
  </div>
  <div class="form-group"><label for="mail-subject">Předmět</label>
    <input type="text" id="mail-subject" name="subject" value="<?= $h($form['subject']) ?>" maxlength="500" required>
  </div>
  <div class="form-group">
    <div class="mail-form__bodyhead">
      <label for="mail-body">Zpráva</label>
      <?php if ($templates): ?>
      <select data-mail-template aria-label="Vložit šablonu odpovědi">
        <option value="">Vložit šablonu…</option>
        <?php foreach ($templates as $tpl): ?><option value="<?= (int) $tpl['id'] ?>"><?= $h($tpl['name']) ?></option><?php endforeach; ?>
      </select>
      <?php endif; ?>
    </div>
    <textarea id="mail-body" name="body" rows="14" required><?= $h($form['body']) ?></textarea>
  </div>

  <div class="form-group">
    <label>Přílohy</label>
    <ul class="mail-attachments" data-mail-attachments>
      <?php foreach ($draftAttachments as $a): ?>
        <li data-attachment-id="<?= (int) $a['id'] ?>"><span><?= $h($a['filename']) ?> <small>(<?= $h(\App\Services\Mailbox\MailboxView::size((int) $a['size'])) ?>)</small></span>
          <button type="button" class="btn-link" data-attachment-remove>Odebrat</button></li>
      <?php endforeach; ?>
    </ul>
    <input type="file" data-mail-file multiple>
    <span class="form-hint">Jedna příloha nejvýše <?= $h(\App\Services\Mailbox\MailboxView::size((int) $maxAttachmentBytes)) ?>.</span>
  </div>

  <div class="mail-form__actions">
    <button type="submit" class="btn btn-primary" data-mail-send>Odeslat</button>
    <button type="button" class="btn btn-secondary" data-mail-save>Uložit koncept</button>
    <button type="button" class="btn btn-secondary" data-mail-discard <?= $draft ? '' : 'hidden' ?>>Zahodit koncept</button>
    <span class="form-hint" data-mail-status aria-live="polite"><?= $draft ? 'Koncept uložen ' . $h(\App\Services\Mailbox\MailboxView::date($draft['updated_at'], 'j. n. H:i')) . ($draft['author_label'] ? ' (' . $h($draft['author_label']) . ')' : '') : '' ?></span>
  </div>
</form>

<div class="mail-modal" data-mail-modal hidden>
  <div class="mail-modal__dialog" role="alertdialog" aria-modal="true" aria-labelledby="mail-modal-title">
    <h2 id="mail-modal-title">Na zprávu už bylo odpovězeno</h2>
    <div data-mail-modal-body></div>
    <div class="mail-modal__actions">
      <button type="button" class="btn btn-secondary" data-mail-modal-cancel>Zrušit, neodesílat</button>
      <button type="button" class="btn btn-danger" data-mail-modal-confirm>Přesto odeslat</button>
    </div>
  </div>
</div>

<script type="application/json" data-mail-templates><?= json_encode(array_column($templates, 'body', 'id'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?></script>
