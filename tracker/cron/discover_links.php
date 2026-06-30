<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

$wid = (int)($_GET['wid'] ?? 0);

pageHeader('Discover Links');

function discLog($msg, $color = '#e6eaf2')
{
    echo "<div style='color:{$color};padding:2px 0;border-bottom:1px solid rgba(255,255,255,.04)'>{$msg}</div>";

    if (ob_get_level()) {
        @ob_flush();
    }

    flush();
}

function fetchUrl($url)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ChangeTracker/2.0)');

    $html = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($httpCode >= 400 || !$html) {
        return false;
    }

    return $html;
}

function normalizeUrl($url)
{
    $url = trim($url);

    $url = strtok($url, '#');
    $url = strtok($url, '?');

    $parts = parse_url($url);

    if (!$parts || empty($parts['host'])) {
        return false;
    }

    $scheme = 'https';

    $host = strtolower($parts['host']);

    $path = $parts['path'] ?? '/';

    $path = preg_replace('#/+#', '/', $path);

    if ($path !== '/') {
        $path = rtrim($path, '/');
    }

    return $scheme . '://' . $host . $path;
}

?>

<div class="container py-4" style="max-width:860px">

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="../dashboard.php">Dashboard</a>
        </li>
        <li class="breadcrumb-item active">
            Discover Links
        </li>
    </ol>
</nav>

<div class="d-flex align-items-center gap-3 mb-4">
    <div style="background:rgba(22,192,121,.15);border:1px solid rgba(22,192,121,.3);border-radius:12px;padding:10px 14px;font-size:1.4rem">
        🔗
    </div>

    <div>
        <h1 class="page-title">Discover Links</h1>
        <p class="text-muted mb-0" style="font-size:.85rem">
            Crawls websites and discovers pages for monitoring
        </p>
    </div>
</div>

<div class="card">

    <div class="card-header">
        <i class="bi bi-terminal me-2"></i>
        Discovery Output

        <span class="badge bg-secondary ms-2" id="discStatus">
            Running...
        </span>
    </div>

    <div class="card-body p-0">

        <div id="discLog"
             style="font-family:monospace;font-size:.82rem;padding:16px;max-height:500px;overflow-y:auto;background:var(--bg)">

<?php

