<?php
$pageTitle = 'Redirecty';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
ob_start();
?>
<div class="page-header">
  <h1>Redirecty</h1>
</div>

<div class="card" style="max-width:560px">
  <div class="card-title">Přidat redirect</div>
  <form method="POST" action="/admin/redirects/store">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Ze stránky (from)</label>
        <input type="text" name="from_path" placeholder="/stara-stranka" required>
      </div>
      <div class="form-group">
        <label>Na stránku (to)</label>
        <input type="text" name="to_path" placeholder="/nova-stranka" required>
      </div>
    </div>
    <div class="form-group">
      <label>Typ přesměrování</label>
      <select name="status_code">
        <option value="301">301 - Trvalý</option>
        <option value="302">302 - Dočasný</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Přidat redirect</button>
  </form>
</div>

<?php if (!empty($redirects)): ?>
<div class="card">
  <div class="card-title">Existující redirecty</div>
  <table>
    <thead><tr><th>Z</th><th>Na</th><th>Typ</th><th>Akce</th></tr></thead>
    <tbody>
      <?php foreach ($redirects as $r): ?>
      <tr>
        <td><code><?= $h($r['from_path']) ?></code></td>
        <td><code><?= $h($r['to_path']) ?></code></td>
        <td><?= (int)$r['status_code'] ?></td>
        <td>
          <form method="POST" action="/admin/redirects/delete" data-confirm="Smazat redirect?">
            <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-danger">Smazat</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
