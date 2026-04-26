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
   RESPONSE
========================= */
function respond($success, $data = null, $message = null, $status = 200) {
    http_response_code($status);

    $res = ["success" => $success];

    if ($data !== null) $res["data"] = $data;
    if ($message !== null) $res["message"] = $message;

    echo json_encode($res);
    exit;
}

/* =========================
   GET ALL
========================= */
if ($method === 'GET' && !$id && !$action) {

    $stmt = $db->query("SELECT * FROM assignments ORDER BY due_date ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['files'] = json_decode($r['files'], true) ?? [];
    }

    respond(true, $rows);
}

/* =========================
   GET ONE
========================= */
if ($method === 'GET' && $id) {

    $stmt = $db->prepare("SELECT * FROM assignments WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        respond(false, null, "Not found", 404);
    }

    $row['files'] = json_decode($row['files'], true) ?? [];

    respond(true, $row);
}

/* =========================
   GET COMMENTS
========================= */
if ($method === 'GET' && $action === 'comments') {

    $aid = $_GET['assignment_id'] ?? null;

    $stmt = $db->prepare("
        SELECT * FROM comments_assignment
        WHERE assignment_id=?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$aid]);

    respond(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* =========================
   CREATE COMMENT
========================= */
if ($method === 'POST' && $action === 'comment') {

    $aid = $data['assignment_id'] ?? null;
    $text = trim($data['text'] ?? '');
    $author = $data['author'] ?? 'Student';

    if (!$aid || $text === '') {
        respond(false, null, "Bad request", 400);
    }

    $check = $db->prepare("SELECT id FROM assignments WHERE id=?");
    $check->execute([$aid]);

    if (!$check->fetch()) {
        respond(false, null, "Not found", 404);
    }

    $stmt = $db->prepare("
        INSERT INTO comments_assignment (assignment_id, author, text)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([$aid, $author, $text]);

    respond(true, [
        "id" => (int)$db->lastInsertId(),
        "assignment_id" => (int)$aid,
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

    if ($title === '' || $desc === '' || $due === '') {
        respond(false, null, "Bad request", 400);
    }

    if (!DateTime::createFromFormat('Y-m-d', $due)) {
        respond(false, null, "Bad date", 400);
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

    respond(true, [
        "id" => (int)$db->lastInsertId()
    ], null, 201);
}

/* =========================
   UPDATE
========================= */
if ($method === 'PUT') {

    $aid = $data['id'] ?? null;

    if (!$aid) {
        respond(false, null, "Bad request", 400);
    }

    $check = $db->prepare("SELECT id FROM assignments WHERE id=?");
    $check->execute([$aid]);

    if (!$check->fetch()) {
        respond(false, null, "Not found", 404);
    }

    if (!DateTime::createFromFormat('Y-m-d', $data['due_date'])) {
        respond(false, null, "Bad date", 400);
    }

    $db->prepare("
        UPDATE assignments
        SET title=?, description=?, due_date=?, files=?
        WHERE id=?
    ")->execute([
        $data['title'],
        $data['description'],
        $data['due_date'],
        json_encode($data['files'] ?? []),
        $aid
    ]);

    respond(true, ["updated" => true]);
}

/* =========================
   DELETE ASSIGNMENT
========================= */
if ($method === 'DELETE' && $id && !$action) {

    $check = $db->prepare("SELECT id FROM assignments WHERE id=?");
    $check->execute([$id]);

    if (!$check->fetch()) {
        respond(false, null, "Not found", 404);
    }

    $db->prepare("DELETE FROM assignments WHERE id=?")->execute([$id]);

    respond(true, ["deleted" => true]);
}

/* =========================
   DELETE COMMENT
========================= */
if ($method === 'DELETE' && $action === 'delete_comment') {

    $cid = $_GET['comment_id'] ?? null;

    if (!$cid) {
        respond(false, null, "Bad request", 400);
    }

    $check = $db->prepare("SELECT id FROM comments_assignment WHERE id=?");
    $check->execute([$cid]);

    if (!$check->fetch()) {
        respond(false, null, "Not found", 404);
    }

    $db->prepare("DELETE FROM comments_assignment WHERE id=?")->execute([$cid]);

    respond(true, ["deleted" => true]);
}

/* =========================
   INVALID
========================= */
respond(false, null, "Method not allowed", 405);