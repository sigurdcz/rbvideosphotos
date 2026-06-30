# UKOL: Opravit ukladani hero videa

Datum: 2026-07-01
Zadani: Opravit admin editaci stranky tak, aby se hero video na homepage po ulozeni neprepsalo prazdnou duplicitni hodnotou.

## Kroky

- [x] Najit formular a save handler pro `hero_video`.
- [x] Opravit duplicitni odesilani stejnych content blocku ve formulari.
- [x] Overit syntaxi a zkontrolovat diff bez sahani na lokalni DB.
- [x] Zhodnotit dokumentaci a zapsat vysledek.

## Poznamky

- `hero_video` se uklada jako content block pres `block_key[]` / `block_content[]`.
- Problem je pravdepodobne duplicitni render stejnych block key ve formulari: horni hero sekce posle novou URL, pozdejsi skryte pole muze stejnou key poslat znovu se starou nebo prazdnou hodnotou a posledni upsert ji prepise.
- `admin/views/pages/form.php` ted sleduje vyrenderovane block key a dalsi duplicitni pole pro stejnou key nevypise.
- Overeno pres `php -l admin/views/pages/form.php`, `php -l app/Controllers/PageController.php` a `git diff --check`.
- Dokumentaci neni potreba menit: jde o bugfix admin formulare, ne o zmenu uzivatelskeho workflow nebo API.

## Vysledek

Hero video pole se ve formulari odesila jen jednou, takze ho pri ulozeni neprepisuje pozdejsi duplicitni prazdna hodnota stejneho content blocku.
