<?php
require_once '../../src/auth.php';
require_once '../../src/db.php';
requireLogin();

header('Content-Type: application/json');

$taskId = isset($_GET['task_id']) ? (int) $_GET['task_id'] : 0;

if ($taskId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid task_id is required']);
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare(
        "SELECT c.*, u.display_name, u.avatar, u.color
         FROM comments c
         JOIN users u ON c.user_id = u.id
         WHERE c.task_id = ?
         ORDER BY c.created_at ASC"
    );

    $stmt->execute([$taskId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}