# UKOL: Fatal error config + FAQ vizuál/obsah

Datum: 2026-06-30
Zadání: Opravit fatal "App\Core\Env not found" v config/app.php + opravit prázdný/amatérský /faq.
Hotovo když: Config nejde spustit přímo z webu (žádný fatal), /faq má obsah a světlý vzhled. Lint OK.

## Kroky

- [x] Diagnóza fatal: config/ pod web rootem → přímý přístup spustí app.php bez autoloaderu
- [x] config/.htaccess — deny web access
- [x] Pojistka v config/app.php — class_exists guard → 404 místo fatal
- [x] Diagnóza /faq: čte z faq_items tabulky (ne page bloky), tmavé CSS bije se s light theme
- [x] Světlé accordion CSS v tryServeFaqPage
- [x] Seed faq_items (12 Q&A) v seed_photographer.php
- [x] PHP lint
- [x] Commit

## Výsledek

1) Fatal error: config/.htaccess blokuje přímý web přístup + guard v app.php vrací 404
   místo fatalu (class_exists(Env) check). Interní require (Config::get) běží dál normálně.
2) /faq: handler čte z tabulky faq_items (FaqRepository), kterou seeder neplnil → prázdné.
   Doplněn seed 12 Q&A do faq_items. CSS přepsáno z tmavého (#111) na světlý accordion
   ladící s jemná béžová (světlé karty, zlatý +/− indikátor, čitelný text).
