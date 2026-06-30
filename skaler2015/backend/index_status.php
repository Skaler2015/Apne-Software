<?php
// Index Status API — GET (all statuses) / POST (update one)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
if (!$pdo) { echo json_encode(['ok'=>false,'error'=>'DB unavailable']); exit; }

// Create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS tool_index_status (
    tool_slug VARCHAR(100) PRIMARY KEY,
    status ENUM('not_submitted','submitted_1','submitted_2','indexed') NOT NULL DEFAULT 'not_submitted',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// GET — return all statuses
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query("SELECT tool_slug, status FROM tool_index_status")->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode(['ok'=>true,'statuses'=>$rows]);
    exit;
}

// POST — update one tool's status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $slug   = trim($data['slug'] ?? '');
    $status = trim($data['status'] ?? '');
    $allowed = ['not_submitted','submitted_1','submitted_2','indexed'];
    if (!$slug || !preg_match('/^[a-z0-9-]+$/', $slug) || !in_array($status, $allowed)) {
        echo json_encode(['ok'=>false,'error'=>'Invalid input']); exit;
    }
    $pdo->prepare(
        "INSERT INTO tool_index_status (tool_slug, status) VALUES (?,?)
         ON DUPLICATE KEY UPDATE status=VALUES(status), updated_at=NOW()"
    )->execute([$slug, $status]);
    echo json_encode(['ok'=>true]);
    exit;
}
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
