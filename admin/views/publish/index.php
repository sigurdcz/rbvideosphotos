<?php
$pageTitle = 'Publikování';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
?>
<div class="page-header">
  <h1>Publikování</h1>
</div>

<?php if ($site): ?>
<div class="card publish-card">
  <div class="card-title">Publikovat celý web</div>
  <p style="color:var(--c-muted);margin-bottom:16px">Vygeneruje statické HTML soubory, sitemap.xml, robots.txt a .htaccess pro všechny publikované stránky.</p>
  <form method="POST" action="/admin/publish/site" data-confirm="Publikovat celý web?">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <input type="hidden" name="site_id" value="<?= (int)$site['id'] ?>">
    <button type="submit" class="btn btn-success">Publikovat celý web</button>
  </form>
</div>

<div class="card">
  <div class="card-title">Výstupní soubory</div>
  <?php
  $publishedDir = dirname(__DIR__, 3) . '/storage/published';
  $files = [];
  if (is_dir($publishedDir)) {
      $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publishedDir, FilesystemIterator::SKIP_DOTS));
      foreach ($iter as $file) {
          if ($file->isFile()) $files[] = str_replace($publishedDir, '', $file->getPathname());
      }
      sort($files);
  }
  ?>
  <?php if (empty($files)): ?>
    <p style="color:var(--c-muted)">Žádné publikované soubory. Spusťte publikování.</p>
  <?php else: ?>
    <ul style="list-style:none;font-family:monospace;font-size:13px;line-height:2">
      <?php foreach ($files as $f): ?>
        <li><?= $h($f) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-title">Historie publikování</div>
  <?php if (empty($history)): ?>
    <p style="color:var(--c-muted)">Zatím žádné publikování.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Verze</th><th>Status</th><th>Poznámka</th><th>Autor</th><th>Datum</th></tr></thead>
    <tbody>
      <?php foreach ($history as $h_row): ?>
      <tr>
        <td>v<?= (int)$h_row['version'] ?></td>
        <td><span class="badge badge-<?= $h_row['status'] === 'success' ? 'published' : 'error' ?>"><?= $h($h_row['status']) ?></span></td>
        <td><?= $h($h_row['notes']) ?></td>
        <td><?= $h($h_row['user_email'] ?? '') ?></td>
        <td><?= $h($h_row['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
