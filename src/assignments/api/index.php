<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';
$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$id     = $_GET['id'] ?? null;

$data = json_decode(file_get_contents("php://input"), true) ?? [];

/* =========================
   GET ALL ASSIGNMENTS
========================= */
if ($method === 'GET' && !$action && !$id) {
    $stmt = $db->query("SELECT * FROM assignments ORDER BY due_date ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['files'] = json_decode($row['files'], true) ?? [];
    }

    echo json_encode(["success" => true, "data" => $rows]);
    exit;
}

/* =========================
   GET SINGLE ASSIGNMENT
========================= */
if ($method === 'GET' && $id) {
    $stmt = $db->prepare("SELECT * FROM assignments WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["success" => false, "data" => null]);
        exit;
    }

    $row['files'] = json_decode($row['files'], true) ?? [];

    echo json_encode(["success" => true, "data" => $row]);
    exit;
}

/* =========================
   GET COMMENTS
========================= */
if ($method === 'GET' && $action === 'comments') {
    $assignment_id = $_GET['assignment_id'] ?? 0;

    $stmt = $db->prepare("SELECT * FROM comments_assignment WHERE assignment_id = ? ORDER BY created_at ASC");
    $stmt->execute([$assignment_id]);

    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $comments]);
    exit;
}

/* =========================
   ADD COMMENT
========================= */
if ($method === 'POST' && $action === 'comment') {
    $assignment_id = $data['assignment_id'] ?? null;
    $author = $data['author'] ?? 'Student';
    $text   = trim($data['text'] ?? '');

    if (!$assignment_id || !$text) {
        echo json_encode(["success" => false]);
        exit;
    }

    $stmt = $db->prepare(
        "INSERT INTO comments_assignment (assignment_id, author, text)
         VALUES (?, ?, ?)"
    );

    $stmt->execute([$assignment_id, $author, $text]);

    $newComment = [
        "id" => $db->lastInsertId(),
        "assignment_id" => $assignment_id,
        "author" => $author,
        "text" => $text,
        "created_at" => date("Y-m-d H:i:s")
    ];

    echo json_encode([
        "success" => true,
        "data" => $newComment
    ]);
    exit;
}

/* =========================
   CREATE ASSIGNMENT (ADMIN)
========================= */
if ($method === 'POST' && !$action) {
    $title = trim($data['title'] ?? '');
    $desc  = trim($data['description'] ?? '');
    $due   = $data['due_date'] ?? '';
    $files = json_encode($data['files'] ?? []);

    if (!$title || !$desc || !$due) {
        echo json_encode(["success" => false]);
        exit;
    }

    $stmt = $db->prepare(
        "INSERT INTO assignments (title, description, due_date, files)
         VALUES (?, ?, ?, ?)"
    );

    $stmt->execute([$title, $desc, $due, $files]);

    echo json_encode([
        "success" => true,
        "id" => $db->lastInsertId()
    ]);
    exit;
}

/* =========================
   DELETE ASSIGNMENT
========================= */
if ($method === 'DELETE' && $id) {
    $stmt = $db->prepare("DELETE FROM assignments WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(["success" => true]);
    exit;
}

/* =========================
   FALLBACK
========================= */
echo json_encode([
    "success" => false,
    "message" => "Invalid request"
]);
