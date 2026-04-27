<?php
// Registration page — allows new users to create an account
require_once '../src/auth.php';
require_once '../src/db.php';
startSession();

// If already logged in, bounce to board
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . appPath('index.php'));
    exit;
}

$error   = '';
$success = '';

$ACCENT_COLORS = [
    '#e8734a', '#4a9ee8', '#7e4ae8', '#4ae8a3',
    '#e8c84a', '#e84a4a', '#e84a9e', '#4ae8e8',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept registration form input, validate each field, and create a new member.
    $username     = trim($_POST['username'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm_password'] ?? '';
    $avatar       = strtoupper(trim($_POST['avatar'] ?? ''));
    $color        = $_POST['color'] ?? $ACCENT_COLORS[0];

    // Validation
    if (!$username || !$display_name || !$password || !$confirm) {
        $error = 'All fields are required.';
    } elseif (!preg_match('/^[a-z0-9_]{3,32}$/', $username)) {
        $error = 'Username must be 3–32 lowercase letters, numbers, or underscores.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!$avatar || strlen($avatar) > 2) {
        $error = 'Avatar must be 1–2 characters.';
    } elseif (!in_array($color, $ACCENT_COLORS, true)) {
        $color = $ACCENT_COLORS[0]; // silently sanitize invalid choices
    } else {
        try {
            $db   = getDB();
            // Create a new user record with member-level privileges.
            $stmt = $db->prepare(
                "INSERT INTO users (username, password, display_name, avatar, color, role)
                 VALUES (?, ?, ?, ?, ?, 'member')"
            );
            $stmt->execute([
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $display_name,
                $avatar,
                $color,
            ]);
            $success = 'Account created! You can now <a href="' . htmlspecialchars(appPath('login.php'), ENT_QUOTES, 'UTF-8') . '">sign in</a>.';
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'UNIQUE') !== false) {
                $error = 'That username is already taken.';
            } else {
                $error = 'Something went wrong — please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agile Board — Create Account</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600;700&family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg:     #0a0a0a;
    --bg-2:   #111;
    --bg-3:   #161616;
    --border: #1e1e1e;
    --border-2: #2a2a2a;
    --text:   #e0e0e0;
    --text-2: #aaa;
    --text-3: #666;
    --accent: #e8734a;
    --green:  #4ae8a3;
    --red:    #e84a4a;
    --radius: 8px;
    --font-mono: 'IBM Plex Mono', monospace;
    --font-sans: 'DM Sans', sans-serif;
}
body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--font-mono);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}
.card {
    background: var(--bg-2);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 40px 36px;
    width: 100%;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    gap: 22px;
}
.logo { text-align: center; }
.logo-mark { font-family: var(--font-sans); font-size: 22px; font-weight: 700; letter-spacing: -.01em; }
.logo-mark span { color: var(--accent); }
.logo-sub { font-size: 9px; letter-spacing: .18em; color: #444; text-transform: uppercase; margin-top: 4px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
label { font-size: 9px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #888; }
input[type="text"], input[type="password"] {
    background: #0d0d0d;
    border: 1px solid #2a2a2a;
    border-radius: var(--radius);
    color: var(--text);
    font-family: var(--font-mono);
    font-size: 12px;
    padding: 10px 13px;
    outline: none;
    transition: border-color .15s;
    width: 100%;
}
input:focus { border-color: var(--accent); }
.btn {
    background: var(--accent);
    border: none;
    border-radius: var(--radius);
    color: #fff;
    cursor: pointer;
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    padding: 12px;
    transition: opacity .15s;
    width: 100%;
}
.btn:hover { opacity: .85; }
.error {
    background: #e84a4a18;
    border: 1px solid #e84a4a66;
    border-radius: 6px;
    color: #f07070;
    font-size: 11px;
    padding: 9px 12px;
}
.success {
    background: #4ae8a318;
    border: 1px solid #4ae8a366;
    border-radius: 6px;
    color: #4ae8a3;
    font-size: 11px;
    padding: 9px 12px;
}
.success a { color: var(--accent); text-decoration: underline; }
.divider { height: 1px; background: var(--border); }
.footer-link { text-align: center; font-size: 10px; color: var(--text-3); }
.footer-link a { color: var(--accent); text-decoration: none; }
.footer-link a:hover { text-decoration: underline; }

/* Color picker row */
.color-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 4px;
}
.color-swatch {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 3px solid transparent;
    cursor: pointer;
    transition: transform .15s, border-color .15s;
    appearance: none;
    -webkit-appearance: none;
}
.color-swatch:checked + label,
input[name="color"][type="radio"]:checked ~ .swatch-label {
    border-color: white;
}
.swatch-wrap { position: relative; display: flex; flex-direction: column; align-items: center; }
.swatch-wrap input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
.swatch-wrap .dot {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    border: 3px solid transparent;
    transition: transform .12s, border-color .12s;
}
.swatch-wrap input[type="radio"]:checked + .dot {
    border-color: #fff;
    transform: scale(1.15);
}

/* Avatar preview */
.avatar-preview {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
    border: 2px solid currentColor;
    flex-shrink: 0;
    transition: background .2s, color .2s;
}
.avatar-row {
    display: flex;
    gap: 12px;
    align-items: center;
}
.avatar-row input { flex: 1; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-mark"><span>▸</span> Agile Board</div>
        <div class="logo-sub">Create Account</div>
    </div>

    <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="success"><?= $success ?></div>
    <?php else: ?>

    <form method="POST" novalidate>
        <div style="display:flex;flex-direction:column;gap:16px">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="lowercase, no spaces"
                       autocomplete="username" maxlength="32"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="display_name">Display Name</label>
                <input type="text" id="display_name" name="display_name" placeholder="How you appear on the board"
                       maxlength="64"
                       value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Avatar &amp; Color</label>
                <div class="avatar-row">
                    <div class="avatar-preview" id="avatar-preview" style="background:#e8734a22;color:#e8734a">
                        <span id="avatar-preview-text">?</span>
                    </div>
                    <input type="text" id="avatar-input" name="avatar" placeholder="1–2 chars, e.g. R"
                           maxlength="2" autocomplete="off" style="text-transform:uppercase"
                           value="<?= htmlspecialchars($_POST['avatar'] ?? '') ?>">
                </div>
                <div class="color-row" id="color-row" style="margin-top:8px">
                    <?php foreach ($ACCENT_COLORS as $c): ?>
                    <label class="swatch-wrap" title="<?= htmlspecialchars($c) ?>">
                        <input type="radio" name="color" value="<?= htmlspecialchars($c) ?>"
                               <?= (($_POST['color'] ?? $ACCENT_COLORS[0]) === $c) ? 'checked' : '' ?>>
                        <span class="dot" style="background:<?= htmlspecialchars($c) ?>"></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Minimum 8 characters"
                       autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password"
                       autocomplete="new-password">
            </div>

            <button type="submit" class="btn">Create Account →</button>
        </div>
    </form>

    <?php endif; ?>

    <div class="divider"></div>
    <div class="footer-link">Already have an account? <a href="<?= htmlspecialchars(appPath('login.php')) ?>">Sign in</a></div>
</div>

<script>
// Live avatar preview
const avatarInput   = document.getElementById('avatar-input');
const avatarPreview = document.getElementById('avatar-preview');
const avatarText    = document.getElementById('avatar-preview-text');
const colorRadios   = document.querySelectorAll('input[name="color"]');

function getSelectedColor() {
    for (const r of colorRadios) if (r.checked) return r.value;
    return '#e8734a';
}

function updatePreview() {
    const val   = (avatarInput.value || '?').toUpperCase().slice(0, 2);
    const color = getSelectedColor();
    avatarText.textContent         = val;
    avatarPreview.style.color      = color;
    avatarPreview.style.background = color + '22';
    avatarPreview.style.borderColor = color;
}

avatarInput.addEventListener('input', updatePreview);
colorRadios.forEach(r => r.addEventListener('change', updatePreview));
updatePreview();
</script>
</body>
</html>
