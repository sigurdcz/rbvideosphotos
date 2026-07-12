# UKOL: Bezova Lens & Tale — cilova homepage kompozice

Datum: 2026-07-12
Zadani: Opravit homepage aktivniho tematu `bezova-lens-tale` tak, aby se na desktopu vizualne a strukturou priblizila dodanemu cilovemu navrhu Lens & Tale.

## Kroky

- [x] Zmapovat aktualni theme template, CSS, runtime data a dostupna demo media
- [x] Prestavet hero, editorialni kapitoly a portfolio na cilovou kompozici
- [x] Sjednotit proces, cenik, reference, blog a zaverecny kontakt/FAQ
- [x] Overit homepage pri viewportu nejmene 1280 px a vyhodnotit druhou iteraci
- [x] Aktualizovat relevantni dokumentaci a provest Wiki Reflection

## Poznamky

Success signal: screenshot na desktopovem viewportu alespon 1280 CSS px obsahuje svetlou editorialni kompozici se zlatym filmovym pasem vlevo, velkym hero s levym textem, sekcemi O nas, portfolio, proces, cenik, reference, blog a kombinovanym kontaktem/FAQ. Zachovaji se DB-driven data a funkcni odkazy/formulare.

Predchozi screenshoty `homepage-current.png` a `homepage-iteration-01-1280.png` jsou lokalni artefakty mimo Git.

Audit odhalil hlavni pricinu: kompletni desktopova korekcni vrstva pro cilovou kompozici byla omylem uzavrena do `@media (max-width: 0px)` a pro realne viewporty se nikdy neaplikovala. Odstraneni teto nedostupne media query aktivuje existujici, DB-driven editorialni layout bez nahrazovani dat.

Druhy screenshot potvrdil aktivaci kompozice. Zbyvajici runtime odchylky jsou konkretni: JavaScript radil kapitoly pouze pokud existoval legacy footer uvnitr obsahu (na aktualni homepage neexistuje) a kontaktu chybel modifier pro tri cilove panely. Oprava pouziva append do obsahu, kdyz footer chybi, a explicitne oznacuje kombinovany kontakt/FAQ layout.

Hero dostal silnejsi vysoke svetle prekryti vlevo a explicitni vrstvy, aby DB fotografie zustala viditelna vpravo, zatimco editorialni text vlevo je citelny a odpovida predloze.

Overeni: `homepage-iteration-04-1280.png` je aktualni screenshot 1280 x 10000 CSS px z `http://127.0.0.1:8766/`; `php tests/run.php` proslo 26/26 a `git diff --check` je cisty. Druha iterace ma skore 78/100: kompozice, poradi kapitol, svetla paleta, filmovy pas a kontakt/FAQ odpovidaji predloze. Zbyvajici rozdil je zamerne DB-driven jmeno, texty a dostupne fotografie RB Videos & Photos oproti referencnimu obsahu Lens & Tale.

## Vysledek

Aktivni desktopova korekce tematu je opravena. CSS se znovu aplikuje na realne viewporty, JavaScript stabilne radi kapitoly i bez legacy footeru a kontakt s FAQ pouziva cilovy tri-panelovy layout. Relevantni pravidlo je doplneno do `INSTRUCTIONS.md`; wiki reflection uklada reprodukovatelne failure modes vizualniho QA.
