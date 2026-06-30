<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

// Create settings table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tracker_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        updated_at DATETIME DEFAULT NOW() ON UPDATE NOW()
    )");
} catch(Exception $e) {}

function getSetting($pdo, $key, $default='') {
    try {
        $s = $pdo->prepare("SELECT setting_value FROM tracker_settings WHERE setting_key=?");
        $s->execute([$key]);
        $val = $s->fetchColumn();
        return $val !== false ? $val : $default;
    } catch(Exception $e) { return $default; }
}

function saveSetting($pdo, $key, $value) {
    $pdo->prepare("INSERT INTO tracker_settings (setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=?, updated_at=NOW()")
        ->execute([$key, $value, $value]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';

    if ($section === 'telegram') {
        saveSetting($pdo, 'telegram_bot_token', trim($_POST['bot_token'] ?? ''));
        saveSetting($pdo, 'telegram_chat_id',   trim($_POST['chat_id']   ?? ''));
        saveSetting($pdo, 'telegram_enabled',   isset($_POST['telegram_enabled']) ? '1' : '0');
        flash('✅ Telegram settings saved!');
    }
    if ($section === 'scan') {
        saveSetting($pdo, 'auto_scan_enabled',  isset($_POST['auto_scan']) ? '1' : '0');
        saveSetting($pdo, 'scan_interval_hours', (int)($_POST['scan_interval'] ?? 6));
        saveSetting($pdo, 'scan_pages_limit',    (int)($_POST['pages_limit'] ?? 150));
        flash('✅ Scan settings saved!');
    }
    if ($section === 'claude') {
        saveSetting($pdo, 'claude_api_key', trim($_POST['claude_key'] ?? ''));
        flash('✅ Claude API key saved!');
    }

    header('Location: settings.php'); exit;
}

// Load settings
$tgToken    = getSetting($pdo, 'telegram_bot_token');
$tgChatId   = getSetting($pdo, 'telegram_chat_id');
$tgEnabled  = getSetting($pdo, 'telegram_enabled', '0');
$autoScan   = getSetting($pdo, 'auto_scan_enabled', '0');
$scanHours  = getSetting($pdo, 'scan_interval_hours', '6');
$pagesLimit = getSetting($pdo, 'scan_pages_limit', '150');
$claudeKey  = getSetting($pdo, 'claude_api_key');

pageHeader('Settings');
?>
<div class="container py-4" style="max-width:700px">
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Settings</li>
  </ol>
</nav>

<h1 class="page-title mb-4"><i class="bi bi-gear me-2"></i>Settings</h1>

<?php showFlash(); ?>

<!-- Telegram -->
<div class="card mb-4">
  <div class="card-header">
    <i class="bi bi-telegram me-2" style="color:#29b6f6"></i>Telegram Notifications
    <span class="badge <?= $tgEnabled==='1'?'bg-success':'bg-secondary' ?> ms-2"><?= $tgEnabled==='1'?'Active':'Inactive' ?></span>
  </div>
  <div class="card-body p-4">
    <form method="post">
      <input type="hidden" name="section" value="telegram">
      <div class="mb-3">
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" name="telegram_enabled" id="tgEnabled" role="switch"
                 <?= $tgEnabled==='1'?'checked':'' ?> style="width:3em;height:1.5em">
          <label class="form-check-label ms-2 fw-bold" for="tgEnabled">Enable Telegram Alerts</label>
        </div>
        <label class="form-label">Bot Token</label>
        <input type="text" name="bot_token" class="form-control" placeholder="1234567890:ABCdef..."
               value="<?= htmlspecialchars($tgToken) ?>">
        <div class="form-text" style="color:var(--dim)">
          <a href="https://t.me/BotFather" target="_blank" style="color:var(--blue)">@BotFather</a> se /newbot karke token lo
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Chat ID</label>
        <input type="text" name="chat_id" class="form-control" placeholder="-1001234567890 ya apna user ID"
               value="<?= htmlspecialchars($tgChatId) ?>">
        <div class="form-text" style="color:var(--dim)">
          <a href="https://t.me/userinfobot" target="_blank" style="color:var(--blue)">@userinfobot</a> se apna Chat ID pata karo
        </div>
      </div>
      <?php if ($tgToken && $tgChatId): ?>
      <button type="button" onclick="testTelegram()" class="btn btn-sm btn-outline-secondary me-2">
        <i class="bi bi-send me-1"></i>Test Message
      </button>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary btn-sm">Save</button>
    </form>
  </div>
</div>

<!-- Auto Scan -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-arrow-repeat me-2" style="color:var(--accent)"></i>Auto Scan</div>
  <div class="card-body p-4">
    <form method="post">
      <input type="hidden" name="section" value="scan">
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" name="auto_scan" id="autoScan" role="switch"
               <?= $autoScan==='1'?'checked':'' ?> style="width:3em;height:1.5em">
        <label class="form-check-label ms-2 fw-bold" for="autoScan">Enable Auto Scan via Cron</label>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="form-label">Scan Interval</label>
          <select name="scan_interval" class="form-select">
            <?php foreach([1,2,3,6,12,24] as $h): ?>
            <option value="<?=$h?>" <?= $scanHours==$h?'selected':'' ?>>Every <?=$h?> hour<?=$h>1?'s':''?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label">Pages per scan</label>
          <select name="pages_limit" class="form-select">
            <?php foreach([50,100,150,200,300] as $p): ?>
            <option value="<?=$p?>" <?= $pagesLimit==$p?'selected':'' ?>><?=$p?> pages</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="p-3 mb-3" style="background:var(--bg3);border-radius:8px;font-size:.8rem;color:var(--dim)">
        <i class="bi bi-terminal me-1"></i>Hostinger Cron Command:
        <code style="display:block;margin-top:6px;color:var(--accent)">
          <?php
          $h = (int)$scanHours;
          if($h===1) echo '0 * * * *';
          elseif($h<=3) echo '0 */3 * * *';
          elseif($h<=6) echo '0 */6 * * *';
          elseif($h<=12) echo '0 */12 * * *';
          else echo '0 0 * * *';
          ?> wget -q -O /dev/null "https://apnesoftware.com/tracker/cron/auto_scan.php"
        </code>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Save</button>
    </form>
  </div>
</div>

<!-- Claude API Key -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-stars me-2" style="color:var(--accent)"></i>Claude AI Key</div>
  <div class="card-body p-4">
    <form method="post">
      <input type="hidden" name="section" value="claude">
      <div class="mb-3">
        <label class="form-label">API Key</label>
        <input type="password" name="claude_key" class="form-control"
               placeholder="sk-ant-api03-..."
               value="<?= htmlspecialchars($claudeKey) ?>">
        <div class="form-text" style="color:var(--dim)">
          <a href="https://platform.claude.com/settings/keys" target="_blank" style="color:var(--blue)">platform.claude.com</a> se API key lo
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Save</button>
    </form>
  </div>
</div>

</div>

<script>
function testTelegram() {
    fetch('send_telegram.php?test=1')
    .then(r=>r.json())
    .then(d=>{
        alert(d.ok ? '✅ Message sent successfully!' : '❌ Failed: ' + (d.error||'Unknown error'));
    }).catch(e=>alert('Error: '+e));
}
</script>

<?php pageFooter(); ?>
