<?php
require_once '../src/db.php';

$db = getDB();
$db->exec("PRAGMA foreign_keys = ON");

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    password TEXT,
    display_name TEXT,
    avatar TEXT,
    color TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS boards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE
)");

$db->exec("CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    board_id INTEGER,
    title TEXT,
    description TEXT,
    status TEXT,
    assigned_to INTEGER,
    priority TEXT,
    tags TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER,
    user_id INTEGER,
    body TEXT
)");

echo "Setup complete";