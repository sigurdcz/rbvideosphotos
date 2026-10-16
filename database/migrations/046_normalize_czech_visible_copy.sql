-- Normalize typography and selected Czech labels in already-seeded visible content.
-- Historical migrations remain immutable; current seeds contain the same copy.

UPDATE pages
SET title = REPLACE(REPLACE(title, '—', '-'), '–', '-');

UPDATE templates
SET name = REPLACE(REPLACE(name, '—', '-'), '–', '-'),
    html_source = REPLACE(REPLACE(html_source, '—', '-'), '–', '-');

UPDATE content_blocks
SET label = REPLACE(REPLACE(label, '—', '-'), '–', '-'),
    content = REPLACE(REPLACE(content, '—', '-'), '–', '-');

UPDATE blog_posts
SET title = REPLACE(REPLACE(title, '—', '-'), '–', '-'),
    excerpt = REPLACE(REPLACE(excerpt, '—', '-'), '–', '-'),
    content = REPLACE(REPLACE(content, '—', '-'), '–', '-');

UPDATE portfolio_entries
SET title = REPLACE(REPLACE(title, '—', '-'), '–', '-'),
    description = REPLACE(REPLACE(description, '—', '-'), '–', '-');

UPDATE reviews
SET author_name = REPLACE(REPLACE(author_name, '—', '-'), '–', '-'),
    context_label = REPLACE(REPLACE(context_label, '—', '-'), '–', '-'),
    review_text = REPLACE(REPLACE(review_text, '—', '-'), '–', '-');

UPDATE faq_items
SET question = REPLACE(REPLACE(question, '—', '-'), '–', '-'),
    answer = REPLACE(REPLACE(answer, '—', '-'), '–', '-');

UPDATE seo_settings
SET meta_title = REPLACE(REPLACE(meta_title, '—', '-'), '–', '-'),
    meta_description = REPLACE(REPLACE(meta_description, '—', '-'), '–', '-'),
    og_title = REPLACE(REPLACE(og_title, '—', '-'), '–', '-'),
    og_description = REPLACE(REPLACE(og_description, '—', '-'), '–', '-'),
    twitter_title = REPLACE(REPLACE(twitter_title, '—', '-'), '–', '-'),
    twitter_description = REPLACE(REPLACE(twitter_description, '—', '-'), '–', '-');

UPDATE content_blocks
SET content = REPLACE(REPLACE(REPLACE(content,
    'Hodnoceni klientu', 'Hodnocení klientů'),
    'Vsechny recenze', 'Všechny recenze'),
    'Nase prace', 'Naše práce');
