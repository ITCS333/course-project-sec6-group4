<?php
require_once 'Database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER["REQUEST_METHOD"];
$data = json_decode(file_get_contents("php://input"), true) ?? [];

$action = $_GET["action"] ?? null;
$id = $_GET["id"] ?? null;
$resource_id = $_GET["resource_id"] ?? null;
$comment_id = $_GET["comment_id"] ?? null;

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, "UTF-8");
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function validateRequiredFields($data, $fields) {
    $missing = [];

    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === "") {
            $missing[] = $field;
        }
    }

    return [
        "valid" => count($missing) === 0,
        "missing" => $missing
    ];
}

function getAllResources($db) {
    $sql = "SELECT id, title, description, link, created_at FROM resources";
    $params = [];

    if (!empty($_GET["search"])) {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
        $params[":search"] = "%" . $_GET["search"] . "%";
    }

    $allowedSort = ["title", "created_at"];
    $sort = in_array($_GET["sort"] ?? "", $allowedSort) ? $_GET["sort"] : "created_at";

    $order = strtolower($_GET["order"] ?? "desc");
    $order = in_array($order, ["asc", "desc"]) ? $order : "desc";

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    sendResponse(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getResourceById($db, $id) {
    if (!$id || !is_numeric($id)) {
        sendResponse(["success" => false, "message" => "Invalid id."], 400);
    }

    $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources WHERE id = ?");
    $stmt->execute([$id]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resource) {
        sendResponse(["success" => true, "data" => $resource]);
    }

    sendResponse(["success" => false, "message" => "Resource not found."], 404);
}

function createResource($db, $data) {
    $check = validateRequiredFields($data, ["title", "link"]);

    if (!$check["valid"]) {
        sendResponse(["success" => false, "message" => "Missing required fields."], 400);
    }

    $title = sanitizeInput($data["title"]);
    $description = sanitizeInput($data["description"] ?? "");
    $link = trim($data["link"]);

    if (!validateUrl($link)) {
        sendResponse(["success" => false, "message" => "Invalid URL."], 400);
    }

    $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
    $stmt->execute([$title, $description, $link]);

    sendResponse([
        "success" => true,
        "message" => "Resource created successfully.",
        "id" => $db->lastInsertId()
    ], 201);
}

function updateResource($db, $data) {
    if (!isset($data["id"]) || !is_numeric($data["id"])) {
        sendResponse(["success" => false, "message" => "Invalid id."], 400);
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $stmt->execute([$data["id"]]);

    if (!$stmt->fetch()) {
        sendResponse(["success" => false, "message" => "Resource not found."], 404);
    }

    $fields = [];
    $values = [];

    if (isset($data["title"])) {
        $fields[] = "title = ?";
        $values[] = sanitizeInput($data["title"]);
    }

    if (isset($data["description"])) {
        $fields[] = "description = ?";
        $values[] = sanitizeInput($data["description"]);
    }

    if (isset($data["link"])) {
        if (!validateUrl($data["link"])) {
            sendResponse(["success" => false, "message" => "Invalid URL."], 400);
        }

        $fields[] = "link = ?";
        $values[] = trim($data["link"]);
    }

    if (count($fields) === 0) {
        sendResponse(["success" => false, "message" => "No fields to update."], 400);
    }

    $values[] = $data["id"];

    $sql = "UPDATE resources SET " . implode(", ", $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse(["success" => true, "message" => "Resource updated successfully."]);
}

function deleteResource($db, $id) {
    if (!$id || !is_numeric($id)) {
        sendResponse(["success" => false, "message" => "Invalid id."], 400);
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(["success" => true, "message" => "Resource deleted successfully."]);
    }

    sendResponse(["success" => false, "message" => "Resource not found."], 404);
}

function getCommentsByResourceId($db, $resourceId) {
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(["success" => false, "message" => "Invalid resource id."], 400);
    }

    $stmt = $db->prepare("SELECT id, resource_id, author, text, created_at FROM comments_resource WHERE resource_id = ? ORDER BY created_at ASC");
    $stmt->execute([$resourceId]);

    sendResponse(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment($db, $data) {
    $check = validateRequiredFields($data, ["resource_id", "author", "text"]);

    if (!$check["valid"] || !is_numeric($data["resource_id"])) {
        sendResponse(["success" => false, "message" => "Invalid comment data."], 400);
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $stmt->execute([$data["resource_id"]]);

    if (!$stmt->fetch()) {
        sendResponse(["success" => false, "message" => "Resource not found."], 404);
    }

    $author = sanitizeInput($data["author"]);
    $text = sanitizeInput($data["text"]);

    $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$data["resource_id"], $author, $text]);

    sendResponse([
        "success" => true,
        "message" => "Comment created successfully.",
        "id" => $db->lastInsertId(),
        "data" => [
            "id" => $db->lastInsertId(),
            "resource_id" => $data["resource_id"],
            "author" => $author,
            "text" => $text
        ]
    ], 201);
}

function deleteComment($db, $commentId) {
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(["success" => false, "message" => "Invalid comment id."], 400);
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(["success" => true, "message" => "Comment deleted successfully."]);
    }

    sendResponse(["success" => false, "message" => "Comment not found."], 404);
}

try {
    if ($method === "GET") {
        if ($action === "comments") {
            getCommentsByResourceId($db, $resource_id);
        } elseif ($id) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }
    } elseif ($method === "POST") {
        if ($action === "comment") {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }
    } elseif ($method === "PUT") {
        updateResource($db, $data);
    } elseif ($method === "DELETE") {
        if ($action === "delete_comment") {
            deleteComment($db, $comment_id);
        } else {
            deleteResource($db, $id);
        }
    } else {
        sendResponse(["success" => false, "message" => "Method not allowed."], 405);
    }
} catch (Exception $e) {
    sendResponse(["success" => false, "message" => "Server error."], 500);
}
?>
