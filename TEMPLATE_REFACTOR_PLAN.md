# Refactoring šablonování: PHTML view systém

Datum: 2026-07-17
Stav: NÁVRH — čeká na schválení, implementace následuje po fázích.

Tento dokument je zdroj pravdy pro refactoring šablonovacího systému. Obsahuje analýzu současného stavu, cílovou architekturu, kontrakty a fázovaný plán. Po dokončení každé fáze se aktualizuje `ARCHITECTURE.md` a tento dokument (stav fáze).

---

## 1. Analýza současného stavu

### 1.1 Odkud se dnes bere HTML

| Zdroj | Co obsahuje | Kde se zpracovává |
| --- | --- | --- |
| `templates.html_source` (DB) | obsahový fragment stránky s `data-block` markery | `PageRenderer::render()` (DOM injektáž bloků) |
| `sites.layout_html` (DB) | legacy site layout s `{page_content}`, `{nav}`, `{site_name}` | `PageRenderer::applyLayout()` (str_replace) |
| `Templates/<key>/layout.html` | theme layout se stejnými placeholdery | `ThemeTemplateRegistry::getLayoutHtmlForPage()` + `applyLayout()` |
| `Templates/<key>/template.json` | manifest: assets, `pages`, per-page `overrides` (layout_html + assets) | `ThemeTemplateRegistry` |
| Heredoc/concat HTML v PHP | celé speciální stránky, sekce, karty, footer, hero | `public/index.php`, `PublishService`, `PortfolioPublishService`, `HomepageContentService`, `ReviewContentService`, `DemoContentSeeder` |

### 1.2 Živý render flow (public/index.php, 3 673 řádků)

`tryServeDatabasePage()` (řádky 82–206) pro CMS stránky:

1. `PageRepository` → `pages`, `BlockRepository` → `content_blocks`, `HomepageContentService::applyFallbacks()` (autofill bloků generuje HTML v PHP), `SeoRepository`
2. `PageRenderer::render()` — DOM injektáž bloků do `templates.html_source`
3. `applyThemeTemplate()` — obalí theme layoutem (`{page_content}`/`{nav}`/`{site_name}`), přidá theme CSS/JS
4. **~20 post-processing průchodů** nad hotovým HTML stringem (regex/DOM chirurgie): `injectHomepageMissingSections`, `injectHomepageThemeDataSources`, `injectCenikPageLayout`, `injectHomepageAboutVisualSource`, `injectAssets`, `injectSeo`, `injectFooterSocials`, `injectSiteLogo`, `injectPageHeroImage`, `injectHomepageHeroImage`, `injectPageHeroVideo`, `injectBodyPageKey`, `injectBreadcrumbTrail`, `sanitizeFrontendHtml`, `injectCmsEditorChrome`, …

Speciální stránky (`tryServeFaqPage`, `tryServeKontaktPage` ~400 ř., `tryServeServicesPage`, `tryServeBlogListingPage`, `tryServeBlogPostPage`, `tryServePortfolioPage`) **nepoužívají templates z DB** — celé `pageContent` skládají heredocem přímo v PHP včetně inline CSS (`$faqCss`) a inline JS, pak projdou `applyThemeTemplate()` + stejnou inject kaskádou.

### 1.3 Publish flow (PublishService, 1 256 řádků)

`publishPage()` **duplikuje celý živý pipeline** — vlastní kopie ~35 privátních metod, které existují i jako globální funkce v `public/index.php`: `injectHomepageMissingSections`, `injectPageHeroVideo`, `buildRichFooterHtml`, `socialIconSvg`, `extractPhoneNumbersFromText`, `enrichContactWithLegacyBlocks`, `normalizeLegacyFrontendMarkup`, `externalizeBodyCssVariables`, … `PortfolioPublishService` má třetí kopii pro portfolio/blog statické výstupy (`buildTilesHtml`, `publicMediaUrl`, `sanitizeHtml`).

### 1.4 Admin integrace

