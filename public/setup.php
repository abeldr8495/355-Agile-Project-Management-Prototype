<?php
// Setup bootstraps a brand-new local demo environment.
// Keep this file idempotent because teammates will use it to recover from a
// deleted test database while they are developing features in parallel.
require_once '../src/db.php';

$db = getDB();
$db->exec("PRAGMA foreign_keys = ON");

// ── USERS ──────────────────────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    username     TEXT UNIQUE NOT NULL,
    password     TEXT NOT NULL,
    display_name TEXT NOT NULL,
    avatar       TEXT NOT NULL,
    color        TEXT NOT NULL,
    role         TEXT NOT NULL DEFAULT 'member',
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Migrate: add missing columns on existing installs
$userCols = array_column($db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC), 'name');
if (!in_array('role', $userCols))       $db->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'member'");
if (!in_array('created_at', $userCols)) $db->exec("ALTER TABLE users ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");

// ── BOARDS ────────────────────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS boards (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Seed only one starter board by default. Extra demo boards made it hard to tell
// whether collaborators were seeing a real bug or just seeded test data.
$defaultBoards = ['Main Board'];
$boardCount = (int) $db->query("SELECT COUNT(*) FROM boards")->fetchColumn();
if ($boardCount === 0) {
    $bStmt = $db->prepare("INSERT INTO boards (name) VALUES (?)");
    foreach ($defaultBoards as $bName) $bStmt->execute([$bName]);
}

$defaultBoardId = (int) $db->query("SELECT id FROM boards WHERE name = 'Main Board' LIMIT 1")->fetchColumn();

// ── BOARD COLUMNS ─────────────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS board_columns (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    board_id   INTEGER NOT NULL REFERENCES boards(id) ON DELETE CASCADE,
    name       TEXT NOT NULL,
    status_key TEXT NOT NULL,
    color      TEXT NOT NULL DEFAULT '#888888',
    position   INTEGER NOT NULL DEFAULT 0,
    UNIQUE(board_id, status_key)
)");

// Seed default columns for every board that has none.
// This lets newly created databases and older databases converge on the same
// board_columns structure without overwriting custom boards.
$allBoards = $db->query("SELECT id FROM boards")->fetchAll(PDO::FETCH_COLUMN);
$colStmt   = $db->prepare(
    "INSERT OR IGNORE INTO board_columns (board_id, name, status_key, color, position) VALUES (?,?,?,?,?)"
);
$defaultCols = [
    ['To Do',       'todo',       '#666666', 0],
    ['In Progress', 'inprogress', '#e8c84a', 1],
    ['Done',        'done',       '#4ae8a3', 2],
];
foreach ($allBoards as $bid) {
    $chk = $db->prepare("SELECT COUNT(*) FROM board_columns WHERE board_id = ?");
    $chk->execute([$bid]);
    if ((int) $chk->fetchColumn() === 0) {
        foreach ($defaultCols as [$name, $key, $color, $pos]) {
            $colStmt->execute([$bid, $name, $key, $color, $pos]);
        }
    }
}

// ── TASKS ─────────────────────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS tasks (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    board_id     INTEGER NOT NULL DEFAULT 1 REFERENCES boards(id) ON DELETE CASCADE,
    title        TEXT NOT NULL,
    description  TEXT DEFAULT '',
    status       TEXT DEFAULT 'todo',
    assigned_to  INTEGER REFERENCES users(id) ON DELETE SET NULL,
    priority     TEXT DEFAULT 'mid',
    tags         TEXT DEFAULT '',
    story_points INTEGER DEFAULT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$taskCols = array_column($db->query("PRAGMA table_info(tasks)")->fetchAll(PDO::FETCH_ASSOC), 'name');
if (!in_array('board_id', $taskCols))     $db->exec("ALTER TABLE tasks ADD COLUMN board_id INTEGER NOT NULL DEFAULT 1");
if (!in_array('story_points', $taskCols)) $db->exec("ALTER TABLE tasks ADD COLUMN story_points INTEGER DEFAULT NULL");

// ── COMMENTS ──────────────────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS comments (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id    INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body       TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// ── ATTACHMENTS ───────────────────────────────────────────────────────────────
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

// Ensure the local upload directory exists on fresh or reset installs.
getUploadsDir();

// Fix tasks that were created without a board reference on older installs.
$db->prepare("UPDATE tasks SET board_id = ? WHERE board_id IS NULL OR board_id = 0")
   ->execute([$defaultBoardId]);

