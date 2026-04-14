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
$data = json_decode(file_get_contents("php://input"), true);

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

// ================= RESPONSE =================
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

// ================= GET ALL USERS =================
function getUsers($db) {
    $stmt = $db->prepare("SELECT id, name, email, is_admin FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse($users);
}

// ================= GET USER BY ID =================
function getUserById($db, $id) {
    $stmt = $db->prepare("SELECT id, name, email, is_admin FROM users WHERE id=:id");
    $stmt->execute(["id" => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) sendResponse("User not found", 404);

    sendResponse($user);
}

// ================= CREATE USER =================
function createUser($db, $data) {

    if (!$data['name'] || !$data['email'] || !$data['password']) {
        sendResponse("Missing fields", 400);
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        sendResponse("Invalid email", 400);
    }

    if (strlen($data['password']) < 8) {
        sendResponse("Password too short", 400);
    }

    $check = $db->prepare("SELECT id FROM users WHERE email=:email");
    $check->execute(["email" => $data['email']]);

    if ($check->fetch()) {
        sendResponse("Email exists", 409);
    }

    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $is_admin = $data['is_admin'] ?? 0;

    $stmt = $db->prepare("INSERT INTO users (name,email,password,is_admin)
                          VALUES (:name,:email,:password,:is_admin)");

    $stmt->execute([
        "name" => $data['name'],
        "email" => $data['email'],
        "password" => $hash,
        "is_admin" => $is_admin
    ]);

    sendResponse(["id" => $db->lastInsertId()], 201);
}

// ================= UPDATE USER =================
function updateUser($db, $data) {

    $stmt = $db->prepare("UPDATE users SET name=:name, email=:email WHERE id=:id");
    $stmt->execute([
        "id" => $data['id'],
        "name" => $data['name'],
        "email" => $data['email']
    ]);

    sendResponse("Updated");
}

// ================= DELETE USER =================
function deleteUser($db, $id) {

    $stmt = $db->prepare("DELETE FROM users WHERE id=:id");
    $stmt->execute(["id" => $id]);

    sendResponse("Deleted");
}

// ================= CHANGE PASSWORD =================
function changePassword($db, $data) {

    $stmt = $db->prepare("SELECT password FROM users WHERE id=:id");
    $stmt->execute(["id" => $data['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) sendResponse("User not found", 404);

    if (!password_verify($data['current_password'], $user['password'])) {
        sendResponse("Wrong password", 401);
    }

    if (strlen($data['new_password']) < 8) {
        sendResponse("Password too short", 400);
    }

    $hash = password_hash($data['new_password'], PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET password=:password WHERE id=:id");
    $stmt->execute([
        "password" => $hash,
        "id" => $data['id']
    ]);

    sendResponse("Password updated");
}

// ================= ROUTER (IMPORTANT) =================
try {

    if ($method === "GET") {
        if ($id) getUserById($db, $id);
        else getUsers($db);
    }

    elseif ($method === "POST") {
        if ($action === "change_password") {
            changePassword($db, $data);
        } else {
            createUser($db, $data);
        }
    }

    elseif ($method === "PUT") {
        updateUser($db, $data);
    }

    elseif ($method === "DELETE") {
        deleteUser($db, $id);
    }

    else {
        sendResponse("Method not allowed", 405);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse("Database error", 500);
} catch (Exception $e) {
    sendResponse($e->getMessage(), 500);
}
?>