<?php
$isEdit = $page !== null;
$pageTitle = $isEdit ? 'Upravit stranku' : 'Nova stranka';
$h = fn($v) => htmlspecialchars((string)$v);
$layoutType = $layoutType ?? 'generic';
$hasPortfolioGallery = $isEdit && trim((string)($page['url_path'] ?? ''), '/') === 'nase-prace';
$templateName = $template['name'] ?? '';
$socialOptions = ['instagram','facebook','youtube','tiktok','linkedin','twitter','pinterest','whatsapp','telegram'];
$fixedSocials = [
    1 => ['key' => 'instagram', 'label' => 'Instagram'],
    2 => ['key' => 'facebook', 'label' => 'Facebook'],
    3 => ['key' => 'youtube', 'label' => 'YouTube'],
    4 => ['key' => 'tiktok', 'label' => 'TikTok'],
    5 => ['key' => 'linkedin', 'label' => 'LinkedIn'],
];
$media = $media ?? [];
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
    foreach (($m['variants'] ?? []) as $variant) {
        if (($variant['format'] ?? '') === 'thumb' && !empty($variant['path'])) {
            return $publicMediaUrl((string)$variant['path']);
        }
    }
    return $mediaUrl($m);
};
$renderMediaField = static function (string $fieldId, string $value, bool $disabled = false) use ($h): void {
    $value = trim($value);
    ?>
    <div class="media-field" data-media-field>
      <input type="hidden" id="<?= $h($fieldId) ?>" name="block_content[]" value="<?= $h($value) ?>" data-media-field-input<?= $disabled ? ' disabled' : '' ?>>
      <div class="media-field__preview" data-media-field-preview>
        <?php if ($value !== ''): ?><img src="<?= $h($value) ?>" alt=""><?php else: ?><span>Neni vybrano</span><?php endif; ?>
      </div>
      <div class="media-field__body">
        <div class="media-field__actions">
          <button type="button" class="btn btn-sm btn-secondary" data-page-media-open data-target-id="<?= $h($fieldId) ?>"<?= $disabled ? ' disabled' : '' ?>>Vybrat z medii</button>
          <button type="button" class="btn btn-sm btn-secondary" data-media-field-clear<?= $disabled ? ' disabled' : '' ?><?= $value === '' ? ' hidden' : '' ?>>Odebrat</button>
        </div>
        <div class="media-field__path" data-media-field-path><?= $value !== '' ? $h($value) : 'Zadne medium neni vybrano' ?></div>
      </div>
    </div>
    <?php
};
// A video field (Vimeo / YouTube URL or a video from the library) with the Media button.
// Shared by both block renderers below, so no page ends up with a bare URL input.
$renderVideoField = static function (string $fieldId, string $value, bool $disabled = false) use ($h): void {
    $off = $disabled ? ' disabled' : '';
    ?>
    <div class="hero-video-field" style="display:flex;gap:8px;align-items:center">
      <input type="text" name="block_content[]" id="<?= $h($fieldId) ?>" value="<?= $h($value) ?>" placeholder="Vimeo / YouTube URL nebo video z Médií (/media/...)" style="flex:1" data-hero-video-input<?= $off ?>>
      <button type="button" class="btn btn-sm btn-secondary" data-hero-video-media data-target-id="<?= $h($fieldId) ?>"<?= $off ?>>Média</button>
      <button type="button" class="btn btn-sm btn-secondary" data-hero-video-clear data-target-id="<?= $h($fieldId) ?>"<?= $off ?>>Odebrat</button>
    </div>
    <?php
};
$blockMap = [];
$homepageAutofillBlocks = $homepageAutofillBlocks ?? [];
$serviceItems = $serviceItems ?? [];
$isHomepage = !empty($isHomepage);
$hiddenHomepageBlockKeys = array_fill_keys([
    'sluzby_cta',
    'portfolio_cta',
    'blog_cta',
    'cenik_cta',
    'faq_cta',
    'kontakt_cta',
    'jb-about-visual__media',
], true);
$visibleBlocks = $blocks ?? [];
if ($isHomepage && !empty($visibleBlocks)) {
    $visibleBlocks = array_values(array_filter($visibleBlocks, static function (array $block) use ($hiddenHomepageBlockKeys): bool {
        return !isset($hiddenHomepageBlockKeys[$block['block_key'] ?? '']);
    }));
}

foreach (($blocks ?? []) as $block) {
    $blockMap[$block['block_key']] = $block;
}

$renderedBlockKeys = [];
$renderBlockField = function (string $key, string $label, string $type = 'text', string $hint = '') use ($blockMap, $h, &$renderedBlockKeys, $renderMediaField, $renderVideoField): void {
    if (isset($renderedBlockKeys[$key])) {
        return;
    }
    $renderedBlockKeys[$key] = true;
    $block = $blockMap[$key] ?? ['content' => '', 'block_type' => $type];
    $value = $block['content'] ?? '';
    $inputType = in_array($key, ['hero_video', 'hero_video_tablet', 'hero_video_mobile'], true)
      ? 'video'
      : ($type === 'html' ? 'html' : ($block['block_type'] ?? $type));
    $fieldId = 'block-content-' . preg_replace('~[^a-z0-9_-]+~i', '-', $key);
    ?>
    <div class="form-group">
      <label><?= $h($label) ?></label>
      <input type="hidden" name="block_key[]" value="<?= $h($key) ?>">
      <?php if (in_array($inputType, ['text', 'link'], true)): ?>
        <input type="text" name="block_content[]" value="<?= $h($value) ?>">
      <?php elseif ($inputType === 'image'): ?>
        <?php $renderMediaField($fieldId, (string)$value); ?>
      <?php elseif ($inputType === 'video'): ?>
        <?php $renderVideoField($fieldId, (string)$value); ?>
      <?php elseif (in_array($inputType, ['html', 'richtext'], true)): ?>
        <div class="article-editor-wrap" data-richtext-editor>
          <div class="article-editor-toolbar">
            <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<h2>" title="Nadpis 2">H2</button>
            <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<h3>" title="Nadpis 3">H3</button>
            <span class="ae-sep"></span>
            <button type="button" class="ae-btn" data-cmd="bold" title="Tucne"><strong>B</strong></button>
            <button type="button" class="ae-btn" data-cmd="italic" title="Kurziva"><em>I</em></button>
            <button type="button" class="ae-btn" data-cmd="underline" title="Podtrzeni"><u>U</u></button>
            <span class="ae-sep"></span>
            <button type="button" class="ae-btn" data-cmd="insertUnorderedList" title="Odrzky">&#8226; ul</button>
            <button type="button" class="ae-btn" data-cmd="insertOrderedList" title="Cislovany seznam">1. ol</button>
            <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<p>" title="Odstavec">&para;</button>
            <span class="ae-sep"></span>
            <button type="button" class="ae-btn" data-cmd="insertMediaImage" title="Vlozit obrazek z medii">Obrazek</button>
            <button type="button" class="ae-btn ae-source-btn" data-cmd="source" title="HTML zdrojovy kod">&lt;/&gt; HTML</button>
          </div>
          <div class="richtext-content" data-richtext-content contenteditable="true"><?= (string)$value ?></div>
          <textarea class="code richtext-html-source" data-richtext-html-source style="display:none" spellcheck="false"></textarea>
        </div>
        <textarea name="block_content[]" data-richtext-hidden hidden><?= $h($value) ?></textarea>
      <?php else: ?>
        <textarea name="block_content[]" class="<?= $inputType === 'html' ? 'code' : '' ?>"><?= $h($value) ?></textarea>
      <?php endif; ?>
    <?php if ($hint !== ''): ?>
        <div class="form-hint"><?= $h($hint) ?></div>
      <?php endif; ?>
    </div>
    <?php
};

