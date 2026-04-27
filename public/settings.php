<?php
// Account settings — theme, display name, avatar/color, password
require_once '../src/auth.php';
require_once '../src/db.php';
requireLogin();

$user = currentUser();
$db   = getDB();
$basePath = appBasePath();

$msg   = '';
$error = '';

$ACCENT_COLORS = [
    '#e8734a', '#4a9ee8', '#7e4ae8', '#4ae8a3',
    '#e8c84a', '#e84a4a', '#e84a9e', '#4ae8e8',
    '#a3e84a', '#e8a34a',
];

$THEMES = [
    ['id' => 'dark',   'label' => 'Dark',   'desc' => 'Classic dark industrial'],
    ['id' => 'light',  'label' => 'Light',  'desc' => 'Clean light mode'],
    ['id' => 'midnight','label' => 'Midnight','desc' => 'Deep blue-black'],
    ['id' => 'forest', 'label' => 'Forest', 'desc' => 'Muted green tones'],
    ['id' => 'rose',   'label' => 'Rose',   'desc' => 'Warm pink theme'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which settings action the user submitted.
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $display_name = trim($_POST['display_name'] ?? '');
        $avatar       = strtoupper(trim($_POST['avatar'] ?? ''));
        $color        = $_POST['color'] ?? $user['color'];

        if (!$display_name) {
            $error = 'Display name cannot be empty.';
        } elseif (strlen($avatar) < 1 || strlen($avatar) > 2) {
            $error = 'Avatar must be 1–2 characters.';
        } elseif (!in_array($color, $ACCENT_COLORS, true)) {
            $error = 'Invalid color selected.';
        } else {
            $db->prepare("UPDATE users SET display_name = ?, avatar = ?, color = ? WHERE id = ?")
               ->execute([$display_name, $avatar, $color, $user['id']]);
            // Clear session cache so currentUser() re-fetches the updated profile data.
            unset($_SESSION['user']);
            $msg = 'Profile updated!';
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $row = $db->prepare("SELECT password FROM users WHERE id = ?");
        $row->execute([$user['id']]);
        $hash = $row->fetchColumn();

        if (!password_verify($current, $hash)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")
               ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $msg = 'Password changed successfully.';
        }
    }
}

// Re-fetch user after possible update so the page displays the freshest profile state.
$stmt = $db->prepare("SELECT id, username, display_name, avatar, color, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agile Board — Settings</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars(appPath('assets/css/style.css')) ?>">
<style>
.settings-wrap { max-width: 680px; margin: 0 auto; padding: 32px 24px; }
.settings-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 32px; border-bottom: 1px solid var(--border); padding-bottom: 20px;
}
.settings-title { font-family: var(--font-sans); font-size: 20px; font-weight: 700; }
.settings-title span { color: var(--accent); }
.back-link {
    font-size: 11px; color: var(--text-3); text-decoration: none;
    display: flex; align-items: center; gap: 6px; transition: color .15s;
}
.back-link:hover { color: var(--accent); }
.section-card {
    background: var(--bg-3); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 24px; margin-bottom: 20px;
}
.section-card-title {
    font-size: 9px; font-weight: 800; letter-spacing: .15em;
    text-transform: uppercase; color: var(--text-4); margin-bottom: 20px;
}
.form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.form-group:last-child { margin-bottom: 0; }
.form-row { display: flex; gap: 14px; }
.form-row .form-group { flex: 1; }
.field-label-sm {
    font-size: 9px; font-weight: 700; letter-spacing: .12em;
    text-transform: uppercase; color: var(--text-4);
}
.btn-save {
    background: var(--accent); border: none; border-radius: var(--radius);
    color: #fff; cursor: pointer; font-family: var(--font-mono);
    font-size: 11px; font-weight: 700; letter-spacing: .08em;
    padding: 10px 22px; transition: opacity .15s; margin-top: 4px;
}
.btn-save:hover { opacity: .85; }
.flash { padding: 10px 14px; border-radius: 7px; font-size: 11px; margin-bottom: 18px; }
.flash.ok  { background: #4ae8a318; border: 1px solid #4ae8a366; color: #4ae8a3; }
.flash.err { background: #e84a4a18; border: 1px solid #e84a4a66; color: #f07070; }

/* Avatar preview */
.avatar-preview {
    width: 52px; height: 52px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 20px; border: 2px solid currentColor;
    flex-shrink: 0; transition: background .2s, color .2s;
}
.avatar-row { display: flex; gap: 14px; align-items: center; }
.avatar-row .form-group { flex: 1; margin-bottom: 0; }
.color-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.swatch-wrap { position: relative; }
.swatch-wrap input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
.swatch-wrap .dot {
    width: 26px; height: 26px; border-radius: 50%; cursor: pointer;
    border: 3px solid transparent; display: block;
    transition: transform .12s, border-color .12s;
}
.swatch-wrap input[type="radio"]:checked + .dot {
    border-color: var(--text); transform: scale(1.18);
}

/* Theme grid */
.theme-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px,1fr)); gap: 10px; }
.theme-card {
    border: 2px solid var(--border); border-radius: 9px; padding: 12px 10px;
    cursor: pointer; transition: border-color .15s; text-align: center; position: relative;
}
.theme-card:hover { border-color: var(--accent)88; }
.theme-card.selected { border-color: var(--accent); }
.theme-card input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
.theme-card-name { font-size: 11px; font-weight: 700; margin-bottom: 2px; }
.theme-card-desc { font-size: 9px; color: var(--text-4); }
.theme-swatch-row { display: flex; gap: 4px; justify-content: center; margin-bottom: 8px; }
.theme-swatch { width: 14px; height: 14px; border-radius: 50%; }