// ── SEED USERS ────────────────────────────────────────────────────────────────
// Add default demo users. Uses INSERT OR IGNORE to preserve existing accounts.
$users = [
    // sysadmin — stored in DB with role 'sysadmin' but auth.php enforces it by username
    ['sysadmin', password_hash('sysadmin123', PASSWORD_DEFAULT), 'System Admin', 'SA', '#ff4444', 'sysadmin'],
    ['rachel',   password_hash('password',    PASSWORD_DEFAULT), 'Rachel',       'R',  '#e8734a', 'member'],
    ['keegan',   password_hash('password',    PASSWORD_DEFAULT), 'Keegan',       'K',  '#4a9ee8', 'member'],
    ['nithin',   password_hash('password',    PASSWORD_DEFAULT), 'Nithin',       'N',  '#7e4ae8', 'member'],
    ['charlie',  password_hash('password',    PASSWORD_DEFAULT), 'Charlie',      'C',  '#4ae8a3', 'member'],
    ['sid',      password_hash('password',    PASSWORD_DEFAULT), 'Sid',          'S',  '#e8c84a', 'member'],
    ['david',    password_hash('password',    PASSWORD_DEFAULT), 'David',        'D',  '#e84a4a', 'admin'],
];
$uStmt = $db->prepare(
    "INSERT OR IGNORE INTO users (username, password, display_name, avatar, color, role) VALUES (?,?,?,?,?,?)"
);
foreach ($users as $u) $uStmt->execute($u);

// Idempotent: ensure roles are correct if users already existed
$db->exec("UPDATE users SET role = 'sysadmin' WHERE username = 'sysadmin'");
$db->exec("UPDATE users SET role = 'admin'    WHERE username = 'david'");
// Downgrade rachel if she was previously set to admin
$db->exec("UPDATE users SET role = 'member'   WHERE username = 'rachel' AND role = 'admin'");

// ── SEED TASKS ────────────────────────────────────────────────────────────────
$count = (int) $db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
if ($count === 0) {
    $tasks = [
        [$defaultBoardId, 'Design system audit',     'Review all components for consistency with the new design tokens.',  'todo',       1, 'high', 'design,qa',           3],
        [$defaultBoardId, 'Auth middleware refactor', 'Extract auth logic into a dedicated middleware layer.',              'inprogress', 2, 'crit', 'backend,security',    8],
        [$defaultBoardId, 'Write API docs',           'Document all public endpoints using OpenAPI 3.1.',                  'inprogress', 3, 'mid',  'docs',                5],
        [$defaultBoardId, 'Set up CI pipeline',       'Configure GitHub Actions for lint, test, and deploy.',              'done',       4, 'high', 'devops',              5],
        [$defaultBoardId, 'Mobile responsiveness',    'Fix layout breakpoints for screens below 768px.',                   'todo',    null, 'mid',  'frontend',            3],
        [$defaultBoardId, 'Database indexing',        'Add missing indexes on the tasks and users tables.',                'todo',       5, 'low',  'backend,performance', 2],
        [$defaultBoardId, 'Notification system',      'Build real-time notification dropdown with read/unread states.',    'done',       6, 'crit', 'frontend,realtime',   13],
    ];
    $tStmt = $db->prepare(
        "INSERT INTO tasks (board_id, title, description, status, assigned_to, priority, tags, story_points) VALUES (?,?,?,?,?,?,?,?)"
    );
    foreach ($tasks as $t) $tStmt->execute($t);
}

echo "<!DOCTYPE html><html><head><title>Setup</title>
<style>
body{font-family:monospace;background:#0a0a0a;color:#4ae8a3;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;flex-direction:column;gap:16px}
a{color:#e8734a;text-decoration:none;border:1px solid #e8734a;padding:8px 18px;border-radius:6px}
a:hover{background:#e8734a22}
table{border-collapse:collapse;font-size:12px;margin-top:8px}
th{color:#666;text-align:left;padding:4px 16px 4px 0;font-size:10px;letter-spacing:.1em;text-transform:uppercase}
td{color:#888;padding:3px 16px 3px 0}
td:first-child{color:#e0e0e0}
.sysadmin{color:#ff4444!important}
.admin{color:#e8734a!important}
</style></head>
<body>
<div style='font-size:24px;font-weight:700'>✓ Setup complete</div>
<div style='color:#666;font-size:13px'>Database ready · boards + columns seeded · users created · tasks checked</div>
<table>
<tr><th>Username</th><th>Password</th><th>Role</th></tr>
<tr><td class='sysadmin'>sysadmin</td><td>sysadmin123</td><td class='sysadmin'>sysadmin</td></tr>
<tr><td class='admin'>david</td><td>password</td><td class='admin'>admin</td></tr>
<tr><td>rachel / keegan / nithin / charlie / sid</td><td>password</td><td>member</td></tr>
</table>
<a href='login.php'>→ Go to Login</a>
</body></html>";
