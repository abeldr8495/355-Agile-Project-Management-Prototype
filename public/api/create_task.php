<?php
// Create a task inside the currently selected board.
// The board owns the valid workflow statuses, so we validate against that
// board's columns instead of the old hardcoded todo/inprogress/done list.
require_once '../../src/auth.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$title       = trim($data['title'] ?? '');
$description = trim($data['description'] ?? '');
$status      = $data['status'] ?? 'todo';
$priority    = $data['priority'] ?? 'mid';
$assigned_to = $data['assigned_to'] ?? null;
$tags        = trim($data['tags'] ?? '');
$board_id    = isset($data['board_id']) ? (int) $data['board_id'] : 0;
$story_points = isset($data['story_points']) && $data['story_points'] !== null && $data['story_points'] !== ''
    ? max(0, (int) $data['story_points']) : null;

if (!$title) {
    http_response_code(400);
    echo json_encode(['error' => 'Title is required']);
    exit;
}

if ($board_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid board_id is required']);
    exit;
}

$allowed_priorities = ['low', 'mid', 'high', 'crit'];
if (!in_array($priority, $allowed_priorities, true)) $priority = 'mid';

try {
    $db = getDB();

    $statusStmt = $db->prepare(
        "SELECT status_key FROM board_columns WHERE board_id = ? ORDER BY position ASC, id ASC"
    );
    $statusStmt->execute([$board_id]);
    $allowedStatuses = $statusStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$allowedStatuses) {
        http_response_code(400);
        echo json_encode(['error' => 'This board has no columns configured']);
        exit;
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $status = $allowedStatuses[0];
    }

    $stmt = $db->prepare(
        "INSERT INTO tasks (board_id, title, description, status, assigned_to, priority, tags, story_points)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->execute([
        $board_id,
        $title,
        $description,
        $status,
        $assigned_to,
        $priority,
        $tags,
        $story_points,
    ]);

    $id = $db->lastInsertId();

    $row = $db->prepare(
        "SELECT t.*, u.display_name, u.avatar, u.color
         FROM tasks t
         LEFT JOIN users u ON t.assigned_to = u.id
         WHERE t.id = ?"
    );

    $row->execute([$id]);
    echo json_encode($row->fetch());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
