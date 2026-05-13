<?php
$isEdit = $page !== null;
$pageTitle = $isEdit ? 'Upravit stranku' : 'Nova stranka';
$h = fn($v) => htmlspecialchars((string)$v);
$layoutType = $layoutType ?? 'generic';
$templateName = $template['name'] ?? '';
$socialOptions = ['instagram','facebook','youtube','tiktok','linkedin','twitter','pinterest','whatsapp','telegram'];
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

$renderBlockField = function (string $key, string $label, string $type = 'text', string $hint = '') use ($blockMap, $h): void {
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
        <input type="text" name="block_content[]" value="<?= $h($value) ?>" placeholder="/storage/media/...">
      <?php elseif ($inputType === 'video'): ?>
        <input type="url" name="block_content[]" value="<?= $h($value) ?>" placeholder="https://... nebo /storage/media/...">
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
        <p class="form-hint" style="margin-bottom:18px">Vyplnte az 5 socialnich siti. Typ urcuje nazev odkazu na webu.</p>
        <?php for ($i = 1; $i <= 5; $i++): ?>
        <div class="form-row" style="align-items:flex-end;margin-bottom:16px">
          <div class="form-group" style="flex:0 0 180px">
            <label>Sit <?= $i ?></label>
            <select name="social_<?= $i ?>_type">
              <option value="">- nevyplneno -</option>
              <?php foreach ($socialOptions as $social): ?>
              <option value="<?= $h($social) ?>" <?= ($contact['social_' . $i . '_type'] ?? '') === $social ? 'selected' : '' ?>>
                <?= ucfirst($h($social)) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="flex:1">
            <label>URL profilu</label>
            <input type="url" name="social_<?= $i ?>_url" value="<?= $h($contact['social_' . $i . '_url'] ?? '') ?>" placeholder="https://...">
          </div>
        </div>
        <?php endfor; ?>
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
                <input type="text" data-service-name="image_url" value="<?= $h($item['image_url'] ?? '') ?>" placeholder="/storage/media/...">
              </div>
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
                <input type="text" name="block_content[]" value="<?= $h($block['content'] ?? '') ?>" placeholder="/storage/media/..."<?= $autofillEnabled ? ' disabled' : '' ?>>
                <div class="form-hint">URL nebo cesta k obrazku</div>
              <?php elseif (($block['block_type'] === 'video') || $block['block_key'] === 'hero_video'): ?>
                <input type="url" name="block_content[]" value="<?= $h($block['content'] ?? '') ?>" placeholder="https://... nebo /storage/media/..."<?= $autofillEnabled ? ' disabled' : '' ?>>
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
          <input type="text" data-service-name="image_url" placeholder="/storage/media/...">
        </div>
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
