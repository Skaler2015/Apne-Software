<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
if (!$id) {
    // Show last 5 changes with content length
    $rows = $pdo->query("
        SELECT c.id, c.change_type, c.detected_at,
               LENGTH(c.old_content) as old_len,
               LENGTH(c.new_content) as new_len,
               LEFT(c.old_content, 200) as old_sample,
               LEFT(c.new_content, 200) as new_sample,
               p.page_url
        FROM changes c
        LEFT JOIN pages p ON c.page_id = p.id
        ORDER BY c.id DESC LIMIT 5
    ")->fetchAll();
    echo '<pre style="font-family:monospace;font-size:12px;padding:20px;background:#1e293b;color:#e2e8f0">';
    foreach ($rows as $r) {
        echo "ID: {$r['id']} | {$r['change_type']} | old_len:{$r['old_len']} new_len:{$r['new_len']}\n";
        echo "URL: {$r['page_url']}\n";
        echo "OLD: " . substr($r['old_sample'],0,150) . "\n";
        echo "NEW: " . substr($r['new_sample'],0,150) . "\n";
        echo "---\n";
    }
    echo '</pre>';
    exit;
}
$row = $pdo->prepare("SELECT c.*, p.page_url FROM changes c LEFT JOIN pages p ON c.page_id=p.id WHERE c.id=?");
$row->execute([$id]);
$row = $row->fetch();
echo '<pre style="font-family:monospace;font-size:11px;padding:20px;white-space:pre-wrap">';
echo "OLD (" . strlen($row['old_content']) . " chars):\n" . htmlspecialchars($row['old_content'] ?? '') . "\n\n";
echo "NEW (" . strlen($row['new_content']) . " chars):\n" . htmlspecialchars($row['new_content'] ?? '');
echo '</pre>';
