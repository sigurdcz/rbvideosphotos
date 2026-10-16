<?php $pageTitle = 'Sdilene sekce'; ob_start(); ?>
<div class="page-header"><div><h1>Sdilene sekce</h1><p class="form-hint">Jeden obsah se pouziva na vsech strankach, ktere danou sekci zobrazuji.</p></div></div>
<div class="card"><div class="card-title">Marketingove sekce</div>
<?php foreach ($sections as $section): ?><div class="list-row"><div><strong><?= htmlspecialchars((string)$section['title']) ?></strong><div class="form-hint"><?= htmlspecialchars((string)$section['section_key']) ?></div></div><a class="btn btn-primary" href="/admin/shared-sections/edit?key=<?= urlencode((string)$section['section_key']) ?>">Upravit</a></div><?php endforeach; ?>
</div>
<div class="card"><div class="card-title">Dalsi centralne spravovany obsah</div><p>Tyto opakovane datove zdroje uz maji vlastni centralni spravu:</p><div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn btn-secondary" href="/admin/faq">FAQ</a><a class="btn btn-secondary" href="/admin/reviews">Reference klientu</a><a class="btn btn-secondary" href="/admin/settings">Branding a globalni nastaveni</a><a class="btn btn-secondary" href="/admin/pages">Hero a strankove texty</a></div></div>
<?php $slot = ob_get_clean(); require dirname(__DIR__) . '/layouts/main.php'; ?>
