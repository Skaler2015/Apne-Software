<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

echo "<pre style='background:#0d1117;color:#e6eaf2;padding:20px;font-family:sans-serif'>";

// Add lastmod column to pages
try {
    $pdo->exec("ALTER TABLE pages ADD COLUMN lastmod DATE NULL");
    echo "✅ pages.lastmod column added\n";
} catch(Exception $e) {
    echo "ℹ️ " . $e->getMessage() . "\n";
}

// Add lastmod index for fast sorting
try {
    $pdo->exec("ALTER TABLE pages ADD INDEX idx_lastmod (website_id, lastmod)");
    echo "✅ Index added\n";
} catch(Exception $e) {
    echo "ℹ️ Index: " . $e->getMessage() . "\n";
}

echo "\n✅ Done! Ab Full Scan chalao.\n";
echo "</pre>";
echo "<a href='../cron/full_scan.php' style='padding:10px 20px;background:#7c5cfc;color:#fff;text-decoration:none;border-radius:8px'>🚀 Full Scan →</a>";