try {

    if ($wid) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM websites
            WHERE id=?
            AND (status='active' OR status='1')
        ");

        $stmt->execute([$wid]);

    } else {

        $stmt = $pdo->query("
            SELECT *
            FROM websites
            WHERE (status='active' OR status='1')
        ");
    }

    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$sites) {

        discLog(
            '⚠️ No active websites found.',
            '#facc15'
        );

    } else {

        $totalAdded = 0;

        foreach ($sites as $site) {

            discLog(
                "🌐 Processing: {$site['website_name']} ({$site['website_url']})",
                '#60a5fa'
            );

            $baseUrl  = normalizeUrl($site['website_url']);
            $baseHost = parse_url($baseUrl, PHP_URL_HOST);

            $html = fetchUrl($baseUrl);

            if (!$html) {

                discLog(
                    '&nbsp;&nbsp;⛔ Unable to fetch website',
                    '#ef4444'
                );

                continue;
            }

            $foundLinks = [];

            preg_match_all(
                '/href=["\']([^"\']+)["\']/i',
                $html,
                $matches
            );

            if (!empty($matches[1])) {

                foreach ($matches[1] as $link) {

                    $link = trim($link);

                    if (
                        empty($link) ||
                        str_starts_with($link, '#') ||
                        str_starts_with($link, 'javascript:') ||
                        str_starts_with($link, 'mailto:') ||
                        str_starts_with($link, 'tel:')
                    ) {
                        continue;
                    }

                    if (!str_starts_with($link, 'http')) {

                        if (str_starts_with($link, '/')) {

                            $link =
                                'https://' .
                                $baseHost .
                                $link;

                        } else {

                            $link =
                                rtrim($baseUrl, '/') .
                                '/' .
                                ltrim($link, '/');
                        }
                    }

                    $link = normalizeUrl($link);

                    if (!$link) {
                        continue;
                    }

                    if (
                        parse_url($link, PHP_URL_HOST)
                        !==
                        $baseHost
                    ) {
                        continue;
                    }

                    if (
                        str_contains($link, '/feed') ||
                        str_contains($link, '/comments/feed') ||
                        str_contains($link, '/wp-json') ||
                        str_contains($link, '/xmlrpc.php') ||
                        str_contains($link, '/wp-admin')
                    ) {
                        continue;
                    }

                    if (
                        preg_match(
                            '/\.(jpg|jpeg|png|gif|webp|svg|pdf|zip|rar|7z|mp4|mp3|css|js|ico|woff|woff2)$/i',
                            $link
                        )
                    ) {
                        continue;
                    }

                    $foundLinks[$link] = true;
                }
            }

            // Sitemap.xml Crawl

            $sitemapUrl = rtrim($baseUrl, '/') . '/sitemap.xml';

            $sitemapXml = fetchUrl($sitemapUrl);

            if ($sitemapXml) {

                discLog(
                    '&nbsp;&nbsp;🗺️ Sitemap Found',
                    '#16c079'
                );

                preg_match_all(
                    '/<loc>(.*?)<\/loc>/i',
                    $sitemapXml,
                    $sitemapMatches
                );

                foreach ($sitemapMatches[1] as $loc) {

                    $loc = normalizeUrl(trim($loc));

                    if (!$loc) {
                        continue;
                    }

                    if (
                        parse_url($loc, PHP_URL_HOST)
                        !==
                        $baseHost
                    ) {
                        continue;
                    }

                    $foundLinks[$loc] = true;
                }

            } else {

                discLog(
                    '&nbsp;&nbsp;⚠️ Sitemap Not Found',
                    '#facc15'
                );
            }

            $added   = 0;
            $skipped = 0;

            foreach (array_keys($foundLinks) as $url) {

                $check = $pdo->prepare("
                    SELECT id
                    FROM pages
                    WHERE page_url=?
                    LIMIT 1
                ");

                $check->execute([$url]);

                if ($check->fetch()) {

                    $skipped++;
                    continue;
                }

                $insert = $pdo->prepare("
                    INSERT INTO pages
                    (
                        website_id,
                        page_url
                    )
                    VALUES
                    (
                        ?,
                        ?
                    )
                ");

                $insert->execute([
                    $site['id'],
                    $url
                ]);

                discLog(
                    "&nbsp;&nbsp;✅ Added: <a href='" . htmlspecialchars($url) . "' target='_blank' style='color:#16c079;text-decoration:underline;text-underline-offset:2px'>" . htmlspecialchars($url) . " ↗</a>",
                    '#16c079'
                );

                $added++;
                $totalAdded++;
            }

            discLog(
                "&nbsp;&nbsp;📊 Added: {$added} | Skipped: {$skipped}",
                '#60a5fa'
            );

            discLog('');
        }

        discLog(
            '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━',
            '#2a3550'
        );

        discLog(
            "✅ Discovery Complete — Total New Pages Added: {$totalAdded}",
            '#16c079'
        );
    }

} catch (Exception $e) {

    discLog(
        '⛔ FATAL ERROR: ' . $e->getMessage(),
        '#ef4444'
    );
}

?>

        </div>
    </div>

    <div class="card-footer d-flex gap-2">

        <a href="../cron/scan_changes.php"
           class="btn btn-primary btn-sm">
            Run Scan Now
        </a>

        <a href="../dashboard.php"
           class="btn btn-secondary btn-sm">
            Dashboard
        </a>

    </div>

</div>

</div>

<script>
document.getElementById('discStatus').textContent = 'Complete';
document.getElementById('discStatus').className = 'badge bg-success ms-2';

const log = document.getElementById('discLog');
log.scrollTop = log.scrollHeight;
</script>

<?php pageFooter(); ?>
