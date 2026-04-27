<?php
/**
 * Admin panel
 * - sysadmin sees: all users, promote/demote admin, reset passwords, delete users
 * - admin sees:    member management only (reset PW, see roles — cannot change roles)
 */
require_once '../src/auth.php';
require_once '../src/db.php';
requireAdmin(); // admits both admin and sysadmin

$user      = currentUser();
$isSys     = isSysadmin();
$db        = getDB();
$basePath  = appBasePath();

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── sysadmin-only actions ─────────────────────────────────────────────────
    if ($action === 'set_role' && $isSys) {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $role = in_array($_POST['role'] ?? '', ['admin', 'member'], true)
              ? $_POST['role'] : 'member';

        // Cannot demote or change sysadmin
        $target = $db->prepare("SELECT username FROM users WHERE id = ?")->execute([$uid]) && false;
        $tStmt  = $db->prepare("SELECT username FROM users WHERE id = ?");
        $tStmt->execute([$uid]);
        $targetUsername = $tStmt->fetchColumn();

        if ($targetUsername === SYSADMIN_USERNAME) {
            $error = 'The system admin account cannot be modified.';
        } elseif ($uid > 0) {
            $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $uid]);
            $msg = "Role updated to {$role}.";
        }

    } elseif ($action === 'delete_user' && $isSys) {
        $uid = (int)($_POST['user_id'] ?? 0);

        $tStmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $tStmt->execute([$uid]);
        $targetUsername = $tStmt->fetchColumn();

        if ($targetUsername === SYSADMIN_USERNAME) {
            $error = 'The system admin account cannot be deleted.';
        } elseif ($uid === (int)$user['id']) {
            $error = 'You cannot delete your own account here.';
        } elseif ($uid > 0) {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $msg = 'User deleted.';
        }

    // ── both admin and sysadmin can reset passwords ───────────────────────────
    } elseif ($action === 'reset_password') {
        $uid  = (int)($_POST['user_id']    ?? 0);
        $pass = trim($_POST['new_password'] ?? '');

        // Admins (non-sysadmin) can only reset member passwords
        if (!$isSys) {
            $tStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
            $tStmt->execute([$uid]);
            $targetRole = $tStmt->fetchColumn();
            if ($targetRole !== 'member') {
                $error = 'Admins can only reset member passwords.';
                goto respond;
            }
        }

        if (strlen($pass) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($uid > 0) {
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")
               ->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);
            $msg = 'Password reset successfully.';
        }
    }
}

