# UKOL: Screenshot QA — iterace 01 homepage

Datum: 2026-07-12
Zadani: Provést první porovnávací iteraci homepage vůči dodanému cílovému návrhu. Každý testovací screenshot musí používat viewport alespoň 1280 CSS px široký.

## Kroky

- [x] Ověřit lokální server a určit srovnávací viewport
- [x] Definovat měřitelná vizuální kritéria
- [x] Pořídit baseline screenshot na viewportu 1280 px
- [x] Porovnat aktuální výsledek s cílovým návrhem a stanovit skóre
- [x] Sepsat největší rozdíly a první následnou korekci

## Kritéria a výsledek

Viewport baseline: 1280 × 10000 CSS px, URL `http://127.0.0.1:8766/`. Homepage vrátila HTTP 200. Artefakty screenshotů jsou lokální a mimo Git.

| Oblast | Váha | Skóre iterace 01 | Zjištění |
| --- | ---: | ---: | --- |
| Struktura a pořadí sekcí | 20 | 10 | Hlavní obsahové bloky existují, ale jejich hierarchie a pořadí neodpovídají cílové editorial kompozici. |
| Hero kompozice | 20 | 8 | Výchozí fotografie je viditelná, ale titul, kontrast, velikost a rozložení se výrazně liší od cíle. |
| Typografie a vertikální rytmus | 15 | 5 | Text je příliš malý a sekce jsou roztažené či stlačené jinak než v předloze. |
| Portfolio a karty | 15 | 6 | Média fungují, ale chybí cílová koláž a světlá editorial prezentace. |
| Ceník, reference a proces | 15 | 7 | Obsahové bloky jsou přítomné, avšak karty, orientace a proporce neodpovídají cíli. |
| Barvy, ornamenty a filmový pás | 10 | 7 | Krémová/zlatá paleta i filmový pás jsou rozpoznatelné, ale tmavé plochy a prázdné bloky narušují shodu. |
| Kontakt a FAQ | 5 | 3 | Cílové spojení kontaktu a FAQ do závěrečného layoutu chybí. |

Celkové skóre: **46 / 100**. Skóre hodnotí vizuální shodu, nikoli funkčnost jednotlivých modulů.

## Následující korekce

Nejvyšší dopad má přestavba hero a nadřazené stránky na cílovou kompozici: plnohodnotný světlý hero blok s levým titulkem, pravým obrazem, správnou výškou a následnou kapitolovou strukturou. Po každé změně se zopakuje screenshot na šířce nejméně 1280 px a aktualizuje toto skóre.

## Stav

První testovací iterace je dokončena. Žádná implementační změna nebyla v této iteraci provedena; následuje prioritní vizuální korekce hero/layoutu.
