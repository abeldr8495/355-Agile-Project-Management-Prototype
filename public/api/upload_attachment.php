<?php
// Accept a single local file upload and attach it to a task in SQLite.
require_once '../../src/auth.php';
require_once '../../src/db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$taskId = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
$file = $_FILES['attachment'] ?? null;
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);

if ($taskId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid task_id is required']);
    exit;
}

if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'A file upload is required']);
    exit;
}

$originalName = trim((string) ($file['name'] ?? ''));
$tmpName = (string) ($file['tmp_name'] ?? '');
$sizeBytes = (int) ($file['size'] ?? 0);

if ($originalName === '' || $tmpName === '' || !is_uploaded_file($tmpName)) {
    http_response_code(400);
    echo json_encode(['error' => 'The uploaded file is invalid']);
    exit;
}

if ($sizeBytes <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'The uploaded file is empty']);
    exit;
}

if ($sizeBytes > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Files must be 10 MB or smaller']);
    exit;
}

$safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($originalName));
$storedName = bin2hex(random_bytes(16)) . '-' . $safeName;
$mimeType = mime_content_type($tmpName) ?: 'application/octet-stream';
$destination = getUploadsDir() . '/' . $storedName;

try {
    $db = getDB();
    ensureAttachmentsTable($db);

    $taskStmt = $db->prepare("SELECT id FROM tasks WHERE id = ?");
    $taskStmt->execute([$taskId]);
    if (!(int) $taskStmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        exit;
    }

    if (!move_uploaded_file($tmpName, $destination)) {
        throw new RuntimeException('Failed to store uploaded file');
    }

    $stmt = $db->prepare(
        "INSERT INTO attachments (task_id, uploaded_by, original_name, stored_name, mime_type, size_bytes)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$taskId, $userId, $originalName, $storedName, $mimeType, $sizeBytes]);

    $attachmentId = (int) $db->lastInsertId();
    $row = $db->prepare(
        "SELECT a.*, u.display_name, u.avatar, u.color
         FROM attachments a
         JOIN users u ON u.id = a.uploaded_by
         WHERE a.id = ?"
    );
    $row->execute([$attachmentId]);
    echo json_encode($row->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    if (is_file($destination ?? '')) {
        @unlink($destination);
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
