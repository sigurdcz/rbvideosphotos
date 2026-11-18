<?php
$visualRecord = (array)($articleVisualRecord ?? []);
$visualFallbackMedia = $articleVisualFallbackMedia ?? null;
$heroMedia = $heroMedia ?? null;
$thumbnailMedia = $thumbnailMedia ?? null;
$visualUrl = static fn(?array $media): string => $media
    ? $publicMediaUrl((string)($media['medium_path'] ?? $media['thumb_path'] ?? $media['storage_path'] ?? ''))
    : '';
$fallbackUrl = $visualUrl($visualFallbackMedia);
$heroUrl = $visualUrl($heroMedia) ?: $fallbackUrl;
$thumbnailUrl = $visualUrl($thumbnailMedia) ?: $fallbackUrl;
$heroFocusStored = $visualRecord['hero_focus_y'] ?? null;
$thumbnailFocusStored = $visualRecord['thumbnail_focus_y'] ?? null;
$visualContext = ($articleVisualContext ?? '') === 'portfolio' ? 'portfolio' : 'blog';
$previewLabels = [
    'hero' => [
        'desktop' => 'Desktop · 1440 × 590',
        'tablet' => 'Tablet · 768 × 590',
        'mobile' => 'Mobil · 390 × 480',
    ],
    'thumbnail' => $visualContext === 'portfolio' ? [
        'desktop' => 'Desktop · nejširší dlaždice',
        'tablet' => 'Tablet · nejširší dlaždice',
        'mobile' => 'Mobil · široká dlaždice',
    ] : [
        'desktop' => 'Desktop · karta 429 × 220',
        'tablet' => 'Tablet · karta 335 × 220',
        'mobile' => 'Mobil · karta 358 × 210',
    ],
];
$visualPickerItems = static fn(?array $media): array => $media ? [[
    'id' => (int)$media['id'],
    'name' => (string)($media['original_filename'] ?? ''),
    'thumb' => $visualUrl($media),
]] : [];
?>
<section class="card article-visual-editor article-visual-editor--<?= $visualContext ?>" data-article-visual-editor
         data-fallback-src="<?= $h($fallbackUrl) ?>">
  <div class="card-title">Thumbnail &amp; Hero</div>
  <p class="form-hint">Volitelné nastavení. Bez vybraného obrázku a pozice se použije dosavadní obrázek i středový ořez.</p>
  <div class="article-visual-editor__layout">
    <div class="article-visual-editor__controls">
      <?php foreach ([
          'hero' => ['Hero fotografie', $heroMedia, $heroFocusStored, $heroUrl],
          'thumbnail' => ['Thumbnail fotografie', $thumbnailMedia, $thumbnailFocusStored, $thumbnailUrl],
      ] as $visualKey => [$visualLabel, $selectedMediaItem, $storedFocus, $resolvedUrl]): ?>
      <fieldset class="article-visual-control" data-visual-control="<?= $visualKey ?>">
        <legend><?= $visualLabel ?></legend>
        <input type="hidden" name="<?= $visualKey ?>_image_id" data-visual-media-id value="<?= !empty($visualRecord[$visualKey . '_image_id']) ? (int)$visualRecord[$visualKey . '_image_id'] : '' ?>">
        <input type="hidden" name="<?= $visualKey ?>_focus_y" data-visual-focus-value value="<?= $storedFocus !== null ? (int)$storedFocus : '' ?>">
        <div class="article-visual-control__actions">
          <button type="button" class="btn btn-secondary" data-visual-pick><?= $selectedMediaItem ? 'Změnit fotografii' : 'Vybrat fotografii' ?></button>
          <button type="button" class="btn btn-secondary" data-visual-clear <?= $selectedMediaItem ? '' : 'hidden' ?>>Použít výchozí</button>
        </div>
        <label class="article-visual-focus">
          <span>Vertikální střed: <strong data-visual-focus-label><?= $storedFocus !== null ? (int)$storedFocus : 50 ?> %</strong></span>
          <input type="range" min="0" max="100" step="1" value="<?= $storedFocus !== null ? (int)$storedFocus : 50 ?>" data-visual-focus>
        </label>
        <button type="button" class="article-visual-reset-focus" data-visual-reset-focus <?= $storedFocus !== null ? '' : 'hidden' ?>>Obnovit výchozí střed</button>
        <p class="form-hint" data-visual-state><?= $selectedMediaItem ? $h((string)($selectedMediaItem['original_filename'] ?? 'Vybraná fotografie')) : 'Používá se dosavadní fotografie' ?></p>
      </fieldset>
      <?php endforeach; ?>
    </div>
    <aside class="article-visual-previews" aria-label="Živé náhledy Hero a Thumbnail">
      <?php foreach (['hero' => 'Hero', 'thumbnail' => 'Thumbnail'] as $visualKey => $visualLabel): ?>
      <section class="article-visual-preview-group" data-preview-group="<?= $visualKey ?>">
        <h3><?= $visualLabel ?></h3>
        <div class="article-visual-preview-grid">
          <?php foreach ($previewLabels[$visualKey] as $device => $deviceLabel): ?>
          <figure class="article-visual-preview article-visual-preview--<?= $visualKey ?> article-visual-preview--<?= $device ?>">
            <div><img src="<?= $h($visualKey === 'hero' ? $heroUrl : $thumbnailUrl) ?>" alt="" data-visual-preview-image></div>
            <figcaption><?= $deviceLabel ?></figcaption>
          </figure>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endforeach; ?>
    </aside>
  </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-article-visual-editor]').forEach(function (editor) {
    const fallbackSrc = editor.dataset.fallbackSrc || '';
    const initial = {
      hero: <?= json_encode($visualPickerItems($heroMedia), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      thumbnail: <?= json_encode($visualPickerItems($thumbnailMedia), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    };
    editor.querySelectorAll('[data-visual-control]').forEach(function (control) {
      const key = control.dataset.visualControl;
      const mediaInput = control.querySelector('[data-visual-media-id]');
      const focusInput = control.querySelector('[data-visual-focus-value]');
      const slider = control.querySelector('[data-visual-focus]');
      const focusLabel = control.querySelector('[data-visual-focus-label]');
      const pick = control.querySelector('[data-visual-pick]');
      const clear = control.querySelector('[data-visual-clear]');
      const reset = control.querySelector('[data-visual-reset-focus]');
      const state = control.querySelector('[data-visual-state]');
      const previews = editor.querySelectorAll('[data-preview-group="' + key + '"] [data-visual-preview-image]');
      let selected = initial[key] || [];

      function render() {
        const item = selected[0] || null;
        const src = item ? (item.thumb || item.url || '') : fallbackSrc;
        const focus = focusInput.value === '' ? 50 : Number(focusInput.value);
        mediaInput.value = item ? item.id : '';
        slider.value = String(focus);
        focusLabel.textContent = String(focus) + ' %';
        pick.textContent = item ? 'Změnit fotografii' : 'Vybrat fotografii';
        clear.hidden = !item;
        reset.hidden = focusInput.value === '';
        state.textContent = item ? (item.name || 'Vybraná fotografie') : 'Používá se dosavadní fotografie';
        previews.forEach(function (image) {
          image.src = src;
          image.style.objectPosition = '50% ' + focus + '%';
          image.closest('figure').classList.toggle('is-empty', !src);
        });
      }
      pick.addEventListener('click', function () {
        AdminMediaPicker.open({mode:'single', imagesOnly:true, selected:selected, onConfirm:function (list) { selected = list.slice(0, 1); render(); }});
      });
      clear.addEventListener('click', function () { selected = []; render(); });
      slider.addEventListener('input', function () { focusInput.value = slider.value; render(); });
      reset.addEventListener('click', function () { focusInput.value = ''; render(); });
      render();
    });
  });
});
</script>