$renderServiceItemsEditor = function (string $cardTitle, string $cardHint) use ($serviceItems, $h): void {
    ?>
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px">
        <div>
          <div class="card-title"><?= $h($cardTitle) ?></div>
          <div class="form-hint"><?= $h($cardHint) ?></div>
        </div>
        <button type="button" class="btn btn-secondary" data-add-service-item>+ Pridat sluzbu</button>
      </div>

      <div id="service-items-list" style="display:flex;flex-direction:column;gap:16px">
        <?php foreach ($serviceItems as $index => $item): ?>
        <div class="card" data-service-item style="padding:18px">
          <div class="form-row">
            <div class="form-group">
              <label>Nazev</label>
              <input type="text" data-service-name="title" value="<?= $h($item['title'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Razeni</label>
              <input type="number" data-service-name="sort_order" value="<?= (int)($item['sort_order'] ?? ($index + 1)) ?>">
            </div>
          </div>
          <div class="form-group">
            <label>Popis</label>
            <textarea data-service-name="description"><?= $h($item['description'] ?? '') ?></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Obrazek / medium</label>
              <div class="media-input-row">
                <input type="text" data-service-name="image_url" value="<?= $h($item['image_url'] ?? '') ?>" placeholder="/media/...">
                <button type="button" class="btn btn-sm btn-secondary" data-service-media-open data-target-name="image_url">Vybrat</button>
              </div>
            </div>
            <div class="form-group">
              <label>Ikona / medium</label>
              <div style="display:flex;gap:8px">
                <input type="text" data-service-name="icon_url" value="<?= $h($item['icon_url'] ?? '') ?>" placeholder="/media/..." style="flex:1">
                <button type="button" class="btn btn-sm btn-secondary" data-service-media-open data-target-name="icon_url">Vybrat</button>
              </div>
              <div class="form-hint">Doporuceni: jednoducha SVG/PNG ikona, idealne 96x96 px.</div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Tlacitko</label>
              <input type="text" data-service-name="button_text" value="<?= $h($item['button_text'] ?? '') ?>" placeholder="Zjistit vice">
            </div>
            <div class="form-group">
              <label>Odkaz tlacitka</label>
              <input type="text" data-service-name="button_url" value="<?= $h($item['button_url'] ?? '') ?>" placeholder="/kontakt">
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
            <label style="display:flex;align-items:center;gap:8px">
              <input type="hidden" data-service-hidden-active value="0">
              <input type="checkbox" data-service-name="is_active" value="1" <?= !empty($item['is_active']) ? 'checked' : '' ?>>
              Aktivni
            </label>
            <button type="button" class="btn btn-sm btn-secondary" data-remove-service-item>Odebrat</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    </div>
    <?php
};

ob_start();
?>
<div class="page-header">
  <h1><?= $pageTitle ?></h1>
  <a href="/admin/pages" class="btn btn-secondary">&larr; Zpet</a>
</div>