- `admin/` už má **minimální PHTML engine**: `App\Core\View::render()` (extract + require z `admin/views/*.php`, escaping `View::e()`). Vzor pro nový systém existuje.
- `TemplateParser::parseBlocks()` objevuje `data-block`/`data-media` elementy z `html_source` → synchronizace `content_blocks` (PageController).
- Inline editor: `PageRenderer` v edit módu tagne elementy `data-inline-key`/`data-inline-type`; procedurální klíče (`faq:ID:question`, `contact:phone_1`) se generují v heredocích přes `inlineAttrs()`.

### 1.5 Klíčové problémy

1. **Duplikace pipeline** live vs. publish (~50 funkcí ve dvou–třech kopiích) — divergence už nastala (`injectFooterSocials` vs. `injectRichFooter`).
2. **Hardcoded HTML v PHP**: 286 HTML-emitujících míst v `public/index.php`, 110 v `DemoContentSeeder` (103 KB), 47 v `HomepageContentService`; celé stránky FAQ/kontakt/služby/blog/portfolio jen v heredocích.
3. **Post-inject architektura**: stránka se nejdřív složí a pak 20× mutuje regexy — pořadí je křehké, override per stránka je nemožný bez zásahu do core.
4. **Theme hacky v core**: `getJemnaBezovaHomepageCssOverrides()` (~70 ř. CSS v PHP), `injectEditorialGoldOverrides()`, podmínky `theme-bezova-lens-tale`/`theme-elegant-white2?` v core službách; `getSharedComponentCss()` = ~800 řádků CSS v heredocu.
5. **Override mechanismus je jen na assety a layout** (`template.json` → `overrides.<pageKey>.layout_html/assets`), ne na obsahovou strukturu stránky.
6. **Defaultní obsah v PHP** (proces sekce, fallback texty) — porušuje vlastní pravidlo „obsah pochází z DB".
7. **Žádné render testy** — `tests/run.php` (27 testů) pokrývá jen security.txt.

---

## 2. Cílová architektura

### 2.1 Principy

- **Jeden render pipeline** pro živý frontend i publish (`RenderMode: live | publish`, plus flag `editMode`).
- **PHTML views** (čisté PHP šablony, žádná kompilace, žádný Blade syntax) — loopy a podmínky přímo v .phtml.
- **ViewModel providery** připravují data z DB (přes Repositories); **nikdy nevrací HTML**.
- **Override kaskáda**: téma může přepsat kteroukoli view; stránka může přepsat archetyp.
- **Snippety = partials**, renderované samostatně a znovupoužitelné (footer, hero, karty, breadcrumb…).
- Escaping je default; raw výstup explicitní.

### 2.2 Nové komponenty (namespace `App\View`)

```text
app/View/ViewEngine.php     render(view, data): string — ob_start + extract + include
app/View/ViewResolver.php   kaskádová resoluce view souborů (realpath containment)
app/View/ViewContext.php    $this v šabloně: e(), raw(), partial(), block(), inlineAttr(),
                            slot()/endSlot(), layout(), asset(), mediaUrl()
app/View/PageComposer.php   jediný orchestrátor: context → viewmodel → page view →
                            layout view → post-render (assets, SEO, consent, sanitize)
app/ViewModels/*.php        HomepageData, FaqPageData, KontaktPageData, SluzbyPageData,
                            CenikPageData, BlogListingData, BlogPostData,
                            PortfolioListingData, PortfolioDetailData, ReviewsPageData,
                            GenericPageData, NotFoundPageData
```

### 2.3 Struktura view souborů

```text
app/Views/                          ← core defaulty (fallback pro všechna témata)
  layouts/default.phtml             ← <!DOCTYPE>… + sloty (nav, content, footer)
  archetypes/page.phtml             ← generická CMS stránka (renderuje DB html_source)
  archetypes/homepage.phtml
  archetypes/faq.phtml
  archetypes/kontakt.phtml
  archetypes/sluzby.phtml
  archetypes/cenik.phtml
  archetypes/blog-listing.phtml
  archetypes/blog-detail.phtml
  archetypes/portfolio-listing.phtml
  archetypes/portfolio-detail.phtml
  archetypes/reviews.phtml
  archetypes/404.phtml
  partials/nav.phtml, footer.phtml, footer-socials.phtml, logo.phtml,
           hero.phtml, hero-video.phtml, breadcrumb.phtml, cookie-consent.phtml,
           faq-list.phtml, cenik-cards.phtml, blog-card.phtml, portfolio-tile.phtml,
           reviews-list.phtml, contact-form.phtml, contact-info.phtml,
           services-grid.phtml, process-steps.phtml
  assets/shared-components.css      ← přesun z ThemeTemplateRegistry::getSharedComponentCss()

Templates/<theme>/                  ← theme overridy (vyhrává nad core)
  views/layout.phtml                ← náhrada layout.html (legacy layout.html dál podporován)
  views/pages/<page-key>.phtml      ← override konkrétní stránky (např. pages/cenik.phtml)
  views/archetypes/<archetype>.phtml
  views/partials/<name>.phtml
  assets/…                          ← beze změny
  template.json                     ← rozšířený manifest (viz 2.6)
```

