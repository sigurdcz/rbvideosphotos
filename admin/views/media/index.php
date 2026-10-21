<?php
$pageTitle = 'Média';
$h         = fn($v) => htmlspecialchars((string)($v ?? ''));
$fmtBytes  = fn(int $b): string => $b >= 1_048_576
    ? round($b / 1_048_576, 1) . ' MB'
    : ($b >= 1_024 ? round($b / 1_024) . ' kB' : $b . ' B');

$mediaThumb = function(array $m): string {
    foreach ($m['variants'] as $v) {
        if ($v['format'] === 'thumb') return '/media/' . ltrim(preg_replace('~^storage/media/~', '', str_replace('\\', '/', (string)$v['path'])) ?? '', '/');
    }
    return '/media/' . ltrim(preg_replace('~^storage/media/~', '', str_replace('\\', '/', (string)$m['storage_path'])) ?? '', '/');
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

<!-- Upload card -->
<div class="card">
  <div class="card-title">Nahrát soubory</div>
  <form id="upload-form" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <div class="form-row" style="margin-bottom:12px">
      <div class="form-group" style="margin-bottom:0">
        <label>ALT text <span style="color:#dc2626">*</span> <span style="font-weight:400;color:var(--c-muted)">(pro všechny nahrané)</span></label>
        <input type="text" name="alt_text" id="upload-alt" required>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label>Název <span style="font-weight:400;color:var(--c-muted)">(nepovinné - zkopíruje ALT)</span></label>
        <input type="text" name="title" id="upload-title">
      </div>
    </div>
    <div class="drop-zone" id="upload-drop" data-upload-input="#upload-file-input">
      <span id="drop-label">Přetáhněte soubory sem nebo klikněte pro výběr</span>
      <br><small style="color:var(--c-muted)">Obrázky: JPG, PNG, WebP, GIF - max 10 MB &nbsp;|&nbsp; Video: MP4, MOV, WebM, AVI - max 512 MB. Pro hero doporučujeme MP4 (H.264) na šířku, do Full HD a do 25 MB. Rozlišení a miniaturu zjistí prohlížeč při nahrání; kvalita se na serveru nesnižuje.</small>
    </div>
    <input type="file" id="upload-file-input" name="files[]"
           accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.mov,.webm,.avi,.mkv" multiple style="display:none">

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
      $isGif   = ($m['mime_type'] === 'image/gif');
      $isVideo = str_starts_with($m['mime_type'] ?? '', 'video/');
      $vmap    = $variantMap($m);
      $thumb   = $isVideo ? (isset($vmap['thumb']) ? $mediaThumb($m) : null) : $mediaThumb($m);
      $videoInfo = $isVideo
        ? \App\Services\VideoMediaInfo::describe(
            (int)($m['width'] ?? 0),
            (int)($m['height'] ?? 0),
            (int)@filesize(dirname(__DIR__, 3) . '/' . ltrim((string)$m['storage_path'], '/'))
          )
        : null;
    ?>
    <div class="media-card media-card--manage">
      <div class="media-card-preview">
        <?php if ($isVideo): ?>
          <?php if ($thumb): ?>
            <img src="<?= $h($thumb) ?>" alt="<?= $h($m['alt_text']) ?>" loading="lazy">
          <?php else: ?>
            <div style="display:flex;align-items:center;justify-content:center;height:100%;background:#111;color:#c4963c;font-size:2.4rem">&#9654;</div>
          <?php endif; ?>
          <span class="media-badge-type">VIDEO</span>
        <?php else: ?>
          <img src="<?= $h($thumb) ?>" alt="<?= $h($m['alt_text']) ?>" loading="lazy">
          <?php if ($isGif): ?>
            <span class="media-badge-type">GIF</span>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <div class="media-card-body">
        <div class="media-card-name" title="<?= $h($m['original_filename']) ?>">
          <?= $h($m['original_filename']) ?>
        </div>
        <?php if (!$isVideo && $m['width'] && $m['height']): ?>
          <div class="media-card-dim"><?= (int)$m['width'] ?> × <?= (int)$m['height'] ?> px</div>
        <?php elseif ($videoInfo): ?>
          <div class="media-card-dim"><?= $h($videoInfo['label']) ?></div>
          <?php foreach ($videoInfo['warnings'] as $warning): ?>
            <div class="media-card-dim" style="color:#b45309"><?= $h($warning) ?></div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="media-card-variants">
          <span class="mv-badge mv-badge--orig" title="Originál">orig</span>
          <?php if ($isVideo): ?>
            <span class="mv-badge <?= isset($vmap['thumb']) ? 'mv-badge--ok' : 'mv-badge--miss' ?>"
                  title="<?= isset($vmap['thumb']) ? 'Miniatura videa · ' . $vmap['thumb']['width'] . '×' . $vmap['thumb']['height'] : 'Miniatura videa chybí (prohlížeč ji nedokázal vytvořit)' ?>">thumb</span>
          <?php elseif (!$isGif): ?>
            <span class="mv-badge <?= isset($vmap['webp'])   ? 'mv-badge--ok' : 'mv-badge--miss' ?>"
                  title="<?= isset($vmap['webp']) ? 'WebP · ' . $vmap['webp']['width'] . '×' . $vmap['webp']['height'] . ' · ' . $fmtBytes((int)$vmap['webp']['filesize']) : 'WebP varianta chybí' ?>">webp</span>
            <span class="mv-badge <?= isset($vmap['thumb'])  ? 'mv-badge--ok' : 'mv-badge--miss' ?>"
                  title="<?= isset($vmap['thumb']) ? 'Náhled · ' . $vmap['thumb']['width'] . '×' . $vmap['thumb']['height'] . ' · ' . $fmtBytes((int)$vmap['thumb']['filesize']) : 'Náhled (300px) chybí' ?>">thumb</span>
            <?php if ((int)$m['width'] > 900): ?>
              <span class="mv-badge <?= isset($vmap['medium']) ? 'mv-badge--ok' : 'mv-badge--miss' ?>"
                    title="<?= isset($vmap['medium']) ? 'Střední · ' . $vmap['medium']['width'] . '×' . $vmap['medium']['height'] . ' · ' . $fmtBytes((int)$vmap['medium']['filesize']) : 'Střední (900px) chybí' ?>">med</span>
            <?php endif; ?>
          <?php else: ?>
            <span class="mv-badge mv-badge--gif" title="Animace - varianty se negenerují">anim</span>
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
  const VIDEO_EXTS  = ['mp4','mov','webm','avi','mkv'];
  const IMG_MAX_MB  = 10;
  const VID_MAX_MB  = 512;

  const form      = document.getElementById('upload-form');
  const input     = document.getElementById('upload-file-input');
  const queue     = document.getElementById('upload-queue');
  const btn       = document.getElementById('upload-btn');
  const status    = document.getElementById('upload-status');
  const dropLabel = document.getElementById('drop-label');

  input.addEventListener('change', renderQueue);

  function isVideo(name) {
    const ext = name.split('.').pop().toLowerCase();
    return VIDEO_EXTS.includes(ext);
  }

  function renderQueue() {
    const files = input.files;
    if (!files.length) { queue.style.display = 'none'; return; }
    queue.style.display = 'block';
    queue.innerHTML = '';
    dropLabel.textContent = files.length + ' soubor' + (files.length > 1 ? 'ů vybráno' : ' vybrán');
    Array.from(files).forEach(f => {
      const maxMb  = isVideo(f.name) ? VID_MAX_MB : IMG_MAX_MB;
      const tooBig = f.size > maxMb * 1048576;
      const el = document.createElement('div');
      el.className = 'upload-queue-item';
      el.innerHTML = '<span class="uq-name">' + esc(f.name) + '</span>'
                   + '<span class="uq-size">' + fmtBytes(f.size) + (tooBig ? ' <span style="color:#dc2626">▲ přes ' + maxMb + ' MB</span>' : '') + '</span>'
                   + '<span class="uq-state">' + (tooBig ? '✗' : '⏸') + '</span>';
      if (tooBig) el.querySelector('.uq-state').style.color = '#dc2626';
      queue.appendChild(el);
    });
  }

  const altInput   = document.getElementById('upload-alt');
  const titleInput = document.getElementById('upload-title');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!input.files.length) { setStatus('Vyberte soubory.', ''); return; }

    const altVal = altInput.value.trim();
    if (!altVal) {
      altInput.focus();
      setStatus('ALT text je povinný.', '#dc2626');
      return;
    }
    // Auto-fill title from alt if left empty
    if (!titleInput.value.trim()) titleInput.value = altVal;

    // Client-side size check before uploading
    const oversized = Array.from(input.files).filter(f => {
      const maxMb = isVideo(f.name) ? VID_MAX_MB : IMG_MAX_MB;
      return f.size > maxMb * 1048576;
    });
    if (oversized.length) {
      setStatus('Některé soubory překračují povolený limit. Zkontrolujte frontu výše.', '#dc2626');
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Nahrávám…';
    setStatus('', '');
    queue.querySelectorAll('.uq-state').forEach(s => { s.textContent = '⏳'; s.style.color = ''; });

    const fd = new FormData(form);
    const selected = Array.from(input.files);
    setStatus('Zjišťuji parametry videí…', '');
    // Probed in parallel, so several undecodable files cost one timeout, not one each.
    const probes = await Promise.all(selected.map(f => isVideo(f.name) ? probeVideo(f) : Promise.resolve(undefined)));
    for (let i = 0; i < selected.length; i++) {
      if (probes[i] === undefined) continue;
      const probe = probes[i];
      const item = queue.querySelectorAll('.upload-queue-item')[i];
      if (!probe) {
        if (item) item.querySelector('.uq-size').insertAdjacentText('beforeend', ' · parametry nezjištěny (kodek prohlížeč nepodporuje)');
        continue;
      }
      fd.append('video_meta_' + i, JSON.stringify(probe.meta));
      if (probe.blob) fd.append('video_thumb_' + i, probe.blob, 'thumb.jpg');
      if (item) item.querySelector('.uq-size').insertAdjacentText('beforeend', ' · ' + probe.meta.width + '×' + probe.meta.height + ' · ' + Math.round(probe.meta.duration) + ' s');
    }
    setStatus('', '');
    try {
      const resp = await fetch('/admin/media/upload', { method: 'POST', body: fd });

      // Server may return non-JSON (e.g. 413 from Apache) - handle gracefully
      const text = await resp.text();
      let data;
      try { data = JSON.parse(text); } catch (_) {
        if (resp.status === 413) {
          setStatus('Soubor je příliš velký - server odmítl požadavek (HTTP 413). Zkuste menší soubor nebo nahrajte přes FTP.', '#dc2626');
        } else {
          setStatus('Neočekávaná chyba serveru (HTTP ' + resp.status + ').', '#dc2626');
        }
        btn.disabled = false;
        btn.textContent = 'Nahrát';
        return;
      }

      if (data.error && !data.results) {
        setStatus('Chyba: ' + data.error, '#dc2626');
        btn.disabled = false;
        btn.textContent = 'Nahrát';
        return;
      }

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
        setStatus('Nahrávání selhalo. ' + (results[0]?.error || ''), '#dc2626');
        btn.disabled = false;
        btn.textContent = 'Nahrát';
      }
    } catch (err) {
      // Network error - most often server rejected oversized request and closed connection
      setStatus('Chyba sítě: soubor je pravděpodobně příliš velký pro server. Zkuste menší soubor nebo nahrajte přes FTP.', '#dc2626');
      btn.disabled = false;
      btn.textContent = 'Nahrát';
    }
  });

  // The server has no ffmpeg, so the browser reads the video's size and length and
  // captures one frame as the thumbnail. A video the browser cannot decode still uploads.
  function probeVideo(file) {
    return new Promise(resolve => {
      const url = URL.createObjectURL(file);
      const video = document.createElement('video');
      video.muted = true; video.preload = 'metadata'; video.playsInline = true;
      let meta = null, done = false, timer = null;
      const finish = blob => {
        if (done) return;
        done = true; clearTimeout(timer);
        URL.revokeObjectURL(url); video.removeAttribute('src'); video.load();
        resolve(meta ? { meta, blob: blob || null } : null);
      };
      timer = setTimeout(() => finish(null), 8000);
      video.onerror = () => finish(null);
      video.onloadedmetadata = () => {
        if (!video.videoWidth || !video.videoHeight) { finish(null); return; }
        meta = { width: video.videoWidth, height: video.videoHeight, duration: isFinite(video.duration) ? video.duration : 0 };
        video.currentTime = Math.min(1, Math.max(0.1, (meta.duration || 2) * 0.1));
      };
      video.onseeked = () => {
        const scale = Math.min(1, 640 / video.videoWidth);
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
        canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
        try {
          canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
          canvas.toBlob(blob => finish(blob), 'image/jpeg', 0.82);
        } catch (_) { finish(null); }
      };
      video.src = url;
    });
  }
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
