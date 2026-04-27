<?php
// Delete one task and clean up any files attached to it from local storage.
require_once '../../src/auth.php';
require_once '../../src/db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['id'] ?? 0);

if (!$id) {
    http_response_code(400); echo json_encode(['error' => 'Missing task id']); exit;
}

try {
    $db = getDB();
    ensureAttachmentsTable($db);

    $filesStmt = $db->prepare("SELECT stored_name FROM attachments WHERE task_id = ?");
    $filesStmt->execute([$id]);
    $storedFiles = $filesStmt->fetchAll(PDO::FETCH_COLUMN);

    $db->prepare("DELETE FROM tasks WHERE id = ?")->execute([$id]);

    foreach ($storedFiles as $storedName) {
        $path = getUploadsDir() . '/' . $storedName;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    echo json_encode(['success' => true, 'id' => $id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
