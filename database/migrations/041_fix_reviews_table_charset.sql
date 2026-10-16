-- Migration 012 created `reviews` without an explicit charset, so the table
-- inherits the database default. On hosts whose default is utf8mb3
-- (e.g. Active24) every bound utf8mb4 parameter then fails with
-- error 3988 "Conversion from collation utf8mb4_0900_ai_ci into
-- utf8mb3_unicode_ci impossible for parameter". Converting to utf8mb4
-- matches all other tables; re-running on an already converted table is a no-op.
ALTER TABLE reviews CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
