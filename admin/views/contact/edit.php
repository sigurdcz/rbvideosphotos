<?php
$h  = fn($v) => htmlspecialchars((string)($v ?? ''));
$c  = $contact ?? [];
$v  = fn($key) => $h($c[$key] ?? '');
$socials = ['instagram','facebook','youtube','tiktok','linkedin','twitter','pinterest','whatsapp','telegram'];
ob_start();
?>
<div class="page-header">
  <h1>Kontakt</h1>
  <a href="/kontakt" target="_blank" class="btn btn-secondary">&#128065; Náhled stránky</a>
</div>

<form method="POST" action="/admin/contact/update">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">

  <div data-tabs-container>
    <div class="tabs">
      <button type="button" class="tab-btn" data-tab="basic">Základní info</button>
      <button type="button" class="tab-btn" data-tab="social">Sociální sítě</button>
      <button type="button" class="tab-btn" data-tab="map">Mapa &amp; Formulář</button>
    </div>

    <!-- ── Základní info ──────────────────────────────── -->
    <div class="tab-pane" id="tab-basic">
      <div class="card">
        <div class="card-title">Telefonní čísla</div>
        <div class="form-row">
          <div class="form-group">
            <label>Telefon 1</label>
            <input type="text" name="phone_1" value="<?= $v('phone_1') ?>" placeholder="+420 123 456 789">
          </div>
          <div class="form-group">
            <label>Telefon 2</label>
            <input type="text" name="phone_2" value="<?= $v('phone_2') ?>" placeholder="+420 987 654 321">
          </div>
          <div class="form-group">
            <label>Telefon 3</label>
            <input type="text" name="phone_3" value="<?= $v('phone_3') ?>" placeholder="Volitelné">
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-title">E-mail</div>
        <div class="form-group">
          <label>E-mailová adresa (zobrazená na webu)</label>
          <input type="email" name="email" value="<?= $v('email') ?>" placeholder="info@firma.cz">
        </div>
      </div>

      <div class="card">
        <div class="card-title">Adresa</div>
        <div class="form-group">
          <label>Adresa 1 (ulice, město)</label>
          <textarea name="address_1" style="min-height:70px" placeholder="Příklad ulice 123&#10;785 01 Město"><?= $v('address_1') ?></textarea>
        </div>
        <div class="form-group">
          <label>Adresa 2 <small style="font-weight:400;color:var(--c-muted)">(volitelná druhá provozovna / pobočka)</small></label>
          <textarea name="address_2" style="min-height:70px" placeholder="Volitelné"><?= $v('address_2') ?></textarea>
        </div>
      </div>
    </div>

    <!-- ── Sociální sítě ───────────────────────────────── -->
    <div class="tab-pane" id="tab-social">
      <div class="card">
        <p style="color:var(--c-muted);font-size:13px;margin-bottom:20px">
          Vyplňte až 5 sociálních sítí. Vyberte typ a vložte URL profilu.
        </p>
        <?php for ($i = 1; $i <= 5; $i++): ?>
        <div class="form-row" style="align-items:flex-end;margin-bottom:16px">
          <div class="form-group" style="flex:0 0 180px">
            <label>Síť <?= $i ?></label>
            <select name="social_<?= $i ?>_type">
              <option value="">— nevyplněno —</option>
              <?php foreach ($socials as $s): ?>
              <option value="<?= $h($s) ?>" <?= ($c['social_' . $i . '_type'] ?? '') === $s ? 'selected' : '' ?>>
                <?= ucfirst($h($s)) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="flex:1">
            <label>URL profilu</label>
            <input type="url" name="social_<?= $i ?>_url"
                   value="<?= $v('social_' . $i . '_url') ?>"
                   placeholder="https://www.instagram.com/vas-profil">
          </div>
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- ── Mapa & Formulář ────────────────────────────── -->
    <div class="tab-pane" id="tab-map">
      <div class="card">
        <div class="card-title">Google Mapy</div>
        <div class="form-group">
          <label>Odkaz na Google Mapy</label>
          <input type="url" name="maps_url" value="<?= $v('maps_url') ?>"
                 placeholder="https://maps.google.com/...">
          <div class="form-hint">Zkopírujte odkaz z Google Maps (tlačítko Sdílet → Zkopírovat odkaz)</div>
        </div>
        <?php if (!empty($c['maps_url'])): ?>
        <div style="margin-top:12px">
          <a href="<?= $h($c['maps_url']) ?>" target="_blank" class="btn btn-sm btn-secondary">Otevřít mapu &rarr;</a>
        </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-title">Kontaktní formulář</div>
        <div class="form-group">
          <label>E-mail pro příjem zpráv z formuláře</label>
          <input type="email" name="form_email" value="<?= $v('form_email') ?>"
                 placeholder="notifikace@firma.cz">
          <div class="form-hint">Na tuto adresu budou chodit zprávy odeslané přes kontaktní formulář na webu</div>
        </div>
      </div>
    </div>
  </div>

  <div style="margin-top:16px">
    <button type="submit" class="btn btn-primary">Uložit kontaktní informace</button>
  </div>
</form>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