### 2.4 Resoluce view (ViewResolver)

Pro stránku s `pageKey` (z `normalizePageKey(url_path)`) a `archetype` (z template/routy):

```text
1. Templates/<aktivní téma>/views/pages/<pageKey>.phtml        ← per-stránka override tématu
2. Templates/<aktivní téma>/views/archetypes/<archetype>.phtml ← per-archetyp override tématu
3. app/Views/pages/<pageKey>.phtml                             ← per-stránka core override
4. app/Views/archetypes/<archetype>.phtml                      ← core default (musí existovat)
```

Partials stejně: `Templates/<theme>/views/partials/<name>.phtml` → `app/Views/partials/<name>.phtml`.

Bezpečnost resoluce: view jméno validované na `[a-z0-9/_-]`, výsledná cesta přes `realpath()` s kontrolou prefixu povolených rootů (`app/Views`, `Templates/<key>/views`). Uživatelský vstup nikdy neresolvuje cestu přímo — `pageKey` pochází z DB `url_path`.

### 2.5 Podoba PHTML (konvence)

```php
<?php /** @var array $faq  viz kontrakt FaqPageData */ ?>
<?= $this->partial('hero', ['title' => $faq['hero']['title'], 'subtitle' => $faq['hero']['subtitle']]) ?>
<section class="fq-wrap"><div class="container"><div class="faq-list">
<?php foreach ($faq['items'] as $item): ?>
  <article class="faq-item">
    <h3 class="faq-q-wrap">
      <button type="button" class="faq-q" aria-expanded="false"<?= $this->inlineAttr('faq:' . $item['id'] . ':question') ?>>
        <span><?= $this->e($item['question']) ?></span>
      </button>
    </h3>
    <div class="faq-a" hidden<?= $this->inlineAttr('faq:' . $item['id'] . ':answer') ?>><?= nl2br($this->e($item['answer'])) ?></div>
  </article>
<?php endforeach; ?>
</div></div></section>
```

- `$this->e()` — jediná cesta pro proměnný text; `$this->raw()` jen pro DB HTML bloky (finálně prochází `sanitizeFrontendHtml`).
- `$this->inlineAttr(key, type)` emituje `data-inline-*` jen v edit módu — inline editor beze změny.
- `$this->block(key, fallbackKeys...)` čte z `content_blocks` mapy v kontextu.
- Žádné DB dotazy ve views — data jen z ViewModelu. Žádné inline `<style>` — CSS patří do theme/shared assets.

### 2.6 Theme manifest v2 (template.json)

Zpětně kompatibilní rozšíření:

```json
{
  "layout_html": "layout.html",          // legacy, dál funguje
  "views_dir": "views",                  // NOVÉ: aktivuje PHTML overridy tématu
  "overrides": {
    "cenik": {
      "view": "views/pages/cenik.phtml", // NOVÉ: obsahový override stránky
      "assets": { "css": ["assets/cenik.css"] }
    }
  }
}
```

Pravidlo priority layoutu: `views/layout.phtml` (pokud existuje) → jinak legacy `layout.html` přes adaptér (`LegacyLayoutView` — provede dnešní `str_replace` placeholderů). Témata se migrují postupně, žádný big-bang.

### 2.7 Flow diagram

