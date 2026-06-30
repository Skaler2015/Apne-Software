<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Create table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS excluded_pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        website_id INT NULL,
        url_pattern VARCHAR(500) NOT NULL,
        match_type ENUM('exact','contains','starts_with') DEFAULT 'contains',
        reason VARCHAR(200) NULL,
        created_at DATETIME DEFAULT NOW()
    )");
} catch(Exception $e) {}

// Static/category pages to exclude from ALL websites
$excludePatterns = [
    '/latest-jobs'    => 'Category page',
    '/admit-card'     => 'Category page',
    '/answer-key'     => 'Category page',
    '/syllabus'       => 'Category page',
    '/result'         => 'Category page',
    '/admission'      => 'Category page',
    '/contact'        => 'Static page',
    '/about'          => 'Static page',
    '/privacy-policy' => 'Static page',
    '/disclaimer'     => 'Static page',
    '/sitemap'        => 'Sitemap page',
    '/tag/'           => 'Tag archive',
    '/category/'      => 'Category archive',
    '/page/'          => 'Pagination',
    'post-sitemap'    => 'Sitemap XML',
];

// Also exclude exact homepage URLs
$homepages = $pdo->query("SELECT id, website_url FROM websites")->fetchAll();

echo "<pre style='background:#0d1117;color:#e6eaf2;padding:20px;font-family:sans-serif;font-size:13px'>";

$added = 0;
foreach ($excludePatterns as $pattern => $reason) {
    try {
        // Check if already exists
        $exists = $pdo->prepare("SELECT id FROM excluded_pages WHERE url_pattern=? AND website_id IS NULL");
        $exists->execute([$pattern]);
        if (!$exists->fetch()) {
            $pdo->prepare("INSERT INTO excluded_pages (website_id, url_pattern, match_type, reason) VALUES (NULL,?,'contains',?)")
                ->execute([$pattern, $reason]);
            echo "✅ Added: $pattern ($reason)\n";
            $added++;
        } else {
            echo "ℹ️ Already exists: $pattern\n";
        }
    } catch(Exception $e) {
        echo "❌ Error: $pattern — " . $e->getMessage() . "\n";
    }
}

// Add exact homepages
foreach ($homepages as $site) {
    $homeUrl = rtrim($site['website_url'], '/') . '/';
    $homeUrl2 = rtrim($site['website_url'], '/');
    foreach ([$homeUrl, $homeUrl2] as $u) {
        $exists = $pdo->prepare("SELECT id FROM excluded_pages WHERE url_pattern=? AND match_type='exact'");
        $exists->execute([$u]);
        if (!$exists->fetch()) {
            $pdo->prepare("INSERT INTO excluded_pages (website_id, url_pattern, match_type, reason) VALUES (?,?,'exact','Homepage')")
                ->execute([$site['id'], $u]);
            echo "✅ Homepage excluded: $u\n";
            $added++;
        }
    }
}

echo "\n✅ Total $added new exclusions added\n";

// Also delete existing changes for these URLs
$deleted = 0;
foreach ($excludePatterns as $pattern => $reason) {
    $del = $pdo->prepare("DELETE FROM changes WHERE page_id IN (SELECT id FROM pages WHERE page_url LIKE ?)");
    $del->execute(['%' . $pattern . '%']);
    $deleted += $del->rowCount();
}
echo "🗑️ $deleted existing changes deleted for excluded pages\n";

echo "\n<a href='tracker/admin/changes.php' style='padding:8px 16px;background:#7c5cfc;color:#fff;text-decoration:none;border-radius:6px'>← Back to Changes</a>";
echo "</pre>";
