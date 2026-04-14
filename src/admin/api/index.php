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
$order = $_GET['order'] ?? 'asc';


// ========================
// GET USERS
// ========================
function getUsers($db, $search, $sort, $order) {

    $allowedSort = ['name', 'email', 'is_admin'];

    $sql = "SELECT id, name, email, is_admin, created_at FROM users";
    $params = [];

    if ($search) {
        $sql .= " WHERE name LIKE :search OR email LIKE :search";
        $params[':search'] = "%$search%";
    }

    if ($sort && in_array($sort, $allowedSort)) {
        $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY $sort $order";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse($users, 200);
}


// ========================
// GET USER BY ID
// ========================
function getUserById($db, $id) {

    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id");
    $stmt->bindParam(":id", $id);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    sendResponse($user, 200);
}


// ========================
// CREATE USER
// ========================
function createUser($db, $data) {

    if (
        empty($data['name']) ||
        empty($data['email']) ||
        empty($data['password'])
    ) {
        sendResponse("Missing fields", 400);
    }

    $name = trim($data['name']);
    $email = trim($data['email']);
    $password = $data['password'];
    $is_admin = isset($data['is_admin']) ? (int)$data['is_admin'] : 0;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse("Invalid email", 400);
    }

    if (strlen($password) < 8) {
        sendResponse("Password too short", 400);
    }

    $check = $db->prepare("SELECT id FROM users WHERE email = :email");
    $check->bindParam(":email", $email);
    $check->execute();

    if ($check->fetch()) {
        sendResponse("Email already exists", 409);
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("
        INSERT INTO users (name, email, password, is_admin)
        VALUES (:name, :email, :password, :is_admin)
    ");

    $stmt->bindParam(":name", $name);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":password", $hashed);
    $stmt->bindParam(":is_admin", $is_admin);

    if ($stmt->execute()) {
        sendResponse(["id" => $db->lastInsertId()], 201);
    }

    sendResponse("Failed to create user", 500);
}


// ========================
// UPDATE USER
// ========================
function updateUser($db, $data) {

    if (empty($data['id'])) {
        sendResponse("Missing ID", 400);
    }

    $id = $data['id'];

    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(":id", $id);
    $stmt->execute();

    if (!$stmt->fetch()) {
        sendResponse("User not found", 404);
    }

    $fields = [];
    $params = [':id' => $id];

    if (!empty($data['name'])) {
        $fields[] = "name = :name";
        $params[':name'] = $data['name'];
    }

    if (!empty($data['email'])) {
        $fields[] = "email = :email";
        $params[':email'] = $data['email'];
    }

    if (isset($data['is_admin'])) {
        $fields[] = "is_admin = :is_admin";
        $params[':is_admin'] = (int)$data['is_admin'];
    }

    if (empty($fields)) {
        sendResponse("No data to update", 400);
    }

    $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = :id";

    $stmt = $db->prepare($sql);

    if ($stmt->execute($params)) {
        sendResponse("User updated", 200);
    }

    sendResponse("Update failed", 500);
}


// ========================
// DELETE USER
// ========================
function deleteUser($db, $id) {

    if (!$id) {
        sendResponse("Missing ID", 400);
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $stmt->bindParam(":id", $id);

    if ($stmt->execute()) {
        sendResponse("User deleted", 200);
    }

    sendResponse("Delete failed", 500);
}


// ========================
// CHANGE PASSWORD
// ========================
function changePassword($db, $data) {

    if (
        empty($data['id']) ||
        empty($data['current_password']) ||
        empty($data['new_password'])
    ) {
        sendResponse("Missing fields", 400);
    }

    if (strlen($data['new_password']) < 8) {
        sendResponse("Password too short", 400);
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->bindParam(":id", $data['id']);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    if (!password_verify($data['current_password'], $user['password'])) {
        sendResponse("Unauthorized", 401);
    }

    $newHash = password_hash($data['new_password'], PASSWORD_DEFAULT);

    $update = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
    $update->bindParam(":password", $newHash);
    $update->bindParam(":id", $data['id']);

    if ($update->execute()) {
        sendResponse("Password updated", 200);
    }

    sendResponse("Failed to update password", 500);
}


// ========================
// ROUTER
// ========================
try {

    if ($method === "GET") {

        if ($id) {
            getUserById($db, $id);
        } else {
            getUsers($db, $search, $sort, $order);
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

} catch (Exception $e) {
    sendResponse($e->getMessage(), 500);
}


// ========================
// HELPERS
// ========================
function sendResponse($data, $status = 200) {
    http_response_code($status);

    if ($status < 400) {
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