```text
 Request (live)                    Publish (admin)
      │                                 │
      ▼                                 ▼
 ┌──────────────────────────────────────────────┐
 │ PageContext (site, page, blocks, seo,        │
 │ pageKey, archetype, RenderMode, editMode)    │
 └──────────────────────┬───────────────────────┘
                        ▼
 ┌──────────────────────────────────────────────┐
 │ ViewModel provider (App\ViewModels\*)        │◀── Repositories (PDO, prepared)
 │ čistá data, žádné HTML                       │
 └──────────────────────┬───────────────────────┘
                        ▼
 ┌──────────────────────────────────────────────┐
 │ ViewEngine + ViewResolver                    │
 │ theme/views → core/views (kaskáda)           │
 │ archetype.phtml → partials → layout.phtml    │
 └──────────────────────┬───────────────────────┘
                        ▼
 ┌──────────────────────────────────────────────┐
 │ PostRender (PageComposer):                   │
 │ assets (FrontendAssetManager), SEO head,     │
 │ body scripts, consent, tracking, sanitize    │
 └──────────┬───────────────────────┬───────────┘
            ▼                       ▼
   live: + editor chrome     publish: zápis do
   echo HTML                 storage/published/…
```

### 2.8 Co se stane se stávajícími komponentami

| Komponenta | Osud |
| --- | --- |
| `PageRenderer::render()` (DOM bloky) | zůstává jako „legacy fragment renderer" pro `templates.html_source` uvnitř archetypu `page.phtml`; dlouhodobě deprecated |
| `PageRenderer::applyLayout()` | přesun do `LegacyLayoutView` adaptéru |
| `applyThemeTemplate()` + duplicity v PublishService | nahrazuje `PageComposer` |
| `inject*` funkce (hero, logo, footer, breadcrumb, sekce) | postupně nahrazeny partials/sloty v layoutu; SEO/assets/consent injektory zůstávají v PostRender kroku (pracují s `<head>`/`</body>`) |
| `HomepageContentService` | přepis na `HomepageData` — autofill vrací data (pole služeb, článků, cen, FAQ), ne HTML |
| `ThemeTemplateRegistry` | zůstává (manifesty, assets, aktivní téma); ztrácí CSS heredocy a theme-specific hacky |
| `TemplateParser` | zůstává pro legacy DB šablony; nové archetypy deklarují editovatelné klíče v manifestu archetypu |
| `PortfolioPublishService` | render část nahrazena PageComposer + archetype views; zůstává orchestrace souborů |
| `templates.html_source` | žije pro generické CMS stránky; speciální stránky ho přestanou potřebovat |

---

## 3. Integrační body a dopady

| Oblast | Dopad | Riziko |
| --- | --- | --- |
| **Frontend (live)** | `tryServe*` funkce se zredukují na: sestavit PageContext → `PageComposer::compose()` → echo | vysoké — pokrýt golden testy |
| **Publish** | `PublishService::publishPage/publishSite` volá stejný composer v publish módu; výstup do `storage/published/` byte-ekvivalentní | vysoké — golden testy povinné |
| **Admin – inline editor** | `inlineAttr()` helper generuje stejné `data-inline-*`; `admin/inline/update` beze změny | střední |
| **Admin – editace šablon** | `templates.html_source` editor zůstává pro generické stránky; u refaktorovaných archetypů admin edituje bloky/entity, ne HTML | nízké |
| **Témata** | legacy `layout.html` dál funguje přes adaptér; nová témata používají `views/` | nízké |
| **Média** | beze změny (`/media/...` root-relative, `mediaUrl()` helper obaluje `publicMediaUrl`) | nízké |
| **Seed/Demo parita** | `DemoStateFingerprintService` porovnává DB stav — nemění se; publish výstup se změní jen schválenými diffy | střední |
| **API** | `ApiController` negeneruje stránkové HTML — bez dopadu | žádné |

### Refaktorované stránky/archetypy

homepage, generická CMS stránka (`page`), `sluzby`, `cenik`, `faq`, `kontakt`, `blog-listing`, `blog-detail` (přímé slugy), `portfolio-listing`, `portfolio-detail`, `reviews`/`hodnoceni`, `404`, statické publish výstupy týchž stránek. Mimo scope: sitemap/robots/llms/security.txt (nejsou HTML šablony), GTM, API.

---

## 4. Plán implementace po fázích

