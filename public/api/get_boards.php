<?php
/**
 * get_boards.php — Return all boards ordered by creation date.
 *
 * Used by the left sidebar to populate the board list and by board.js on
 * every init and bfcache restore. Requires an active session.
 *
 * GET /api/get_boards.php
 * Response: JSON array of board rows [{id, name, created_at}, …]
 */
require_once '../../src/auth.php';
require_once '../../src/db.php';
requireLogin();

header('Content-Type: application/json');

try {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM boards ORDER BY created_at ASC");
    echo json_encode($stmt->fetchAll());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
