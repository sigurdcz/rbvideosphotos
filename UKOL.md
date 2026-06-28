# UKOL: Fix GtmConnectionService::update() neexistuje

Datum: 2026-06-28
Zadání: Opravit fatal error při /admin/gtm/inventory/refresh — GtmInventoryService volá neexistující metodu GtmConnectionService::update().
Hotovo když: Inventory refresh proběhne bez fatal erroru, snapshot se uloží do gtm_connections, PHP lint je čistý.

## Kroky

- [x] Zjistit příčinu — GtmInventoryService->load() volá $this->connections->update() na service, kde metoda neexistuje (existuje jen na repository)
- [x] Přidat dedikovanou metodu storeInventorySnapshot() do GtmConnectionService
- [x] Upravit GtmInventoryService->load() — použít novou metodu, odstranit redundantní updateInventoryStamp
- [x] Ověřit PHP syntaxi (php -l)
- [x] Commit

## Poznámky

- Sloupce inventory_snapshot_json / inventory_snapshot_at existují (migrace 017).
- Repository GtmConnectionRepository::update() je generická; service vystavuje jen konkrétní wrappery (updateSelection, updateInventoryStamp...).
- Bez zásahu do GA4 / frontend trackingu.

## Výsledek

Přidána metoda `GtmConnectionService::storeInventorySnapshot()`, která uloží snapshot
(`inventory_snapshot_json`, `inventory_snapshot_at`) a zároveň stamp `last_inventory_at` + `status`.
`GtmInventoryService::load()` ji nyní volá místo neexistující `update()`; odstraněn redundantní
`updateInventoryStamp()` a zbytečná kontrola connection id. Inventory refresh už nepadá fatal errorem.
