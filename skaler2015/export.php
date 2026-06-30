<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db_connection();

$type = $_GET['type'] ?? 'tools';
$range = $_GET['range'] ?? '30';

if (!$pdo) {
    die('Database not available.');
}

$filename = 'apnesoftware-' . $type . '-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly

if ($type === 'tools') {
    fputcsv($out, ['Tool Name', 'Category', 'Total Views', 'Total Runs', 'Conversion %']);
    $stmt = $pdo->query("SELECT tool_name, category, total_views, total_runs FROM tools ORDER BY total_runs DESC");
    foreach ($stmt->fetchAll() as $r) {
        $conv = $r['total_views'] > 0 ? round($r['total_runs'] / $r['total_views'] * 100, 1) : 0;
        fputcsv($out, [$r['tool_name'], $r['category'], $r['total_views'], $r['total_runs'], $conv . '%']);
    }
} elseif ($type === 'daily') {
    $days = is_numeric($range) ? (int) $range : 90;
    fputcsv($out, ['Date', 'Views', 'Runs', 'Unique Visitors', 'Mobile Views', 'Desktop Views', 'Tablet Views']);
    $stmt = $pdo->prepare("SELECT * FROM daily_stats WHERE stat_date >= CURDATE() - INTERVAL ? DAY ORDER BY stat_date ASC");
    $stmt->execute([$days]);
    foreach ($stmt->fetchAll() as $r) {
        fputcsv($out, [$r['stat_date'], $r['total_views'], $r['total_runs'], $r['unique_visitors'], $r['mobile_views'], $r['desktop_views'], $r['tablet_views']]);
    }
} else {
    fputcsv($out, ['Error: unknown export type']);
}

fclose($out);
exit;
