<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once "db.php";
$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

/* ---------- RESPONSE ---------- */
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode([
        "success" => $status < 400,
        "data" => $data
    ]);
    exit;
}

/* ---------- GET USERS ---------- */
if ($method === 'GET') {
    $stmt = $db->query("SELECT id, name, email, is_admin FROM users");
    sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* ---------- ADD USER ---------- */
if ($method === 'POST') {

    if ($action === 'change_password') {
        sendResponse("Password changed");
    }

    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        sendResponse("Missing fields", 400);
    }

    $hash = password_hash($data['password'], PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO users (name, email, password, is_admin)
                          VALUES (:name, :email, :password, :is_admin)");

    $stmt->execute([
        ':name' => $data['name'],
        ':email' => $data['email'],
        ':password' => $hash,
        ':is_admin' => $data['is_admin'] ?? 0
    ]);

    sendResponse(["id" => $db->lastInsertId()], 201);
}

/* ---------- UPDATE ---------- */
if ($method === 'PUT') {

    $stmt = $db->prepare("UPDATE users SET name=:name, email=:email WHERE id=:id");
    $stmt->execute([
        ':id' => $data['id'],
        ':name' => $data['name'],
        ':email' => $data['email']
    ]);

    sendResponse("updated");
}

/* ---------- DELETE ---------- */
if ($method === 'DELETE') {

    $stmt = $db->prepare("DELETE FROM users WHERE id=:id");
    $stmt->execute([':id' => $id]);

    sendResponse("deleted");
}

sendResponse("Invalid request", 405);