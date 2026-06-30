<?php
// ============================================================
// Run this once after setting up the database, and again any
// time you add new tools to assets/tools-data.json.
// Visit it in your browser: https://apnesoftware.com/backend/sync_tools.php
// It will NOT delete or reset view/run counts for existing tools.
// ============================================================

require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

$pdo = get_db_connection();
if (!$pdo) {
    die("Database connection failed. Check backend/config.php\n");
}

$jsonPath = __DIR__ . '/../assets/tools-data.json';
if (!file_exists($jsonPath)) {
    die("Could not find assets/tools-data.json at expected path: $jsonPath\n");
}

$data = json_decode(file_get_contents($jsonPath), true);
if (!isset($data['tools']) || !is_array($data['tools'])) {
    die("tools-data.json did not parse as expected.\n");
}

$inserted = 0;
$updated = 0;

$stmt = $pdo->prepare(
    "INSERT INTO tools (tool_slug, tool_name, category, icon) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE tool_name = VALUES(tool_name), category = VALUES(category), icon = VALUES(icon)"
);

foreach ($data['tools'] as $tool) {
    if (empty($tool['published'])) continue;
    $slug = $tool['id'] ?? null;
    if (!$slug) continue;

    // check if it already existed before this upsert, just for the inserted/updated counter
    $check = $pdo->prepare("SELECT id FROM tools WHERE tool_slug = ?");
    $check->execute([$slug]);
    $existed = (bool) $check->fetch();

    $stmt->execute([
        $slug,
        $tool['name'] ?? $slug,
        $tool['category'] ?? 'other',
        $tool['icon'] ?? null,
    ]);

    if ($existed) $updated++; else $inserted++;
}

echo "Sync complete.\n";
echo "New tools added: $inserted\n";
echo "Existing tools updated: $updated\n";
echo "Total tools in tools-data.json: " . count($data['tools']) . "\n";
