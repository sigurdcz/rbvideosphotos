<?php
$isEdit    = $post !== null;
$pageTitle = $isEdit ? 'Upravit článek' : 'Nový článek';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
?>
<div class="page-header">
  <h1><?= $pageTitle ?></h1>
  <a href="/admin/blog" class="btn btn-secondary">← Zpět</a>
</div>

<form method="POST" action="<?= $isEdit ? '/admin/blog/update' : '/admin/blog/store' ?>" id="blog-form">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$post['id'] ?>"><?php endif; ?>

  <div data-tabs-container>
    <div class="tabs">
      <button type="button" class="tab-btn" data-tab="content">Obsah</button>
      <button type="button" class="tab-btn" data-tab="meta">SEO &amp; Meta</button>
    </div>

    <!-- TAB: Obsah -->
    <div class="tab-pane" id="tab-content">
      <div class="card">
        <div class="form-group">
          <label for="blog-title">Název článku *</label>
          <input type="text" id="blog-title" name="title" value="<?= $h($post['title'] ?? '') ?>" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="blog-slug">Slug</label>
            <input type="text" id="blog-slug" name="slug" value="<?= $h($post['slug'] ?? '') ?>">
            <div class="form-hint">Vyplní se automaticky z názvu</div>
          </div>
          <div class="form-group">
            <label>Stav</label>
            <select name="status">
              <option value="draft"     <?= ($post['status'] ?? 'draft') === 'draft'     ? 'selected' : '' ?>>Koncept</option>
              <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Publikováno</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="blog-excerpt">Perex <small style="font-weight:400;color:var(--c-muted)">(krátký popis pro výpis)</small></label>
          <textarea name="excerpt" id="blog-excerpt" style="min-height:80px"><?= $h($post['excerpt'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Article editor -->
      <div class="card">
        <div class="card-title">Text článku</div>
        <div class="article-editor-wrap">
          <div class="article-editor-toolbar" id="ae-toolbar">
            <button type="button" class="ae-btn ae-sep-after" data-cmd="formatBlock" data-val="<h2>" title="Nadpis 2">H2</button>
            <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<h3>" title="Nadpis 3">H3</button>
            <span class="ae-sep"></span>
            <button type="button" class="ae-btn" data-cmd="bold"      title="Tučné"><strong>B</strong></button>
            <button type="button" class="ae-btn" data-cmd="italic"    title="Kurzíva"><em>I</em></button>
            <button type="button" class="ae-btn" data-cmd="underline" title="Podtržení"><u>U</u></button>
            <span class="ae-sep"></span>
            <button type="button" class="ae-btn" data-cmd="insertUnorderedList" title="Odrážky">&#8226; ul</button>
            <button type="button" class="ae-btn" data-cmd="insertOrderedList"   title="Číslovaný seznam">1. ol</button>
            <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<blockquote>" title="Citát">&#10077;</button>
            <span class="ae-sep"></span>
            <button type="button" class="ae-btn" data-cmd="createLink" title="Vložit odkaz">&#128279; Link</button>
            <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<p>" title="Odstavec">¶</button>
            <span class="ae-sep"></span>
            <button type="button" class="ae-btn ae-source-btn" data-cmd="source" title="HTML zdrojový kód">&lt;/&gt; HTML</button>
          </div>
          <!-- WYSIWYG editor div -->
          <div id="article-editor" contenteditable="true"><?= $post['content'] ?? '' ?></div>
          <!-- Source mode textarea (hidden by default) -->
          <textarea id="article-source" style="display:none" class="code" spellcheck="false"></textarea>
        </div>
        <!-- Hidden field submitted with the form -->
        <input type="hidden" name="content" id="article-content-hidden" value="<?= $h($post['content'] ?? '') ?>">
      </div>
    </div>

    <!-- TAB: SEO & Meta -->
    <div class="tab-pane" id="tab-meta">
      <div class="card">
        <div class="form-group">
          <label>Meta title</label>
          <input type="text" name="meta_title" value="<?= $h($post['meta_title'] ?? '') ?>">
          <div class="form-hint">Pokud prázdné, použije se název článku</div>
        </div>
        <div class="form-group">
          <label>Meta description</label>
          <textarea name="meta_description" style="min-height:80px"><?= $h($post['meta_description'] ?? '') ?></textarea>
        </div>
        <?php if (!empty($media)): ?>
        <div class="form-group">
          <label>Náhledový obrázek (featured image)</label>
          <div style="display:flex;gap:12px;align-items:center">
            <select name="featured_image_id" id="fi-select" style="flex:1">
              <option value="">— Bez obrázku —</option>
              <?php foreach ($media as $m): ?>
              <option value="<?= (int)$m['id'] ?>" <?= ((int)($post['featured_image_id'] ?? 0)) === (int)$m['id'] ? 'selected' : '' ?>>
                <?= $h($m['original_filename']) ?> (<?= (int)$m['width'] ?>×<?= (int)$m['height'] ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <img id="fi-preview" src="" alt="" style="height:48px;width:64px;object-fit:cover;border-radius:4px;display:none;border:1px solid var(--c-border)">
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div style="margin-top:16px;display:flex;gap:8px">
    <button type="submit" class="btn btn-primary">Uložit článek</button>
    <a href="/admin/blog" class="btn btn-secondary">Zrušit</a>
  </div>
</form>

<script>
// Pre-populate featured image preview
(function() {
  const sel = document.getElementById('fi-select');
  const img = document.getElementById('fi-preview');
  if (!sel || !img) return;
  const mediaMap = {
    <?php foreach ($media as $m): ?>
    <?= (int)$m['id'] ?>: '<?= $h('/' . $m['storage_path']) ?>',
    <?php endforeach; ?>
  };
  function updatePreview() {
    const id = sel.value;
    if (id && mediaMap[id]) { img.src = mediaMap[id]; img.style.display = 'block'; }
    else { img.style.display = 'none'; }
  }
  sel.addEventListener('change', updatePreview);
  updatePreview();
})();

// Auto-slug from title
(function() {
  const title = document.getElementById('blog-title');
  const slug  = document.getElementById('blog-slug');
  if (!title || !slug || slug.value) return;
  title.addEventListener('input', () => {
    slug.value = slugify(title.value);
  });
  function slugify(str) {
    const map = {á:'a',č:'c',ď:'d',é:'e',ě:'e',í:'i',ň:'n',ó:'o',ř:'r',š:'s',ť:'t',ú:'u',ů:'u',ý:'y',ž:'z'};
    return str.toLowerCase().replace(/[áčďéěíňóřšťúůýž]/g, c => map[c]||c)
      .replace(/[^a-z0-9\s-]/g,'').trim().replace(/\s+/g,'-').replace(/-+/g,'-');
  }
})();
</script>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