Každá fáze = samostatný commit `refactor(templates): …` (fáze 0 `test(templates): …`), vlastní dopadová analýza (admin/frontend/publish/šablony/média), spuštění `php tests/run.php`, golden porovnání a vizuální QA (visual-screenshot-score-loop, viewporty 390×844 a 1920×1080 dle `qa-testing.md`).

### Fáze 0 — Záchranná síť (PRVNÍ, bez ní se nerefaktoruje)
- Golden-master testy: vyrenderovat všechny veřejné routy (`/`, `/sluzby`, `/cenik`, `/faq`, `/kontakt`, `/blog`, `/nase-prace`, 1× blog detail, 1× portfolio detail, `/hodnoceni`, generická stránka, 404) přes stávající pipeline a uložit normalizované HTML (strip publish timestamp, asset hashe) do `tests/goldens/`.
- Totéž pro publish výstup (`storage/published/**`).
- Rozšířit `tests/run.php` o golden runner s režimem `--update-goldens`.
- Výstup: každý další krok se měří proti byte-diffu.

### Fáze 1 — View engine core (žádná změna chování)
- `App\View\ViewEngine`, `ViewResolver`, `ViewContext` + helpery, `LegacyLayoutView`.
- Unit testy: escaping, kaskáda resoluce, realpath containment, slot/partial mechanika.
- Nic ho zatím nevolá — bezpečný merge.

### Fáze 2 — Sjednocení pipeline (největší přínos, ještě bez PHTML obsahu)
- Extrakce duplikovaných funkcí z `public/index.php` + `PublishService` do sdílených služeb `App\Services\Render\*` (postupně: footer/socials, hero media, telefonní/kontakt normalizace, legacy markup normalizace, SEO/asset injektory).
- `PageComposer` s módy live/publish; `tryServeDatabasePage` i `publishPage` volají composer.
- Kritérium: golden diff prázdný (nebo explicitně schválené rozdíly).

### Fáze 3 — Layout a chrome do PHTML
- `app/Views/layouts/default.phtml` + partials `nav`, `footer`, `footer-socials`, `logo`, `breadcrumb`, `cookie-consent`, `hero`, `hero-video`.
- Layout renderuje sloty přímo → odstraní se odpovídající `inject*` regex chirurgie.
- Přesun `getSharedComponentCss()` do `app/Views/assets/shared-components.css`.
- Theme layouty: adaptér pro `layout.html`; `elegant-white3` (aktivní) dostane `views/layout.phtml` jako referenční implementaci.

### Fáze 4 — Speciální archetypy do PHTML (jeden archetyp = jeden commit)
Pořadí podle rizika (od nejjednodušší): **faq → sluzby → cenik → reviews → blog-listing → blog-detail → portfolio-listing → portfolio-detail → kontakt** (kontakt poslední — formulář, CSRF, honeypot, throttling).
Pro každý: ViewModel provider + `archetypes/<x>.phtml` + partials; smazat heredoc větev z `public/index.php`; publish větev přes tentýž composer; inline editor klíče zachovány; golden + vizuální QA.

### Fáze 5 — Homepage a generické stránky
- `HomepageData` (autofill jako data) + `archetypes/homepage.phtml` se sekcemi (services, portfolio, pricing, blog, faq, process, kontakt) → nahradí `injectHomepageMissingSections`/`injectHomepageThemeDataSources`/`injectCenikPageLayout`/`buildCenikPackageCardsHtml`.
- Defaultní obsah (proces kroky) přesunout z PHP do seedu/DB (nová migrace-datová? ne — seed bloky; schema se nemění).
- `archetypes/page.phtml` obaluje legacy `PageRenderer` fragment pro generické stránky (data-block editace beze změny).

### Fáze 6 — Theme overridy a úklid
- Manifest v2 (`views_dir`, `overrides.<pageKey>.view`); dokumentace v `ARCHITECTURE.md`.
- Migrace theme hacků z core do témat: `getJemnaBezovaHomepageCssOverrides` → `Templates/jemna-bezova/assets/homepage-overrides.css`, `injectEditorialGoldOverrides` → theme assets, odstranit `theme-*` podmínky z core.
- Smazat mrtvé `inject*`/`build*` funkce a duplicitní kopie; `DemoContentSeeder` HTML zjednodušit na data (volitelný dovětek).
- Aktualizace `ARCHITECTURE.md`, `INSTRUCTIONS.md` (pravidla pro views), případně `README.md`.

