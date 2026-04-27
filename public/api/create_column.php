<?php
// Add a new workflow column to a board and derive its status key from the name.
require_once '../../src/auth.php';
require_once '../../src/db.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$boardId = isset($data['board_id']) ? (int) $data['board_id'] : 0;
$name    = trim($data['name'] ?? '');
$color   = trim($data['color'] ?? '#888888');

if ($boardId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid board_id is required']);
    exit;
}

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Column name is required']);
    exit;
}

if (strlen($name) > 50) {
    http_response_code(400);
    echo json_encode(['error' => 'Column name must be 50 characters or fewer']);
    exit;
}

// Validate hex color
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
    $color = '#888888';
}

try {
    $db = getDB();

    // Reject duplicate column names within a board in a case-insensitive way.
    $duplicate = $db->prepare("SELECT COUNT(*) FROM board_columns WHERE board_id = ? AND LOWER(name) = LOWER(?)");
    $duplicate->execute([$boardId, $name]);
    if ((int)$duplicate->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'A column with that name already exists']);
        exit;
    }

    // Generate a slug-style status key from the name
    $slug = preg_replace('/[^a-z0-9]/', '', strtolower($name));
    if (!$slug) $slug = 'col' . time();

    // Ensure uniqueness within this board
    $existing = $db->prepare("SELECT COUNT(*) FROM board_columns WHERE board_id = ? AND status_key = ?");
    $existing->execute([$boardId, $slug]);
    if ((int)$existing->fetchColumn() > 0) {
        $slug .= rand(10, 99);
    }

    // Get next position
    $posStmt = $db->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM board_columns WHERE board_id = ?");
    $posStmt->execute([$boardId]);
    $position = (int) $posStmt->fetchColumn();

    $stmt = $db->prepare(
        "INSERT INTO board_columns (board_id, name, status_key, color, position) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$boardId, $name, $slug, $color, $position]);
    $id = $db->lastInsertId();

    $row = $db->prepare("SELECT * FROM board_columns WHERE id = ?");
    $row->execute([$id]);
    echo json_encode($row->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
