# UKOL: robots.txt friendly pro AI/LLM crawlery

Datum: 2026-07-06
Zadani: Rozsirit generovani robots.txt o explicitni povoleni hlavnich AI/LLM crawleru (OpenAI, Anthropic, Perplexity, Google AI, Meta, Amazon, Apple, Common Crawl, Cohere) a navazat na existujici llms.txt.
Hotovo when: RobotsGenerator::generate() vraci platny robots.txt s wildcard pravidlem + explicitnimi AI crawler sekcemi + odkazem na sitemap a llms.txt; overeno PHP lintem a primym volanim generatoru; zmena je commitnuta do gitu (zadne rucni SQL/server zasahy).

## Kroky

- [x] Zkontrolovat aktualni RobotsGenerator a napojeni na PublishService/LlmsTxtGenerator
- [x] Nacist seo-analiza skill pro GEO/AEO doporuceni
- [x] Prepsat RobotsGenerator::generate() s explicitnimi AI crawler sekcemi
- [x] Overit PHP syntax (php -l)
- [x] Overit primym volanim generatoru na testovaci domene (bez lokalniho serveru)
- [x] Aktualizovat UKOL.md vysledek
- [x] Commit

## Poznamky

- robots.txt neni seedovana obsahova data — generuje ho cistě kod (RobotsGenerator), volany z PublishService::publishSite().
  Zmena v gitu se tedy projevi automaticky pri dalsim publikovani webu, bez rucnich SQL/server zasahu.
- Projekt uz ma /llms.txt (LlmsTxtGenerator) — jen doplnuji odkaz/komentar v robots.txt, negeneruji novy soubor.
- Bez lokalniho serveru: generator je cista PHP trida bez DB/HTTP zavislosti, testovatelna primym volanim.

## Vysledek

`App\Services\RobotsGenerator::generate()` nyni vraci robots.txt se tremi vrstvami: (1) obecne
`User-agent: *` pravidlo pro standardni crawlery, (2) explicitni sekce pro hlavni AI/LLM crawlery
seskupene podle poskytovatele — OpenAI (GPTBot, ChatGPT-User, OAI-SearchBot), Anthropic (ClaudeBot,
Claude-User, Claude-SearchBot, anthropic-ai), Perplexity (PerplexityBot, Perplexity-User), Google AI
(Google-Extended), Meta AI, Amazon AI, Apple AI, Common Crawl, Cohere — vsechny s `Allow: /`, (3) odkaz
na existujici `/llms.txt` (strojove citelny souhrn webu, generuje `LlmsTxtGenerator`) a na `/sitemap.xml`.

Zmena je cistě v kodu generatoru (zadne SQL, zadny rucni zasah na serveru). robots.txt neni seedovana
data — pise ho `PublishService::publishSite()` pri kazdem publikovani webu, takze se nova verze projevi
automaticky pri nejblizsim publish po nasazeni tohoto commitu.

Overeni: `php -l app/Services/RobotsGenerator.php` bez chyb; primy vypis vystupu tridy pro testovaci
domenu potvrdil validni strukturu (wildcard blok, vsechny AI sekce, Llms + Sitemap radky na konci).
Zadny jiny soubor nebyl zmenen (izolovany diff).
