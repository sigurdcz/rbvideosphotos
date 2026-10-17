# Schválená video galerie — 17. 10. 2026

Uživatelské pořadí je závazné; datum videa je nepřerovnává. Názvy a dostupnost metadat ověřeny z oficiálního YouTube oEmbed. To samo nepotvrzuje přehrávání v každém prohlížeči nebo regionu.

| Pořadí | Video | Název |
| --- | --- | --- |
| 1 | [L-zaa5hj-aU](https://youtu.be/L-zaa5hj-aU) | Ivanka a Lubomír Nevěřilovi |
| 2 | [cXU7Ty-kjyA](https://youtu.be/cXU7Ty-kjyA) | Simona a Michal Novákovi |
| 3 | [hYj2w3P5rK8](https://youtu.be/hYj2w3P5rK8) | Lucie a Miroslav Provazovi |
| 4 | [5ZlVVcIu10U](https://youtu.be/5ZlVVcIu10U) | Pavlína a Marek Krylovi |
| 5 | [O5i9TzCms5c](https://youtu.be/O5i9TzCms5c) | Marie a Miroslav |
| 6 | [b9WhNN8VYhc](https://youtu.be/b9WhNN8VYhc) | Martina a Jaroslav Žáčkovi |
| 7 | [C1wL3QFZhn0](https://youtu.be/C1wL3QFZhn0) | Nikola a Jirka Kaňkovští |
| 8 | [DBWKJ46eM4c](https://youtu.be/DBWKJ46eM4c) | Markéta a Petr Pláškovi |
| 9 | [su0hKnBnCKw](https://youtu.be/su0hKnBnCKw) | Ester a Tomáš Krůpovi |
| 10 | [1z-qckQn5UU](https://youtu.be/1z-qckQn5UU) | Michaela a Jan Jünglingovi |
| 11 | [o-1xGllIShY](https://youtu.be/o-1xGllIShY) | Iveta a René Gruntovi |
| 12 | [GBKJ_VB_Nmw](https://www.youtube.com/watch?v=GBKJ_VB_Nmw) | Tomáš a Helena Hufovi |

Osm URL se překrývá s původní staging galerií a zůstává podle zadání. Vyřazená ID: `FzchuDWJ5Vg`, `g2xbGhaUkpg`, `sc06FkCv_Mw`, `zviMaOQnxUY`.

## Implementace a ověření

- Append-only 106: sdílený obsahový handler až po SQL schématu, bez změn starých migrací. Demo importer sdílí stejný manifest.
- Rozsah: video položky prvního veřejného webu, včetně draftů; fotografie a ostatní weby zachovány. Číslování videí navazuje za existující fotografie, jejich relativní pořadí se nemění.
- Odstranění neplatných sekundárních videí a asociací fotek u video položek, zachování fyzických médií. Vyřazené statické video exporty se odstraní; selhání odstranění je explicitní a DB transakce se vrátí.
- `php tests/ApprovedVideoPortfolioIntegrationTest.php`: 48 kontrol prošlo; přesné URL/pořadí, zachování fotografií/jiného webu/hero/médií, odstranění starého exportu, idempotence včetně IDs a timestamps.
- Upgrade test: všech 108 migračních souborů, 16 obsahových handlerů po dokončení SQL schématu.
- Funkční regrese: 219/219. HTML goldens 27/28; známý starší rozdíl posteru O nás nebyl přepsán.

## Staging a produkce

Před změnou byl bezpečně načten staging API seznam a veřejný listing; lokální záloha bez tajemství je v ignorovaném `.codex-qa/staging-portfolio-before.json`. Nasazení, spuštění migrace a výsledná veřejná kontrola zatím čekají. Staging push zahrne také dříve připravené frontend opravy; produkce se tím nemění. Merge do masteru vyžaduje nové konkrétní schválení uživatele po staging kontrole.
