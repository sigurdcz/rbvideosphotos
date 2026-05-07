<?php
$pageTitle = 'Stranky';
ob_start();
?>
<div class="page-header">
  <h1>Stranky</h1>
  <a href="/admin/pages/create" class="btn btn-primary">+ Nova stranka</a>
</div>

<?php if (empty($pages)): ?>
  <div class="card"><p style="color:var(--c-muted)">Zadne stranky. <a href="/admin/pages/create">Vytvorte prvni</a>.</p></div>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>Nazev</th>
      <th>Slug</th>
      <th>URL</th>
      <th>Layout</th>
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
      <td><?= htmlspecialchars($p['template_name'] ?? '-') ?></td>
      <td><span class="badge badge-<?= $p['status'] === 'published' ? 'published' : 'draft' ?>"><?= $p['status'] === 'published' ? 'Publikovano' : 'Koncept' ?></span></td>
      <td>
        <div class="actions">
          <a href="/admin/pages/edit?id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">Upravit</a>
          <a href="/admin/seo/edit?page_id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">SEO</a>
          <?php $frontUrl = '/' . ltrim($p['url_path'] ?? '', '/'); ?>
          <?php $previewUrl = $frontUrl . (strpos($frontUrl, '?') === false ? '?' : '&') . 'cms_preview=1'; ?>
          <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" class="btn btn-sm btn-secondary" title="Otevrit cisty nahled stranky">&#128065; Nahled</a>
          <a href="<?= htmlspecialchars($frontUrl) ?>" target="_blank" class="btn btn-sm btn-inline-edit" title="Otevrit stranku ve frontendu a editovat inline">&#9998; Editace</a>
          <form method="POST" action="/admin/publish/page" data-confirm="Publikovat tuto stranku?">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrfToken) ?>">
            <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn btn-sm btn-success">Publikovat</button>
          </form>
          <form method="POST" action="/admin/pages/delete" data-confirm="Opravdu smazat stranku?">
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
