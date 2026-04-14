<?php
header("Content-Type: application/json");

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);
$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

// ===== RESPONSE =====
function sendResponse($data, $status = 200) {
    http_response_code($status);

    if ($status < 400) {
        echo json_encode(["success" => true, "data" => $data]);
    } else {
        echo json_encode(["success" => false, "message" => $data]);
    }
    exit;
}

// ===== GET USERS =====
function getUsers($db) {
    $stmt = $db->query("SELECT id, name, email, is_admin, created_at FROM users");
    sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ===== GET USER =====
function getUserById($db, $id) {
    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id=?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) sendResponse("User not found", 404);
    sendResponse($user);
}

// ===== CREATE =====
function createUser($db, $data) {
    if (!$data["name"] || !$data["email"] || !$data["password"]) {
        sendResponse("Missing fields", 400);
    }

    if (!filter_var($data["email"], FILTER_VALIDATE_EMAIL)) {
        sendResponse("Invalid email", 400);
    }

    if (strlen($data["password"]) < 8) {
        sendResponse("Password too short", 400);
    }

    $check = $db->prepare("SELECT id FROM users WHERE email=?");
    $check->execute([$data["email"]]);
    if ($check->fetch()) {
        sendResponse("Email already exists", 409);
    }

    $hash = password_hash($data["password"], PASSWORD_DEFAULT);
    $is_admin = $data["is_admin"] ?? 0;

    $stmt = $db->prepare("INSERT INTO users (name,email,password,is_admin) VALUES (?,?,?,?)");
    $stmt->execute([$data["name"], $data["email"], $hash, $is_admin]);

    sendResponse(["id" => $db->lastInsertId()], 201);
}

// ===== UPDATE =====
function updateUser($db, $data) {
    if (!$data["id"]) sendResponse("Missing id", 400);

    $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$data["id"]]);
    if (!$stmt->fetch()) sendResponse("Not found", 404);

    if (!empty($data["email"])) {
        $check = $db->prepare("SELECT id FROM users WHERE email=? AND id != ?");
        $check->execute([$data["email"], $data["id"]]);
        if ($check->fetch()) sendResponse("Email exists", 409);
    }

    $stmt = $db->prepare("UPDATE users SET name=?, email=?, is_admin=? WHERE id=?");
    $stmt->execute([
        $data["name"] ?? "",
        $data["email"] ?? "",
        $data["is_admin"] ?? 0,
        $data["id"]
    ]);

    sendResponse("Updated");
}

// ===== DELETE =====
function deleteUser($db, $id) {
    if (!$id) sendResponse("Missing id", 400);

    $check = $db->prepare("SELECT id FROM users WHERE id=?");
    $check->execute([$id]);
    if (!$check->fetch()) sendResponse("Not found", 404);

    $stmt = $db->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$id]);

    sendResponse("Deleted");
}

// ===== PASSWORD =====
function changePassword($db, $data) {
    if (!$data["id"] || !$data["current_password"] || !$data["new_password"]) {
        sendResponse("Missing fields", 400);
    }

    if (strlen($data["new_password"]) < 8) {
        sendResponse("Password too short", 400);
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
    $stmt->execute([$data["id"]]);
    $user = $stmt->fetch();

    if (!$user) sendResponse("Not found", 404);

    if (!password_verify($data["current_password"], $user["password"])) {
        sendResponse("Wrong password", 401);
    }

    $hash = password_hash($data["new_password"], PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->execute([$hash, $data["id"]]);

    sendResponse("Password updated");
}

// ===== ROUTER =====
try {
    if ($method === "GET") {
        $id ? getUserById($db, $id) : getUsers($db);
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

} catch (Exception $e) {
    sendResponse("Server error", 500);
}