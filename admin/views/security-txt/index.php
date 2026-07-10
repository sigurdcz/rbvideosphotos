<?php
$pageTitle = 'Security.txt';
$h = fn($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$config = $state['config'] ?? [];
$schemaAvailable = !empty($state['schema_available']);
$contacts = is_array($config['contacts'] ?? null) ? $config['contacts'] : [];
if ($contacts === []) $contacts = [''];
$expiration = $state['expiration'] ?? ['code' => 'missing', 'label' => 'Expirace chybi'];
$endpoint = $state['endpoint'] ?? ['code' => 'disabled', 'label' => 'Vypnuto', 'content_type' => null];
$validationErrors = array_merge($state['complete_validation']['errors'] ?? [], $formErrors ?? []);
$preview = (string) ($state['preview'] ?? '');
ob_start();
?>
<div class="page-header security-txt-header">
  <div>
    <h1>Security.txt</h1>
    <p>Kontaktní informace pro odpovědné hlášení zranitelností podle RFC 9116.</p>
  </div>
  <div class="actions">
    <a class="btn btn-secondary" href="/.well-known/security.txt" target="_blank" rel="noopener">Otevřít security.txt</a>
  </div>
</div>

<?php if (!$site): ?>
  <div class="alert alert-error">Web nebyl nalezen.</div>
<?php else: ?>
  <?php if (!$schemaAvailable): ?>
    <div class="alert alert-error">Databázové tabulky security.txt chybí. Spusť migraci <code>020_create_security_txt_tables.sql</code>. Funkce zůstává vypnutá a veřejný endpoint vrací 404.</div>
  <?php endif; ?>

  <div class="security-status-grid">
    <div class="security-status-card security-status-card--<?= $h(!empty($config['enabled']) ? 'ok' : 'muted') ?>">
      <span>Generátor</span>
      <strong><?= !empty($config['enabled']) ? 'Zapnutý' : 'Vypnutý' ?></strong>
    </div>
    <div class="security-status-card security-status-card--<?= $h(($state['complete_validation']['valid'] ?? false) ? 'ok' : 'error') ?>">
      <span>Validace</span>
      <strong><?= !empty($state['complete_validation']['valid']) ? 'Platná konfigurace' : 'Vyžaduje opravu' ?></strong>
    </div>
    <div class="security-status-card security-status-card--<?= $h(in_array($expiration['code'], ['ok'], true) ? 'ok' : ($expiration['code'] === 'warning' ? 'warning' : 'error')) ?>">
      <span>Expirace</span>
      <strong><?= $h($expiration['label']) ?></strong>
    </div>
    <div class="security-status-card security-status-card--<?= $h($endpoint['code'] === 'ok' ? 'ok' : 'muted') ?>">
      <span>Interní endpoint check</span>
      <strong><?= $h($endpoint['label']) ?></strong>
      <?php if (!empty($endpoint['content_type'])): ?><small><?= $h($endpoint['content_type']) ?></small><?php endif; ?>
    </div>
  </div>

  <?php if ($validationErrors !== []): ?>
    <div class="alert alert-error">
      <strong>Stav validace:</strong>
      <ul class="security-error-list">
        <?php foreach (array_unique($validationErrors) as $message): ?>
          <li><?= $h($message) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="security-layout">
    <form class="card security-form" method="POST" action="/admin/security-txt/update" data-security-form>
      <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
      <fieldset <?= $schemaAvailable ? '' : 'disabled' ?>>
        <div class="security-toggle-row">
          <div>
            <div class="card-title">Publikování</div>
            <p>Je-li vypnuto nebo konfigurace expiruje, oba veřejné endpointy vrátí 404.</p>
          </div>
          <label class="toggle-switch">
            <input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?> data-security-enabled>
            <span>Zapnuto</span>
          </label>
        </div>

        <div class="form-group">
          <div class="security-field-heading">
            <label>Contact <span class="required">povinné při zapnutí</span></label>
            <button type="button" class="btn btn-sm btn-secondary" data-contact-add>Přidat kontakt</button>
          </div>
          <p class="form-help">Pořadí určuje preferenci. Povolené formáty: <code>mailto:</code>, <code>tel:</code>, <code>https://</code>.</p>
          <div class="security-contacts" data-contact-list>
            <?php foreach ($contacts as $contact): ?>
              <div class="security-contact-row" data-contact-row>
                <span class="security-contact-handle" aria-hidden="true">↕</span>
                <input type="text" name="contacts[]" value="<?= $h($contact) ?>" maxlength="2048" placeholder="mailto:security@example.com" data-contact-input>
                <button type="button" class="btn btn-sm btn-secondary" data-contact-up aria-label="Posunout nahoru">↑</button>
                <button type="button" class="btn btn-sm btn-secondary" data-contact-down aria-label="Posunout dolů">↓</button>
                <button type="button" class="btn btn-sm btn-danger" data-contact-remove aria-label="Odstranit kontakt">×</button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="security-expires">Expires v UTC <span class="required">povinné při zapnutí</span></label>
            <input id="security-expires" type="text" name="expires" value="<?= $h($config['expires_at'] ?? '') ?>" maxlength="35" placeholder="2027-01-10T23:59:59Z" data-security-field>
            <p class="form-help">Skutečný okamžik kontroly, méně než rok v budoucnosti. Hodnota se při HTTP požadavku neposouvá.</p>
          </div>
          <div class="form-group">
            <label for="security-languages">Preferred-Languages</label>
            <input id="security-languages" type="text" name="preferred_languages" value="<?= $h($config['preferred_languages'] ?? '') ?>" maxlength="255" placeholder="cs, en" data-security-field>
            <p class="form-help">Jeden čárkami oddělený seznam BCP 47 jazykových tagů.</p>
          </div>
        </div>

        <div class="form-group">
          <label for="security-canonical">Canonical URL</label>
          <input id="security-canonical" type="url" name="canonical_url" value="<?= $h($config['canonical_url'] ?? '') ?>" maxlength="2048" placeholder="https://example.com/.well-known/security.txt" data-security-field>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="security-policy">Policy URL</label>
            <input id="security-policy" type="url" name="policy_url" value="<?= $h($config['policy_url'] ?? '') ?>" maxlength="2048" placeholder="https://example.com/security-policy" data-security-field>
          </div>
          <div class="form-group">
            <label for="security-encryption">Encryption URL</label>
            <input id="security-encryption" type="url" name="encryption_url" value="<?= $h($config['encryption_url'] ?? '') ?>" maxlength="2048" placeholder="https://example.com/pgp-key.txt" data-security-field>
            <p class="form-help">Pouze URL veřejného klíče. Nikdy nevkládej soukromý ani celý veřejný klíč.</p>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="security-acknowledgments">Acknowledgments URL</label>
            <input id="security-acknowledgments" type="url" name="acknowledgments_url" value="<?= $h($config['acknowledgments_url'] ?? '') ?>" maxlength="2048" placeholder="https://example.com/security-thanks" data-security-field>
          </div>
          <div class="form-group">
            <label for="security-hiring">Hiring URL</label>
            <input id="security-hiring" type="url" name="hiring_url" value="<?= $h($config['hiring_url'] ?? '') ?>" maxlength="2048" placeholder="https://example.com/jobs" data-security-field>
          </div>
        </div>

        <div class="form-group">
          <label for="security-comments">Komentáře</label>
          <textarea id="security-comments" name="comments" rows="4" maxlength="4000" placeholder="Každý řádek bude bezpečně zapsán jako komentář začínající #." data-security-comments><?= $h($config['comments'] ?? '') ?></textarea>
        </div>

        <div class="security-form-actions">
          <button type="submit" class="btn btn-primary">Uložit security.txt</button>
          <span data-live-validation aria-live="polite">Serverová validace proběhne při uložení.</span>
        </div>
      </fieldset>
    </form>

    <aside class="card security-preview-card">
      <div class="card-title">Živý náhled</div>
      <p>Náhled je informativní. Rozhodující je serverová validace.</p>
      <pre data-security-preview><?= $h($preview !== '' ? $preview : "# Security contact information\n") ?></pre>
    </aside>
  </div>

  <template id="security-contact-template">
    <div class="security-contact-row" data-contact-row>
      <span class="security-contact-handle" aria-hidden="true">↕</span>
      <input type="text" name="contacts[]" maxlength="2048" placeholder="mailto:security@example.com" data-contact-input>
      <button type="button" class="btn btn-sm btn-secondary" data-contact-up aria-label="Posunout nahoru">↑</button>
      <button type="button" class="btn btn-sm btn-secondary" data-contact-down aria-label="Posunout dolů">↓</button>
      <button type="button" class="btn btn-sm btn-danger" data-contact-remove aria-label="Odstranit kontakt">×</button>
    </div>
  </template>

  <div class="card security-note">
    <div class="card-title">Důležité</div>
    <p>Zveřejnění <code>security.txt</code> samo o sobě neuděluje oprávnění k bezpečnostnímu testování. Rozsah a podmínky testování popiš v odkazované bezpečnostní politice.</p>
  </div>
<?php endif; ?>

<script>
(() => {
  const form = document.querySelector('[data-security-form]');
  if (!form) return;
  const list = form.querySelector('[data-contact-list]');
  const template = document.getElementById('security-contact-template');
  const preview = document.querySelector('[data-security-preview]');
  const liveStatus = document.querySelector('[data-live-validation]');

  const values = () => ({
    contacts: [...list.querySelectorAll('[data-contact-input]')].map(input => input.value.trim()).filter(Boolean),
    expires: form.elements.expires.value.trim(),
    languages: form.elements.preferred_languages.value.trim(),
    canonical: form.elements.canonical_url.value.trim(),
    policy: form.elements.policy_url.value.trim(),
    encryption: form.elements.encryption_url.value.trim(),
    acknowledgments: form.elements.acknowledgments_url.value.trim(),
    hiring: form.elements.hiring_url.value.trim(),
    comments: form.elements.comments.value.replace(/\r\n?/g, '\n').trim(),
  });

  const renderPreview = () => {
    const data = values();
    const lines = ['# Security contact information'];
    data.comments.split('\n').map(line => line.trim()).filter(Boolean).forEach(line => lines.push('# ' + line.replace(/^#\s*/, '')));
    lines.push('');
    data.contacts.forEach(value => lines.push('Contact: ' + value));
    if (data.expires) lines.push('Expires: ' + data.expires);
    if (data.languages) lines.push('Preferred-Languages: ' + data.languages);
    if (data.canonical) lines.push('Canonical: ' + data.canonical);
    if (data.policy) lines.push('Policy: ' + data.policy);
    if (data.encryption) lines.push('Encryption: ' + data.encryption);
    if (data.acknowledgments) lines.push('Acknowledgments: ' + data.acknowledgments);
    if (data.hiring) lines.push('Hiring: ' + data.hiring);
    preview.textContent = lines.join('\n') + '\n';

    const enabled = !!form.querySelector('[data-security-enabled]').checked;
    if (!enabled) liveStatus.textContent = 'Generátor je vypnutý; endpoint vrátí 404.';
    else if (!data.contacts.length) liveStatus.textContent = 'Chybí alespoň jeden Contact.';
    else if (!data.expires) liveStatus.textContent = 'Chybí Expires.';
    else liveStatus.textContent = 'Základní pole jsou vyplněna; server ověří formát a expiraci.';
  };

  const syncButtons = () => {
    const rows = [...list.querySelectorAll('[data-contact-row]')];
    rows.forEach((row, index) => {
      row.querySelector('[data-contact-up]').disabled = index === 0;
      row.querySelector('[data-contact-down]').disabled = index === rows.length - 1;
    });
  };

  form.addEventListener('input', renderPreview);
  form.addEventListener('change', renderPreview);
  form.querySelector('[data-contact-add]').addEventListener('click', () => {
    list.appendChild(template.content.cloneNode(true));
    syncButtons();
    renderPreview();
    list.querySelector('[data-contact-row]:last-child [data-contact-input]').focus();
  });
  list.addEventListener('click', event => {
    const row = event.target.closest('[data-contact-row]');
    if (!row) return;
    if (event.target.closest('[data-contact-remove]')) {
      row.remove();
      if (!list.querySelector('[data-contact-row]')) list.appendChild(template.content.cloneNode(true));
    } else if (event.target.closest('[data-contact-up]') && row.previousElementSibling) {
      list.insertBefore(row, row.previousElementSibling);
    } else if (event.target.closest('[data-contact-down]') && row.nextElementSibling) {
      list.insertBefore(row.nextElementSibling, row);
    } else return;
    syncButtons();
    renderPreview();
  });

  syncButtons();
  renderPreview();
})();
</script>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
