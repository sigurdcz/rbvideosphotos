<?php
$pageTitle = 'Naše práce';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
$publicMediaUrl = static function (?string $path): string {
    $normalized = str_replace('\\', '/', trim((string)$path));
    if ($normalized === '') return '';
    if (preg_match('~https?://[^/]+(/storage/media/.*)$~i', $normalized, $m)) {
        return '/media/' . ltrim(substr($m[1], strlen('/storage/media/')), '/');
    }
    if (str_starts_with($normalized, '/media/')) return $normalized;
    if (str_starts_with($normalized, '/storage/media/')) return '/media/' . ltrim(substr($normalized, strlen('/storage/media/')), '/');
    if (str_starts_with($normalized, 'storage/media/')) return '/media/' . ltrim(substr($normalized, strlen('storage/media/')), '/');
    return '/' . ltrim($normalized, '/');
};
ob_start();
?>
<div class="page-header">
  <h1>Naše práce</h1>
  <div class="actions">
    <a href="/admin/portfolio/create" class="btn btn-primary">+ Nova prace</a>
  </div>
</div>

<div class="alert alert-warning" style="font-size:13px">
  <strong>Tip:</strong> Po ulozeni prace se dlazdice automaticky synchronizuji do bloku <code>portfolio-tiles</code> na homepage.
  Pokud chces, aby se propsaly i do verejneho exportu, publikuj potom web nebo konkretni stranku znovu.
</div>

<?php if (empty($entries)): ?>
  <div class="card"><p style="color:var(--c-muted)">Zadne prace. <a href="/admin/portfolio/create">Pridat prvni</a>.</p></div>
<?php else: ?>
<div class="media-grid media-grid--manage">
  <?php foreach ($entries as $e):
    $thumb = '';
    if ($e['video_url']) {
        $thumb = \App\Repositories\PortfolioRepository::youtubeThumbnail($e['video_url']);
    }
    if (!$thumb && $e['_first_media']) {
        $thumb = $publicMediaUrl((string)($e['_first_media']['storage_path'] ?? ''));
    }
  ?>
  <div class="media-card media-card--manage">
    <div class="media-card-preview">
      <?php if ($thumb): ?>
        <img src="<?= $h($thumb) ?>" alt="<?= $h($e['title']) ?>" loading="lazy">
      <?php else: ?>
        <div style="height:130px;background:#111;display:flex;align-items:center;justify-content:center;font-size:2rem;color:rgba(255,255,255,.3)">&#9654;</div>
      <?php endif; ?>
      <span class="media-badge-type"><?= $e['status'] === 'published' ? '&#10003;' : '&#8943;' ?></span>
    </div>
    <div class="media-card-body">
      <div class="media-card-name" title="<?= $h($e['title']) ?>"><?= $h($e['title']) ?></div>
      <div class="media-card-dim"><?= $e['video_url'] ? 'Video + fotky' : 'Fotky' ?> - poradi: <?= (int)$e['sort_order'] ?></div>
      <div class="media-card-variants">
        <span class="mv-badge <?= $e['status'] === 'published' ? 'mv-badge--ok' : 'badge-draft' ?>"><?= $e['status'] === 'published' ? 'Publikovano' : 'Koncept' ?></span>
      </div>
      <div class="media-card-footer">
        <a href="/admin/portfolio/edit?id=<?= (int)$e['id'] ?>" class="btn btn-sm btn-secondary">Upravit</a>
        <form method="POST" action="/admin/portfolio/delete" data-confirm="Smazat tuto praci?">
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
