<?php
$pageTitle = 'Pocatecni nastaveni';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
$d = $diag ?? [];
?>
<div class="page-header">
  <h1>Pocatecni nastaveni dat</h1>
</div>

<?php if ($pageCount > 0): ?>
<div class="alert alert-success">
  Databaze uz obsahuje <?= (int) $pageCount ?> stranek. Seed neni potreba spoustet znovu
  (pokud nechces prepsat existujici data).
</div>
<?php else: ?>
<div class="alert alert-warning">
  Databaze neobsahuje zadne stranky. Spust seed pro nacteni ukazkovych dat RB Photos &amp; Videos.
</div>
<?php endif; ?>

<div class="card">
  <div class="card-title">Spustit seed: RB Photos &amp; Videos</div>
  <p style="color:var(--c-muted);margin-bottom:16px;font-size:13.5px">
    Seed vytvori uzivatele, layouty, stranky s obsahem, SEO nastaveni a publikuje cely web.
    Existujici zaznamy se prepisi jako upsert, nic se nemaze.
  </p>
  <form method="POST" action="/admin/setup/run" data-confirm="Spustit seed a nacist data?">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <button type="submit" class="btn btn-primary">&#9654; Spustit seed</button>
  </form>
</div>

<div class="card">
  <div class="card-title">Doplnit demo obsah</div>
  <p style="color:var(--c-muted);margin-bottom:16px;font-size:13.5px">
    Doplní 5 článků, 5 položek Naše práce, 4 služby + 4 stránky služeb, 12 FAQ, 10 recenzí, svatební ceník
    a kompletní SEO všech hlavních stránek (vč. OG/Twitter obrázků a Schema.org). Reálné demo fotografie
    nahraje do mediální knihovny přes stejný proces jako běžný upload v administraci — včetně ALTů, titulků
    a variant (thumb, medium i OG 1200×630). Existující záznamy se najdou podle slugů a aktualizují se,
    nebudou se duplikovat.
  </p>
  <div class="alert alert-warning" style="margin-bottom:16px">
    Referencni profil po dokonceni explicitne aktivuje motiv <code>bezova-lens-tale</code> a web znovu publikuje.
  </div>
  <form method="POST" action="/admin/setup/demo-content" data-confirm="Doplnit demo články, práce, recenze a média?">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <button type="submit" class="btn btn-primary">Doplnit demo obsah</button>
  </form>
</div>

<div class="card">
  <div class="card-title">Armagedon</div>
  <p style="color:var(--c-muted);margin-bottom:16px;font-size:13.5px">
    Provede kompletní reset: smaže média, publikovaný web, cache, generované frontend assety i souborový stav motivu,
    shodí celou databázi, znovu spustí migrace a nahraje výhradně základní <code>database/seeds/seed.php</code>.
  </p>
  <div class="alert alert-error" style="margin-bottom:16px">
    Tohle je nevratná akce. Všechna vlastní média i data v aktuální databázi budou ztracena.
  </div>
  <form method="POST" action="/admin/setup/armageddon" data-confirm="ARMAGEDON smaže všechna runtime data i celou databázi a znovu nahraje základní seed. Opravdu pokračovat?">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <button type="submit" class="btn btn-danger">Armagedon</button>
  </form>
</div>

<div class="card">
  <div class="card-title">Diagnostika inline editace</div>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <tr>
      <td style="padding:6px 10px;color:var(--c-muted);width:180px">Verze aplikace</td>
      <td style="padding:6px 10px;font-family:monospace"><?= $h($d['version'] ?? '-') ?></td>
    </tr>
    <tr style="background:rgba(0,0,0,.04)">
      <td style="padding:6px 10px;color:var(--c-muted)">Pripojeni k DB</td>
      <td style="padding:6px 10px">
        <?php if ($d['db_ok'] ?? false): ?>
          <span style="color:#40a02b">OK</span>
        <?php else: ?>
          <span style="color:#d20f39">CHYBA: <?= $h($d['db_error'] ?? '') ?></span>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td style="padding:6px 10px;color:var(--c-muted)">Stranky v DB celkem</td>
      <td style="padding:6px 10px;font-family:monospace"><?= (int) ($d['pages_total'] ?? 0) ?></td>
    </tr>
    <tr style="background:rgba(0,0,0,.04)">
      <td style="padding:6px 10px;color:var(--c-muted)">Homepage (url_path='')</td>
      <td style="padding:6px 10px">
        <?php if (!empty($d['homepage'])): ?>
          <span style="color:#40a02b">Nalezena - id=<?= (int) $d['homepage']['id'] ?>, title=<?= $h($d['homepage']['title'] ?? '') ?></span>
        <?php elseif ($d['db_ok'] ?? false): ?>
          <span style="color:#d20f39">Zadna stranka s url_path='' - inline edit selze, spust seed</span>
        <?php else: ?>
          <span style="color:#e64553">DB nedostupna</span>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td style="padding:6px 10px;color:var(--c-muted)">Demo state fingerprint</td>
      <td style="padding:6px 10px">
        <?php if (!empty($d['demo_fingerprint'])): ?>
          <code style="overflow-wrap:anywhere"><?= $h($d['demo_fingerprint']) ?></code>
          <div class="help-text">Po Armagedon -&gt; Demo obsah musi byt tento SHA-256 stejny lokalne i online.</div>
        <?php else: ?>
          <span style="color:#d20f39">NEDOSTUPNY: <?= $h($d['fingerprint_error'] ?? 'neznamy duvod') ?></span>
        <?php endif; ?>
      </td>
    </tr>
  </table>
</div>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
