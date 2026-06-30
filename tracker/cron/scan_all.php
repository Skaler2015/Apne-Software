<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

$batchSize = 100;
$total = $pdo->query("SELECT COUNT(*) FROM pages p INNER JOIN websites w ON p.website_id=w.id WHERE (w.status='active' OR w.status='1')")->fetchColumn();
$batches = ceil($total / $batchSize);

pageHeader('Scan All Pages');
?>
<div class="container py-4" style="max-width:700px">
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Scan All</li>
  </ol>
</nav>

<div class="d-flex align-items-center gap-3 mb-4">
  <div style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:10px 14px;font-size:1.4rem">🔁</div>
  <div>
    <h1 class="page-title">Scan All <?= $total ?> Pages</h1>
    <p class="text-muted mb-0" style="font-size:.85rem"><?= $batches ?> batches × <?= $batchSize ?> pages each</p>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body p-4">
    <div id="progress-area">
      <div class="d-flex justify-content-between mb-2">
        <span style="font-size:.85rem;color:var(--dim)">Overall Progress</span>
        <span id="overallPct" style="font-weight:700;color:var(--accent)">0%</span>
      </div>
      <div style="background:var(--bg3);border-radius:20px;height:16px;overflow:hidden;margin-bottom:16px">
        <div id="overallBar" style="height:100%;width:0%;background:linear-gradient(90deg,var(--accent),#a78bfa);border-radius:20px;transition:width .5s"></div>
      </div>
      <div class="row g-2 text-center mb-3">
        <div class="col-3">
          <div style="background:var(--bg3);border-radius:10px;padding:10px">
            <div id="statTotal" style="font-size:1.2rem;font-weight:800;color:var(--txt)"><?= $total ?></div>
            <div style="font-size:.68rem;color:var(--dim)">Total Pages</div>
          </div>
        </div>
        <div class="col-3">
          <div style="background:var(--bg3);border-radius:10px;padding:10px">
            <div id="statDone" style="font-size:1.2rem;font-weight:800;color:var(--accent)">0</div>
            <div style="font-size:.68rem;color:var(--dim)">Scanned</div>
          </div>
        </div>
        <div class="col-3">
          <div style="background:var(--bg3);border-radius:10px;padding:10px">
            <div id="statChanges" style="font-size:1.2rem;font-weight:800;color:var(--red)">0</div>
            <div style="font-size:.68rem;color:var(--dim)">Changes</div>
          </div>
        </div>
        <div class="col-3">
          <div style="background:var(--bg3);border-radius:10px;padding:10px">
            <div id="statBatch" style="font-size:1.2rem;font-weight:800;color:var(--yellow)">0/<?= $batches ?></div>
            <div style="font-size:.68rem;color:var(--dim)">Batch</div>
          </div>
        </div>
      </div>
      <div id="statusMsg" style="font-size:.85rem;color:var(--dim);margin-bottom:12px">Ready to scan</div>
    </div>

    <div class="d-flex gap-2">
      <button id="startBtn" onclick="startScan()" class="btn btn-primary">
        <i class="bi bi-play-fill me-1"></i>Start Full Scan
      </button>
      <button id="stopBtn" onclick="stopScan()" class="btn btn-secondary" disabled>
        <i class="bi bi-stop-fill me-1"></i>Stop
      </button>
      <a href="../admin/changes.php" class="btn btn-outline-secondary">
        <i class="bi bi-activity me-1"></i>View Changes
      </a>
    </div>

    <!-- Batch log -->
    <div id="batchLog" style="margin-top:16px;font-size:.78rem;font-family:monospace;max-height:200px;overflow-y:auto;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px;display:none"></div>
  </div>
</div>

<!-- Batch links fallback -->
<div class="card">
  <div class="card-header"><i class="bi bi-list-ol me-2"></i>Manual Batch Links</div>
  <div class="card-body p-3">
    <div style="font-size:.8rem;color:var(--dim);margin-bottom:8px">Agar auto scan slow lage to manually batch by batch chalao:</div>
    <div style="display:flex;flex-wrap:wrap;gap:6px">
      <?php for($i=0; $i<$batches; $i++): ?>
      <a href="scan_changes.php?start=<?= $i*$batchSize ?>" target="_blank"
         style="padding:4px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:6px;font-size:.75rem;text-decoration:none;color:var(--txt)">
        <?= ($i*$batchSize+1) ?>–<?= min(($i+1)*$batchSize, $total) ?>
      </a>
      <?php endfor; ?>
    </div>
  </div>
</div>

</div>

<script>
var totalPages = <?= $total ?>;
var batchSize  = <?= $batchSize ?>;
var batches    = <?= $batches ?>;
var stopped    = false;
var totalChanges = 0;
var totalScanned = 0;

function startScan() {
    document.getElementById('startBtn').disabled = true;
    document.getElementById('stopBtn').disabled  = false;
    document.getElementById('batchLog').style.display = 'block';
    stopped = false;
    runBatch(0);
}

function stopScan() {
    stopped = true;
    document.getElementById('stopBtn').disabled = true;
    document.getElementById('startBtn').disabled = false;
    document.getElementById('statusMsg').textContent = 'Scan stopped by user';
}

function runBatch(batchNum) {
    if (stopped || batchNum >= batches) {
        if (!stopped) {
            document.getElementById('statusMsg').textContent = '✅ All pages scanned!';
            document.getElementById('startBtn').disabled = false;
            document.getElementById('stopBtn').disabled  = true;
        }
        return;
    }
    var start = batchNum * batchSize;
    document.getElementById('statusMsg').textContent = 'Scanning batch ' + (batchNum+1) + ' of ' + batches + '...';
    document.getElementById('statBatch').textContent = (batchNum+1) + '/' + batches;

    // Use fetch with iframe approach for streaming
    var url = 'scan_changes.php?start=' + start + '&ajax=1';

    fetch(url)
    .then(function(r) { return r.text(); })
    .then(function(text) {
        // Parse changes count from response
        var m = text.match(/Changes:\s*(\d+)/);
        var c = m ? parseInt(m[1]) : 0;
        var ms = text.match(/Scanned:\s*(\d+)/);
        var s = ms ? parseInt(ms[1]) : batchSize;

        totalChanges += c;
        totalScanned += s;

        // Update UI
        var pct = Math.round(totalScanned / totalPages * 100);
        document.getElementById('overallBar').style.width = pct + '%';
        document.getElementById('overallPct').textContent = pct + '%';
        document.getElementById('statDone').textContent    = totalScanned;
        document.getElementById('statChanges').textContent = totalChanges;

        // Log
        var log = document.getElementById('batchLog');
        log.innerHTML += '<div style="color:#60a5fa">Batch ' + (batchNum+1) + ': scanned ' + s + ' pages, ' + c + ' changes</div>';
        log.scrollTop = log.scrollHeight;

        // Next batch after small delay
        setTimeout(function() { runBatch(batchNum + 1); }, 300);
    })
    .catch(function(err) {
        var log = document.getElementById('batchLog');
        log.innerHTML += '<div style="color:#ef4444">Batch ' + (batchNum+1) + ' error: ' + err + '</div>';
        // Still try next batch
        setTimeout(function() { runBatch(batchNum + 1); }, 1000);
    });
}
</script>

<?php pageFooter(); ?>
