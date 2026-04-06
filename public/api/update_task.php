<?php
// Include authentication helpers and require login
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

$allowed_statuses   = ['todo', 'inprogress', 'done'];
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
if (isset($data['status']))      {
    $s = in_array($data['status'], $allowed_statuses, true) ? $data['status'] : 'todo';
    $fields[] = 'status = ?'; $params[] = $s;
}
if (isset($data['priority']))    {
    $p = in_array($data['priority'], $allowed_priorities, true) ? $data['priority'] : 'mid';
    $fields[] = 'priority = ?'; $params[] = $p;
}
if (array_key_exists('assigned_to', $data)) {
    $fields[] = 'assigned_to = ?';
    $params[] = $data['assigned_to'] ? (int)$data['assigned_to'] : null;
}
if (isset($data['tags'])) { $fields[] = 'tags = ?'; $params[] = trim($data['tags']); }

if (!$fields) {
    http_response_code(400); echo json_encode(['error' => 'No fields to update']); exit;
}

$fields[]  = 'updated_at = CURRENT_TIMESTAMP';
$params[]  = $id;

try {
    $db   = getDB();
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
