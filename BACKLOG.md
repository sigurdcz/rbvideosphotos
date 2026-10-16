# Backlog

Odložená práce. Před zahájením vybereme prioritu; rozpracovaný úkol se vede v `UKOL.md`.

## 1. Stabilní workflow produkce a stagingu — P1, částečně hotovo

- Hotovo: `dev` → environment `staging`, `master` → `production`; samostatné FTP secrets, staging povoluje jen větev `dev`; pravidla v `INSTRUCTIONS.md`, instalační návod `STAGING.md`.
- Zbývá: dokončit první staging instalaci (FTP secrets, DNS/TLS, vlastní DB, `.env`, obsah), ověřit celý postup staging → uživatelem schválená produkce a zpětnou obnovu ze zálohy.
- Zbývá: dohodnout a nastavit ochranu `master` na GitHubu (PR/review, žádný force push), vyřešit oprávnění schvalovatele. Samotná pravidla v dokumentaci tuto ochranu nevynucují.
- Hotovo bude, když staging funguje včetně administrace, médií, migrací a SMTP a další změna projde kontrolou před výslovně schváleným merge do `master`. Bez automatického merge.

## 2. České znaky ve fontech — P2, čeká

- Některé řezy nezobrazují správně `ř`, `š` a další české znaky.
- Ověřit skutečně načtený font, řezy a Latin Extended subset; opravit zdroj/font konfiguraci, nikoli nahradit český text.
- Hotovo: `ěščřžýáíéůúďťň` správně v nadpisech i textu, na desktopu i mobilu, bez nechtěného náhradního fontu.

## 3. Výška hero videa homepage — P2, čeká

- Pod videem prosvítá fallback obrázek; video nepokrývá celou výšku hero.
- Změřit skutečné rozměry a computed CSS ve vybrané šabloně včetně generovaných assetů; prověřit `height`, `min-height`, `object-fit` a přepnutí poster/video.
- Hotovo: video pokrývá celou hero bez pruhu obrázku na desktopu i mobilu. Před načtením a při nedostupném videu zůstává záměrný fallback správně viditelný.

## 4. Burger tlačítko navigace — P2, čeká

- Po otevření menu se burger přestal animovat do křížku.
- Při hoveru se pod tlačítkem zobrazuje nechtěné tmavé pozadí.
- Ověřit stav otevřeného menu, vazbu na `aria-expanded`, animační CSS a pravidla pro hover/focus v aktivní šabloně i generovaných assetech.
- Hotovo: otevření plynule změní burger na křížek, zavření vrátí burger; hover nemá rušivé tmavé pozadí. Ověřit myš, dotyk i klávesnici a zachovat viditelný focus pro ovládání klávesnicí.
