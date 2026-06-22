<?php
$pageTitle = 'Google Tag Manager - Eventy';
$h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$definitions = $definitions ?? [];
ob_start();
?>
<div class="page-header">
  <h1>GTM eventy</h1>
  <div class="actions">
    <a class="btn btn-secondary" href="/admin/gtm">Zpet na prehled</a>
    <a class="btn btn-secondary" href="/admin/gtm/entities">Entity</a>
    <a class="btn btn-secondary" href="/admin/gtm/versions">Verze</a>
  </div>
</div>

<div class="card">
  <div class="card-title">Katalog eventu</div>
  <p class="help-text" style="margin-bottom:12px">Predpripraveny katalog se doplnuje pri otevreni GTM sekce. Upravuj hlavne popis, stav a nazvy entit.</p>
  <div style="overflow:auto">
    <table>
      <thead>
        <tr>
          <th>Event</th>
          <th>Nazev</th>
          <th>Kategorie</th>
          <th>Stav</th>
          <th>Tag</th>
          <th>Trigger</th>
          <th>Verze</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($definitions as $definition): ?>
        <tr>
          <td><code><?= $h($definition['event_key']) ?></code></td>
          <td><?= $h($definition['label']) ?></td>
          <td><?= $h($definition['category']) ?></td>
          <td><?= !empty($definition['enabled']) ? '<span class="badge badge-success">Zapnuto</span>' : '<span class="badge badge-draft">Vypnuto</span>' ?></td>
          <td><?= $h($definition['tag_type']) ?></td>
          <td><?= $h($definition['trigger_type']) ?></td>
          <td><?= (int)($definition['local_version'] ?? 1) ?></td>
          <td><a class="btn btn-secondary btn-sm" href="/admin/gtm/events/edit?id=<?= (int)$definition['id'] ?>">Upravit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
