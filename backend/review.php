<?php
// Tool Reviews API — GET (fetch) / POST (submit)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/config.php';

$pdo = get_db_connection();
if (!$pdo) { echo json_encode(['ok'=>false,'error'=>'DB unavailable']); exit; }

// Create table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tool_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tool_slug VARCHAR(100) NOT NULL,
        tool_name VARCHAR(150) NOT NULL DEFAULT '',
        rating TINYINT NOT NULL DEFAULT 5,
        comment TEXT DEFAULT NULL,
        reviewer_ip VARCHAR(45) NOT NULL DEFAULT '',
        reviewer_name VARCHAR(80) DEFAULT NULL,
        status ENUM('approved','pending','spam') NOT NULL DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tool (tool_slug),
        INDEX idx_status (status),
        INDEX idx_date (created_at DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) {}

// ── GET ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $slug = trim($_GET['slug'] ?? '');
    if (!$slug || !preg_match('/^[a-z0-9-]+$/', $slug)) {
        echo json_encode(['ok'=>false,'reviews'=>[],'stats'=>null]); exit;
    }

    // Stats
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as total, AVG(rating) as avg_rating,
         SUM(rating=5) as r5, SUM(rating=4) as r4, SUM(rating=3) as r3,
         SUM(rating=2) as r2, SUM(rating=1) as r1
         FROM tool_reviews WHERE tool_slug=? AND status='approved'"
    );
    $stmt->execute([$slug]);
    $stats = $stmt->fetch();

    // Reviews (latest 10 approved)
    $stmt = $pdo->prepare(
        "SELECT id, reviewer_name, rating, comment, created_at
         FROM tool_reviews
         WHERE tool_slug=? AND status='approved'
         ORDER BY created_at DESC LIMIT 10"
    );
    $stmt->execute([$slug]);
    $reviews = $stmt->fetchAll();

    echo json_encode(['ok'=>true,'stats'=>$stats,'reviews'=>$reviews]);
    exit;
}

// ── POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $slug    = trim($data['slug'] ?? '');
    $name    = trim(substr($data['name'] ?? '', 0, 80));
    $rating  = (int)($data['rating'] ?? 0);
    $comment = trim(substr($data['comment'] ?? '', 0, 1000));
    $toolName = trim(substr($data['tool_name'] ?? '', 0, 150));

    if (!$slug || !preg_match('/^[a-z0-9-]+$/', $slug) || $rating < 1 || $rating > 5) {
        echo json_encode(['ok'=>false,'error'=>'Invalid input']); exit;
    }
    // Comment is optional — no validation needed

    // Default values for optional fields
    if (empty($name))    $name    = 'Anonymous';
    if (empty($comment)) $comment = '';

    // Rate limit: 1 review per IP per tool per day
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = trim(explode(',', $ip)[0]);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tool_reviews WHERE tool_slug=? AND reviewer_ip=? AND DATE(created_at)=CURDATE()"
    );
    $stmt->execute([$slug, $ip]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['ok'=>false,'error'=>'You have already submitted a review for this tool today.']); exit;
    }

    // Auto-approve if comment looks clean (basic spam check)
    // Skip spam check if comment is empty (only rating submitted)
    $isSpam = false;
    if (!empty($comment)) {
        $spamWords = ['http','www.','<script','viagra','casino','click here'];
        foreach ($spamWords as $w) { if (stripos($comment, $w) !== false) { $isSpam = true; break; } }
    }
    $status = $isSpam ? 'spam' : 'approved';

    $stmt = $pdo->prepare(
        "INSERT INTO tool_reviews (tool_slug, tool_name, rating, comment, reviewer_name, reviewer_ip, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$slug, $toolName, $rating, $comment, $name ?: 'Anonymous', $ip, $status]);

    if ($status === 'spam') {
        echo json_encode(['ok'=>false,'error'=>'Your review was flagged. Please avoid links in comments.']); exit;
    }

    echo json_encode(['ok'=>true,'message'=>'Thank you for your review!']);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
