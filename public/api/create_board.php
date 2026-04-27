<?php
// Admin-only board creation endpoint. A board is created together with its
// starter workflow columns so the UI can render it immediately.
require_once '../../src/auth.php';
require_once '../../src/db.php';
requireAdmin();

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
    $existing = $db->prepare("SELECT COUNT(*) FROM boards WHERE LOWER(name) = LOWER(?)");
    $existing->execute([$name]);
    if ((int)$existing->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'A board with that name already exists']);
        exit;
    }

    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO boards (name) VALUES (?)");
    $stmt->execute([$name]);

    $id = $db->lastInsertId();

    $defaultColumns = [
        ['To Do', 'todo', '#666666', 0],
        ['In Progress', 'inprogress', '#e8c84a', 1],
        ['Done', 'done', '#4ae8a3', 2],
    ];
    $colStmt = $db->prepare(
        "INSERT INTO board_columns (board_id, name, status_key, color, position) VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($defaultColumns as [$colName, $statusKey, $color, $position]) {
        $colStmt->execute([$id, $colName, $statusKey, $color, $position]);
    }

    $row = $db->prepare("SELECT * FROM boards WHERE id = ?");
    $row->execute([$id]);
    $board = $row->fetch(PDO::FETCH_ASSOC);

    $db->commit();

    echo json_encode($board);
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if (stripos($e->getMessage(), 'UNIQUE') !== false) {
        http_response_code(400);
        echo json_encode(['error' => 'A board with that name already exists']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
