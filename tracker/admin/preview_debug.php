<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Get first change record for testing
$row = $pdo->query("SELECT c.id, c.page_url, p.page_url as purl FROM changes c LEFT JOIN pages p ON c.page_id=p.id LIMIT 1")->fetch();
echo "<pre>";
echo "Change ID: " . ($row['id'] ?? 'none') . "\n";
echo "page_url from changes: " . ($row['page_url'] ?? 'empty') . "\n";
echo "page_url from pages: " . ($row['purl'] ?? 'empty') . "\n";
echo "</pre>";

if ($row) {
    echo "<a href='page_preview.php?id={$row['id']}&mode=new' target='_blank'>Test New Preview</a><br>";
    echo "<a href='page_preview.php?id={$row['id']}&mode=old' target='_blank'>Test Old Preview</a>";
}
?>
