<?php
require_once '../../src/auth.php';
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