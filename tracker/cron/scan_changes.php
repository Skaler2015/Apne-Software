<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

$wid = (int)(isset($_GET['wid']) ? $_GET['wid'] : 0);

// Batch scanning support
$batchSize  = 100; // pages per batch
$batchStart = (int)(isset($_GET['start']) ? $_GET['start'] : 0);
$batchEnd   = $batchStart + $batchSize;

pageHeader('Scan Changes');

function logLine($msg, $color = '#e6eaf2', $url = '') {
    if ($url) {
        $safe = "<a href='" . htmlspecialchars($url) . "' target='_blank'
                    style='color:inherit;text-decoration:underline;text-underline-offset:2px'>
                    {$msg} <span style='font-size:.7rem;opacity:.6'>&#8599;</span></a>";
    } else { $safe = $msg; }
    echo "<div style='color:{$color};padding:3px 0;border-bottom:1px solid rgba(255,255,255,.04);font-size:.8rem'>{$safe}</div>";
    if (ob_get_level()) @ob_flush(); flush();
}

function updateProgress($done, $total) {
    if ($total <= 0) return;
    $pct = round($done / $total * 100);
    echo "<script>
      ['progBar','progPct','progDone','progPending','pendBar'].forEach(function(id){
        var el=document.getElementById(id); if(!el) return;
        if(id==='progBar') el.style.width='{$pct}%';
        else if(id==='progPct') el.textContent='{$pct}%';
        else if(id==='progDone') el.textContent='{$done}';
        else if(id==='progPending') el.textContent='" . ($total-$done) . "';
        else if(id==='pendBar') el.style.width='" . (100-$pct) . "%';
      });
    </script>";
    if (ob_get_level()) @ob_flush(); flush();
}

