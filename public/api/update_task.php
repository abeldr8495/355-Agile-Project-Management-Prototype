<?php
// Update one task in place from the sidebar, drag/drop, or modal flows.
// Status validation uses the task's board columns so custom workflows work.
require_once '../../src/auth.php';
requireLogin();

header('Content-Type: application/json');

// Only POST allowed for update endpoint
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse JSON payload
$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing task id']);
    exit;
}

$allowed_priorities = ['low', 'mid', 'high', 'crit'];

// Build SET clause only for fields present in the request body
$fields = [];
$params = [];

if (isset($data['title'])) {
    $title = trim($data['title']);
    if (!$title) { http_response_code(400); echo json_encode(['error' => 'Title cannot be empty']); exit; }
    $fields[] = 'title = ?'; $params[] = $title;
}
if (isset($data['description'])) { $fields[] = 'description = ?'; $params[] = trim($data['description']); }
if (isset($data['priority']))    {
    $p = in_array($data['priority'], $allowed_priorities, true) ? $data['priority'] : 'mid';
    $fields[] = 'priority = ?'; $params[] = $p;
}
if (array_key_exists('assigned_to', $data)) {
    $fields[] = 'assigned_to = ?';
    $params[] = $data['assigned_to'] ? (int)$data['assigned_to'] : null;
}
if (isset($data['tags'])) { $fields[] = 'tags = ?'; $params[] = trim($data['tags']); }
if (array_key_exists('story_points', $data)) {
    $sp = $data['story_points'];
    $fields[] = 'story_points = ?';
    $params[] = ($sp !== null && $sp !== '') ? max(0, (int)$sp) : null;
}

$statusRequested = array_key_exists('status', $data) ? $data['status'] : null;
if (!$fields && $statusRequested === null) {
    http_response_code(400);
    echo json_encode(['error' => 'No fields to update']);
    exit;
}

try {
    $db   = getDB();
    $taskStmt = $db->prepare("SELECT board_id FROM tasks WHERE id = ?");
    $taskStmt->execute([$id]);
    $boardId = (int) $taskStmt->fetchColumn();

    if ($boardId <= 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Task not found']);
        exit;
    }

    if ($statusRequested !== null) {
        $statusStmt = $db->prepare(
            "SELECT status_key FROM board_columns WHERE board_id = ? ORDER BY position ASC, id ASC"
        );
        $statusStmt->execute([$boardId]);
        $allowedStatuses = $statusStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!$allowedStatuses) {
            http_response_code(400);
            echo json_encode(['error' => 'This board has no columns configured']);
            exit;
        }

        $s = in_array($statusRequested, $allowedStatuses, true) ? $statusRequested : $allowedStatuses[0];
        $fields[] = 'status = ?';
        $params[] = $s;
    }

    if (!$fields) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }

    $fields[]  = 'updated_at = CURRENT_TIMESTAMP';
    $params[]  = $id;

    $sql  = 'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $db->prepare($sql)->execute($params);

    $row = $db->prepare("
        SELECT t.*, u.display_name, u.avatar, u.color
        FROM   tasks t
        LEFT   JOIN users u ON t.assigned_to = u.id
        WHERE  t.id = ?
    ");
    $row->execute([$id]);
    echo json_encode($row->fetch());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
