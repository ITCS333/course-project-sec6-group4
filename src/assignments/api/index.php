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
   HELPER
========================= */
function sendResponse($success, $data = null, $message = null, $status = 200) {
    http_response_code($status);

    $res = ["success" => $success];

    if ($data !== null) $res["data"] = $data;
    if ($message !== null) $res["message"] = $message;

    echo json_encode($res);
    exit;
}

/* =========================
   GET ALL + SEARCH
========================= */
if ($method === 'GET' && !$id && !$action) {

    $search = $_GET['search'] ?? null;

    if ($search) {
        $stmt = $db->prepare("
            SELECT * FROM assignments
            WHERE title LIKE ? OR description LIKE ?
            ORDER BY due_date ASC
        ");
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $db->query("SELECT * FROM assignments ORDER BY due_date ASC");
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['files'] = json_decode($row['files'], true) ?? [];
    }

    sendResponse(true, $rows);
}

/* =========================
   GET ONE
========================= */
if ($method === 'GET' && $id) {

    $stmt = $db->prepare("SELECT * FROM assignments WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendResponse(false, null, "Not found", 404);
    }

    $row['files'] = json_decode($row['files'], true) ?? [];

    sendResponse(true, $row);
}

/* =========================
   GET COMMENTS
========================= */
if ($method === 'GET' && $action === 'comments') {

    $assignment_id = $_GET['assignment_id'] ?? null;

    $stmt = $db->prepare("
        SELECT * FROM comments_assignment
        WHERE assignment_id=?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$assignment_id]);

    sendResponse(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* =========================
   CREATE COMMENT
========================= */
if ($method === 'POST' && $action === 'comment') {

    $assignment_id = $data['assignment_id'] ?? null;
    $text = trim($data['text'] ?? '');
    $author = $data['author'] ?? 'Student';

    if (!$assignment_id || !$text) {
        sendResponse(false, null, "Missing fields", 400);
    }

    $check = $db->prepare("SELECT id FROM assignments WHERE id=?");
    $check->execute([$assignment_id]);

    if (!$check->fetch()) {
        sendResponse(false, null, "Not found", 404);
    }

    $stmt = $db->prepare("
        INSERT INTO comments_assignment (assignment_id, author, text)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([$assignment_id, $author, $text]);

    sendResponse(true, [
        "id" => $db->lastInsertId(),
        "assignment_id" => $assignment_id,
        "author" => $author,
        "text" => $text
    ], null, 201);
}

/* =========================
   CREATE ASSIGNMENT
========================= */
if ($method === 'POST' && !$action) {

    $title = trim($data['title'] ?? '');
    $desc  = trim($data['description'] ?? '');
    $due   = $data['due_date'] ?? '';

    if (!$title || !$desc || !$due) {
        sendResponse(false, null, "Missing fields", 400);
    }

    if (!DateTime::createFromFormat('Y-m-d', $due)) {
        sendResponse(false, null, "Invalid date", 400);
    }

    $stmt = $db->prepare("
        INSERT INTO assignments (title, description, due_date, files)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $title,
        $desc,
        $due,
        json_encode($data['files'] ?? [])
    ]);

    sendResponse(true, ["id" => $db->lastInsertId()], null, 201);
}

/* =========================
   UPDATE ASSIGNMENT
========================= */
if ($method === 'PUT') {

    $id = $data['id'] ?? null;

    if (!$id) {
        sendResponse(false, null, "Missing id", 400);
    }

    if (!DateTime::createFromFormat('Y-m-d', $data['due_date'])) {
        sendResponse(false, null, "Invalid date", 400);
    }

    $check = $db->prepare("SELECT id FROM assignments WHERE id=?");
    $check->execute([$id]);

    if (!$check->fetch()) {
        sendResponse(false, null, "Not found", 404);
    }

    $stmt = $db->prepare("
        UPDATE assignments
        SET title=?, description=?, due_date=?, files=?
        WHERE id=?
    ");

    $stmt->execute([
        $data['title'],
        $data['description'],
        $data['due_date'],
        json_encode($data['files'] ?? []),
        $id
    ]);

    sendResponse(true, null);
}

/* =========================
   DELETE ASSIGNMENT
========================= */
if ($method === 'DELETE' && $id && !$action) {

    $check = $db->prepare("SELECT id FROM assignments WHERE id=?");
    $check->execute([$id]);

    if (!$check->fetch()) {
        sendResponse(false, null, "Not found", 404);
    }

    $stmt = $db->prepare("DELETE FROM assignments WHERE id=?");
    $stmt->execute([$id]);

    sendResponse(true);
}

/* =========================
   DELETE COMMENT
========================= */
if ($method === 'DELETE' && $action === 'delete_comment') {

    $comment_id = $_GET['comment_id'] ?? null;

    if (!$comment_id) {
        sendResponse(false, null, "Missing id", 400);
    }

    $check = $db->prepare("SELECT id FROM comments_assignment WHERE id=?");
    $check->execute([$comment_id]);

    if (!$check->fetch()) {
        sendResponse(false, null, "Not found", 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_assignment WHERE id=?");
    $stmt->execute([$comment_id]);

    sendResponse(true);
}

/* =========================
   INVALID METHOD
========================= */
sendResponse(false, null, "Method not allowed", 405);