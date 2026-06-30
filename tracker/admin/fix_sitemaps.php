<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

echo "<pre style='background:#0d1117;color:#e6eaf2;padding:20px;font-family:sans-serif;font-size:13px'>";

function fetchSitemap($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_NOSIGNAL => 1,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ]);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($r && $c < 400) ? $r : '';
}

$sites = $pdo->query("SELECT id, website_name, website_url, sitemap_url FROM websites")->fetchAll();

foreach ($sites as $site) {
    echo "\n🌐 {$site['website_name']}\n";

    $siteUrl = rtrim($site['website_url'], '/');
    $savedSm = trim($site['sitemap_url'] ?? '');

    // Try sitemaps
    $toTry = [];
    if ($savedSm) $toTry[] = $savedSm;
    $toTry[] = $siteUrl . '/post-sitemap2.xml';
    $toTry[] = $siteUrl . '/post-sitemap.xml';
    $toTry   = array_unique($toTry);

    $urlDates = [];
    foreach ($toTry as $smUrl) {
        $xml = fetchSitemap($smUrl);
        if (!$xml || strpos($xml, '<loc>') === false) continue;
        echo "  ✅ Fetched: " . basename($smUrl) . "\n";

        preg_match_all('/<url>(.*?)<\/url>/is', $xml, $blocks);
        foreach ($blocks[1] as $block) {
            preg_match('/<loc>(.*?)<\/loc>/is',     $block, $lm);
            preg_match('/<lastmod>(.*?)<\/lastmod>/is', $block, $dm);
            $u = trim(strip_tags($lm[1] ?? ''));
            $d = substr(trim($dm[1] ?? ''), 0, 10);
            if ($u && strpos($u, 'sitemap') === false) {
                if (!isset($urlDates[$u]) || $d > $urlDates[$u]) {
                    $urlDates[$u] = $d ?: null;
                }
            }
        }
    }

    if (empty($urlDates)) { echo "  ⚠️ No URLs found\n"; continue; }

    // Sort by date DESC
    arsort($urlDates);
    $latest150 = array_slice(array_keys($urlDates), 0, 150, true);

    echo "  📋 Total sitemap URLs: " . count($urlDates) . "\n";
    echo "  📌 Latest 150 (newest first):\n";

    // Show top 5
    $shown = 0;
    foreach ($urlDates as $u => $d) {
        if ($shown >= 5) break;
        echo "    {$d} — " . basename(rtrim($u,'/')) . "\n";
        $shown++;
    }
    echo "    ... and " . (count($urlDates)-5) . " more\n";

    // Update lastmod for existing pages
    $updated = 0;
    $updateStmt = $pdo->prepare("UPDATE pages SET lastmod=? WHERE website_id=? AND page_url=?");
    foreach ($urlDates as $u => $d) {
        if ($d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $updateStmt->execute([$d, $site['id'], $u]);
            if ($updateStmt->rowCount() > 0) $updated++;
        }
    }
    echo "  ✅ Updated lastmod for {$updated} existing pages\n";

    // Delete pages NOT in latest 150 (cleanup old pages)
    // First ensure latest 150 are in DB
    $existCheck = $pdo->prepare("SELECT page_url FROM pages WHERE website_id=?");
    $existCheck->execute([$site['id']]);
    $existingInDB = array_flip($existCheck->fetchAll(PDO::FETCH_COLUMN));

    // Insert missing latest pages
    $insertStmt = $pdo->prepare("INSERT IGNORE INTO pages (website_id,page_url,lastmod,created_at) VALUES (?,?,?,NOW())");
    $inserted = 0;
    foreach ($latest150 as $u) {
        if (!isset($existingInDB[$u])) {
            try {
                $insertStmt->execute([$site['id'], $u, $urlDates[$u] ?: null]);
                $inserted++;
            } catch(Exception $e) {}
        }
    }
    if ($inserted > 0) echo "  ➕ Inserted {$inserted} missing latest pages\n";

    // Keep only latest 150 — delete old ones
    $countQ = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE website_id=?");
    $countQ->execute([$site['id']]);
    $totalPages = $countQ->fetchColumn();

    if ($totalPages > 150) {
        // Get IDs of latest 150 (by lastmod DESC, then id DESC)
        try {
            $keepQ = $pdo->prepare("
                SELECT id FROM pages WHERE website_id=?
                ORDER BY COALESCE(lastmod,'2000-01-01') DESC, id DESC
                LIMIT 150
            ");
        } catch(Exception $e) {
            $keepQ = $pdo->prepare("SELECT id FROM pages WHERE website_id=? ORDER BY id DESC LIMIT 150");
        }
        $keepQ->execute([$site['id']]);
        $keepIds = $keepQ->fetchAll(PDO::FETCH_COLUMN);

        if ($keepIds) {
            $ph = implode(',', array_fill(0, count($keepIds), '?'));
            $params = array_merge([$site['id']], $keepIds);
            $delQ = $pdo->prepare("DELETE FROM pages WHERE website_id=? AND id NOT IN ({$ph})");
            $delQ->execute($params);
            $deleted = $delQ->rowCount();
            echo "  🗑️ Deleted {$deleted} old pages (kept latest 150 of {$totalPages})\n";
        }
    }

    // Update sitemap_url to post-sitemap2.xml if Sarkari Result
    if (strpos($site['website_url'], 'sarkariresult') !== false) {
        $newSm = $siteUrl . '/post-sitemap2.xml';
        $pdo->prepare("UPDATE websites SET sitemap_url=? WHERE id=?")->execute([$newSm, $site['id']]);
        echo "  📌 Sitemap updated to post-sitemap2.xml\n";
    }
}

echo "\n\n✅ ALL DONE! Ab Full Scan chalao — sirf latest 150 pages aayenge.\n";
echo "</pre>";
echo "<a href='../cron/full_scan.php' style='padding:10px 20px;background:#7c5cfc;color:#fff;text-decoration:none;border-radius:8px;margin-right:10px'>🚀 Full Scan →</a>";
echo "<a href='websites.php' style='padding:10px 20px;background:#16c079;color:#fff;text-decoration:none;border-radius:8px'>← Websites</a>";
