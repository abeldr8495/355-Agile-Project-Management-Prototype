<?php
// Return all tasks for one board, together with assignee display metadata.
require_once '../../src/auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    $db = getDB();

    $boardId = isset($_GET['board_id']) ? (int) $_GET['board_id'] : 0;

    if ($boardId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid board_id is required']);
        exit;
    }

    $stmt = $db->prepare(
        "SELECT t.*, u.display_name, u.avatar, u.color
         FROM tasks t
         LEFT JOIN users u ON t.assigned_to = u.id
         WHERE t.board_id = ?
         ORDER BY t.created_at ASC"
    );

    $stmt->execute([$boardId]);

    echo json_encode($stmt->fetchAll());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