/* Theme color palettes (preview swatches) */
.theme-palettes {
    dark:     ['#0a0a0a','#e8734a','#4ae8a3'],
    light:    ['#eef2f7','#d9653f','#2fb879'],
    midnight: ['#050914','#5b8dee','#6ee7b7'],
    forest:   ['#0c120c','#7bc47f','#a3e84a'],
    rose:     ['#130a0d','#e84a9e','#e8734a'],
}
@media (max-width: 600px) {
    .settings-wrap { padding: 20px 14px; }
    .settings-card { padding: 18px 14px !important; }
    .theme-options { grid-template-columns: repeat(2, 1fr) !important; }
    .swatch-row { flex-wrap: wrap !important; }
}
</style>
</head>
<body>

<div class="settings-wrap">
    <div class="settings-header">
        <div class="settings-title"><span>▸</span> Account Settings</div>
        <a href="<?= htmlspecialchars(appPath('index.php')) ?>" class="back-link">← Back to Board</a>
    </div>

    <?php if ($msg): ?><div class="flash ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="flash err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- ── Profile ── -->
    <div class="section-card">
        <div class="section-card-title">Profile</div>

        <form method="POST">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <div class="field-label-sm">Avatar &amp; Color</div>
                <div class="avatar-row">
                    <div class="avatar-preview" id="avatar-preview"
                         style="color:<?= htmlspecialchars($user['color']) ?>;background:<?= htmlspecialchars($user['color']) ?>22;border-color:<?= htmlspecialchars($user['color']) ?>">
                        <span id="avatar-preview-text"><?= htmlspecialchars($user['avatar']) ?></span>
                    </div>
                    <div class="form-group" style="margin-bottom:0;flex:1">
                        <label class="field-label-sm" for="avatar-inp">Avatar (1–2 chars)</label>
                        <input type="text" id="avatar-inp" name="avatar"
                               maxlength="2" style="text-transform:uppercase"
                               value="<?= htmlspecialchars($user['avatar']) ?>">
                    </div>
                </div>
                <div class="color-row" id="color-row">
                    <?php foreach ($ACCENT_COLORS as $c): ?>
                    <label class="swatch-wrap" title="<?= htmlspecialchars($c) ?>">
                        <input type="radio" name="color" value="<?= htmlspecialchars($c) ?>"
                               <?= ($user['color'] === $c) ? 'checked' : '' ?>>
                        <span class="dot" style="background:<?= htmlspecialchars($c) ?>"></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="field-label-sm" for="display-name">Display Name</label>
                    <input type="text" id="display-name" name="display_name" maxlength="64"
                           value="<?= htmlspecialchars($user['display_name']) ?>">
                </div>
                <div class="form-group">
                    <label class="field-label-sm">Username</label>
                    <input type="text" value="@<?= htmlspecialchars($user['username']) ?>" disabled style="opacity:.5;cursor:not-allowed">
                </div>
            </div>

            <button type="submit" class="btn-save">Save Profile</button>
        </form>
    </div>

    <!-- ── Theme ── -->
    <div class="section-card">
        <div class="section-card-title">Board Theme</div>

        <div class="theme-grid" id="theme-grid">
            <?php foreach ($THEMES as $th): ?>
            <label class="theme-card" id="theme-card-<?= $th['id'] ?>">
                <input type="radio" name="theme_pick" value="<?= $th['id'] ?>">
                <div class="theme-swatch-row" id="swatches-<?= $th['id'] ?>">
                    <!-- Filled by JS -->
                </div>
                <div class="theme-card-name"><?= $th['label'] ?></div>
                <div class="theme-card-desc"><?= $th['desc'] ?></div>
            </label>
            <?php endforeach; ?>
        </div>

        <?php if (in_array($user['role'], ['admin', 'sysadmin'], true)): ?>
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
            <a href="<?= htmlspecialchars(appPath('admin.php')) ?>" style="font-size:11px;color:var(--accent);text-decoration:none">
                ⚙ Admin Panel →
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Password ── -->
    <div class="section-card">
        <div class="section-card-title">Change Password</div>

        <form method="POST">
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label class="field-label-sm" for="current-pw">Current Password</label>
                <input type="password" id="current-pw" name="current_password" autocomplete="current-password">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="field-label-sm" for="new-pw">New Password</label>
                    <input type="password" id="new-pw" name="new_password" autocomplete="new-password" minlength="8">
                </div>
                <div class="form-group">
                    <label class="field-label-sm" for="confirm-pw">Confirm New Password</label>
                    <input type="password" id="confirm-pw" name="confirm_password" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="btn-save">Change Password</button>
        </form>
    </div>
