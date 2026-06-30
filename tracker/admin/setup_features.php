<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$results = [];
$sqls = [
    // Notes column in changes
    "ALTER TABLE changes ADD COLUMN note TEXT NULL" => "changes.note column",
    // Scan schedule table
    "CREATE TABLE IF NOT EXISTS scan_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        is_active TINYINT(1) DEFAULT 0,
        interval_hours INT DEFAULT 6,
        last_run DATETIME NULL,
        next_run DATETIME NULL
    )" => "scan_schedule table",
    "INSERT IGNORE INTO scan_schedule (id,is_active,interval_hours) VALUES (1,0,6)" => "scan_schedule default row",
    // Scan log
    "CREATE TABLE IF NOT EXISTS scan_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ran_at DATETIME DEFAULT NOW(),
        pages_scanned INT DEFAULT 0,
        changes_found INT DEFAULT 0,
        errors INT DEFAULT 0
    )" => "scan_log table",
    // Keywords
    "CREATE TABLE IF NOT EXISTS keywords (
        id INT AUTO_INCREMENT PRIMARY KEY,
        keyword VARCHAR(200) NOT NULL,
        website_id INT NULL,
        alert_type ENUM('any','added','removed') DEFAULT 'any',
        is_active TINYINT(1) DEFAULT 1,
        match_count INT DEFAULT 0,
        created_at DATETIME DEFAULT NOW()
    )" => "keywords table",
];

foreach($sqls as $sql => $label) {
    try {
        $pdo->exec($sql);
        $results[] = ['ok', $label . " ✅"];
    } catch(Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg,'Duplicate') !== false || strpos($msg,'already exists') !== false) {
            $results[] = ['skip', $label . " (already exists)"];
        } else {
            $results[] = ['err', $label . " — " . $msg];
        }
    }
}
?>
<!DOCTYPE html><html><head><title>Setup Features</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#0d1117;color:#e6eaf2;font-family:sans-serif;padding:30px">
<h2>⚙️ Setup New Features</h2>
<?php foreach($results as $r): ?>
<div style="padding:6px 12px;margin:4px 0;border-radius:6px;background:<?=$r[0]==='ok'?'rgba(22,192,121,.15)':($r[0]==='skip'?'rgba(96,165,250,.1)':'rgba(239,68,68,.15)')?>">
  <?=$r[1]?>
</div>
<?php endforeach; ?>
<br>
<a href="changes.php" style="padding:10px 20px;background:#7c5cfc;color:#fff;text-decoration:none;border-radius:8px">
  ← Go to Changes
</a>
</body></html>
