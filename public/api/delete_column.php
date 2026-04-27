<?php
// Remove a workflow column and move its tasks into the next safe fallback lane.
require_once '../../src/auth.php';
require_once '../../src/db.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int) $data['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid column id is required']);
    exit;
}

try {
    $db = getDB();

    // Fetch column to check it exists and get its board
    $col = $db->prepare("SELECT * FROM board_columns WHERE id = ?");
    $col->execute([$id]);
    $column = $col->fetch(PDO::FETCH_ASSOC);

    if (!$column) {
        http_response_code(404);
        echo json_encode(['error' => 'Column not found']);
        exit;
    }

    // Prevent deleting if it's the last column on the board
    $countStmt = $db->prepare("SELECT COUNT(*) FROM board_columns WHERE board_id = ?");
    $countStmt->execute([$column['board_id']]);
    if ((int) $countStmt->fetchColumn() <= 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete the last column on a board']);
        exit;
    }

    // Move tasks in this column to the first remaining column
    $firstColStmt = $db->prepare(
        "SELECT status_key FROM board_columns WHERE board_id = ? AND id != ? ORDER BY position ASC LIMIT 1"
    );
    $firstColStmt->execute([$column['board_id'], $id]);
    $fallbackStatus = $firstColStmt->fetchColumn() ?: 'todo';

    $db->prepare("UPDATE tasks SET status = ? WHERE board_id = ? AND status = ?")
       ->execute([$fallbackStatus, $column['board_id'], $column['status_key']]);

    // Delete the column
    $db->prepare("DELETE FROM board_columns WHERE id = ?")->execute([$id]);

    echo json_encode(['success' => true, 'id' => $id, 'tasks_moved_to' => $fallbackStatus]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
