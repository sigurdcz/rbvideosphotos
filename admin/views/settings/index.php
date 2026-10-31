<?php
$pageTitle = 'Nastavení webu';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
$media = $media ?? [];
$contact = $contact ?? [];
$publicMediaUrl = static function (?string $path): string {
    $normalized = str_replace('\\', '/', trim((string)$path));
    if ($normalized === '') return '';
    if (preg_match('~https?://[^/]+(/storage/media/.*)$~i', $normalized, $m)) {
        return '/media/' . ltrim(substr($m[1], strlen('/storage/media/')), '/');
    }
    if (str_starts_with($normalized, '/media/')) return $normalized;
    if (str_starts_with($normalized, '/storage/media/')) return '/media/' . ltrim(substr($normalized, strlen('/storage/media/')), '/');
    if (str_starts_with($normalized, 'storage/media/')) return '/media/' . ltrim(substr($normalized, strlen('storage/media/')), '/');
    return '/' . ltrim($normalized, '/');
};
$mediaUrl = fn(array $m): string => $publicMediaUrl((string)($m['storage_path'] ?? ''));
$mediaThumb = function(array $m) use ($mediaUrl, $publicMediaUrl): string {
    foreach (($m['variants'] ?? []) as $v) {
        if (($v['format'] ?? '') === 'thumb' && !empty($v['path'])) {
            return $publicMediaUrl((string)$v['path']);
        }
    }
    return $mediaUrl($m);
};
$imageMediaById = [];
foreach ($media as $item) {
    if (str_starts_with((string)($item['mime_type'] ?? ''), 'image/')) {
        $imageMediaById[(int)$item['id']] = $item;
    }
}
$selectedDefaultOg = $imageMediaById[(int)($site['default_og_image_id'] ?? 0)] ?? null;
$selectedDefaultInstagram = $imageMediaById[(int)($site['default_instagram_image_id'] ?? 0)] ?? null;
$selectedDefaultTwitter = $imageMediaById[(int)($site['default_twitter_image_id'] ?? 0)] ?? null;

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$requestHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestUrl = $requestHost !== '' ? $scheme . '://' . $requestHost : '';

$storedDomain = trim((string)($site['domain'] ?? ''));
$isLocalDomain = $storedDomain === '' || str_contains($storedDomain, 'localhost');
$isRequestLocal = $requestUrl === '' || str_contains($requestUrl, 'localhost');
$domainValue = ($isLocalDomain && !$isRequestLocal) ? $requestUrl : $storedDomain;
$videoOverlayValue = (string)($site['video_overlay_opacity'] ?? '0.62');
$mediaImageQuality = (int)($site['media_image_quality'] ?? 90);
if ($mediaImageQuality <= 0) {
    $mediaImageQuality = 90;
}
$mediaImageQuality = max(1, min(100, $mediaImageQuality));
$spamConfig = $spamConfig ?? \App\Services\ContactSpamConfig::defaults();
$spamWeights = $spamConfig['weights'];

ob_start();
?>
<div class="page-header">
  <h1>Nastavení webu</h1>
</div>

<?php if ($isLocalDomain && !$isRequestLocal && $requestUrl !== ''): ?>
<div class="alert alert-warning" style="margin-bottom:16px">
  Domena je nastavena na <strong><?= $h($storedDomain ?: '(prazdna)') ?></strong>.
  Automaticky jsem predvyplnil spravnou URL z aktualniho requestu, zkontroluj ji a uloz.
</div>
<?php endif; ?>

<div class="settings-workspace" data-settings-workspace>
  <nav class="settings-tabs" role="tablist" aria-label="Sekce nastavení webu">
    <?php foreach ([
      'general' => 'Obecné',
      'appearance' => 'Vzhled a média',
      'communication' => 'Komunikace',
      'measurement' => 'Měření a skripty',
      'antispam' => 'Antispam',
      'dev' => 'Dev',
    ] as $settingsTabKey => $settingsTabLabel): ?>
      <button
        type="button"
        class="settings-tab"
        id="settings-tab-<?= $h($settingsTabKey) ?>"
        role="tab"
        aria-controls="settings-panel-<?= $h($settingsTabKey) ?>"
        aria-selected="false"
        tabindex="-1"
        data-settings-tab="<?= $h($settingsTabKey) ?>"
      ><?= $h($settingsTabLabel) ?></button>
    <?php endforeach; ?>
  </nav>

