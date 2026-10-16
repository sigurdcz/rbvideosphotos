# SEO query mapa RB Videos & Photos

Datum: 2026-08-10

Mapa určuje jednu hlavní URL pro každý záměr. Přesná klíčová fráze se nemá mechanicky vkládat do každého H1; podpůrné články a galerie mají odkazovat na příslušnou službu přirozeným kontextovým odkazem.

| Záměr | Hlavní URL | Doporučený title a H1 | Podpůrné URL |
| --- | --- | --- | --- |
| Svatební fotograf a kameraman Olomouc | `/svatebni-foto-video` | Svatební fotograf a kameraman Olomouc | `/svatebni-fotograf-olomouc`, `/proc-mit-svatebni-video`, svatební galerie |
| Svatební video, video ze svatby | `/svatebni-foto-video` | Svatební foto a video v Olomouci | `/proc-mit-svatebni-video`, video realizace a ceník |
| Rodinné a portrétní focení Olomouc | `/rodinne-a-portretni-foceni` | Rodinné a portrétní focení v Olomouci | `/rodinne-foceni-olomouc`, `/jak-vybrat-misto-na-foceni` |
| Reklamní fotografie a video pro firmy | `/reklamni-foto-video` | Reklamní foto a video pro firmy | `/reklamni-foto-video-pro-firmy` |
| Párové a zásnubní focení | `/rande-a-zasnuby` | Párové a zásnubní focení v Olomouci | relevantní párové galerie |
| Cena focení a svatebního videa | `/cenik` | Ceník focení a svatebního videa | všechny servisní stránky |
| Reference a recenze fotografa | `/hodnoceni` | Hodnocení klientů | homepage, `/kontakt`, `/nase-prace` |
| Ukázky práce a svatební galerie | `/nase-prace` | Naše práce - fotografie a video | jednotlivé galerie a články |
| Rezervace termínu a poptávka | `/kontakt` | Kontakt a rezervace termínu | servisní stránky, ceník, FAQ |
| Informace o týmu | `/o-nas` | Fotograf a kameraman RB Videos & Photos | homepage, služby, články |
| Obecné otázky před focením | `/faq` | Často kladené otázky | ceník, kontakt a služby |

## Rozhodnutí ke sporným frázím a URL

- Nepřirozená fráze `video svatba` se nepoužije. Obsah pokrývá přirozené varianty `svatební video`, `video ze svatby`, `svatební kameraman Olomouc` a `ukázky svatebních videí`.
- `/svatebni-fotograf-olomouc` zůstává podpůrným článkem. Hlavní obchodní landing page je `/svatebni-foto-video`, aby se obě URL nepřetahovaly o stejný záměr.
- Jmenné galerie, kontakt, podmínky užití a stránka o týmu si ponechají přirozené H1 bez násilného přidávání komerčních frází.
- Pět dříve dlouhých blogových slugů už má kratší kanonické cesty v DB, sitemap a Markdown zdrojích. Původní cesty zůstávají pouze jako idempotentní 301 redirecty; další slugy se bez důkazu ze Search Console a přesného 301 plánu nemění.

## Pravidla interního prolinkování

- Podpůrný článek odkazuje na právě jednu hlavní službu, ceník nebo kontakt podle tématu.
- Servisní landing page může odkazovat na relevantní článek a skutečné galerie jako důkaz práce.
- Anchor text popisuje cíl přirozeně; neopakuje mechanicky stejnou přesnou frázi.
- Nová URL nesmí duplikovat záměr existující hlavní stránky.
