<?php
$pageTitle = 'Stránky';
ob_start();
?>
<div class="page-header">
  <h1>Stránky</h1>
  <a href="/admin/pages/create" class="btn btn-primary">+ Nová stránka</a>
</div>

<?php if (empty($pages)): ?>
  <div class="card"><p style="color:var(--c-muted)">Žádné stránky. <a href="/admin/pages/create">Vytvořte první</a>.</p></div>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>Název</th>
      <th>Slug</th>
      <th>URL</th>
      <th>Šablona</th>
      <th>Status</th>
      <th>Akce</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($pages as $p): ?>
    <tr>
      <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
      <td><code><?= htmlspecialchars($p['slug']) ?></code></td>
      <td><?= htmlspecialchars($p['url_path']) ?></td>
      <td><?= htmlspecialchars($p['template_name'] ?? '—') ?></td>
      <td><span class="badge badge-<?= $p['status'] === 'published' ? 'published' : 'draft' ?>"><?= $p['status'] === 'published' ? 'Publikováno' : 'Koncept' ?></span></td>
      <td>
        <div class="actions">
          <a href="/admin/pages/edit?id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">Upravit</a>
          <a href="/admin/seo/edit?page_id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">SEO</a>
          <?php $frontUrl = '/' . ltrim($p['url_path'] ?? '', '/'); ?>
          <a href="<?= htmlspecialchars($frontUrl) ?>" target="_blank" class="btn btn-sm btn-inline-edit" title="Otevřít stránku ve frontendu a editovat inline">&#9998; Na webu</a>
          <form method="POST" action="/admin/publish/page" data-confirm="Publikovat tuto stránku?">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrfToken) ?>">
            <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn btn-sm btn-success">Publikovat</button>
          </form>
          <form method="POST" action="/admin/pages/delete" data-confirm="Opravdu smazat stránku?">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrfToken) ?>">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Smazat</button>
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
