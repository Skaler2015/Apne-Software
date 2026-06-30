<?php
// Public read-only endpoint — site-wide analytics for homepage right sidebar
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/config.php';

$result = [
    'has_data'        => false,
    'total_visitors'  => 0,
    'total_runs'      => 0,
    'today_visitors'  => 0,
    'today_runs'      => 0,
    'total_tools'     => 0,
    'top_tools'       => [],
    'recent_activity' => [],
];

$pdo = get_db_connection();
if (!$pdo) { echo json_encode($result); exit; }

try {
    $today = date('Y-m-d');

    // Total unique visitors (all-time, by distinct IP)
    $result['total_visitors'] = (int)$pdo->query(
        "SELECT COUNT(DISTINCT ip_address) FROM tool_views"
    )->fetchColumn();

    // Total tool runs all-time
    $result['total_runs'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM tool_runs"
    )->fetchColumn();

    // Today's unique visitors
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT ip_address) FROM tool_views WHERE view_date = ?"
    );
    $stmt->execute([$today]);
    $result['today_visitors'] = (int)$stmt->fetchColumn();

    // Today's runs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tool_runs WHERE run_date = ?");
    $stmt->execute([$today]);
    $result['today_runs'] = (int)$stmt->fetchColumn();

    // Total published tools
    $result['total_tools'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM tools"
    )->fetchColumn();

    // Top 8 tools by total runs
    $rows = $pdo->query(
        "SELECT tool_slug, tool_name, icon, category, total_views, total_runs
         FROM tools WHERE total_runs > 0
         ORDER BY total_runs DESC LIMIT 8"
    )->fetchAll();
    $result['top_tools'] = $rows;

    // Last 7 days views per day (for sparkline)
    $stmt = $pdo->prepare(
        "SELECT stat_date, total_views, total_runs, unique_visitors
         FROM daily_stats
         WHERE stat_date >= DATE_SUB(?, INTERVAL 7 DAY)
         ORDER BY stat_date ASC"
    );
    $stmt->execute([$today]);
    $result['recent_activity'] = $stmt->fetchAll();

    if ($result['total_visitors'] > 0 || $result['total_runs'] > 0) {
        $result['has_data'] = true;
    }
} catch (Exception $e) {
    error_log('site_stats.php: ' . $e->getMessage());
}

echo json_encode($result);
