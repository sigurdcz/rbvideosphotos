# Povinný postup: nejdřív staging

- Všechny změny nejprve připravuj na `dev` nebo pracovní `codex/*` větvi založené z `dev`. Poté je nasaď a ověř na stagingu.
- Nikdy nevyvíjej, necommituj ani přímo nepushuj změny do `master`. Produkci neobcházej ručním FTP uploadem ani spuštěním deploymentu.
- Merge ověřených změn z `dev` do `master` smíš provést pouze po **výslovném souhlasu uživatele s tímto konkrétním merge do `master`**, uděleném po ověření stagingu.
- Souhlas s prací, commitem nebo staging pushem ani zprávy „OK“, „funguje“, „pokračuj“ nebo samotné „pushni“ nejsou souhlas s merge do `master`. Automatický merge je zakázaný. Změní-li se schvalovaný obsah, vyžádej nový konkrétní souhlas.
- Podrobný závazný postup je v [INSTRUCTIONS.md](INSTRUCTIONS.md), instalace prostředí v [STAGING.md](STAGING.md).
