<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

// Get websites and page IDs for JS
$websites = $pdo->query("SELECT id, website_name FROM websites WHERE status='active' OR status='1' ORDER BY id")->fetchAll();
$wids = array_column($websites, 'id');

// STRICTLY latest 150 pages per website
// Sort by lastmod (sitemap date) DESC — newest posts first
// Pages with no lastmod use created_at as fallback
$pageIds = [];
foreach ($wids as $wid) {
    // Get latest 150 pages sorted by sitemap lastmod date (newest first)
    // Important pages (result/admit etc) get priority boost
    // Get latest 150 pages — newest ID first, important pages prioritized
    $latest = $pdo->prepare("
        SELECT id, page_url,
               CASE
                 WHEN page_url LIKE '%result%' OR page_url LIKE '%admit%'
                   OR page_url LIKE '%answer-key%' OR page_url LIKE '%cutoff%'
                   OR page_url LIKE '%merit%' OR page_url LIKE '%notification%'
                 THEN 1 ELSE 0
               END as is_important
        FROM pages
        WHERE website_id=?
        AND page_url NOT LIKE '%sitemap%'
        ORDER BY is_important DESC, id DESC
        LIMIT 150
    ");
    $latest->execute([$wid]);
    $rows = $latest->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $pageIds[] = (int)$row['id'];
    }
}
$pageIds = array_unique($pageIds);

$totalPages    = count($pageIds);
$websitesJson  = json_encode($wids);
$pageIdsJson   = json_encode($pageIds);

pageHeader('Full Scan');
?>
<div class="container py-4" style="max-width:940px">
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Full Scan</li>
  </ol>
</nav>

<div class="d-flex align-items-center gap-3 mb-3">
  <div style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:10px 14px;font-size:1.5rem">🚀</div>
  <div>
    <h1 class="page-title mb-0">Full Scan</h1>
    <p class="text-muted mb-0" style="font-size:.82rem">Discover new pages + Scan <?= $totalPages ?> pages with Smart Filter</p>
  </div>
</div>

<!-- Counters -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center">
      <div id="cNew" style="font-size:1.6rem;font-weight:800;color:var(--accent)">0</div>
      <div style="font-size:.7rem;color:var(--dim)">New Pages</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center">
      <div id="cScanned" style="font-size:1.6rem;font-weight:800;color:var(--blue)">0</div>
      <div style="font-size:.7rem;color:var(--dim)">Scanned</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center">
      <div id="cChanges" style="font-size:1.6rem;font-weight:800;color:var(--red)">0</div>
      <div style="font-size:.7rem;color:var(--dim)">Changes</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:12px;text-align:center">
      <div id="cErrors" style="font-size:1.6rem;font-weight:800;color:#6b7280">0</div>
      <div style="font-size:.7rem;color:var(--dim)">Errors</div>
    </div>
  </div>
</div>

<!-- Progress -->
<div class="card mb-3">
  <div class="card-body p-3">
    <div class="d-flex justify-content-between mb-1">
      <span style="font-size:.8rem;color:var(--dim)" id="progLabel">Ready to scan</span>
      <span style="font-weight:700;color:var(--accent)" id="progPct">0%</span>
    </div>
    <div style="background:var(--bg3);border-radius:20px;height:12px;overflow:hidden">
      <div id="progBar" style="height:100%;width:0%;background:linear-gradient(90deg,var(--accent),#a78bfa);border-radius:20px;transition:width .2s"></div>
    </div>
    <div id="curUrl" style="font-size:.72rem;color:var(--dim);margin-top:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></div>
  </div>
</div>

<!-- Buttons -->
<div class="d-flex gap-2 mb-3">
  <button id="startBtn" onclick="startScan()" class="btn btn-primary">
    <i class="bi bi-play-fill me-1"></i>Start Full Scan
  </button>
  <button id="stopBtn" onclick="stopScan()" class="btn btn-secondary" disabled>
    <i class="bi bi-stop-fill me-1"></i>Stop
  </button>
  <a href="../admin/smart_dashboard.php" class="btn btn-outline-secondary btn-sm ms-auto">
    <i class="bi bi-lightning-charge me-1"></i>Smart Dashboard
  </a>
</div>

<!-- Log -->
<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-terminal me-1"></i>Scan Log</span>
    <span class="badge bg-secondary" id="scanBadge">Idle</span>
  </div>
  <div id="scanLog" style="font-family:monospace;font-size:.75rem;padding:12px;height:380px;overflow-y:auto;background:var(--bg)"></div>
</div>

</div>

