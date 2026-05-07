<?php
$pageTitle = 'Dashboard';
ob_start();
?>
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-num"><?= (int)$pagesCount ?></div>
    <div class="stat-lbl">Stranky</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= (int)$templatesCount ?></div>
    <div class="stat-lbl">Layouty stranek</div>
  </div>
  <?php if ($site): ?>
  <div class="stat-card stat-card--wide">
    <div class="stat-url"><?= htmlspecialchars($site['domain']) ?></div>
    <div class="stat-lbl">Domena</div>
  </div>
  <?php endif; ?>
</div>

<?php if (!$site): ?>
  <div class="card">
    <p>Nejprve <a href="/admin/settings">nastavte web</a> (nazev + domenu).</p>
  </div>
<?php elseif ((int)$pagesCount === 0): ?>
  <div class="alert alert-warning">
    Databaze neobsahuje zadne stranky. <a href="/admin/setup"><strong>Spustit pocatecni seed -&gt;</strong></a>
  </div>
<?php else: ?>

<div class="card">
  <div class="card-title">Rychle akce</div>
  <div class="actions">
    <a href="/admin/pages/create" class="btn btn-primary">+ Nova stranka</a>
    <a href="/admin/layouts/create" class="btn btn-secondary">+ Novy layout</a>
    <a href="/admin/publish" class="btn btn-success">Publikovat</a>
    <a href="/?cms_preview=1" target="_blank" class="btn btn-secondary">&#128065; Nahled webu</a>
    <a href="/" target="_blank" class="btn btn-inline-edit">&#9998; Editovat inline</a>
  </div>
</div>

<div class="card">
  <div class="card-title">Posledni publikovani</div>
  <?php if (empty($history)): ?>
    <p style="color:var(--c-muted)">Zatim zadne publikovani.</p>
  <?php else: ?>
    <table class="history-table">
      <thead><tr><th>#</th><th>Verze</th><th>Status</th><th>Autor</th><th>Datum</th></tr></thead>
      <tbody>
        <?php foreach ($history as $h): ?>
        <tr>
          <td><?= (int)$h['id'] ?></td>
          <td>v<?= (int)$h['version'] ?></td>
          <td><span class="badge badge-<?= $h['status'] === 'success' ? 'published' : 'error' ?>"><?= htmlspecialchars($h['status']) ?></span></td>
          <td><?= htmlspecialchars($h['user_email'] ?? '') ?></td>
          <td><?= htmlspecialchars($h['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
