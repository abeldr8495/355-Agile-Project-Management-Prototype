<?php
require_once '../../src/auth.php';
require_once '../../src/db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$taskId = isset($data['task_id']) ? (int) $data['task_id'] : 0;
$body = trim($data['body'] ?? '');
$user = currentUser();
$userId = (int) $user['id'];

if ($taskId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid task_id is required']);
    exit;
}

if ($body === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Comment cannot be empty']);
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare(
        "INSERT INTO comments (task_id, user_id, body) VALUES (?, ?, ?)"
    );
    $stmt->execute([$taskId, $userId, $body]);

    $id = $db->lastInsertId();

    $row = $db->prepare(
        "SELECT c.*, u.display_name, u.avatar, u.color
         FROM comments c
         JOIN users u ON c.user_id = u.id
         WHERE c.id = ?"
    );
    $row->execute([$id]);

    echo json_encode($row->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}