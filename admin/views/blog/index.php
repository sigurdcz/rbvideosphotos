<?php
$pageTitle = 'Blog — články';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
?>
<div class="page-header">
  <h1>Blog — články</h1>
  <a href="/admin/blog/create" class="btn btn-primary">+ Nový článek</a>
</div>

<?php if (empty($posts)): ?>
  <div class="card"><p style="color:var(--c-muted)">Žádné články. <a href="/admin/blog/create">Vytvořit první</a>.</p></div>
<?php else: ?>
<div class="card" style="padding:0">
  <table>
    <thead>
      <tr>
        <th>Název</th>
        <th>Slug</th>
        <th>Stav</th>
        <th>Publikováno</th>
        <th>Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($posts as $p): ?>
      <tr>
        <td><strong><?= $h($p['title']) ?></strong></td>
        <td style="font-family:monospace;font-size:12px"><?= $h($p['slug']) ?></td>
        <td>
          <span class="badge <?= $p['status'] === 'published' ? 'badge-published' : 'badge-draft' ?>">
            <?= $p['status'] === 'published' ? 'Publikováno' : 'Koncept' ?>
          </span>
        </td>
        <td style="font-size:12px;color:var(--c-muted)"><?= $p['published_at'] ? date('j. n. Y', strtotime($p['published_at'])) : '—' ?></td>
        <td>
          <div class="actions">
            <a href="/admin/blog/edit?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-secondary">Upravit</a>
            <form method="POST" action="/admin/blog/delete" data-confirm="Smazat článek &quot;<?= $h($p['title']) ?>&quot;?" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-sm btn-danger">Smazat</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