<form method="POST" action="/admin/settings/update" data-settings-form>
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">

  <div class="card settings-panel" id="settings-panel-antispam" role="tabpanel" aria-labelledby="settings-tab-antispam" data-settings-panel="antispam">
    <div class="card-title">Antispam kontaktního formuláře</div>
    <p class="help-text" style="margin-bottom:16px">Statický bodový filtr zpráv. Při dosažení prahu se zpráva uloží jako spam do E-mailů a zpráv, ale neodešle se přes SMTP. Honeypot a omezení podle IP zůstávají aktivní samostatně.</p>
    <?php if (!empty($spamConfigError)): ?><div class="alert alert-danger"><?= $h($spamConfigError) ?></div><?php endif; ?>
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="contact_spam_enabled" value="1" <?= !empty($spamConfig['enabled']) ? 'checked' : '' ?> style="width:auto">
        Zapnout statický antispam
      </label>
    </div>
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="contact_spam_notify_recipients" value="1" <?= !empty($spamConfig['notify_recipients']) ? 'checked' : '' ?> style="width:auto">
        Poslat příjemcům e-mailové upozornění na zachycený spam
      </label>
      <p class="help-text">Upozornění dostane hlavní příjemce kontaktního formuláře i všechny nastavené CC adresy. Původní text ani odkazy ze spamu se do e-mailu nekopírují.</p>
    </div>
    <div class="form-group" style="max-width:260px">
      <label>Práh pro označení spamu</label>
      <input type="number" name="contact_spam_threshold" min="1" max="100" required value="<?= $h($spamConfig['threshold']) ?>">
      <p class="help-text">Výchozí hodnota je 7 bodů.</p>
    </div>
    <div class="form-row-3">
      <?php foreach ([
        'known_phrase' => ['Známá spamová fráze', 'contact_spam_weight_known_phrase'],
        'url_in_name' => ['Odkaz ve jménu', 'contact_spam_weight_url_in_name'],
        'promotion_terms' => ['Dvě reklamní slova', 'contact_spam_weight_promotion_terms'],
        'call_to_action' => ['Reklamní výzva', 'contact_spam_weight_call_to_action'],
        'link_in_message' => ['Odkaz ve zprávě', 'contact_spam_weight_link_in_message'],
        'contact_junk' => ['Text navíc za e-mailem', 'contact_spam_weight_contact_junk'],
      ] as $weightKey => [$weightLabel, $weightName]): ?>
      <div class="form-group">
        <label><?= $h($weightLabel) ?></label>
        <input type="number" name="<?= $h($weightName) ?>" min="0" max="100" required value="<?= $h($spamWeights[$weightKey]) ?>">
      </div>
      <?php endforeach; ?>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Známé spamové fráze</label>
        <textarea name="contact_spam_known_phrases" rows="6" required><?= $h(\App\Services\ContactSpamConfig::listText($spamConfig['known_phrases'])) ?></textarea>
        <label>Kombinovaná pravidla kampaní</label>
        <textarea name="contact_spam_campaign_rules" rows="6" placeholder="část textu &amp;&amp; další část textu"><?= $h(\App\Services\ContactSpamConfig::listText($spamConfig['campaign_rules'])) ?></textarea>
        <small>Jedno pravidlo na řádek. Všechny části oddělené &amp;&amp; musí být současně ve jménu nebo zprávě. Shoda přidá váhu známé spamové fráze nejvýše jednou; práh i ostatní pravidla zůstávají platné. Čárky jsou součást textu.</small>
        <p class="help-text">Jedna fráze na řádek. Shoda přidá nastavený počet bodů.</p>
      </div>
      <div class="form-group">
        <label>Reklamní a hazardní slova</label>
        <textarea name="contact_spam_promotion_terms" rows="6" required><?= $h(\App\Services\ContactSpamConfig::listText($spamConfig['promotion_terms'])) ?></textarea>
        <p class="help-text">Jedno slovo nebo výraz na řádek. Body se přidají při výskytu alespoň dvou položek.</p>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Reklamní výzvy</label>
        <textarea name="contact_spam_calls_to_action" rows="5" required><?= $h(\App\Services\ContactSpamConfig::listText($spamConfig['calls_to_action'])) ?></textarea>
        <p class="help-text">Například „play now“ nebo „click here“.</p>
      </div>
      <div class="form-group">
        <label>Povolené domény ve zprávách</label>
        <textarea name="contact_spam_allowed_domains" rows="5" placeholder="mujweb.cz&#10;partner.cz"><?= $h(\App\Services\ContactSpamConfig::listText($spamConfig['allowed_domains'])) ?></textarea>
        <p class="help-text">Volitelné. Odkazy na tyto domény a jejich subdomény nepřidají body za odkaz ve zprávě.</p>
      </div>
    </div>
    <button type="button" class="btn btn-secondary" data-open-spam-test>Otestovat filtr bez odeslání</button>
  </div>

  <div class="card settings-panel" id="settings-panel-general" role="tabpanel" aria-labelledby="settings-tab-general" data-settings-panel="general">
    <div class="card-title">Zakladni informace</div>
    <div class="form-row">
      <div class="form-group">
        <label>Nazev webu *</label>
        <input type="text" name="name" value="<?= $h($site['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Domena *</label>
        <input type="url" name="domain" value="<?= $h($domainValue) ?>" placeholder="https://example.cz" required>
      </div>
    </div>
    <div class="form-group">
      <label for="company-registration-id">IČO</label>
      <input id="company-registration-id" type="text" name="company_registration_id" value="<?= $h($site['company_registration_id'] ?? '') ?>" inputmode="numeric" maxlength="8" pattern="[0-9]{8}" placeholder="12345678" aria-describedby="company-registration-id-help">
      <p class="help-text" id="company-registration-id-help" style="margin-top:6px">Osmimístné identifikační číslo firmy. Po uložení se zobrazí v kontaktních informacích.</p>
    </div>
    <div class="form-group">
      <label>Výchozí jazyk</label>
      <select name="default_language">
        <option value="cs" <?= ($site['default_language'] ?? 'cs') === 'cs' ? 'selected' : '' ?>>Cestina</option>
        <option value="en" <?= ($site['default_language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
        <option value="sk" <?= ($site['default_language'] ?? '') === 'sk' ? 'selected' : '' ?>>Slovencina</option>
      </select>
    </div>
  </div>

  <div class="card settings-panel" id="settings-panel-appearance" role="tabpanel" aria-labelledby="settings-tab-appearance" data-settings-panel="appearance">
    <div class="card-title">Obrazky pro sdileni na socialnich sitich</div>
    <p class="help-text" style="margin-bottom:16px">
      Stranka nebo clanek muze globalni obrazek prepsat vlastnim SEO obrazkem.
      Pouzij JPG nebo WebP, hlavni motiv a text nech s bezpecnym odsazenim od okraju.
    </p>
    <div class="form-row-3" style="align-items:stretch">
    <div class="form-group" style="min-width:0">
      <label>Facebook / Open Graph</label>
      <div class="help-text" style="min-height:54px;margin-bottom:8px">
        Nejlepsi je <strong>landscape 1,91:1</strong>, idealne <strong>1200 &times; 630 px</strong>.
        Pouziva se jako <code>og:image</code>.
      </div>
      <div class="brand-asset-picker">
        <input
          type="hidden"
          name="default_og_image_id"
          id="default-og-image-id"
          data-brand-value-mode="id"
          value="<?= $selectedDefaultOg ? (int)$selectedDefaultOg['id'] : '' ?>"
        >
        <div class="brand-asset-preview" data-brand-preview for="default-og-image-id">
          <?php if ($selectedDefaultOg): ?>
            <img src="<?= $h($mediaThumb($selectedDefaultOg)) ?>" alt="<?= $h($selectedDefaultOg['alt_text'] ?? $selectedDefaultOg['original_filename'] ?? '') ?>">
          <?php else: ?>
            <span>Neni vybrano</span>
          <?php endif; ?>
        </div>
        <div class="brand-asset-actions">
          <button type="button" class="btn btn-secondary" data-open-brand-media data-target="default-og-image-id">Vybrat z medii</button>
          <button type="button" class="btn btn-secondary" data-clear-brand-media data-target="default-og-image-id">Odebrat</button>
        </div>
        <div class="help-text brand-asset-path" data-brand-path for="default-og-image-id"><?= $selectedDefaultOg ? $h($mediaUrl($selectedDefaultOg)) : '' ?></div>
      </div>
    </div>
    <div class="form-group" style="min-width:0">
      <label>Instagram</label>
      <div class="help-text" style="min-height:54px;margin-bottom:8px">
        Pro prispevek zvol <strong>square 1:1</strong>, idealne <strong>1080 &times; 1080 px</strong>;
        pro vyssi plochu lze portrait 1080 &times; 1350 px. Instagram obrazek slouzi jako exportni podklad a webove OG tagy jej automaticky nepublikuji.
      </div>
      <div class="brand-asset-picker">
        <input
          type="hidden"
          name="default_instagram_image_id"
          id="default-instagram-image-id"
          data-brand-value-mode="id"
          value="<?= $selectedDefaultInstagram ? (int)$selectedDefaultInstagram['id'] : '' ?>"
        >
        <div class="brand-asset-preview" data-brand-preview for="default-instagram-image-id">
          <?php if ($selectedDefaultInstagram): ?>
            <img src="<?= $h($mediaThumb($selectedDefaultInstagram)) ?>" alt="<?= $h($selectedDefaultInstagram['alt_text'] ?? $selectedDefaultInstagram['original_filename'] ?? '') ?>">
          <?php else: ?>
            <span>Neni vybrano</span>
          <?php endif; ?>
        </div>
        <div class="brand-asset-actions">
          <button type="button" class="btn btn-secondary" data-open-brand-media data-target="default-instagram-image-id">Vybrat z medii</button>
          <button type="button" class="btn btn-secondary" data-clear-brand-media data-target="default-instagram-image-id">Odebrat</button>
        </div>
        <div class="help-text brand-asset-path" data-brand-path for="default-instagram-image-id"><?= $selectedDefaultInstagram ? $h($mediaUrl($selectedDefaultInstagram)) : '' ?></div>
      </div>
    </div>
    <div class="form-group" style="min-width:0">
      <label>Twitter / X</label>
      <div class="help-text" style="min-height:54px;margin-bottom:8px">
        Pro velkou kartu zvol <strong>landscape 2:1</strong>, idealne <strong>1200 &times; 600 px</strong>.
        Pouziva se jako <code>twitter:image</code>.
      </div>
      <div class="brand-asset-picker">
        <input
          type="hidden"
          name="default_twitter_image_id"
          id="default-twitter-image-id"
          data-brand-value-mode="id"
          value="<?= $selectedDefaultTwitter ? (int)$selectedDefaultTwitter['id'] : '' ?>"
        >
        <div class="brand-asset-preview" data-brand-preview for="default-twitter-image-id">
          <?php if ($selectedDefaultTwitter): ?>
            <img src="<?= $h($mediaThumb($selectedDefaultTwitter)) ?>" alt="<?= $h($selectedDefaultTwitter['alt_text'] ?? $selectedDefaultTwitter['original_filename'] ?? '') ?>">
          <?php else: ?>
            <span>Neni vybrano</span>
          <?php endif; ?>
        </div>
        <div class="brand-asset-actions">
          <button type="button" class="btn btn-secondary" data-open-brand-media data-target="default-twitter-image-id">Vybrat z medii</button>
          <button type="button" class="btn btn-secondary" data-clear-brand-media data-target="default-twitter-image-id">Odebrat</button>
        </div>
        <div class="help-text brand-asset-path" data-brand-path for="default-twitter-image-id"><?= $selectedDefaultTwitter ? $h($mediaUrl($selectedDefaultTwitter)) : '' ?></div>
      </div>
    </div>
    </div>
  </div>

  <div class="card settings-panel" id="settings-panel-communication" role="tabpanel" aria-labelledby="settings-tab-communication" data-settings-panel="communication">
    <div class="card-title">Kontaktni komunikace</div>
    <?php if (trim((string)($contact['address_1'] ?? '')) === '' && trim((string)($contact['address_2'] ?? '')) === ''): ?>
    <div class="alert alert-warning" style="margin-bottom:12px">
      Adresa neni vyplnena v databazi. Public footer a Local SEO z ni proto nic nevykresli.
    </div>
    <?php endif; ?>
    <div class="form-group">
      <label>E-mail pro příjem formulářů a obnovu přístupu</label>
      <input type="email" name="form_email" aria-label="E-mail pro příjem formulářů a obnovu přístupu" value="<?= $h($contact['form_email'] ?? '') ?>" placeholder="spravce@vase-domena.cz">
      <p class="help-text" style="margin-top:6px">
        Na tuto uloženou adresu chodí kontaktní zprávy a odkazy pro obnovu hesla účtů.
        Pro obnovu přístupu musí být pole vyplněné platnou adresou a uložené.
      </p>
    </div>
    <div class="form-group">
      <label for="form-email-cc">Kopie kontaktních zpráv (nepovinné)</label>
      <textarea id="form-email-cc" name="form_email_cc" rows="4" maxlength="60000" spellcheck="false" placeholder="kopie@vase-domena.cz&#10;dalsi@vase-domena.cz"><?= $h($contact['form_email_cc'] ?? '') ?></textarea>
      <p class="help-text">Zadejte jednu nebo více CC adres oddělených čárkou, mezerou nebo novým řádkem. Prázdné pole = bez kopie. Odkazy pro obnovu hesla se sem neposílají.</p>
    </div>
  </div>

  <div class="card settings-panel" data-settings-panel="appearance">
    <div class="card-title">Logo a favicony</div>
    <p class="help-text" style="margin-bottom:16px">
      Vyber logo a ikony primo z knihovny medii. Logo se pouzije ve verejne hlavicce webu,
      favicony se vlozi do <code>&lt;head&gt;</code>.
    </p>
    <div class="form-row">
      <div class="form-group">
        <label>Logo webu</label>
        <div class="help-text" style="margin-bottom:8px">Doporuceni: SVG nebo PNG/WebP, sirka 320-600 px, vyska 80-160 px. Pro retina displeje idealne 2x vetsi.</div>
        <div class="brand-asset-picker">
          <input type="hidden" name="logo_url" id="brand-logo-url" value="<?= $h($site['logo_url'] ?? '') ?>">
          <div class="brand-asset-preview" data-brand-preview for="brand-logo-url">
            <?php if (!empty($site['logo_url'])): ?><img src="<?= $h($publicMediaUrl($site['logo_url'] ?? '')) ?>" alt="Logo"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
          </div>
          <div class="brand-asset-actions">
            <button type="button" class="btn btn-secondary" data-open-brand-media data-target="brand-logo-url">Vybrat z medii</button>
            <button type="button" class="btn btn-secondary" data-clear-brand-media data-target="brand-logo-url">Odebrat</button>
          </div>
          <div class="help-text brand-asset-path" data-brand-path for="brand-logo-url"><?= $h($site['logo_url'] ?? '') ?></div>
        </div>
      </div>
      <div class="form-group">
        <label>Favicon ICO</label>
        <div class="help-text" style="margin-bottom:8px">Doporuceni: soubor .ico obsahujici 16x16, 32x32 a 48x48 px.</div>
        <div class="brand-asset-picker">
          <input type="hidden" name="favicon_ico_url" id="brand-favicon-ico-url" value="<?= $h($site['favicon_ico_url'] ?? '') ?>">
          <div class="brand-asset-preview brand-asset-preview--icon" data-brand-preview for="brand-favicon-ico-url">
            <?php if (!empty($site['favicon_ico_url'])): ?><img src="<?= $h($publicMediaUrl($site['favicon_ico_url'] ?? '')) ?>" alt="Favicon ICO"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
          </div>
          <div class="brand-asset-actions">
            <button type="button" class="btn btn-secondary" data-open-brand-media data-target="brand-favicon-ico-url">Vybrat z medii</button>
            <button type="button" class="btn btn-secondary" data-clear-brand-media data-target="brand-favicon-ico-url">Odebrat</button>
          </div>
          <div class="help-text brand-asset-path" data-brand-path for="brand-favicon-ico-url"><?= $h($site['favicon_ico_url'] ?? '') ?></div>
        </div>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Favicon PNG/SVG</label>
        <div class="help-text" style="margin-bottom:8px">Doporuceni: PNG 32x32 px nebo SVG s ctvercovym viewBoxem. Pro moderni prohlizece je vhodne i 192x192 px.</div>
        <div class="brand-asset-picker">
          <input type="hidden" name="favicon_png_url" id="brand-favicon-png-url" value="<?= $h($site['favicon_png_url'] ?? '') ?>">
          <div class="brand-asset-preview brand-asset-preview--icon" data-brand-preview for="brand-favicon-png-url">
            <?php if (!empty($site['favicon_png_url'])): ?><img src="<?= $h($publicMediaUrl($site['favicon_png_url'] ?? '')) ?>" alt="Favicon PNG/SVG"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
          </div>
          <div class="brand-asset-actions">
            <button type="button" class="btn btn-secondary" data-open-brand-media data-target="brand-favicon-png-url">Vybrat z medii</button>
            <button type="button" class="btn btn-secondary" data-clear-brand-media data-target="brand-favicon-png-url">Odebrat</button>
          </div>
          <div class="help-text brand-asset-path" data-brand-path for="brand-favicon-png-url"><?= $h($site['favicon_png_url'] ?? '') ?></div>
        </div>
      </div>
      <div class="form-group">
        <label>Apple touch icon</label>
        <div class="help-text" style="margin-bottom:8px">Doporuceni: PNG 180x180 px bez pruhlednosti, ctverec s dostatecnym okrajem kolem loga.</div>
        <div class="brand-asset-picker">
          <input type="hidden" name="apple_touch_icon_url" id="brand-apple-touch-icon-url" value="<?= $h($site['apple_touch_icon_url'] ?? '') ?>">
          <div class="brand-asset-preview brand-asset-preview--icon" data-brand-preview for="brand-apple-touch-icon-url">
            <?php if (!empty($site['apple_touch_icon_url'])): ?><img src="<?= $h($publicMediaUrl($site['apple_touch_icon_url'] ?? '')) ?>" alt="Apple touch icon"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
          </div>
          <div class="brand-asset-actions">
            <button type="button" class="btn btn-secondary" data-open-brand-media data-target="brand-apple-touch-icon-url">Vybrat z medii</button>
            <button type="button" class="btn btn-secondary" data-clear-brand-media data-target="brand-apple-touch-icon-url">Odebrat</button>
          </div>
          <div class="help-text brand-asset-path" data-brand-path for="brand-apple-touch-icon-url"><?= $h($site['apple_touch_icon_url'] ?? '') ?></div>
        </div>
      </div>
    </div>
    <p class="help-text">Pokud pole nechas prazdne, system zustane u vychoziho faviconu / sablony. Nova media nahrajes v sekci <a href="/admin/media">Media</a>.</p>
  </div>

  <div class="card settings-panel" data-settings-panel="appearance">
    <div class="card-title">Výchozí hero video</div>
    <p class="help-text" style="margin-bottom:16px">
      Toto video se pouzije jako fallback pro vsechny stranky, pokud konkretni stranka nema vlastni hero video.
      Poster obrazek se zobrazi okamzite pri nacitani videa, aby uzivatel nevidel cernou plochu.
    </p>
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="video_autoplay_enabled" value="1" <?= (int)($site['video_autoplay_enabled'] ?? 1) === 1 ? 'checked' : '' ?> style="width:auto">
        Prehravat videa automaticky po nacteni stranky
      </label>
      <p class="help-text" style="margin-top:6px">
        Tyka se hero videa na hlavni strance a videa Tomase Sincla na strance O nas.
        Kdyz je vypnuto, na videu zustane tlacitko pro prehrani a prehravac se nacte az po kliknuti.
        <strong>Zapnute automaticke prehravani stranku vyrazne zteziuje</strong> - merene 495 kB bez videa
        proti 1644 kB s videem, a necinna stranka necha prehravac bufferovat dal. Na pomalem mobilnim
        pripojeni to zpomali nacteni.
      </p>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Hero video pro desktop (nad 1050 px)</label>
        <div class="help-text" style="margin-bottom:8px"><strong>Kazda sirka okna ma vlastni video a nic se nededi: prazdne pole = na dane sirce zadne video.</strong> Vlozte odkaz z Vimeo nebo YouTube, nebo kliknete na Media a vyberte video z knihovny. Vimeo: z Vimeo nebo YouTube, napr. <code>https://vimeo.com/123456789</code> nebo <code>https://www.youtube.com/watch?v=...</code>.</div>
        <div class="hero-video-field" style="display:flex;gap:8px;align-items:center">
          <input type="text" name="default_hero_video_url" id="default-hero-video-url" value="<?= $h($site['default_hero_video_url'] ?? '') ?>" placeholder="Vimeo / YouTube URL nebo video z Médií (/media/...)" style="flex:1" data-hero-video-input>
          <button type="button" class="btn btn-sm btn-secondary" data-hero-video-media data-target-id="default-hero-video-url">Média</button>
          <button type="button" class="btn btn-sm btn-secondary" data-hero-video-clear data-target-id="default-hero-video-url">Odebrat</button>
        </div>
        <label style="margin-top:12px">Hero video pro tablet (701-1050 px)</label>
        <div class="help-text" style="margin-bottom:8px">Prehraje se pri sirce okna 701-1050 px. Hero ho jiz nepouziva.</div>
        <div class="hero-video-field" style="display:flex;gap:8px;align-items:center">
          <input type="text" name="default_hero_video_url_tablet" id="default-hero-video-url-tablet" value="<?= $h($site['default_hero_video_url_tablet'] ?? '') ?>" placeholder="Vimeo / YouTube URL nebo video z Médií (/media/...)" style="flex:1" data-hero-video-input>
          <button type="button" class="btn btn-sm btn-secondary" data-hero-video-media data-target-id="default-hero-video-url-tablet">Média</button>
          <button type="button" class="btn btn-sm btn-secondary" data-hero-video-clear data-target-id="default-hero-video-url-tablet">Odebrat</button>
        </div>
        <label style="margin-top:12px">Hero video pro mobil (do 700 px)</label>
        <div class="help-text" style="margin-bottom:8px">Hero ho jiz nepouziva.</div>
        <div class="hero-video-field" style="display:flex;gap:8px;align-items:center">
          <input type="text" name="default_hero_video_url_mobile" id="default-hero-video-url-mobile" value="<?= $h($site['default_hero_video_url_mobile'] ?? '') ?>" placeholder="Vimeo / YouTube URL nebo video z Médií (/media/...)" style="flex:1" data-hero-video-input>
          <button type="button" class="btn btn-sm btn-secondary" data-hero-video-media data-target-id="default-hero-video-url-mobile">Média</button>
          <button type="button" class="btn btn-sm btn-secondary" data-hero-video-clear data-target-id="default-hero-video-url-mobile">Odebrat</button>
        </div>
      </div>
      <div class="form-group">
        <label>Placeholder / poster obrazek</label>
        <div class="help-text" style="margin-bottom:8px">Doporuceni: WebP/JPG 1920x1080 px, do 300-600 kB, tmavsi varianta vhodna pod text v hero sekci.</div>
        <div class="brand-asset-picker">
          <input type="hidden" name="default_hero_poster_url" id="default-hero-poster-url" value="<?= $h($site['default_hero_poster_url'] ?? '') ?>">
          <div class="brand-asset-preview" data-brand-preview for="default-hero-poster-url">
            <?php if (!empty($site['default_hero_poster_url'])): ?><img src="<?= $h($publicMediaUrl($site['default_hero_poster_url'] ?? '')) ?>" alt="Hero poster"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
          </div>
          <div class="brand-asset-actions">
            <button type="button" class="btn btn-secondary" data-open-brand-media data-target="default-hero-poster-url">Vybrat z medii</button>
            <button type="button" class="btn btn-secondary" data-clear-brand-media data-target="default-hero-poster-url">Odebrat</button>
          </div>
          <div class="help-text brand-asset-path" data-brand-path for="default-hero-poster-url"><?= $h($site['default_hero_poster_url'] ?? '') ?></div>
        </div>
      </div>
    </div>
    <div class="form-group">
      <label>Intenzita tmaveho prekryvu videa (0-1)</label>
      <div class="help-text" style="margin-bottom:8px">Globální nastavení pro vsechna hero videa a video playery s overlayem. <code>0</code> = bez ztmaveni, <code>1</code> = maximalni tmavy prekryv.</div>
      <div class="form-row" style="align-items:end">
        <div class="form-group" style="flex:1">
          <input type="range" id="video-overlay-opacity-range" min="0" max="1" step="0.01" value="<?= $h($videoOverlayValue) ?>">
        </div>
        <div class="form-group" style="max-width:140px">
          <input type="number" name="video_overlay_opacity" id="video-overlay-opacity-input" min="0" max="1" step="0.01" value="<?= $h($videoOverlayValue) ?>" placeholder="0.62">
        </div>
      </div>
      <div class="help-text">Aktualni hodnota: <strong id="video-overlay-opacity-value"><?= $h($videoOverlayValue) ?></strong></div>
    </div>
    <div class="form-group" style="margin-top:20px">
      <label>Kvalita komprese medií (1-100)</label>
      <div class="help-text" style="margin-bottom:8px">Vyssi hodnota = hezci obraz, ale vetsi soubory. Doporučení je 85-90.</div>
      <div class="form-row" style="align-items:end">
        <div class="form-group" style="flex:1">
          <input type="range" id="media-image-quality-range" min="1" max="100" step="1" value="<?= $h((string)$mediaImageQuality) ?>">
        </div>
        <div class="form-group" style="max-width:140px">
          <input type="number" name="media_image_quality" id="media-image-quality-input" min="1" max="100" step="1" value="<?= $h((string)$mediaImageQuality) ?>" placeholder="90">
        </div>
      </div>
      <div class="help-text">Aktualni hodnota: <strong id="media-image-quality-value"><?= $h((string)$mediaImageQuality) ?></strong></div>
    </div>
  </div>

  <div class="card settings-panel" id="settings-panel-measurement" role="tabpanel" aria-labelledby="settings-tab-measurement" data-settings-panel="measurement">
    <div class="card-title">Cookies, consent a analytics</div>
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="cookie_consent_enabled" value="1" <?= (int)($site['cookie_consent_enabled'] ?? 1) === 1 ? 'checked' : '' ?> style="width:auto">
        Zobrazovat consent listu
      </label>
      <p class="help-text" style="margin-top:6px">
        Toto je hlavni prepinac cookie listy. Kdyz je zapnuty, navstevnik vidi consent listu a skripty podle kategorii
        se spusti az po souhlasu. Kdyz je vypnuty, lista se nezobrazi a skripty se nacitaji bez consent kroku.
      </p>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="cookie_consent_analytics_enabled" value="1" <?= (int)($site['cookie_consent_analytics_enabled'] ?? 1) === 1 ? 'checked' : '' ?> style="width:auto">
          Povolit kategorii Analyticke cookies
        </label>
        <p class="help-text" style="margin-top:6px">
          Pouzij pro Google Analytics, Clarity, Hotjar a dalsi merici nastroje.
        </p>
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="cookie_consent_marketing_enabled" value="1" <?= (int)($site['cookie_consent_marketing_enabled'] ?? 1) === 1 ? 'checked' : '' ?> style="width:auto">
          Povolit kategorii Marketingove cookies
        </label>
        <p class="help-text" style="margin-top:6px">
          Pouzij pro remarketing, reklamni pixely a dalsi marketingove skripty.
        </p>
      </div>
    </div>
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="visit_analytics_enabled" value="1" <?= (int)($site['visit_analytics_enabled'] ?? 1) === 1 ? 'checked' : '' ?> style="width:auto">
        Vlastni mereni navstevnosti (zaloha k Google Analytics)
      </label>
      <p class="help-text" style="margin-top:6px">
        Nezavisle na GA4/GTM. Bez souhlasu se uklada jen serverovy zaznam s denne rotujicim anonymnim otiskem (bez IP adresy a bez cookie);
        cookie <code>cms_vid</code> vznika az po souhlasu s analytickymi cookies. Prehled je v <a href="/admin/analytics">Navstevnost</a>.
      </p>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Google Analytics ID</label>
        <input type="text" name="google_analytics_id" value="<?= $h($site['google_analytics_id'] ?? '') ?>" placeholder="G-XXXXXXXXXX">
        <p class="help-text">Measurement ID z GA4 → Správce → Datové toky. Spusti se s Analytics consent.</p>
      </div>
      <div class="form-group">
        <label>Google Tag Manager ID</label>
        <input type="text" name="google_tag_manager_id" value="<?= $h($site['google_tag_manager_id'] ?? '') ?>" placeholder="GTM-XXXXXXX">
        <p class="help-text">ID kontejneru ze Správce značek (vlevo nahoře). Snippet se na web nacte az s Marketing consent.</p>
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="enable_gtm_tracking_logs" value="1" <?= (int)($site['enable_gtm_tracking_logs'] ?? 0) === 1 ? 'checked' : '' ?> style="width:auto">
          Zobrazovat GTM tracking logy v DevTools konzoli
        </label>
        <p class="help-text" style="margin-top:6px">
          Zapne vypis [GA4 Tracking] zpráv do DevTools konzole pro debugování. Užitečné při testování GTM konfiguraci.
        </p>
      </div>
    </div>
  </div>

  <div class="card settings-panel settings-collapsible is-collapsed" data-settings-panel="measurement" data-settings-collapsible>
    <button type="button" class="card-title settings-collapsible__toggle" aria-expanded="false" data-settings-collapse-toggle>Technicky nezbytne skripty <span aria-hidden="true">⌄</span></button>
    <p class="help-text" style="margin-bottom:12px">
      Sem patri pouze skripty nutne pro fungovani webu. Analytiku, pixely, remarketing,
      heatmapy nebo podobne nastroje vkladej do sekci nize podle kategorie souhlasu.
    </p>
    <div class="form-group">
      <label>Skript do &lt;head&gt;</label>
      <textarea class="code" name="head_scripts" rows="5"><?= $h($site['head_scripts'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label>Skript pred &lt;/body&gt;</label>
      <textarea class="code" name="body_scripts" rows="5"><?= $h($site['body_scripts'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="card settings-panel settings-collapsible is-collapsed" data-settings-panel="measurement" data-settings-collapsible>
    <button type="button" class="card-title settings-collapsible__toggle" aria-expanded="false" data-settings-collapse-toggle>Analytické skripty <span aria-hidden="true">⌄</span></button>
    <p class="help-text" style="margin-bottom:12px">
      Google Analytics, Hotjar, Clarity nebo podobne merici nastroje. Pri zapnute consent liste
      se spusti pouze po povoleni kategorie Analyticke cookies.
    </p>
    <div class="form-group">
      <label>Analytika do &lt;head&gt;</label>
      <textarea class="code" name="analytics_head_scripts" rows="5"><?= $h($site['analytics_head_scripts'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label>Analytika pred &lt;/body&gt;</label>
      <textarea class="code" name="analytics_body_scripts" rows="5"><?= $h($site['analytics_body_scripts'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="card settings-panel settings-collapsible is-collapsed" data-settings-panel="measurement" data-settings-collapsible>
    <button type="button" class="card-title settings-collapsible__toggle" aria-expanded="false" data-settings-collapse-toggle>Marketingové skripty <span aria-hidden="true">⌄</span></button>
    <p class="help-text" style="margin-bottom:12px">
      Meta Pixel, Google Ads remarketing, TikTok Pixel a dalsi reklamni nebo remarketingove nastroje.
      Pri zapnute consent liste se spusti pouze po povoleni kategorie Marketingove cookies.
    </p>
    <div class="form-group">
      <label>Marketing do &lt;head&gt;</label>
      <textarea class="code" name="marketing_head_scripts" rows="5"><?= $h($site['marketing_head_scripts'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label>Marketing pred &lt;/body&gt;</label>
      <textarea class="code" name="marketing_body_scripts" rows="5"><?= $h($site['marketing_body_scripts'] ?? '') ?></textarea>
    </div>
  </div>

  <?php
    $trackingEventsDefault = [
      'lead_form_submit' => true, 'lead_thank_you_page' => true,
      'phone_click' => true, 'email_click' => true, 'cta_click' => true,
      'social_click' => true, 'gallery_open' => true, 'video_play' => true,
      'pricing_click' => true, 'scroll_90' => true,
    ];
    $trackingEventsRaw = trim((string)($site['tracking_events_json'] ?? ''));
    $trackingEvents = $trackingEventsDefault;
    if ($trackingEventsRaw !== '') {
      $dec = json_decode($trackingEventsRaw, true);
      if (is_array($dec)) $trackingEvents = array_merge($trackingEventsDefault, $dec);
    }
    $trackingEventLabels = [
      'lead_form_submit'   => 'Odeslani kontaktniho formulare',
      'lead_thank_you_page'=> 'Navsteva stranky /dekujeme (hlavni konverze)',
      'phone_click'        => 'Klik na telefonni cislo',
      'email_click'        => 'Klik na e-mailovou adresu',
      'cta_click'          => 'Klik na CTA tlacitko (data-track-event)',
      'social_click'       => 'Klik na socialni site (Instagram, FB, YouTube...)',
      'gallery_open'       => 'Otevreni polozky galerie / portfolia',
      'video_play'         => 'Prehrani videa (prvni spusteni)',
      'pricing_click'      => 'Klik na cenovou kartu',
      'scroll_90'          => 'Scrollovani 90 % stranky',
    ];
  ?>
  <div class="card settings-panel" data-settings-panel="measurement">
    <div class="card-title">Tracking / Konverze</div>
    <p class="help-text" style="margin-bottom:16px">
      Centralni nastaveni merenı konverzı a analytickych eventu. Tracking se spustı pouze
      kdyz je zapnuty a (pokud je vyzadovan souhlas) uzivatel odsouhlasil Analytics cookies.
    </p>

    <div class="form-group" style="display:flex;gap:32px;flex-wrap:wrap;margin-bottom:16px">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600">
        <input type="checkbox" name="tracking_enabled" value="1" <?= !empty($site['tracking_enabled']) || !isset($site['tracking_enabled']) ? 'checked' : '' ?>>
        Tracking povolen
      </label>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600">
        <input type="checkbox" name="tracking_require_consent" value="1" <?= !isset($site['tracking_require_consent']) || !empty($site['tracking_require_consent']) ? 'checked' : '' ?>>
        Vyzadovat souhlas uzivatele (GDPR)
      </label>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="tracking_debug" value="1" <?= !empty($site['tracking_debug']) ? 'checked' : '' ?>>
        Debug mod (loguje do konzole)
      </label>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Microsoft Clarity ID</label>
        <input type="text" name="clarity_id" value="<?= $h($site['clarity_id'] ?? '') ?>" placeholder="xxxxxxxxxx" style="font-family:monospace">
        <p class="help-text">Najdes na clarity.microsoft.com → Nastavení projektu. Spusti se s Analytics consent.</p>
      </div>
      <div class="form-group">
        <label>Meta Pixel ID</label>
        <input type="text" name="meta_pixel_id" value="<?= $h($site['meta_pixel_id'] ?? '') ?>" placeholder="000000000000000" style="font-family:monospace">
        <p class="help-text">Najdes v Meta Events Manager → Datove zdroje. Spusti se s Marketing consent.</p>
      </div>
    </div>

    <div class="form-group" style="margin-top:8px">
      <label style="font-weight:600;display:block;margin-bottom:10px">Merene eventy</label>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:8px">
        <?php foreach ($trackingEventLabels as $key => $label): ?>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 10px;border:1px solid var(--c-border);border-radius:8px;background:var(--c-bg)">
          <input type="checkbox" name="tracking_event_<?= $h($key) ?>" value="1" <?= !empty($trackingEvents[$key]) ? 'checked' : '' ?>>
          <span>
            <span style="display:block;font-size:13px;font-weight:500"><?= $h($label) ?></span>
            <span style="display:block;font-size:11px;color:var(--c-muted);font-family:monospace"><?= $h($key) ?></span>
          </span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card settings-panel settings-collapsible is-collapsed" data-settings-panel="general" data-settings-collapsible>
    <button type="button" class="card-title settings-collapsible__toggle" aria-expanded="false" data-settings-collapse-toggle>Pokročilé: layout webu <span aria-hidden="true">⌄</span></button>
    <p class="help-text" style="margin-bottom:16px">
      HTML layout obali kazdou stranku. Dostupne placeholdery:<br>
      <code>{page_content}</code> - obsah stranky &nbsp;|&nbsp;
      <code>{site_name}</code> - nazev webu &nbsp;|&nbsp;
      <code>{nav}</code> - navigace (viz pole nav nize). Ponech prazdne, pokud kazda sablona ma vlastni HTML.
    </p>
    <div class="form-group">
      <label>Layout HTML</label>
      <textarea class="code" name="layout_html" rows="14"><?= $h($site['layout_html'] ?? '') ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Layout CSS (spolecne styly - nav, paticka, zaklad)</label>
        <textarea class="code" name="layout_css" rows="10"><?= $h($site['layout_css'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>Layout JS (spolecny skript - scroll, nav efekty)</label>
        <textarea class="code" name="layout_js" rows="10"><?= $h($site['layout_js'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Nav - homepage (hash anchory pro One Page scroll)</label>
        <textarea class="code" name="layout_nav_home" rows="8"><?= $h($site['layout_nav_home'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>Nav - subpages (prime URL na jednotlive stranky)</label>
        <textarea class="code" name="layout_nav_sub" rows="8"><?= $h($site['layout_nav_sub'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <div class="settings-savebar">
    <span class="help-text">Uložení platí pro všechny záložky.</span>
    <button type="submit" class="btn btn-primary">Uložit nastavení</button>
  </div>
</form>

<div class="settings-modal<?= is_array($spamTestResult ?? null) ? ' is-open' : '' ?>" id="antispam-test" aria-hidden="<?= is_array($spamTestResult ?? null) ? 'false' : 'true' ?>" data-spam-test-modal>
 <div class="settings-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="antispam-test-title" tabindex="-1">
  <div class="settings-modal__head">
    <div class="card-title" id="antispam-test-title">Test antispamu bez odeslání</div>
    <button type="button" class="btn btn-secondary" data-close-spam-test>Zavřít</button>
  </div>
  <p class="help-text" style="margin-bottom:14px">Používá naposledy uložené nastavení. Test nic nezapíše do archivu a neodešle žádný e-mail.</p>
  <?php if (is_array($spamTestResult ?? null)): ?>
    <div class="alert <?= !empty($spamTestResult['isSpam']) ? 'alert-danger' : 'alert-success' ?>">
      Výsledek: <strong><?= !empty($spamTestResult['isSpam']) ? 'SPAM' : 'V pořádku' ?></strong>, skóre <?= $h($spamTestResult['score'] ?? 0) ?>.
      Důvody: <?= $h(($spamTestResult['reasons'] ?? []) === [] ? 'žádné' : implode(', ', $spamTestResult['reasons'])) ?>
    </div>
  <?php endif; ?>
  <form method="POST" action="/admin/settings/spam-test">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <div class="form-row">
      <div class="form-group"><label>Testovací jméno</label><input type="text" name="spam_test_name" required value="Test návštěvník"></div>
      <div class="form-group"><label>Testovací kontakt</label><input type="text" name="spam_test_contact" required value="test@example.com"></div>
    </div>
    <div class="form-group"><label>Testovací zpráva</label><textarea name="spam_test_message" rows="5" required placeholder="Vložte text pro výpočet skóre"></textarea></div>
    <button type="submit" class="btn btn-secondary">Vyhodnotit bez odeslání</button>
  </form>
 </div>
</div>

<details class="card settings-panel settings-tool-card" id="settings-panel-dev" role="tabpanel" aria-labelledby="settings-tab-dev" data-settings-panel="dev">
  <summary class="card-title">REST API <span aria-hidden="true">⌄</span></summary>
  <?php if ($apiKey): ?>
  <div class="form-group">
    <label>API klic</label>
    <div style="display:flex;gap:8px;align-items:center">
      <input type="text" id="api-key-field" value="<?= $h($apiKey) ?>" readonly style="font-family:monospace;flex:1;background:var(--c-bg);color:var(--c-muted)">
      <button type="button" class="btn btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('api-key-field').value).then(()=>{this.textContent='Zkopirovano!';setTimeout(()=>this.textContent='Kopirovat',1500)})">Kopirovat</button>
    </div>
    <p class="help-text" style="margin-top:6px">Pouzij jako <code>X-Api-Key: {klic}</code> nebo <code>Authorization: Bearer {klic}</code>.</p>
  </div>
  <div class="form-group" style="margin-top:4px">
    <label>Dostupne endpointy</label>
    <ul style="margin:4px 0 0 18px;font-size:13px;color:var(--c-muted);line-height:1.8">
      <li><code>GET    /api/v1/articles</code> - seznam vsech clanku</li>
      <li><code>POST   /api/v1/articles</code> - vytvorit novy clanek (JSON: title*, slug, excerpt, content, status, featured_image_id, gallery_ids[], meta_title, meta_description)</li>
      <li><code>GET    /api/v1/articles/{slug}</code> - detail clanku</li>
      <li><code>PUT    /api/v1/articles/{slug}</code> - aktualizovat clanek (JSON: stejna tela jako POST, vsechna pole volitelna)</li>
      <li><code>DELETE /api/v1/articles/{slug}</code> - smazat clanek</li>
      <li style="margin-top:6px"><code>GET    /api/v1/portfolio</code> - seznam portfolia</li>
      <li><code>POST   /api/v1/portfolio</code> - vytvorit polozku (JSON: title*, slug, description, video_url, status, sort_order, media_ids[], meta_title, meta_description)</li>
      <li><code>GET    /api/v1/portfolio/{slug}</code> - detail polozky</li>
      <li><code>PUT    /api/v1/portfolio/{slug}</code> - aktualizovat polozku (JSON: stejna tela jako POST, vsechna pole volitelna)</li>
      <li><code>DELETE /api/v1/portfolio/{slug}</code> - smazat polozku</li>
      <li style="margin-top:6px"><code>POST   /api/v1/media</code> - nahrat medium (multipart/form-data: file/files[], alt_text*, title)</li>
      <li><code>DELETE /api/v1/media/{id}</code> - smazat medium</li>
    </ul>
    <p class="help-text" style="margin-top:8px">* povinna pole &nbsp;|&nbsp; status: <code>draft</code> (vychozi) nebo <code>published</code></p>
  </div>
  <?php else: ?>
  <p class="help-text">API klic bude automaticky vygenerovan pri prvnim volani API endpointu.</p>
  <?php endif; ?>
</details>
</div>

<style>
.settings-workspace{--settings-accent:var(--c-primary);position:relative}
.settings-tabs{position:sticky;top:0;z-index:20;display:flex;gap:6px;margin:0 0 18px;padding:8px;border:1px solid var(--c-border);border-radius:12px;background:color-mix(in srgb,var(--c-surface) 94%,transparent);box-shadow:0 8px 24px rgba(15,23,42,.06);overflow-x:auto;scrollbar-width:thin}
.settings-tab{flex:0 0 auto;border:0;border-radius:8px;background:transparent;color:var(--c-muted);cursor:pointer;font:inherit;font-size:13px;font-weight:700;padding:10px 14px;white-space:nowrap}
.settings-tab:hover{background:var(--c-bg);color:var(--c-text)}
.settings-tab:focus-visible{outline:3px solid color-mix(in srgb,var(--settings-accent) 28%,transparent);outline-offset:1px}
.settings-tab[aria-selected="true"]{background:var(--settings-accent);color:#fff;box-shadow:0 5px 14px color-mix(in srgb,var(--settings-accent) 24%,transparent)}
.settings-workspace.is-enhanced .settings-panel[hidden]{display:none}
.settings-workspace.is-enhanced .settings-panel{animation:settings-panel-in .16s ease-out}
.settings-collapsible__toggle{display:flex;width:100%;align-items:center;justify-content:space-between;border:0;background:transparent;color:inherit;cursor:pointer;font:inherit;text-align:left}
.settings-collapsible__toggle:focus-visible{outline:3px solid color-mix(in srgb,var(--settings-accent) 28%,transparent);outline-offset:3px;border-radius:4px}
.settings-workspace.is-enhanced .settings-collapsible.is-collapsed>:not(.settings-collapsible__toggle){display:none}
.settings-collapsible:not(.is-collapsed) .settings-collapsible__toggle span{transform:rotate(180deg)}
.settings-collapsible__toggle span{transition:transform .16s ease}
.settings-savebar{position:sticky;bottom:12px;z-index:18;display:flex;align-items:center;justify-content:flex-end;gap:14px;margin:18px 0;padding:12px 14px;border:1px solid var(--c-border);border-radius:12px;background:color-mix(in srgb,var(--c-surface) 94%,transparent);box-shadow:0 12px 34px rgba(15,23,42,.14);backdrop-filter:blur(10px)}
.settings-tool-card>summary{display:flex;align-items:center;justify-content:space-between;cursor:pointer;list-style:none;margin:0}
.settings-tool-card>summary::-webkit-details-marker{display:none}
.settings-tool-card[open]>summary{margin-bottom:18px}
.settings-modal{position:fixed;inset:0;z-index:1300;display:none;align-items:flex-start;justify-content:center;padding:7vh 24px;background:rgba(15,23,42,.68)}
.settings-modal.is-open{display:flex}
.settings-modal__dialog{width:min(760px,100%);max-height:86vh;overflow:auto;padding:20px;border:1px solid var(--c-border);border-radius:14px;background:var(--c-surface);box-shadow:0 28px 80px rgba(15,23,42,.32)}
.settings-modal__head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:12px}
@keyframes settings-panel-in{from{opacity:.65;transform:translateY(3px)}to{opacity:1;transform:none}}
@media(max-width:768px){.settings-tabs{top:0;margin-inline:-4px;padding:6px}.settings-tab{padding:9px 11px}.settings-savebar{bottom:8px;justify-content:space-between}.settings-savebar .help-text{max-width:150px}.settings-modal{padding:16px}.settings-modal__dialog{max-height:calc(100vh - 32px);padding:16px}}
.brand-asset-picker{display:grid;gap:10px}
.brand-asset-preview{min-height:76px;border:1px dashed var(--c-border);border-radius:10px;background:var(--c-bg);display:flex;align-items:center;justify-content:center;padding:12px;color:var(--c-muted);font-size:13px}
.brand-asset-preview img{max-width:100%;max-height:58px;object-fit:contain;display:block}
.brand-asset-preview--icon{width:96px;min-height:76px}
.brand-asset-preview--icon img{max-width:48px;max-height:48px}
.brand-asset-actions{display:flex;gap:8px;flex-wrap:wrap}
.brand-asset-path{min-height:17px;word-break:break-all}
.brand-media-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:flex-start;justify-content:center;padding:7vh 24px;background:rgba(15,23,42,.62)}
.brand-media-modal.is-open{display:flex}
.brand-media-dialog{width:min(980px,100%);max-height:86vh;overflow:auto;background:var(--c-surface);border-radius:14px;border:1px solid var(--c-border);box-shadow:0 24px 70px rgba(15,23,42,.25);padding:18px}
.brand-media-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:14px}
.brand-media-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
.brand-media-item{border:1px solid var(--c-border);border-radius:10px;background:var(--c-bg);overflow:hidden;text-align:left;cursor:pointer;padding:0}
.brand-media-item:hover{box-shadow:0 10px 24px rgba(15,23,42,.12);border-color:var(--c-primary)}
.brand-media-thumb{height:104px;display:flex;align-items:center;justify-content:center;background:#fff}
.brand-media-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.brand-media-file{font-size:24px;color:var(--c-muted)}
.brand-media-name{padding:8px;font-size:11px;color:var(--c-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
</style>
<script>
(function(){
  const workspace = document.querySelector('[data-settings-workspace]');
  if (workspace) {
    const tabs = Array.from(workspace.querySelectorAll('[data-settings-tab]'));
    const panels = Array.from(workspace.querySelectorAll('[data-settings-panel]'));
    const validKeys = tabs.map(tab => tab.dataset.settingsTab);
    const legacyHashes = {antispam:'antispam', 'antispam-test':'antispam', 'settings-antispam':'antispam'};

    function keyFromHash() {
      const hash = window.location.hash.slice(1);
      if (legacyHashes[hash]) return legacyHashes[hash];
      if (hash.startsWith('settings-')) return hash.slice(9);
      return '';
    }

    function activate(key, options) {
      const selected = validKeys.includes(key) ? key : 'general';
      tabs.forEach(tab => {
        const active = tab.dataset.settingsTab === selected;
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
        tab.tabIndex = active ? 0 : -1;
      });
      panels.forEach(panel => { panel.hidden = panel.dataset.settingsPanel !== selected; });
      if (options && options.focus) {
        const tab = tabs.find(item => item.dataset.settingsTab === selected);
        if (tab) tab.focus();
      }
      if (options && options.updateHash) {
        history.replaceState(null, '', '#settings-' + selected);
      }
    }

    workspace.classList.add('is-enhanced');
    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => activate(tab.dataset.settingsTab, {updateHash:true}));
      tab.addEventListener('keydown', event => {
        let next = index;
        if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
        else if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = tabs.length - 1;
        else return;
        event.preventDefault();
        activate(tabs[next].dataset.settingsTab, {focus:true,updateHash:true});
      });
    });
    workspace.querySelectorAll('[data-settings-collapsible]').forEach(section => {
      const toggle = section.querySelector('[data-settings-collapse-toggle]');
      if (!toggle) return;
      toggle.addEventListener('click', () => {
        const collapsed = section.classList.toggle('is-collapsed');
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      });
    });
    const form = workspace.querySelector('[data-settings-form]');
    if (form) {
      form.addEventListener('invalid', event => {
        const panel = event.target.closest('[data-settings-panel]');
        if (panel) activate(panel.dataset.settingsPanel, {updateHash:true});
        const collapsed = event.target.closest('[data-settings-collapsible]');
        if (collapsed) {
          collapsed.classList.remove('is-collapsed');
          const toggle = collapsed.querySelector('[data-settings-collapse-toggle]');
          if (toggle) toggle.setAttribute('aria-expanded', 'true');
        }
      }, true);
    }
    window.addEventListener('hashchange', () => activate(keyFromHash() || 'general'));
    activate(keyFromHash() || 'general');
  }

  const spamModal = document.querySelector('[data-spam-test-modal]');
  if (spamModal) {
    const dialog = spamModal.querySelector('.settings-modal__dialog');
    let returnFocus = null;
    function openSpamModal(trigger) {
      returnFocus = trigger || document.activeElement;
      spamModal.classList.add('is-open');
      spamModal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      if (dialog) dialog.focus();
    }
    function closeSpamModal() {
      spamModal.classList.remove('is-open');
      spamModal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (returnFocus && typeof returnFocus.focus === 'function') returnFocus.focus();
    }
    document.querySelectorAll('[data-open-spam-test]').forEach(button => button.addEventListener('click', () => openSpamModal(button)));
    spamModal.querySelectorAll('[data-close-spam-test]').forEach(button => button.addEventListener('click', closeSpamModal));
    spamModal.addEventListener('click', event => { if (event.target === spamModal) closeSpamModal(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && spamModal.classList.contains('is-open')) closeSpamModal(); });
    if (spamModal.classList.contains('is-open')) {
      document.body.style.overflow = 'hidden';
      if (dialog) dialog.focus();
    }
  }

  function bindRangeNumber(rangeId, inputId, valueId, fallback, decimals){
    var range = document.getElementById(rangeId);
    var input = document.getElementById(inputId);
    var value = document.getElementById(valueId);
    if(!range || !input || !value) return;
    function normalize(v){
      var n = parseFloat(String(v).replace(',', '.'));
      if (!Number.isFinite(n)) n = fallback;
      if (range.min !== '') n = Math.max(parseFloat(range.min), n);
      if (range.max !== '') n = Math.min(parseFloat(range.max), n);
      return n.toFixed(decimals);
    }
    function syncFrom(source, target){
      var normalized = normalize(source.value);
      source.value = normalized;
      target.value = normalized;
      value.textContent = normalized;
    }
    range.addEventListener('input', function(){ syncFrom(range, input); });
    input.addEventListener('input', function(){ syncFrom(input, range); });
    input.addEventListener('change', function(){ syncFrom(input, range); });
    syncFrom(input, range);
  }

  bindRangeNumber('video-overlay-opacity-range', 'video-overlay-opacity-input', 'video-overlay-opacity-value', 0.62, 2);
  bindRangeNumber('media-image-quality-range', 'media-image-quality-input', 'media-image-quality-value', 90, 0);
})();
</script>

<div class="brand-media-modal" id="brand-media-modal" aria-hidden="true">
  <div class="brand-media-dialog" role="dialog" aria-modal="true" aria-label="Vyber media">
    <div class="brand-media-head">
      <div>
        <div class="card-title" style="margin:0">Vyber z medii</div>
        <div class="help-text">Kliknutim vyberes soubor pro aktualni pole.</div>
      </div>
      <button type="button" class="btn btn-secondary" data-brand-media-close>Zavrit</button>
    </div>
    <?php if (empty($media)): ?>
      <p class="help-text">Zatím nejsou nahrána žádná media. Nejdrive je nahraj v sekci <a href="/admin/media">Media</a>.</p>
    <?php else: ?>
      <div class="brand-media-list">
        <?php foreach ($media as $m): ?>
          <?php
            $url = $mediaUrl($m);
            $thumb = $mediaThumb($m);
            $mime = (string)($m['mime_type'] ?? '');
            $isImage = str_starts_with($mime, 'image/');
          ?>
          <button type="button" class="brand-media-item" data-brand-media-item data-id="<?= (int)$m['id'] ?>" data-url="<?= $h($url) ?>" data-mime="<?= $h($mime) ?>" title="<?= $h($m['original_filename'] ?? '') ?>">
            <div class="brand-media-thumb">
              <?php if ($isImage): ?>
                <img src="<?= $h($thumb) ?>" alt="<?= $h($m['alt_text'] ?? $m['original_filename'] ?? '') ?>" loading="lazy">
              <?php else: ?>
                <span class="brand-media-file">FILE</span>
              <?php endif; ?>
            </div>
            <div class="brand-media-name"><?= $h($m['original_filename'] ?? $url) ?></div>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function(){
  const modal = document.getElementById('brand-media-modal');
  if (!modal) return;
  let activeTarget = '';

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function previewFor(targetId) {
    return document.querySelector('[data-brand-preview][for="' + targetId + '"]');
  }

  function pathFor(targetId) {
    return document.querySelector('[data-brand-path][for="' + targetId + '"]');
  }

  // Stored paths look like /storage/media/..., but the public route serves /media/...
  // Mirrors the $publicMediaUrl helper above so previews resolve the same way.
  // Media paths always arrive from the server with forward slashes.
  const STORAGE_PREFIX = '/storage/media/';
  function toPublicMediaUrl(url) {
    const value = String(url || '').trim();
    if (value === '') return '';
    const absolute = value.match(/^https?:\/\/[^/]+(\/storage\/media\/.*)$/i);
    if (absolute) return '/media/' + absolute[1].slice(STORAGE_PREFIX.length);
    if (value.startsWith(STORAGE_PREFIX)) return '/media/' + value.slice(STORAGE_PREFIX.length);
    if (value.startsWith(STORAGE_PREFIX.slice(1))) return '/media/' + value.slice(STORAGE_PREFIX.length - 1);
    return value;
  }

  function setAsset(targetId, url, mime, mediaId) {
    const input = document.getElementById(targetId);
    if (!input) return;
    const valueMode = input.dataset.brandValueMode || 'url';
    input.value = valueMode === 'id' ? (mediaId || '') : url;
    const preview = previewFor(targetId);
    const path = pathFor(targetId);
    if (preview) {
      preview.textContent = '';
      if (url) {
        if ((mime || '').startsWith('image/')) {
          const img = document.createElement('img');
          img.src = toPublicMediaUrl(url);
          img.alt = '';
          preview.appendChild(img);
        } else {
          const selected = document.createElement('span');
          selected.textContent = url;
          preview.appendChild(selected);
        }
      } else {
        const empty = document.createElement('span');
        empty.textContent = 'Neni vybrano';
        preview.appendChild(empty);
      }
    }
    if (path) path.textContent = url;
  }

  document.querySelectorAll('[data-open-brand-media]').forEach(btn => {
    btn.addEventListener('click', () => {
      activeTarget = btn.dataset.target || '';
      const target = document.getElementById(activeTarget);
      AdminMediaPicker.open({mode:'single',imagesOnly:target && target.dataset.brandValueMode==='id',onConfirm:list=>{
        const item=list[0]; if(item) setAsset(activeTarget,item.url,item.mime,item.id);
      }});
    });
  });

  document.querySelectorAll('[data-clear-brand-media]').forEach(btn => {
    btn.addEventListener('click', () => setAsset(btn.dataset.target || '', '', '', ''));
  });

  modal.querySelectorAll('[data-brand-media-item]').forEach(item => {
    item.addEventListener('click', () => {
      const target = document.getElementById(activeTarget);
      if (target && target.dataset.brandValueMode === 'id' && !(item.dataset.mime || '').startsWith('image/')) {
        window.alert('Pro OG fotografii lze vybrat pouze obrazek.');
        return;
      }
      if (activeTarget) setAsset(activeTarget, item.dataset.url || '', item.dataset.mime || '', item.dataset.id || '');
      closeModal();
    });
  });

  modal.querySelectorAll('[data-brand-media-close]').forEach(btn => btn.addEventListener('click', closeModal));
  modal.addEventListener('click', e => {
    if (e.target === modal) closeModal();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });
})();
</script>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
