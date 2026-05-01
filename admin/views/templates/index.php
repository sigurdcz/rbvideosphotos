<?php
$pageTitle = 'Šablony';
ob_start();
?>
<div class="page-header">
  <h1>Šablony</h1>
  <a href="/admin/templates/create" class="btn btn-primary">+ Nová šablona</a>
</div>

<?php if (empty($templates)): ?>
  <div class="card"><p style="color:var(--c-muted)">Žádné šablony. <a href="/admin/templates/create">Vytvořte první</a>.</p></div>
<?php else: ?>
<table>
  <thead><tr><th>Název</th><th>ID</th><th>Vytvořeno</th><th>Akce</th></tr></thead>
  <tbody>
    <?php foreach ($templates as $t): ?>
    <tr>
      <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
      <td>#<?= (int)$t['id'] ?></td>
      <td><?= htmlspecialchars(substr($t['created_at'], 0, 10)) ?></td>
      <td>
        <div class="actions">
          <a href="/admin/templates/edit?id=<?= $t['id'] ?>" class="btn btn-sm btn-secondary">Upravit</a>
          <form method="POST" action="/admin/templates/delete" data-confirm="Smazat šablonu?">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrfToken) ?>">
            <input type="hidden" name="id" value="<?= $t['id'] ?>">
            <button class="btn btn-sm btn-danger">Smazat</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
