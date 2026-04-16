<?php
require_once '../../src/auth.php';
require_once '../../src/db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$name = trim($data['name'] ?? '');

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Board name is required']);
    exit;
}

if (strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Board name must be 100 characters or fewer']);
    exit;
}

try {
    $db = getDB();

    $stmt = $db->prepare("INSERT INTO boards (name) VALUES (?)");
    $stmt->execute([$name]);

    $id = $db->lastInsertId();

    $row = $db->prepare("SELECT * FROM boards WHERE id = ?");
    $row->execute([$id]);

    echo json_encode($row->fetch(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'UNIQUE') !== false) {
        http_response_code(400);
        echo json_encode(['error' => 'A board with that name already exists']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}