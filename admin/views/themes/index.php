<?php
$pageTitle = 'Sablony';
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
ob_start();
?>
<div class="page-header">
  <div>
    <h1>Sablony</h1>
    <p class="form-hint">Aktivni sablona urcuje styl a vizual celeho webu. Kdyz zadna neni aktivni, pouzije se soucasny defaultni stav.</p>
  </div>
</div>

<?php if (empty($themes)): ?>
  <div class="card">
    <p style="color:var(--c-muted);margin:0">V adresari <code>Templates/</code> zatim nejsou zadne sablony.</p>
  </div>
<?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">
    <?php foreach ($themes as $theme): ?>
      <div class="card" style="display:flex;flex-direction:column;gap:14px">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
          <div>
            <div class="card-title" style="margin-bottom:6px"><?= $h($theme['name']) ?></div>
            <div class="form-hint" style="margin:0 0 8px">Klic: <code><?= $h($theme['key']) ?></code></div>
            <?php if (!empty($theme['version'])): ?>
              <div class="form-hint" style="margin:0">Verze: <?= $h($theme['version']) ?></div>
            <?php endif; ?>
          </div>
          <?php if (!empty($theme['active'])): ?>
            <span class="badge badge-success">Aktivni</span>
          <?php endif; ?>
        </div>

        <?php if (!empty($theme['description'])): ?>
          <div style="color:var(--c-text);line-height:1.6"><?= $h($theme['description']) ?></div>
        <?php endif; ?>

        <div class="form-hint">
          Stranky: <?= !empty($theme['pages']) ? $h(implode(', ', $theme['pages'])) : 'zadne' ?><br>
          Assety: CSS <?= (int)($theme['asset_css'] ?? 0) ?>, JS <?= (int)($theme['asset_js'] ?? 0) ?>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <a class="btn btn-secondary" href="/?theme_preview=<?= rawurlencode((string)$theme['key']) ?>" target="_blank">&#128065; Preview</a>
          <?php if (empty($theme['active'])): ?>
            <form method="POST" action="/admin/shablony/activate" style="margin:0">
              <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
              <input type="hidden" name="theme_key" value="<?= $h($theme['key']) ?>">
              <button type="submit" class="btn btn-primary">Aktivovat</button>
            </form>
          <?php else: ?>
            <form method="POST" action="/admin/shablony/activate" style="margin:0">
              <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
              <input type="hidden" name="theme_key" value="">
              <button type="submit" class="btn btn-secondary">Deaktivovat</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
