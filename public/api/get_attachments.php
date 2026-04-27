<?php
// Return the attachment list for one task, including uploader display metadata.
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
    ensureAttachmentsTable($db);
    $stmt = $db->prepare(
        "SELECT a.*, u.display_name, u.avatar, u.color
         FROM attachments a
         JOIN users u ON u.id = a.uploaded_by
         WHERE a.task_id = ?
         ORDER BY a.created_at DESC, a.id DESC"
    );
    $stmt->execute([$taskId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
