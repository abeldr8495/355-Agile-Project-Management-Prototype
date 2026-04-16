<?php
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

$allowed_statuses   = ['todo', 'inprogress', 'done'];
$allowed_priorities = ['low', 'mid', 'high', 'crit'];

if (!in_array($status, $allowed_statuses, true)) $status = 'todo';
if (!in_array($priority, $allowed_priorities, true)) $priority = 'mid';

try {
    $db = getDB();

    $stmt = $db->prepare(
        "INSERT INTO tasks (board_id, title, description, status, assigned_to, priority, tags)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->execute([
        $board_id,
        $title,
        $description,
        $status,
        $assigned_to,
        $priority,
        $tags
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