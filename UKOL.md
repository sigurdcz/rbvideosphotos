# UKOL: Bezova Lens & Tale — iterace podle preview-desktop-reviews

Datum: 2026-07-12
Zadani: Srovnat homepage s lokalnim zdrojem pravdy `preview-desktop-reviews.png`; predchozi hodnoceni 91/100 je zruseno.

## Kroky

- [x] Zmerit kompozicni rozdily vuci `preview-desktop-reviews.png`
- [x] Obnovit presnou levou kapitlovou osu s cisly 1–8 a svetly hero
- [x] Dorovnat textovou hierarchii, roztece a layout vsech kapitol
- [x] Dorovnat karty ceníku, referenci, blogu a formulare na cilove proporce
- [x] Udelat finalni screenshot pri 1440 px a nove prisne skore

## Poznamky

Zdroj pravdy je lokalni `preview-desktop-reviews.png`, nikoli predchozi slovni interpretace. Cíl obsahuje levou zlatou osu s kapitolami 1–8; ma se obnovit. Iterace neskonci, dokud srovnani neprojde vsemi sekcemi.

Hlavni rozdily: cil ma sirsi filmovy pas a levou osu v samostatnem gutteru, hero s titulkem „PŘÍBĚHY, které zůstanou“, vyssi serifovou typografii a vzdušnejsi, kratší kapitoly. Aktualni web mel osu odstraněnou podle starsi instrukce, proto se nyní vrací s cisly 1–8.

Kontrola po obnoveni osy ukazala dalsi rozdil: blogovy runtime vybiral vnorené i obalove `.hp-blog-card`, proto nezakryl treti kartu a CSS je rozlozilo vertikalne. Selektor je omezen na prime obaly gridu; cíl jsou dve horizontalni karty.

Posledni screenshot odhalil lazy-load placeholdery v blogu. Blogove obrazky se proto pro desktopovou kompozici nactou eager a prime karty gridu se renderuji jako dve horizontalni editorialni karty.

Iterace 13 zacina opravou celeho desktopoveho platna: shell 1296 px, filmovy pas 190 px, osa v x=244 px uvnitr shellu a vyrazne kratsi kapitoly. Pred touto iteraci je prisne skore 63/100; nejvetsi penalizaci ma temer dvojnasobna vyska stranky a spatna horizontalni geometrie.

Prvni snimek iterace 13 dosahl 69/100. Odhalil, ze vysku procesu, ceniku, referenci a blogu nevytvarely karty, ale automaticke radky gridu pro kazdy levy textovy prvek. Levy editorialni obsah je proto ukotven v jedinem radku a vysku kapitoly nyni urcuje jeji hlavni obsah.

Screenshot 14 potvrdil, ze obecne pravidlo pro `.section` nezasahlo vsechny JS preklasifikovane kapitoly. Kapitoly proto dostaly explicitni vysky podle souradnic ciloveho artboardu; tim se odstranuje hlavni systemova odchylka pred dalsim ladenim detailu.

Iterace 15 dosahla 78/100 a poprve ma celkovou vysku blizkou cili. Orez ceniku a referenci zpusobovaly prazdne legacy obaly a obal CTA, ktere zustaly v normalnim toku pred JS presunutymi gridy. Tyto obaly jsou nyni vyjmuty z toku a ovladani referenci je zarovnano s kartami.

Mereni iterace 16 odhalilo systemovou pricinu: CMS minifikator odstranil mezeru pred `:is(...)`, takze kriticke descendant selektory zmenil na compound selektory a pravidla se nepouzila. Kriticka desktopova geometrie je proto prepsana explicitnimi, minifier-safe selektory bez `:is`.

Iterace 17 dosahla 84/100. Vsechny hlavni gridy jsou nyni viditelne a ve spravnem sloupci. Dalsi korekce zmensuje pretekajici leve titulky, zesiluje cilovy bily hero gradient, posouva motiv fotografie doprava, doplnuje druhe showreel CTA a dava kontaktu dostatecnou vysku.

Iterace 18 dosahla 89/100. Side-by-side ve shodnem meritku odhalil, ze runtime trida about kapitoly je `.blt-about`, zatimco vyska byla omylem nastavena jen pro `.blt-about-section`. Oprava sjednocuje vysku kapitoly s cilem, zkracuje kontakt na cilovou proporci a vynucuje skutecne nacteni obou blogovych obrazku.

Iterace 19 dosahla 92/100. Pri presnem 1440x3140 porovnani jsou prave gridy proporcne blizko cili; zbyva posunout osu a leve editorialni bloky o 40 px doleva a zkratit vertikalni soucet hero + reference, aby kontakt zacinal na cilove souradnici.

Iterace 20 zustala na 92/100. Zkraceni referenci na 250 px pouze orezalo CTA, proto je tato dilci zmena vracena; dalsi korekce musi vychazet z presneho prekryvu sekcnich hran, ne z dalsiho plosneho zkracovani.

Pixelovy overlay iterace 20 ukazal temer konstantni posun vsech hran od konce hero. Cilova hlavicka je overlay nad hero, zatimco aktualni hlavicka zabirala vlastni radek. Desktopova hlavicka je proto absolutne polozena nad hero; obsah kapitol zustava beze zmeny.

Iterace 21 dosahla 94/100. Overlay hlavicky srovnal sekcni hranice; zbyvajici systemove rozdily jsou chybejici kruhove znacky kapitol 2-8 a prilis citelna fotografie pod levou casti hero. Znacky jsou ukotveny negativnim offsetem k ose gutteru a hero dostava silnejsi dvojity bily zavoj.

Iterace 22 dosahla 94.5/100. Negativni offset znacek byl orezan `overflow` kapitol; osa se proto posouva o polomer znacky a kruhy zustavaji cele uvnitr sekce bez povoleni pretekani obsahu.

Iterace 23 ma 95/100 pro strukturu a geometrii, ale prisne celkove hodnoceni zustava 94.5/100: reference jsou proti cili asi o 50 px vyssi a posouvaji blog i kontakt. Zkracuje se pouze tato kapitola, s mensim vnitrnim paddingem, a zesiluje se globalni admin overlay hero fotografie.

Iterace 24 zustava prisne na 94.5/100 kvuli orezu CTA/ovladani referenci a asi o 35 px vyssimu ceniku. Ovladače se posouvaji dovnitr referencni kapitoly a cenik i karty se zkracuji o namereny rozdil; uvolnena vyska patri kontaktu.

Iterace 26 dosahla 95/100 pro celkovou kompozici. Pred finalnim uzavrenim se CTA ceniku posouva dovnitr kapitoly a nespolehlive pseudo-elementy kapitol 2-8 se nahrazuji skutecnymi DOM markery, aby cisla vzdy prezila produkcni minifikaci a overflow pravidla.

Iterace 27 potvrzuje 95/100. DOM obsahuje markery 2-8 a posledni zbyvajici orez cenikoveho CTA se odstranuje zvysenim jeho spodniho odsazeni.

## Vysledek

Hotovo. Finalni kontrola pouziva viewport 1440x3140, stejny jako cilovy soubor. Dosazene prisne skore: 95/100.
