# UKOL: GTM createVersion – duplicate version ID

Datum: 2026-06-29
Zadání: createVersion kontrola existující verze — vráti existující nebo hlásí jelas error, ne DB constraint.
Hotovo když: Double-click na "Vytvořit verzi" nepadne; PHP lint čistý.

## Kroky

- [x] Přidat findByContainerVersionId do GtmContainerVersionRepository
- [x] GtmVersionService::createVersion: check existující verze, idempotence
- [x] PHP lint
- [x] Commit

## Poznámky

- Double-click nebo retry na stejný version_id → unique (site_id, container_version_id) violation.
- Řešení: zkontrolovat dřív, než se vkládá; jestli existuje, vrátit existující (idempotence).
- Bez exception, jasná hláška uživateli (pokud je to opravdu duplicita, ne bug).

## Výsledek

GtmVersionService::createVersion je idempotentní: zkontroluje, jestli verze s tím containerVersionId
už existuje (findByContainerVersionId v repository), a pokud existuje, vrátí ji bez vytváření nové
(run se aktualizuje na 'succeeded', return flag 'existing: true'). Double-click nebo retry bez DB chyby.
