<?php
// Portfolio owns gallery data; page blocks own only the surrounding copy.
$galleryGroups = ['photo' => 'Fotogalerie', 'video' => 'Videogalerie'];
?>
<div class="alert alert-info">
  Galerie jsou společné se sekcí Naše práce. Zde otevřete úpravu fotografií,
  video odkazů, názvu, stavu a pořadí. Editor se otevře v nové kartě;
  rozepsané změny této stránky zůstanou zachované. Po uložení galerie obnovte tento přehled.
</div>
<?php foreach ($galleryGroups as $galleryType => $galleryLabel): ?>
  <?php $galleryItems = array_filter($portfolioEntries, static fn(array $item): bool => ($item['content_type'] ?? '') === $galleryType); ?>
  <section class="card" aria-label="<?= $h($galleryLabel) ?>">
    <h2><?= $h($galleryLabel) ?> (<?= count($galleryItems) ?>)</h2>
    <p><a class="btn btn-primary" href="/admin/portfolio/create?content_type=<?= $galleryType ?>" target="_blank" rel="noopener">+ Přidat <?= $galleryType === 'video' ? 'videogalerii' : 'fotogalerii' ?> (nová karta)</a></p>
    <?php if (!$galleryItems): ?>
      <p>Zatím nejsou vytvořené žádné galerie tohoto typu.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($galleryItems as $galleryItem): ?>
          <li style="margin-bottom:12px">
            <a href="/admin/portfolio/edit?id=<?= (int)$galleryItem['id'] ?>" target="_blank" rel="noopener">Upravit: <?= $h($galleryItem['title']) ?> (nová karta)</a>
            — pořadí <?= (int)$galleryItem['sort_order'] ?>,
            <?= ($galleryItem['status'] ?? '') === 'published' ? 'publikováno' : 'koncept' ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<p><a class="btn btn-secondary" href="/admin/portfolio" target="_blank" rel="noopener">Kompletní správa galerií včetně mazání (nová karta)</a></p>
