<?php
$pageTitle = 'Naše práce';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
?>
<div class="page-header">
  <h1>Naše práce</h1>
  <div class="actions">
    <a href="/admin/portfolio/create" class="btn btn-primary">+ Nová práce</a>
  </div>
</div>

<div class="alert alert-warning" style="font-size:13px">
  <strong>Tip:</strong> Po uložení práce se dlaždice automaticky synchronizují do bloku <code>portfolio-tiles</code> na homepage.
  Aby se dlaždice zobrazily na webu, ujistěte se, že homepage šablona obsahuje <code>&lt;section data-block="portfolio-tiles"&gt;&lt;/section&gt;</code>, pak <a href="/admin/publish">Publikujte web</a>.
</div>

<?php if (empty($entries)): ?>
  <div class="card"><p style="color:var(--c-muted)">Žádné práce. <a href="/admin/portfolio/create">Přidat první</a>.</p></div>
<?php else: ?>
<div class="media-grid media-grid--manage">
  <?php foreach ($entries as $e):
    $thumb = '';
    if ($e['video_url']) {
        $thumb = \App\Repositories\PortfolioRepository::youtubeThumbnail($e['video_url']);
    }
    if (!$thumb && $e['_first_media']) {
        $thumb = '/' . $e['_first_media']['storage_path'];
    }
  ?>
  <div class="media-card media-card--manage">
    <div class="media-card-preview">
      <?php if ($thumb): ?>
        <img src="<?= $h($thumb) ?>" alt="<?= $h($e['title']) ?>" loading="lazy">
      <?php else: ?>
        <div style="height:130px;background:#111;display:flex;align-items:center;justify-content:center;font-size:2rem;color:rgba(255,255,255,.3)">&#9654;</div>
      <?php endif; ?>
      <span class="media-badge-type"><?= $e['status'] === 'published' ? '✓' : '⋯' ?></span>
    </div>
    <div class="media-card-body">
      <div class="media-card-name" title="<?= $h($e['title']) ?>"><?= $h($e['title']) ?></div>
      <div class="media-card-dim"><?= $e['video_url'] ? 'Video + fotky' : 'Fotky' ?> · pořadí: <?= (int)$e['sort_order'] ?></div>
      <div class="media-card-variants">
        <span class="mv-badge <?= $e['status'] === 'published' ? 'mv-badge--ok' : 'badge-draft' ?>"><?= $e['status'] === 'published' ? 'Publikováno' : 'Koncept' ?></span>
      </div>
      <div class="media-card-footer">
        <a href="/admin/portfolio/edit?id=<?= (int)$e['id'] ?>" class="btn btn-sm btn-secondary">Upravit</a>
        <form method="POST" action="/admin/portfolio/delete" data-confirm="Smazat tuto práci?">
          <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
          <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
          <button class="btn btn-sm btn-danger">Smazat</button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