// ── Smart content extractor ──────────────────────────────
function extractContent($html, $pageUrl) {
    if (!$html) return '';

    // Step 1: Remove scripts, styles, noscript
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
    $html = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', '', $html);

    // Step 2: Remove structural noise blocks (nav, header, footer, aside)
    foreach (array('nav','header','footer','aside','form') as $tag) {
        $html = preg_replace('/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '>/is', '', $html);
    }

    // Step 3: Remove divs/sections with noise class/id patterns
    $noisePattern = '/(nav|navbar|menu|sidebar|widget|footer|header|breadcrumb|advertisement|banner|cookie|popup|modal|related|tags|comment|download-app|app-download|follow-us|social|share-btn|whatsapp|telegram)/i';
    $html = preg_replace_callback(
        '/<(div|section|ul|aside)[^>]+(?:class|id)=["\']([^"\']*)["\'][^>]*>(.*?)<\/\1>/is',
        function($m) use ($noisePattern) {
            if (preg_match($noisePattern, $m[2])) return '';
            return $m[0];
        },
        $html
    );

    // Step 4: Try to find main content area
    $patterns = array(
        // Article tag (most reliable)
        '/<article\b[^>]*>(.*?)<\/article>/is',
        // Common CMS content classes
        '/<div[^>]+class=["\'][^"\']*\b(post-content|entry-content|article-content|article-body|post-body|content-body|td-post-content|jeg_post_content|single-post-content|article__content|post__content|the-content|main-content|page-content)\b[^"\']*["\'][^>]*>(.*?)<\/div>/is',
        // Main tag
        '/<main\b[^>]*>(.*?)<\/main>/is',
        // ID-based
        '/<div[^>]+id=["\'](?:content|main|post|article|entry|the-content|post-content|primary)["\'][^>]*>(.*?)<\/div>/is',
    );

    $main = '';
    foreach ($patterns as $pat) {
        if (preg_match($pat, $html, $m)) {
            $candidate = end($m); // last capture group = deepest match
            $candidate = strip_tags($candidate);
            $candidate = html_entity_decode($candidate, ENT_QUOTES, 'UTF-8');
            $candidate = preg_replace('/\s+/', ' ', trim($candidate));
            if (strlen($candidate) >= 150) {
                $main = $candidate;
                break;
            }
        }
    }

    // Step 5: Fallback to full stripped body
    if (strlen($main) < 150) {
        $main = strip_tags($html);
        $main = html_entity_decode($main, ENT_QUOTES, 'UTF-8');
        $main = preg_replace('/\s+/', ' ', trim($main));
    }

    // Step 6: Remove DYNAMIC noise phrases that change every scan
    // These are site-specific patterns that are NOT real content changes
    $dynamicPatterns = array(
        // Timestamps - all formats
        '/Updated\s+[A-Za-z]+\s+\d{1,2},?\s+\d{4}[^<]*/i',
        '/\b[A-Za-z]+\s+\d{1,2},\s+\d{4}\s+\d{1,2}:\d{2}\s*[AP]M\b/i',
        '/\d{1,2}:\d{2}\s*[AP]M\b/i',
        '/\d+\s+(?:second|minute|hour|day|week|month)s?\s+ago/i',
        '/Last\s+[Uu]pdated[\s:]+[^.]+/i',
        '/Post\s+Date\s*:[^.\n]+/i',
        // Author bylines
        '/\bby\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)+/i',
        '/Posted\s+by[^.]+/i',
        // FreeJobAlert specific
        '/Add\s+FJA\s+on[^.]+/i',
        '/As\s+Preferred\s+Source[^.]*\.?/i',
        '/FJA\s+on[^.]+/i',
        // SarkariResult specific  
        '/Download\s+SarkariResult\s+App\s+Now[^.]*\.?/i',
        '/SarkariResult\.com\.cm[^\s]*/i',
        // App download prompts
        '/Download\s+(?:Mobile\s+)?App[^.]*\.?/i',
        '/Install\s+(?:Our\s+)?App[^.]*\.?/i',
        // Social media follow sections
        '/FOLLOW\s+US[^.]+/i',
        '/Follow\s+(?:Us|On)[^.]+(?:WhatsApp|Telegram|Instagram|YouTube)/i',
        '/(?:WhatsApp|Telegram|Instagram|YouTube|Facebook)\s+(?:Group|Channel|Page)[^.]*\.?/i',
        // View/share counts
        '/\d+[,\d]*\s+(?:views?|shares?|comments?|likes?|reads?)\b/i',
        // Copyright notices
        '/Copyright\s+[©©]?\s*\d{4}[^.]+\.?/i',
        // Advertisement markers  
        '/\[?(?:Advertisement|Sponsored|Ad)\]?/i',
    );

    foreach ($dynamicPatterns as $dp) {
        $main = preg_replace($dp, '', $main);
    }

    // Final normalize
    $main = preg_replace('/\s+/', ' ', trim($main));

    return $main;
}

// Store content in a normalized form for better comparison
function normalizeForStorage($text) {
    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    // Remove extra whitespace
    $text = preg_replace('/\s+/', ' ', trim($text));
    return $text;
}

// ── Similarity with normalization ────────────────────────
function isMeaningfulChange($old, $new) {
    if (!$old && !$new) return false;
    if (!$old || !$new) return true;
    // Normalize: lowercase + remove punctuation + collapse spaces
    $n = function($t) {
        $t = strtolower($t);
        $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
        $t = preg_replace('/[^a-z0-9\s]/u', ' ', $t);
        $t = preg_replace('/\s+/', ' ', trim($t));
        return $t;
    };
    return $n($old) !== $n($new);
}

function getChangePct($old, $new) {
    if (!$old && !$new) return 0.0;
    if (!$old || !$new) return 100.0;

    // Normalize for comparison: lowercase, remove punctuation, collapse spaces
    $normalize = function($t) {
        $t = strtolower($t);
        $t = preg_replace('/[^\w\s]/u', ' ', $t);
        $t = preg_replace('/\s+/', ' ', trim($t));
        return $t;
    };

    $oldN = $normalize($old);
    $newN = $normalize($new);

    if ($oldN === $newN) return 0.0;

    $sim = 0.0;
    similar_text($oldN, $newN, $sim);
    return round(100 - $sim, 1);
}
?>

<div class="container py-4" style="max-width:900px">
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Scan Changes</li>
  </ol>
</nav>
<div class="d-flex align-items-center gap-3 mb-4">
  <div style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:10px 14px;font-size:1.4rem">🔍</div>
  <div>
    <h1 class="page-title">Scan Changes</h1>
    <p class="text-muted mb-0" style="font-size:.85rem">
      Smart scan — <span id="batchInfo" style="color:var(--accent);font-weight:600"></span>
    </p>
  </div>
</div>

<!-- PROGRESS -->
<div class="card mb-3">
  <div class="card-body p-3">
    <div class="d-flex align-items-center justify-content-between mb-1">
      <span style="font-size:.82rem;color:var(--dim)"><i class="bi bi-arrow-repeat me-1"></i>Scan Progress</span>
      <span id="progPct" style="font-weight:800;color:var(--accent);font-size:1rem">0%</span>
    </div>
    <div style="background:var(--bg3);border-radius:20px;height:14px;overflow:hidden;margin-bottom:10px">
      <div id="progBar" style="height:100%;width:0%;background:linear-gradient(90deg,var(--accent),#a78bfa);border-radius:20px;transition:width .4s ease"></div>
    </div>
    <div class="row g-2 text-center">
      <div class="col-3">
        <div style="background:var(--bg3);border-radius:10px;padding:8px 4px">
          <div id="totalPages" style="font-size:1.1rem;font-weight:800;color:var(--txt)">—</div>
          <div style="font-size:.68rem;color:var(--dim)">Total</div>
        </div>
      </div>
      <div class="col-3">
        <div style="background:var(--bg3);border-radius:10px;padding:8px 4px">
          <div id="progDone" style="font-size:1.1rem;font-weight:800;color:var(--accent)">0</div>
          <div style="font-size:.68rem;color:var(--dim)">Scanned</div>
        </div>
      </div>
      <div class="col-3">
        <div style="background:var(--bg3);border-radius:10px;padding:8px 4px">
          <div id="progPending" style="font-size:1.1rem;font-weight:800;color:var(--yellow)">—</div>
          <div style="font-size:.68rem;color:var(--dim)">Pending</div>
        </div>
      </div>
      <div class="col-3">
        <div style="background:var(--bg3);border-radius:10px;padding:8px 4px">
          <div id="changesCount" style="font-size:1.1rem;font-weight:800;color:var(--red)">0</div>
          <div style="font-size:.68rem;color:var(--dim)">Changes</div>
        </div>
      </div>
    </div>
    <div class="d-flex justify-content-between mt-2 mb-1">
      <span style="font-size:.75rem;color:var(--dim)">Pending</span>
      <span style="font-size:.75rem;color:var(--dim)">Complete</span>
    </div>
    <div style="background:var(--bg3);border-radius:20px;height:6px;overflow:hidden">
      <div id="pendBar" style="height:100%;width:100%;background:rgba(250,204,21,.35);border-radius:20px;transition:width .4s ease"></div>
    </div>
  </div>
</div>

<!-- LOG -->
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span><i class="bi bi-terminal me-2"></i>Scan Output</span>
    <span class="badge bg-secondary ms-2" id="scanStatus">Running...</span>
  </div>
  <div class="card-body p-0">
    <div id="scanLog" style="font-family:monospace;font-size:.8rem;padding:14px;max-height:460px;overflow-y:auto;background:var(--bg)">

<?php

// Change threshold — only report if content changed by this % or more
define('CHANGE_THRESHOLD', 0.0);

try {
    if ($wid > 0) {
        $stmt = $pdo->prepare("
            SELECT p.*, w.website_name
            FROM pages p INNER JOIN websites w ON p.website_id = w.id
            WHERE p.website_id = ? AND (w.status='active' OR w.status='1')
            ORDER BY p.id ASC
            LIMIT " . $batchSize . " OFFSET " . $batchStart . "
        ");
        $stmt->execute(array($wid));
    } else {
        $stmt = $pdo->query("
            SELECT p.*, w.website_name
            FROM pages p INNER JOIN websites w ON p.website_id = w.id
            WHERE (w.status='active' OR w.status='1')
            ORDER BY p.id ASC
            LIMIT " . $batchSize . " OFFSET " . $batchStart . "
        ");
    }

    $allPages   = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalPages = count($allPages);

    // Get actual total for display
    $totalAllPages = $pdo->query("SELECT COUNT(*) FROM pages p INNER JOIN websites w ON p.website_id=w.id WHERE (w.status='active' OR w.status='1')")->fetchColumn();
    $displayTotal  = "Batch " . ceil($batchStart/$batchSize + 1) . ": pages " . ($batchStart+1) . "-" . min($batchEnd, $totalAllPages) . " of {$totalAllPages}";
    echo "<script>
        var t=document.getElementById('totalPages'); if(t) t.textContent='{$totalPages}';
        var p=document.getElementById('progPending'); if(p) p.textContent='{$totalPages}';
        var bt=document.getElementById('batchInfo'); if(bt) bt.textContent='" . addslashes($displayTotal) . "';
    </script>";
    if (ob_get_level()) @ob_flush(); flush();

    logLine("📋 Found {$totalPages} pages to scan...", "#60a5fa");

    $scanned = 0; $changesFound = 0; $errors = 0; $skipped = 0;

    if ($totalPages == 0) {
        logLine('⚠️ No pages found. Run Discover Links first.', '#facc15');
    } else {

        foreach ($allPages as $page) {
            $scanned++;
            $pageUrl = $page['page_url'];

            logLine("🔍 [{$scanned}/{$totalPages}] " . htmlspecialchars($pageUrl), '#94a3b8', $pageUrl);
            updateProgress($scanned, $totalPages);

            // Fetch
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL            => $pageUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            ));
            $html      = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $dlSize    = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
            $curlError = curl_error($ch);
            curl_close($ch);
            // Skip extremely large pages (>500KB) — likely heavy JS pages
            if ($dlSize > 512000) {
                $html = substr($html, 0, 200000); // take only first 200KB
            }

            if ($curlError) { logLine("  ⛔ {$curlError}", '#ef4444'); $errors++; continue; }
            if ($httpCode >= 400) { logLine("  ⛔ HTTP {$httpCode}", '#ef4444'); $errors++; continue; }
            if (!$html) { logLine("  ⛔ Empty response", '#ef4444'); $errors++; continue; }

            // Extract fields
            preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m);
            $title = trim(strip_tags(html_entity_decode(isset($m[1])?$m[1]:'', ENT_QUOTES, 'UTF-8')));

            preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/is', $html, $m);
            $meta = trim(html_entity_decode(isset($m[1])?$m[1]:'', ENT_QUOTES, 'UTF-8'));

            preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m);
            $h1 = trim(strip_tags(isset($m[1])?$m[1]:''));

            // Smart content extraction
            $content = extractContent($html, $pageUrl);
            $content = normalizeForStorage($content);
            $newHash = md5(strtolower(preg_replace('/[^a-z0-9 ]/i', '', $content)));

            // First scan — save baseline
            if (empty($page['content_hash'])) {
                $pdo->prepare("UPDATE pages SET title=?,meta_description=?,h1_tag=?,content=?,content_hash=?,last_scan=NOW() WHERE id=?")
                    ->execute(array($title,$meta,$h1,$content,$newHash,$page['id']));
                logLine("  ✅ Baseline saved (" . strlen($content) . " chars)", '#16c079');
                continue;
            }

            // Skip if matches resolved state
            $resolvedHash = isset($page['resolved_hash']) ? $page['resolved_hash'] : '';
            if ($resolvedHash && $newHash === $resolvedHash) {
                logLine("  ✔ No change since resolve", '#374151');
                $pdo->prepare("UPDATE pages SET last_scan=NOW() WHERE id=?")->execute(array($page['id']));
                continue;
            }

            // Skip if content hasn't changed at all
            if ($newHash === $page['content_hash']) {
                logLine("  ✔ No changes", '#374151');
                $pdo->prepare("UPDATE pages SET last_scan=NOW() WHERE id=?")->execute(array($page['id']));
                continue;
            }

            $changed = false;

            // Title
            $oldTitle = isset($page['title']) ? $page['title'] : '';
            if ($title !== $oldTitle && $oldTitle !== '') {
                $pdo->prepare("INSERT INTO changes (website_id,page_id,change_type,old_content,new_content,detected_at) VALUES(?,?,'TITLE_CHANGED',?,?,NOW())")
                    ->execute(array($page['website_id'],$page['id'],$oldTitle,$title));
                logLine("  🟡 Title: " . htmlspecialchars(substr($oldTitle,0,50)) . " → " . htmlspecialchars(substr($title,0,50)), '#facc15', $pageUrl);
                $changesFound++; $changed = true;
            }

            // Content changed — only if meaningfully different (not just case/punctuation)
            // Recompute old hash same way for fair comparison
            $oldContent  = isset($page['content']) ? $page['content'] : '';
            $oldHashNorm = md5(strtolower(preg_replace('/[^a-z0-9 ]/i', '', $oldContent)));

            if ($oldHashNorm !== $newHash) {
                if (isMeaningfulChange($oldContent, $content)) {
                    $changePct = getChangePct($oldContent, $content);
                    $pdo->prepare("INSERT INTO changes (website_id,page_id,change_type,old_content,new_content,detected_at) VALUES(?,?,'CONTENT_CHANGED',?,?,NOW())")
                        ->execute(array($page['website_id'],$page['id'],$oldContent,$content));
                    $changesFound++; $changed = true;
                    logLine("  🔴 Content changed ({$changePct}%)", '#ef4444', $pageUrl);
                } else {
                    logLine("  ⚪ Case/format only — skipped", '#374151');
                }
            }

            if (!$changed && $newHash !== $page['content_hash']) logLine("  ⚪ Hash changed but normalized content same", '#374151');

            echo "<script>var cc=document.getElementById('changesCount');if(cc)cc.textContent='{$changesFound}';</script>";
            if (ob_get_level()) @ob_flush(); flush();

            $pdo->prepare("UPDATE pages SET title=?,meta_description=?,h1_tag=?,content=?,content_hash=?,last_scan=NOW() WHERE id=?")
                ->execute(array($title,$meta,$h1,$content,$newHash,$page['id']));
        }
    }

    logLine('', '#1e293b');
    logLine('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', '#1e293b');
    logLine("✅ Done — Scanned: {$scanned} | Changes: {$changesFound} | Errors: {$errors}", '#16c079');

} catch (Exception $e) {
    logLine('⛔ FATAL: ' . $e->getMessage(), '#ef4444');
}
?>

    </div>
  </div>
  <div class="card-footer d-flex gap-2">
    <a href="../admin/changes.php" class="btn btn-primary btn-sm">
      <i class="bi bi-activity me-1"></i>View Changes
    </a>
    <?php
    $nextStart = $batchStart + $batchSize;
    if (isset($totalAllPages) && $nextStart < $totalAllPages):
    ?>
    <a href="scan_changes.php?start=<?= $nextStart ?><?= $wid ? "&wid=$wid" : '' ?>"
       class="btn btn-warning btn-sm">
      <i class="bi bi-arrow-right me-1"></i>Next Batch (<?= $nextStart+1 ?>–<?= min($nextStart+$batchSize, $totalAllPages) ?>)
    </a>
    <?php else: ?>
    <span class="badge bg-success p-2"><i class="bi bi-check-circle me-1"></i>All pages scanned!</span>
    <?php endif; ?>
    <a href="../dashboard.php" class="btn btn-secondary btn-sm">
      <i class="bi bi-speedometer2 me-1"></i>Dashboard
    </a>
  </div>
</div>
</div>

<script>
document.getElementById('scanStatus').textContent='Batch Complete';
document.getElementById('scanStatus').className='badge bg-success ms-2';
var pb=document.getElementById('progBar'); if(pb) pb.style.width='100%';
var pp=document.getElementById('progPct'); if(pp) pp.textContent='100%';
var pB=document.getElementById('pendBar'); if(pB) pB.style.width='0%';
var lg=document.getElementById('scanLog'); if(lg) lg.scrollTop=lg.scrollHeight;
</script>

<?php pageFooter(); ?>
