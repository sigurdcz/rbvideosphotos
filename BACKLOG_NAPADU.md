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
