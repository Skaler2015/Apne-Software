<?php
// ============================================================
// DB Connection Test — DELETE THIS FILE AFTER USE!
// Visit: https://apnesoftware.com/backend/db-test.php
// ============================================================

// Try these host values one by one if localhost fails
$hosts_to_try = ['localhost', '127.0.0.1'];

// Read actual config values
require_once __DIR__ . '/config.php';

echo "<h2>ApneSoftware DB Connection Test</h2>";
echo "<pre>";

echo "DB_HOST : " . DB_HOST . "\n";
echo "DB_NAME : " . DB_NAME . "\n";
echo "DB_USER : " . DB_USER . "\n";
echo "DB_PASS : " . str_repeat('*', strlen(DB_PASS)) . " (" . strlen(DB_PASS) . " chars)\n\n";

// Test connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ CONNECTION SUCCESSFUL!\n\n";
    
    // Check if tables exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database: \n";
    if(empty($tables)) {
        echo "  (none — run sync_tools.php to create them)\n";
    } else {
        foreach($tables as $t) echo "  - $t\n";
    }
    
} catch (PDOException $e) {
    echo "❌ CONNECTION FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    // Common fixes
    echo "--- DIAGNOSIS ---\n";
    $msg = $e->getMessage();
    if(strpos($msg, 'Access denied') !== false) {
        echo "CAUSE: Wrong username or password.\n";
        echo "FIX: Double-check DB_USER and DB_PASS in config.php\n";
        echo "     Make sure no extra spaces before/after the password\n";
    } elseif(strpos($msg, 'Unknown database') !== false) {
        echo "CAUSE: Database name is wrong.\n";
        echo "FIX: Check exact name in hPanel → Databases (case-sensitive!)\n";
    } elseif(strpos($msg, "Can't connect") !== false || strpos($msg, 'refused') !== false) {
        echo "CAUSE: Host is wrong.\n";
        echo "FIX: Try DB_HOST = 'localhost' (most Hostinger plans use this)\n";
    }
}

echo "</pre>";
echo "<br><b style='color:red'>⚠️ DELETE this file from File Manager after use!</b>";
