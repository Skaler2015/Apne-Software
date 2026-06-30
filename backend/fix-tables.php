<?php
// Fix missing columns in tables created by setup.php
// Visit: https://apnesoftware.com/backend/fix-tables.php
// DELETE after use.

require_once __DIR__ . '/config.php';
header('Content-Type: text/html; charset=utf-8');
echo "<style>body{font-family:monospace;background:#111;color:#0f0;padding:20px} .err{color:#f44} .info{color:#ff0}</style><pre>";

$pdo = get_db_connection();
if (!$pdo) die("<span class='err'>❌ DB connection failed</span>");
echo "✅ Connected\n\n";

// Add missing columns to tool_views
$fixes = [
    "ALTER TABLE tool_views ADD COLUMN IF NOT EXISTS region VARCHAR(80) DEFAULT NULL AFTER country",
    "ALTER TABLE tool_views ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT NULL AFTER region",
    "ALTER TABLE tool_views ADD COLUMN IF NOT EXISTS referrer_url VARCHAR(500) DEFAULT NULL AFTER referrer_source",
    "ALTER TABLE tool_views ADD COLUMN IF NOT EXISTS landing_page VARCHAR(255) DEFAULT NULL AFTER referrer_url",
    "ALTER TABLE tool_views ADD COLUMN IF NOT EXISTS user_agent VARCHAR(500) DEFAULT NULL AFTER landing_page",
    "ALTER TABLE tool_runs ADD COLUMN IF NOT EXISTS region VARCHAR(80) DEFAULT NULL AFTER country",
    "ALTER TABLE tool_runs ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT NULL AFTER region",
    "ALTER TABLE daily_stats ADD COLUMN IF NOT EXISTS unique_visitors BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER total_runs",
];

foreach ($fixes as $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ " . substr($sql, 0, 60) . "...\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "⏭️  Already exists (ok)\n";
        } else {
            echo "<span class='err'>❌ " . $e->getMessage() . "</span>\n";
        }
    }
}

// Test tracking manually
echo "\n--- Testing track.php manually ---\n";
$testSlug = 'pdf-merge';
$stmt = $pdo->prepare("SELECT id FROM tools WHERE tool_slug = ?");
$stmt->execute([$testSlug]);
$tool = $stmt->fetch();
if ($tool) {
    echo "✅ Tool 'pdf-merge' found in DB (id: {$tool['id']})\n";
} else {
    echo "<span class='err'>❌ 'pdf-merge' NOT in DB — run sync_tools.php</span>\n";
}

// Count current records
$views = $pdo->query("SELECT COUNT(*) FROM tool_views")->fetchColumn();
$runs  = $pdo->query("SELECT COUNT(*) FROM tool_runs")->fetchColumn();
echo "\nCurrent records:\n";
echo "  tool_views: $views rows\n";
echo "  tool_runs:  $runs rows\n";

echo "\n<span style='color:#0f0;font-size:1.2em'>✅ Fix complete! DELETE this file now.</span>\n";
echo "\n<a href='https://apnesoftware.com/skaler2015/' style='color:#7C5CFC'>→ Open Admin Panel</a>\n";
echo "</pre>";
