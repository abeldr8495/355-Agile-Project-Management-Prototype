<?php
// Return the ordered workflow columns for one board.
require_once '../../src/auth.php';
require_once '../../src/db.php';
requireLogin();

header('Content-Type: application/json');

$boardId = isset($_GET['board_id']) ? (int) $_GET['board_id'] : 0;

if ($boardId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid board_id is required']);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT * FROM board_columns WHERE board_id = ? ORDER BY position ASC, id ASC"
    );
    $stmt->execute([$boardId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
