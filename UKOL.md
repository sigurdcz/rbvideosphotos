# UKOL: Elegant White — opravy homepage

Datum: 2026-07-10
Zadani: Opravit pouze homepage sablony Elegant White podle aktualnich screenshotu: sjednotit defaultni font podle nav odkazu Kontakt, roztahnout hero video pres celou sekci, posunout About fotografii nahoru, zarovnat portfolio nahledy, zobrazit dalsi DB FAQ, nahradit proces variantou z Kontakt stranky a zvysit kvalitu hero obrazku.

Hotovo kdyz: homepage pouziva jednotny font; hero media vyplnuji celou sirku bez praveho omezeni; About foto preferuje horni cast; portfolio karty maji zarovnane nahledy bez ohledu na delku textu; homepage FAQ zobrazuje o jednu existujici DB polozku vic; proces vizualne odpovida kontaktni strance; hero obrazky nepouzivaji zbytecne thumbnail/low-quality varianty; desktop i mobil nemaji horizontalni preteceni.

## Kroky

- [x] Overit aktualni produkcni DOM, computed styly a DB-driven zdroje homepage
- [x] Sjednotit typografii a opravit hero video pres celou sirku
- [x] Opravit About fotografii a zarovnani portfolio karet
- [x] Zobrazit dalsi existujici FAQ a prevzit procesni kompozici z Kontakt stranky
- [x] Opravit kvalitu hero medii bez vkladani dat do sablony
- [x] Overit desktop, mobil, interakce, syntax a diff
- [x] Aktualizovat relevantni dokumentaci/wiki a uzavrit vysledek

## Poznamky

- Rozsah je pouze homepage; ostatni stranky se budou resit pozdeji.
- Obsah zustava v DB. Novy FAQ text se nebude vkladat do theme souboru; homepage pouze zobrazi dalsi existujici FAQ polozku.
- Pracovni strom byl pri startu cisty.
- `theme.js`: FAQ umi sloucit ctvrty DB zdroj bez duplicit a proces dostava stejne strukturalni tridy jako kontaktni ctyrkrokova sekce.
- `theme.css`: vsechny textove prvky pouzivaji Manrope, homepage hero zacina na levem okraji a ma plnou sirku, hero filtry a 4% zvetseni jsou odstraneny, About foto kotvi nahore a portfolio thumb ma `margin-top:auto`.
- `theme.css`: homepage proces pouziva ctyrsloupcovou linku, kruhova cisla a mobilni jednosloupcovou variantu podle Kontakt stranky.
- Runtime a publish renderer vkladaji skryty `faq_4` datovy zdroj z DB; Elegant White jej presune do homepage FAQ. FAQ synchronizace i inline povoleni byly rozsirené ze 3 na 4 polozky.
- Aktualni hero soubory na vnitrnich stranach maji sirku 1400 px a homepage poster 1920 px. CSS uz je dale nezesvetluje ani nezvetsuje; vyssi skutecny detail nad zdrojova data nelze vyrobit bez noveho uploadu.
- Lokalni DB ma starsi homepage bez cele FAQ sekce a s peti procesnimi kroky. Renderer proto umi FAQ sekci slozit ze ctyr DB bloku a Elegant White omezuje kontaktni procesni kompozici na prvni ctyri kroky.
- Prvni mobilni computed-style kontrola ukazala, ze starsi specificita drzela Cormorant na H1; finalni heading selektory byly proto zesileny na stejnou specificitu a zustava Manrope i na nadpisech.
- Produkcni DOM test odhalil jeste vyssi homepage specificitu pro nav weight a hero filter; finalni pravidla proto obsahuji stejny `data-cms-page-key="homepage"` scope a skutecne prebiji stare 900/filtr hodnoty.
- Doplnovana FAQ sekce pouziva DB `faq_title`; pouze strukturální štítek má český fallback „Otázky“ pro starou šablonu bez celé sekce.
- Overeni: PHP syntax vsech zmenenych PHP souboru prosla, `git diff --check` proslo, lokalni Chrome ma cistou konzoli, FAQ akordeon se otevira a desktop i mobil maji nulove horizontalni preteceni.
- Produkcni DOM s lokalnim finalnim CSS: hero media `x=0`, sirka odpovida viewportu, nav i Kontakt maji Manrope 11px/600, About ma `object-position: 50% 0%` a vsechny portfolio thumby maji stejny dolni okraj.
- `node --check` nebylo mozne spustit, protoze Node.js neni nainstalovan; ekvivalentni parse/runtime kontrola probehla v Chrome bez JavaScript chyb.
- README ani architekturni dokumenty nepotrebovaly zmenu; pravidla DB/theme kontraktu byla doplnena do existujici wiki stranky a commitnuta ve wiki repozitari jako `898079f`.
- FAQ datovy most i doplneni chybejici sekce jsou explicitne omezeny na `theme-elegant-white`, aby zmena homepage neovlivnila ostatni sablony.

## Vysledek

Homepage Elegant White pouziva jednotny Manrope, plnosirkove hero video, ostrejsi nefiltrovana hero media, horni orez About fotografie, dole zarovnane portfolio thumby, ctyri DB FAQ a kontaktni ctyrkrokovy proces. Runtime i publish cesta predavaji stejny ctvrty FAQ blok a responzivni kontrola neukazala preteceni ani chyby konzole.
