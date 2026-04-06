<?php
// Authentication helper module
// - startSession: starts PHP session if not active
// - requireLogin: redirects to login.php when no user_id in session
// - currentUser: returns currently logged in user object from session
// - login: validates username/password in users table, writes user session state
// - logout: destroys session
require_once __DIR__ . '/db.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function requireLogin(): void {
    startSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function currentUser(): ?array {
    startSession();
    return $_SESSION['user'] ?? null;
}

function login(string $username, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        startSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = [
            'id'           => $user['id'],
            'username'     => $user['username'],
            'display_name' => $user['display_name'],
            'avatar'       => $user['avatar'],
            'color'        => $user['color'],
        ];
        return true;
    }
    return false;
}

function logout(): void {
    startSession();
    session_destroy();
}
