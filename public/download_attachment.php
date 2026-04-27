<?php
// Authenticated download endpoint for locally stored task attachments.
require_once '../src/auth.php';
require_once '../src/db.php';
requireLogin();

$attachmentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($attachmentId <= 0) {
    http_response_code(400);
    echo 'Invalid attachment id';
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
        echo 'Attachment not found';
        exit;
    }

    $path = getUploadsDir() . '/' . $attachment['stored_name'];
    if (!is_file($path)) {
        http_response_code(404);
        echo 'Attachment file is missing';
        exit;
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . ($attachment['mime_type'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . rawurlencode($attachment['original_name']) . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    readfile($path);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo 'Failed to download attachment';
}
