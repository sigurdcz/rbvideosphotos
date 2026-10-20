<?php
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageTitle = 'Šablony odpovědí';
$activeTab = 'templates';
ob_start(); ?>
<div class="page-header"><h1>Šablony odpovědí</h1></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="card" style="max-width:720px">
  <div class="card-title"><?= $edit ? 'Upravit šablonu' : 'Nová šablona' ?></div>
  <form method="post" action="/admin/mail/templates/save">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : '' ?>">
    <div class="form-group"><label for="tpl-name">Název</label><input type="text" id="tpl-name" name="name" maxlength="150" value="<?= $h($edit['name'] ?? '') ?>" required></div>
    <div class="form-group"><label for="tpl-body">Text</label><textarea id="tpl-body" name="body" rows="8" required><?= $h($edit['body'] ?? '') ?></textarea></div>
    <button type="submit" class="btn btn-primary">Uložit šablonu</button>
    <?php if ($edit): ?><a class="btn btn-secondary" href="/admin/mail/templates">Zrušit úpravu</a><?php endif; ?>
  </form>
</div>
<?php if ($templates): ?>
<div class="card">
  <div class="card-title">Uložené šablony</div>
  <table>
    <thead><tr><th>Název</th><th>Náhled</th><th>Akce</th></tr></thead>
    <tbody>
    <?php foreach ($templates as $t): ?>
      <tr>
        <td><?= $h($t['name']) ?></td>
        <td><?= $h(mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $t['body']) ?? ''), 0, 100, 'UTF-8')) ?></td>
        <td>
          <a class="btn btn-sm btn-secondary" href="/admin/mail/templates?edit=<?= (int) $t['id'] ?>">Upravit</a>
          <form method="post" action="/admin/mail/templates/delete" style="display:inline" data-confirm="Smazat šablonu?">
            <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button class="btn btn-sm btn-danger">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php $slot = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
