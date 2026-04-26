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
   RESPONSE HELPER
========================= */
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/* =========================
   GET ALL + SEARCH
========================= */
if ($method === 'GET' && !$id && !$action) {

    $search = $_GET['search'] ?? null;

    if ($search) {
        $stmt = $db->prepare(
            "SELECT * FROM assignments
             WHERE title LIKE ? OR description LIKE ?
             ORDER BY due_date ASC"
        );
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $db->query("SELECT * FROM assignments ORDER BY due_date ASC");
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['files'] = json_decode($row['files'], true) ?? [];
    }

    sendResponse(["success" => true, "data" => $rows], 200);
}

/* =========================
   GET ONE ASSIGNMENT
========================= */
if ($method === 'GET' && $id) {

    $stmt = $db->prepare("SELECT * FROM assignments WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendResponse(["success" => false, "message" => "Not found"], 404);
    }

    $row['files'] = json_decode($row['files'], true) ?? [];

    sendResponse(["success" => true, "data" => $row], 200);
}

/* =========================
   GET COMMENTS
========================= */
if ($method === 'GET' && $action === 'comments') {

    $assignment_id = $_GET['assignment_id'] ?? null;

    $stmt = $db->prepare(
        "SELECT * FROM comments_assignment
         WHERE assignment_id=? ORDER BY created_at ASC"
    );

    $stmt->execute([$assignment_id]);

    sendResponse([
        "success" => true,
        "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ], 200);
}

/* =========================
   CREATE COMMENT
========================= */
if ($method === 'POST' && $action === 'comment') {

    $assignment_id = $data['assignment_id'] ?? null;
    $author = $data['author'] ?? 'Student';
    $text = trim($data['text'] ?? '');

    if (!$assignment_id || !$text) {
        sendResponse(["success" => false, "message" => "Missing fields"], 400);
    }

    $check = $db->prepare("SELECT id FROM assignments WHERE id=?");
    $check->execute([$assignment_id]);

    if (!$check->fetch()) {
        sendResponse(["success" => false, "message" => "Not found"], 404);
    }

    $stmt = $db->prepare(
        "INSERT INTO comments_assignment (assignment_id, author, text)
         VALUES (?, ?, ?)"
    );

    $stmt->execute([$assignment_id, $author, $text]);

    sendResponse([
        "success" => true,
        "data" => [
            "id" => $db->lastInsertId(),
            "assignment_id" => $assignment_id,
            "author" => $author,
            "text" => $text
        ]
    ], 201);
}

/* =========================
   CREATE ASSIGNMENT
========================= */
if ($method === 'POST' && !$action) {

    $title = trim($data['title'] ?? '');
    $desc  = trim($data['description'] ?? '');
    $due   = $data['due_date'] ?? '';

    if (!$title || !$desc || !$due) {
        sendResponse(["success" => false, "message" => "Missing fields"], 400);
    }

    if (!DateTime::createFromFormat('Y-m-d', $due)) {
        sendResponse(["success" => false, "message" => "Invalid date"], 400);
    }

    $stmt = $db->prepare(
        "INSERT INTO assignments (title, description, due_date, files)
         VALUES (?, ?, ?, ?)"
    );

    $stmt->execute([
        $title,
        $desc,
        $due,
        json_encode($data['files'] ?? [])
    ]);

    sendResponse([
        "success" => true,
        "id" => $db->lastInsertId()
    ], 201);
}

/* =========================
   UPDATE ASSIGNMENT
========================= */
if ($method === 'PUT') {

    $id = $data['id'] ?? null;

    if (!$id) {
        sendResponse(["success" => false], 400);
    }

    $check = $db->prepare("SELECT id FROM assignments WHERE id=?");
    $check->execute([$id]);

    if (!$check->fetch()) {
        sendResponse(["success" => false], 404);
    }

    $stmt = $db->prepare(
        "UPDATE assignments
         SET title=?, description=?, due_date=?, files=?
         WHERE id=?"
    );

    $stmt->execute([
        $data['title'],
        $data['description'],
        $data['due_date'],
        json_encode($data['files'] ?? []),
        $id
    ]);

    sendResponse(["success" => true], 200);
}

/* =========================
   DELETE ASSIGNMENT
========================= */
if ($method === 'DELETE' && $id && !$action) {

    $check = $db->prepare("SELECT id FROM assignments WHERE id=?");
    $check->execute([$id]);

    if (!$check->fetch()) {
        sendResponse(["success" => false], 404);
    }

    $stmt = $db->prepare("DELETE FROM assignments WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(["success" => true], 200);
}

/* =========================
   DELETE COMMENT
========================= */
if ($method === 'DELETE' && $action === 'delete_comment') {

    $comment_id = $_GET['comment_id'] ?? null;

    $check = $db->prepare("SELECT id FROM comments_assignment WHERE id=?");
    $check->execute([$comment_id]);

    if (!$check->fetch()) {
        sendResponse(["success" => false], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_assignment WHERE id=?");
    $stmt->execute([$comment_id]);

    sendResponse(["success" => true], 200);
}

/* =========================
   METHOD NOT ALLOWED
========================= */
sendResponse([
    "success" => false,
    "message" => "Method not allowed"
], 405);