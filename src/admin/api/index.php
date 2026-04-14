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
$search = $_GET['search'] ?? null;
$sort = $_GET['sort'] ?? null;
$order = $_GET['order'] ?? "asc";

function sendResponse($data, $status = 200) {
    http_response_code($status);
    if ($status < 400) {
        echo json_encode(["success" => true, "data" => $data]);
    } else {
        echo json_encode(["success" => false, "message" => $data]);
    }
    exit;
}

function getUsers($db) {
    global $search, $sort, $order;

    $sql = "SELECT id, name, email, is_admin, created_at FROM users";
    $params = [];

    if ($search) {
        $sql .= " WHERE name LIKE :s OR email LIKE :s";
        $params[":s"] = "%$search%";
    }

    $allowed = ["name", "email", "is_admin"];
    if (in_array($sort, $allowed)) {
        $sql .= " ORDER BY $sort " . ($order === "desc" ? "DESC" : "ASC");
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getUserById($db, $id) {
    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id=?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) sendResponse("Not found", 404);
    sendResponse($user);
}

function createUser($db, $data) {
    if (!$data['name'] || !$data['email'] || !$data['password']) {
        sendResponse("Missing fields", 400);
    }

    if (strlen($data['password']) < 8) {
        sendResponse("Password too short", 400);
    }

    $check = $db->prepare("SELECT id FROM users WHERE email=?");
    $check->execute([$data['email']]);
    if ($check->fetch()) {
        sendResponse("Email exists", 409);
    }

    $pass = password_hash($data['password'], PASSWORD_DEFAULT);
    $is = $data['is_admin'] ?? 0;

    $stmt = $db->prepare("INSERT INTO users (name,email,password,is_admin) VALUES (?,?,?,?)");
    $stmt->execute([$data['name'],$data['email'],$pass,$is]);

    sendResponse(["id"=>$db->lastInsertId()], 201);
}

function updateUser($db, $data) {
    $stmt = $db->prepare("UPDATE users SET name=?, email=?, is_admin=? WHERE id=?");
    $stmt->execute([$data['name'],$data['email'],$data['is_admin'],$data['id']]);
    sendResponse("updated");
}

function deleteUser($db, $id) {
    $stmt = $db->prepare("DELETE FROM users WHERE id=?", [$id]);
    $stmt->execute();
    sendResponse("deleted");
}

function changePassword($db, $data) {
    $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
    $stmt->execute([$data['id']]);
    $user = $stmt->fetch();

    if (!$user) sendResponse("Not found", 404);

    if (!password_verify($data['current_password'], $user['password'])) {
        sendResponse("Wrong password", 401);
    }

    $new = password_hash($data['new_password'], PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->execute([$new, $data['id']]);

    sendResponse("password updated");
}

try {
    if ($method === "GET") {
        if ($id) getUserById($db, $id);
        else getUsers($db);
    }

    if ($method === "POST") {
        if ($action === "change_password") changePassword($db, $data);
        else createUser($db, $data);
    }

    if ($method === "PUT") updateUser($db, $data);

    if ($method === "DELETE") deleteUser($db, $id);

} catch (Exception $e) {
    sendResponse($e->getMessage(), 500);
}
?>