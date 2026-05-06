<?php
declare(strict_types=1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(["success" => true]);
    exit;
}

require_once __DIR__ . '/../../common/db.php';

try {
    $db = getDBConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$id     = $_GET['id'] ?? null;

$data = json_decode(file_get_contents("php://input"), true) ?? [];

/* Helpers */
function respond(bool $success, $data = null, string $message = null, int $status = 200): void {
    http_response_code($status);
    $res = ["success" => $success];
    if ($data !== null) $res["data"] = $data;
    if ($message !== null) $res["message"] = $message;
    echo json_encode($res);
    exit;
}
function isValidDate(string $date, string $format = 'Y-m-d'): bool {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/* GET all */
if ($method === 'GET' && !$id && !$action) {
    $search = $_GET['search'] ?? null;
    if ($search) {
        $stmt = $db->prepare("SELECT * FROM assignments WHERE title LIKE ? OR description LIKE ? ORDER BY due_date ASC");
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $db->query("SELECT * FROM assignments ORDER BY due_date ASC");
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['files'] = json_decode($r['files'] ?? '[]', true) ?? [];
    }
    respond(true, $rows);
}

/* GET one */
if ($method === 'GET' && $id && !$action) {
    $stmt = $db->prepare("SELECT * FROM assignments WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) respond(false, null, "Not found", 404);
    $row['id'] = (int)$row['id'];
    $row['files'] = json_decode($row['files'] ?? '[]', true) ?? [];
    respond(true, $row);
}

/* GET comments */
if ($method === 'GET' && $action === 'comments') {
    $aid = $_GET['assignment_id'] ?? null;
    if (!$aid) respond(false, null, "Missing assignment ID", 400);
    $stmt = $db->prepare("SELECT * FROM comments_assignment WHERE assignment_id = ? ORDER BY created_at ASC");
    $stmt->execute([$aid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['assignment_id'] = (int)$r['assignment_id'];
    }
    respond(true, $rows);
}

/* POST comment */
if ($method === 'POST' && $action === 'comment') {
    $aid = $data['assignment_id'] ?? null;
    $text = trim($data['text'] ?? '');
    $author = $data['author'] ?? 'Student';
    if (!$aid || $text === '') respond(false, null, "Bad request", 400);
    $check = $db->prepare("SELECT id FROM assignments WHERE id = ?");
    $check->execute([$aid]);
    if (!$check->fetch()) respond(false, null, "Assignment not found", 404);
    $stmt = $db->prepare("INSERT INTO comments_assignment (assignment_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$aid, $author, $text]);
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "id" => (int)$db->lastInsertId(),
        "assignment_id" => (int)$aid,
        "author" => $author,
        "text" => $text
    ]);
    exit;
}

/* POST assignment */
if ($method === 'POST' && !$action) {
    $title = trim($data['title'] ?? '');
    $desc  = trim($data['description'] ?? '');
    $due   = $data['due_date'] ?? '';
    if ($title === '' || $desc === '' || $due === '') respond(false, null, "Missing fields", 400);
    if (!isValidDate($due)) respond(false, null, "Invalid date format", 400);
    $stmt = $db->prepare("INSERT INTO assignments (title, description, due_date, files) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $desc, $due, json_encode($data['files'] ?? [])]);
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "id" => (int)$db->lastInsertId()
    ]);
    exit;
}

/* PUT update */
if ($method === 'PUT') {
    $aid = $data['id'] ?? $id;
    if (!$aid) respond(false, null, "ID missing", 400);
    $check = $db->prepare("SELECT id FROM assignments WHERE id = ?");
    $check->execute([$aid]);
    if (!$check->fetch()) respond(false, null, "Not found", 404);
    if (isset($data['due_date']) && !isValidDate($data['due_date'])) {
        respond(false, null, "Invalid date format", 400);
    }
    $stmt = $db->prepare("UPDATE assignments SET title=?, description=?, due_date=?, files=? WHERE id=?");
    $stmt->execute([
        $data['title'] ?? '',
        $data['description'] ?? '',
        $data['due_date'] ?? '',
        json_encode($data['files'] ?? []),
        $aid
    ]);
    respond(true, ["updated" => true]);
}

/* DELETE assignment */
if ($method === 'DELETE' && $id && !$action) {
    $stmt = $db->prepare("DELETE FROM assignments WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) respond(false, null, "Not found", 404);
    echo json_encode(["success" => true]);
    exit;
}

/* DELETE comment */
if ($method === 'DELETE' && $action === 'delete_comment') {
    $cid = $_GET['comment_id'] ?? null;
    if (!$cid) respond(false, null, "Bad request", 400);
    $stmt = $db->prepare("DELETE FROM comments_assignment WHERE id = ?");
    $stmt->execute([$cid]);
    if ($stmt->rowCount() === 0) respond(false, null, "Not found", 404);
    echo json_encode(["success" => true]);
    exit;
}

respond(false, null, "Method not allowed", 405);
