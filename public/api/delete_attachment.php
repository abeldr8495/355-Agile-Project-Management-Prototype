<?php
// Remove one attachment. Uploaders and admins can delete files.
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
$attachmentId = isset($data['id']) ? (int) $data['id'] : 0;
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);

if ($attachmentId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid attachment id is required']);
    exit;
}

try {
    $db = getDB();
    ensureAttachmentsTable($db);
    $stmt = $db->prepare("SELECT * FROM attachments WHERE id = ?");
    $stmt->execute([$attachmentId]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        http_response_code(404);
        echo json_encode(['error' => 'Attachment not found']);
        exit;
    }

    if ((int) $attachment['uploaded_by'] !== $userId && !isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'You can only delete your own attachments']);
        exit;
    }

    $db->prepare("DELETE FROM attachments WHERE id = ?")->execute([$attachmentId]);

    $path = getUploadsDir() . '/' . $attachment['stored_name'];
    if (is_file($path)) {
        @unlink($path);
    }

    echo json_encode(['success' => true, 'id' => $attachmentId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
