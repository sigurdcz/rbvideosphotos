# UKOL: Dodelani Elegant White podle aktualni kontroly

Datum: 2026-07-04
Zadani: Opravit zbyvajici problemy na homepage po kontrole live webu: spatne kontakty v paticce, chybejici adresa, necitelna tlacitka a footer, prilis kontrastni kontaktni sekce, proces podle reference, sjednoceni sekce Co nabizime a znovunahrani obrazku.

## Kroky

- [x] 1. Opravit demo kontaktni data vcetne adresy a cisteho renderingu paticky
- [x] 2. Sjednotit HP sekci Co nabizime do jedne horni sekce s obrazkem
- [x] 3. Upravit viditelnost tlacitek, footer a jemnejsi kontaktni barevne schema
- [x] 4. Upravit sekci Jak to probiha podle referencniho navrhu
- [x] 5. Upravit deploy tak, aby se repo media znovu nahrala na server
- [x] 6. Overit syntaxi, diff a pripravit commit/push

## Poznamky

- Success signal: footer na HP ukazuje spravne telefony, email a adresu; spodni odkazy jsou citelne; CTA tlacitka maji citelnou textaci; kontaktni sekce je jemne kremovo-zlata; Co nabizime neni duplicitni; proces ma 5 kroku z reference; obrazky se po deployi maji odkud nacist.

## Vysledek

- Footer bere ciste serverove kontaktni radky, doplni demo adresu pro RB kontakt a JS uz nelepi labely do hodnot.
- Homepage ma jednu sekci Co nabizime s `weare2.png` zdrojem, proces ma 5 referencnich kroku a CTA/footer/kontakt maji citelnejsi schema.
- Deploy znovu zahrnuje `storage/media`, cache a logy zustavaji vynechane.
