<?php
// Pure AJAX endpoint - no HTML, no layout, no pageHeader
// Called by full_scan.php JS fetch()
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']); exit;
}

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once 'content_filter.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ── DISCOVER ──────────────────────────────────────────
if ($action === 'discover') {
    $wid  = (int)($_GET['wid'] ?? 0);
    $site = $pdo->prepare("SELECT * FROM websites WHERE id=?");
    $site->execute([$wid]);
    $site = $site->fetch();
    if (!$site) { echo json_encode(['done'=>true,'added'=>0,'msg'=>'Site not found']); exit; }

    $siteUrl     = rtrim($site['website_url'], '/');
    $savedSitemap = trim($site['sitemap_url'] ?? '');

    $toTry = [];
    if ($savedSitemap) $toTry[] = $savedSitemap;
    $toTry[] = $siteUrl.'/post-sitemap2.xml';
    $toTry[] = $siteUrl.'/post-sitemap.xml';
    $toTry[] = $siteUrl.'/sitemap.xml';
    $toTry = array_unique($toTry);

    // Fetch all sitemaps and collect URLs WITH lastmod dates
    $urlsWithDate = []; $usedSitemaps = [];

    foreach ($toTry as $smUrl) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL=>$smUrl, CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>8,
            CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_NOSIGNAL=>1,
            CURLOPT_USERAGENT=>'Mozilla/5.0',
            CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!$resp || $code >= 400 || strpos($resp,'<loc>') === false) continue;
        $usedSitemaps[] = basename($smUrl);

        // Parse <url><loc>...</loc><lastmod>...</lastmod></url> blocks
        preg_match_all('/<url>(.*?)<\/url>/is', $resp, $urlBlocks);
        foreach ($urlBlocks[1] as $block) {
            preg_match('/<loc>(.*?)<\/loc>/is', $block, $locM);
            preg_match('/<lastmod>(.*?)<\/lastmod>/is', $block, $modM);
            $u = trim(strip_tags($locM[1] ?? ''));
            $d = trim($modM[1] ?? '2020-01-01');
            if ($u && strpos($u,'sitemap') === false) {
                // Keep latest date if URL seen in multiple sitemaps
                if (!isset($urlsWithDate[$u]) || $d > $urlsWithDate[$u]) {
                    $urlsWithDate[$u] = $d;
                }
            }
        }

        // Also parse plain <loc> tags (fallback)
        if (empty($urlBlocks[1])) {
            preg_match_all('/<loc>(.*?)<\/loc>/is', $resp, $locs);
            foreach ($locs[1] as $u) {
                $u = trim(strip_tags($u));
                if ($u && strpos($u,'sitemap') === false && !isset($urlsWithDate[$u]))
                    $urlsWithDate[$u] = '2020-01-01';
            }
        }
    }

    if (empty($urlsWithDate)) {
        echo json_encode(['done'=>true,'added'=>0,'msg'=>'No URLs found in sitemaps']);
        exit;
    }

    // Sort by lastmod DESCENDING — latest first
    arsort($urlsWithDate);

    // Get existing URLs for this website
    $existing = $pdo->prepare("SELECT page_url FROM pages WHERE website_id=?");
    $existing->execute([$wid]);
    $existingSet = array_flip($existing->fetchAll(PDO::FETCH_COLUMN));

    // Take only NEW urls (not already tracked) — latest first — max 150
    $newUrls = [];
    foreach ($urlsWithDate as $u => $date) {
        if (!isset($existingSet[$u])) {
            $newUrls[] = $u;
            if (count($newUrls) >= 150) break;
        }
    }

    $added = 0;
    if ($newUrls) {
        // Bulk insert - simple and reliable
        $ph   = implode(',', array_fill(0, count($newUrls), '(?,?,NOW())'));
        $vals = [];
        foreach ($newUrls as $u) { $vals[] = $wid; $vals[] = $u; }
        try {
            $st = $pdo->prepare("INSERT IGNORE INTO pages (website_id,page_url,created_at) VALUES {$ph}");
            $st->execute($vals);
            $added = $st->rowCount();
        } catch(Exception $e) {
            // One by one fallback
            foreach ($newUrls as $u) {
                try {
                    $pdo->prepare("INSERT IGNORE INTO pages (website_id,page_url,created_at) VALUES (?,?,NOW())")
                        ->execute([$wid, $u]);
                    $added++;
                } catch(Exception $e2) {}
            }
        }
    }

    echo json_encode([
        'done'     => true,
        'added'    => $added,
        'total'    => count($urlsWithDate),
        'sitemaps' => implode(', ', $usedSitemaps),
        'msg'      => "Added {$added} latest new pages from: ".implode(', ', $usedSitemaps)
    ]);
    exit;
}

