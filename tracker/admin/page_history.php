<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

$pid = (int)($_GET['page_id'] ?? 0);
if (!$pid) { flash('Invalid page ID','danger'); header('Location: websites.php'); exit; }

$page = $pdo->prepare("SELECT p.*, w.website_name FROM pages p LEFT JOIN websites w ON p.website_id=w.id WHERE p.id=?");
$page->execute([$pid]);
$page = $page->fetch();
if (!$page) { flash('Page not found','danger'); header('Location: websites.php'); exit; }

$changes = $pdo->prepare("
    SELECT * FROM changes
    WHERE page_id=?
    ORDER BY detected_at DESC
")->fetchAll() ?: [];
$changes = $pdo->prepare("SELECT * FROM changes WHERE page_id=? ORDER BY detected_at DESC");
$changes->execute([$pid]);
$changes = $changes->fetchAll();

$ctMap = [
    'TITLE_CHANGED'   => ['ct-title',  'bi-cursor-text', 'Title'],
    'META_CHANGED'    => ['ct-meta',   'bi-card-text',   'Meta'],
    'H1_CHANGED'      => ['ct-h1',     'bi-type-h1',     'H1'],
    'CONTENT_CHANGED' => ['ct-content','bi-file-diff',   'Content'],
];

pageHeader('Page History');
?>
<style>
.timeline { position:relative; padding-left:28px; }
.timeline::before { content:''; position:absolute; left:8px; top:0; bottom:0; width:2px; background:var(--border); }
.tl-item { position:relative; margin-bottom:20px; }
.tl-dot { position:absolute; left:-24px; top:4px; width:14px; height:14px; border-radius:50%; border:2px solid var(--bg2); }
.tl-card { background:var(--bg2); border:1px solid var(--border); border-radius:10px; padding:12px 16px; }
.tl-card:hover { border-color:rgba(124,92,252,.3); }
</style>

<div class="container py-4" style="max-width:860px">
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="changes.php">Changes</a></li>
    <li class="breadcrumb-item active">Page History</li>
  </ol>
</nav>

<div class="d-flex align-items-start gap-3 mb-4">
  <div style="background:rgba(168,139,250,.15);border:1px solid rgba(168,139,250,.3);border-radius:12px;padding:10px 14px;font-size:1.4rem">📋</div>
  <div style="flex:1;min-width:0">
    <h1 class="page-title">Page History</h1>
    <a href="<?=htmlspecialchars($page['page_url']??'')?>" target="_blank"
       style="font-size:.82rem;color:var(--blue);text-decoration:underline;word-break:break-all">
      <?=htmlspecialchars($page['page_url']??'')?>
      <i class="bi bi-box-arrow-up-right ms-1" style="font-size:.65rem"></i>
    </a>
    <div style="font-size:.78rem;color:var(--dim);margin-top:2px">
      <i class="bi bi-globe me-1"></i><?=htmlspecialchars($page['website_name']??'')?>
      &nbsp;·&nbsp; <?=count($changes)?> total changes
      <?php if($page['last_scan']): ?>
      &nbsp;·&nbsp; Last scan: <?=date('d M Y, h:i A',strtotime($page['last_scan']))?> IST
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if(!$changes): ?>
<div class="card"><div class="card-body text-center py-5" style="color:var(--dim)">
  <i class="bi bi-check-circle" style="font-size:2.5rem;color:var(--green)"></i>
  <p class="mt-3 mb-0 fw-bold">No changes recorded for this page</p>
</div></div>
<?php else: ?>

<!-- Stats bar -->
<?php
$typeCounts = [];
foreach($changes as $c) {
    $t = $c['change_type'];
    $typeCounts[$t] = ($typeCounts[$t]??0) + 1;
}
?>
<div class="row g-3 mb-4">
  <?php foreach($ctMap as $type => [$cls,$icon,$lbl]): ?>
  <div class="col-6 col-md-3">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:1.4rem;font-weight:800;color:var(--accent)"><?=isset($typeCounts[$type])?$typeCounts[$type]:0?></div>
      <div style="font-size:.72rem;color:var(--dim)"><i class="bi <?=$icon?> me-1"></i><?=$lbl?> Changes</div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Timeline -->
<div class="timeline">
  <?php foreach($changes as $c):
    $ct = $c['change_type'];
    $info = isset($ctMap[$ct]) ? $ctMap[$ct] : ['','bi-question','Change'];
    $dotColor = $ct==='CONTENT_CHANGED'?'#ef4444':($ct==='TITLE_CHANGED'?'#facc15':($ct==='H1_CHANGED'?'#a78bfa':'#60a5fa'));
    $resolved = !empty($c['resolved']);
  ?>
  <div class="tl-item">
    <div class="tl-dot" style="background:<?=$dotColor?>"></div>
    <div class="tl-card <?=$resolved?'':''" style="<?=$resolved?'opacity:.6':''?>">
      <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
        <span class="change-type <?=$info[0]?>">
          <i class="bi <?=$info[1]?> me-1"></i><?=$info[2]?> Changed
        </span>
        <?php if($resolved): ?>
        <span style="background:rgba(22,192,121,.15);color:var(--green);border:1px solid rgba(22,192,121,.25);border-radius:20px;padding:1px 8px;font-size:.68rem;font-weight:700">
          <i class="bi bi-check2-circle me-1"></i>Resolved
        </span>
        <?php endif; ?>
        <span style="font-size:.72rem;color:var(--dim);margin-left:auto">
          <?=date('d M Y',strtotime($c['detected_at']))?> 
          <span style="color:var(--accent)"><?=date('h:i A',strtotime($c['detected_at']))?> IST</span>
        </span>
      </div>
      <!-- Note display -->
      <?php if(!empty($c['note'])): ?>
      <div style="background:rgba(250,204,21,.08);border-left:3px solid var(--yellow);padding:6px 10px;border-radius:0 6px 6px 0;font-size:.8rem;color:var(--txt);margin-bottom:8px">
        <i class="bi bi-sticky me-1" style="color:var(--yellow)"></i><?=htmlspecialchars($c['note'])?>
      </div>
      <?php endif; ?>
      <!-- Content preview -->
      <?php
        $old = trim($c['old_content']??'');
        $new = trim($c['new_content']??'');
        $preview_old = mb_substr($old, 0, 120);
        $preview_new = mb_substr($new, 0, 120);
      ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:.75rem">
        <div style="background:rgba(239,68,68,.06);border-radius:6px;padding:6px 8px;color:var(--dim)">
          <div style="font-weight:700;color:var(--red);margin-bottom:3px">Old</div>
          <?=htmlspecialchars($preview_old)?><?=strlen($old)>120?'...':''?>
        </div>
        <div style="background:rgba(22,192,121,.06);border-radius:6px;padding:6px 8px;color:var(--dim)">
          <div style="font-weight:700;color:var(--green);margin-bottom:3px">New</div>
          <?=htmlspecialchars($preview_new)?><?=strlen($new)>120?'...':''?>
        </div>
      </div>
      <div class="mt-2 d-flex gap-2">
        <a href="changes.php?diff_id=<?=$c['id']?>" 
           style="font-size:.72rem;color:var(--blue);text-decoration:underline">View Full Diff</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>
</div>
<?php pageFooter(); ?>
