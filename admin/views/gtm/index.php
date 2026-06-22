<?php
$pageTitle = 'Google Tag Manager';
$h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$connection = $connection ?? null;
$inventory = $inventory ?? [];
$accounts = $inventory['accounts'] ?? [];
$containers = $inventory['containers'] ?? [];
$workspaces = $inventory['workspaces'] ?? [];
$tags = $inventory['tags'] ?? [];
$triggers = $inventory['triggers'] ?? [];
$variables = $inventory['variables'] ?? [];
$versionsRemote = $inventory['versions'] ?? [];
$runs = $runs ?? [];
$versions = $versions ?? [];
$definitions = $definitions ?? [];
$entities = $entities ?? [];

$statusLabel = $connection ? ucfirst((string)($connection['status'] ?? 'disconnected')) : 'Disconnected';
$selectedAccountId = (string)($connection['account_id'] ?? '');
$selectedContainerId = (string)($connection['container_id'] ?? '');
$selectedWorkspaceId = (string)($connection['workspace_id'] ?? '');

ob_start();
?>
<div class="page-header">
  <h1>Google Tag Manager</h1>
  <div class="actions">
    <a class="btn btn-secondary" href="/admin/gtm/events">Eventy</a>
    <a class="btn btn-secondary" href="/admin/gtm/entities">Entity</a>
    <a class="btn btn-secondary" href="/admin/gtm/versions">Verze</a>
    <?php if ($connection): ?>
      <form method="POST" action="/admin/gtm/inventory/refresh" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
        <button type="submit" class="btn btn-primary">Nacist inventar</button>
      </form>
      <form method="POST" action="/admin/gtm/disconnect" style="display:inline" onsubmit="return confirm('Odpojit Google účet?')">
        <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
        <button type="submit" class="btn btn-danger">Odpojit</button>
      </form>
    <?php else: ?>
      <a class="btn btn-primary" href="/admin/gtm/connect">Pripojit Google účet</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$configReady): ?>
  <div class="alert alert-warning">
    GTM OAuth neni nakonfigurovan. Doplň `GTM_CLIENT_ID`, `GTM_CLIENT_SECRET` a `GTM_ENCRYPTION_KEY` do `.env`, jinak pripojeni nebude fungovat.
  </div>
<?php endif; ?>

<?php if (!empty($inventory['error'])): ?>
  <div class="alert alert-error"><?= $h($inventory['error']) ?></div>
<?php endif; ?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-num"><?= $connection ? '1' : '0' ?></div>
    <div class="stat-lbl">Připojení</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= count($definitions) ?></div>
    <div class="stat-lbl">Eventy v katalogu</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= count($entities) ?></div>
    <div class="stat-lbl">Spravované entity</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= count($versions) ?></div>
    <div class="stat-lbl">Lokální verze</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= count($tags) ?></div>
    <div class="stat-lbl">Remote tagy</div>
  </div>
</div>

<div class="card">
  <div class="card-title">Připojení</div>
  <?php if ($connection): ?>
    <div class="form-row">
      <div class="form-group">
        <label>Status</label>
        <input type="text" value="<?= $h($statusLabel) ?>" readonly>
      </div>
      <div class="form-group">
        <label>Scope</label>
        <input type="text" value="<?= $h((string)($connection['token_scopes'] ?? '')) ?>" readonly>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Account</label>
        <input type="text" value="<?= $h((string)($connection['account_name'] ?? $connection['account_id'] ?? 'Nevybráno')) ?>" readonly>
      </div>
      <div class="form-group">
        <label>Container</label>
        <input type="text" value="<?= $h((string)($connection['container_name'] ?? $connection['container_id'] ?? 'Nevybráno')) ?>" readonly>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Workspace</label>
        <input type="text" value="<?= $h((string)($connection['workspace_name'] ?? $connection['workspace_id'] ?? 'Nevybráno')) ?>" readonly>
      </div>
      <div class="form-group">
        <label>Poslední kontrola</label>
        <input type="text" value="<?= $h((string)($connection['last_verified_at'] ?? '')) ?>" readonly>
      </div>
    </div>
    <form method="POST" action="/admin/gtm/connection/select">
      <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
      <div class="form-row">
        <div class="form-group">
          <label>Google account</label>
          <select name="account_id">
            <option value="">Vyber účet</option>
            <?php foreach ($accounts as $account): ?>
              <?php $value = (string)($account['accountId'] ?? ''); ?>
              <option value="<?= $h($value) ?>" <?= $selectedAccountId === $value ? 'selected' : '' ?>>
                <?= $h((string)($account['name'] ?? $value)) ?><?= $value !== '' ? ' (' . $h($value) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Container</label>
          <select name="container_id">
            <option value="">Vyber container</option>
            <?php foreach ($containers as $container): ?>
              <?php $value = (string)($container['containerId'] ?? ''); ?>
              <option value="<?= $h($value) ?>" <?= $selectedContainerId === $value ? 'selected' : '' ?>>
                <?= $h((string)($container['name'] ?? $value)) ?><?= $value !== '' ? ' (' . $h($value) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Workspace</label>
          <select name="workspace_id">
            <option value="">Vyber workspace</option>
            <?php foreach ($workspaces as $workspace): ?>
              <?php $value = (string)($workspace['workspaceId'] ?? ''); ?>
              <option value="<?= $h($value) ?>" <?= $selectedWorkspaceId === $value ? 'selected' : '' ?>>
                <?= $h((string)($workspace['name'] ?? $value)) ?><?= $value !== '' ? ' (' . $h($value) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="display:flex;align-items:end">
          <button type="submit" class="btn btn-primary">Ulozit vyber</button>
        </div>
      </div>
    </form>
  <?php else: ?>
    <p class="help-text">
      Připoj Google účet, potom se zobrazí výběr accountu, containeru a workspace.
    </p>
  <?php endif; ?>
