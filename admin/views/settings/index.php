<?php
$pageTitle = 'Nastaveni webu';
$h = fn($v) => htmlspecialchars((string)($v ?? ''));
$media = $media ?? [];
$mediaUrl = fn(array $m): string => '/' . ltrim((string)($m['storage_path'] ?? ''), '/');
$mediaThumb = function(array $m) use ($mediaUrl): string {
    foreach (($m['variants'] ?? []) as $v) {
        if (($v['format'] ?? '') === 'thumb' && !empty($v['path'])) {
            return '/' . ltrim((string)$v['path'], '/');
        }
    }
    return $mediaUrl($m);
};

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$requestHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestUrl = $requestHost !== '' ? $scheme . '://' . $requestHost : '';

$storedDomain = trim((string)($site['domain'] ?? ''));
$isLocalDomain = $storedDomain === '' || str_contains($storedDomain, 'localhost');
$isRequestLocal = $requestUrl === '' || str_contains($requestUrl, 'localhost');
$domainValue = ($isLocalDomain && !$isRequestLocal) ? $requestUrl : $storedDomain;

ob_start();
?>
<div class="page-header">
  <h1>Nastaveni webu</h1>
</div>

<?php if ($isLocalDomain && !$isRequestLocal && $requestUrl !== ''): ?>
<div class="alert alert-warning" style="margin-bottom:16px">
  Domena je nastavena na <strong><?= $h($storedDomain ?: '(prazdna)') ?></strong>.
  Automaticky jsem predvyplnil spravnou URL z aktualniho requestu, zkontroluj ji a uloz.
</div>
<?php endif; ?>

