<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$wid      = (int)($_GET['website_id'] ?? 0);
$type     = $_GET['type'] ?? '';

$where = ["DATE(c.detected_at) BETWEEN ? AND ?"];
$params = [$dateFrom, $dateTo];
if ($wid)  { $where[] = 'c.website_id=?'; $params[] = $wid; }
if ($type) { $where[] = 'c.change_type=?'; $params[] = $type; }

$rows = $pdo->prepare("
    SELECT c.id, w.website_name, p.page_url, c.change_type,
           c.note, c.detected_at, c.resolved,
           LEFT(c.new_content, 500) as content_preview
    FROM changes c
    LEFT JOIN pages p ON c.page_id=p.id
    LEFT JOIN websites w ON c.website_id=w.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.detected_at DESC
");
$rows->execute($params);
$data = $rows->fetchAll();

// Output CSV (Excel compatible)
$filename = 'changes_' . $dateFrom . '_to_' . $dateTo . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
// BOM for Excel Hindi support
fputs($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, ['#', 'Website', 'Page URL', 'Change Type', 'AI Summary/Note', 'Detected At', 'Status', 'Content Preview']);

foreach ($data as $r) {
    fputcsv($out, [
        $r['id'],
        $r['website_name'] ?? '',
        $r['page_url'] ?? '',
        str_replace('_', ' ', $r['change_type'] ?? ''),
        $r['note'] ?? '',
        date('d M Y, h:i A', strtotime($r['detected_at'])) . ' IST',
        $r['resolved'] ? 'Resolved' : 'Pending',
        str_replace(["\n","\r"], ' ', strip_tags($r['content_preview'] ?? '')),
    ]);
}
fclose($out);
