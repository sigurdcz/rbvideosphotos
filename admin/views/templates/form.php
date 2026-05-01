<?php
$isEdit    = $template !== null;
$pageTitle = $isEdit ? 'Upravit šablonu' : 'Nová šablona';
$h = fn($v) => htmlspecialchars((string)$v);
ob_start();
?>
<div class="page-header">
  <h1><?= $pageTitle ?></h1>
  <a href="/admin/templates" class="btn btn-secondary">← Zpět</a>
</div>

<form method="POST" action="<?= $isEdit ? '/admin/templates/update' : '/admin/templates/store' ?>">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$template['id'] ?>"><?php endif; ?>

  <div class="card">
    <div class="form-group">
      <label>Název šablony *</label>
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
          <label>HTML kód šablony</label>
          <div class="form-hint" style="margin-bottom:8px">Použijte <code>data-block="nazev"</code> pro editovatelné bloky a <code>data-media="nazev"</code> pro obrázky</div>
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
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Uložit změny' : 'Vytvořit šablonu' ?></button>
    <?php if ($isEdit): ?>
      <a href="/admin/templates/parse?id=<?= (int)$template['id'] ?>" class="btn btn-secondary" id="btn-parse" target="_blank">Zobrazit bloky</a>
    <?php endif; ?>
  </div>
</form>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
