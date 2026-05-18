# UKOL: Homepage UI — blog bílý text, FAQ HR, process linka

Datum: 2026-05-18
Zadání: Opravit 3 přetrvávající vizuální chyby na homepage jemna-bezova: bílý text v blog kartách, nekonzistentní HR v FAQ, špatná pozice čáry v sekci Jak to probíhá.

## Kroky

- [x] Blog karty — opravit inline CSS v HomepageContentService.php (flex na .hp-blog-body, odebrat color:#fff)
- [x] Blog karty — theme.css: přidat !important na color overrides aby prorazily inline CSS
- [x] FAQ — navýšit min-height na .hp-faq-question pro konzistentní pozici HR
- [x] Process linka — změnit top: 29px → top: 55px (uživatel potvrdil)
- [ ] Commitnout výsledek

## Poznámky

- HR v FAQ se vkládá přes JS (theme.js řádek 297-299) za .hp-faq-question
- inline `<style>` v PHP je generován s specificitem 0,1,0 vs theme.css 0,2,0 — ale !important zaručí override
- top: 55px confirmed by user testing

## Výsledek
