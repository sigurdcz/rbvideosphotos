# UKOL: SEO audit webu + dev tracking ID do .env.example

Datum: 2026-05-18
Zadání: Technický SEO a performance audit rbvideosphotos.kvalitne.cz + doplnit dev tracking ID do .env.example.

## Kroky

- [x] Fetch homepage a analýza HTML, obrázků, videí
- [x] Fetch dalších stránek (portfolio, kontakt, ceník)
- [x] Výstup: audit obrázků — seznam s návrhy optimalizace
- [x] Výstup: audit videí a embedů
- [x] Výstup: doporučení struktury homepage
- [x] Aktualizovat .env.example — opravit GTM_ID (mělo G-BD4BS9R4JZ, správně GTM-542MMSL)
- [x] Opravit injectSiteLogo — servírovat WebP přes picture element
- [ ] Commitnout změny

## Poznámky

Web běží na vlastním PHP CMS nasazeném na webzdarma.cz.
URL: https://rbvideosphotos.kvalitne.cz/

Dev tracking IDs z .env:
- GA4_ID=G-BD4BS9R4JZ
- GTM_ID=GTM-542MMSL
- CLARITY_ID=wss3v71dbz

## Výsledek

- `.env.example` doplněn: GA4_ID, GTM_ID (opraven z chybné G-BD4BS9R4JZ na správnou GTM-542MMSL), CLARITY_ID
- `public/index.php` — `injectSiteLogo` přepsán:
  - Vyhledá WebP variantu loga z `media_variants` (přes `MediaRepository::findByStoragePath` + `variantsByMediaId`)
  - Pokud WebP existuje, wrapper je `<picture><source type="image/webp">` + `<img fetchpriority="high" decoding="async">`
  - Přidán `.site-logo-picture{display:contents}` do injektovaného CSS
- Audit: viz výstup v konverzaci (PRIORITA 1/2/3 s konkrétními nálezy a doporučeními)