<div data-tabs-container>
  <div class="tabs">
    <button type="button" class="tab-btn" data-tab="basic">Zakladni</button>
    <?php if ($hasPortfolioGallery): ?>
      <button type="button" class="tab-btn" data-tab="portfolio-gallery">Foto a video galerie</button>
    <?php endif; ?>
    <?php if ($isEdit && $page['template_id']): ?>
      <?php if ($layoutType === 'faq'): ?>
        <button type="button" class="tab-btn" data-tab="page-content">Obsah stranky</button>
        <button type="button" class="tab-btn" data-tab="faq-items">FAQ polozky</button>
      <?php elseif ($layoutType === 'contact'): ?>
        <button type="button" class="tab-btn" data-tab="page-content">Obsah stranky</button>
        <button type="button" class="tab-btn" data-tab="contact-basic">Zakladni info</button>
        <button type="button" class="tab-btn" data-tab="contact-social">Socialni site</button>
        <button type="button" class="tab-btn" data-tab="contact-map">Mapa a formular</button>
      <?php elseif ($layoutType === 'pricing'): ?>
        <button type="button" class="tab-btn" data-tab="page-content">Obsah stranky</button>
        <button type="button" class="tab-btn" data-tab="pricing-sections">Sekce ceniku</button>
        <?php for ($pricingCard = 1; $pricingCard <= 8; $pricingCard++): ?>
        <button type="button" class="tab-btn" data-tab="pricing-<?= $pricingCard ?>">Karta <?= $pricingCard ?></button>
        <?php endfor; ?>
      <?php elseif ($layoutType === 'services'): ?>
        <button type="button" class="tab-btn" data-tab="page-content">Obsah stranky</button>
        <button type="button" class="tab-btn" data-tab="services-items">Polozky sluzeb</button>
        <button type="button" class="tab-btn" data-tab="services-grid">Sluzby</button>
        <?php if ($isHomepage): ?>
          <button type="button" class="tab-btn" data-tab="homepage-services">Služby na hlavní stránce</button>
        <?php endif; ?>
      <?php else: ?>
        <button type="button" class="tab-btn" data-tab="blocks">Bloky obsahu <?= !empty($visibleBlocks) ? '(' . count($visibleBlocks) . ')' : '' ?></button>
        <?php if ($isHomepage): ?>
          <button type="button" class="tab-btn" data-tab="homepage-services">Služby homepage</button>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <form method="POST" action="<?= $isEdit ? '/admin/pages/update' : '/admin/pages/store' ?>" id="page-form">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$page['id'] ?>"><?php endif; ?>

    <div class="tab-pane" id="tab-basic">
      <div class="card">
        <div class="form-group">
          <label for="page-title">Nazev stranky *</label>
          <input type="text" id="page-title" name="title" value="<?= $h($page['title'] ?? '') ?>" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="page-slug">Slug *</label>
            <input type="text" id="page-slug" name="slug" value="<?= $h($page['slug'] ?? '') ?>" required>
            <div class="form-hint">Bez diakritiky, mala pismena, pomlcky</div>
          </div>
          <div class="form-group">
            <label for="page-url">URL cesta</label>
            <input type="text" id="page-url" name="url_path" value="<?= $h($page['url_path'] ?? '') ?>">
            <div class="form-hint">Napriklad sluzby/fotografie</div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group form-group--full">
            <label>Layout stranky</label>
            <div class="tpl-picker" id="tpl-picker">
              <label class="tpl-card <?= empty($page['template_id']) ? 'tpl-card--active' : '' ?>">
                <input type="radio" name="template_id" value="" <?= empty($page['template_id']) ? 'checked' : '' ?>>
                <span class="tpl-card-icon">&empty;</span>
                <span class="tpl-card-name">Bez layoutu</span>
              </label>
              <?php foreach ($templates as $t): ?>
              <label class="tpl-card <?= ($page['template_id'] ?? 0) == $t['id'] ? 'tpl-card--active' : '' ?>">
                <input type="radio" name="template_id" value="<?= (int)$t['id'] ?>" <?= ($page['template_id'] ?? 0) == $t['id'] ? 'checked' : '' ?>>
                <span class="tpl-card-icon">&#8854;</span>
                <span class="tpl-card-name"><?= $h($t['name']) ?></span>
                <a href="/admin/layouts/edit?id=<?= (int)$t['id'] ?>" class="tpl-card-edit" onclick="event.stopPropagation()" title="Upravit layout">&#9998;</a>
              </label>
              <?php endforeach; ?>
            </div>
            <?php if ($isEdit && $templateName !== ''): ?>
              <div class="form-hint">Aktivni layout: <?= $h($templateName) ?></div>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label for="page-lang">Jazyk</label>
            <select id="page-lang" name="language">
              <option value="cs" <?= ($page['language'] ?? 'cs') === 'cs' ? 'selected' : '' ?>>Cestina</option>
              <option value="en" <?= ($page['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
              <option value="sk" <?= ($page['language'] ?? '') === 'sk' ? 'selected' : '' ?>>Slovencina</option>
            </select>
          </div>
        </div>
      </div>

      <?php if (!$isEdit): ?>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">Vytvorit stranku</button>
        <a href="/admin/pages" class="btn btn-secondary">Zrusit</a>
      </div>
      <?php else: ?>
      <div class="card">
        <div class="card-title">Hero pozadi (video nebo foto)</div>
        <p class="form-hint" style="margin-bottom:12px">
          Prednost ma video. Pokud hero video nechate prazdne a vyplnite hero foto, pouzije se foto.
          Kazda sirka okna ma vlastni video a nic se nededi mezi zarizenimi: prazdne pole = na dane sirce zadne video. Kdyz jsou vsechna tri pole prazdna, pouzije se video z Nastaveni (Vychozi hero video).
          Vsechny vyplnene sirky musi byt stejneho druhu - bud Vimeo/YouTube odkazy, nebo videa z Medii. Pri smesi se prehraje jen druh prvni vyplnene sirky.
        </p>
        <?php $renderBlockField('hero_video', 'Hero video - desktop', 'link', 'Prehraje se pri sirce okna nad 1050 px. Vimeo / YouTube URL, nebo tlacitko Media -> zalozka Video. Prazdne = na desktopu zadne video.'); ?>
        <?php $renderBlockField('hero_video_tablet', 'Hero video - tablet', 'link', 'Prehraje se pri sirce okna 701-1050 px. Prazdne = na tabletu zadne video (nededi se z desktopu).'); ?>
        <?php $renderBlockField('hero_video_mobile', 'Hero video - mobil', 'link', 'Prehraje se pri sirce okna do 700 px. Prazdne = na mobilu zadne video (nededi se z tabletu ani desktopu).'); ?>
        <?php $renderBlockField('hero_bg', 'Hero foto', 'image', 'Pouzije se jako pozadi hero sekce, kdyz neni vyplnene hero video.'); ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($isEdit && $page['template_id'] && empty($blocks) && !in_array($layoutType, ['faq', 'contact'], true)): ?>
    <div class="tab-pane" id="tab-blocks">
      <div class="card">
        <p style="color:var(--c-muted);margin-bottom:14px">Tato stranka zatim nema zadne bloky. Synchronizujte je z layoutu.</p>
        <button type="submit" form="sync-form" class="btn btn-primary">Nacist bloky z layoutu</button>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($isEdit && $isHomepage): ?>
    <div class="tab-pane" id="tab-homepage-services">
      <?php $aboutVisualBlock = $blockMap['jb-about-visual__media'] ?? ['content' => '']; ?>
      <?php $aboutVisualUrl = $publicMediaUrl((string)($aboutVisualBlock['content'] ?? '')); ?>
      <div class="card" style="margin-bottom:16px">
        <div class="card-title">Vizuál sekce „Naše služby“</div>
        <div class="form-hint" style="margin-bottom:12px">
          Tohle je samostatný obrazový panel pro homepage about vizuál. Vybraný obrázek se použije na homepage a můžeš ho kdykoli vyměnit z médií.
        </div>
        <div class="brand-asset-picker" data-homepage-about-visual-picker>
          <input type="hidden" name="homepage_about_visual_url" id="homepage-about-visual-url" value="<?= $h($aboutVisualUrl) ?>">
          <div class="brand-asset-preview homepage-about-visual-preview" data-homepage-about-visual-preview>
            <?php if ($aboutVisualUrl !== ''): ?>
              <img src="<?= $h($aboutVisualUrl) ?>" alt="Homepage vizuál služby">
            <?php else: ?>
              <span>Neni vybrano</span>
            <?php endif; ?>
          </div>
          <div class="brand-asset-actions">
            <button type="button" class="btn btn-secondary" data-homepage-about-visual-open>Vybrat z medii</button>
            <button type="button" class="btn btn-secondary" data-homepage-about-visual-clear>Odebrat</button>
          </div>
          <div class="homepage-about-visual-upload">
            <div class="form-group">
              <label for="homepage-about-visual-file">Nahrat novou fotku</label>
              <input type="file" id="homepage-about-visual-file" accept="image/jpeg,image/png,image/webp,image/gif" data-homepage-about-visual-file>
            </div>
            <div class="form-group">
              <label for="homepage-about-visual-alt">ALT text *</label>
              <input type="text" id="homepage-about-visual-alt" value="RB Videos &amp; Photos pri praci" data-homepage-about-visual-alt>
            </div>
            <button type="button" class="btn btn-primary" data-homepage-about-visual-upload>Nahrat a vybrat fotku</button>
            <div class="help-text" role="status" data-homepage-about-visual-upload-status></div>
          </div>
          <div class="help-text brand-asset-path" data-homepage-about-visual-path><?= $h($aboutVisualUrl) ?></div>
        </div>
      </div>
      <?php $renderServiceItemsEditor('Služby na homepage', 'Tyto polozky se zobrazuji na homepage i na strance Služby. Obrazek se bere z media knihovny a pouzije se zmensena varianta.'); ?>
    </div>
    <?php endif; ?>

    <?php if ($isEdit && $layoutType === 'faq'): ?>
    <div class="tab-pane" id="tab-page-content">
      <div style="margin-bottom:12px">
        <button type="submit" form="sync-form" class="btn btn-sm btn-secondary">&#8635; Synchronizovat bloky z layoutu</button>
      </div>
      <div class="card">
        <?php $renderBlockField('hero_h1', 'Hero nadpis'); ?>
        <?php $renderBlockField('hero_sub', 'Hero intro', 'textarea'); ?>
        <?php $renderBlockField('cta_wrap', 'Spodni CTA', 'html', 'Napriklad tlacitko nebo vyzva pod seznamem otazek.'); ?>
      </div>
    </div>

    <div class="tab-pane" id="tab-faq-items">
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px">
          <div>
            <div class="card-title">FAQ polozky</div>
            <div class="form-hint">Tyto polozky se zobrazi na FAQ strance a prvni tri aktivni se propsi i na homepage.</div>
          </div>
          <button type="button" class="btn btn-secondary" data-add-faq-item>+ Pridat otazku</button>
        </div>

        <div id="faq-items-list" style="display:flex;flex-direction:column;gap:16px">
          <?php foreach (($faqItems ?? []) as $index => $item): ?>
          <div class="card" data-faq-item style="padding:18px">
            <div class="form-row">
              <div class="form-group">
                <label>Otazka</label>
                <input type="text" data-faq-name="question" value="<?= $h($item['question'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Razeni</label>
                <input type="number" data-faq-name="sort_order" value="<?= (int)($item['sort_order'] ?? ($index + 1)) ?>">
              </div>
            </div>
            <div class="form-group">
              <label>Odpoved</label>
              <textarea data-faq-name="answer"><?= $h($item['answer'] ?? '') ?></textarea>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
              <label style="display:flex;align-items:center;gap:8px">
                <input type="hidden" data-faq-hidden-active value="0">
                <input type="checkbox" data-faq-name="is_active" value="1" <?= !empty($item['is_active']) ? 'checked' : '' ?>>
                Aktivni
              </label>
              <button type="button" class="btn btn-sm btn-secondary" data-remove-faq-item>Odebrat</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <?php if (empty($faqItems)): ?>
        <p class="form-hint" id="faq-empty-state">Zatím tu nejsou žádné otázky. Přidáte je tlačítkem nahoře.</p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($isEdit && $layoutType === 'contact'): ?>
    <div class="tab-pane" id="tab-page-content">
      <div style="margin-bottom:12px">
        <button type="submit" form="sync-form" class="btn btn-sm btn-secondary">&#8635; Synchronizovat bloky z layoutu</button>
      </div>
      <div class="card">
        <?php $renderBlockField('hero_h1', 'Hero nadpis'); ?>
        <?php $renderBlockField('hero_sub', 'Hero intro', 'textarea'); ?>
      </div>
    </div>

    <div class="tab-pane" id="tab-contact-basic">
      <div class="card">
        <div class="card-title">Telefonni cisla</div>
        <div class="form-row">
          <div class="form-group">
            <label>Telefon 1</label>
            <input type="text" name="phone_1" value="<?= $h($contact['phone_1'] ?? '') ?>" placeholder="+420 123 456 789">
          </div>
          <div class="form-group">
            <label>Telefon 2</label>
            <input type="text" name="phone_2" value="<?= $h($contact['phone_2'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Telefon 3</label>
            <input type="text" name="phone_3" value="<?= $h($contact['phone_3'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-title">E-mail a adresy</div>
        <div class="form-group">
          <label>E-mail</label>
          <input type="email" name="email" value="<?= $h($contact['email'] ?? '') ?>" placeholder="info@firma.cz">
        </div>
        <div class="form-group">
          <label>Adresa 1</label>
          <textarea name="address_1"><?= $h($contact['address_1'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label>Adresa 2</label>
          <textarea name="address_2"><?= $h($contact['address_2'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <div class="tab-pane" id="tab-contact-social">
      <div class="card">
        <p class="form-hint" style="margin-bottom:18px">Kazda socialni sit ma vlastni URL pole. Pokud ji nepouzivas, nech pole prazdne.</p>
        <?php foreach ($fixedSocials as $i => $social): ?>
        <div class="form-row" style="align-items:flex-end;margin-bottom:16px">
          <div class="form-group" style="flex:0 0 180px">
            <label><?= $h($social['label']) ?></label>
            <input type="hidden" name="social_<?= $i ?>_type" value="<?= $h($social['key']) ?>">
            <input type="text" value="<?= $h(strtoupper(substr($social['key'], 0, 2))) ?>" disabled>
          </div>
          <div class="form-group" style="flex:1">
            <label>URL profilu</label>
            <input type="url" name="social_<?= $i ?>_url" value="<?= $h($contact['social_' . $i . '_url'] ?? '') ?>" placeholder="https://...">
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tab-pane" id="tab-contact-map">
      <div class="card">
        <div class="card-title">Mapa</div>
        <div class="form-group">
          <label>Odkaz na Google Mapy</label>
          <input type="url" name="maps_url" value="<?= $h($contact['maps_url'] ?? '') ?>" placeholder="https://maps.google.com/...">
        </div>
      </div>

      <div class="card">
        <div class="card-title">Kontaktní formulář</div>
        <div class="form-group">
          <label>E-mail pro prijem zprav</label>
          <input type="email" name="form_email" value="<?= $h($contact['form_email'] ?? '') ?>" placeholder="notifikace@firma.cz">
          <div class="form-hint">Na tuto adresu budou chodit zpravy z formulare na strance Kontakt.</div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($isEdit && $layoutType === 'pricing'): ?>
    <div class="tab-pane" id="tab-page-content">
      <div style="margin-bottom:12px">
        <button type="submit" form="sync-form" class="btn btn-sm btn-secondary">&#8635; Synchronizovat bloky z layoutu</button>
      </div>
      <div class="card">
        <?php
        // The pricing page renders hero_heading/hero_intro; hero_h1/hero_sub are
        // the older generic keys that some sites still store. Edit whichever pair
        // this page actually has, so the field is never wired to a dead block.
        $heroHeadingKey = isset($blockMap['hero_heading']) ? 'hero_heading' : 'hero_h1';
        $heroIntroKey   = isset($blockMap['hero_intro']) ? 'hero_intro' : 'hero_sub';
        ?>
        <?php $renderBlockField($heroHeadingKey, 'Hero nadpis'); ?>
        <?php $renderBlockField($heroIntroKey, 'Hero intro', 'textarea'); ?>
        <?php $renderBlockField('hero_bg', 'Hero obrazek / medium', 'image'); ?>
        <?php $renderBlockField('cta_wrap', 'Spodni CTA', 'html'); ?>
      </div>
    </div>
    <div class="tab-pane" id="tab-pricing-sections">
      <div class="card">
        <div class="card-title">Balicky</div>
        <?php $renderBlockField('cenik_packages_kicker', 'Stitek nad nadpisem'); ?>
        <?php $renderBlockField('pricing_weddings_heading', 'Nadpis sekce'); ?>
        <?php $renderBlockField('pricing_weddings_sub', 'Text pod nadpisem', 'textarea'); ?>
      </div>
      <div class="card">
        <div class="card-title">Doprava</div>
        <?php $renderBlockField('cenik_transport_kicker', 'Stitek nad nadpisem'); ?>
        <?php $renderBlockField('pricing_transport_heading', 'Nadpis sekce'); ?>
        <?php $renderBlockField('pricing_transport_text', 'Text sekce', 'textarea'); ?>
      </div>
      <div class="card">
        <div class="card-title">Caste otazky</div>
        <?php $renderBlockField('cenik_faq_kicker', 'Stitek nad nadpisem'); ?>
        <?php $renderBlockField('cenik_faq_heading', 'Nadpis sekce'); ?>
        <?php for ($faqSlot = 1; $faqSlot <= 5; $faqSlot++): ?>
          <?php $renderBlockField('cenik_faq_' . $faqSlot . '_question', 'Otazka ' . $faqSlot); ?>
          <?php $renderBlockField('cenik_faq_' . $faqSlot . '_answer', 'Odpoved ' . $faqSlot, 'textarea'); ?>
        <?php endfor; ?>
      </div>
      <div class="card">
        <div class="card-title">Spodni CTA</div>
        <?php $renderBlockField('cenik_cta_kicker', 'Stitek nad nadpisem'); ?>
        <?php $renderBlockField('cta_heading', 'Nadpis'); ?>
        <?php $renderBlockField('cta_sub', 'Text pod nadpisem', 'textarea'); ?>
        <?php $renderBlockField('cenik_cta_button', 'Zalozni tlacitko', 'text', 'Pouzije se jen kdyz je pole "Spodni CTA" v zalozce Obsah stranky prazdne.'); ?>
      </div>
    </div>
    <?php for ($pricingCard = 1; $pricingCard <= 8; $pricingCard++): ?>
    <div class="tab-pane" id="tab-pricing-<?= $pricingCard ?>"><div class="card">
      <?php $renderBlockField('cenik_card_' . $pricingCard . '_title', 'Nadpis karty'); ?>
      <?php $renderBlockField('cenik_card_' . $pricingCard . '_category', 'Kategorie (stitek nad nadpisem)'); ?>
      <?php $renderBlockField('cenik_card_' . $pricingCard . '_badge', 'Odznak (napr. Nejzadanejsi)', 'text', 'Kdyz je vyplneno, karta se zvyrazni.'); ?>
      <?php $renderBlockField('cenik_card_' . $pricingCard . '_price', 'Cena'); ?>
      <?php $renderBlockField('cenik_card_' . $pricingCard . '_unit', 'Jednotka za cenou (napr. cely den)'); ?>
      <?php $renderBlockField('cenik_card_' . $pricingCard . '_desc', 'Popis', 'textarea', 'Prvni odstavec je shrnuti karty, dalsi radky se vypisi jako odrazky.'); ?>
      <?php $renderBlockField('cenik_card_' . $pricingCard . '_cta_label', 'Text tlacitka'); ?>
      <?php $renderBlockField('cenik_card_' . $pricingCard . '_cta_url', 'Odkaz tlacitka'); ?>
    </div></div>
    <?php endfor; ?>
    <?php endif; ?>

    <?php if ($isEdit && $layoutType === 'services'): ?>
    <div class="tab-pane" id="tab-page-content">
      <div style="margin-bottom:12px">
        <button type="submit" form="sync-form" class="btn btn-sm btn-secondary">&#8635; Synchronizovat bloky z layoutu</button>
      </div>
      <div class="card">
        <?php $renderBlockField('hero_h1', 'Hero nadpis'); ?>
        <?php $renderBlockField('hero_sub', 'Hero intro', 'textarea'); ?>
        <?php $renderBlockField('cta_wrap', 'Spodni CTA', 'html'); ?>
      </div>
    </div>
    <div class="tab-pane" id="tab-services-items">
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px">
          <div>
            <div class="card-title">Polozky sluzeb</div>
            <div class="form-hint">Polozky se zobrazi na strance Služby a prvni tri aktivni se propsi i na homepage.</div>
          </div>
          <button type="button" class="btn btn-secondary" data-add-service-item>+ Pridat sluzbu</button>
        </div>

        <div id="service-items-list" style="display:flex;flex-direction:column;gap:16px">
          <?php foreach ($serviceItems as $index => $item): ?>
          <div class="card" data-service-item style="padding:18px">
            <div class="form-row">
              <div class="form-group">
                <label>Nazev</label>
                <input type="text" data-service-name="title" value="<?= $h($item['title'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Razeni</label>
                <input type="number" data-service-name="sort_order" value="<?= (int)($item['sort_order'] ?? ($index + 1)) ?>">
              </div>
            </div>
            <div class="form-group">
              <label>Popis</label>
              <textarea data-service-name="description"><?= $h($item['description'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Obrazek / medium</label>
                <div class="media-input-row">
                  <input type="text" data-service-name="image_url" value="<?= $h($item['image_url'] ?? '') ?>" placeholder="/media/...">
                  <button type="button" class="btn btn-sm btn-secondary" data-service-media-open data-target-name="image_url">Vybrat</button>
                </div>
              </div>
              <div class="form-group">
                <label>Ikona / medium</label>
                <div style="display:flex;gap:8px">
                  <input type="text" data-service-name="icon_url" value="<?= $h($item['icon_url'] ?? '') ?>" placeholder="/media/..." style="flex:1">
                  <button type="button" class="btn btn-sm btn-secondary" data-service-media-open data-target-name="icon_url">Vybrat</button>
                </div>
                <div class="form-hint">Doporuceni: jednoducha SVG/PNG ikona, idealne 96x96 px.</div>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Tlacitko</label>
                <input type="text" data-service-name="button_text" value="<?= $h($item['button_text'] ?? '') ?>" placeholder="Zjistit vice">
              </div>
              <div class="form-group">
                <label>Odkaz tlacitka</label>
                <input type="text" data-service-name="button_url" value="<?= $h($item['button_url'] ?? '') ?>" placeholder="/kontakt">
              </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
              <label style="display:flex;align-items:center;gap:8px">
                <input type="hidden" data-service-hidden-active value="0">
                <input type="checkbox" data-service-name="is_active" value="1" <?= !empty($item['is_active']) ? 'checked' : '' ?>>
                Aktivni
              </label>
              <button type="button" class="btn btn-sm btn-secondary" data-remove-service-item>Odebrat</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      </div>
    </div>
    <div class="tab-pane" id="tab-services-grid">
      <div class="card">
        <div class="card-title">Automaticky generovany obsah</div>
        <p class="form-hint">Tento obsah se sklada z polozek sluzeb vyse a pri publikaci se doplni automaticky.</p>
        <div style="padding:18px;border:1px dashed var(--c-border);border-radius:12px;background:var(--c-bg);color:var(--c-muted)">
          Náhled obsahu je generován z položek služeb a nelze jej upravovat ručně.
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($isEdit && $layoutType === 'generic' && !empty($blocks)): ?>
    <div class="tab-pane" id="tab-blocks">
      <div style="margin-bottom:12px">
        <button type="submit" form="sync-form" class="btn btn-sm btn-secondary">&#8635; Synchronizovat bloky z layoutu</button>
      </div>
      <div class="card">
        <div class="blocks-list">
          <?php foreach ($blocks as $block): ?>
          <?php if ($isHomepage && isset($hiddenHomepageBlockKeys[$block['block_key'] ?? ''])) continue; ?>
          <?php if (isset($renderedBlockKeys[$block['block_key'] ?? ''])) continue; ?>
          <?php $autofillBlock = $isHomepage ? ($homepageAutofillBlocks[$block['block_key']] ?? null) : null; ?>
          <?php $autofillEnabled = $autofillBlock ? empty($block['autofill_disabled']) : false; ?>
          <?php $blockFieldId = 'block-content-' . preg_replace('~[^a-z0-9_-]+~i', '-', (string)($block['block_key'] ?? 'block')); ?>
          <div class="block-item<?= $autofillBlock ? ' autofill-panel' : '' ?><?= $autofillEnabled ? ' is-autofill-enabled' : '' ?>" data-autofill-panel="<?= $autofillBlock ? '1' : '0' ?>">
            <div class="block-label">
              <?= $h($block['label'] ?? $block['block_key']) ?>
              <span class="block-type-badge"><?= $h($block['block_type']) ?></span>
            </div>
            <div class="autofill-panel__fields"<?= $autofillEnabled ? ' hidden aria-hidden="true"' : '' ?>>
              <input type="hidden" name="block_key[]" value="<?= $h($block['block_key']) ?>">
              <?php if (in_array($block['block_type'], ['text','link'], true)): ?>
                <input type="text" name="block_content[]" value="<?= $h($block['content'] ?? '') ?>"<?= $autofillEnabled ? ' disabled' : '' ?>>
              <?php elseif ($block['block_type'] === 'image'): ?>
                <?php $renderMediaField($blockFieldId, (string)($block['content'] ?? ''), $autofillEnabled); ?>
              <?php elseif (($block['block_type'] === 'video') || $block['block_key'] === 'hero_video'): ?>
                <?php $renderVideoField($blockFieldId, (string)($block['content'] ?? ''), $autofillEnabled); ?>
                <?php
                  $videoHints = [
                    'hero_video' => 'Desktop (nad 1050 px). Vimeo / YouTube URL, nebo tlačítko Média → záložka Video. Prázdné = na desktopu žádné video.',
                    'hero_video_tablet' => 'Tablet (701-1050 px). Prázdné = na tabletu žádné video (nedědí se z desktopu).',
                    'hero_video_mobile' => 'Mobil (do 700 px). Prázdné = na mobilu žádné video (nedědí se z tabletu ani desktopu).',
                  ];
                ?>
                <div class="form-hint"><?= $h($videoHints[$block['block_key']] ?? 'URL videa (YouTube nebo Vimeo) nebo video z Médií. Pokud je vyplněno, přehraje se místo přiřazené fotografie.') ?></div>
              <?php elseif (in_array($block['block_type'], ['html','richtext'], true)): ?>
                <div class="article-editor-wrap" data-richtext-editor>
                  <div class="article-editor-toolbar">
                    <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<h2>" title="Nadpis 2">H2</button>
                    <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<h3>" title="Nadpis 3">H3</button>
                    <span class="ae-sep"></span>
                    <button type="button" class="ae-btn" data-cmd="bold" title="Tucne"><strong>B</strong></button>
                    <button type="button" class="ae-btn" data-cmd="italic" title="Kurziva"><em>I</em></button>
                    <button type="button" class="ae-btn" data-cmd="underline" title="Podtrzeni"><u>U</u></button>
                    <span class="ae-sep"></span>
                    <button type="button" class="ae-btn" data-cmd="insertUnorderedList" title="Odrzky">&#8226; ul</button>
                    <button type="button" class="ae-btn" data-cmd="insertOrderedList" title="Cislovany seznam">1. ol</button>
                    <button type="button" class="ae-btn" data-cmd="formatBlock" data-val="<p>" title="Odstavec">&para;</button>
                    <span class="ae-sep"></span>
                    <button type="button" class="ae-btn" data-cmd="insertMediaImage" title="Vlozit obrazek z medii">Obrazek</button>
                    <button type="button" class="ae-btn ae-source-btn" data-cmd="source" title="HTML zdrojovy kod">&lt;/&gt; HTML</button>
                  </div>
                  <div class="richtext-content" data-richtext-content contenteditable="<?= $autofillEnabled ? 'false' : 'true' ?>"><?= (string)($block['content'] ?? '') ?></div>
                  <textarea class="code richtext-html-source" data-richtext-html-source style="display:none" spellcheck="false"></textarea>
                </div>
                <textarea name="block_content[]" data-richtext-hidden hidden<?= $autofillEnabled ? ' disabled' : '' ?>><?= $h($block['content'] ?? '') ?></textarea>
              <?php else: ?>
                <textarea name="block_content[]" class="<?= $block['block_type'] === 'html' ? 'code' : '' ?>"<?= $autofillEnabled ? ' disabled' : '' ?>><?= $h($block['content'] ?? '') ?></textarea>
              <?php endif; ?>
            </div>
            <?php if ($autofillBlock): ?>
              <div class="autofill-panel__overlay">
                <div class="autofill-panel__overlay-title">Sekce se doplní automaticky</div>
                <div class="autofill-panel__overlay-text">
                  <?= $h($autofillBlock['hint'] ?? 'Kdyz je zapnuto, sekce se pri publikaci doplni z aktualnich dat.') ?>
                </div>
              </div>
              <label class="autofill-panel__toggle">
                <input type="checkbox" name="block_autofill_enabled[<?= $h($block['block_key']) ?>]" value="1" data-autofill-toggle <?= empty($block['autofill_disabled']) ? 'checked' : '' ?>>
                <span>Povolit automaticke doplnovani teto sekce</span>
              </label>
              <div class="autofill-panel__note">Obsah se doplní automaticky z reálných dat a ruční pole je v tomto režimu vypnuté.</div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($isEdit): ?>
    <?php $frontUrl = '/' . ltrim($page['url_path'] ?? '', '/'); ?>
    <?php $previewUrl = $frontUrl . (strpos($frontUrl, '?') === false ? '?' : '&') . 'cms_preview=1'; ?>
    <div style="display:flex;gap:10px;margin-top:4px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary">Uložit změny</button>
      <a href="/admin/seo/edit?page_id=<?= (int)$page['id'] ?>" class="btn btn-secondary">SEO nastaveni</a>
      <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" class="btn btn-secondary">&#128065; Nahled stranky</a>
      <a href="<?= htmlspecialchars($frontUrl) ?>" target="_blank" class="btn btn-inline-edit">&#9998; Editovat inline</a>
      <button type="submit" name="publish_after_save" value="1" class="btn btn-success" onclick="return confirm('Uložit změny a publikovat stranku?')">Publikovat</button>
    </div>
    <?php endif; ?>
  </form>

  <?php if ($isEdit): ?>
  <?php if ($hasPortfolioGallery): ?>
    <div class="tab-pane" id="tab-portfolio-gallery">
      <?php require __DIR__ . '/portfolio-gallery.php'; ?>
    </div>
  <?php endif; ?>
  <form id="sync-form" method="POST" action="/admin/blocks/sync-template">
    <input type="hidden" name="_csrf" value="<?= $h($_csrfToken) ?>">
    <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
  </form>
  <?php if ($layoutType === 'faq'): ?>
  <template id="faq-item-template">
    <div class="card" data-faq-item style="padding:18px">
      <div class="form-row">
        <div class="form-group">
          <label>Otazka</label>
          <input type="text" data-faq-name="question">
        </div>
        <div class="form-group">
          <label>Razeni</label>
          <input type="number" data-faq-name="sort_order" value="0">
        </div>
      </div>
      <div class="form-group">
        <label>Odpoved</label>
        <textarea data-faq-name="answer"></textarea>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="hidden" data-faq-hidden-active value="0">
          <input type="checkbox" data-faq-name="is_active" value="1" checked>
          Aktivni
        </label>
        <button type="button" class="btn btn-sm btn-secondary" data-remove-faq-item>Odebrat</button>
      </div>
    </div>
  </template>
  <?php endif; ?>
  <style>
  .service-media-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:flex-start;justify-content:center;padding:7vh 24px;background:rgba(15,23,42,.62)}
  .service-media-modal.is-open{display:flex}
  .service-media-dialog{width:min(980px,100%);max-height:86vh;overflow:auto;background:var(--c-surface);border-radius:14px;border:1px solid var(--c-border);box-shadow:0 24px 70px rgba(15,23,42,.25);padding:18px}
  .service-media-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:14px}
  .service-media-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
  .service-media-item{border:1px solid var(--c-border);border-radius:10px;background:var(--c-bg);overflow:hidden;text-align:left;cursor:pointer;padding:0}
  .service-media-item:hover{box-shadow:0 10px 24px rgba(15,23,42,.12);border-color:var(--c-primary)}
  .service-media-thumb{height:104px;display:flex;align-items:center;justify-content:center;background:#fff}
  .service-media-thumb img{width:100%;height:100%;object-fit:cover;display:block}
  .service-media-name{padding:8px;font-size:11px;color:var(--c-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .homepage-about-visual-preview{width:min(360px,100%);height:220px;min-height:220px;align-items:center;justify-content:center;overflow:hidden}
  .homepage-about-visual-preview img{width:100%;height:100%;object-fit:cover;display:block}
  .homepage-about-visual-upload{display:grid;grid-template-columns:minmax(220px,1fr) minmax(220px,1fr);gap:12px;align-items:end;padding:14px;border:1px solid var(--c-border);border-radius:8px;background:var(--c-bg)}
  .homepage-about-visual-upload .form-group{margin:0}
  .homepage-about-visual-upload [data-homepage-about-visual-upload-status]{grid-column:1/-1;min-height:17px}
  @media(max-width:720px){.homepage-about-visual-upload{grid-template-columns:1fr}}
  </style>
  <div class="service-media-modal" id="service-media-modal" aria-hidden="true">
    <div class="service-media-dialog" role="dialog" aria-modal="true" aria-label="Vyber media">
      <div class="service-media-head">
        <div>
          <div class="card-title" style="margin:0">Vyber z medii</div>
          <div class="form-hint">Kliknutim vlozis cestu do aktualniho pole nebo obrazek do editoru.</div>
        </div>
        <button type="button" class="btn btn-secondary" data-service-media-close>Zavrit</button>
      </div>
      <?php if (empty($media)): ?>
        <p class="form-hint">Zatím nejsou nahrána žádná media. Nejdrive je nahraj v sekci <a href="/admin/media">Media</a>.</p>
      <?php else: ?>
        <div class="service-media-list">
          <?php foreach ($media as $m): ?>
            <?php
              $url = $mediaUrl($m);
              $thumb = $mediaThumb($m);
              $mime = (string)($m['mime_type'] ?? '');
              $isImage = str_starts_with($mime, 'image/');
            ?>
            <button type="button" class="service-media-item" data-service-media-item data-url="<?= $h($url) ?>" title="<?= $h($m['original_filename'] ?? '') ?>">
              <div class="service-media-thumb">
                <?php if ($isImage): ?>
                  <img src="<?= $h($thumb) ?>" alt="<?= $h($m['alt_text'] ?? $m['original_filename'] ?? '') ?>" loading="lazy">
                <?php else: ?>
                  <span>FILE</span>
                <?php endif; ?>
              </div>
              <div class="service-media-name"><?= $h($m['original_filename'] ?? $url) ?></div>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <script>
  (function(){
    const modal = document.getElementById('service-media-modal');
    if (!modal) return;
    let activeInput = null;
    let activeSelectCallback = null;
    const close = () => {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      activeInput = null;
      activeSelectCallback = null;
    };
    const openModal = () => AdminMediaPicker.open({mode:'single', onConfirm:list => {
      const media = list[0]; if (!media) return;
      if (activeInput) { activeInput.value=media.url; activeInput.dispatchEvent(new Event('input',{bubbles:true})); activeInput.dispatchEvent(new Event('change',{bubbles:true})); }
      if (activeSelectCallback) activeSelectCallback(media.url);
    }});
    document.addEventListener('click', event => {
      const openBtn = event.target.closest('[data-service-media-open]');
      if (openBtn) {
        const item = openBtn.closest('[data-service-item]');
        activeInput = item ? item.querySelector('[data-service-name="' + (openBtn.dataset.targetName || 'icon_url') + '"]') : null;
        activeSelectCallback = null;
        openModal();
      }
      const pageOpenBtn = event.target.closest('[data-page-media-open]');
      if (pageOpenBtn) {
        activeInput = document.getElementById(pageOpenBtn.dataset.targetId || '');
        activeSelectCallback = null;
        openModal();
      }
      const mediaItem = event.target.closest('[data-service-media-item]');
      if (mediaItem && (activeInput || activeSelectCallback)) {
        const url = mediaItem.dataset.url || '';
        if (activeInput) {
          activeInput.value = url;
          activeInput.dispatchEvent(new Event('input', { bubbles: true }));
          activeInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (activeSelectCallback) activeSelectCallback(url);
        close();
      }
      if (event.target.closest('[data-service-media-close]') || event.target === modal) {
        close();
      }
    });
    document.addEventListener('admin:media-picker:open', event => {
      if (!event.detail || typeof event.detail.select !== 'function') return;
      activeInput = null;
      activeSelectCallback = event.detail.select;
      openModal();
    });
    const renderMediaField = field => {
      const input = field.querySelector('[data-media-field-input]');
      const preview = field.querySelector('[data-media-field-preview]');
      const path = field.querySelector('[data-media-field-path]');
      const clearBtn = field.querySelector('[data-media-field-clear]');
      if (!input || !preview || !path) return;
      const value = (input.value || '').trim();
      preview.innerHTML = '';
      if (value) {
        const img = document.createElement('img');
        img.src = value;
        img.alt = '';
        preview.appendChild(img);
      } else {
        const span = document.createElement('span');
        span.textContent = 'Neni vybrano';
        preview.appendChild(span);
      }
      path.textContent = value || 'Zadne medium neni vybrano';
      if (clearBtn) clearBtn.hidden = value === '';
    };
    document.addEventListener('input', event => {
      const input = event.target.closest('[data-media-field-input]');
      if (input) renderMediaField(input.closest('[data-media-field]'));
    });
    document.addEventListener('change', event => {
      const input = event.target.closest('[data-media-field-input]');
      if (input) renderMediaField(input.closest('[data-media-field]'));
    });
    document.addEventListener('click', event => {
      const clearBtn = event.target.closest('[data-media-field-clear]');
      if (!clearBtn || clearBtn.disabled) return;
      const field = clearBtn.closest('[data-media-field]');
      const input = field && field.querySelector('[data-media-field-input]');
      if (!input) return;
      input.value = '';
      renderMediaField(field);
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && modal.classList.contains('is-open')) close();
    });
  })();
  </script>
  <?php if ($isEdit && $isHomepage): ?>
  <script>
  (function(){
    const input = document.getElementById('homepage-about-visual-url');
    const preview = document.querySelector('[data-homepage-about-visual-preview]');
    const path = document.querySelector('[data-homepage-about-visual-path]');
    const openBtn = document.querySelector('[data-homepage-about-visual-open]');
    const clearBtn = document.querySelector('[data-homepage-about-visual-clear]');
    const fileInput = document.querySelector('[data-homepage-about-visual-file]');
    const altInput = document.querySelector('[data-homepage-about-visual-alt]');
    const uploadBtn = document.querySelector('[data-homepage-about-visual-upload]');
    const uploadStatus = document.querySelector('[data-homepage-about-visual-upload-status]');
    const csrfInput = document.querySelector('#page-form input[name="_csrf"]');
    if (!input || !preview || !path || !openBtn || !clearBtn || !fileInput || !altInput || !uploadBtn || !uploadStatus || !csrfInput) return;

    const render = (url) => {
      const value = (url || '').trim();
      preview.innerHTML = '';
      if (value) {
        const img = document.createElement('img');
        img.src = value;
        img.alt = 'Homepage vizuál služby';
        preview.appendChild(img);
      } else {
        const span = document.createElement('span');
        span.textContent = 'Neni vybrano';
        preview.appendChild(span);
      }
      path.textContent = value;
    };

    openBtn.addEventListener('click', () => {
      document.dispatchEvent(new CustomEvent('admin:media-picker:open', {
        detail: {
          select: (url) => {
            input.value = url || '';
            render(url || '');
          }
        }
      }));
    });

    clearBtn.addEventListener('click', () => {
      input.value = '';
      render('');
    });

    uploadBtn.addEventListener('click', async () => {
      const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
      const altText = altInput.value.trim();
      if (!file) {
        uploadStatus.textContent = 'Vyber fotku z pocitace.';
        uploadStatus.style.color = '#dc2626';
        return;
      }
      if (!altText) {
        altInput.focus();
        uploadStatus.textContent = 'ALT text je povinny.';
        uploadStatus.style.color = '#dc2626';
        return;
      }

      const formData = new FormData();
      formData.append('_csrf', csrfInput.value);
      formData.append('alt_text', altText);
      formData.append('title', altText);
      formData.append('images_only', '1');
      formData.append('files[]', file);
      uploadBtn.disabled = true;
      uploadBtn.textContent = 'Nahravam...';
      uploadStatus.textContent = '';

      try {
        const response = await fetch('/admin/media/upload', {method:'POST', body:formData});
        const payload = await response.json();
        const result = Array.isArray(payload.results) ? payload.results[0] : null;
        if (!response.ok || !result || !result.success || !result.url) {
          throw new Error((result && result.error) || payload.error || 'Upload selhal.');
        }
        input.value = result.url;
        render(result.url);
        fileInput.value = '';
        uploadStatus.textContent = 'Fotka byla nahrana a vybrana. Pro pouziti na homepage uloz stranku.';
        uploadStatus.style.color = '#16a34a';
      } catch (error) {
        uploadStatus.textContent = error && error.message ? error.message : 'Upload selhal.';
        uploadStatus.style.color = '#dc2626';
      } finally {
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Nahrat a vybrat fotku';
      }
    });
  })();
  </script>
  <?php endif; ?>
  <?php if ($layoutType === 'services'): ?>
  <template id="service-item-template">
    <div class="card" data-service-item style="padding:18px">
      <div class="form-row">
        <div class="form-group">
          <label>Nazev</label>
          <input type="text" data-service-name="title">
        </div>
        <div class="form-group">
          <label>Razeni</label>
          <input type="number" data-service-name="sort_order" value="0">
        </div>
      </div>
      <div class="form-group">
        <label>Popis</label>
        <textarea data-service-name="description"></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Obrazek / medium</label>
          <div class="media-input-row">
            <input type="text" data-service-name="image_url" placeholder="/media/...">
            <button type="button" class="btn btn-sm btn-secondary" data-service-media-open data-target-name="image_url">Vybrat</button>
          </div>
        </div>
        <div class="form-group">
          <label>Ikona / medium</label>
          <div style="display:flex;gap:8px">
            <input type="text" data-service-name="icon_url" placeholder="/media/..." style="flex:1">
            <button type="button" class="btn btn-sm btn-secondary" data-service-media-open data-target-name="icon_url">Vybrat</button>
          </div>
          <div class="form-hint">Doporuceni: jednoducha SVG/PNG ikona, idealne 96x96 px.</div>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Tlacitko</label>
          <input type="text" data-service-name="button_text" placeholder="Zjistit vice">
        </div>
        <div class="form-group">
          <label>Odkaz tlacitka</label>
          <input type="text" data-service-name="button_url" placeholder="/kontakt">
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
        <label style="display:flex;align-items:center;gap:8px">
          <input type="hidden" data-service-hidden-active value="0">
          <input type="checkbox" data-service-name="is_active" value="1" checked>
          Aktivni
        </label>
        <button type="button" class="btn btn-sm btn-secondary" data-remove-service-item>Odebrat</button>
      </div>
    </div>
  </template>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
