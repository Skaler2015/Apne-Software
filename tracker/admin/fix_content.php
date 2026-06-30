<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Clean HTML from stored changes content
if (isset($_GET['run'])) {
    $rows = $pdo->query("SELECT id, old_content, new_content FROM changes")->fetchAll();
    $fixed = 0;
    foreach ($rows as $r) {
        $old = html_entity_decode(strip_tags($r['old_content'] ?? ''), ENT_QUOTES, 'UTF-8');
        $new = html_entity_decode(strip_tags($r['new_content'] ?? ''), ENT_QUOTES, 'UTF-8');
        $old = preg_replace('/\s+/', ' ', trim($old));
        $new = preg_replace('/\s+/', ' ', trim($new));
        $pdo->prepare("UPDATE changes SET old_content=?, new_content=? WHERE id=?")
            ->execute([$old, $new, $r['id']]);
        $fixed++;
    }
    // Also clean pages content
    $pages = $pdo->query("SELECT id, content FROM pages WHERE content IS NOT NULL")->fetchAll();
    foreach ($pages as $p) {
        $c = html_entity_decode(strip_tags($p['content'] ?? ''), ENT_QUOTES, 'UTF-8');
        $c = preg_replace('/\s+/', ' ', trim($c));
        $pdo->prepare("UPDATE pages SET content=?, content_hash=MD5(?) WHERE id=?")
            ->execute([$c, $c, $p['id']]);
    }
    echo '<div style="font-family:sans-serif;padding:20px;background:#f0fdf4;border:1px solid #22c55e;border-radius:8px;color:#14532d">
    <h2>✅ Fixed ' . $fixed . ' change records + ' . count($pages) . ' page records</h2>
    <p>HTML tags stripped from all stored content.</p>
    <a href="changes.php" style="padding:8px 16px;background:#7c5cfc;color:#fff;text-decoration:none;border-radius:6px">← Back to Changes</a>
    </div>';
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Fix Content</title></head>
<body style="font-family:sans-serif;padding:30px;background:#0d1117;color:#e6eaf2">
<h2>🔧 Fix Stored Content</h2>
<p style="color:#94a3b8">This will strip HTML tags from all stored old_content and new_content in the database.<br>
This fixes the <code>class="ct-rem"</code> display issue in Old Snapshot.</p>
<?php
$count = $pdo->query("SELECT COUNT(*) FROM changes")->fetchColumn();
echo '<p>Records to fix: <strong>' . $count . '</strong></p>';
?>
<a href="fix_content.php?run=1" 
   style="padding:10px 24px;background:#ef4444;color:#fff;text-decoration:none;border-radius:8px;font-weight:700"
   onclick="return confirm('Fix all ' + <?= $count ?> + ' records?')">
   🔧 Run Fix Now
</a>
&nbsp;
<a href="changes.php" style="padding:10px 24px;background:#374151;color:#fff;text-decoration:none;border-radius:8px">Cancel</a>
</body>
</html>
