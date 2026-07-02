<?php
$isEdit = $page !== null;
$pageTitle = $isEdit ? 'Upravit stranku' : 'Nova stranka';
$h = fn($v) => htmlspecialchars((string)$v);
$layoutType = $layoutType ?? 'generic';
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
$mediaUrl = fn(array $m): string => '/media/' . ltrim(preg_replace('~^storage/media/~', '', str_replace('\\', '/', (string)($m['storage_path'] ?? ''))) ?? '', '/');
$mediaThumb = function(array $m) use ($mediaUrl): string {
    foreach (($m['variants'] ?? []) as $variant) {
        if (($variant['format'] ?? '') === 'thumb' && !empty($variant['path'])) {
            return '/' . ltrim((string)$variant['path'], '/');
        }
    }
    return $mediaUrl($m);
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
$renderBlockField = function (string $key, string $label, string $type = 'text', string $hint = '') use ($blockMap, $h, &$renderedBlockKeys): void {
    if (isset($renderedBlockKeys[$key])) {
        return;
    }
    $renderedBlockKeys[$key] = true;
    $block = $blockMap[$key] ?? ['content' => '', 'block_type' => $type];
    $value = $block['content'] ?? '';
    $inputType = $key === 'hero_video'
      ? 'video'
      : ($type === 'html' ? 'html' : ($block['block_type'] ?? $type));
    ?>
    <div class="form-group">
      <label><?= $h($label) ?></label>
      <input type="hidden" name="block_key[]" value="<?= $h($key) ?>">
      <?php if (in_array($inputType, ['text', 'link'], true)): ?>
        <input type="text" name="block_content[]" value="<?= $h($value) ?>">
      <?php elseif ($inputType === 'image'): ?>
        <input type="text" name="block_content[]" value="<?= $h($value) ?>" placeholder="/media/...">
      <?php elseif ($inputType === 'video'): ?>
        <input type="url" name="block_content[]" value="<?= $h($value) ?>" placeholder="https://... nebo /media/...">
      <?php else: ?>
        <textarea name="block_content[]" class="<?= $inputType === 'html' ? 'code' : '' ?>"><?= $h($value) ?></textarea>
      <?php endif; ?>
      <?php if ($hint !== ''): ?>
        <div class="form-hint"><?= $h($hint) ?></div>
      <?php endif; ?>
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
        <button type="button" class="tab-btn" data-tab="pricing-1">Karta 1</button>
        <button type="button" class="tab-btn" data-tab="pricing-2">Karta 2</button>
        <button type="button" class="tab-btn" data-tab="pricing-3">Karta 3</button>
      <?php elseif ($layoutType === 'services'): ?>
        <button type="button" class="tab-btn" data-tab="page-content">Obsah stranky</button>
        <button type="button" class="tab-btn" data-tab="services-items">Polozky sluzeb</button>
        <button type="button" class="tab-btn" data-tab="services-grid">Sluzby</button>
      <?php else: ?>
        <button type="button" class="tab-btn" data-tab="blocks">Bloky obsahu <?= !empty($visibleBlocks) ? '(' . count($visibleBlocks) . ')' : '' ?></button>
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
          Kdyz necelate prazdne obe, stranka pouzije vychozi hero video ze sekce Nastaveni.
        </p>
        <?php $renderBlockField('hero_video', 'Hero video — Vimeo / YouTube URL', 'link', 'Napr. https://vimeo.com/123456789, https://youtu.be/... nebo https://www.youtube.com/watch?v=...'); ?>
        <?php $renderBlockField('hero_bg', 'Hero foto — URL nebo /media/...', 'image', 'Pouzije se jako pozadi hero sekce, kdyz neni vyplnene hero video.'); ?>
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
        <p class="form-hint" id="faq-empty-state">Zatim tu nejsou zadne otazky. Pridate je tlacitkem nahore.</p>
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
        <div class="card-title">Kontaktni formular</div>
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
        <?php $renderBlockField('hero_h1', 'Hero nadpis'); ?>
        <?php $renderBlockField('hero_sub', 'Hero intro', 'textarea'); ?>
        <?php $renderBlockField('hero_bg', 'Hero obrazek / medium', 'image'); ?>
        <?php $renderBlockField('cta_wrap', 'Spodni CTA', 'html'); ?>
      </div>
    </div>
    <div class="tab-pane" id="tab-pricing-1"><div class="card"><?php $renderBlockField('cenik_card_1_title', 'Nadpis karty'); ?><?php $renderBlockField('cenik_card_1_desc', 'Popis', 'textarea'); ?><?php $renderBlockField('cenik_card_1_price', 'Cena / paticka'); ?></div></div>
    <div class="tab-pane" id="tab-pricing-2"><div class="card"><?php $renderBlockField('cenik_card_2_title', 'Nadpis karty'); ?><?php $renderBlockField('cenik_card_2_desc', 'Popis', 'textarea'); ?><?php $renderBlockField('cenik_card_2_price', 'Cena / paticka'); ?></div></div>
    <div class="tab-pane" id="tab-pricing-3"><div class="card"><?php $renderBlockField('cenik_card_3_title', 'Nadpis karty'); ?><?php $renderBlockField('cenik_card_3_desc', 'Popis', 'textarea'); ?><?php $renderBlockField('cenik_card_3_price', 'Cena / paticka'); ?></div></div>
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
                <label>Obrazek / URL</label>
                <input type="text" data-service-name="image_url" value="<?= $h($item['image_url'] ?? '') ?>" placeholder="/media/...">
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

        <?php if (empty($serviceItems)): ?>
        <p class="form-hint" id="service-empty-state">Zatim tu nejsou zadne sluzby. Pridate je tlacitkem nahore.</p>
        <?php endif; ?>
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
                <input type="text" name="block_content[]" value="<?= $h($block['content'] ?? '') ?>" placeholder="/media/..."<?= $autofillEnabled ? ' disabled' : '' ?>>
                <div class="form-hint">URL nebo cesta k obrazku</div>
              <?php elseif (($block['block_type'] === 'video') || $block['block_key'] === 'hero_video'): ?>
                <input type="url" name="block_content[]" value="<?= $h($block['content'] ?? '') ?>" placeholder="https://... nebo /media/..."<?= $autofillEnabled ? ' disabled' : '' ?>>
                <div class="form-hint">URL videa pro pozadí v hero sekci homepage.</div>
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
      <button type="submit" class="btn btn-primary">Ulozit zmeny</button>
      <a href="/admin/seo/edit?page_id=<?= (int)$page['id'] ?>" class="btn btn-secondary">SEO nastaveni</a>
      <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" class="btn btn-secondary">&#128065; Nahled stranky</a>
      <a href="<?= htmlspecialchars($frontUrl) ?>" target="_blank" class="btn btn-inline-edit">&#9998; Editovat inline</a>
      <button type="submit" name="publish_after_save" value="1" class="btn btn-success" onclick="return confirm('Ulozit zmeny a publikovat stranku?')">Publikovat</button>
    </div>
    <?php endif; ?>
  </form>

  <?php if ($isEdit): ?>
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
  </style>
  <div class="service-media-modal" id="service-media-modal" aria-hidden="true">
    <div class="service-media-dialog" role="dialog" aria-modal="true" aria-label="Vyber media">
      <div class="service-media-head">
        <div>
          <div class="card-title" style="margin:0">Vyber ikonu z medii</div>
          <div class="form-hint">Kliknutim se cesta vlozi do aktualni sluzby.</div>
        </div>
        <button type="button" class="btn btn-secondary" data-service-media-close>Zavrit</button>
      </div>
      <?php if (empty($media)): ?>
        <p class="form-hint">Zatim nejsou nahrana zadna media. Nejdrive je nahraj v sekci <a href="/admin/media">Media</a>.</p>
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
    const close = () => {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      activeInput = null;
    };
    document.addEventListener('click', event => {
      const openBtn = event.target.closest('[data-service-media-open]');
      if (openBtn) {
        const item = openBtn.closest('[data-service-item]');
        activeInput = item ? item.querySelector('[data-service-name="' + (openBtn.dataset.targetName || 'icon_url') + '"]') : null;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
      }
      const mediaItem = event.target.closest('[data-service-media-item]');
      if (mediaItem && activeInput) {
        activeInput.value = mediaItem.dataset.url || '';
        close();
      }
      if (event.target.closest('[data-service-media-close]') || event.target === modal) {
        close();
      }
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && modal.classList.contains('is-open')) close();
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
          <label>Obrazek / URL</label>
          <input type="text" data-service-name="image_url" placeholder="/media/...">
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
