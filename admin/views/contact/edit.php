<?php
$h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES);
$c = $contact ?? [];
$v = fn($key) => $h($c[$key] ?? '');
$fixedSocials = [
    1 => ['key' => 'instagram', 'label' => 'Instagram'],
    2 => ['key' => 'facebook', 'label' => 'Facebook'],
    3 => ['key' => 'youtube', 'label' => 'YouTube'],
    4 => ['key' => 'tiktok', 'label' => 'TikTok'],
    5 => ['key' => 'linkedin', 'label' => 'LinkedIn'],
];
ob_start();
?>
<div class="page-header">
  <h1>Kontakt</h1>
  <a href="/kontakt" target="_blank" class="btn btn-secondary">&#128065; Nahled stranky</a>
</div>

<form method="POST" action="/admin/contact/update">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">

  <div data-tabs-container>
    <div class="tabs">
      <button type="button" class="tab-btn" data-tab="basic">Zakladni info</button>
      <button type="button" class="tab-btn" data-tab="social">Socialni site</button>
      <button type="button" class="tab-btn" data-tab="map">Mapa &amp; formular</button>
    </div>

    <div class="tab-pane" id="tab-basic">
      <div class="card">
        <div class="card-title">Telefonni cisla</div>
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
            <input type="text" name="phone_3" value="<?= $v('phone_3') ?>" placeholder="Volitelne">
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-title">E-mail</div>
        <div class="form-group">
          <label>E-mailova adresa zobrazena na webu</label>
          <input type="email" name="email" value="<?= $v('email') ?>" placeholder="info@firma.cz">
        </div>
      </div>

      <div class="card">
        <div class="card-title">Adresa</div>
        <?php if (trim((string)($c['address_1'] ?? '')) === '' && trim((string)($c['address_2'] ?? '')) === ''): ?>
        <div class="alert alert-warning" style="margin-bottom:12px">
          Adresa neni vyplnena v databazi. Ve verejnem footeru se pak nezobrazi zadna lokalita.
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label>Adresa 1</label>
          <textarea name="address_1" style="min-height:70px" placeholder="Priklad ulice 123&#10;785 01 Mesto"><?= $v('address_1') ?></textarea>
        </div>
        <div class="form-group">
          <label>Adresa 2 <small style="font-weight:400;color:var(--c-muted)">(volitelna druha provozovna / pobocka)</small></label>
          <textarea name="address_2" style="min-height:70px" placeholder="Volitelne"><?= $v('address_2') ?></textarea>
        </div>
      </div>
    </div>

    <div class="tab-pane" id="tab-social">
      <div class="card">
        <p style="color:var(--c-muted);font-size:13px;margin-bottom:20px">
          Kazda socialni sit ma vlastni URL pole. Pokud ji nepouzivas, nech ji prazdnou.
        </p>
        <?php foreach ($fixedSocials as $i => $social): ?>
        <div class="form-row" style="align-items:flex-end;margin-bottom:16px">
          <div class="form-group" style="flex:0 0 180px">
            <label><?= $h($social['label']) ?></label>
            <input type="hidden" name="social_<?= $i ?>_type" value="<?= $h($social['key']) ?>">
            <input type="text" value="<?= $h(strtoupper(substr($social['key'], 0, 2))) ?>" disabled>
          </div>
          <div class="form-group" style="flex:1">
            <label>URL profilu</label>
            <input type="url" name="social_<?= $i ?>_url" value="<?= $v('social_' . $i . '_url') ?>" placeholder="https://www.instagram.com/vas-profil">
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tab-pane" id="tab-map">
      <div class="card">
        <div class="card-title">Google Mapy</div>
        <div class="form-group">
          <label>Odkaz na Google Mapy</label>
          <input type="url" name="maps_url" value="<?= $v('maps_url') ?>" placeholder="https://maps.google.com/...">
          <div class="form-hint">Zkopirujte odkaz z Google Maps.</div>
        </div>
        <div class="form-group">
          <label>Odkaz na Google recenze</label>
          <input type="url" name="reviews_url" value="<?= $v('reviews_url') ?>" placeholder="https://maps.app.goo.gl/...">
          <div class="form-hint">Verejny odkaz na firemni profil nebo prehled recenzi. Zobrazi se na strance Reference.</div>
        </div>
        <?php if (!empty($c['maps_url'])): ?>
        <div style="margin-top:12px">
          <a href="<?= $h($c['maps_url']) ?>" target="_blank" class="btn btn-sm btn-secondary">Otevrit mapu &rarr;</a>
        </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-title">Kontaktní formulář</div>
        <div class="form-group">
          <label>E-mail pro prijem zprav z formulare</label>
          <input type="email" name="form_email" value="<?= $v('form_email') ?>" placeholder="notifikace@firma.cz" <?= ($_user['role'] ?? '') !== 'admin' ? 'readonly' : '' ?>>
          <div class="form-hint">Na tuto adresu chodí kontaktní zprávy i odkazy pro obnovu hesla. Měnit ji smí pouze administrátor.</div>
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
