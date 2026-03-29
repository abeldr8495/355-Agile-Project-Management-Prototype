<?php
// Include auth and require login for API access
require_once '../../src/auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    $db = getDB();

    // Return user directory metadata for assignment selectors
    $stmt = $db->query('SELECT id, username, display_name, avatar, color FROM users ORDER BY id');
    echo json_encode($stmt->fetchAll());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
