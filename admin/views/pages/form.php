<?php
$isEdit    = $page !== null;
$pageTitle = $isEdit ? 'Upravit stránku' : 'Nová stránka';
$h = fn($v) => htmlspecialchars((string)$v);
ob_start();
?>
<div class="page-header">
  <h1><?= $pageTitle ?></h1>
  <a href="/admin/pages" class="btn btn-secondary">← Zpět</a>
</div>

<div data-tabs-container>
  <div class="tabs">
    <button type="button" class="tab-btn" data-tab="basic">Základní</button>
    <?php if ($isEdit && $page['template_id']): ?>
    <button type="button" class="tab-btn" data-tab="blocks">Bloky obsahu <?= !empty($blocks) ? '(' . count($blocks) . ')' : '' ?></button>
    <?php endif; ?>
  </div>

  <form method="POST" action="<?= $isEdit ? '/admin/pages/update' : '/admin/pages/store' ?>">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$page['id'] ?>"><?php endif; ?>

    <!-- Tab: Základní -->
    <div class="tab-pane" id="tab-basic">
      <div class="card">
        <div class="form-group">
          <label for="page-title">Název stránky *</label>
          <input type="text" id="page-title" name="title" value="<?= $h($page['title'] ?? '') ?>" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="page-slug">Slug *</label>
            <input type="text" id="page-slug" name="slug" value="<?= $h($page['slug'] ?? '') ?>" required>
            <div class="form-hint">Bez diakritiky, malá písmena, pomlčky</div>
          </div>
          <div class="form-group">
            <label for="page-url">URL cesta</label>
            <input type="text" id="page-url" name="url_path" value="<?= $h($page['url_path'] ?? '') ?>">
            <div class="form-hint">Např. sluzby/fotografie</div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="page-template">Šablona</label>
            <select id="page-template" name="template_id">
              <option value="">— bez šablony —</option>
              <?php foreach ($templates as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= ($page['template_id'] ?? 0) == $t['id'] ? 'selected' : '' ?>><?= $h($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="page-lang">Jazyk</label>
            <select id="page-lang" name="language">
              <option value="cs" <?= ($page['language'] ?? 'cs') === 'cs' ? 'selected' : '' ?>>Čeština</option>
              <option value="en" <?= ($page['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
              <option value="sk" <?= ($page['language'] ?? '') === 'sk' ? 'selected' : '' ?>>Slovenčina</option>
            </select>
          </div>
        </div>
      </div>

      <?php if (!$isEdit): ?>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">Vytvořit stránku</button>
        <a href="/admin/pages" class="btn btn-secondary">Zrušit</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Tab: Bloky (empty state) -->
    <?php if ($isEdit && empty($blocks) && $page['template_id']): ?>
    <div class="tab-pane" id="tab-blocks">
      <div class="card">
        <p style="color:var(--c-muted);margin-bottom:14px">Tato stránka zatím nemá žádné bloky. Synchronizujte je ze šablony.</p>
        <form method="POST" action="/admin/blocks/sync-template">
          <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
          <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
          <button type="submit" class="btn btn-primary">Načíst bloky ze šablony</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Tab: Bloky -->
    <?php if ($isEdit && !empty($blocks)): ?>
    <div class="tab-pane" id="tab-blocks">
      <div style="margin-bottom:12px">
        <form method="POST" action="/admin/blocks/sync-template" style="display:inline">
          <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
          <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
          <button type="submit" class="btn btn-sm btn-secondary">&#8635; Synchronizovat bloky ze šablony</button>
        </form>
      </div>
      <div class="card">
        <div class="blocks-list">
          <?php foreach ($blocks as $i => $block): ?>
          <div class="block-item">
            <div class="block-label">
              <?= $h($block['label'] ?? $block['block_key']) ?>
              <span class="block-type-badge"><?= $h($block['block_type']) ?></span>
            </div>
            <input type="hidden" name="block_key[]" value="<?= $h($block['block_key']) ?>">
            <?php if (in_array($block['block_type'], ['text','link'])): ?>
              <input type="text" name="block_content[]" value="<?= $h($block['content'] ?? '') ?>">
            <?php elseif ($block['block_type'] === 'image'): ?>
              <input type="text" name="block_content[]" value="<?= $h($block['content'] ?? '') ?>" placeholder="/storage/media/...">
              <div class="form-hint">URL nebo cesta k obrázku</div>
            <?php else: ?>
              <textarea name="block_content[]" class="<?= $block['block_type'] === 'html' ? 'code' : '' ?>"><?= $h($block['content'] ?? '') ?></textarea>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($isEdit): ?>
    <div style="display:flex;gap:10px;margin-top:4px">
      <button type="submit" class="btn btn-primary">Uložit změny</button>
      <a href="/admin/seo/edit?page_id=<?= (int)$page['id'] ?>" class="btn btn-secondary">SEO nastavení</a>
      <form method="POST" action="/admin/publish/page" style="display:inline" data-confirm="Publikovat stránku?">
        <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
        <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
        <button type="submit" class="btn btn-success">Publikovat</button>
      </form>
    </div>
    <?php endif; ?>
  </form>
</div>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
