# Workflow Notes

Tento soubor slouzi jako pracovni log a dohoda o tom, jak v repu postupuji.

## Pracovni pravidla

- Nejdriv si nacitam relevantni kontext v repu.
- Kdyz se dokumentace rozchazi s kodem, beru jako zdroj pravdy aktualni implementaci.
- Zmeny delam co nejmensi a bez zasahu do nesouvisejicich casti.
- Pred upravou overuji dopad na admin, frontend, publish a media.
- Po uprave delam aspon zakladni kontrolu vystupu nebo syntaxe.

## Aktualni poznamky

- Projekt je aktualne `MySQL/MariaDB only`.
- Verejny web neni uz jen ciste staticky export. Bezne stranky se renderuji z databaze, cast vystupu se ale stale publikuje do `storage/published`.
- Portfolio detail a listing jsou generovane HTML soubory.
- Portfolio media maji pouzivat root-relative cesty ze `storage_path`, ne skladani pres ulozenou domenu webu.

## Log

- 2026-05-07: Nacteny `README.md` a `ARCHITECTURE.md`.
- 2026-05-07: Zalozen `WORKFLOW.md`.
- 2026-05-07: Opraven render portfolio medii tak, aby generoval verejne root-relative URL pro obrazky.
- 2026-05-07: Aktualizovany vsechny `.md` soubory podle skutecneho stavu projektu.
