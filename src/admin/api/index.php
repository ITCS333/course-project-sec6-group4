<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

/* =========================
   GET USERS
========================= */
function getUsers($db)
{
    $params = [];
    $sql = "SELECT id, name, email, is_admin, created_at FROM users";

    if (!empty($_GET['search'])) {
        $search = "%" . strtolower($_GET['search']) . "%";
        $sql .= " WHERE LOWER(name) LIKE :search OR LOWER(email) LIKE :search";
        $params['search'] = $search;
    }

    $allowedSort = ['name', 'email', 'is_admin'];
    $sort = $_GET['sort'] ?? null;
    $order = strtolower($_GET['order'] ?? 'asc');

    if ($sort && in_array($sort, $allowedSort)) {
        $order = $order === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY $sort $order";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* =========================
   GET USER BY ID
========================= */
function getUserById($db, $id)
{
    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    sendResponse($user);
}

/* =========================
   CREATE USER
========================= */
function createUser($db, $data)
{
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        sendResponse("Missing fields", 400);
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        sendResponse("Invalid email", 400);
    }

    if (strlen($data['password']) < 8) {
        sendResponse("Password must be at least 8 characters", 400);
    }

    $check = $db->prepare("SELECT id FROM users WHERE email = :email");
    $check->execute(['email' => $data['email']]);

    if ($check->fetch()) {
        sendResponse("Email already exists", 409);
    }

    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $is_admin = $data['is_admin'] ?? 0;

    $stmt = $db->prepare("
        INSERT INTO users (name, email, password, is_admin)
        VALUES (:name, :email, :password, :is_admin)
    ");

    $stmt->execute([
        "name" => $data['name'],
        "email" => $data['email'],
        "password" => $hash,
        "is_admin" => $is_admin
    ]);

    sendResponse(["id" => $db->lastInsertId()], 201);
}

/* =========================
   UPDATE USER
========================= */
function updateUser($db, $data)
{
    if (empty($data['id'])) {
        sendResponse("Missing id", 400);
    }

    $check = $db->prepare("SELECT * FROM users WHERE id = :id");
    $check->execute(['id' => $data['id']]);
    $user = $check->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    $fields = [];
    $params = ["id" => $data['id']];

    if (!empty($data['name'])) {
        $fields[] = "name = :name";
        $params['name'] = $data['name'];
    }

    if (!empty($data['email'])) {

        $checkEmail = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
        $checkEmail->execute([
            "email" => $data['email'],
            "id" => $data['id']
        ]);

        if ($checkEmail->fetch()) {
            sendResponse("Email already exists", 409);
        }

        $fields[] = "email = :email";
        $params['email'] = $data['email'];
    }

    if (isset($data['is_admin'])) {
        $fields[] = "is_admin = :is_admin";
        $params['is_admin'] = $data['is_admin'];
    }

    $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = :id";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    sendResponse("Updated");
}

/* =========================
   DELETE USER
========================= */
function deleteUser($db, $id)
{
    if (!$id) {
        sendResponse("Missing id", 400);
    }

    $check = $db->prepare("SELECT id FROM users WHERE id = :id");
    $check->execute(['id' => $id]);

    if (!$check->fetch()) {
        sendResponse("User not found", 404);
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute(['id' => $id]);

    sendResponse("Deleted");
}

/* =========================
   CHANGE PASSWORD
========================= */
function changePassword($db, $data)
{
    if (empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])) {
        sendResponse("Missing fields", 400);
    }

    if (strlen($data['new_password']) < 8) {
        sendResponse("Password must be at least 8 characters", 400);
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->execute(['id' => $data['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    if (!password_verify($data['current_password'], $user['password'])) {
        sendResponse("Incorrect password", 401);
    }

    $hash = password_hash($data['new_password'], PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET password = :p WHERE id = :id");
    $stmt->execute([
        "p" => $hash,
        "id" => $data['id']
    ]);

    sendResponse("Password updated");
}

/* =========================
   ROUTER
========================= */
try {

    if ($method === "GET") {

        if (!empty($id)) {
            getUserById($db, $id);
        } else {
            getUsers($db);
        }

    } elseif ($method === "POST") {

        if ($action === "change_password") {
            changePassword($db, $data);
        } else {
            createUser($db, $data);
        }

    } elseif ($method === "PUT") {
        updateUser($db, $data);

    } elseif ($method === "DELETE") {
        deleteUser($db, $id);

    } else {
        sendResponse("Method not allowed", 405);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse("Database error", 500);
}

function sendResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);

    if ($statusCode < 400) {
        echo json_encode([
            "success" => true,
            "data" => $data
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => $data
        ]);
    }

    exit;
}