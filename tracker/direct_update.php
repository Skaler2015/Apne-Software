<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Direct DB update - no form processing
$updates = [
    1 => 'https://sarkariresult.com.cm/post-sitemap.xml',   // Sarkari Result
    2 => 'https://www.freejobalert.com/post-sitemap.xml',   // FreeJobAlert  
    3 => 'https://rojgarresult.com/post-sitemap.xml',       // Rojgar Result
];

echo "<pre style='background:#0d1117;color:#e6eaf2;padding:20px;font-family:sans-serif'>";

foreach ($updates as $id => $sitemap) {
    try {
        $pdo->prepare("UPDATE websites SET sitemap_url=? WHERE id=?")->execute([$sitemap, $id]);
        $site = $pdo->prepare("SELECT website_name, sitemap_url FROM websites WHERE id=?");
        $site->execute([$id]);
        $site = $site->fetch();
        echo "✅ #{$id} {$site['website_name']} → {$site['sitemap_url']}\n";
    } catch(Exception $e) {
        echo "❌ #{$id} Error: " . $e->getMessage() . "\n";
    }
}

echo "\n--- Current websites table ---\n";
$all = $pdo->query("SELECT id, website_name, website_url, sitemap_url, status FROM websites")->fetchAll();
foreach ($all as $w) {
    echo "#{$w['id']} {$w['website_name']}\n";
    echo "  URL: {$w['website_url']}\n";
    echo "  Sitemap: " . ($w['sitemap_url'] ?: '(none)') . "\n";
    echo "  Status: {$w['status']}\n\n";
}
echo "</pre>";
echo "<a href='../tracker/admin/websites.php' style='padding:10px 20px;background:#7c5cfc;color:#fff;text-decoration:none;border-radius:8px'>← Back to Websites</a>";