</div>

<script>
// ── Theme palettes (bg, accent, green)
const PALETTES = {
    dark:     ['#0a0a0a', '#e8734a', '#4ae8a3'],
    light:    ['#eef2f7', '#d9653f', '#2fb879'],
    midnight: ['#050914', '#5b8dee', '#6ee7b7'],
    forest:   ['#0c120c', '#7bc47f', '#a3e84a'],
    rose:     ['#130a0d', '#e84a9e', '#e8734a'],
};

// Render swatch previews
for (const [id, colors] of Object.entries(PALETTES)) {
    const el = document.getElementById('swatches-' + id);
    if (!el) continue;
    el.innerHTML = colors.map(c => `<div class="theme-swatch" style="background:${c}"></div>`).join('');
}

// Load saved theme
const savedTheme = localStorage.getItem('theme') || 'dark';
applyTheme(savedTheme);

// Mark selected card
function markSelected(themeId) {
    document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('selected'));
    const card = document.getElementById('theme-card-' + themeId);
    if (card) card.classList.add('selected');
    const radio = card?.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;
}
markSelected(savedTheme);

// Theme card clicks
document.getElementById('theme-grid').addEventListener('click', (e) => {
    const card = e.target.closest('.theme-card');
    if (!card) return;
    const id = card.id.replace('theme-card-', '');
    applyTheme(id);
    markSelected(id);
});

function applyTheme(id) {
    const body = document.body;
    // Remove all theme classes
    body.classList.remove('light-mode', 'midnight-mode', 'forest-mode', 'rose-mode');
    localStorage.setItem('theme', id);
    if (id === 'light')    body.classList.add('light-mode');
    if (id === 'midnight') body.classList.add('midnight-mode');
    if (id === 'forest')   body.classList.add('forest-mode');
    if (id === 'rose')     body.classList.add('rose-mode');
}

// Apply on load (before paint)
applyTheme(savedTheme);

// Avatar/color preview
const avatarInp  = document.getElementById('avatar-inp');
const preview    = document.getElementById('avatar-preview');
const previewTxt = document.getElementById('avatar-preview-text');
const colorRadios = document.querySelectorAll('input[name="color"]');

function getColor() {
    for (const r of colorRadios) if (r.checked) return r.value;
    return '#e8734a';
}
function updatePreview() {
    const val = (avatarInp.value || '?').toUpperCase().slice(0,2);
    const col = getColor();
    previewTxt.textContent = val;
    preview.style.color = col;
    preview.style.background = col + '22';
    preview.style.borderColor = col;
}
avatarInp.addEventListener('input', updatePreview);
colorRadios.forEach(r => r.addEventListener('change', updatePreview));
updatePreview();
</script>
</body>
</html>
