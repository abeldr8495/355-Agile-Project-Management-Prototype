<?php
// Persist the drag/drop order for a board's columns.
require_once '../../src/auth.php';
require_once '../../src/db.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data  = json_decode(file_get_contents('php://input'), true);
$order = $data['order'] ?? []; // array of column ids in desired order

if (!is_array($order) || empty($order)) {
    http_response_code(400);
    echo json_encode(['error' => 'Order array is required']);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare("UPDATE board_columns SET position = ? WHERE id = ?");
    foreach ($order as $position => $colId) {
        $stmt->execute([$position, (int) $colId]);
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