<form method="POST" action="/admin/settings/update">
  <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">

  <div class="card">
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
      <label>Vychozi jazyk</label>
      <select name="default_language">
        <option value="cs" <?= ($site['default_language'] ?? 'cs') === 'cs' ? 'selected' : '' ?>>Cestina</option>
        <option value="en" <?= ($site['default_language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
        <option value="sk" <?= ($site['default_language'] ?? '') === 'sk' ? 'selected' : '' ?>>Slovencina</option>
      </select>
    </div>
  </div>

  <div class="card">
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
            <?php if (!empty($site['logo_url'])): ?><img src="<?= $h($site['logo_url']) ?>" alt="Logo"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
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
            <?php if (!empty($site['favicon_ico_url'])): ?><img src="<?= $h($site['favicon_ico_url']) ?>" alt="Favicon ICO"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
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
            <?php if (!empty($site['favicon_png_url'])): ?><img src="<?= $h($site['favicon_png_url']) ?>" alt="Favicon PNG/SVG"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
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
            <?php if (!empty($site['apple_touch_icon_url'])): ?><img src="<?= $h($site['apple_touch_icon_url']) ?>" alt="Apple touch icon"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
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

  <div class="card">
    <div class="card-title">Vychozi hero video</div>
    <p class="help-text" style="margin-bottom:16px">
      Toto video se pouzije jako fallback pro vsechny stranky, pokud konkretni stranka nema vlastni hero video.
      Poster obrazek se zobrazi okamzite pri nacitani videa, aby uzivatel nevidel cernou plochu.
    </p>
    <div class="form-row">
      <div class="form-group">
        <label>Defaultni hero video URL</label>
        <div class="help-text" style="margin-bottom:8px">Vlozte odkaz z Vimeo nebo YouTube, napr. <code>https://vimeo.com/123456789</code> nebo <code>https://www.youtube.com/watch?v=...</code>.</div>
        <input type="url" name="default_hero_video_url" id="default-hero-video-url" value="<?= $h($site['default_hero_video_url'] ?? '') ?>" placeholder="https://vimeo.com/... nebo https://www.youtube.com/watch?v=...">
      </div>
      <div class="form-group">
        <label>Placeholder / poster obrazek</label>
        <div class="help-text" style="margin-bottom:8px">Doporuceni: WebP/JPG 1920x1080 px, do 300-600 kB, tmavsi varianta vhodna pod text v hero sekci.</div>
        <div class="brand-asset-picker">
          <input type="hidden" name="default_hero_poster_url" id="default-hero-poster-url" value="<?= $h($site['default_hero_poster_url'] ?? '') ?>">
          <div class="brand-asset-preview" data-brand-preview for="default-hero-poster-url">
            <?php if (!empty($site['default_hero_poster_url'])): ?><img src="<?= $h($site['default_hero_poster_url']) ?>" alt="Hero poster"><?php else: ?><span>Neni vybrano</span><?php endif; ?>
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
          <input type="range" id="video-overlay-opacity-range" min="0" max="1" step="0.01" value="<?= $h($site['video_overlay_opacity'] ?? '0.62') ?>">
        </div>
        <div class="form-group" style="max-width:140px">
          <input type="number" name="video_overlay_opacity" id="video-overlay-opacity-input" min="0" max="1" step="0.01" value="<?= $h($site['video_overlay_opacity'] ?? '0.62') ?>" placeholder="0.62">
        </div>
      </div>
      <div class="help-text">Aktualni hodnota: <strong id="video-overlay-opacity-value"><?= $h($site['video_overlay_opacity'] ?? '0.62') ?></strong></div>
    </div>
  </div>

  <div class="card">
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

  <div class="card">
    <div class="card-title">Technicky nezbytne skripty</div>
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

  <div class="card">
    <div class="card-title">Analyticke skripty</div>
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

  <div class="card">
    <div class="card-title">Marketingove skripty</div>
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
  <div class="card">
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
        <p class="help-text">Najdes na clarity.microsoft.com → Nastaveni projektu. Spusti se s Analytics consent.</p>
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

  <div class="card">
    <div class="card-title">Layout webu (spolecna hlavicka a paticka)</div>
    <p class="help-text" style="margin-bottom:16px">
      HTML layout obali kazdou stranku. Dostupne placeholdery:<br>
      <code>{page_content}</code> — obsah stranky &nbsp;|&nbsp;
      <code>{site_name}</code> — nazev webu &nbsp;|&nbsp;
      <code>{nav}</code> — navigace (viz pole nav nize). Ponech prazdne, pokud kazda sablona ma vlastni HTML.
    </p>
    <div class="form-group">
      <label>Layout HTML</label>
      <textarea class="code" name="layout_html" rows="14"><?= $h($site['layout_html'] ?? '') ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Layout CSS (spolecne styly — nav, paticka, zaklad)</label>
        <textarea class="code" name="layout_css" rows="10"><?= $h($site['layout_css'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>Layout JS (spolecny skript — scroll, nav efekty)</label>
        <textarea class="code" name="layout_js" rows="10"><?= $h($site['layout_js'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Nav — homepage (hash anchory pro One Page scroll)</label>
        <textarea class="code" name="layout_nav_home" rows="8"><?= $h($site['layout_nav_home'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>Nav — subpages (prime URL na jednotlive stranky)</label>
        <textarea class="code" name="layout_nav_sub" rows="8"><?= $h($site['layout_nav_sub'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">Ulozit nastaveni</button>
</form>

<div class="card" style="margin-top:24px">
  <div class="card-title">REST API</div>
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
      <li><code>GET    /api/v1/articles</code> — seznam vsech clanku</li>
      <li><code>POST   /api/v1/articles</code> — vytvorit novy clanek (JSON: title*, slug, excerpt, content, status, featured_image_id, gallery_ids[], meta_title, meta_description)</li>
      <li><code>GET    /api/v1/articles/{slug}</code> — detail clanku</li>
      <li><code>PUT    /api/v1/articles/{slug}</code> — aktualizovat clanek (JSON: stejna tela jako POST, vsechna pole volitelna)</li>
      <li><code>DELETE /api/v1/articles/{slug}</code> — smazat clanek</li>
      <li style="margin-top:6px"><code>GET    /api/v1/portfolio</code> — seznam portfolia</li>
      <li><code>POST   /api/v1/portfolio</code> — vytvorit polozku (JSON: title*, slug, description, video_url, status, sort_order, media_ids[], meta_title, meta_description)</li>
      <li><code>GET    /api/v1/portfolio/{slug}</code> — detail polozky</li>
      <li><code>PUT    /api/v1/portfolio/{slug}</code> — aktualizovat polozku (JSON: stejna tela jako POST, vsechna pole volitelna)</li>
      <li><code>DELETE /api/v1/portfolio/{slug}</code> — smazat polozku</li>
      <li style="margin-top:6px"><code>POST   /api/v1/media</code> — nahrat medium (multipart/form-data: file/files[], alt_text*, title)</li>
      <li><code>DELETE /api/v1/media/{id}</code> — smazat medium</li>
    </ul>
    <p class="help-text" style="margin-top:8px">* povinna pole &nbsp;|&nbsp; status: <code>draft</code> (vychozi) nebo <code>published</code></p>
  </div>
  <?php else: ?>
  <p class="help-text">API klic bude automaticky vygenerovan pri prvnim volani API endpointu.</p>
  <?php endif; ?>
</div>

<style>
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
  var range = document.getElementById('video-overlay-opacity-range');
  var input = document.getElementById('video-overlay-opacity-input');
  var value = document.getElementById('video-overlay-opacity-value');
  if(!range || !input || !value) return;
  function normalize(v){
    var n = parseFloat(String(v).replace(',', '.'));
    if (!Number.isFinite(n)) n = 0.62;
    n = Math.max(0, Math.min(1, n));
    return n.toFixed(2);
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
      <p class="help-text">Zatim nejsou nahrana zadna media. Nejdrive je nahraj v sekci <a href="/admin/media">Media</a>.</p>
    <?php else: ?>
      <div class="brand-media-list">
        <?php foreach ($media as $m): ?>
          <?php
            $url = $mediaUrl($m);
            $thumb = $mediaThumb($m);
            $mime = (string)($m['mime_type'] ?? '');
            $isImage = str_starts_with($mime, 'image/');
          ?>
          <button type="button" class="brand-media-item" data-brand-media-item data-url="<?= $h($url) ?>" data-mime="<?= $h($mime) ?>" title="<?= $h($m['original_filename'] ?? '') ?>">
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

  function setAsset(targetId, url, mime) {
    const input = document.getElementById(targetId);
    if (!input) return;
    input.value = url;
    const preview = previewFor(targetId);
    const path = pathFor(targetId);
    if (preview) {
      preview.textContent = '';
      if (url) {
        if ((mime || '').startsWith('image/')) {
          const img = document.createElement('img');
          img.src = url;
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
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
    });
  });

  document.querySelectorAll('[data-clear-brand-media]').forEach(btn => {
    btn.addEventListener('click', () => setAsset(btn.dataset.target || '', '', ''));
  });

  modal.querySelectorAll('[data-brand-media-item]').forEach(item => {
    item.addEventListener('click', () => {
      if (activeTarget) setAsset(activeTarget, item.dataset.url || '', item.dataset.mime || '');
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
