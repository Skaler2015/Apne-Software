<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$date  = $_GET['date']  ?? date('Y-m-d');
$type  = $_GET['type']  ?? '';
$limit = min(100, (int)($_GET['limit'] ?? 20));

$where  = ["DATE(c.detected_at)=?"];
$params = [$date];

if ($type) {
    $typeMap = ['result'=>'Result','admit'=>'Admit Card','admit_card'=>'Admit Card',
                'answer_key'=>'Answer Key','cutoff'=>'Cut Off','notification'=>'Notification'];
    $cat = $typeMap[strtolower($type)] ?? ucfirst($type);
    $where[] = 'c.category=?'; $params[] = $cat;
}
$where[] = '(c.resolved IS NULL OR c.resolved=0)';

try {
    $stmt = $pdo->prepare("
        SELECT c.id, w.website_name, p.page_url,
               c.category, c.priority_score, c.confidence,
               c.note, c.detected_at, c.structured_data
        FROM changes c
        LEFT JOIN pages p ON c.page_id=p.id
        LEFT JOIN websites w ON c.website_id=w.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.priority_score DESC, c.detected_at DESC
        LIMIT {$limit}
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch(Exception $e) { $rows = []; }

$output = [];
foreach ($rows as $r) {
    $sd = json_decode($r['structured_data'] ?? '{}', true) ?: [];
    $output[] = [
        'id'          => (int)$r['id'],
        'website'     => $r['website_name'],
        'url'         => $r['page_url'],
        'category'    => $r['category'] ?: 'Update',
        'priority'    => (int)($r['priority_score'] ?? 5),
        'confidence'  => (int)($r['confidence'] ?? 50),
        'summary'     => $r['note'] ?: '',
        'detected_at' => $r['detected_at'],
        'detected_ist'=> date('d M Y, h:i A', strtotime($r['detected_at'])) . ' IST',
        'structured'  => $sd,
    ];
}

echo json_encode(['status'=>'ok','date'=>$date,'count'=>count($output),'changes'=>$output],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
