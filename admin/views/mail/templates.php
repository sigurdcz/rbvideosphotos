<?php
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageTitle = 'Šablony odpovědí';
$activeTab = 'templates';
ob_start(); ?>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="mail-split">
  <section class="mail-panel mail-panel--pad">
    <h2 class="mail-h2"><?= $edit ? 'Upravit šablonu' : 'Nová šablona' ?></h2>
    <form method="post" action="/admin/mail/templates/save" class="mail-form">
      <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
      <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : '' ?>">
      <div class="mail-field"><label for="tpl-name">Název</label><input type="text" id="tpl-name" name="name" maxlength="150" value="<?= $h($edit['name'] ?? '') ?>" required></div>
      <textarea id="tpl-body" name="body" rows="9" aria-label="Text šablony" placeholder="Text šablony" required><?= $h($edit['body'] ?? '') ?></textarea>
      <div class="mail-form__buttons mail-form__buttons--solo">
        <button type="submit" class="btn btn-primary">Uložit šablonu</button>
        <?php if ($edit): ?><a class="btn btn-secondary" href="/admin/mail/templates">Zrušit úpravu</a><?php endif; ?>
      </div>
    </form>
  </section>
  <section class="mail-panel">
    <?php if ($templates === []): ?>
      <div class="mail-empty"><p>Zatím žádné šablony.</p></div>
    <?php else: ?>
      <div class="mail-list">
        <?php foreach ($templates as $t): ?>
          <div class="mail-row mail-row--tpl">
            <span class="mail-row__main"><strong><?= $h($t['name']) ?></strong><span class="mail-row__snippet"><?= $h(mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $t['body']) ?? ''), 0, 110, 'UTF-8')) ?></span></span>
            <span class="mail-row__flags">
              <a class="mail-linkbtn" href="/admin/mail/templates?edit=<?= (int) $t['id'] ?>">Upravit</a>
              <form method="post" action="/admin/mail/templates/delete" data-confirm="Smazat šablonu?">
                <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <button class="mail-linkbtn mail-linkbtn--danger">Smazat</button>
              </form>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