---

## 5. Kontrakty ViewModelů (podklad pro implementaci)

Společný obal pro každou stránku (`PageContext::viewData()`):

```php
[
  'site'   => [ 'id', 'name', 'domain', 'logo_url', 'favicon_*', 'active_theme_key', ... ],
  'page'   => [ 'id', 'title', 'url_path', 'page_key', 'archetype', 'is_home' ],
  'blocks' => [ 'block_key' => ['content','type','label'], ... ],   // mapová forma
  'seo'    => [ 'title', 'description', 'canonical', 'og_image_url', 'twitter_image_url', 'json_ld' => [...] ],
  'nav'    => [ 'html' /* legacy */ | 'items' => [['label','url','active']] ],
  'hero'   => [ 'title', 'subtitle', 'image_url', 'video_url', 'poster_url', 'overlay_opacity' ],
  'breadcrumbs' => [ ['label','url'], ... ],
  'mode'   => [ 'live'|'publish', 'edit' => bool ],
]
```

Archetypové klíče (nad rámec společného obalu):

| Archetyp | Klíč | Obsah |
| --- | --- | --- |
| faq | `faq.items` | `[['id','question','answer']]` z `faq_items` (allActive) |
| sluzby | `services.items` | `[['id','title','description','image_url','url']]` ze `service_items` |
| cenik | `pricing.packages` | `[['title','price','summary','features'=>[]]]` z bloků `cenik_card_*` |
| kontakt | `contact` | `contact_info` + normalizované telefony, socials, form config (CSRF token, honeypot) |
| blog-listing | `posts` | `[['id','title','slug','excerpt','image_url','published_at']]` |
| blog-detail | `post`, `gallery` | `blog_posts` + `blog_media` |
| portfolio-listing | `entries` | `portfolio_entries` + cover média |
| portfolio-detail | `entry`, `gallery` | + `portfolio_media` |
| reviews | `reviews.items` | `reviews` (aktivní, řazené) |
| homepage | `sections.{services,portfolio,pricing,blog,faq,process,reviews,contact}` | data z příslušných tabulek (dnešní autofill) |
| page | `legacy_fragment` | HTML z `PageRenderer::render()` (raw) |

Pravidla:
- Provider vrací pouze scalary/pole; URL médií už přeložené na `/media/...` root-relative.
- Fallbacky obsahu řeší provider (DB → prázdný stav), ne view; view řeší jen prázdné stavy vizuálně.
- Procedurální inline klíče (`faq:ID:question`) jsou součástí kontraktu — view je emituje přes `inlineAttr()`.

## 6. Bezpečnostní kontrakt view vrstvy

- `ViewResolver`: `realpath()` + prefix check na povolené rooty; view name regex `^[a-z0-9][a-z0-9/_-]*$`; žádná resoluce z user inputu.
- Escaping default (`e()` = `htmlspecialchars ENT_QUOTES|ENT_SUBSTITUTE, UTF-8`); `raw()` povolen jen pro DB HTML bloky, výstup vždy projde `sanitizeFrontendHtml`.
- Formuláře ve views: CSRF token z kontraktu, ne generovaný ve view; honeypot a throttling zůstávají v controlleru/routě.
- DB výhradně přes Repositories s prepared statements; ve views žádné `new PDO`, žádné queries (vynutit code-review pravidlem v INSTRUCTIONS.md ve fázi 6).

## 7. Rizika a mitigace

| Riziko | Mitigace |
| --- | --- |
| Byte-diff publish výstupu rozbije paritu/fingerprint | golden testy od fáze 0; fingerprint porovnává DB, ne HTML — ověřit po každé fázi |
| Divergence live vs. publish během přechodu | fáze 2 sjednocuje pipeline PŘED přepisem obsahu do PHTML |
| Rozbití inline editoru | `inlineAttr()` kontraktní test + ruční QA editace na každém archetypu ve fázi 4 |
| Theme regresní vzhled | vizuální QA loop na 390×844 a 1920×1080 po každé fázi; aktivní téma `elegant-white3` jako referenční |
| Rozsah (3 673 ř. index.php) | archetyp = samostatný commit; heredoc větev se maže až po zeleném goldenu |
