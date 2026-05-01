<?php
$pageTitle = 'SEO — ' . htmlspecialchars($page['title'] ?? '');
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
?>
<div class="page-header">
  <h1>SEO: <?= $h($page['title']) ?></h1>
  <a href="/admin/pages/edit?id=<?= (int)$page['id'] ?>" class="btn btn-secondary">← Zpět na stránku</a>
</div>

<!-- SEO Audit -->
<?php if (!empty($audit)): ?>
<div class="card">
  <div class="card-title">SEO Audit</div>
  <ul class="audit-list">
    <?php foreach ($audit as $item): ?>
    <li class="audit-<?= $item['status'] ?>">
      <span class="audit-icon"><?= $item['status'] === 'ok' ? '✓' : ($item['status'] === 'error' ? '✗' : '⚠') ?></span>
      <span><?= $h($item['label']) ?></span>
      <?php if (!empty($item['note'])): ?><span style="color:var(--c-muted);font-size:12px">(<?= $h($item['note']) ?>)</span><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form method="POST" action="/admin/seo/update">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
  <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">

  <div data-tabs-container>
    <div class="tabs">
      <button type="button" class="tab-btn" data-tab="basic-seo">Základní SEO</button>
      <button type="button" class="tab-btn" data-tab="og">Open Graph</button>
      <button type="button" class="tab-btn" data-tab="twitter">Twitter Card</button>
      <button type="button" class="tab-btn" data-tab="schema">Schema.org</button>
    </div>

    <div class="tab-pane" id="tab-basic-seo">
      <div class="card">
        <div class="form-group">
          <label>Meta title</label>
          <input type="text" name="meta_title" value="<?= $h($seo['meta_title'] ?? '') ?>" maxlength="255">
          <div class="form-hint">Doporučená délka: 50–60 znaků</div>
        </div>
        <div class="form-group">
          <label>Meta description</label>
          <textarea name="meta_description" rows="3"><?= $h($seo['meta_description'] ?? '') ?></textarea>
          <div class="form-hint">Doporučená délka: 120–160 znaků</div>
        </div>
        <div class="form-group">
          <label>Meta keywords</label>
          <input type="text" name="meta_keywords" value="<?= $h($seo['meta_keywords'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Canonical URL</label>
          <input type="url" name="canonical_url" value="<?= $h($seo['canonical_url'] ?? '') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <div class="checkbox-row">
              <input type="checkbox" id="robots_index" name="robots_index" value="1" <?= !empty($seo['robots_index']) ? 'checked' : '' ?>>
              <label for="robots_index" style="margin-bottom:0">Indexovat (robots: index)</label>
            </div>
          </div>
          <div class="form-group">
            <div class="checkbox-row">
              <input type="checkbox" id="robots_follow" name="robots_follow" value="1" <?= !empty($seo['robots_follow']) ? 'checked' : '' ?>>
              <label for="robots_follow" style="margin-bottom:0">Sledovat odkazy (robots: follow)</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="tab-pane" id="tab-og">
      <div class="card">
        <div class="form-group">
          <label>OG Title</label>
          <input type="text" name="og_title" value="<?= $h($seo['og_title'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>OG Description</label>
          <textarea name="og_description" rows="3"><?= $h($seo['og_description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label>OG Image (ID média)</label>
          <input type="number" name="og_image_id" id="og_image_id" value="<?= (int)($seo['og_image_id'] ?? 0) ?: '' ?>">
          <?php if (!empty($mediaList)): ?>
          <div class="form-hint">Dostupná média: <?php foreach ($mediaList as $m): ?><a href="#" onclick="document.getElementById('og_image_id').value=<?= $m['id'] ?>;return false">#<?= $m['id'] ?> <?= $h($m['original_filename']) ?></a> <?php endforeach; ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="tab-pane" id="tab-twitter">
      <div class="card">
        <div class="form-group">
          <label>Twitter Title</label>
          <input type="text" name="twitter_title" value="<?= $h($seo['twitter_title'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Twitter Description</label>
          <textarea name="twitter_description" rows="3"><?= $h($seo['twitter_description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label>Twitter Image (ID média)</label>
          <input type="number" name="twitter_image_id" id="twitter_image_id" value="<?= (int)($seo['twitter_image_id'] ?? 0) ?: '' ?>">
        </div>
      </div>
    </div>

    <div class="tab-pane" id="tab-schema">
      <div class="card">
        <div class="form-group">
          <label>Schema type</label>
          <select name="schema_type">
            <option value="">— nevyplněno —</option>
            <option value="LocalBusiness" <?= ($seo['schema_type'] ?? '') === 'LocalBusiness' ? 'selected' : '' ?>>LocalBusiness</option>
            <option value="Person" <?= ($seo['schema_type'] ?? '') === 'Person' ? 'selected' : '' ?>>Person</option>
            <option value="Article" <?= ($seo['schema_type'] ?? '') === 'Article' ? 'selected' : '' ?>>Article</option>
            <option value="WebPage" <?= ($seo['schema_type'] ?? '') === 'WebPage' ? 'selected' : '' ?>>WebPage</option>
          </select>
        </div>
        <div class="form-group">
          <label>Schema JSON-LD</label>
          <textarea class="code" name="schema_json" rows="12"><?= $h($seo['schema_json'] ?? '') ?></textarea>
          <div class="form-hint">Celý objekt včetně &lt;script type="application/ld+json"&gt; nebo jen JSON</div>
        </div>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:10px;margin-top:4px">
    <button type="submit" class="btn btn-primary">Uložit SEO</button>
  </div>
</form>

<form method="POST" action="/admin/publish/page" style="display:inline;margin-top:0" data-confirm="Publikovat stránku?">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
  <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
  <button type="submit" class="btn btn-success" style="margin-top:10px">Publikovat</button>
</form>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
