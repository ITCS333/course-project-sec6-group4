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
$data = json_decode(file_get_contents("php://input"), true) ?? [];

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

// ================= RESPONSE =================
function sendResponse($data, $code = 200) {
    http_response_code($code);

    if ($code < 400) {
        echo json_encode(["success" => true, "data" => $data]);
    } else {
        echo json_encode(["success" => false, "message" => $data]);
    }

    exit;
}

// ================= ROUTER =================
try {

    if ($method === "GET") {
        if ($id) {
            $stmt = $db->prepare("SELECT id,name,email,is_admin,created_at FROM users WHERE id=?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) sendResponse("Not found", 404);
            sendResponse($user);
        }

        $stmt = $db->prepare("SELECT id,name,email,is_admin,created_at FROM users");
        $stmt->execute();
        sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($method === "POST") {

        if ($action === "change_password") {

            $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
            $stmt->execute([$data['id']]);
            $user = $stmt->fetch();

            if (!password_verify($data['current_password'], $user['password'])) {
                sendResponse("Wrong password", 401);
            }

            $hash = password_hash($data['new_password'], PASSWORD_DEFAULT);

            $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->execute([$hash, $data['id']]);

            sendResponse("Password updated");
        }

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $db->prepare("INSERT INTO users (name,email,password,is_admin)
                              VALUES (?,?,?,?)");

        $stmt->execute([
            $data['name'],
            $data['email'],
            $hash,
            $data['is_admin'] ?? 0
        ]);

        sendResponse(["id" => $db->lastInsertId()], 201);
    }

    if ($method === "PUT") {

        $stmt = $db->prepare("UPDATE users SET name=?, email=? WHERE id=?");
        $stmt->execute([$data['name'], $data['email'], $data['id']]);

        sendResponse("Updated");
    }

    if ($method === "DELETE") {

        $stmt = $db->prepare("DELETE FROM users WHERE id=?");
        $stmt->execute([$id]);

        sendResponse("Deleted");
    }

} catch (Exception $e) {
    sendResponse("Server error", 500);
}