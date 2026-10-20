-- Shared mailbox (team@ via IMAP/SMTP): threads, messages, attachments, drafts, reply templates.
-- All timestamps are UTC. Message-ID values are indexed through a SHA-1 hash so the unique key
-- stays short on old MariaDB row formats. author_label is a free-text placeholder until
-- profiles/ACL exist; author_user_id is reserved for them.
CREATE TABLE IF NOT EXISTS mailbox_threads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  subject VARCHAR(500) NOT NULL,
  subject_key CHAR(40) NOT NULL,
  counterparty VARCHAR(255) NOT NULL DEFAULT '',
  snippet VARCHAR(300) NOT NULL DEFAULT '',
  message_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_unread TINYINT(1) NOT NULL DEFAULT 0,
  last_message_at DATETIME NOT NULL,
  last_inbound_at DATETIME NULL,
  last_outbound_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX mailbox_threads_last (last_message_at),
  INDEX mailbox_threads_subject (subject_key, last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mailbox_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  thread_id BIGINT UNSIGNED NOT NULL,
  direction ENUM('in','out') NOT NULL,
  status ENUM('received','sending','sent') NOT NULL DEFAULT 'received',
  folder VARCHAR(100) NULL,
  imap_uid INT UNSIGNED NULL,
  message_id VARCHAR(998) NOT NULL,
  message_id_hash CHAR(40) NOT NULL,
  in_reply_to VARCHAR(998) NULL,
  refs TEXT NULL,
  from_addr VARCHAR(255) NOT NULL DEFAULT '',
  from_name VARCHAR(255) NOT NULL DEFAULT '',
  reply_to VARCHAR(500) NULL,
  to_addrs TEXT NULL,
  cc_addrs TEXT NULL,
  bcc_addrs TEXT NULL,
  subject VARCHAR(500) NOT NULL DEFAULT '',
  body_text MEDIUMTEXT NULL,
  body_html MEDIUMTEXT NULL,
  has_attachments TINYINT(1) NOT NULL DEFAULT 0,
  author_label VARCHAR(100) NULL,
  author_user_id INT UNSIGNED NULL,
  sent_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY mailbox_messages_mid (message_id_hash),
  UNIQUE KEY mailbox_messages_uid (folder, imap_uid),
  INDEX mailbox_messages_thread (thread_id, sent_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mailbox_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  thread_id BIGINT UNSIGNED NULL,
  to_addrs TEXT NULL,
  cc_addrs TEXT NULL,
  bcc_addrs TEXT NULL,
  subject VARCHAR(500) NOT NULL DEFAULT '',
  body MEDIUMTEXT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  author_label VARCHAR(100) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY mailbox_drafts_thread (thread_id),
  INDEX mailbox_drafts_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mailbox_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  message_id BIGINT UNSIGNED NULL,
  draft_id BIGINT UNSIGNED NULL,
  filename VARCHAR(255) NOT NULL,
  mime VARCHAR(150) NOT NULL DEFAULT 'application/octet-stream',
  size INT UNSIGNED NOT NULL DEFAULT 0,
  storage_key CHAR(40) NOT NULL,
  content_id VARCHAR(255) NULL,
  is_inline TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX mailbox_attachments_message (message_id),
  INDEX mailbox_attachments_draft (draft_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mailbox_sync_state (
  folder VARCHAR(100) NOT NULL PRIMARY KEY,
  uidvalidity BIGINT UNSIGNED NULL,
  last_uid INT UNSIGNED NOT NULL DEFAULT 0,
  last_run_at DATETIME NULL,
  last_success_at DATETIME NULL,
  last_error VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mailbox_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