</div>

<div class="form-row">
  <div class="card">
    <div class="card-title">Inventar</div>
    <p class="help-text" style="margin-bottom:12px">Aktuální stav načtený z GTM workspace.</p>
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
        <label>Verze</label>
        <input type="text" value="<?= count($versionsRemote) ?>" readonly>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Rychle</div>
    <div class="actions" style="margin-bottom:12px">
      <form method="POST" action="/admin/gtm/plan" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
        <button type="submit" class="btn btn-secondary">Sestavit plan</button>
      </form>
      <form method="POST" action="/admin/gtm/apply" style="display:inline" onsubmit="return confirm('Aplikovat plan do workspace?')">
        <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
        <button type="submit" class="btn btn-primary">Aplikovat plan</button>
      </form>
    </div>
    <div class="help-text">Plan, apply a verze pracuji nad vybraným workspace. Publikace je samostatna akce.</div>
  </div>
</div>

<div class="card">
  <div class="card-title">Predpripravene eventy</div>
  <div style="overflow:auto">
    <table>
      <thead>
        <tr>
          <th>Event</th>
          <th>Kategorie</th>
          <th>Stav</th>
          <th>Tag</th>
          <th>Trigger</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($definitions as $definition): ?>
        <tr>
          <td>
            <strong><?= $h($definition['event_key']) ?></strong>
            <div class="form-hint"><?= $h($definition['label']) ?></div>
          </td>
          <td><?= $h($definition['category']) ?></td>
          <td><?= !empty($definition['enabled']) ? '<span class="badge badge-success">Zapnuto</span>' : '<span class="badge badge-draft">Vypnuto</span>' ?></td>
          <td><?= $h($definition['tag_type']) ?></td>
          <td><?= $h($definition['trigger_type']) ?></td>
          <td><a class="btn btn-secondary btn-sm" href="/admin/gtm/events/edit?id=<?= (int)$definition['id'] ?>">Upravit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="form-row">
  <div class="card">
    <div class="card-title">Posledni syncky</div>
    <div style="overflow:auto">
      <table class="history-table">
        <thead>
          <tr>
            <th>Cas</th>
            <th>Typ</th>
            <th>Status</th>
            <th>Souhrn</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($runs as $run): ?>
          <tr>
            <td><?= $h($run['created_at'] ?? '') ?></td>
            <td><?= $h($run['run_type'] ?? '') ?></td>
            <td><?= $h($run['status'] ?? '') ?></td>
            <td><?= $h($run['summary'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Posledni verze</div>
    <div style="overflow:auto">
      <table class="history-table">
        <thead>
          <tr>
            <th>Cas</th>
            <th>Nazev</th>
            <th>Stav</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($versions as $version): ?>
          <tr>
            <td><?= $h($version['created_at'] ?? '') ?></td>
            <td><?= $h($version['version_name'] ?? '') ?></td>
            <td><?= !empty($version['published']) ? '<span class="badge badge-success">Publikovana</span>' : '<span class="badge badge-draft">Draft</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
