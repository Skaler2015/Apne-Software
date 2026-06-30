<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

function getSetting($pdo, $key, $default='') {
    try {
        $s = $pdo->prepare("SELECT setting_value FROM tracker_settings WHERE setting_key=?");
        $s->execute([$key]);
        $val = $s->fetchColumn();
        return $val !== false ? $val : $default;
    } catch(Exception $e) { return $default; }
}
function saveSetting($pdo, $key, $val) {
    try {
        $pdo->prepare("INSERT INTO tracker_settings (setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=?")
            ->execute([$key,$val,$val]);
    } catch(Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    saveSetting($pdo, 'cron_interval', $_POST['interval'] ?? '2');
    saveSetting($pdo, 'cron_pages',    $_POST['pages']    ?? '150');
    flash('✅ Settings saved! Ab Hostinger mein cron command daalo.');
    header('Location: cron_setup.php'); exit;
}

$interval = getSetting($pdo,'cron_interval','2');
$pages    = getSetting($pdo,'cron_pages','150');

// Check last auto scan
try {
    $lastRun = $pdo->query("SELECT MAX(ran_at) FROM scan_log")->fetchColumn();
} catch(Exception $e) { $lastRun = null; }

// Cron expression
$cronMap = ['1'=>'* * * *','2'=>'*/2 * * *','3'=>'*/3 * * *','6'=>'0 */6 * *','12'=>'0 */12 * *','24'=>'0 0 * * *'];
$expr    = isset($cronMap[$interval]) ? '0 '.$cronMap[$interval] : '0 */2 * * *';
if ($interval == 1) $expr = '* * * * *';

$baseUrl = 'https://apnesoftware.com/tracker/cron/auto_scan.php';

pageHeader('Cron Setup');
?>
<div class="container py-4" style="max-width:800px">
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Auto Scan Setup</li>
  </ol>
</nav>

<div class="d-flex align-items-center gap-3 mb-4">
  <div style="background:rgba(96,165,250,.15);border:1px solid rgba(96,165,250,.3);border-radius:12px;padding:10px 14px;font-size:1.4rem">⏰</div>
  <div>
    <h1 class="page-title">Auto Scan — Hostinger Cron Setup</h1>
    <p class="text-muted mb-0" style="font-size:.85rem">Har <?= $interval ?> ghante mein automatic scan — bina manually chalaye</p>
  </div>
</div>

<?php showFlash(); ?>

<!-- Status -->
<div class="row g-3 mb-4">
  <div class="col-4">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:1.5rem"><?= $lastRun ? '🟢' : '⚪' ?></div>
      <div style="font-size:.72rem;color:var(--dim);margin-top:4px">Cron Status</div>
      <div style="font-weight:700;font-size:.8rem"><?= $lastRun ? 'Active' : 'Not Set' ?></div>
    </div>
  </div>
  <div class="col-4">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:1rem;font-weight:800;color:var(--accent)"><?= $interval ?>h</div>
      <div style="font-size:.72rem;color:var(--dim);margin-top:4px">Interval</div>
      <div style="font-weight:700;font-size:.8rem">Every <?= $interval ?> hour(s)</div>
    </div>
  </div>
  <div class="col-4">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:.8rem;font-weight:800;color:var(--blue)"><?= $lastRun ? date('d M, h:i A', strtotime($lastRun)) : 'Never' ?></div>
      <div style="font-size:.72rem;color:var(--dim);margin-top:4px">Last Auto Scan</div>
    </div>
  </div>
</div>

<!-- Settings -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-gear me-2"></i>Scan Settings</div>
  <div class="card-body p-4">
    <form method="post">
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="form-label">Scan Interval</label>
          <select name="interval" class="form-select" onchange="updateCron(this.value)">
            <?php foreach(['1'=>'Every 1 hour','2'=>'Every 2 hours','3'=>'Every 3 hours','6'=>'Every 6 hours','12'=>'Every 12 hours','24'=>'Once daily'] as $v=>$l): ?>
            <option value="<?=$v?>" <?=$interval==$v?'selected':''?>><?=$l?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Pages per scan</label>
          <select name="pages" class="form-select">
            <?php foreach([50,100,150,200,300] as $p): ?>
            <option value="<?=$p?>" <?=$pages==$p?'selected':''?>><?=$p?> latest pages</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Save Settings</button>
    </form>
  </div>
</div>

<!-- Hostinger Setup Steps -->
<div class="card mb-4">
  <div class="card-header" style="background:rgba(124,92,252,.1)">
    <i class="bi bi-list-ol me-2" style="color:var(--accent)"></i>
    Hostinger Cron Setup — Step by Step
  </div>
  <div class="card-body p-4">

    <div style="display:flex;flex-direction:column;gap:16px">

      <div style="display:flex;gap:12px;align-items:flex-start">
        <div style="background:var(--accent);color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0">1</div>
        <div>
          <div style="font-weight:700;margin-bottom:4px">Hostinger hPanel kholo</div>
          <div style="font-size:.82rem;color:var(--dim)">hpanel.hostinger.com → Hosting → Manage → Advanced tab</div>
        </div>
      </div>

      <div style="display:flex;gap:12px;align-items:flex-start">
        <div style="background:var(--accent);color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0">2</div>
        <div>
          <div style="font-weight:700;margin-bottom:4px">"Cron Jobs" section mein jaao</div>
          <div style="font-size:.82rem;color:var(--dim)">Advanced → Cron Jobs → "Enter Manually" select karo</div>
        </div>
      </div>

      <div style="display:flex;gap:12px;align-items:flex-start">
        <div style="background:var(--accent);color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0">3</div>
        <div>
          <div style="font-weight:700;margin-bottom:6px">Yeh command paste karo:</div>
          <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:12px 14px;font-family:monospace;font-size:.8rem;position:relative">
            <span style="color:var(--yellow)" id="cronExpr"><?= $expr ?></span>
            <span style="color:var(--dim)"> wget -q -O /dev/null "<?= $baseUrl ?>"</span>
            <button onclick="copyCron()" style="position:absolute;right:8px;top:8px;background:var(--accent);border:none;color:#fff;border-radius:4px;padding:2px 8px;font-size:.7rem;cursor:pointer">
              <i class="bi bi-clipboard"></i> Copy
            </button>
          </div>
          <div style="font-size:.75rem;color:var(--dim);margin-top:4px">
            Ya sirf URL method use karo: <code><?= $baseUrl ?></code>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:12px;align-items:flex-start">
        <div style="background:var(--green);color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0">4</div>
        <div>
          <div style="font-weight:700;margin-bottom:4px">Save karo — ho gaya! ✅</div>
          <div style="font-size:.82rem;color:var(--dim)">Ab har <?= $interval ?> ghante mein automatic scan hoga. Aap kuch nahi karo.</div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Test button -->
<div class="card">
  <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div style="font-size:.85rem;color:var(--dim)">
      <i class="bi bi-info-circle me-1"></i>
      Auto scan cron URL ko browser mein bhi test kar sako:
    </div>
    <div class="d-flex gap-2">
      <a href="<?= $baseUrl ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-play me-1"></i>Test Run
      </a>
      <a href="smart_dashboard.php" class="btn btn-sm btn-primary">
        <i class="bi bi-lightning-charge me-1"></i>Smart Dashboard
      </a>
    </div>
  </div>
</div>

</div>
<script>
var cronExprs = {
    '1':'* * * * *',
    '2':'0 */2 * * *',
    '3':'0 */3 * * *',
    '6':'0 */6 * * *',
    '12':'0 */12 * * *',
    '24':'0 0 * * *'
};
function updateCron(val) {
    var expr = cronExprs[val] || '0 */2 * * *';
    document.getElementById('cronExpr').textContent = expr;
}
function copyCron() {
    var expr = document.getElementById('cronExpr').textContent;
    var cmd  = expr + ' wget -q -O /dev/null "<?= $baseUrl ?>"';
    navigator.clipboard.writeText(cmd).then(function(){
        alert('✅ Command copied!');
    });
}
</script>
<?php pageFooter(); ?>
