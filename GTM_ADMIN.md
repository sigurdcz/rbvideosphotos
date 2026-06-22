# Google Tag Manager administrace

Tento dokument popisuje novou admin sekci `/admin/gtm`, která slouží k přípravě a správě Google Tag Manager konfigurace přes API. Neřeší runtime trackování na webu. Nezasahuje do `public/assets/tracking.js` ani do současné GA4 logiky.

## 1. Quick setup

### Co je potřeba v Google účtu

1. V Google Cloud projektu povolit Google Tag Manager API.
2. Vytvořit OAuth klienta pro webovou aplikaci.
3. Přidat redirect URI na:
   `https://tvoje-domena/admin/gtm/oauth/callback`
   nebo použít vlastní hodnotu z `GTM_REDIRECT_URI`.
4. Ověřit, že účet má přístup k GTM účtu, kontejneru a workspace, které chceš spravovat.

### Co je potřeba v `.env`

Nastav tyto hodnoty:

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
```

`GTM_ENCRYPTION_KEY` musí být 32-byte klíč pro šifrování refresh tokenu.

### Rychlý postup v administraci

1. Otevři `/admin/gtm`.
2. Klikni na připojení Google účtu.
3. Uděl OAuth autorizaci.
4. Vyber GTM účet, kontejner a workspace.
5. Načti inventář GTM.
6. Zkontroluj eventy a případně uprav definice.
7. Vygeneruj plán.
8. Aplikuj plán do workspace.
9. Vytvoř verzi.
10. Pokud je vše správně, publikuj verzi.

### Co je důležité

- Refresh token se ukládá šifrovaně.
- Publikace není automatická, je to samostatný krok.
- Externí entity v GTM se nemění bez explicitního potvrzení.
- Pokud chybí OAuth konfigurace, administrace to zobrazí jako blokující stav.

## 2. Popis admin sekce

### Přehled `/admin/gtm`

Toto je hlavní rozcestník celé GTM administrace. Zobrazuje:

- stav OAuth připojení
- vybraný GTM účet, kontejner a workspace
- poslední synchronizaci
- poslední GTM verzi
- dostupnost konfigurace a případné chyby

Z přehledu jde rovnou přejít na eventy, entity a verze.

### Eventy `/admin/gtm/events`

Tady se spravuje katalog předpřipravených událostí. Každý záznam odpovídá jednomu business eventu, například:

- `lead_form_submit`
- `phone_click`
- `email_click`
- `cta_click`
- `scroll_90`
- `booking_complete`

V této části se řeší:

- název eventu
- popis
- kategorie
- zda je event aktivní
- typ triggeru
- typ tagu
- parametry tagu a triggeru
- poznámky a metadata

Smysl této sekce je mít jeden centrální katalog, ze kterého se generuje GTM konfigurace konzistentně a bez ručního skládání JSONu.

### Detail eventu `/admin/gtm/events/edit`

Detail eventu slouží k jemnému nastavení konkrétní definice.

Přehledná část formuláře je určená pro běžnou správu. Pokročilá část umožňuje upravit strukturované GTM parametry, které se ukládají jako JSON.

Používej ji tehdy, když je potřeba:

- upravit názvy tagů a triggerů
- změnit defaultní parametry
- doplnit metadata pro plánování a validaci

### Entity `/admin/gtm/entities`

Sekce entity ukazuje, co je v GTM aktuálně spravované a co je jen lokálně připravené.

Typicky zde najdeš:

- tagy
- triggery
- proměnné
- vygenerované nebo importované položky
- vazbu na lokální `management_key`

Účel této části:

- odlišit entity spravované aplikací od externích entit
- zkontrolovat duplicitní nebo konfliktní položky
- adoptovat existující GTM entitu pod správu aplikace
- odstranit entitu z lokální správy, pokud už ji nechceš řídit z administrace

### Inventář a synchronizace

Inventář GTM se načítá z vybraného account/container/workspace. Tím se zobrazuje aktuální stav a umožňuje porovnání s lokálním katalogem.

Po kliknutí na `Nacist inventar` se stav uloží jako lokální snapshot. `Sestavit plan` a `Aplikovat plan` už nad GTM nevolají live list endpointy, ale pracují s tímto uloženým snapshotem. Tím se drží API kvóta a současně je jasně oddělený refresh od plánování.

Tahle vrstva slouží k tomu, aby šlo:

- načíst aktuální stav GTM
- detekovat rozdíl mezi lokálním záměrem a skutečným workspace stavem
- připravit plán změn před zápisem
- zabránit duplicitám při opakovaném spuštění

### Plan / Apply

Tohle je hlavní bezpečnostní mechanismus celé GTM administrace.

`Plan`:

- spočítá, co se má vytvořit, upravit nebo zachovat
- nezapisuje do GTM
- ukáže konflikty, duplicity a nejasnosti

`Apply`:

- zapíše připravený plán do workspace
- používá stabilní management keys
- ukládá auditní stopu
- po úspěchu uloží stav lokálně, aby další běh neprodukoval duplicity

### Verze `/admin/gtm/versions`

Sekce verzí řeší práci s container verzemi.

Umí:

- vytvořit verzi z aktuálního workspace
- uložit metadata verze
- zobrazit historii verzí
- publikovat konkrétní verzi

Publikace je samostatná akce. Vytvoření verze neznamená automatickou publikaci.

### Chování při chybě

Administrace má být hlasitá, ne tichá.

To znamená:

- OAuth chyba se zobrazí přímo v UI
- chybějící scope nebo práva se nezamlčí
- API error se uloží do auditního logu
- konfliktní stav se ukáže jako konflikt, ne jako úspěch

### Co se v této fázi nemění

Tato implementace zatím neřeší:

- runtime odesílání eventů z webu do GTM
- přepsání současného GA4 tracking runtime
- automatické publishování po každé úpravě

To je až další krok refaktoru.
