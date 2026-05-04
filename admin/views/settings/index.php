<?php
$pageTitle = 'Nastavení webu';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));

// Auto-detect correct domain for the warning / pre-fill
$detectedUrl   = \App\Core\Config::get('app', 'url');
$storedDomain  = $site['domain'] ?? '';
$isLocalDomain = str_contains($storedDomain, 'localhost') || $storedDomain === '';
$domainValue   = $isLocalDomain ? $detectedUrl : $storedDomain;

ob_start();
?>
<div class="page-header">
  <h1>Nastavení webu</h1>
</div>

<?php if ($isLocalDomain && $detectedUrl && !str_contains($detectedUrl, 'localhost')): ?>
<div class="alert alert-warning" style="margin-bottom:16px">
  ⚠️ Doména je nastavena na <strong><?= $h($storedDomain ?: '(prázdná)') ?></strong>.
  Automaticky jsem předvyplnil správnou URL z aktuálního requestu — zkontroluj a ulož.
</div>
<?php endif; ?>

<form method="POST" action="/admin/settings/update">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">

  <div class="card">
    <div class="card-title">Základní informace</div>
    <div class="form-row">
      <div class="form-group">
        <label>Název webu *</label>
        <input type="text" name="name" value="<?= $h($site['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Doména *</label>
        <input type="url" name="domain" value="<?= $h($domainValue) ?>" placeholder="https://example.cz" required>
      </div>
    </div>
    <div class="form-group">
      <label>Výchozí jazyk</label>
      <select name="default_language">
        <option value="cs" <?= ($site['default_language'] ?? 'cs') === 'cs' ? 'selected' : '' ?>>Čeština</option>
        <option value="en" <?= ($site['default_language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
        <option value="sk" <?= ($site['default_language'] ?? '') === 'sk' ? 'selected' : '' ?>>Slovenčina</option>
      </select>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Analytics</div>
    <div class="form-row">
      <div class="form-group">
        <label>Google Analytics ID</label>
        <input type="text" name="google_analytics_id" value="<?= $h($site['google_analytics_id'] ?? '') ?>" placeholder="G-XXXXXXXXXX">
      </div>
      <div class="form-group">
        <label>Google Tag Manager ID</label>
        <input type="text" name="google_tag_manager_id" value="<?= $h($site['google_tag_manager_id'] ?? '') ?>" placeholder="GTM-XXXXXXX">
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Vlastní skripty</div>
    <div class="form-group">
      <label>Skript do &lt;head&gt;</label>
      <textarea class="code" name="head_scripts" rows="5"><?= $h($site['head_scripts'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label>Skript před &lt;/body&gt;</label>
      <textarea class="code" name="body_scripts" rows="5"><?= $h($site['body_scripts'] ?? '') ?></textarea>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">Uložit nastavení</button>
</form>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
