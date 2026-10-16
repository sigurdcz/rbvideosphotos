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
  Databáze neobsahuje žádné stránky. Spust seed pro nacteni ukazkovych dat RB Photos &amp; Videos.
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
    nahraje do mediální knihovny přes stejný proces jako běžný upload v administraci - včetně ALTů, titulků
    a variant (thumb, medium i OG 1200×630). Existující záznamy se najdou podle slugů a aktualizují se,
    nebudou se duplikovat.
  </p>
  <div class="alert alert-warning" style="margin-bottom:16px">
    Referencni profil po dokonceni explicitne aktivuje motiv <code>golden-bash-diagonal</code> a web znovu publikuje.
    Operace probiha v kratkych davkach a lze v ni pokracovat i po obnoveni stranky.
  </div>
  <button type="button" class="btn btn-primary" id="demo-seed-open">Doplnit demo obsah</button>
</div>

<div class="demo-seed-modal" id="demo-seed-modal" hidden role="dialog" aria-modal="true" aria-labelledby="demo-seed-title">
  <div class="demo-seed-dialog">
    <div class="demo-seed-head">
      <div><div class="card-title" id="demo-seed-title">Dávkové vytvoření Demo obsahu</div>
        <p>Nezavírejte kartu během právě zpracovávané dávky. Přerušenou úlohu lze obnovit později.</p></div>
      <button type="button" class="demo-seed-close" id="demo-seed-close" aria-label="Zavřít">&times;</button>
    </div>
    <div class="demo-seed-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="demo-seed-bar"></span></div>
    <div class="demo-seed-percent" id="demo-seed-percent">0 %</div>
    <div class="demo-seed-message" id="demo-seed-message">Připraveno ke spuštění.</div>
    <div class="demo-seed-count" id="demo-seed-count">Dávka 0 z 0</div>
    <div class="alert alert-error" id="demo-seed-error" hidden></div>
    <div class="demo-seed-actions">
      <button type="button" class="btn btn-primary" id="demo-seed-start">Spustit</button>
      <button type="button" class="btn btn-primary" id="demo-seed-retry" hidden>Opakovat poslední dávku</button>
      <button type="button" class="btn" id="demo-seed-resume" hidden>Obnovit stav</button>
    </div>
  </div>
</div>

