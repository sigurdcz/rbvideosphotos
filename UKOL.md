# UKOL: Migrace cele work wiki na dossier v2

Datum: 2026-07-12
Zadani: Projit vsech 12 projektu jeden po druhem, z realneho kodu vytvorit uplny vnoreneny dossier, opakovat rebuild test Ralph loopem a nakonec uzavrit wiki lintem a cistym repozitarem.

## Kroky

- [x] Nacist standard skillu, inventarizovat wiki a potvrdit zdrojove cesty
- [x] Sjednotit strom: jeden top-level hub na projekt, domeny v `projects/<slug>/`
- [x] DirectionSails - audit, 7 domen, rebuild audit, commit `809c3df` + tree fix `07cc432`
- [x] SEO Editor - audit, 7 domen + skill zaznam, rebuild audit, commit `680055c`
- [x] test combat v5 - audit, 7 domen + WorldVar, rebuild audit, commit `f1a47a6`
- [x] SCP Mobile Story - audit, 7 domen, rebuild audit, commit `536ab6a`
- [x] IGEN - audit kodu a dokumentu, 7 domen, rebuild audit, commit `ebb7e8d`
- [x] test physics tools - 8 sekci, rebuild audit a odkazy OK, commit `a5d40a7`; zdroj nema Git metadata
- [x] Escape of Frontline - 8 sekci, rebuild audit a odkazy OK, commit `d80efeb`; integrations a pipeline pravdive partial
- [x] SCP Initiative - 7 sekci, rebuild audit a odkazy OK, commit `a12aacc`; integrations a pipeline pravdive partial
- [x] DracakHada - 7 sekci, rebuild audit a odkazy OK, commit `227f70a`; Selenium live test blokovan chybejicim modulem, pipeline partial
- [x] OMTop remove background - 6 relevantnich sekci, synteticky algoritmicky rebuild a odkazy OK, commit `1d387d3`; rembg clean install a vizualni regression partial
- [x] Mocap Skeleton Video DualCam - 7 sekci, compile/config/naming/smoothing rebuild a odkazy OK, commit `90cee36`; 3D fusion a live video encode partial
- [x] work-wiki-knowledge-base - Codex + Claude audit, 7 domen, parity/rebuild audit a odkazy OK, commit `31bbb45`; automaticky parity test chybi
- [x] Regenerovat `index.html`, overit `llms.txt` limit a vsechny vnorene stromy
- [x] Spustit finalni lint: coverage, checklist, anti-generic, delete/rebuild, links, secrets
- [x] Vytvorit finalni lint report, opravit povolene nalezy a commitnout `555b0e5`
- [x] Provest completion audit vsech akceptacnich kriterii a overit cisty wiki worktree

## Poznamky

Hotovo 12/12 projektu. Completion audit: 12 hubu, 87 vnorenych stran, coverage u vsech hubu, `llms.txt` 15/30, finalni report, vsechny projektove commity a cisty `C:\Wiki` worktree.

Pravidlo dokonceni projektu: vsechny relevantni domeny maji vlastni vnorenenou stranku s excerptem a cisly; `ok` jen pri splnenem checklistu, jinak pravdive `partial` s konkretni mezerou. Hub obsahuje coverage a Tree obsahuje projekt jednou. Po link/security/rebuild kontrole nasleduje samostatny wiki commit.

Zdrojove projektove worktrees se pouze ctou. Existujici rozpracovane zmeny uzivatele se nemeni.

IGEN dokoncen 2026-07-12: architecture/data-model/domain-logic/algorithms/tools = ok; integrations = partial kvuli neoverenym provider cenam/quota/retry; pipelines = partial kvuli rozpracovanemu APP_BASE_PATH a Range/HEAD source flow bez stabilniho smoke testu. Wiki commit `ebb7e8d`.

Po IGEN commitu zustaly v C:\Wiki cizi soubezne zmeny DirectionSails testing pipeline a novy pattern `patterns/unity-editmode-batch-testing.html`. Nebyly zahrnuty do IGEN commitu a musi se zachovat/overit pred finalnim lintem.

## Vysledek

Vsech 12 projektu ma jeden hub a vnorene relevantni domeny. Finalni report je `C:\Wiki\reports\wiki-lint-2026-07-12.html`; globalni opravy jsou v commitu `555b0e5`. Completion audit prosel a wiki repozitar je cisty.
