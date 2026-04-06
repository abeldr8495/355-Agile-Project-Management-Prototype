<?php
// Include authentication helpers and enforce login before processing
require_once '../../src/auth.php';
requireLogin();

// All responses are JSON for the JS frontend
header('Content-Type: application/json');

// This endpoint only supports POST requests for creating new tasks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse client JSON payload from request body
$data = json_decode(file_get_contents('php://input'), true);

// Normalize and default task fields
$title       = trim($data['title']       ?? '');        // required
$description = trim($data['description'] ?? '');        // optional
$status      = $data['status']           ?? 'todo';    // default to todo
$priority    = $data['priority']         ?? 'mid';     // default to mid
$assigned_to = $data['assigned_to']      ?? null;      // optional user assignment
$tags        = trim($data['tags']        ?? '');        // optional tag string

// Validate required field
if (!$title) {
    http_response_code(400);
    echo json_encode(['error' => 'Title is required']);
    exit;
}

// Allowed enumerations for stable data storage
$allowed_statuses   = ['todo', 'inprogress', 'done'];
$allowed_priorities = ['low', 'mid', 'high', 'crit'];

// Fall back to defaults when client sends unexpected values
if (!in_array($status,   $allowed_statuses,   true)) $status   = 'todo';
if (!in_array($priority, $allowed_priorities, true)) $priority = 'mid';

try {
    // Write the new task row, using prepared statements to avoid SQL injection
    $db = getDB();
    $stmt = $db->prepare(
        "INSERT INTO tasks (title, description, status, assigned_to, priority, tags) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$title, $description, $status, $assigned_to, $priority, $tags]);

    // Fetch generated ID to return the created row with join metadata
    $id = $db->lastInsertId();

    // Return task row including user info for frontend convenience
    $row = $db->prepare(
        "SELECT t.*, u.display_name, u.avatar, u.color
         FROM tasks t
         LEFT JOIN users u ON t.assigned_to = u.id
         WHERE t.id = ?"
    );
    $row->execute([$id]);
    echo json_encode($row->fetch());
} catch (Exception $e) {
    // Server-side error handling
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
