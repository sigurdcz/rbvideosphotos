# Legitimate contact corpus

`contact-legitimate-1000.json` contains exactly 1,000 synthetic, concrete inquiry
records with unique message bodies: 20 categories, five reviewed templates per
category and ten contexts (cities, dates, venues and fictional contacts).
These are examples of plausible customer messages, not collected personal data.

Coverage includes formal/casual Czech, no diacritics, typos, very short questions,
phone-only contact, wedding/family/business work, pricing, invoices, video,
links to rbvideosphotos.cz, venues, Maps, Instagram reels, Vimeo, YouTube, Drive,
Pinterest, Facebook and photo albums, and EN/DE/SK/PL/RU/SQ inquiries.
An ordinary English cash-payment/bonus-print request deliberately exercises the
former false-positive combination of two ambiguous words plus a URL.
URL path words such as Instagram `/reel/` must not count as promotional prose.

Run `php tests/fixtures/generate-contact-legitimate.php` to reproduce the JSON.
The deterministic generator must reproduce identical bytes. Generated fixture
changes are committed separately from their generator and classifier changes.

Run `php tests/run-contact-spam.php --mysql` to test all 1,000 against defaults,
their stored JSON roundtrip, and a migrated local profile. The MySQL mode requires
DB_HOST=127.0.0.1 and uses a connection-local temporary sites table; it never
changes real site settings. Without `--mysql`, the first two modes still run.
The corpus is also included in `php tests/run.php`.

Tests call the classifier directly: no HTTP submissions, SMTP, SMS or archived
messages. Failures identify the record and score/reasons. Never relabel a
legitimate example simply to make an antispam change pass. Review its intent
first, fix false positives, and retain negative spam regression examples.

The PASS contract targets the default threshold/weights, plus the tested
migrated profile. An administrator who intentionally lowers the threshold or
adds a conflicting custom phrase may reject these examples. This finite,
template-derived corpus does not prove safety for every human message or language.
