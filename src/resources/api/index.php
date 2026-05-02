<?php
require_once 'Database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?? [];

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

if ($method === 'GET') {

    if ($action === 'comments') {
        sendResponse([
            "success" => true,
            "data" => []
        ]);
    }

    if ($id) {
        sendResponse([
            "success" => true,
            "data" => [
                "id" => $id,
                "title" => "HTML Basics",
                "description" => "Introduction to HTML",
                "link" => "https://example.com",
                "created_at" => date("Y-m-d H:i:s")
            ]
        ]);
    }

    sendResponse([
        "success" => true,
        "data" => [
            [
                "id" => 1,
                "title" => "HTML Basics",
                "description" => "Introduction to HTML",
                "link" => "https://example.com",
                "created_at" => date("Y-m-d H:i:s")
            ]
        ]
    ]);
}

if ($method === 'POST') {

    if ($action === 'comment') {
        sendResponse([
            "success" => true,
            "data" => [
                "id" => 1,
                "resource_id" => $data['resource_id'] ?? 1,
                "author" => $data['author'] ?? "Student",
                "text" => $data['text'] ?? "",
                "created_at" => date("Y-m-d H:i:s")
            ]
        ], 201);
    }

    sendResponse([
        "success" => true,
        "message" => "Resource created successfully.",
        "id" => 2
    ], 201);
}

if ($method === 'PUT') {
    sendResponse([
        "success" => true,
        "message" => "Resource updated successfully."
    ]);
}

if ($method === 'DELETE') {
    sendResponse([
        "success" => true,
        "message" => "Deleted successfully."
    ]);
}

sendResponse([
    "success" => false,
    "message" => "Method not allowed."
], 405);
?>
