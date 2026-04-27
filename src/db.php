<?php
/**
 * Database helper module.
 *
 * This file is intentionally small because every page and API endpoint reaches
 * for it. The important detail is that SQLite connection-level settings, like
 * foreign key enforcement, have to be turned on for each new request.
 */
function getDB(): PDO {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $db = new PDO('sqlite:' . $dir . '/agile.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');
    return $db;
}

/**
 * Local attachment storage lives under data/uploads so the prototype stays
 * self-contained and does not need an external object store.
 */
function getUploadsDir(): string {
    $dir = __DIR__ . '/../data/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

/**
 * Attachments were added after the first round of the prototype, so the newer
 * endpoints can lazily ensure the table exists for older local databases.
 */
function ensureAttachmentsTable(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS attachments (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        task_id       INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
        uploaded_by   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        original_name TEXT NOT NULL,
        stored_name   TEXT NOT NULL UNIQUE,
        mime_type     TEXT NOT NULL DEFAULT 'application/octet-stream',
        size_bytes    INTEGER NOT NULL DEFAULT 0,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}
