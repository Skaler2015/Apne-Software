<?php
// ============================================================
// Public, read-only endpoint — no login required.
// Used by the homepage right sidebar to show real "Most Used Tools".
// Returns gracefully empty data if the DB isn't set up yet, so the
// homepage never breaks even before the backend is deployed.
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';
$pdo = get_db_connection();

$result = ['top_tools' => [], 'has_data' => false];

if ($pdo) {
    try {
        $stmt = $pdo->query(
            "SELECT tool_slug, tool_name, icon, category, total_runs
             FROM tools WHERE total_runs > 0
             ORDER BY total_runs DESC LIMIT 10"
        );
        $rows = $stmt->fetchAll();
        if ($rows) {
            $result['top_tools'] = $rows;
            $result['has_data'] = true;
        }
    } catch (Exception $e) {
        error_log('popular_tools.php error: ' . $e->getMessage());
    }
}

echo json_encode($result);
