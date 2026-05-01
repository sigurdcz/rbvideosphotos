PRAGMA journal_mode=WAL;
PRAGMA foreign_keys=ON;

CREATE TABLE IF NOT EXISTS "users" (
  "id"            INTEGER PRIMARY KEY AUTOINCREMENT,
  "email"         TEXT NOT NULL UNIQUE,
  "password_hash" TEXT NOT NULL,
  "role"          TEXT NOT NULL DEFAULT 'editor' CHECK(role IN ('admin','editor')),
  "created_at"    TEXT NOT NULL DEFAULT (datetime('now')),
  "updated_at"    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS "sites" (
  "id"                    INTEGER PRIMARY KEY AUTOINCREMENT,
  "name"                  TEXT NOT NULL,
  "domain"                TEXT NOT NULL,
  "default_language"      TEXT NOT NULL DEFAULT 'cs',
  "google_analytics_id"   TEXT DEFAULT NULL,
  "google_tag_manager_id" TEXT DEFAULT NULL,
  "head_scripts"          TEXT DEFAULT NULL,
  "body_scripts"          TEXT DEFAULT NULL,
  "created_at"            TEXT NOT NULL DEFAULT (datetime('now')),
  "updated_at"            TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS "templates" (
  "id"          INTEGER PRIMARY KEY AUTOINCREMENT,
  "site_id"     INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
  "name"        TEXT NOT NULL,
  "html_source" TEXT DEFAULT '',
  "css_source"  TEXT DEFAULT '',
  "js_source"   TEXT DEFAULT '',
  "created_at"  TEXT NOT NULL DEFAULT (datetime('now')),
  "updated_at"  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS "pages" (
  "id"           INTEGER PRIMARY KEY AUTOINCREMENT,
  "site_id"      INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
  "template_id"  INTEGER DEFAULT NULL REFERENCES templates(id) ON DELETE SET NULL,
  "title"        TEXT NOT NULL,
  "slug"         TEXT NOT NULL,
  "url_path"     TEXT NOT NULL DEFAULT '',
  "language"     TEXT NOT NULL DEFAULT 'cs',
  "status"       TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','published')),
  "published_at" TEXT DEFAULT NULL,
  "created_at"   TEXT NOT NULL DEFAULT (datetime('now')),
  "updated_at"   TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE("site_id","slug")
);

CREATE TABLE IF NOT EXISTS "content_blocks" (
  "id"         INTEGER PRIMARY KEY AUTOINCREMENT,
  "page_id"    INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
  "block_key"  TEXT NOT NULL,
  "block_type" TEXT NOT NULL DEFAULT 'text' CHECK(block_type IN ('text','textarea','richtext','image','link','html')),
  "label"      TEXT DEFAULT NULL,
  "content"    TEXT DEFAULT '',
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT NOT NULL DEFAULT (datetime('now')),
  "updated_at" TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE("page_id","block_key")
);

CREATE TABLE IF NOT EXISTS "media" (
  "id"                INTEGER PRIMARY KEY AUTOINCREMENT,
  "site_id"           INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
  "original_filename" TEXT NOT NULL,
  "storage_path"      TEXT NOT NULL,
  "alt_text"          TEXT DEFAULT NULL,
  "title"             TEXT DEFAULT NULL,
  "width"             INTEGER DEFAULT NULL,
  "height"            INTEGER DEFAULT NULL,
  "mime_type"         TEXT NOT NULL,
  "created_at"        TEXT NOT NULL DEFAULT (datetime('now')),
  "updated_at"        TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS "media_variants" (
  "id"         INTEGER PRIMARY KEY AUTOINCREMENT,
  "media_id"   INTEGER NOT NULL REFERENCES media(id) ON DELETE CASCADE,
  "format"     TEXT NOT NULL,
  "width"      INTEGER DEFAULT NULL,
  "height"     INTEGER DEFAULT NULL,
  "path"       TEXT NOT NULL,
  "filesize"   INTEGER DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS "seo_settings" (
  "id"                  INTEGER PRIMARY KEY AUTOINCREMENT,
  "page_id"             INTEGER NOT NULL UNIQUE REFERENCES pages(id) ON DELETE CASCADE,
  "meta_title"          TEXT DEFAULT NULL,
  "meta_description"    TEXT DEFAULT NULL,
  "meta_keywords"       TEXT DEFAULT NULL,
  "canonical_url"       TEXT DEFAULT NULL,
  "robots_index"        INTEGER NOT NULL DEFAULT 1,
  "robots_follow"       INTEGER NOT NULL DEFAULT 1,
  "og_title"            TEXT DEFAULT NULL,
  "og_description"      TEXT DEFAULT NULL,
  "og_image_id"         INTEGER DEFAULT NULL REFERENCES media(id) ON DELETE SET NULL,
  "twitter_title"       TEXT DEFAULT NULL,
  "twitter_description" TEXT DEFAULT NULL,
  "twitter_image_id"    INTEGER DEFAULT NULL REFERENCES media(id) ON DELETE SET NULL,
  "schema_type"         TEXT DEFAULT NULL,
  "schema_json"         TEXT DEFAULT NULL,
  "created_at"          TEXT NOT NULL DEFAULT (datetime('now')),
  "updated_at"          TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS "redirects" (
  "id"          INTEGER PRIMARY KEY AUTOINCREMENT,
  "site_id"     INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
  "from_path"   TEXT NOT NULL,
  "to_path"     TEXT NOT NULL,
  "status_code" INTEGER NOT NULL DEFAULT 301,
  "created_at"  TEXT NOT NULL DEFAULT (datetime('now')),
  "updated_at"  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS "publish_history" (
  "id"         INTEGER PRIMARY KEY AUTOINCREMENT,
  "site_id"    INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
  "user_id"    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  "version"    INTEGER NOT NULL DEFAULT 1,
  "status"     TEXT NOT NULL DEFAULT 'success' CHECK(status IN ('success','error')),
  "notes"      TEXT DEFAULT NULL,
  "created_at" TEXT NOT NULL DEFAULT (datetime('now'))
);
