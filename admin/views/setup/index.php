<?php
$pageTitle = 'Počáteční nastavení';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
?>
<div class="page-header">
  <h1>Počáteční nastavení dat</h1>
</div>

<?php if ($pageCount > 0): ?>
<div class="alert alert-success">
  Databáze již obsahuje <?= (int)$pageCount ?> stránek. Seed není potřeba spouštět znovu
  (pokud nechceš přepsat existující data).
</div>
<?php else: ?>
<div class="alert alert-warning">
  Databáze neobsahuje žádné stránky. Spusť seed pro načtení ukázkových dat RB Photos &amp; Videos.
</div>
<?php endif; ?>

<div class="card">
  <div class="card-title">Spustit seed: RB Photos &amp; Videos</div>
  <p style="color:var(--c-muted);margin-bottom:16px;font-size:13.5px">
    Seed vytvoří uživatele, šablony, stránky s obsahem, SEO nastavení a publikuje celý web.
    Existující záznamy se přepíší (upsert), nic se nesmaže.
  </p>
  <form method="POST" action="/admin/setup/run" data-confirm="Spustit seed a načíst data?">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <button type="submit" class="btn btn-primary">&#9654; Spustit seed</button>
  </form>
</div>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
