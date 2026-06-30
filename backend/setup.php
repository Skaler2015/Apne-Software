<?php
// ============================================================
// ApneSoftware.com — One-Click Setup
// Visit: https://apnesoftware.com/backend/setup.php
// This creates all tables AND syncs tools in one go.
// DELETE THIS FILE after setup is complete.
// ============================================================

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<style>body{font-family:monospace;background:#111;color:#0f0;padding:20px} .ok{color:#0f0} .err{color:#f44} .info{color:#ff0}</style>";
echo "<h2 style='color:#fff'>ApneSoftware — Database Setup</h2><pre>";

$pdo = get_db_connection();
if (!$pdo) {
    die("<span class='err'>❌ DB connection failed. Check config.php credentials.</span>");
}
echo "<span class='ok'>✅ Database connected successfully!</span>\n\n";

// ── Create Tables ────────────────────────────────────────────
$tables = [
"CREATE TABLE IF NOT EXISTS tools (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tool_slug VARCHAR(100) NOT NULL UNIQUE,
  tool_name VARCHAR(150) NOT NULL,
  category VARCHAR(50) NOT NULL,
  icon VARCHAR(20) DEFAULT NULL,
  total_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_runs BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS tool_views (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tool_id INT NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  country VARCHAR(80) DEFAULT NULL,
  browser VARCHAR(50) DEFAULT NULL,
  os VARCHAR(50) DEFAULT NULL,
  device_type ENUM('desktop','mobile','tablet','other') DEFAULT 'other',
  referrer_source VARCHAR(50) DEFAULT NULL,
  referrer_url VARCHAR(500) DEFAULT NULL,
  view_date DATE NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE,
  INDEX idx_tool_date (tool_id, view_date),
  INDEX idx_date (view_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS tool_runs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tool_id INT NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  country VARCHAR(80) DEFAULT NULL,
  browser VARCHAR(50) DEFAULT NULL,
  os VARCHAR(50) DEFAULT NULL,
  device_type ENUM('desktop','mobile','tablet','other') DEFAULT 'other',
  run_date DATE NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE,
  INDEX idx_tool_date (tool_id, run_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS daily_stats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stat_date DATE NOT NULL UNIQUE,
  total_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_runs BIGINT UNSIGNED NOT NULL DEFAULT 0,
  unique_visitors BIGINT UNSIGNED NOT NULL DEFAULT 0,
  mobile_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  desktop_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
  tablet_views BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS geoip_cache (
  ip_address VARCHAR(45) PRIMARY KEY,
  country VARCHAR(80) DEFAULT NULL,
  region VARCHAR(80) DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  cached_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

echo "Creating tables...\n";
foreach ($tables as $sql) {
    try {
        $pdo->exec($sql);
        // extract table name
        preg_match('/TABLE IF NOT EXISTS (\w+)/', $sql, $m);
        echo "<span class='ok'>  ✅ Table '{$m[1]}' ready</span>\n";
    } catch (PDOException $e) {
        echo "<span class='err'>  ❌ Error: " . $e->getMessage() . "</span>\n";
    }
}

// ── Sync Tools ───────────────────────────────────────────────
echo "\nSyncing tools from tools-data.json...\n";

$jsonPath = __DIR__ . '/../assets/tools-data.json';
if (!file_exists($jsonPath)) {
    echo "<span class='err'>❌ tools-data.json not found at: $jsonPath</span>\n";
} else {
    $data = json_decode(file_get_contents($jsonPath), true);
    $tools = $data['tools'] ?? [];
    $inserted = 0; $updated = 0;

    $stmt = $pdo->prepare(
        "INSERT INTO tools (tool_slug, tool_name, category, icon)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           tool_name = VALUES(tool_name),
           category  = VALUES(category),
           icon      = VALUES(icon)"
    );

    foreach ($tools as $tool) {
        if (empty($tool['published']) || empty($tool['id'])) continue;
        $check = $pdo->prepare("SELECT id FROM tools WHERE tool_slug = ?");
        $check->execute([$tool['id']]);
        $existed = (bool)$check->fetch();
        $stmt->execute([$tool['id'], $tool['name'] ?? $tool['id'], $tool['category'] ?? 'other', $tool['icon'] ?? null]);
        $existed ? $updated++ : $inserted++;
    }

    echo "<span class='ok'>  ✅ Added: $inserted new | Updated: $updated existing | Total: " . count($tools) . " tools</span>\n";
}

echo "\n<span class='ok' style='font-size:1.2em'>🎉 SETUP COMPLETE!</span>\n";
echo "\n<span class='info'>⚠️  Now DELETE this file from File Manager: public_html/backend/setup.php</span>\n";
echo "\n<a href='https://apnesoftware.com/skaler2015/' style='color:#7C5CFC'>→ Open Admin Panel</a>\n";
echo "</pre>";