<script>
var websites = <?= $websitesJson ?>;
var pageIds  = <?= $pageIdsJson ?>;
var total    = <?= $totalPages ?>;
var stopped  = false;
var newCount = 0, scanned = 0, changes = 0, errors = 0;
var AJAX_URL = '../cron/scan_ajax.php';

var catColors = {
    'Result':'#16c079','Admit Card':'#60a5fa','Answer Key':'#a78bfa',
    'Cut Off':'#f97316','Application Open':'#facc15','Vacancy Update':'#f97316',
    'Notification':'#ef4444','Update':'#6b7280'
};

function log(msg, color) {
    var d = document.getElementById('scanLog');
    d.innerHTML += '<div style="color:'+(color||'#e6eaf2')+';padding:1px 0;border-bottom:1px solid rgba(255,255,255,.03)">' + msg + '</div>';
    d.scrollTop = d.scrollHeight;
}

function upd() {
    document.getElementById('cNew').textContent     = newCount;
    document.getElementById('cScanned').textContent = scanned;
    document.getElementById('cChanges').textContent = changes;
    document.getElementById('cErrors').textContent  = errors;
}

function setProgress(done, tot, url) {
    var pct = tot > 0 ? Math.round(done/tot*100) : 0;
    document.getElementById('progBar').style.width  = pct + '%';
    document.getElementById('progPct').textContent  = pct + '%';
    document.getElementById('progLabel').textContent = 'Scanning ' + done + '/' + tot;
    if (url) document.getElementById('curUrl').textContent = url;
}

async function startScan() {
    document.getElementById('startBtn').disabled = true;
    document.getElementById('stopBtn').disabled  = false;
    document.getElementById('scanBadge').textContent = 'Running...';
    document.getElementById('scanBadge').className   = 'badge bg-warning';
    stopped = false;
    newCount = scanned = changes = errors = 0;
    document.getElementById('scanLog').innerHTML = '';

    // Phase 1
    log('━━━ PHASE 1: Discovering new pages ━━━', '#60a5fa');
    for (var i = 0; i < websites.length; i++) {
        if (stopped) break;
        log('🌐 Website #' + websites[i] + '...', '#94a3b8');
        try {
            var r = await fetch(AJAX_URL + '?action=discover&wid=' + websites[i]);
            var txt = await r.text();
            var d;
            try { d = JSON.parse(txt); }
            catch(e) { log('  ⛔ PHP error (see Hostinger error log)', '#ef4444'); continue; }
            newCount += (d.added || 0);
            log('  ✅ ' + d.msg, '#16c079');
            upd();
        } catch(e) { log('  ⛔ Network error: ' + e, '#ef4444'); }
    }

    if (stopped) { done(); return; }

    // Phase 2
    log('', '#1e293b');
    log('━━━ PHASE 2: Scanning ' + total + ' pages ━━━', '#60a5fa');

    for (var j = 0; j < pageIds.length; j++) {
        if (stopped) break;
        setProgress(j+1, total);

        try {
            var r2 = await fetch(AJAX_URL + '?action=scan_page&pid=' + pageIds[j]);
            var txt2 = await r2.text();
            var d2;
            try { d2 = JSON.parse(txt2); }
            catch(e2) { errors++; upd(); continue; }

            scanned++;
            if (d2.error) {
                errors++;
            } else if (d2.changed) {
                changes++;
                var catColor = catColors[d2.category] || '#ef4444';
                var stars = d2.priority >= 8 ? ' ⭐' : '';
                log('  🔴 [<span style="color:'+catColor+'">'+d2.category+'</span>]'+stars+' — '+(d2.title||'').substring(0,60), '#e6eaf2');
            }
            upd();
        } catch(e) { errors++; upd(); }
    }

    done();
}

function done() {
    document.getElementById('startBtn').disabled = false;
    document.getElementById('stopBtn').disabled  = true;
    document.getElementById('scanBadge').textContent = stopped ? 'Stopped' : '✅ Complete';
    document.getElementById('scanBadge').className   = stopped ? 'badge bg-secondary' : 'badge bg-success';
    if (!stopped) {
        document.getElementById('progBar').style.width  = '100%';
        document.getElementById('progPct').textContent  = '100%';
        document.getElementById('progLabel').textContent = '✅ Done!';
    }
    document.getElementById('curUrl').textContent = '';
    log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', '#1e293b');
    log('✅ Scanned: '+scanned+' | Changes: '+changes+' | New: '+newCount+' | Errors: '+errors, '#16c079');
}

function stopScan() {
    stopped = true;
    document.getElementById('stopBtn').disabled = true;
}
</script>

<?php pageFooter(); ?>
