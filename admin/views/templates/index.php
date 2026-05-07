<?php
$pageTitle = 'Layouty stranek';
ob_start();
?>
<div class="page-header">
  <h1>Layouty stranek</h1>
  <a href="/admin/layouts/create" class="btn btn-primary">+ Novy layout</a>
</div>

<?php if (empty($templates)): ?>
  <div class="card"><p style="color:var(--c-muted)">Zadne layouty. <a href="/admin/layouts/create">Vytvorte prvni</a>.</p></div>
<?php else: ?>
<table>
  <thead><tr><th>Nazev</th><th>ID</th><th>Vytvoreno</th><th>Akce</th></tr></thead>
  <tbody>
    <?php foreach ($templates as $t): ?>
    <tr>
      <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
      <td>#<?= (int)$t['id'] ?></td>
      <td><?= htmlspecialchars(substr($t['created_at'], 0, 10)) ?></td>
      <td>
        <div class="actions">
          <a href="/admin/layouts/edit?id=<?= $t['id'] ?>" class="btn btn-sm btn-secondary">Upravit</a>
          <form method="POST" action="/admin/layouts/delete" data-confirm="Smazat layout?">
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