respond:
$allUsers  = $db->query("SELECT id, username, display_name, avatar, color, role, created_at FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$boards    = $db->query("SELECT id, name, created_at FROM boards ORDER BY created_at")->fetchAll(PDO::FETCH_ASSOC);
$taskCnts  = $db->query("SELECT board_id, COUNT(*) as cnt FROM tasks GROUP BY board_id")->fetchAll(PDO::FETCH_ASSOC);
$tcMap     = array_column($taskCnts, 'cnt', 'board_id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agile Board — Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars(appPath('assets/css/style.css')) ?>">
<script>(function(){const t=localStorage.getItem('theme');if(t==='light')document.documentElement.classList.add('light-mode');if(t==='midnight')document.documentElement.classList.add('midnight-mode');if(t==='forest')document.documentElement.classList.add('forest-mode');if(t==='rose')document.documentElement.classList.add('rose-mode');})();</script>
<style>
.admin-wrap{max-width:960px;margin:0 auto;padding:32px 24px}
.admin-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;border-bottom:1px solid var(--border);padding-bottom:20px}
.admin-title{font-family:var(--font-sans);font-size:20px;font-weight:700}
.admin-title span{color:var(--accent)}
.admin-sub{font-size:10px;color:var(--text-4);letter-spacing:.1em;text-transform:uppercase;margin-top:3px}
.back-link{font-size:11px;color:var(--text-3);text-decoration:none;display:flex;align-items:center;gap:6px;transition:color .15s}
.back-link:hover{color:var(--accent)}
.section-card{background:var(--bg-3);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;margin-bottom:24px}
.section-card-title{font-size:9px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:var(--text-4);margin-bottom:18px;display:flex;align-items:center;gap:8px}
.badge-count{background:var(--accent);color:#fff;border-radius:4px;padding:2px 7px;font-size:9px}
table{width:100%;border-collapse:collapse}
th{font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-4);text-align:left;padding:0 0 10px;border-bottom:1px solid var(--border)}
td{padding:10px 8px 10px 0;font-size:11px;color:var(--text-2);border-bottom:1px solid var(--border);vertical-align:middle}
tr:last-child td{border-bottom:none}
.user-cell{display:flex;align-items:center;gap:10px}
.u-avatar{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;border:2px solid currentColor;flex-shrink:0}
.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:9px;font-weight:700;letter-spacing:.07em;text-transform:uppercase}
.badge-sysadmin{background:#ff444422;color:#ff4444;border:1px solid #ff444444}
.badge-admin{background:var(--accent)22;color:var(--accent);border:1px solid var(--accent)44}
.badge-member{background:var(--bg-5);color:var(--text-3);border:1px solid var(--border-3)}
.action-row{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.btn-sm{font-size:10px;font-family:var(--font-mono);padding:5px 10px;border-radius:5px;border:1px solid var(--border-2);background:var(--bg-4);color:var(--text-2);cursor:pointer;transition:background .15s,border-color .15s}
.btn-sm:hover{background:var(--bg-5);border-color:var(--accent);color:var(--accent)}
.btn-sm.danger:hover{border-color:var(--red);color:var(--red)}
select.role-select{background:var(--bg-4);border:1px solid var(--border-2);border-radius:5px;color:var(--text-2);font-family:var(--font-mono);font-size:10px;padding:4px 8px;cursor:pointer}
.flash{padding:10px 14px;border-radius:7px;font-size:11px;margin-bottom:18px}
.flash.ok{background:#4ae8a318;border:1px solid #4ae8a366;color:#4ae8a3}
.flash.err{background:#e84a4a18;border:1px solid #e84a4a66;color:#f07070}
.inline-form{display:inline}
details summary{cursor:pointer;font-size:10px;color:var(--accent);user-select:none}
details[open] summary{margin-bottom:8px}
.reset-form{display:flex;gap:8px;margin-top:8px;align-items:center}
.reset-form input{background:var(--bg-4);border:1px solid var(--border-2);border-radius:5px;color:var(--text-2);font-family:var(--font-mono);font-size:11px;padding:5px 10px;outline:none;flex:1}
.reset-form input:focus{border-color:var(--accent)}
.muted{font-size:10px;color:var(--text-4)}
.sysadmin-locked{font-size:10px;color:var(--text-4);font-style:italic}
.role-note{font-size:9px;color:var(--text-4);margin-top:12px;padding:10px;background:var(--bg-4);border-radius:6px;border:1px solid var(--border-2);line-height:1.7}
</style>
</head>
<body>
<div class="admin-wrap">
    <div class="admin-header">
        <div>
            <div class="admin-title">
                <span>▸</span> <?= $isSys ? 'System Admin Panel' : 'Admin Panel' ?>
            </div>
            <div class="admin-sub">
                Signed in as <?= htmlspecialchars($user['display_name']) ?>
                (<?= htmlspecialchars($user['role']) ?>)
            </div>
        </div>
        <a href="<?= htmlspecialchars(appPath('index.php')) ?>" class="back-link">← Back to Board</a>
    </div>

    <?php if ($msg): ?><div class="flash ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="flash err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- ── Role hierarchy note ── -->
    <div class="role-note">
        <strong style="color:var(--text-2)">Role Hierarchy:</strong>
        <span style="color:#ff4444">⬡ Sysadmin</span> — manages admin accounts, cannot be modified here ·
        <span style="color:var(--accent)">⬡ Admin</span> — manages boards, columns, tasks, members ·
        <span style="color:var(--text-3)">⬡ Member</span> — creates and edits tasks, adds comments
        <?php if (!$isSys): ?>
        <br><em>You are an admin — only the system admin can promote or demote other admins.</em>
        <?php endif; ?>
    </div>

    <!-- ── Users ── -->
    <div class="section-card">
        <div class="section-card-title">
            Users <span class="badge-count"><?= count($allUsers) ?></span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($allUsers as $u):
                $isMe      = ((int)$u['id'] === (int)$user['id']);
                $isSysUser = ($u['username'] === SYSADMIN_USERNAME);
                $badgeClass = $isSysUser ? 'badge-sysadmin' : ($u['role'] === 'admin' ? 'badge-admin' : 'badge-member');
                $displayRole = $isSysUser ? 'sysadmin' : $u['role'];
            ?>
            <tr>
                <td>
                    <div class="user-cell">
                        <div class="u-avatar" style="color:<?= htmlspecialchars($u['color']) ?>;background:<?= htmlspecialchars($u['color']) ?>22;border-color:<?= htmlspecialchars($u['color']) ?>">
                            <?= htmlspecialchars($u['avatar']) ?>
                        </div>
                        <?= htmlspecialchars($u['display_name']) ?>
                        <?php if ($isMe): ?> <span class="muted">(you)</span><?php endif; ?>
                    </div>
                </td>
                <td class="muted">@<?= htmlspecialchars($u['username']) ?></td>
                <td><span class="badge <?= $badgeClass ?>"><?= $displayRole ?></span></td>
                <td class="muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <div class="action-row">

                    <?php if ($isSysUser): ?>
                        <span class="sysadmin-locked">🔒 Protected</span>

                    <?php elseif ($isSys && !$isMe): ?>
                        <!-- Sysadmin: role dropdown (only for non-sysadmin accounts) -->
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="action" value="set_role">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <select name="role" class="role-select" onchange="this.form.submit()">
                                <option value="member" <?= $u['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                                <option value="admin"  <?= $u['role'] === 'admin'  ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </form>

                        <!-- Reset password -->
                        <details>
                            <summary>Reset PW</summary>
                            <form method="POST" class="reset-form">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="password" name="new_password" placeholder="New password (min 8)" minlength="8" required>
                                <button type="submit" class="btn-sm">Set</button>
                            </form>
                        </details>

                        <!-- Delete -->
                        <form method="POST" class="inline-form"
                              onsubmit="return confirm('Delete @<?= htmlspecialchars($u['username']) ?>? This cannot be undone.')">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-sm danger">Delete</button>
                        </form>

                    <?php elseif (!$isSys && $u['role'] === 'member'): ?>
                        <!-- Regular admin: can only reset member passwords -->
                        <details>
                            <summary>Reset PW</summary>
                            <form method="POST" class="reset-form">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="password" name="new_password" placeholder="New password (min 8)" minlength="8" required>
                                <button type="submit" class="btn-sm">Set</button>
                            </form>
                        </details>

                    <?php elseif ($isMe): ?>
                        <span class="muted">—</span>

                    <?php else: ?>
                        <span class="sysadmin-locked" title="Only sysadmin can modify admin accounts">🔒 Admin</span>
                    <?php endif; ?>

                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Boards overview ── -->
    <div class="section-card">
        <div class="section-card-title">Boards <span class="badge-count"><?= count($boards) ?></span></div>
        <table>
            <thead>
                <tr><th>Name</th><th>Tasks</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php foreach ($boards as $b): ?>
            <tr>
                <td><?= htmlspecialchars($b['name']) ?></td>
                <td class="muted"><?= $tcMap[$b['id']] ?? 0 ?> tasks</td>
                <td class="muted"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
