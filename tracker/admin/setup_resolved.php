<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$results = [];

$queries = [
    "ALTER TABLE changes ADD COLUMN resolved TINYINT(1) NOT NULL DEFAULT 0" => "changes.resolved column",
    "ALTER TABLE changes ADD COLUMN resolved_at DATETIME NULL"               => "changes.resolved_at column",
    "ALTER TABLE pages ADD COLUMN resolved_hash VARCHAR(32) NULL"            => "pages.resolved_hash column",
    "CREATE INDEX IF NOT EXISTS idx_resolved ON changes(resolved)"           => "resolved index",
];

foreach ($queries as $sql => $label) {
    try {
        $pdo->exec($sql);
        $results[] = ['ok', $label . " — added"];
    } catch (Exception $e) {
        // Column already exists = fine
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            $results[] = ['skip', $label . " — already exists (OK)"];
        } else {
            $results[] = ['err', $label . " — " . $e->getMessage()];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Setup Resolved</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#0d1117;color:#e6eaf2;font-family:sans-serif;padding:30px">
<h2>&#9881; Database Setup — Resolved Feature</h2>
<?php foreach ($results as $r): ?>
<div style="padding:6px 12px;margin:4px 0;border-radius:6px;background:<?= $r[0]==='ok'?'rgba(22,192,121,.15)':($r[0]==='skip'?'rgba(96,165,250,.1)':'rgba(239,68,68,.15)') ?>">
  <?= $r[0]==='ok'?'✅':($r[0]==='skip'?'ℹ️':'❌') ?> <?= htmlspecialchars($r[1]) ?>
</div>
<?php endforeach; ?>
<br>
<a href="changes.php" style="padding:10px 20px;background:#7c5cfc;color:#fff;text-decoration:none;border-radius:8px">
  &#8592; Go to Changes
</a>
</body>
</html>
