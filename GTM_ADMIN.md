# Google Tag Manager administrace

Tento dokument popisuje admin sekci `/admin/gtm`, ktera slouzi ke sprave Google Tag Manager konfigurace pres API. Neresi runtime emitovani eventu na webu a neprepisuje `public/assets/tracking.js`.

## Verejny runtime kontejner

Kontejner verejneho webu se nastavuje v `sites.google_tag_manager_id`. Pro RB Videos & Photos jej migrace `119_set_rbvideosphotos_gtm_container.sql` nastavuje na `GTM-P7Z5TP6G` pro produkcni i stagingovou domenu.

`SeoRenderer::renderConsentRuntime()` posle ID do `cookie-consent-runtime.js`. Pokud jsou vyplnene GTM i prime GA4 ID, primarni loader je GTM; tagy uvnitr kontejneru pak mohou obslouzit GA4 bez druheho paralelniho loaderu. Skript `gtm.js` se po splneni marketingoveho consentu nacte okamzite a pouze jednou, aby jej nasel Google Tag Assistant. Pri aktivnim consentu bez souhlasu zustava zcela zablokovany.

Po staging deployi over v HTML meta `cms-cookie-consent-settings` hodnoty `googleTagManagerId: GTM-P7Z5TP6G` a `trackingLoaderMode: gtm`. Po marketingovem souhlasu musi Network obsahovat jediny request na `https://www.googletagmanager.com/gtm.js?id=GTM-P7Z5TP6G`.

## Co tato sekce umi

- OAuth pripojeni Google uctu
- vyber GTM accountu, containeru a workspace
- nacteni a ulozeni inventory snapshotu
- spravu katalogu GTM event definic
- plan/apply workflow pro zapis zmen do workspace
- tvorbu a publikaci GTM verzi

## Quick setup

### Google strana

1. V Google Cloud projektu povol Google Tag Manager API.
2. Vytvor OAuth klienta typu web application.
3. Pridej redirect URI:
   `https://tvoje-domena/admin/gtm/oauth/callback`
   nebo pouzij vlastni `GTM_REDIRECT_URI`.
4. Over, ze ucet ma pristup k pozadovanemu GTM accountu, containeru a workspace.

### `.env`

```env
GTM_CLIENT_ID=
GTM_CLIENT_SECRET=
GTM_ENCRYPTION_KEY=
GTM_REDIRECT_URI=
GTM_AUTH_URI=https://accounts.google.com/o/oauth2/v2/auth
GTM_TOKEN_URI=https://oauth2.googleapis.com/token
GTM_API_BASE_URI=https://tagmanager.googleapis.com/tagmanager/v2
GTM_REQUEST_TIMEOUT=20
GTM_CONNECT_TIMEOUT=10
GTM_RETRY_DELAY_MS=500
GTM_MIN_REQUEST_INTERVAL_MS=300
```

`GTM_ENCRYPTION_KEY` musi byt neprazdny, nahodny a dostatecne dlouhy secret. Aplikace z nej pres SHA-256 odvozuje 32byte AES-256-GCM klic; pro sifrovani tokenu musi byt dostupne PHP rozsireni OpenSSL. Volani GTM API navic vyzaduje rozsireni cURL.

Kdyz `GTM_REDIRECT_URI` neni nastavene, aplikace sklada callback z `APP_URL` a fallbackne na `http://localhost:8000/admin/gtm/oauth/callback`.

## Doporuceny workflow

1. Otevri `/admin/gtm`.
2. Pripoj Google ucet.
3. Vyber GTM account, container a workspace.
4. Spust `Nacist inventar`.
5. Zkontroluj event definice.
6. Spust `Sestavit plan`.
7. Az po kontrole spus `Aplikovat plan`.
8. Vytvor verzi.
9. Publikuj verzi samostatnym krokem.

Publikace neni automaticka soucast apply kroku.

## Hlavni obrazovky

### `/admin/gtm`

Prehled zobrazuje:

- stav OAuth pripojeni
- vybrany account, container a workspace
- posledni inventory sync
- posledni GTM verzi
- pripraveny plan a posledni run

### `/admin/gtm/events`

Sprava lokaniho katalogu event definic, ze ktereho se kompiluje GTM konfigurace.
Tabulka navíc ukazuje lokálně zaznamenaný počet jednotlivých eventů za posledních 30 dní a odkazuje na podrobnou kartu **Analytika → Události**. Počet znamená, že veřejný web event úspěšně předal do `dataLayer` nebo `gtag` a následně zapsal bezpečnou lokální kopii; není to potvrzení zpracování v GA4.

Priklady eventu v katalogu:

- `lead_form_submit`
- `phone_click`
- `email_click`
- `pricing_click`
- `cta_click`
- `gallery_open`
- `video_play`
- `scroll_90`
- `booking_complete`

Poznamka: katalog GTM eventu je sirsi nez aktualni runtime tracking v `public/assets/tracking.js`. To je zamerne; GTM katalog muze obsahovat i budouci nebo manualne mapovane eventy.

### `/admin/gtm/entities`

Ukazuje spravovane entity a diff proti inventory snapshotu. Pouziva se pro:

- kontrolu, co je lokalne spravovane
- adopci existujici GTM entity pod spravu aplikace
- odstraneni entity z GTM nebo z lokalni spravy

### `/admin/gtm/versions`

Slouzi pro:

- vytvoreni container verze z aktualniho workspace
- zobrazeni historie verzi
- publikaci vybrane verze

## Inventory snapshot a plan/apply

Po kliknuti na `Nacist inventar` se aktualni stav workspace ulozi lokalne. `Plan` a `Apply` pak pracuji s timto snapshotem, ne s live list endpointy pri kazdem behu.

To prinasi:

- stabilnejsi diff
- nizsi API kvotu
- jasne oddeleni mezi refresh a zapisovou operaci

`Plan` nic nezapisuje, jen ukaze:

- co se vytvori
- co se upravi
- co zustane beze zmeny
- konflikty a nejasnosti

`Apply` zapisuje zmeny do workspace a uklada auditni stopu.

## Bezpecnostni pravidla

- refresh tokeny se ukladaji sifrovane
- GTM chyby se nezamlcuji; UI je ma zobrazit explicitne
- publikace verze je oddelena od vytvoreni verze
- externi entity se nemaji menit bez potvrzeni nebo adopce pod spravu

## Co tato sekce neresi

- runtime emitovani eventu z verejneho webu
- logiku consent gate
- upravy `public/assets/tracking.js`
- automaticke publikovani po kazde zmene

Tyto oblasti patri do runtime trackingu a architektury webu, ne do GTM admin orchestracni vrstvy. GTM admin pouze zobrazuje agregované výsledky, které tato oddělená runtime vrstva uložila.

## Lokalni statistiky eventu

`tracking.js` po úspěšném GTM/direct-GA dispatchi odešle samostatný keepalive požadavek na `/api/tracking-event`. Ukládá se pouze allowlistovaný `event_name`, cesta bez query, čas, denní HMAC návštěvníka a flag výslovného analytického souhlasu. `event_label`, `event_category`, telefon, e-mail a cílová URL se neukládají. Neznámý event, admin cesta a cizí origin se odmítnou. Data drží tabulka `tracking_events` z migrace 122 a stejně jako vlastní návštěvnost se čistí po 14 měsících.

Detail v `/admin/analytics?view=events` ukazuje celkový počet, denní vývoj, počty podle eventu a nejčastější kombinace event + stránka. Chyba lokálního zápisu se vypíše v konzoli a endpoint vrátí neúspěšný HTTP stav; nemění už provedený GTM dispatch.
