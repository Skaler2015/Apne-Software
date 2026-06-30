<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$results = [];
$sqls = [
    "ALTER TABLE websites ADD COLUMN sitemap_url VARCHAR(500) NULL AFTER website_url" => "websites.sitemap_url column",
    "ALTER TABLE websites ADD COLUMN scan_interval INT DEFAULT 6 AFTER sitemap_url"   => "websites.scan_interval column",
];
foreach($sqls as $sql => $label) {
    try {
        $pdo->exec($sql);
        $results[] = ['ok', "✅ $label added"];
    } catch(Exception $e) {
        $results[] = [strpos($e->getMessage(),'Duplicate')!==false?'skip':'err',
                      (strpos($e->getMessage(),'Duplicate')!==false ? 'ℹ️' : '❌') . " $label — " . $e->getMessage()];
    }
}
echo "<pre style='background:#0d1117;color:#e6eaf2;padding:20px;font-family:sans-serif'>";
foreach($results as $r) echo $r[1] . "\n";
echo "</pre><a href='websites.php' style='padding:10px 20px;background:#7c5cfc;color:#fff;text-decoration:none;border-radius:8px'>← Websites</a>";
