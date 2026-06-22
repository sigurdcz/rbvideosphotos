<?php
$pageTitle = 'Google Tag Manager - Verze';
$h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$versions = $versions ?? [];
ob_start();
?>
<div class="page-header">
  <h1>GTM verze</h1>
  <div class="actions">
    <a class="btn btn-secondary" href="/admin/gtm">Prehled</a>
    <a class="btn btn-secondary" href="/admin/gtm/entities">Entity</a>
  </div>
</div>

<div class="card publish-card">
  <div class="card-title">Vytvořit verzi</div>
  <form method="POST" action="/admin/gtm/versions/create">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <div class="form-group">
      <label>Název verze</label>
      <input type="text" name="version_name" placeholder="SEO Editor - GTM v1" required>
    </div>
    <div class="form-group">
      <label>Poznámka</label>
      <textarea name="version_notes" rows="4" placeholder="Auditní poznámka, co bylo ve workspace připraveno."></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Vytvořit verzi</button>
  </form>
</div>

<div class="card">
  <div class="card-title">Publikovat verzi</div>
  <p class="help-text" style="margin-bottom:12px">Publikace je oddělená od vytvoření verze. Vyber konkrétní verzi, kterou chceš zveřejnit.</p>
  <div style="overflow:auto">
    <table>
      <thead>
        <tr>
          <th>Název</th>
          <th>Path</th>
          <th>Fingerprint</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($versions as $version): ?>
        <tr>
          <td><?= $h($version['version_name'] ?? '') ?></td>
          <td style="word-break:break-all"><?= $h($version['version_path'] ?? '') ?></td>
          <td style="word-break:break-all"><?= $h($version['fingerprint'] ?? '') ?></td>
          <td><?= !empty($version['published']) ? '<span class="badge badge-success">Publikováno</span>' : '<span class="badge badge-draft">Draft</span>' ?></td>
          <td>
            <?php if (!empty($version['version_path'])): ?>
              <form method="POST" action="/admin/gtm/versions/publish" style="display:inline" onsubmit="return confirm('Publikovat vybranou verzi?')">
                <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
                <input type="hidden" name="version_path" value="<?= $h($version['version_path']) ?>">
                <button type="submit" class="btn btn-secondary btn-sm">Publikovat</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
