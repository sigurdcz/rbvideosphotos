# UKOL: Inject GTM snippet do head tak, aby ho Google naslo

Datum: 2026-05-18
Zadání: Google checker hlásí "značka nenalezena" — GTM se načítá až po souhlasu, ale Google ho musí vidět přímo v HTML. Přesunout GTM snippet do head (unconditionally), GA4/Pixel nechat za consentem.

## Kroky

- [ ] Přečíst SeoRenderer.php a cookie-consent-runtime.js — pochopit stávající implementaci
- [ ] Přidat renderGtmHead() a renderGtmNoscript() do SeoRenderer
- [ ] Injektovat GTM head snippet do `</head>` v SeoRenderer::render() nebo v index.php
- [ ] Injektovat GTM noscript snippet hned za `<body>`
- [ ] Odebrat GTM z dynamického načítání v cookie-consent-runtime.js
- [ ] Commitnout výsledek

## Poznámky

- GTM je kontejner pro správu consent — má být vždy přítomen v head
- GA4, Clarity, Meta Pixel zůstávají za consentem v cookie-consent-runtime.js
- GTM noscript musí jít hned za `<body>`, ne ke konci body

## Výsledek
