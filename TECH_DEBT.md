# Technical debt

## TD-001: Vzdy prehravane hero video bez obchazeni cookie souhlasu

- Stav: blokovano
- Priorita: klientsky pozadovana feature
- Pozadavek: hero video se ma po otevreni webu automaticky prehravat i tehdy, kdy navstevnik marketingove cookies odmitne nebo zatim zadnou volbu neudelal.
- Soucasny blocker: nastavene video je YouTube embed. Jeho nacteni pred souhlasem nebo po explicitnim odmitnuti by ignorovalo consent model; podminka, ze nesouhlasici navstevnik musi web opustit, z odmitnuti nevytvori platny souhlas. V projektu navic neni lokalni zdrojove MP4/WebM video.
- Bezpecna cesta k odblokovani: dodat a nasadit self-hosted MP4/WebM variantu stejneho videa. Nativni hero player uz podporuje `autoplay`, `muted`, `playsinline` a `loop` bez zavislosti na YouTube cookies.
- Acceptance: po odmitnuti analytickych i marketingovych cookies se lokalni hero video automaticky prehrava, stranka neposila pozadavky na YouTube/Vimeo a pravni text popisuje skutecne zpracovani bez cookie wall.

Pravni podklad k blockeru: [Cookies - UOOU](https://uoou.gov.cz/verejnost/qa-otazky-a-odpovedi/cookies) a [EDPB Guidelines 05/2020](https://www.edpb.europa.eu/sites/default/files/files/file1/edpb_guidelines_202005_consent_en.pdf).
