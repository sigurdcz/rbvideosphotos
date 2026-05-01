<?php
$pageTitle = 'Dashboard';
ob_start();
?>
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-num"><?= (int)$pagesCount ?></div>
    <div class="stat-lbl">Stránky</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= (int)$templatesCount ?></div>
    <div class="stat-lbl">Šablony</div>
  </div>
  <?php if ($site): ?>
  <div class="stat-card">
    <div class="stat-num" style="font-size:16px"><?= htmlspecialchars($site['domain']) ?></div>
    <div class="stat-lbl">Doména</div>
  </div>
  <?php endif; ?>
</div>

<?php if (!$site): ?>
  <div class="card">
    <p>Nejprve <a href="/admin/settings">nastavte web</a> (název + doménu).</p>
  </div>
<?php else: ?>

<div class="card">
  <div class="card-title">Rychlé akce</div>
  <div class="actions">
    <a href="/admin/pages/create" class="btn btn-primary">+ Nová stránka</a>
    <a href="/admin/templates/create" class="btn btn-secondary">+ Nová šablona</a>
    <a href="/admin/publish" class="btn btn-success">Publikovat</a>
  </div>
</div>

<div class="card">
  <div class="card-title">Poslední publikování</div>
  <?php if (empty($history)): ?>
    <p style="color:var(--c-muted)">Zatím žádné publikování.</p>
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
