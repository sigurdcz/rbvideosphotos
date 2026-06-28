# UKOL: GTM apply – odolnost vůči API kvótě

Datum: 2026-06-28
Zadání: Apply nesmí padat na Quota exceeded; pacing + retry + lehčí inventář + resume přes idempotenci.
Hotovo když: Apply na 429 nespadne celý, opakované kliknutí postupně dokončí 40 operací bez duplicit; PHP lint čistý.

## Kroky

- [x] config/gtm.php + .env.example: přidat min_request_interval_ms (GTM_MIN_REQUEST_INTERVAL_MS)
- [x] GtmApiClient: proaktivní pacing (static lastRequestAt) v rawRequest
- [x] GtmApiClient: create/update tag & trigger retryable (429/5xx), delete/version/publish ponechat
- [x] GtmInventoryService: loadWorkspaceEntities() = connection + tags + triggers + variables (lehký load)
- [x] GtmController::apply: použít lehký load; na GtmApiException 429 srozumitelná resume hláška
- [x] PHP lint všech změněných
- [x] Commit

## Poznámky

- GTM API default kvóta je nízká (~15 dotazů/min, příp. 0,25 QPS). 40 zápisů + inventář nejde do jednoho
  requestu (i PHP timeout na sdíleném hostingu). Řešení = pacing + resume přes idempotenci, ne one-shot.
- Idempotence už funguje: persistEntityResult ukládá hotové entity (last_status fix), fresh inventář při
  dalším apply je vidí jako noop/update -> žádné duplicity, pokračuje se ve zbytku.
- Pacing: static property na GtmApiClient (drží stav v rámci jednoho PHP requestu napříč instancemi klienta).
- Retry: jen create/update (rejected 429 = nezapsáno, bezpečné opakovat). NE create_version/publish (dle zadání).
- Alternativa pro one-shot: uživatel může požádat o navýšení kvóty v Google Cloud (Tag Manager API > Quotas).
- Bez zásahu do GA4 runtime / frontend eventů.

## Výsledek

Apply je nyní odolný vůči nízké GTM API kvótě:
- GtmApiClient má centrální rate limiter (throttle(), static lastRequestAt) s konfigurovatelným
  rozestupem GTM_MIN_REQUEST_INTERVAL_MS (default 300 ms) — žádné burst requesty.
- create/update tag & trigger jsou retryovatelné (429/5xx, backoff); delete/version/publish ne (dle zadání).
- Apply staví plán z lehkého loadWorkspaceEntities() (tagy+triggery+proměnné) místo plného load() — méně
  read callů, víc kvóty na zápisy.
- Při 429 controller ukáže jasnou hlášku, že hotové operace zůstaly uložené a stačí za chvíli kliknout
  Aplikovat plán znovu. Díky idempotenci (uložené entity = noop/update) se pokračuje bez duplicit.
- GA4 runtime / frontend eventy beze změny.

Pozn.: Pro one-shot apply lze v Google Cloud (Tag Manager API > Quotas) požádat o navýšení kvóty.
