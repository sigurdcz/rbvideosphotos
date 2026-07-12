# UKOL: Vychozi hero fotografie v demo seedu

Datum: 2026-07-12
Zadani: Pri hlavnim seedu a Demo obsahu nastavit vychozi hero fotografii z overene nahrane demo medialni knihovny, aby homepage nemela prazdne bile pozadi.

## Kroky

- [x] Auditovat zdroj hero media, DB nastaveni a runtime/publish spotrebu
- [x] Zvolit stabilni demo soubor a nastavit jej jako `default_hero_poster_url`
- [x] Zajistit, ze Demo obsah hodnotu znovu nastavi po nahrani medii
- [x] Pridat regresni test reference na existujici demo medium
- [x] Overit Armageddon -> Demo obsah, DB hodnotu a homepage screenshot/HTTP
- [x] Aktualizovat relevantni dokumentaci
- [x] Provest Wiki Reflection a uzavrit vysledek

## Poznamky

Acceptance signal: po resetu musi `default_hero_poster_url` odkazovat na existujici zaznam v media knihovne a hero homepage nesmi byt bez obrazoveho pozadi. Video zustava volitelne a tato zmena jej nevynucuje.

Historicke migrace se nemeni; sloupce pro defaultni hero media uz existuji. Screenshot `homepage-current.png` je lokalni artefakt mimo Git.

Audit: public runtime i PublishService uz maji stejnou prioritu hero zdroju a pouziji `default_hero_poster_url`, pokud chybi video i strankova hero fotka. Zvolena stabilni demo fotografie je `svatba-nevesta-zenich-polibek-kamenna-zed.webp`.

Hlavni seed ji nahrava do media knihovny a po nahrani explicitne zapisuje jeji cestu do `sites.default_hero_poster_url`; chybejici soubor je viditelna chyba seedu. DemoContentSeeder stejnou fotografii po nahrani medii znovu nastavuje a chybu take nezamlci.

Demo fingerprint nove zahrnuje defaultni hero video i poster cestu, aby rozdil hero pozadi mezi lokalem a online serverem nebyl skryty.

Regresni testy hlidaji existenci zvoleneho souboru, jeho shodny nazev v hlavnim i Demo seedu, zapis poster URL do sites a zmenu fingerprintu pri zmene hero posteru.

Lokalni reset aplikoval 21 migraci, nastavil poster na `/storage/media/2026/07/img_6a53db4c6cccc1.23189644.webp` a media zaznam potvrzuje puvodni soubor `svatba-nevesta-zenich-polibek-kamenna-zed.webp`. Homepage vraci HTTP 200 a novy screenshot prokazuje obrazove hero pozadi.

README dokumentuje viditelny vysledek, ARCHITECTURE poradi hero zdroju a INSTRUCTIONS povinnost fail-fast reference na existujici demo medium. API se nemeni.

## Vysledek

Hlavni i Demo seed znovu pouzivaji existujici svatebni demo fotografii jako `default_hero_poster_url`. Lokalni reset overil zaznam v media knihovne, HTTP 200 a screenshot s viditelnym hero pozadim. Fingerprint po resetu je `e47efedce0da7a223a1187996e56ca80042f94efbf1527849a38904301e85950`.

Aktualizovany relevantni dokumenty: `README.md`, `ARCHITECTURE.md` a `INSTRUCTIONS.md`. API se nemenil. Vsechny zmenene PHP soubory prosly lintem, regresni sada prosla 26/26 a historicke migrace zustaly beze zmeny.

Wiki Reflection: domena `pipelines` zustava `ok`; hub a dossier byly aktualizovany a samostatne commitnuty v `C:\Wiki` jako `511077d docs(seo editor): record default hero poster`. Nespadajici rozpracovane wiki soubory zustaly beze zmeny.