<style>
.demo-seed-modal{position:fixed;inset:0;z-index:10000;display:grid;place-items:center;padding:24px;background:rgba(15,23,42,.62)}.demo-seed-modal[hidden]{display:none}
.demo-seed-dialog{width:min(620px,100%);padding:26px;border-radius:14px;background:#fff;box-shadow:0 24px 70px rgba(0,0,0,.28)}
.demo-seed-head{display:flex;justify-content:space-between;gap:20px}.demo-seed-head p{margin:7px 0 20px;color:var(--c-muted);font-size:13px}.demo-seed-close{width:38px;height:38px;border:0;border-radius:8px;background:#f4f4f5;font-size:26px;line-height:1;cursor:pointer}
.demo-seed-progress{height:14px;overflow:hidden;border-radius:999px;background:#e5e7eb}.demo-seed-progress span{display:block;width:0;height:100%;background:linear-gradient(90deg,#4f46e5,#7c3aed);transition:width .2s ease}
.demo-seed-percent{margin-top:12px;font-size:30px;font-weight:700}.demo-seed-message{margin-top:5px;font-weight:600}.demo-seed-count{margin-top:4px;color:var(--c-muted);font-size:13px}.demo-seed-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px}
</style>
<script>
(() => {
  const csrf = <?= json_encode((string)$_csrfToken) ?>;
  const byId = id => document.getElementById(id);
  const modal = byId('demo-seed-modal');
  const start = byId('demo-seed-start');
  const retry = byId('demo-seed-retry');
  const resume = byId('demo-seed-resume');
  const bar = byId('demo-seed-bar');
  const progress = bar.parentElement;
  const error = byId('demo-seed-error');
  let job = null;
  let active = false;
  let requestRunning = false;

  const readPayload = async response => {
    if (response.redirected && new URL(response.url, window.location.href).pathname === '/admin/login') {
      throw new Error('Přihlášení vypršelo. Přihlas se znovu a obnov stav dávky.');
    }
    let payload;
    try {
      payload = JSON.parse(await response.text());
    } catch (problem) {
      throw new Error(`Server nevrátil platný JSON (HTTP ${response.status}). Zkontroluj aktuální PHP chybu v serverovém logu.`);
    }
    if (!payload || !response.ok || payload.success !== true) {
      throw new Error(payload?.error || `Server vrátil HTTP ${response.status}.`);
    }
    return payload;
  };

  const post = async (url, values = {}) => {
    const body = new FormData();
    body.append('_csrf', csrf);
    Object.entries(values).forEach(([key, value]) => body.append(key, value));
    const response = await fetch(url, {method: 'POST', body, credentials: 'same-origin'});
    const payload = await readPayload(response);
    return payload.job;
  };

  const render = value => {
    job = value;
    const valuePercent = value ? Number(value.percent || 0) : 0;
    bar.style.width = `${valuePercent}%`;
    progress.setAttribute('aria-valuenow', String(valuePercent));
    byId('demo-seed-percent').textContent = `${valuePercent} %`;
    byId('demo-seed-message').textContent = value?.message || 'Připraveno ke spuštění.';
    byId('demo-seed-count').textContent = value ? `Dávka ${value.completed_units} z ${value.total_units}` : 'Dávka 0 z 0';
    error.hidden = !value?.error;
    error.textContent = value?.error || '';
    start.hidden = !!value && value.status !== 'completed';
    start.textContent = value?.status === 'completed' ? 'Spustit znovu' : 'Spustit';
    retry.hidden = value?.status !== 'failed';
    resume.hidden = value?.status !== 'running';
  };

  const showConnectionError = problem => {
    active = false;
    error.hidden = false;
    error.textContent = `Dávka byla pozastavena: ${problem.message}`;
    resume.hidden = false;
  };

  const runNext = async () => {
    if (!active || requestRunning || !job || job.status !== 'running') return;
    requestRunning = true;
    try {
      const next = await post('/admin/setup/demo-content/step', {job_token: job.job_token});
      render(next);
      if (next.status === 'running' && active) window.setTimeout(runNext, next.busy ? 1200 : 180);
      else active = false;
    } catch (problem) {
      showConnectionError(problem);
    } finally {
      requestRunning = false;
    }
  };

  const loadStatus = async autoRun => {
    const response = await fetch('/admin/setup/demo-content/status', {credentials: 'same-origin'});
    const payload = await readPayload(response);
    render(payload.job);
    if (payload.job?.status === 'running' && autoRun) {
      modal.hidden = false;
      active = true;
      runNext();
    }
  };

  byId('demo-seed-open').addEventListener('click', () => { modal.hidden = false; render(job); });
  byId('demo-seed-close').addEventListener('click', () => { modal.hidden = true; active = false; });
  start.addEventListener('click', async () => {
    start.disabled = true;
    error.hidden = true;
    try { render(await post('/admin/setup/demo-content/start')); active = true; runNext(); }
    catch (problem) { showConnectionError(problem); }
    finally { start.disabled = false; }
  });
  retry.addEventListener('click', async () => {
    retry.disabled = true;
    try { render(await post('/admin/setup/demo-content/retry', {job_token: job.job_token})); active = true; runNext(); }
    catch (problem) { showConnectionError(problem); }
    finally { retry.disabled = false; }
  });
  resume.addEventListener('click', async () => {
    try { await loadStatus(true); } catch (problem) { showConnectionError(problem); }
  });

  loadStatus(true).catch(problem => {
    error.hidden = false;
    error.textContent = problem.message;
  });
})();
</script>

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
