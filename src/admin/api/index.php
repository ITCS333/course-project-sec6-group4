<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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


// ---------------- RESPONSE ----------------
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


// ---------------- VALIDATION ----------------
function validateEmail($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}


// ---------------- GET USERS ----------------
function getUsers($db) {
    global $search, $sort, $order;

    $query = "SELECT id, name, email, is_admin, created_at FROM users";

    $conditions = [];
    $params = [];

    if ($search) {
        $conditions[] = "(name LIKE :search OR email LIKE :search)";
        $params[':search'] = "%$search%";
    }

    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    $allowedSort = ["name", "email", "is_admin"];

    if ($sort && in_array($sort, $allowedSort)) {
        $order = strtolower($order) === "desc" ? "DESC" : "ASC";
        $query .= " ORDER BY $sort $order";
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse($users);
}


// ---------------- GET USER BY ID ----------------
function getUserById($db, $id) {
    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    sendResponse($user);
}


// ---------------- CREATE USER ----------------
function createUser($db, $data) {

    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        sendResponse("Missing fields", 400);
    }

    $name = sanitizeInput($data['name']);
    $email = sanitizeInput($data['email']);
    $password = $data['password'];

    if (!validateEmail($email)) {
        sendResponse("Invalid email", 400);
    }

    if (strlen($password) < 8) {
        sendResponse("Password too short", 400);
    }

    $check = $db->prepare("SELECT id FROM users WHERE email = :email");
    $check->execute([':email' => $email]);

    if ($check->fetch()) {
        sendResponse("Email already exists", 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $is_admin = isset($data['is_admin']) ? (int)$data['is_admin'] : 0;

    $stmt = $db->prepare("INSERT INTO users (name, email, password, is_admin)
                          VALUES (:name, :email, :password, :is_admin)");

    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':password' => $hash,
        ':is_admin' => $is_admin
    ]);

    sendResponse(["id" => $db->lastInsertId()], 201);
}


// ---------------- UPDATE USER ----------------
function updateUser($db, $data) {

    if (empty($data['id'])) {
        sendResponse("ID required", 400);
    }

    $id = $data['id'];

    $fields = [];
    $params = [':id' => $id];

    if (!empty($data['name'])) {
        $fields[] = "name = :name";
        $params[':name'] = sanitizeInput($data['name']);
    }

    if (!empty($data['email'])) {
        if (!validateEmail($data['email'])) {
            sendResponse("Invalid email", 400);
        }
        $fields[] = "email = :email";
        $params[':email'] = sanitizeInput($data['email']);
    }

    if (isset($data['is_admin'])) {
        $fields[] = "is_admin = :is_admin";
        $params[':is_admin'] = (int)$data['is_admin'];
    }

    if (empty($fields)) {
        sendResponse("Nothing to update", 400);
    }

    $stmt = $db->prepare("UPDATE users SET " . implode(", ", $fields) . " WHERE id = :id");
    $stmt->execute($params);

    sendResponse("User updated");
}


// ---------------- DELETE USER ----------------
function deleteUser($db, $id) {

    if (!$id) {
        sendResponse("ID required", 400);
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);

    sendResponse("User deleted");
}


// ---------------- CHANGE PASSWORD ----------------
function changePassword($db, $data) {

    if (empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])) {
        sendResponse("Missing fields", 400);
    }

    if (strlen($data['new_password']) < 8) {
        sendResponse("Password too short", 400);
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->execute([':id' => $data['id']]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    }

    if (!password_verify($data['current_password'], $user['password'])) {
        sendResponse("Wrong password", 401);
    }

    $newHash = password_hash($data['new_password'], PASSWORD_DEFAULT);

    $update = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
    $update->execute([
        ':password' => $newHash,
        ':id' => $data['id']
    ]);

    sendResponse("Password updated");
}


// ---------------- ROUTER ----------------
try {

    if ($method === 'GET') {

        if ($id) {
            getUserById($db, $id);
        } else {
            getUsers($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'change_password') {
            changePassword($db, $data);
        } else {
            createUser($db, $data);
        }

    } elseif ($method === 'PUT') {
        updateUser($db, $data);

    } elseif ($method === 'DELETE') {
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

?>