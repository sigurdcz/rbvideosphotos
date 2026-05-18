# UKOL: Homepage UI opravy — jemna bezova

Datum: 2026-05-18
Zadání: Opravit 5 vizuálních chyb na homepage šablony jemna-bezova (blog karty, FAQ, recenze, process linka).

## Kroky

- [x] Blog karty — opravit bílý text (přidat color: var(--jb-brown) pro title, var(--theme-muted) pro excerpt)
- [x] Blog karty — připevnit "Číst dál" na spodek karty (flex layout na .hp-blog-body, margin-top: auto na .hp-blog-link)
- [x] FAQ — HR oddělovač na stejné výšce pro všechny karty (min-height na .hp-faq-question, flex na .hp-faq-item)
- [x] Recenze — zarovnat hvězdičky, text, autora na stejnou pozici (flex layout na .hp-review-card, flex:1 na p)
- [x] Process linka — opravit pozici (top: 29px místo 28px) a zvýšit viditelnost (opacity: .45 místo .28)
- [x] Commitnout výsledek

## Poznámky

- Konflikt: HomepageContentService.php generuje inline CSS s color:#fff pro dark theme, ale jemna-bezova přepisuje background na světlý — text byl neviditelný
- Fixes jsou v theme.css, inline CSS v HomepageContentService.php zůstává (platí pro ostatní tmavé šablony)

## Výsledek

Opraveno 5 vizuálních chyb v Templates/jemna-bezova/assets/theme.css:

- Blog karty mají čitelný tmavý text a tlačítko přichycené ke spodku
- FAQ HR oddělovač je na konzistentní výšce díky min-height na nadpisu
- Recenze jsou zarovnány přes flex layout
- Process linka je správně vycentrována a viditelnější
