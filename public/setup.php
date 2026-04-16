<?php
// Setup helper: Initializes DB schema and seeds demo data if needed
require_once '../src/db.php';

$db = getDB();

// Enable foreign keys in SQLite
$db->exec("PRAGMA foreign_keys = ON");

// ── USERS ─────────────────────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    username     TEXT UNIQUE NOT NULL,
    password     TEXT NOT NULL,
    display_name TEXT NOT NULL,
    avatar       TEXT NOT NULL,
    color        TEXT NOT NULL
)");

// ── BOARDS ────────────────────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS boards (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Seed default boards
$boards = [
    ['Main Board'],
    ['Design Board'],
    ['Research Board']
];
$stmt = $db->prepare("INSERT OR IGNORE INTO boards (name) VALUES (?)");
foreach ($boards as $b) {
    $stmt->execute($b);
}

// Get default board id
$defaultBoardId = (int) $db->query("SELECT id FROM boards WHERE name = 'Main Board' LIMIT 1")->fetchColumn();

// ── TASKS ─────────────────────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS tasks (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    board_id    INTEGER NOT NULL DEFAULT 1 REFERENCES boards(id) ON DELETE CASCADE,
    title       TEXT NOT NULL,
    description TEXT DEFAULT '',
    status      TEXT DEFAULT 'todo',
    assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
    priority    TEXT DEFAULT 'mid',
    tags        TEXT DEFAULT '',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS comments (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id     INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body        TEXT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Add board_id to existing tasks table if it does not already exist
$columns = $db->query("PRAGMA table_info(tasks)")->fetchAll(PDO::FETCH_ASSOC);
$hasBoardId = false;

foreach ($columns as $col) {
    if (($col['name'] ?? '') === 'board_id') {
        $hasBoardId = true;
        break;
    }
}

if (!$hasBoardId) {
    $db->exec("ALTER TABLE tasks ADD COLUMN board_id INTEGER NOT NULL DEFAULT 1");
}

// Make sure existing tasks are assigned to a board
$stmt = $db->prepare("UPDATE tasks SET board_id = ? WHERE board_id IS NULL OR board_id = 0");
$stmt->execute([$defaultBoardId]);

// ── SEED USERS ────────────────────────────────────────────────────────────────
$users = [
    ['rachel',  password_hash('password', PASSWORD_DEFAULT), 'Rachel',  'R', '#e8734a'],
    ['keegan',  password_hash('password', PASSWORD_DEFAULT), 'Keegan',  'K', '#4a9ee8'],
    ['nithin',  password_hash('password', PASSWORD_DEFAULT), 'Nithin',  'N', '#7e4ae8'],
    ['charlie', password_hash('password', PASSWORD_DEFAULT), 'Charlie', 'C', '#4ae8a3'],
    ['sid',     password_hash('password', PASSWORD_DEFAULT), 'Sid',     'S', '#e8c84a'],
    ['david',   password_hash('password', PASSWORD_DEFAULT), 'David',   'D', '#e84a4a'],
];
$stmt = $db->prepare("INSERT OR IGNORE INTO users (username, password, display_name, avatar, color) VALUES (?,?,?,?,?)");
foreach ($users as $u) {
    $stmt->execute($u);
}

// ── SEED TASKS ONLY IF EMPTY ─────────────────────────────────────────────────
$count = (int) $db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();

if ($count === 0) {
    $tasks = [
        [$defaultBoardId, 'Design system audit',      'Review all components for consistency with the new design tokens.',  'todo',       1, 'high', 'design,qa'],
        [$defaultBoardId, 'Auth middleware refactor', 'Extract auth logic into a dedicated middleware layer.',               'inprogress', 2, 'crit', 'backend,security'],
        [$defaultBoardId, 'Write API docs',           'Document all public endpoints using OpenAPI 3.1.',                   'inprogress', 3, 'mid',  'docs'],
        [$defaultBoardId, 'Set up CI pipeline',       'Configure GitHub Actions for lint, test, and deploy.',               'done',       4, 'high', 'devops'],
        [$defaultBoardId, 'Mobile responsiveness',    'Fix layout breakpoints for screens below 768px.',                    'todo',       null, 'mid', 'frontend'],
        [$defaultBoardId, 'Database indexing',        'Add missing indexes on the tasks and users tables.',                 'todo',       5, 'low',  'backend,performance'],
        [$defaultBoardId, 'Notification system',      'Build real-time notification dropdown with read/unread states.',     'done',       6, 'crit', 'frontend,realtime'],
    ];

    $stmt = $db->prepare("
        INSERT INTO tasks (board_id, title, description, status, assigned_to, priority, tags)
        VALUES (?,?,?,?,?,?,?)
    ");

    foreach ($tasks as $t) {
        $stmt->execute($t);
    }
}

echo "<!DOCTYPE html><html><head><title>Setup</title>
<style>
body{
    font-family:monospace;
    background:#0a0a0a;
    color:#4ae8a3;
    display:flex;
    align-items:center;
    justify-content:center;
    height:100vh;
    margin:0;
    flex-direction:column;
    gap:16px
}
a{
    color:#e8734a;
    text-decoration:none;
    border:1px solid #e8734a;
    padding:8px 18px;
    border-radius:6px
}
a:hover{background:#e8734a22}
</style></head>
<body>
<div style='font-size:24px;font-weight:700'>✓ Setup complete</div>
<div style='color:#666;font-size:13px'>Database ready · boards created · users seeded · tasks checked</div>
<div style='color:#555;font-size:12px;margin-top:8px'>
Login with any username: rachel / keegan / nithin / charlie / sid / david<br>
Password for all: <span style='color:#888'>password</span>
</div>
<a href='/login.php'>→ Go to Login</a>
</body></html>";