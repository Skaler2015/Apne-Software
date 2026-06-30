<?php
// ============================================================
// ONE-TIME DIAGNOSTIC TOOL
// Visit: https://apnesoftware.com/backend/diagnose.php
// Shows exactly why the dashboard is showing zeros / DB errors.
// DELETE THIS FILE after you're done — it reveals server details
// that shouldn't stay publicly accessible long-term.
// ============================================================

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='font-family:monospace;font-size:14px;line-height:1.6;background:#0f0f1a;color:#f1f1f6;padding:24px'>";

function ok($msg)   { echo "✅ $msg\n"; }
function bad($msg)  { echo "❌ $msg\n"; }
function info($msg) { echo "ℹ️  $msg\n"; }

echo "==================== STEP 1: PHP environment ====================\n";
info("PHP version: " . phpversion());
if (extension_loaded('pdo_mysql')) { ok('pdo_mysql extension is loaded'); }
else { bad('pdo_mysql extension is NOT loaded — contact Hostinger support, this is required'); }

echo "\n==================== STEP 2: config.php values ====================\n";
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    bad("config.php not found at $configPath — did you upload the backend/ folder?");
    echo "</pre>";
    exit;
}
require_once $configPath;
info("DB_HOST = " . DB_HOST);
info("DB_NAME = " . DB_NAME);
info("DB_USER = " . DB_USER);
info("DB_PASS = " . (DB_PASS === 'YOUR_DATABASE_PASSWORD_HERE' ? '⚠️ STILL THE PLACEHOLDER — you need to edit this' : '(hidden — ' . strlen(DB_PASS) . ' characters set)'));

$looksLikePlaceholder = (DB_NAME === 'u123456789_apnesoftware' || DB_USER === 'u123456789_dbuser' || DB_PASS === 'YOUR_DATABASE_PASSWORD_HERE');
if ($looksLikePlaceholder) {
    bad("STOP — config.php still has placeholder/example values. This is almost certainly the entire problem.");
    echo "\nFix: open backend/config.php, replace DB_NAME / DB_USER / DB_PASS with the REAL values\n";
    echo "from hPanel -> Databases -> MySQL Databases, then re-upload this one file and refresh this page.\n";
    echo "</pre>";
    exit;
}

echo "\n==================== STEP 3: Actual connection attempt ====================\n";
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    ok("Connected to the database successfully!");
} catch (PDOException $e) {
    bad("Connection FAILED. Real error from MySQL:");
    echo "\n   " . $e->getMessage() . "\n\n";
    echo "Common fixes:\n";
    echo "  - 'Access denied' -> DB_USER or DB_PASS is wrong, or the user isn't attached to this database in hPanel\n";
    echo "  - 'Unknown database' -> DB_NAME is wrong, or the database wasn't actually created\n";
    echo "  - 'Unknown host' / timeout -> DB_HOST is wrong (try 'localhost' first; Hostinger Cloud sometimes\n";
    echo "    needs a different internal host — check hPanel -> Databases -> MySQL Databases for the exact host shown there)\n";
    echo "</pre>";
    exit;
}

echo "\n==================== STEP 4: Required tables ====================\n";
$requiredTables = ['tools', 'tool_views', 'tool_runs', 'daily_stats', 'geoip_cache'];
$stmt = $pdo->query("SHOW TABLES");
$existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($requiredTables as $t) {
    if (in_array($t, $existingTables)) { ok("Table '$t' exists"); }
    else { bad("Table '$t' is MISSING — go import backend/db_schema.sql via phpMyAdmin"); }
}

echo "\n==================== STEP 5: Is the tools table populated? ====================\n";
if (in_array('tools', $existingTables)) {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM tools")->fetchColumn();
    if ($count > 0) {
        ok("tools table has $count rows");
    } else {
        bad("tools table is EMPTY. This means tracking will silently reject every view/run as 'unknown_tool'.");
        echo "Fix: visit https://apnesoftware.com/backend/sync_tools.php once.\n";
    }
}

echo "\n==================== STEP 6: Any tracking data recorded yet? ====================\n";
if (in_array('tool_views', $existingTables)) {
    $viewCount = (int) $pdo->query("SELECT COUNT(*) FROM tool_views")->fetchColumn();
    info("tool_views has $viewCount rows total");
}
if (in_array('tool_runs', $existingTables)) {
    $runCount = (int) $pdo->query("SELECT COUNT(*) FROM tool_runs")->fetchColumn();
    info("tool_runs has $runCount rows total");
}

echo "\n==================== STEP 7: Write test — insert + read back ====================\n";
try {
    $pdo->exec("INSERT INTO geoip_cache (ip_address, country, region, city) VALUES ('1.2.3.4', 'TestCountry', 'TestRegion', 'TestCity')
                ON DUPLICATE KEY UPDATE country='TestCountry'");
    $check = $pdo->query("SELECT country FROM geoip_cache WHERE ip_address='1.2.3.4'")->fetchColumn();
    if ($check === 'TestCountry') {
        ok("Write + read test succeeded — the database user has correct permissions.");
        $pdo->exec("DELETE FROM geoip_cache WHERE ip_address='1.2.3.4'");
    } else {
        bad("Write succeeded but read-back didn't match — unexpected.");
    }
} catch (Exception $e) {
    bad("Write test FAILED: " . $e->getMessage());
    echo "Your database user likely doesn't have INSERT/UPDATE privileges — check hPanel, re-attach the user with ALL PRIVILEGES.\n";
}

echo "\n==================== DONE ====================\n";
echo "If everything above is green, visit a tool page on your live site, use the tool once,\n";
echo "then refresh the dashboard — numbers should move. If a view/run still doesn't appear,\n";
echo "open your browser's DevTools -> Network tab on a tool page and check the track.php request directly.\n";
echo "</pre>";