// ── SCAN PAGE ─────────────────────────────────────────
if ($action === 'scan_page') {
    $pid  = (int)($_GET['pid'] ?? 0);
    $page = $pdo->prepare("SELECT p.*, w.website_name FROM pages p INNER JOIN websites w ON p.website_id=w.id WHERE p.id=?");
    $page->execute([$pid]);
    $page = $page->fetch();
    if (!$page) { echo json_encode(['changed'=>false,'msg'=>'Not found']); exit; }

    $url = $page['page_url'];
    if (strpos($url,'sitemap')!==false) {
        echo json_encode(['changed'=>false,'msg'=>'Sitemap skipped']); exit;
    }

    // Check exclusions
    try {
        $excls = $pdo->query("SELECT url_pattern,match_type,website_id FROM excluded_pages")->fetchAll();
        foreach ($excls as $ex) {
            if ($ex['website_id'] && $ex['website_id'] != $page['website_id']) continue;
            $match = false;
            if ($ex['match_type']==='exact')       $match = ($url===$ex['url_pattern']);
            elseif ($ex['match_type']==='contains') $match = (strpos($url,$ex['url_pattern'])!==false);
            else                                    $match = (strpos($url,$ex['url_pattern'])===0);
            if ($match) { echo json_encode(['changed'=>false,'excluded'=>true,'msg'=>'Excluded']); exit; }
        }
    } catch(Exception $e) {}

    // Fetch
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>8,
        CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_NOSIGNAL=>1,
        CURLOPT_USERAGENT=>'Mozilla/5.0',
        CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,
    ]);
    $html  = curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err   = curl_error($ch);
    curl_close($ch);

    if ($err || $code>=400 || !$html) {
        $pdo->prepare("UPDATE pages SET last_scan=NOW() WHERE id=?")->execute([$pid]);
        echo json_encode(['changed'=>false,'error'=>true,'msg'=>$err?:"HTTP {$code}"]);
        exit;
    }

    // Extract
    preg_match('/<title[^>]*>(.*?)<\/title>/is',$html,$tm);
    $title   = trim(strip_tags(html_entity_decode($tm[1]??'',ENT_QUOTES,'UTF-8')));
    $content = SmartContentFilter::process($html, $url);
    $newHash = md5(SmartContentFilter::normalize($content));
    $category = SmartContentFilter::detectChangeType('', $content, $url);
    $catName  = $category['type'];
    $priority = $category['priority'];

    // First scan — save baseline
    if (empty($page['content_hash'])) {
        $pdo->prepare("UPDATE pages SET title=?,content=?,content_hash=?,last_scan=NOW() WHERE id=?")
            ->execute([$title,$content,$newHash,$pid]);
        echo json_encode(['changed'=>false,'baseline'=>true,'msg'=>'Baseline saved','title'=>$title]);
        exit;
    }

    $oldHash = $page['content_hash'] ?? '';
    // Resolved check
    $resolvedHash = $page['resolved_hash'] ?? '';
    if ($resolvedHash && $newHash===$resolvedHash) {
        $pdo->prepare("UPDATE pages SET last_scan=NOW() WHERE id=?")->execute([$pid]);
        echo json_encode(['changed'=>false,'msg'=>'No change (resolved)']);
        exit;
    }
    // No change
    if ($newHash===$oldHash) {
        $pdo->prepare("UPDATE pages SET last_scan=NOW() WHERE id=?")->execute([$pid]);
        echo json_encode(['changed'=>false,'msg'=>'No change']);
        exit;
    }

    $oldContent  = $page['content'] ?? '';
    $filterResult = SmartContentFilter::shouldReport($oldContent, $content, $url, 3.0);
    $changed = false;

    if ($filterResult['report']) {
        // Confidence score
        $sim = 0;
        similar_text(strtolower($oldContent), strtolower($content), $sim);
        $confidence = min(100, round((100-$sim)*1.5));
        if ($category['important']) $confidence = min(100, $confidence+15);

        // Auto AI summary for high priority
        $note = '';
        if ($priority >= 7) {
            try {
                $apiKey = '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';
                $oldSnip = mb_substr(strip_tags($oldContent),0,600);
                $newSnip = mb_substr(strip_tags($content),0,600);
                $prompt  = "Govt job page change. Category:{$catName}.\nOLD:{$oldSnip}\nNEW:{$newSnip}\nWhat changed? EN: [1 line] | HI: [1 line]";
                $aiCh = curl_init('https://api.anthropic.com/v1/messages');
                curl_setopt_array($aiCh,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,
                    CURLOPT_POSTFIELDS=>json_encode(['model'=>'claude-haiku-4-5-20251001','max_tokens'=>100,
                        'messages'=>[['role'=>'user','content'=>$prompt]]]),
                    CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.$apiKey,'anthropic-version: 2023-06-01']]);
                $aiR = curl_exec($aiCh); curl_close($aiCh);
                $aiD = json_decode($aiR,true);
                if (!empty($aiD['content'][0]['text'])) $note = trim($aiD['content'][0]['text']);
            } catch(Exception $e) {}
        }

        // No duplicate changes today: UPDATE if exists, INSERT if new
        $todayChk = $pdo->prepare("SELECT id FROM changes WHERE page_id=? AND DATE(detected_at)=CURDATE() LIMIT 1");
        $todayChk->execute([$pid]);
        $existId = $todayChk->fetchColumn();

        if ($existId) {
            // Update existing record — keep old_content, update new_content only
            try { $pdo->prepare("UPDATE changes SET new_content=?,detected_at=NOW(),note=? WHERE id=?")->execute([$content,$note,$existId]); }
            catch(Exception $e2) { $pdo->prepare("UPDATE changes SET new_content=?,detected_at=NOW() WHERE id=?")->execute([$content,$existId]); }
        } else {
            // First change today for this page
            try { $pdo->prepare("INSERT INTO changes (website_id,page_id,change_type,old_content,new_content,detected_at,note) VALUES(?,?,'CONTENT_CHANGED',?,?,NOW(),?)")->execute([$page['website_id'],$pid,$oldContent,$content,$note]); }
            catch(Exception $e2) { $pdo->prepare("INSERT INTO changes (website_id,page_id,change_type,old_content,new_content,detected_at) VALUES(?,?,'CONTENT_CHANGED',?,?,NOW())")->execute([$page['website_id'],$pid,$oldContent,$content]); }
        }

        // Auto Telegram for important pages
        if ($category['important']) {
            try {
                $tgT = $pdo->query("SELECT setting_value FROM tracker_settings WHERE setting_key='telegram_bot_token'")->fetchColumn();
                $tgC = $pdo->query("SELECT setting_value FROM tracker_settings WHERE setting_key='telegram_chat_id'")->fetchColumn();
                $tgE = $pdo->query("SELECT setting_value FROM tracker_settings WHERE setting_key='telegram_enabled'")->fetchColumn();
                if ($tgT && $tgC && $tgE==='1') {
                    $path2 = parse_url($url,PHP_URL_PATH)?:'/';
                    $msg2 = "⚡ {$catName} Change!\n{$page['website_name']}\n{$path2}";
                    if ($note) $msg2 .= "\n💡 ".substr($note,0,200);
                    $tc = curl_init("https://api.telegram.org/bot{$tgT}/sendMessage");
                    curl_setopt_array($tc,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,
                        CURLOPT_POSTFIELDS=>http_build_query(['chat_id'=>$tgC,'text'=>$msg2])]);
                    curl_exec($tc); curl_close($tc);
                }
            } catch(Exception $e) {}
        }

        $changed = true;
    }

    $pdo->prepare("UPDATE pages SET title=?,content=?,content_hash=?,last_scan=NOW() WHERE id=?")
        ->execute([$title,$content,$newHash,$pid]);

    echo json_encode([
        'changed'  => $changed,
        'msg'      => $changed?'CHANGED':'No meaningful change (filtered)',
        'title'    => $title,
        'category' => $catName,
        'priority' => $priority,
        'diff'     => $filterResult['diff'],
    ]);
    exit;
}

echo json_encode(['error'=>'Unknown action: '.$action]);
