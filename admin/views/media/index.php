<?php
$pageTitle = 'Média';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
?>
<div class="page-header">
  <h1>Média</h1>
</div>

<!-- Upload form -->
<div class="card">
  <div class="card-title">Nahrát obrázek</div>
  <form id="upload-form" method="POST" action="/admin/media/upload" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <div class="form-row">
      <div class="form-group">
        <label>ALT text</label>
        <input type="text" name="alt_text" id="upload-alt">
      </div>
      <div class="form-group">
        <label>Název</label>
        <input type="text" name="title" id="upload-title">
      </div>
    </div>
    <div class="drop-zone" data-upload-input="#upload-file-input" style="margin-bottom:12px">
      <span>Přetáhněte obrázek sem nebo klikněte pro výběr</span>
      <br><small style="color:var(--c-muted)">JPG, PNG, WebP — max 10 MB</small>
    </div>
    <input type="file" id="upload-file-input" name="file" accept=".jpg,.jpeg,.png,.webp" style="display:none">
    <button type="submit" class="btn btn-primary">Nahrát</button>
  </form>
</div>

<!-- Media grid -->
<?php if (empty($media)): ?>
  <div class="card"><p style="color:var(--c-muted)">Žádná média.</p></div>
<?php else: ?>
<div class="card">
  <div class="card-title">Knihovna médií</div>
  <div class="media-grid">
    <?php foreach ($media as $m): ?>
    <div class="media-card">
      <img src="/<?= $h($m['storage_path']) ?>" alt="<?= $h($m['alt_text']) ?>" loading="lazy">
      <div class="media-card-info"><?= $h($m['original_filename']) ?></div>
      <div style="padding:4px 8px 8px;display:flex;gap:4px">
        <form method="POST" action="/admin/media/delete" data-confirm="Smazat médium?">
          <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="btn btn-sm btn-danger">Smazat</button>
        </form>
        <span style="font-size:10px;color:var(--c-muted);padding:5px 0">ID: <?= (int)$m['id'] ?></span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script>
document.getElementById('upload-file-input').addEventListener('change', function() {
  if (this.files.length) {
    document.querySelector('.drop-zone span').textContent = this.files[0].name;
  }
});
</script>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
