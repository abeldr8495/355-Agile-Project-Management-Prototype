<?php
// Admin-only board deletion endpoint. Tasks, comments, and attachments cascade
// through foreign keys once the board row is removed.
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

$board_id = isset($data['id']) ? (int)$data['id'] : 0;

if ($board_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid board id']);
    exit;
}

try {
    $db = getDB();
    ensureAttachmentsTable($db);

    // Prevent deleting last board
    $count = (int)$db->query("SELECT COUNT(*) FROM boards")->fetchColumn();
    if ($count <= 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete the last board']);
        exit;
    }

    $filesStmt = $db->prepare(
        "SELECT a.stored_name
         FROM attachments a
         JOIN tasks t ON t.id = a.task_id
         WHERE t.board_id = ?"
    );
    $filesStmt->execute([$board_id]);
    $storedFiles = $filesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Delete board (tasks and attachment rows cascade via foreign keys)
    $stmt = $db->prepare("DELETE FROM boards WHERE id = ?");
    $stmt->execute([$board_id]);

    foreach ($storedFiles as $storedName) {
        $path = getUploadsDir() . '/' . $storedName;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
