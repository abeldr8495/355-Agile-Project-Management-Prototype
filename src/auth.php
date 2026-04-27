<?php
/**
 * auth.php — Authentication & session helpers
 *
 * Roles (3-tier):
 *   sysadmin — hardcoded username, can promote/demote admins, cannot be demoted
 *   admin    — can create/delete boards, tasks, manage columns
 *   member   — can create/edit tasks, comment
 *
 * Session security:
 *   - Session-only cookies (cleared on browser close)
 *   - Idle timeout: 30 minutes logs out automatically
 *   - beforeunload beacon hits logout_beacon.php (tab/window close)
 */

require_once __DIR__ . '/db.php';

define('SYSADMIN_USERNAME', 'sysadmin');
define('IDLE_TIMEOUT', 1800); // 30 minutes
define('ABSOLUTE_SESSION_TIMEOUT', 7200); // 2 hours

// Build the application's base path from the current script location.
// This lets the app work correctly when deployed in a subfolder.
function appBasePath(): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '/' || $dir === '.') {
        return '';
    }
    return rtrim($dir, '/');
}

// Resolve a relative URL path to the current application base path.
function appPath(string $path = ''): string {
    $base = appBasePath();
    $normalized = ltrim($path, '/');
    if ($normalized === '') {
        return $base !== '' ? $base . '/' : '/';
    }
    return ($base !== '' ? $base : '') . '/' . $normalized;
}

// Configure and start a secure PHP session if one has not already started.
// Sessions are stored server-side; cookies are limited to the browser session only.
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        // lifetime=0 means cookie deleted when browser closes
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// Authenticate credentials and initialize the user's session state.
function login(string $username, string $password): bool {
    startSession();
    // Authenticate the credentials against the users table and initialize
    // session state on success.
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT id, username, display_name, avatar, color, role FROM users WHERE username = ?"
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return false;

    $pwStmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $pwStmt->execute([$user['id']]);
    $hash = $pwStmt->fetchColumn();
    if (!password_verify($password, $hash)) return false;

    // Force sysadmin role for hardcoded username regardless of DB value
    if ($user['username'] === SYSADMIN_USERNAME) {
        $user['role'] = 'sysadmin';
    }

    session_regenerate_id(true);
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user']          = $user;
    $_SESSION['login_time']    = time();
    $_SESSION['last_activity'] = time();
    return true;
}

// Destroy the session and remove the session cookie.
function logout(): void {
    startSession();
    // Clear all session variables and expire the session cookie immediately.
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// Enforce both absolute and idle session timeouts for logged-in users.
// Idle expiration triggers after a period of inactivity, while absolute
// expiration expires every login after a fixed lifetime.
function checkIdleTimeout(): void {
    if (empty($_SESSION['user_id'])) return;

    if (isset($_SESSION['login_time'])) {
        if ((time() - (int) $_SESSION['login_time']) > ABSOLUTE_SESSION_TIMEOUT) {
            logout();
            header('Location: ' . appPath('login.php?reason=expired'));
            exit;
        }
    }

    if (isset($_SESSION['last_activity'])) {
        if ((time() - $_SESSION['last_activity']) > IDLE_TIMEOUT) {
            logout();
            header('Location: ' . appPath('login.php?reason=idle'));
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

// Require an authenticated user and refresh idle timeout state.
function requireLogin(): void {
    startSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . appPath('login.php'));
        exit;
    }
    checkIdleTimeout();
    // Always enforce sysadmin override
    if (!empty($_SESSION['user']['username']) &&
        $_SESSION['user']['username'] === SYSADMIN_USERNAME) {
        $_SESSION['user']['role'] = 'sysadmin';
    }
}

// Require admin or sysadmin privileges for the current user.
function requireAdmin(): void {
    requireLogin();
    $user = currentUser();
    if (!in_array($user['role'] ?? '', ['admin', 'sysadmin'], true)) {
        header('Location: ' . appPath('index.php')); exit;
    }
}

// Require only the system administrator account.
function requireSysadmin(): void {
    requireLogin();
    if ((currentUser()['role'] ?? '') !== 'sysadmin') {
        header('Location: ' . appPath('index.php')); exit;
    }
}

// Read the authenticated user record from session or fallback to the database.
// This keeps the user object cached in the session but refreshes it if needed.
function currentUser(): array {
    startSession();
    if (empty($_SESSION['user_id'])) return [];

    if (!empty($_SESSION['user']) && isset($_SESSION['user']['role'])) {
        $u = $_SESSION['user'];
        if (($u['username'] ?? '') === SYSADMIN_USERNAME) $u['role'] = 'sysadmin';
        return $u;
    }

    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT id, username, display_name, avatar, color, role FROM users WHERE id = ?"
        );
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            if ($user['username'] === SYSADMIN_USERNAME) $user['role'] = 'sysadmin';
            $_SESSION['user'] = $user;
            return $user;
        }
    } catch (Exception $e) {}

    return $_SESSION['user'] ?? [];
}

// Return true when the current user has an admin or higher role.
// Useful for permission checks in pages and API endpoints.
function isAdmin(): bool {
    return in_array(currentUser()['role'] ?? '', ['admin', 'sysadmin'], true);
}

// Return true when the current user is the sysadmin account.
// The sysadmin user gets extra abilities that normal admins do not.
function isSysadmin(): bool {
    return (currentUser()['role'] ?? '') === 'sysadmin';
}

// Refresh session user data from the database, useful after profile updates.
function refreshSessionUser(): void {
    startSession();
    if (empty($_SESSION['user_id'])) return;
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "SELECT id, username, display_name, avatar, color, role FROM users WHERE id = ?"
        );
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            if ($user['username'] === SYSADMIN_USERNAME) $user['role'] = 'sysadmin';
            $_SESSION['user'] = $user;
        }
    } catch (Exception $e) {}
}
