<?php
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json');
$pdo = get_db_connection();

if (!$pdo) {
    echo json_encode(['live_count' => 0, 'activities' => []]);
    exit;
}

try {
    // "Live" = activity in the last 5 minutes
    $liveCount = (int) $pdo->query(
        "SELECT COUNT(DISTINCT ip_address) FROM (
            SELECT ip_address, created_at FROM tool_views WHERE created_at >= NOW() - INTERVAL 5 MINUTE
            UNION ALL
            SELECT ip_address, created_at FROM tool_runs WHERE created_at >= NOW() - INTERVAL 5 MINUTE
         ) t"
    )->fetchColumn();

    $stmt = $pdo->query(
        "SELECT 'view' as type, t.tool_name, t.icon, v.device_type, v.country, v.created_at
         FROM tool_views v JOIN tools t ON t.id = v.tool_id
         UNION ALL
         SELECT 'run' as type, t.tool_name, t.icon, r.device_type, r.country, r.created_at
         FROM tool_runs r JOIN tools t ON t.id = r.tool_id
         ORDER BY created_at DESC LIMIT 100"
    );
    $activities = $stmt->fetchAll();

    echo json_encode(['live_count' => $liveCount, 'activities' => $activities]);
} catch (Exception $e) {
    error_log('realtime_feed.php error: ' . $e->getMessage());
    echo json_encode(['live_count' => 0, 'activities' => []]);
}
