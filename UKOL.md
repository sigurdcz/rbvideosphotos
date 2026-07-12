# UKOL: Nova sablona bezova-lens-tale

Datum: 2026-07-12
Zadani: Z dodaneho multipage designu Lens & Tale vytvorit novou instalovatelnou CMS sablonu `bezova-lens-tale`, ktera zachova bezovy editorialni vzhled a pouzije skutecny DB-driven obsah SEO Editoru.

## Kroky

- [x] Prozkoumat zdrojovy design, assety a kontrakt existujicich CMS temat
- [x] Navrhnout mapovani stranek a placeholderu bez kopirovani obsahovych dat do sablony
- [x] Vytvorit `Templates/bezova-lens-tale/` vcetne manifestu, layoutu a assetu
- [x] Overit registry/manifest, syntax, odkazy a kompatibilitu se specialnimi CMS strankami
- [x] Spustit lokalni runtime kontrolu desktop/mobile pravidel a opravit nalezy
- [x] Aktualizovat relevantni dokumentaci a provest Wiki Reflection

## Poznamky

Zdrojovy balicek v Downloads slouzi pouze jako designova reference a nebude prepisovan. Hotova sablona musi zit v `Templates/bezova-lens-tale/` a nesmi vlastnit obsah, ktery patri do databaze.

Vytvoren manifest, DB-neutralni layout, responzivni CSS a drobna JS vrstva. Filmovy pas je kresleny v CSS bez obsahovych fotografii; `{site_name}`, `{nav}` a `{page_content}` zustavaji jedinymi obsahovymi vstupy layoutu.

`README.md` nyni uvadi nove tema v seznamu dostupnych sablon. `ARCHITECTURE.md` zmenu nepotrebuje, protoze obecny kontrakt temat zustal beze zmeny.

CSS bylo doplneno o skutecne tridy rendereru (`hp-*`, `pt-*`, `ckp-*`, `bl-*`, `kt-*`), aby se vzhled aplikoval take na DB generovane homepage, portfolio, blog, cenik a kontakt.

Registry smoke test nacetl tema i vsechny tri assety. Lokalni HTTP preview vratil 200, obsahoval `theme-bezova-lens-tale` a `blt-film`; hashovany theme CSS asset vratil 200 a obsahoval novou paletu. Vestaveny vizualni prohlizec se v tomto behu nepripojil kvuli chybejicimu sandbox metadata poli, proto nebylo mozne poridit skutecny desktop/mobile screenshot; responzivni breakpointy byly overeny staticky a runtime pres HTTP.

`php tests/run.php` prosel 18/18, `git diff --check` bez obsahovych chyb. Wiki Reflection: domeny architecture/pipelines/tools jsou v dossieru SEO Editor jiz `ok` a obecny postup DB-first temat vcetne preview i responzivni kontroly je pokryt; verdikt SKIP.

## Vysledek

Dokoncena instalovatelna sablona `bezova-lens-tale` s manifestem, DB-neutralnim layoutem, responzivnim vzhledem a interakcemi. Aktualizovan `README.md`; `ARCHITECTURE.md` zustava platny beze zmeny.
