<?php
$pageTitle = 'Média';
$h         = fn($v) => htmlspecialchars((string)($v ?? ''));
$fmtBytes  = fn(int $b): string => $b >= 1_048_576
    ? round($b / 1_048_576, 1) . ' MB'
    : ($b >= 1_024 ? round($b / 1_024) . ' kB' : $b . ' B');

$mediaThumb = function(array $m): string {
    foreach ($m['variants'] as $v) {
        if ($v['format'] === 'thumb') return '/' . $v['path'];
    }
    return '/' . $m['storage_path'];
};

$variantMap = function(array $m): array {
    $map = [];
    foreach ($m['variants'] as $v) {
        $map[$v['format']] = $v;
    }
    return $map;
};

ob_start();
?>
<div class="page-header">
  <h1>Média</h1>
  <span style="color:var(--c-muted);font-size:13px"><?= count($media) ?> souborů</span>
</div>

<?php if ($success ?? null): ?><div class="alert alert-success"><?= $h($success) ?></div><?php endif; ?>
<?php if ($error   ?? null): ?><div class="alert alert-error"><?= $h($error) ?></div><?php endif; ?>

<!-- Upload card -->
<div class="card">
  <div class="card-title">Nahrát obrázky</div>
  <form id="upload-form" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <div class="form-row" style="margin-bottom:12px">
      <div class="form-group" style="margin-bottom:0">
        <label>ALT text <span style="font-weight:400;color:var(--c-muted)">(pro všechny nahrané)</span></label>
        <input type="text" name="alt_text" id="upload-alt">
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label>Název</label>
        <input type="text" name="title" id="upload-title">
      </div>
    </div>
    <div class="drop-zone" id="upload-drop" data-upload-input="#upload-file-input">
      <span id="drop-label">Přetáhněte obrázky sem nebo klikněte pro výběr</span>
      <br><small style="color:var(--c-muted)">JPG, PNG, WebP, GIF — max 10 MB — lze vybrat více souborů najednou</small>
    </div>
    <input type="file" id="upload-file-input" name="files[]"
           accept=".jpg,.jpeg,.png,.webp,.gif" multiple style="display:none">

    <div id="upload-queue" style="display:none;margin-top:10px"></div>

    <div style="margin-top:12px;display:flex;gap:10px;align-items:center">
      <button type="submit" class="btn btn-primary" id="upload-btn">Nahrát</button>
      <span id="upload-status" style="font-size:13px"></span>
    </div>
  </form>
</div>

<!-- Media library -->
<?php if (empty($media)): ?>
  <div class="card"><p style="color:var(--c-muted)">Žádná média. Nahrajte první soubor výše.</p></div>
<?php else: ?>
<div class="card">
  <div class="card-title">Knihovna médií</div>
  <div class="media-grid media-grid--manage">
    <?php foreach ($media as $m):
      $thumb = $mediaThumb($m);
      $vmap  = $variantMap($m);
      $isGif = ($m['mime_type'] === 'image/gif');
    ?>
    <div class="media-card media-card--manage">
      <div class="media-card-preview">
        <img src="<?= $h($thumb) ?>" alt="<?= $h($m['alt_text']) ?>" loading="lazy">
        <?php if ($isGif): ?>
          <span class="media-badge-type">GIF</span>
        <?php endif; ?>
      </div>
      <div class="media-card-body">
        <div class="media-card-name" title="<?= $h($m['original_filename']) ?>">
          <?= $h($m['original_filename']) ?>
        </div>
        <?php if ($m['width'] && $m['height']): ?>
          <div class="media-card-dim"><?= (int)$m['width'] ?> × <?= (int)$m['height'] ?> px</div>
        <?php endif; ?>

        <div class="media-card-variants">
          <span class="mv-badge mv-badge--orig" title="Originál">orig</span>
          <?php if (!$isGif): ?>
            <span class="mv-badge <?= isset($vmap['webp'])   ? 'mv-badge--ok' : 'mv-badge--miss' ?>"
                  title="<?= isset($vmap['webp']) ? 'WebP · ' . $vmap['webp']['width'] . '×' . $vmap['webp']['height'] . ' · ' . $fmtBytes((int)$vmap['webp']['filesize']) : 'WebP varianta chybí' ?>">webp</span>
            <span class="mv-badge <?= isset($vmap['thumb'])  ? 'mv-badge--ok' : 'mv-badge--miss' ?>"
                  title="<?= isset($vmap['thumb']) ? 'Náhled · ' . $vmap['thumb']['width'] . '×' . $vmap['thumb']['height'] . ' · ' . $fmtBytes((int)$vmap['thumb']['filesize']) : 'Náhled (300px) chybí' ?>">thumb</span>
            <?php if ((int)$m['width'] > 900): ?>
              <span class="mv-badge <?= isset($vmap['medium']) ? 'mv-badge--ok' : 'mv-badge--miss' ?>"
                    title="<?= isset($vmap['medium']) ? 'Střední · ' . $vmap['medium']['width'] . '×' . $vmap['medium']['height'] . ' · ' . $fmtBytes((int)$vmap['medium']['filesize']) : 'Střední (900px) chybí' ?>">med</span>
            <?php endif; ?>
          <?php else: ?>
            <span class="mv-badge mv-badge--gif" title="Animace — varianty se negenerují">anim</span>
          <?php endif; ?>
        </div>

        <div class="media-card-footer">
          <span class="media-card-id">ID: <?= (int)$m['id'] ?></span>
          <form method="POST" action="/admin/media/delete" data-confirm="Smazat médium a všechny varianty?">
            <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-sm btn-danger">Smazat</button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script>
