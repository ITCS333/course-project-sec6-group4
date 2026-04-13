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

/* ================= RESPONSE ================= */
function sendResponse($data, $status = 200) {
    http_response_code($status);

    if ($status < 400) {
        echo json_encode(["success" => true, "data" => $data]);
    } else {
        echo json_encode(["success" => false, "message" => $data]);
    }
    exit;
}

/* ================= GET USERS ================= */
function getUsers($db) {
    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users");
    $stmt->execute();
    sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* ================= GET USER BY ID ================= */
function getUserById($db, $id) {
    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    sendResponse($user);
}

/* ================= CREATE USER ================= */
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

    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$data['email']]);
    if ($check->fetch()) {
        sendResponse("Email already exists", 409);
    }

    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $is_admin = $data['is_admin'] ?? 0;

    $stmt = $db->prepare("INSERT INTO users (name,email,password,is_admin) VALUES (?,?,?,?)");
    $stmt->execute([$data['name'], $data['email'], $hash, $is_admin]);

    sendResponse(["id" => $db->lastInsertId()], 201);
}

/* ================= UPDATE USER ================= */
function updateUser($db, $data) {

    if (!$data['id']) {
        sendResponse("ID required", 400);
    }

    $fields = [];
    $values = [];

    if (isset($data['name'])) {
        $fields[] = "name=?";
        $values[] = $data['name'];
    }

    if (isset($data['email'])) {
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            sendResponse("Invalid email", 400);
        }
        $fields[] = "email=?";
        $values[] = $data['email'];
    }

    if (isset($data['is_admin'])) {
        $fields[] = "is_admin=?";
        $values[] = $data['is_admin'];
    }

    $values[] = $data['id'];

    $stmt = $db->prepare("UPDATE users SET " . implode(",", $fields) . " WHERE id=?");
    $stmt->execute($values);

    sendResponse("Updated successfully");
}

/* ================= DELETE USER ================= */
function deleteUser($db, $id) {

    $stmt = $db->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$id]);

    sendResponse("Deleted successfully");
}

/* ================= CHANGE PASSWORD ================= */
function changePassword($db, $data) {

    $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
    $stmt->execute([$data['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    if (!password_verify($data['current_password'], $user['password'])) {
        sendResponse("Wrong password", 401);
    }

    if (strlen($data['new_password']) < 8) {
        sendResponse("Password too short", 400);
    }

    $hash = password_hash($data['new_password'], PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->execute([$hash, $data['id']]);

    sendResponse("Password updated");
}

/* ================= ROUTER ================= */
try {

    if ($method === "GET") {
        if ($id) getUserById($db, $id);
        else getUsers($db);

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

} catch (Exception $e) {
    sendResponse("Server error", 500);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>