# Backlog napadu

Tento dokument eviduje napady na budouci rozvoj projektu. Polozky v backlogu nejsou automaticky schvalene k implementaci.

## 1. Kompletni zalohy webu a obnova

**Stav:** Napad

### Cil

Umoznit spravci kdykoliv vytvorit, stahnout, nahrat a obnovit uplnou zalohu webu. Obnova musi zahrnovat textovy obsah, nastaveni, databazove vazby, originalni fotografie i vsechny vytvorene varianty medii.

### Obsah zalohy

- kompletni obsah databaze,
- originalni fotografie a pripadna nahrana videa ze `storage/media`,
- WebP, medium, thumb, OG a dalsi odvozene varianty medii,
- manifest s verzi systemu, datem vytvoreni, velikosti a kontrolnimi soucty,
- informace potrebne pro kontrolu kompatibility a integrity zalohy.

Do zalohy neni nutne ukladat cache, logy ani publikovane staticke vystupy, protoze je lze po obnove znovu vygenerovat.

### Automaticke zalohovani

- kontrolovat stav webu jednou denne pomoci cron ulohy,
- novou zalohu vytvorit pouze v pripade, ze se od posledni zalohy zmenila databaze nebo media,
- uchovavat nejvyse 2 denni automaticke zalohy,
- uchovavat nejvyse 1 samostatnou tydenni automatickou zalohu,
- chybu nebo nedostupny cron zobrazit viditelne v administraci; nepouzivat skryty fallback spousteny navstevou webu.

### Rucni a nahrane zalohy

- umoznit rucni vytvoreni a stazeni ZIP zalohy,
- umoznit nahrani drive stazene zalohy z pocitace,
- rucni a nahrane zalohy nepocitat do limitu automatickych zaloh,
- rucni a nahrane zalohy nikdy automaticky nemazat,
- pred obnovou nahrany balicek nejprve ulozit a overit; obnovu spustit az po vyslovnem potvrzeni.

### Bezpecnost obnovy

- pred kazdou obnovou automaticky vytvorit ochrannou zalohu aktualniho stavu,
- databazi a `storage/media` obnovovat jako jeden konzistentni celek,
- pred obnovou overit manifest, kontrolni soucty, chybejici soubory a kompatibilitu verze,
- pri chybe obnovu viditelne zastavit a nezanechat web v castecne obnovenem stavu,
- po uspesne obnove znovu vygenerovat publikovane stranky,
- zalohy ukladat mimo verejne pristupnou cast webu.

### Administrace

Stranka `Zalohy` ma zobrazovat automaticke, rucni a nahrane zalohy vcetne typu, data, velikosti a stavu kontroly. U kazde vhodne polozky ma byt dostupne stazeni, overeni, obnova a rucni odstraneni.

### Odhad

Kompletni implementace vcetne automaticke rotace, uploadu, bezpecne obnovy a testu: priblizne 5 az 7 pracovnich dnu. Odhad zavisi na limitech hostingu pro cron, velikost uploadu, dobu behu PHP a dostupne misto.

## 2. Sdilena posta (mailbox) v SeoEditoru s ochranou proti dvojite odpovedi

**Stav:** Implementovano na vetvi `codex/shared-mailbox` (celek najednou, bez etap); ceka na staging a overeni s realnou schrankou. Popis a provoz: `ARCHITECTURE.md`, sekce "Sdilena posta".

### Cil

Klient pouziva SeoEditor jako centralni misto pro cteni a odpovidani na maily ze schranky `team@rbvideosphotos.cz` (Active24). Na poste pracuje vice lidi a stava se, ze na stejny mail odpovi dva najednou. System musi takovou dvojitou odpoved zachytit a upozornit.

### Zakladni funkce

- odesilani pres SMTP Active24 z adresy `team@rbvideosphotos.cz` (spravne SPF/DKIM, ne PHP `mail()`),
- cteni prichozi posty pres IMAP (cron synchronizace do DB), kopie zprav zustava ve schrance,
- odeslana odpoved ulozit i do slozky Odeslane ve schrance (IMAP APPEND),
- zahrnout i odpovedi odeslane mimo SeoEditor (webmail, mobil) - synchronizace slozky Odeslane,
- vlákna (konverzace) podle hlavicek `Message-ID`, `In-Reply-To`, `References`; odchozi odpoved s korektnimi hlavickami.

### Ochrana proti dvojite odpovedi (hlavni pozadavek)

- Pokud na posledni zpravu klienta uz nekdo odpovedel, zobrazit u vlakna **cerveny banner** s datem a nahledem te odpovedi.
- Pred odeslanim zobrazit **modal s potvrzenim**: "Na zpravu uz bylo odpovezeno. Opravdu odeslat?"
- Kontrola musi byt na serveru, ne jen ve strance: formular posila ID posledni zname zpravy vlakna; pokud mezitim pribyla odchozi odpoved, server odeslani odmitne a vyzada potvrzeni (priznak `confirmed`). Tim se zachyti i soubeh.
- Banner se ma obnovovat i behem psani (polling), aby se objevil, kdyz nekdo odpovi, zatimco je okno otevrene.

### Bezne funkce odpovedi

- koncepty (drafty) nedopsanych zprav s automatickym ukladanim na server, jeden sdileny koncept na vlakno, verzovani proti tichemu prepsani, smazani po odeslani,
- prilohy (odesilani i stahovani prichozich) s limitem velikosti a typu, ulozeni mimo webroot,
- pole Komu, Cc, Bcc, vice prijemcu s validaci; Odpovedet a Odpovedet vsem,
- HTML z prichozich mailu cistit (sanitizace) kvuli XSS.

### Mimo rozsah (zatim)

- Zadna personalizace, uzivatelske profily ani ACL: v teto fazi neexistuji individualni ucty, takze se neresi, kdo odpovedel ani zamky "prave odpovida X". Prihlaseni pres profily a ACL pribude pozdeji; tento modul na nej ma byt pripraven (napr. nepovinny sloupec autora u odchozich zprav).
- Mailove rozhrani musi byt za stavajicim prihlasenim do SeoEditoru.

### Navrhovane etapy

1. Synchronizace schranky, seznam vlaken, detail, odesilani (Komu/Cc/Bcc, prilohy), banner + modal + serverova pojistka.
2. Koncepty, Odpovedet vsem, notifikace o nove poste.
3. Hledani, sablony odpovedi, cisteni HTML.

### Otevrene otazky

- jedna sdilena schranka, nebo vice schranek,
- dostupnost PHP IMAP rozsireni na hostingu (jinak knihovna v cistem PHP),
- limity Active24 pro pocet odeslanych mailu za hodinu a velikost priloh,
- sifrovane ulozeni hesla ke schrance v konfiguraci.

### Odhad

Etapa 1 a 2 priblizne 5 az 8 pracovnich dnu; etapa 3 dalsi 2 az 3 dny. Odhad zavisi na IMAP dostupnosti a limitech hostingu. Vse nejdriv na `dev` a staging.
