<?php
// Login page controller
// - Redirects to board when already authenticated
// - Handles login form POST and sets user session on success
require_once '../src/auth.php';
startSession();
if (!empty($_SESSION['user_id'])) { header('Location: /'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login($username, $password)) {
        header('Location: /');
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agile Board — Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600;700&family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0a0a0a;color:#e0e0e0;font-family:'IBM Plex Mono','Courier New',monospace;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#111;border:1px solid #1e1e1e;border-radius:12px;padding:40px;width:360px;display:flex;flex-direction:column;gap:24px}
.logo{text-align:center}
.logo-mark{font-family:'DM Sans',sans-serif;font-size:22px;font-weight:700;color:#e0e0e0;letter-spacing:-.01em}
.logo-mark span{color:#e8734a}
.logo-sub{font-size:9px;letter-spacing:.18em;color:#444;text-transform:uppercase;margin-top:4px}
.form-group{display:flex;flex-direction:column;gap:6px}
label{font-size:9px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#444}
input{background:#0d0d0d;border:1px solid #222;border-radius:7px;color:#ccc;font-family:inherit;font-size:12px;padding:10px 13px;outline:none;transition:border-color .15s;width:100%}
input:focus{border-color:#e8734a}
.btn{background:#e8734a;border:none;border-radius:7px;color:#fff;cursor:pointer;font-family:inherit;font-size:11px;font-weight:700;letter-spacing:.08em;padding:11px;transition:opacity .15s;width:100%}
.btn:hover{opacity:.85}
.error{background:#e84a4a18;border:1px solid #e84a4a44;border-radius:6px;color:#e84a4a;font-size:11px;padding:9px 12px;text-align:center}
.hint{background:#ffffff06;border:1px solid #1e1e1e;border-radius:6px;font-size:10px;color:#555;padding:10px 12px;line-height:1.8}
.hint strong{color:#888;font-weight:600}
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-mark"><span>▸</span> Agile Board</div>
        <div class="logo-sub">Final Edition</div>
    </div>

    <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <div style="display:flex;flex-direction:column;gap:14px">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="alex" autocomplete="username" required
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn">Sign In →</button>
        </div>
    </form>

    <div class="hint">
        <strong>Demo accounts</strong><br>
        Username: <strong>rachel</strong>, <strong>keegan</strong>, <strong>nithin</strong>, <strong>charlie</strong>, <strong>sid</strong>, or <strong>david</strong><br>
        Password: <strong>password</strong>
    </div>
</div>
</body>
</html>
