<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once "db.php";
$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

$raw = file_get_contents("php://input");
$data = json_decode($raw, true) ?? [];

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;


// ===================== SEND RESPONSE =====================
function sendResponse($data, $statusCode = 200) {
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


// ===================== GET USERS =====================
function getUsers($db) {

    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users");
    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse($users);
}


// ===================== GET USER BY ID =====================
function getUserById($db, $id) {

    if (!$id) {
        sendResponse("Missing ID", 400);
    }

    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = ?");
    $stmt->execute([$id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    sendResponse($user);
}


// ===================== CREATE USER =====================
function createUser($db, $data) {

    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        sendResponse("Please fill all fields", 400);
    }

    $name = trim($data['name']);
    $email = trim($data['email']);
    $password = $data['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse("Invalid email", 400);
    }

    if (strlen($password) < 8) {
        sendResponse("Password must be at least 8 characters", 400);
    }

    // check duplicate email
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);

    if ($check->fetch()) {
        sendResponse("Email already exists", 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $is_admin = $data['is_admin'] ?? 0;

    $stmt = $db->prepare("
        INSERT INTO users (name, email, password, is_admin)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([$name, $email, $hash, $is_admin]);

    sendResponse(["id" => $db->lastInsertId()], 201);
}


// ===================== UPDATE USER =====================
function updateUser($db, $data) {

    if (empty($data['id'])) {
        sendResponse("ID required", 400);
    }

    $id = $data['id'];

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        sendResponse("User not found", 404);
    }

    $fields = [];
    $params = [];

    if (!empty($data['name'])) {
        $fields[] = "name = ?";
        $params[] = $data['name'];
    }

    if (!empty($data['email'])) {
        $fields[] = "email = ?";
        $params[] = $data['email'];
    }

    if (isset($data['is_admin'])) {
        $fields[] = "is_admin = ?";
        $params[] = $data['is_admin'];
    }

    if (empty($fields)) {
        sendResponse("No data to update", 400);
    }

    $params[] = $id;

    $sql = "UPDATE users SET " . implode(",", $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);

    $stmt->execute($params);

    sendResponse("User updated successfully");
}


// ===================== DELETE USER =====================
function deleteUser($db, $id) {

    if (!$id) {
        sendResponse("Missing ID", 400);
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    sendResponse("User deleted successfully");
}


// ===================== CHANGE PASSWORD =====================
function changePassword($db, $data) {

    if (empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])) {
        sendResponse("Missing fields", 400);
    }

    if (strlen($data['new_password']) < 8) {
        sendResponse("Password too short", 400);
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$data['id']]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    if (!password_verify($data['current_password'], $user['password'])) {
        sendResponse("Invalid password", 401);
    }

    $hash = password_hash($data['new_password'], PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hash, $data['id']]);

    sendResponse("Password updated successfully");
}


// ===================== ROUTER =====================
try {

    if ($method === "GET") {

        if ($id) {
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

} catch (Exception $e) {
    sendResponse("Server error", 500);
}

?>