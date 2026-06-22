<?php
$pageTitle = 'Google Tag Manager - Entity';
$h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$connection = $connection ?? null;
$inventory = $inventory ?? [];
$entities = $entities ?? [];
$tags = $inventory['tags'] ?? [];
$triggers = $inventory['triggers'] ?? [];
$variables = $inventory['variables'] ?? [];
$run = $run ?? null;
$operations = $operations ?? [];
ob_start();
?>
<div class="page-header">
  <h1>GTM entity</h1>
  <div class="actions">
    <a class="btn btn-secondary" href="/admin/gtm">Prehled</a>
    <a class="btn btn-secondary" href="/admin/gtm/events">Eventy</a>
    <a class="btn btn-secondary" href="/admin/gtm/versions">Verze</a>
  </div>
</div>

<?php if (!empty($inventory['error'])): ?>
  <div class="alert alert-error"><?= $h($inventory['error']) ?></div>
<?php endif; ?>

<div class="form-row">
  <div class="card">
    <div class="card-title">Akce</div>
    <div class="actions" style="margin-bottom:12px">
      <form method="POST" action="/admin/gtm/inventory/refresh" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
        <button type="submit" class="btn btn-primary">Nacist inventar</button>
      </form>
      <form method="POST" action="/admin/gtm/plan" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
        <button type="submit" class="btn btn-secondary">Sestavit plan</button>
      </form>
      <form method="POST" action="/admin/gtm/apply" style="display:inline" onsubmit="return confirm('Aplikovat GTM plan?')">
        <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
        <button type="submit" class="btn btn-primary">Aplikovat plan</button>
      </form>
    </div>
    <p class="help-text">Plan odděluje návrh od zápisu do workspace. Aplikace proběhne až po potvrzení.</p>
  </div>

  <div class="card">
    <div class="card-title">Souhrn inventare</div>
    <div class="form-row">
      <div class="form-group">
        <label>Tagy</label>
        <input type="text" value="<?= count($tags) ?>" readonly>
      </div>
      <div class="form-group">
        <label>Triggery</label>
        <input type="text" value="<?= count($triggers) ?>" readonly>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Proměnné</label>
        <input type="text" value="<?= count($variables) ?>" readonly>
      </div>
      <div class="form-group">
        <label>Spravované entity</label>
        <input type="text" value="<?= count($entities) ?>" readonly>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-title">Spravovane entity</div>
  <div style="overflow:auto">
    <table>
      <thead>
        <tr>
          <th>Typ</th>
          <th>Název</th>
          <th>Ownership</th>
          <th>Remote path</th>
          <th>Fingerprint</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($entities as $entity): ?>
        <tr>
          <td><?= $h($entity['entity_type'] ?? '') ?></td>
          <td>
            <strong><?= $h($entity['display_name'] ?? '') ?></strong>
            <div class="form-hint"><?= $h($entity['management_key'] ?? '') ?></div>
          </td>
          <td><?= $h($entity['ownership'] ?? '') ?></td>
          <td style="word-break:break-all"><?= $h($entity['remote_path'] ?? '') ?></td>
          <td style="word-break:break-all"><?= $h($entity['fingerprint'] ?? '') ?></td>
          <td><?= $h($entity['last_status'] ?? '') ?></td>
          <td>
            <div class="actions">
              <form method="POST" action="/admin/gtm/entities/adopt" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
                <input type="hidden" name="entity_id" value="<?= (int)$entity['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm">Adoptovat</button>
              </form>
              <form method="POST" action="/admin/gtm/entities/delete" style="display:inline" onsubmit="return confirm('Smazat entitu z GTM?')">
                <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
                <input type="hidden" name="entity_id" value="<?= (int)$entity['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Smazat</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="form-row">
  <div class="card">
    <div class="card-title">Remote tagy</div>
    <div style="overflow:auto;max-height:280px">
      <table>
        <thead><tr><th>Název</th><th>ID</th><th>Path</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($tags, 0, 20) as $tag): ?>
          <tr>
            <td><?= $h($tag['name'] ?? '') ?></td>
            <td><?= $h($tag['tagId'] ?? '') ?></td>
            <td style="word-break:break-all"><?= $h($tag['path'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Remote triggery</div>
    <div style="overflow:auto;max-height:280px">
      <table>
        <thead><tr><th>Název</th><th>ID</th><th>Path</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($triggers, 0, 20) as $trigger): ?>
          <tr>
            <td><?= $h($trigger['name'] ?? '') ?></td>
            <td><?= $h($trigger['triggerId'] ?? '') ?></td>
            <td style="word-break:break-all"><?= $h($trigger['path'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-title">Remote proměnné</div>
  <div style="overflow:auto;max-height:280px">
    <table>
      <thead><tr><th>Název</th><th>ID</th><th>Path</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($variables, 0, 20) as $variable): ?>
        <tr>
          <td><?= $h($variable['name'] ?? '') ?></td>
          <td><?= $h($variable['variableId'] ?? '') ?></td>
          <td style="word-break:break-all"><?= $h($variable['path'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($run): ?>
<div class="card">
  <div class="card-title">Posledni plan / run #<?= (int)($run['id'] ?? 0) ?></div>
  <div style="overflow:auto">
    <table class="history-table">
      <thead>
        <tr>
          <th>Poradi</th>
          <th>Typ</th>
          <th>Akce</th>
          <th>Management key</th>
          <th>Stav</th>
          <th>Souhrn</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($operations as $operation): ?>
        <tr>
          <td><?= (int)($operation['operation_order'] ?? 0) ?></td>
          <td><?= $h($operation['entity_type'] ?? '') ?></td>
          <td><?= $h($operation['action'] ?? '') ?></td>
          <td style="word-break:break-all"><?= $h($operation['management_key'] ?? '') ?></td>
          <td><?= $h($operation['status'] ?? '') ?></td>
          <td><?= $h($operation['summary'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
