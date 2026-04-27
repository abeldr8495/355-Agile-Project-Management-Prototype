<?php
// Edit one comment, restricted to the comment's original author.
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

$commentId = isset($data['id']) ? (int) $data['id'] : 0;
$body = trim($data['body'] ?? '');
$user = currentUser();
$userId = (int) $user['id'];

if ($commentId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid comment id is required']);
    exit;
}

if ($body === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Comment cannot be empty']);
    exit;
}

try {
    $db = getDB();

    // Make sure comment exists and belongs to current user
    $check = $db->prepare("SELECT * FROM comments WHERE id = ?");
    $check->execute([$commentId]);
    $comment = $check->fetch(PDO::FETCH_ASSOC);

    if (!$comment) {
        http_response_code(404);
        echo json_encode(['error' => 'Comment not found']);
        exit;
    }

    if ((int) $comment['user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'You can only edit your own comments']);
        exit;
    }

    $stmt = $db->prepare("UPDATE comments SET body = ? WHERE id = ?");
    $stmt->execute([$body, $commentId]);

    $row = $db->prepare(
        "SELECT c.*, u.display_name, u.avatar, u.color
         FROM comments c
         JOIN users u ON c.user_id = u.id
         WHERE c.id = ?"
    );
    $row->execute([$commentId]);

    echo json_encode($row->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
