<?php
$isEdit = $template !== null;
$pageTitle = $isEdit ? 'Upravit layout stranky' : 'Novy layout stranky';
$h = fn($v) => htmlspecialchars((string)$v);
ob_start();
?>
<div class="page-header">
  <h1><?= $pageTitle ?></h1>
  <a href="/admin/layouts" class="btn btn-secondary">&larr; Zpet</a>
</div>

<form method="POST" action="<?= $isEdit ? '/admin/layouts/update' : '/admin/layouts/store' ?>">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$template['id'] ?>"><?php endif; ?>

  <div class="card">
    <div class="form-group">
      <label>Nazev layoutu *</label>
      <input type="text" name="name" value="<?= $h($template['name'] ?? '') ?>" required>
    </div>
  </div>

  <div data-tabs-container>
    <div class="tabs">
      <button type="button" class="tab-btn" data-tab="html">HTML</button>
      <button type="button" class="tab-btn" data-tab="css">CSS</button>
      <button type="button" class="tab-btn" data-tab="js">JavaScript</button>
    </div>

    <div class="tab-pane" id="tab-html">
      <div class="card">
        <div class="form-group">
          <label>HTML kod layoutu</label>
          <div class="form-hint" style="margin-bottom:8px">Pouzijte <code>data-block="nazev"</code> pro editovatelne bloky a <code>data-media="nazev"</code> pro obrazky.</div>
          <textarea class="code" name="html_source" style="min-height:360px"><?= $h($template['html_source'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <div class="tab-pane" id="tab-css">
      <div class="card">
        <div class="form-group">
          <label>CSS</label>
          <textarea class="code" name="css_source" style="min-height:360px"><?= $h($template['css_source'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <div class="tab-pane" id="tab-js">
      <div class="card">
        <div class="form-group">
          <label>JavaScript</label>
          <textarea class="code" name="js_source" style="min-height:360px"><?= $h($template['js_source'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:10px;margin-top:4px">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Uložit změny' : 'Vytvorit layout' ?></button>
    <?php if ($isEdit): ?>
      <a href="/admin/layouts/parse?id=<?= (int)$template['id'] ?>" class="btn btn-secondary" id="btn-parse" target="_blank">Zobrazit bloky</a>
    <?php endif; ?>
  </div>
</form>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
