# UKOL: GTM last_status ENUM nepřijme 'succeeded'

Datum: 2026-06-28
Zadání: Apply zapisuje last_status='succeeded', ale ENUM tu hodnotu nemá -> Data truncated 1265.
Hotovo když: Apply uloží entitu bez chyby truncation; migrace je append-only a idempotentní; PHP/SQL OK.

## Kroky

- [x] Najít příčinu — gtm_managed_entities.last_status ENUM bez 'succeeded' (016), apply zapisuje 'succeeded'
- [x] Ověřit, že last_status se nikde nečte v plán/diff logice (jen zobrazení) — potvrzeno
- [x] Přidat migraci 018 rozšiřující ENUM o 'succeeded' a 'failed'
- [x] Ověřit způsob aplikace migrace (migrate-util: pdo->exec celého souboru)
- [x] Commit

## Poznámky

- ENUM v 016: ('new','update','noop','conflict','duplicate','deleted'). Apply (GtmApplyService:190,210)
  zapisuje 'succeeded' -> mimo ENUM -> MySQL truncuje -> warning 1265.
- Kód intenci 'succeeded' používá konzistentně na 2 místech => správné je sladit schéma s kódem.
- Migrace MODIFY COLUMN je přirozeně idempotentní; append-only, nemění historické migrace.
- 'failed' přidáno preventivně jako symetrická result-hodnota.
- POZOR: migraci je nutné spustit na produkci (/migrate-util.php?token=...) po deployi.

## Výsledek

Přidána append-only migrace 018, která rozšiřuje ENUM gtm_managed_entities.last_status
o hodnoty 'succeeded' a 'failed' (MODIFY COLUMN, idempotentní). Apply tak uloží entitu
bez chyby "Data truncated". Migraci je nutné po deployi spustit na produkci přes
/migrate-util.php?token=PROD_MIGRATE_TOKEN.