(function () {
  const form  = document.getElementById('upload-form');
  const input = document.getElementById('upload-file-input');
  const queue = document.getElementById('upload-queue');
  const btn   = document.getElementById('upload-btn');
  const status = document.getElementById('upload-status');
  const dropLabel = document.getElementById('drop-label');

  input.addEventListener('change', renderQueue);

  function renderQueue() {
    const files = input.files;
    if (!files.length) { queue.style.display = 'none'; return; }
    queue.style.display = 'block';
    queue.innerHTML = '';
    dropLabel.textContent = files.length + ' soubor' + (files.length > 1 ? 'ů vybráno' : ' vybrán');
    Array.from(files).forEach(f => {
      const el = document.createElement('div');
      el.className = 'upload-queue-item';
      el.innerHTML = '<span class="uq-name">' + esc(f.name) + '</span>'
                   + '<span class="uq-size">' + fmtBytes(f.size) + '</span>'
                   + '<span class="uq-state">⏸</span>';
      queue.appendChild(el);
    });
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!input.files.length) { setStatus('Vyberte soubory.', ''); return; }

    btn.disabled = true;
    btn.textContent = 'Nahrávám…';
    setStatus('', '');
    queue.querySelectorAll('.uq-state').forEach(s => { s.textContent = '⏳'; s.style.color = ''; });

    const fd = new FormData(form);
    try {
      const resp = await fetch('/admin/media/upload', { method: 'POST', body: fd });
      const data = await resp.json();

      const results = Array.isArray(data.results)
        ? data.results
        : (data.error ? [{ error: data.error }] : []);

      const qItems = queue.querySelectorAll('.upload-queue-item');
      let ok = 0, fail = 0;
      results.forEach((r, i) => {
        const item = qItems[i];
        if (!item) return;
        const st = item.querySelector('.uq-state');
        if (r.success) { st.textContent = '✓'; st.style.color = '#16a34a'; ok++; }
        else           { st.textContent = '✗'; st.style.color = '#dc2626'; st.title = r.error || 'Chyba'; fail++; }
      });

      if (ok > 0 && fail === 0) {
        setStatus('✓ Nahráno ' + ok + ' soubor' + (ok > 1 ? 'ů' : '') + '.', '#16a34a');
        setTimeout(() => location.reload(), 1200);
      } else if (ok > 0) {
        setStatus(ok + ' nahráno, ' + fail + ' selhalo. Obnovuji…', '#d97706');
        setTimeout(() => location.reload(), 2500);
      } else {
        setStatus('Nahrávání selhalo. Zkontrolujte formát a velikost.', '#dc2626');
        btn.disabled = false;
        btn.textContent = 'Nahrát';
      }
    } catch (err) {
      setStatus('Chyba sítě: ' + err.message, '#dc2626');
      btn.disabled = false;
      btn.textContent = 'Nahrát';
    }
  });

  function setStatus(msg, color) {
    status.textContent = msg;
    status.style.color = color;
  }
  function esc(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  function fmtBytes(b) {
    if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
    if (b >= 1024)    return Math.round(b / 1024) + ' kB';
    return b + ' B';
  }
})();
</script>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
