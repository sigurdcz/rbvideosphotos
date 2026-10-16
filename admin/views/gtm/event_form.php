<?php
$pageTitle = 'Google Tag Manager - Upravit event';
$h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$definition = $definition ?? [];
$catalogItem = $catalogItem ?? null;

$pretty = static function (mixed $value): string {
    if ($value === null || $value === []) {
        return '';
    }
    if (is_string($value)) {
        return $value;
    }
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json === false ? '' : $json;
};

ob_start();
?>
<div class="page-header">
  <h1>Upravit GTM event</h1>
  <div class="actions">
    <a class="btn btn-secondary" href="/admin/gtm/events">Zpet na eventy</a>
    <a class="btn btn-secondary" href="/admin/gtm">Prehled</a>
  </div>
</div>

<div class="card">
  <div class="card-title">Zakladni nastaveni</div>
  <p class="help-text" style="margin-bottom:16px">
    Basic formular spravuje stejné eventové definice, které se budou později mapovat na GTM tagy a triggery.
    Surový JSON je schován v rozšířené sekci dole.
  </p>

  <form method="POST" action="/admin/gtm/events/update">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <input type="hidden" name="id" value="<?= (int)($definition['id'] ?? 0) ?>">

    <div class="form-row">
      <div class="form-group">
        <label>Event key</label>
        <input type="text" value="<?= $h($definition['event_key'] ?? '') ?>" readonly>
      </div>
      <div class="form-group">
        <label>Verze</label>
        <input type="text" value="<?= (int)($definition['local_version'] ?? 1) ?>" readonly>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Název</label>
        <input type="text" name="label" value="<?= $h($definition['label'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Kategorie</label>
        <input type="text" name="category" value="<?= $h($definition['category'] ?? '') ?>" required>
      </div>
    </div>

    <div class="form-group">
      <label>Popis</label>
      <textarea name="description" rows="4"><?= $h($definition['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group checkbox-row">
      <input type="checkbox" name="enabled" value="1" <?= !empty($definition['enabled']) ? 'checked' : '' ?>>
      <span>Event je aktivní</span>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Název tagu</label>
        <input type="text" name="tag_name" value="<?= $h($definition['tag_name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Název triggeru</label>
        <input type="text" name="trigger_name" value="<?= $h($definition['trigger_name'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Typ tagu</label>
        <select name="tag_type">
          <option value="gaawe" <?= ($definition['tag_type'] ?? '') === 'gaawe' ? 'selected' : '' ?>>Google Analytics: GA4 event</option>
        </select>
      </div>
      <div class="form-group">
        <label>Typ triggeru</label>
        <select name="trigger_type">
          <option value="custom_event" <?= ($definition['trigger_type'] ?? '') === 'custom_event' ? 'selected' : '' ?>>Custom event</option>
        </select>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Uložit událost</button>
  </form>
</div>

<div class="card">
  <div class="card-title">Pokročilé GTM parametry</div>
  <details>
    <summary class="help-text" style="cursor:pointer;margin-bottom:14px">Zobrazit pokročilý editor parametru a filtrů</summary>
    <form method="POST" action="/admin/gtm/events/update">
      <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
      <input type="hidden" name="id" value="<?= (int)($definition['id'] ?? 0) ?>">
      <input type="hidden" name="label" value="<?= $h($definition['label'] ?? '') ?>">
      <input type="hidden" name="category" value="<?= $h($definition['category'] ?? '') ?>">
      <input type="hidden" name="description" value="<?= $h($definition['description'] ?? '') ?>">
      <input type="hidden" name="enabled" value="<?= !empty($definition['enabled']) ? '1' : '0' ?>">
      <input type="hidden" name="tag_name" value="<?= $h($definition['tag_name'] ?? '') ?>">
      <input type="hidden" name="trigger_name" value="<?= $h($definition['trigger_name'] ?? '') ?>">
      <input type="hidden" name="tag_type" value="<?= $h($definition['tag_type'] ?? 'gaawe') ?>">
      <input type="hidden" name="trigger_type" value="<?= $h($definition['trigger_type'] ?? 'custom_event') ?>">

      <div class="form-group">
        <label>Tag parameter JSON</label>
        <textarea class="code" name="tag_parameter_json" rows="8"><?= $h($pretty($definition['tag_parameter_json'] ?? [])) ?></textarea>
      </div>
      <div class="form-group">
        <label>Trigger filter JSON</label>
        <textarea class="code" name="trigger_filter_json" rows="8"><?= $h($pretty($definition['trigger_filter_json'] ?? [])) ?></textarea>
      </div>
      <div class="form-group">
        <label>Notes JSON</label>
        <textarea class="code" name="notes_json" rows="8"><?= $h($pretty($definition['notes_json'] ?? [])) ?></textarea>
      </div>
      <button type="submit" class="btn btn-secondary">Uložit pokročilé parametry</button>
    </form>
  </details>
</div>

<?php if ($catalogItem): ?>
<div class="card">
  <div class="card-title">Šablona z katalogu</div>
  <div class="form-row">
    <div class="form-group">
      <label>Kategorie</label>
      <input type="text" value="<?= $h($catalogItem['category'] ?? '') ?>" readonly>
    </div>
    <div class="form-group">
      <label>Očekávaný tag</label>
      <input type="text" value="<?= $h($catalogItem['tag_name'] ?? '') ?>" readonly>
    </div>
  </div>
  <p class="help-text"><?= $h($catalogItem['description'] ?? '') ?></p>
</div>
<?php endif; ?>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
