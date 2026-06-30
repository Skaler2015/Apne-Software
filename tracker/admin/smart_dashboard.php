<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

// Create settings table safely
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tracker_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT)");
} catch(Exception $e) {}

function getSetting($pdo, $key, $default='') {
    try {
        $s = $pdo->prepare("SELECT setting_value FROM tracker_settings WHERE setting_key=?");
        $s->execute([$key]);
        $val = $s->fetchColumn();
        return $val !== false ? $val : $default;
    } catch(Exception $e) { return $default; }
}

$today = date('Y-m-d');

// Safe stats queries
try { $todayTotal = $pdo->query("SELECT COUNT(*) FROM changes WHERE DATE(detected_at)='$today'")->fetchColumn(); }
catch(Exception $e) { $todayTotal = 0; }

try { $todayPending = $pdo->query("SELECT COUNT(*) FROM changes WHERE DATE(detected_at)='$today' AND (resolved IS NULL OR resolved=0)")->fetchColumn(); }
catch(Exception $e) { $todayPending = $todayTotal; }

try { $newPages = $pdo->query("SELECT COUNT(*) FROM pages WHERE DATE(created_at)='$today'")->fetchColumn(); }
catch(Exception $e) { $newPages = 0; }

// Important changes — safe
try {
    $importantChanges = $pdo->query("
        SELECT c.*, p.page_url, w.website_name
        FROM changes c
        LEFT JOIN pages p ON c.page_id=p.id
        LEFT JOIN websites w ON c.website_id=w.id
        WHERE DATE(c.detected_at)='$today'
        AND (c.resolved IS NULL OR c.resolved=0)
        AND (p.page_url LIKE '%result%' OR p.page_url LIKE '%admit%'
          OR p.page_url LIKE '%answer-key%' OR p.page_url LIKE '%merit%'
          OR p.page_url LIKE '%cutoff%' OR p.page_url LIKE '%notification%')
        ORDER BY c.detected_at DESC LIMIT 20
    ")->fetchAll();
} catch(Exception $e) { $importantChanges = []; }

// Other changes
try {
    $allToday = $pdo->query("
        SELECT c.*, p.page_url, w.website_name
        FROM changes c
        LEFT JOIN pages p ON c.page_id=p.id
        LEFT JOIN websites w ON c.website_id=w.id
        WHERE DATE(c.detected_at)='$today'
        AND (c.resolved IS NULL OR c.resolved=0)
        AND p.page_url NOT LIKE '%result%'
        AND p.page_url NOT LIKE '%admit%'
        AND p.page_url NOT LIKE '%answer-key%'
        ORDER BY c.detected_at DESC LIMIT 30
    ")->fetchAll();
} catch(Exception $e) { $allToday = []; }

// New pages
try {
    $newPagesData = $pdo->query("
        SELECT p.*, w.website_name FROM pages p
        LEFT JOIN websites w ON p.website_id=w.id
        WHERE DATE(p.created_at)='$today' ORDER BY p.id DESC LIMIT 20
    ")->fetchAll();
} catch(Exception $e) { $newPagesData = []; }

$tgToken  = getSetting($pdo,'telegram_bot_token');
$tgChatId = getSetting($pdo,'telegram_chat_id');
$tgOk     = !empty($tgToken) && !empty($tgChatId);

$catColors = ['Result'=>'#16c079','Admit Card'=>'#60a5fa','Answer Key'=>'#a78bfa',
              'Cut Off'=>'#f97316','Recruitment'=>'#facc15','Notification'=>'#ef4444'];

pageHeader('Smart Dashboard');
?>
<style>
.imp-card{background:var(--bg2);border:1px solid rgba(239,68,68,.25);border-radius:10px;padding:11px 14px;margin-bottom:7px;transition:.2s}
.imp-card:hover{border-color:rgba(239,68,68,.5)}
.other-card{background:var(--bg2);border:1px solid var(--border);border-radius:7px;padding:7px 12px;margin-bottom:5px}
.other-card:hover{border-color:rgba(124,92,252,.3)}
.new-card{background:rgba(22,192,121,.07);border:1px solid rgba(22,192,121,.2);border-radius:7px;padding:7px 12px;margin-bottom:5px}
.stat-pill{background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center}
</style>

<div class="container-fluid py-4 px-4">
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h1 class="page-title mb-0">⚡ Smart Dashboard</h1>
    <p class="text-muted mb-0" style="font-size:.82rem"><?=date('d F Y, l')?> IST</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="../cron/full_scan.php" class="btn btn-primary btn-sm"><i class="bi bi-play-fill me-1"></i>Full Scan</a>
    <a href="analytics.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bar-chart me-1"></i>Analytics</a>
    <?php if($tgOk): ?>
    <button onclick="sendAllImportant()" class="btn btn-sm" style="background:#29b6f6;color:#fff;border:none">
      <i class="bi bi-telegram me-1"></i>Send All
    </button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-pill"><div style="font-size:2rem;font-weight:800;color:var(--red)"><?=$todayPending?></div><div style="font-size:.72rem;color:var(--dim)">Pending</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-pill"><div style="font-size:2rem;font-weight:800;color:#f97316"><?=count($importantChanges)?></div><div style="font-size:.72rem;color:var(--dim)">⚡ Important</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-pill"><div style="font-size:2rem;font-weight:800;color:var(--green)"><?=$newPages?></div><div style="font-size:.72rem;color:var(--dim)">New Pages</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-pill"><div style="font-size:2rem;font-weight:800;color:var(--accent)"><?=$todayTotal?></div><div style="font-size:.72rem;color:var(--dim)">Total Today</div></div></div>
</div>

<div class="row g-4">
  <!-- Important -->
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <span>⚡ Important <span class="badge bg-danger ms-1"><?=count($importantChanges)?></span></span>
        <span style="font-size:.72rem;color:var(--dim)">Result · Admit · Answer Key</span>
      </div>
      <div class="card-body p-3" style="max-height:480px;overflow-y:auto">
        <?php if (!$importantChanges): ?>
        <div style="text-align:center;padding:30px;color:var(--dim)">
          <i class="bi bi-check-circle" style="font-size:2rem;color:var(--green)"></i>
          <p class="mt-2 mb-0 small">Aaj koi important change nahi</p>
        </div>
        <?php else: ?>
        <?php foreach($importantChanges as $ch):
          $url  = $ch['page_url'] ?? '';
          $path = parse_url($url, PHP_URL_PATH) ?: '/';
          $cat  = $ch['category'] ?? '';
          if (!$cat) {
            if (stripos($url,'result')!==false || stripos($url,'merit')!==false) $cat='Result';
            elseif (stripos($url,'admit')!==false) $cat='Admit Card';
            elseif (stripos($url,'answer-key')!==false) $cat='Answer Key';
            elseif (stripos($url,'notification')!==false) $cat='Notification';
            else $cat='Update';
          }
          $catColor = $catColors[$cat] ?? 'var(--dim)';
          $pri = (int)($ch['priority_score'] ?? 5);
        ?>
        <div class="imp-card" id="imp-<?=$ch['id']?>">
          <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <span style="background:<?=$catColor?>22;color:<?=$catColor?>;border-radius:4px;padding:1px 7px;font-size:.68rem;font-weight:700"><?=$cat?></span>
            <span style="font-size:.7rem;color:var(--dim)"><?=$ch['website_name']??''?></span>
            <?php if($pri>=8): ?><span style="color:#facc15;font-size:.72rem">⭐</span><?php endif; ?>
            <span style="font-size:.68rem;color:var(--dim);margin-left:auto"><?=date('h:i A',strtotime($ch['detected_at']))?> IST</span>
          </div>
          <a href="<?=htmlspecialchars($url)?>" target="_blank"
             style="color:var(--blue);font-size:.79rem;text-decoration:underline;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:5px">
            <?=htmlspecialchars($path)?> ↗
          </a>
          <?php if($ch['note']): ?>
          <div style="font-size:.75rem;color:#fef08a;background:rgba(250,204,21,.08);border-radius:4px;padding:3px 8px;margin-bottom:5px">
            💡 <?=htmlspecialchars($ch['note'])?>
          </div>
          <?php endif; ?>
          <div class="d-flex gap-1 flex-wrap">
            <button onclick="quickAI(<?=$ch['id']?>,this)" class="btn btn-sm" style="font-size:.68rem;padding:2px 8px;background:rgba(124,92,252,.15);color:var(--accent);border:1px solid rgba(124,92,252,.3)"><i class="bi bi-stars me-1"></i>AI</button>
            <?php if($tgOk): ?><button onclick="quickTg(<?=$ch['id']?>,this)" class="btn btn-sm" style="font-size:.68rem;padding:2px 8px;background:rgba(41,182,246,.1);color:#29b6f6;border:1px solid rgba(41,182,246,.3)"><i class="bi bi-telegram me-1"></i>Send</button><?php endif; ?>
            <button onclick="quickResolve(<?=$ch['id']?>,this,'imp-<?=$ch['id']?>')" class="btn btn-sm" style="font-size:.68rem;padding:2px 8px;background:rgba(22,192,121,.1);color:var(--green);border:1px solid rgba(22,192,121,.3)"><i class="bi bi-check2 me-1"></i>Done</button>
          </div>
          <div id="ai-<?=$ch['id']?>" style="display:none;margin-top:5px;font-size:.73rem;color:var(--dim);line-height:1.4"></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Right column -->
  <div class="col-lg-6">
    <?php if($newPagesData): ?>
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-file-earmark-plus me-1" style="color:var(--green)"></i>New Pages Today <span class="badge bg-success ms-1"><?=count($newPagesData)?></span></div>
      <div class="card-body p-3" style="max-height:180px;overflow-y:auto">
        <?php foreach($newPagesData as $p): $path=parse_url($p['page_url']??'',PHP_URL_PATH)?:'/'; ?>
        <div class="new-card d-flex align-items-center gap-2">
          <a href="<?=htmlspecialchars($p['page_url']??'')?>" target="_blank" style="color:var(--green);font-size:.78rem;text-decoration:underline;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($path)?> ↗</a>
          <span style="font-size:.65rem;background:rgba(22,192,121,.15);color:var(--green);padding:1px 6px;border-radius:4px;white-space:nowrap"><?=htmlspecialchars($p['website_name']??'')?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-activity me-1"></i>Other Changes</span>
        <span style="font-size:.72rem;color:var(--dim)"><?=count($allToday)?> pending</span>
      </div>
      <div class="card-body p-3" style="max-height:<?=$newPagesData?'230px':'400px'?>;overflow-y:auto">
        <?php if (!$allToday): ?>
        <div style="text-align:center;padding:20px;color:var(--dim);font-size:.82rem">✅ Sab resolve!</div>
        <?php else: ?>
        <?php foreach($allToday as $ch):
          $path = parse_url($ch['page_url']??'',PHP_URL_PATH)?:'/';
          $cat  = $ch['category'] ?? '';
          $catColor = $catColors[$cat] ?? 'transparent';
        ?>
        <div class="other-card d-flex align-items-center gap-2 flex-wrap" id="other-<?=$ch['id']?>">
          <span style="font-size:.68rem;color:var(--dim);min-width:60px"><?=$ch['website_name']??''?></span>
          <?php if($cat): ?><span style="font-size:.65rem;color:<?=$catColor?>;font-weight:700"><?=$cat?></span><?php endif; ?>
          <a href="<?=htmlspecialchars($ch['page_url']??'')?>" target="_blank" style="color:var(--blue);font-size:.77rem;text-decoration:underline;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($path)?> ↗</a>
          <span style="font-size:.65rem;color:var(--dim)"><?=date('h:i A',strtotime($ch['detected_at']))?></span>
          <button onclick="quickResolve(<?=$ch['id']?>,this,'other-<?=$ch['id']?>')" style="background:none;border:1px solid var(--border);color:var(--dim);border-radius:4px;padding:1px 6px;font-size:.68rem;cursor:pointer;flex-shrink:0">✓</button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php if(count($allToday)>0): ?>
      <div class="card-footer">
        <button onclick="resolveAllOther()" class="btn btn-sm btn-outline-secondary w-100" style="font-size:.75rem">
          <i class="bi bi-check-all me-1"></i>Resolve All Other (<?=count($allToday)?>)
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>

<script>
var impIds = <?=json_encode(array_column($importantChanges,'id'))?>;
var otherIds = <?=json_encode(array_column($allToday,'id'))?>;

function quickAI(id,btn) {
    var el=document.getElementById('ai-'+id);
    btn.innerHTML='⏳'; btn.disabled=true;
    fetch('../admin/ai_analysis.php?change_id='+id)
    .then(function(r){return r.json();})
    .then(function(d){
        var t=d.summary||d.error||'No summary';
        if(d.summary_hindi)t+='\n🇮🇳 '+d.summary_hindi;
        if(d.what_added&&d.what_added.length)t+='\n✅ '+d.what_added.slice(0,2).join(', ');
        el.textContent=t; el.style.display='block';
        btn.innerHTML='<i class="bi bi-stars me-1"></i>AI'; btn.disabled=false;
    }).catch(function(e){el.textContent='Error: '+e;el.style.display='block';btn.innerHTML='AI';btn.disabled=false;});
}
function quickTg(id,btn){
    btn.innerHTML='⏳'; btn.disabled=true;
    fetch('../admin/send_telegram.php?change_id='+id)
    .then(function(r){return r.json();})
    .then(function(d){
        if(d.ok){btn.innerHTML='✅';btn.style.color='var(--green)';}
        else{btn.innerHTML='❌';btn.disabled=false;alert('Telegram: '+(d.error||'Error'));}
    });
}
function quickResolve(id,btn,cardId){
    fetch('../admin/changes.php?resolve='+id)
    .then(function(){
        var card=document.getElementById(cardId||'imp-'+id);
        if(card)card.style.opacity='0.3';
        btn.innerHTML='✅'; btn.disabled=true;
    });
}
function resolveAllOther(){
    if(!confirm('Resolve all '+otherIds.length+' other changes?'))return;
    var done=0;
    function next(i){
        if(i>=otherIds.length){alert('✅ '+done+' resolved!');location.reload();return;}
        fetch('../admin/changes.php?resolve='+otherIds[i]).then(function(){done++;next(i+1);});
    }
    next(0);
}
function sendAllImportant(){
    if(!impIds.length){alert('No important changes today');return;}
    if(!confirm('Send '+impIds.length+' messages?'))return;
    var sent=0;
    function s(i){
        if(i>=impIds.length){alert('✅ '+sent+' sent!');return;}
        fetch('../admin/send_telegram.php?change_id='+impIds[i]).then(function(r){return r.json();}).then(function(d){if(d.ok)sent++;setTimeout(function(){s(i+1);},500);});
    }
    s(0);
}
</script>

<?php pageFooter(); ?>
