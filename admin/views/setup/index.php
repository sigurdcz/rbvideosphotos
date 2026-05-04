<?php
$pageTitle = 'Počáteční nastavení';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
$d = $diag ?? [];
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

<div class="card">
  <div class="card-title">Diagnostika inline editace</div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <tr>
      <td style="padding:6px 10px;color:var(--c-muted);width:180px">Verze aplikace</td>
      <td style="padding:6px 10px;font-family:monospace"><?= $h($d['version'] ?? '—') ?></td>
    </tr>
    <tr style="background:rgba(0,0,0,.04)">
      <td style="padding:6px 10px;color:var(--c-muted)">Připojení k DB</td>
      <td style="padding:6px 10px">
        <?php if ($d['db_ok'] ?? false): ?>
          <span style="color:#40a02b">✓ OK</span>
        <?php else: ?>
          <span style="color:#d20f39">✗ CHYBA: <?= $h($d['db_error'] ?? '') ?></span>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td style="padding:6px 10px;color:var(--c-muted)">Stránky v DB (celkem)</td>
      <td style="padding:6px 10px;font-family:monospace"><?= (int)($d['pages_total'] ?? 0) ?></td>
    </tr>
    <tr style="background:rgba(0,0,0,.04)">
      <td style="padding:6px 10px;color:var(--c-muted)">Homepage (url_path='')</td>
      <td style="padding:6px 10px">
        <?php if (!empty($d['homepage'])): ?>
          <span style="color:#40a02b">✓ nalezena — id=<?= (int)$d['homepage']['id'] ?>, title=<?= $h($d['homepage']['title'] ?? '') ?></span>
        <?php elseif ($d['db_ok'] ?? false): ?>
          <span style="color:#d20f39">✗ žádná stránka s url_path='' → inline edit selže, spusť seed</span>
        <?php else: ?>
          <span style="color:#e64553">— DB nedostupná</span>
        <?php endif; ?>
      </td>
    </tr>
  </table>
</div>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
