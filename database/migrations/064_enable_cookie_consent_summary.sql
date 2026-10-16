-- Enable the non-blocking consent summary for existing installations.
-- The detailed preferences dialog remains user-triggered via Nastaveni.

UPDATE sites
SET cookie_consent_enabled = 1
WHERE cookie_consent_enabled = 0;
