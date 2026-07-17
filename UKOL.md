# UKOL: EW3 QA loop na pevných viewპორტech

Datum: 2026-07-17
Zadání: Porovnávat aktivní runtime Elegant White 3 s `VZOR EW3.png`, opravovat odchylky a hodnotit pouze na pevných QA viewპორტech až do poctivých 100/100.

## Kroky

- [x] Potvrdit aktivní téma `elegant-white3` a autoritativní URL lokálního runtime.
- [x] Zapsat opravený QA kontrakt: zoom 100 % a neměnné viewporty 390 x 844 a 1920 x 1080 CSS px.
- [-] Dokončit mobilní kontrolu na 390 x 844 CSS px: screenshot a overflow jsou ověřené, zbývají interakce.
- [-] Dokončit desktopovou kontrolu na 1920 x 1080 CSS px: screenshot a overflow jsou ověřené, zbývají interakce.
- [x] Stanovit nový oficiální baseline pouze z obou pevných viewportů.
- [ ] Opravit největší odchylku v `Templates/elegant-white3` a po každé změně znovu otestovat oba viewporty.
- [ ] Opakovat opravy a měření do 100/100 bez vizuálních nebo funkčních regresí.
- [ ] Spustit testy, `git diff --check`, projít povinné routy a zaznamenat výsledek.

## Poznámky

- Rozsah úprav zůstává výhradně v `Templates/elegant-white3`; CMS core ani DB data se nemění.
- Referenční obraz `VZOR EW3.png` je designový cíl a nesmí se přidávat do commitu.
- Autoritativní testovací URL: `http://127.0.0.1:8766/`; aktivní téma je `elegant-white3`.
- Uživatel 2026-07-17 výslovně schválil lokální Google Chrome jako QA cestu. Každý screenshot vzniká s čistým profilem, cache vypnutou a `--force-device-scale-factor=1` (100 %).
- Platné a neměnné QA viewporty jsou pouze 390 x 844 CSS px (telefon / iPhone 12 Pro) a 1920 x 1080 CSS px (PC), přesně podle `qa-testing.md`.
- Starší snímky při šířce 724 px jsou archivní průzkumné podklady pro srovnání s rozměrem referenčního obrazu. Nesmí se použít pro oficiální skóre ani pro potvrzení overflow.
- Dříve uvedené skóre 96/100 pocházelo z průzkumného 724 px capture, proto je zneplatněno. Nový baseline se stanoví až po obou povinných QA captures.
- Kontrola Chrome DevTools 2026-07-17 odhalila, že samotný headless přepínač `--window-size=390,844` vynutil minimální vnitřní viewport 500 x 749 CSS px. Všechny takto zachycené mobilní obrazy jsou proto pouze pracovní náhledy, nikoli QA důkaz. Další snímky se pořídí až přes `Emulation.setDeviceMetricsOverride` na přesných 390 x 844 CSS px; stejně se explicitně nastaví desktop 1920 x 1080.
- Předchozí úpravy již řeší viditelnost druhé hero CTA, dvousloupcový kontakt, tmavou patičku a kompaktní přechodový breakpoint. Nyní se ověří jejich chování na skutečných QA viewპორტech.
- Mobilní baseline odhalil pevný klon CTA pod herem, který neodpovídá vzoru a zbytečně překrýval viewport. `homepage-v3.js` jej již nevytváří. Současně mobilní override omezuje hero obsah na šířku viewportu, vynucuje zalomení pomocné řádky a vrací viditelný ovladač mobilního menu i značku.
- Po validním snímku 390 x 844 zůstala pomocná SEO řádka i popis u pravé hrany příliš těsné. Na telefonu proto zůstává pouze čitelný titul a dvě hlavní CTA; desktopová hero hierarchie a její popis se nemění.
- Oficiální baseline, iterace QA-01: mobilní snímek `visual-ew3-qa-mobile-390x844-exact-20260717.png`, desktopový snímek `visual-ew3-qa-desktop-1920x1080-exact-20260717.png`. DevTools emulace potvrzuje přesné viewporty 390 x 844 a 1920 x 1080 CSS px; `scrollWidth === clientWidth` platí na obou (375/375 a 1905/1905, rozdíl způsobuje svislý scrollbar). Aktuální vizuální skóre je 88/100. Desktop je blízko vzoru, mobil je nyní čistý bez horizontálního overflow; největší rezerva je v dalším srovnání hustoty a navazujících sekcí na mobilu.
- Ověřeno po QA-01: `php tests/run.php` = 27 tests, 0 failures; `git diff --check` bez chyb (pouze standardní CRLF upozornění).
- QA capture byl rozšířen o `qa_chrome_fullpage_capture.ps1`. Nově zachovává pevnou QA šířku, nejprve měří dokument na základním viewportu a následně nastaví pouze capture výšku na celý stabilizovaný dokument. Validace 2026-07-17: 390 x 844 -> capture 390 x 9689, 1920 x 1080 -> capture 1920 x 7840; overflow je `true` na obou. Aktuální full-page screenshoty jsou uložené v kořeni projektu.
- Iterace QA-02: full-page mobilní snímek ukázal, že starší `nth-child(n + 3)` pravidlo skrylo část portfolia a blog fungoval jako horizontální rail. Mobilní override nyní ukazuje všechny portfolio karty a skládá blog do jednoho svislého sloupce; desktopová pravidla zůstávají beze změny.

## Výsledek

Nevyplněno — probíhá nový baseline na 390 x 844 a 1920 x 1080 CSS px.
