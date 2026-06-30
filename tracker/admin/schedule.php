<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

// Setup schedule table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS scan_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        is_active TINYINT(1) DEFAULT 0,
        interval_hours INT DEFAULT 6,
        last_run DATETIME NULL,
        next_run DATETIME NULL,
        created_at DATETIME DEFAULT NOW()
    )");
    $pdo->exec("INSERT IGNORE INTO scan_schedule (id, is_active, interval_hours) VALUES (1, 0, 6)");
} catch(Exception $e) {}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $active   = isset($_POST['is_active']) ? 1 : 0;
    $interval = (int)($_POST['interval_hours'] ?? 6);
    if ($interval < 1) $interval = 1;
    $nextRun  = $active ? date('Y-m-d H:i:s', strtotime("+{$interval} hours")) : null;
    $pdo->prepare("UPDATE scan_schedule SET is_active=?, interval_hours=?, next_run=? WHERE id=1")
        ->execute([$active, $interval, $nextRun]);
    flash($active ? "✅ Auto scan enabled — every {$interval} hour(s)" : "⏸ Auto scan disabled", $active ? 'success' : 'warning');
    header('Location: schedule.php'); exit;
}

$sched = $pdo->query("SELECT * FROM scan_schedule WHERE id=1")->fetch();

// Get scan log
try {
    $logs = $pdo->query("SELECT * FROM scan_log ORDER BY ran_at DESC LIMIT 10")->fetchAll();
} catch(Exception $e) { $logs = []; }

pageHeader('Auto Scan Schedule');
?>
<div class="container py-4" style="max-width:700px">
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Auto Scan Schedule</li>
  </ol>
</nav>

<div class="d-flex align-items-center gap-3 mb-4">
  <div style="background:rgba(96,165,250,.15);border:1px solid rgba(96,165,250,.3);border-radius:12px;padding:10px 14px;font-size:1.4rem">⏰</div>
  <div>
    <h1 class="page-title">Auto Scan Schedule</h1>
    <p class="text-muted mb-0" style="font-size:.85rem">Automatically scan websites at regular intervals via cron job</p>
  </div>
</div>

<?php showFlash(); ?>

<div class="card mb-4">
  <div class="card-header"><i class="bi bi-gear me-2"></i>Schedule Settings</div>
  <div class="card-body p-4">
    <form method="post">
      <div class="mb-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="is_active" id="isActive" role="switch"
                 <?= $sched['is_active'] ? 'checked' : '' ?> style="width:3em;height:1.5em">
          <label class="form-check-label ms-2" for="isActive" style="font-weight:700;font-size:1rem">
            Enable Auto Scan
          </label>
        </div>
        <div style="font-size:.82rem;color:var(--dim);margin-top:6px">
          When enabled, scan runs automatically at the selected interval
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label">Scan Interval</label>
        <div class="row g-2">
          <?php foreach([1,3,6,12,24] as $h): ?>
          <div class="col-auto">
            <input type="radio" class="btn-check" name="interval_hours" id="h<?=$h?>" value="<?=$h?>"
                   <?= $sched['interval_hours']==$h ? 'checked' : '' ?>>
            <label class="btn btn-outline-secondary btn-sm" for="h<?=$h?>">
              Every <?=$h?> hr<?=$h>1?'s':''?>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-circle me-1"></i>Save Schedule
      </button>
    </form>
  </div>
</div>

<!-- Cron command -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-terminal me-2"></i>Hostinger Cron Job Setup</div>
  <div class="card-body p-3">
    <p style="font-size:.85rem;color:var(--dim)">Hostinger → Hosting → Advanced → Cron Jobs mein yeh command add karein:</p>
    <?php
    $interval = $sched['interval_hours'];
    $i = (int)$interval;
    if ($i === 1)       $cronExpr = '0 * * * *';
    elseif ($i === 3)   $cronExpr = '0 */3 * * *';
    elseif ($i === 6)   $cronExpr = '0 */6 * * *';
    elseif ($i === 12)  $cronExpr = '0 */12 * * *';
    elseif ($i === 24)  $cronExpr = '0 0 * * *';
    else                $cronExpr = '0 */6 * * *';
    $domain = 'https://apnesoftware.com/tracker/cron/auto_scan.php';
    ?>
    <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:12px 14px;font-family:monospace;font-size:.82rem;margin-bottom:10px">
      <span style="color:var(--yellow)"><?= $cronExpr ?></span>
      <span style="color:var(--dim)"> wget -q -O /dev/null "<?= $domain ?>"</span>
    </div>
    <div style="font-size:.78rem;color:var(--dim)">
      <i class="bi bi-info-circle me-1"></i>
      Ya simply Hostinger ke cron mein URL set karein: <code><?= $domain ?></code>
    </div>
  </div>
</div>

<!-- Status -->
<div class="card">
  <div class="card-header"><i class="bi bi-clock-history me-2"></i>Schedule Status</div>
  <div class="card-body p-3">
    <div class="row g-3 text-center">
      <div class="col-4">
        <div style="background:var(--bg3);border-radius:10px;padding:14px">
          <div style="font-size:1.4rem"><?= $sched['is_active'] ? '🟢' : '⏸️' ?></div>
          <div style="font-size:.72rem;color:var(--dim);margin-top:4px">Status</div>
          <div style="font-weight:700;font-size:.85rem"><?= $sched['is_active'] ? 'Active' : 'Paused' ?></div>
        </div>
      </div>
      <div class="col-4">
        <div style="background:var(--bg3);border-radius:10px;padding:14px">
          <div style="font-size:1.4rem">⏱️</div>
          <div style="font-size:.72rem;color:var(--dim);margin-top:4px">Last Run</div>
          <div style="font-weight:700;font-size:.82rem"><?= $sched['last_run'] ? date('d M, h:i A', strtotime($sched['last_run'])) : 'Never' ?></div>
        </div>
      </div>
      <div class="col-4">
        <div style="background:var(--bg3);border-radius:10px;padding:14px">
          <div style="font-size:1.4rem">🔜</div>
          <div style="font-size:.72rem;color:var(--dim);margin-top:4px">Next Run</div>
          <div style="font-weight:700;font-size:.82rem"><?= $sched['next_run'] ? date('d M, h:i A', strtotime($sched['next_run'])) : '—' ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

</div>
<?php pageFooter(); ?>
