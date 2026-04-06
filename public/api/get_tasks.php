<?php
// Include auth and require login for API access
require_once '../../src/auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    $db   = getDB();

    // Get all tasks, including assigned user display metadata
    $stmt = $db->query(
        "SELECT t.*, u.display_name, u.avatar, u.color
         FROM tasks t
         LEFT JOIN users u ON t.assigned_to = u.id
         ORDER BY t.created_at ASC"
    );

    echo json_encode($stmt->fetchAll());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
