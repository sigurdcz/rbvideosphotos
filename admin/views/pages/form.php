<?php
$isEdit = $page !== null;
$pageTitle = $isEdit ? 'Upravit stranku' : 'Nova stranka';
$h = fn($v) => htmlspecialchars((string)$v);
$layoutType = $layoutType ?? 'generic';
$templateName = $template['name'] ?? '';
$socialOptions = ['instagram','facebook','youtube','tiktok','linkedin','twitter','pinterest','whatsapp','telegram'];
$blockMap = [];
$homepageAutofillBlocks = $homepageAutofillBlocks ?? [];
$isHomepage = !empty($isHomepage);

foreach (($blocks ?? []) as $block) {
    $blockMap[$block['block_key']] = $block;
}

$renderBlockField = function (string $key, string $label, string $type = 'text', string $hint = '') use ($blockMap, $h): void {
    $block = $blockMap[$key] ?? ['content' => '', 'block_type' => $type];
    $value = $block['content'] ?? '';
    $inputType = $type === 'html' ? 'html' : ($block['block_type'] ?? $type);
    ?>
    <div class="form-group">
      <label><?= $h($label) ?></label>
      <input type="hidden" name="block_key[]" value="<?= $h($key) ?>">
      <?php if (in_array($inputType, ['text', 'link'], true)): ?>
        <input type="text" name="block_content[]" value="<?= $h($value) ?>">
      <?php elseif ($inputType === 'image'): ?>
        <input type="text" name="block_content[]" value="<?= $h($value) ?>" placeholder="/storage/media/...">
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
        <button type="button" class="tab-btn" data-tab="services-grid">Sluzby</button>
      <?php else: ?>
        <button type="button" class="tab-btn" data-tab="blocks">Bloky obsahu <?= !empty($blocks) ? '(' . count($blocks) . ')' : '' ?></button>
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
                <input type="text" name="faq_items[<?= $index ?>][question]" value="<?= $h($item['question'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Razeni</label>
                <input type="number" name="faq_items[<?= $index ?>][sort_order]" value="<?= (int)($item['sort_order'] ?? ($index + 1)) ?>">
              </div>
            </div>
            <div class="form-group">
              <label>Odpoved</label>
              <textarea name="faq_items[<?= $index ?>][answer]"><?= $h($item['answer'] ?? '') ?></textarea>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
              <label style="display:flex;align-items:center;gap:8px">
                <input type="hidden" name="faq_items[<?= $index ?>][is_active]" value="0">
                <input type="checkbox" name="faq_items[<?= $index ?>][is_active]" value="1" <?= !empty($item['is_active']) ? 'checked' : '' ?>>
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
    <div class="tab-pane" id="tab-services-grid">
      <div class="card">
        <?php $renderBlockField('sluzby_grid', 'Obsah sluzeb', 'html', 'Sem patri strukturovane karty nebo HTML obsah sluzeb pro tuto stranku.'); ?>
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
          <div class="block-item">
            <div class="block-label">
              <?= $h($block['label'] ?? $block['block_key']) ?>
              <span class="block-type-badge"><?= $h($block['block_type']) ?></span>
            </div>
            <?php $autofillBlock = $isHomepage ? ($homepageAutofillBlocks[$block['block_key']] ?? null) : null; ?>
            <input type="hidden" name="block_key[]" value="<?= $h($block['block_key']) ?>">
            <?php if (in_array($block['block_type'], ['text','link'], true)): ?>
              <input type="text" name="block_content[]" value="<?= $h($block['content'] ?? '') ?>">
            <?php elseif ($block['block_type'] === 'image'): ?>
              <input type="text" name="block_content[]" value="<?= $h($block['content'] ?? '') ?>" placeholder="/storage/media/...">
              <div class="form-hint">URL nebo cesta k obrazku</div>
            <?php else: ?>
              <textarea name="block_content[]" class="<?= $block['block_type'] === 'html' ? 'code' : '' ?>"><?= $h($block['content'] ?? '') ?></textarea>
            <?php endif; ?>
            <?php if ($autofillBlock): ?>
              <label style="display:flex;align-items:flex-start;gap:8px;margin-top:10px;font-size:.9rem;color:var(--c-muted)">
                <input type="checkbox" name="block_autofill_disabled[<?= $h($block['block_key']) ?>]" value="1" <?= !empty($block['autofill_disabled']) ? 'checked' : '' ?>>
                <span>
                  Vypnout automaticke doplnovani teto sekce
                  <span style="display:block;margin-top:4px;font-size:.82rem;line-height:1.45">
                    <?= $h($autofillBlock['hint'] ?? 'Po vypnuti zustane pole prazdne i pri publikaci.') ?>
                  </span>
                </span>
              </label>
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
  <?php endif; ?>
</div>
<?php
$slot = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
