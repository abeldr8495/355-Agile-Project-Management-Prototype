<?php
// Database helper module
// - Ensures the data directory exists
// - Creates/opens an SQLite database at data/agile.db
// - Configures PDO error mode and fetch mode for the app
function getDB(): PDO {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $db = new PDO('sqlite:' . $dir . '/agile.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $db;
}
