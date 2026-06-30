<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$results = [];
$sqls = [
    // New columns in changes table
    "ALTER TABLE changes ADD COLUMN confidence TINYINT DEFAULT 50" => "changes.confidence",
    "ALTER TABLE changes ADD COLUMN category VARCHAR(50) NULL"       => "changes.category",
    "ALTER TABLE changes ADD COLUMN priority_score TINYINT DEFAULT 5"=> "changes.priority_score",
    "ALTER TABLE changes ADD COLUMN links_added TEXT NULL"           => "changes.links_added",
    "ALTER TABLE changes ADD COLUMN links_removed TEXT NULL"         => "changes.links_removed",
    "ALTER TABLE changes ADD COLUMN structured_data TEXT NULL"       => "changes.structured_data",
    // Snapshots table
    "CREATE TABLE IF NOT EXISTS page_snapshots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_id INT NOT NULL,
        website_id INT NOT NULL,
        content TEXT,
        content_hash VARCHAR(32),
        snapshot_date DATE,
        created_at DATETIME DEFAULT NOW(),
        INDEX idx_page_date (page_id, snapshot_date)
    )" => "page_snapshots table",
    // Settings table
    "CREATE TABLE IF NOT EXISTS tracker_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        updated_at DATETIME DEFAULT NOW() ON UPDATE NOW()
    )" => "tracker_settings table",
];

foreach ($sqls as $sql => $label) {
    try {
        $pdo->exec($sql);
        $results[] = ['ok', "✅ {$label}"];
    } catch(Exception $e) {
        $msg = $e->getMessage();
        $results[] = [strpos($msg,'Duplicate')!==false||strpos($msg,'already exists')!==false ? 'skip':'err',
                      (strpos($msg,'Duplicate')!==false ? 'ℹ️' : '❌') . " {$label}: " . substr($msg,0,60)];
    }
}
?>
<!DOCTYPE html><html><head><title>Migrate</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body style="background:#0d1117;color:#e6eaf2;padding:30px;font-family:sans-serif">
<h2>⚙️ Advanced Features Migration</h2>
<?php foreach($results as $r): ?>
<div style="padding:5px 12px;margin:3px 0;border-radius:6px;background:<?=$r[0]==='ok'?'rgba(22,192,121,.15)':($r[0]==='skip'?'rgba(96,165,250,.1)':'rgba(239,68,68,.15)')?>">
  <?=$r[1]?>
</div>
<?php endforeach; ?>
<br>
<a href="changes.php" style="padding:10px 20px;background:#7c5cfc;color:#fff;text-decoration:none;border-radius:8px">← Changes</a>
<a href="smart_dashboard.php" style="padding:10px 20px;background:#16c079;color:#fff;text-decoration:none;border-radius:8px;margin-left:8px">Smart Dashboard</a>
</body></html>
