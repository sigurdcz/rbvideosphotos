# Bezova Lens & Tale: desktop target reference

This document is the static acceptance reference for the homepage at a minimum width of 1280 CSS pixels. The canonical visual implementation is the supplied `lens-tale-multipage` static site (`index.html`, `styles.css`, `script.js`, `pages.js` and its page HTML files). It is a design contract, not page content; all business text, media and links remain DB-driven.

## Canonical source and precedence

- Desktop geometry, typography, component proportions, icons, gradients, hover states and responsive behavior come from the supplied static implementation, not from visual guessing against a compressed overview image.
- Its reference canvas is `864 x 2093`, with a `108px` desktop film strip, `Manrope` sans-serif copy, `Cormorant Garamond` headings and the shared gold palette `#bd8b2f`, `#e4be6a`, `#fff4cf`.
- The CMS theme may scale that canvas to the available desktop width, but must preserve its proportions and component hierarchy. Content and media continue to come from the CMS database.
- Explicit later user corrections override the static source. In particular, the CMS homepage omits the source chapter rail and numbered dots, while retaining the film strip.
- The supplied subpages are authoritative component references for contact, FAQ, blog listing/detail, portfolio listing/detail, pricing and terms pages.

## Canvas

- Centered warm-beige page on a pale neutral outer background.
- A fixed, narrow black-and-gold film strip sits on the left edge and uses real page media.
- There is no vertical chapter line, circular section numbers, empty rail borders, or decorative timeline.
- Every chapter is a light editorial surface with subtle gold rules and a stable left text column.

## Header and hero

- One brand mark, one navigation. Only the current route is underlined. The legacy checkbox/menu label must not be visible.
- Hero has a left content column and a right image field. The content contains eyebrow, large black serif headline, short copy, gold primary CTA and outlined secondary CTA.
- Hero image has two independent layers: the configurable admin overlay covers the complete image; a static white-to-transparent gradient runs from left to right above it. This makes the left black text readable without hiding the photo on the right.

## Chapters in exact order

1. About: left title/copy/CTA, right rounded team photo, then five equally wide icon service cells.
2. Portfolio: left editorial title/copy/CTA, right asymmetric media mosaic with a visible "Nase prace" label.
3. Process: left title/copy; five horizontally aligned icons on the right, vertically centered against the title; include a CTA.
4. Pricing: left title/copy/CTA; three equal cards on the right, center card emphasized. No isolated title or CTA.
5. Reviews: left title/copy/rating/CTA; three equal review cards on the right with controls below.
6. Blog: left title/copy/CTA; cards are two-column objects with an image on the left and a white text panel on the right. Never place the whole article text on the photo.
7. Contact: left text/contact rows/icons, center clearly labelled form and submit button, right FAQ intro and compact accordion rows.

## Footer

- Starts immediately after the contact chapter; no empty viewport-sized remainder.
- Uses a separate beige surface, gold/ink text, a restrained rule and readable brand/contact/social groups.

## Verification

- Capture a fresh full-page screenshot with a real `1280 x 800` or wider desktop viewport after each visual change. Progressively scroll once before capture so lazy/reveal content is rendered, then return to the top.
- Score all eight criteria in `UKOL.md`; a claim of completion requires every hard criterion and an aggregate score of at least 90/100.
- Verify zero horizontal overflow, no active navigation item while the hero is current, and that fixed-height cards end inside their parent section.
