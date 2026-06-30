<?php
// Public read-only endpoint — per-tool analytics for tool page right sidebar
// Usage: GET /backend/tool_stats.php?slug=pdf-merge
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/config.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    echo json_encode(['has_data' => false]); exit;
}

$result = [
    'has_data'       => false,
    'tool_name'      => '',
    'icon'           => '',
    'total_views'    => 0,
    'total_runs'     => 0,
    'today_views'    => 0,
    'today_runs'     => 0,
    'last_7d_views'  => 0,
    'last_7d_runs'   => 0,
    'last_30d_views' => 0,
    'last_30d_runs'  => 0,
    'top_countries'  => [],
    'device_split'   => [],
];

$pdo = get_db_connection();
if (!$pdo) { echo json_encode($result); exit; }

try {
    $today = date('Y-m-d');

    // Get tool info
    $stmt = $pdo->prepare(
        "SELECT id, tool_name, icon, total_views, total_runs FROM tools WHERE tool_slug = ?"
    );
    $stmt->execute([$slug]);
    $tool = $stmt->fetch();
    if (!$tool) { echo json_encode($result); exit; }

    $toolId = $tool['id'];
    $result['tool_name']   = $tool['tool_name'];
    $result['icon']        = $tool['icon'] ?? '';
    $result['total_views'] = (int)$tool['total_views'];
    $result['total_runs']  = (int)$tool['total_runs'];

    // Today
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tool_views WHERE tool_id = ? AND view_date = ?"
    );
    $stmt->execute([$toolId, $today]);
    $result['today_views'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tool_runs WHERE tool_id = ? AND run_date = ?"
    );
    $stmt->execute([$toolId, $today]);
    $result['today_runs'] = (int)$stmt->fetchColumn();

    // Last 7 days
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tool_views WHERE tool_id = ? AND view_date >= DATE_SUB(?, INTERVAL 7 DAY)"
    );
    $stmt->execute([$toolId, $today]);
    $result['last_7d_views'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tool_runs WHERE tool_id = ? AND run_date >= DATE_SUB(?, INTERVAL 7 DAY)"
    );
    $stmt->execute([$toolId, $today]);
    $result['last_7d_runs'] = (int)$stmt->fetchColumn();

    // Last 30 days
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tool_views WHERE tool_id = ? AND view_date >= DATE_SUB(?, INTERVAL 30 DAY)"
    );
    $stmt->execute([$toolId, $today]);
    $result['last_30d_views'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tool_runs WHERE tool_id = ? AND run_date >= DATE_SUB(?, INTERVAL 30 DAY)"
    );
    $stmt->execute([$toolId, $today]);
    $result['last_30d_runs'] = (int)$stmt->fetchColumn();

    // Top countries (last 30d)
    $stmt = $pdo->prepare(
        "SELECT country, COUNT(*) as cnt FROM tool_views
         WHERE tool_id = ? AND country IS NOT NULL
         AND view_date >= DATE_SUB(?, INTERVAL 30 DAY)
         GROUP BY country ORDER BY cnt DESC LIMIT 5"
    );
    $stmt->execute([$toolId, $today]);
    $result['top_countries'] = $stmt->fetchAll();

    // Device split (last 30d)
    $stmt = $pdo->prepare(
        "SELECT device_type, COUNT(*) as cnt FROM tool_views
         WHERE tool_id = ? AND view_date >= DATE_SUB(?, INTERVAL 30 DAY)
         GROUP BY device_type"
    );
    $stmt->execute([$toolId, $today]);
    $result['device_split'] = $stmt->fetchAll();

    if ($result['total_views'] > 0 || $result['total_runs'] > 0) {
        $result['has_data'] = true;
    }
} catch (Exception $e) {
    error_log('tool_stats.php: ' . $e->getMessage());
}

echo json_encode($result);
