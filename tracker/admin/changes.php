<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

$wid       = (int)($_GET['website_id'] ?? 0);
$type      = $_GET['type'] ?? '';
$search    = trim($_GET['search'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$showResolved = isset($_GET['show_resolved']) && $_GET['show_resolved'] === '1';
// Date filter — default: last 15 days
$dateFrom  = isset($_GET['date_from']) && $_GET['date_from'] ? $_GET['date_from'] : date('Y-m-d', strtotime('-15 days'));
$dateTo    = isset($_GET['date_to'])   && $_GET['date_to']   ? $_GET['date_to']   : date('Y-m-d');

// ── ACTIONS ──────────────────────────────────────────────
// Resolve a change (mark done + store resolved_hash on page)
if (isset($_GET['resolve'])) {
    $rid = (int)$_GET['resolve'];
    try {
        // Get page_id and current content_hash
        $r = $pdo->prepare("SELECT c.page_id, p.content_hash FROM changes c LEFT JOIN pages p ON c.page_id=p.id WHERE c.id=?");
        $r->execute([$rid]);
        $r = $r->fetch();
        if ($r) {
            // Mark this change resolved
            $pdo->prepare("UPDATE changes SET resolved=1, resolved_at=NOW() WHERE id=?")
                ->execute([$rid]);
            // Also resolve ALL changes for this page (same page = same resolution)
            $pdo->prepare("UPDATE changes SET resolved=1, resolved_at=NOW() WHERE page_id=? AND resolved=0")
                ->execute([$r['page_id']]);
            // Save resolved_hash so scan knows this state is "accepted"
            try {
                $pdo->prepare("UPDATE pages SET resolved_hash=content_hash WHERE id=?")
                    ->execute([$r['page_id']]);
            } catch(Exception $e) {}
        }
        flash('✅ Change marked as resolved. Will reappear only if page changes again.', 'success');
    } catch(Exception $e) {
        flash('Error: ' . $e->getMessage(), 'danger');
    }
    $qs = http_build_query(['website_id'=>$wid,'type'=>$type,'search'=>$search,'page'=>$page]);
    header('Location: changes.php?' . $qs); exit;
}

// Unresolve
if (isset($_GET['unresolve'])) {
    $uid = (int)$_GET['unresolve'];
    $pdo->prepare("UPDATE changes SET resolved=0, resolved_at=NULL WHERE id=?")->execute([$uid]);
    flash('Change marked as unresolved.', 'warning');
    $qs = http_build_query(['website_id'=>$wid,'type'=>$type,'search'=>$search,'page'=>$page,'show_resolved'=>1]);
    header('Location: changes.php?' . $qs); exit;
}

// Delete single
if (isset($_GET['delete_change'])) {
    $pdo->prepare("DELETE FROM changes WHERE id=?")->execute([(int)$_GET['delete_change']]);
    flash('Change record deleted.');
    $qs = http_build_query(['website_id'=>$wid,'type'=>$type,'search'=>$search,'page'=>$page]);
    header('Location: changes.php?' . $qs); exit;
}

// Clear all unresolved
if (isset($_GET['clear_all']) && $_GET['clear_all'] === 'yes') {
    $pdo->query("DELETE FROM changes WHERE resolved=0");
    flash('All unresolved changes cleared.', 'warning');
    header('Location: changes.php'); exit;
}

// AJAX diff
if (isset($_GET['diff_id'])) {
    $did = (int)$_GET['diff_id'];
    $row = $pdo->prepare("SELECT c.*, p.page_url FROM changes c LEFT JOIN pages p ON c.page_id=p.id WHERE c.id=?");
    $row->execute([$did]);
    $row = $row->fetch();
    if (!$row) { echo json_encode(['error'=>'Not found']); exit; }

    // Limit size to prevent timeout on large pages
    $old = mb_substr(isset($row['old_content']) ? trim($row['old_content']) : '', 0, 3000);
    $new = mb_substr(isset($row['new_content']) ? trim($row['new_content']) : '', 0, 3000);

    function wdiff($oldT, $newT) {
        $oW = preg_split('/(\s+)/', $oldT, -1, PREG_SPLIT_DELIM_CAPTURE);
        $nW = preg_split('/(\s+)/', $newT, -1, PREG_SPLIT_DELIM_CAPTURE);
        $m = count($oW); $n = count($nW);
        if ($m > 200 || $n > 200) {  // Lower limit to prevent timeout
            $oS = preg_split('/(?<=[.!?।])\s+/', $oldT);
            $nS = preg_split('/(?<=[.!?।])\s+/', $newT);
            $oSet = array_flip($oS); $nSet = array_flip($nS);
            $oO = ''; $nO = '';
            foreach ($oS as $s) { $e=htmlspecialchars($s).' '; $oO.=isset($nSet[$s])?$e:"<mark class='d-del'>{$e}</mark>"; }
            foreach ($nS as $s) { $e=htmlspecialchars($s).' '; $nO.=isset($oSet[$s])?$e:"<mark class='d-ins'>{$e}</mark>"; }
            return ['old'=>$oO,'new'=>$nO];
        }
        $dp=array_fill(0,$m+1,array_fill(0,$n+1,0));
        for($i=1;$i<=$m;$i++) for($j=1;$j<=$n;$j++)
            $dp[$i][$j]=$oW[$i-1]===$nW[$j-1]?$dp[$i-1][$j-1]+1:max($dp[$i-1][$j],$dp[$i][$j-1]);
        $ops=[];$i=$m;$j=$n;
        while($i>0||$j>0){
            if($i>0&&$j>0&&strtolower($oW[$i-1])===strtolower($nW[$j-1])){$ops[]=['eq',$oW[$i-1]];$i--;$j--;}
            elseif($j>0&&($i==0||$dp[$i][$j-1]>=$dp[$i-1][$j])){$ops[]=['ins',$nW[$j-1]];$j--;}
            else{$ops[]=['del',$oW[$i-1]];$i--;}
        }
        $ops=array_reverse($ops);
        $oO='';$nO='';
        foreach($ops as $op){
            $w=htmlspecialchars($op[1]);
            if($op[0]==='eq'){$oO.=$w;$nO.=$w;}
            elseif($op[0]==='del')$oO.="<mark class='d-del'>{$w}</mark>";
            elseif($op[0]==='ins')$nO.="<mark class='d-ins'>{$w}</mark>";
        }
        return ['old'=>$oO,'new'=>$nO];
    }

    $diff = wdiff($old, $new);
    header('Content-Type: application/json');
    echo json_encode(['old'=>$diff['old'],'new'=>$diff['new'],'url'=>(isset($row['page_url']) ? $row['page_url'] : ''),'same'=>($old===$new)]);
    exit;
}


// Save note
if (isset($_GET['save_note'])) {
    $nid  = (int)$_GET['save_note'];
    $note = trim($_POST['note'] ?? '');
    try {
        $pdo->prepare("UPDATE changes SET note=? WHERE id=?")->execute(array($note, $nid));
        echo json_encode(array('ok'=>true,'note'=>$note));
    } catch(Exception $e) {
        echo json_encode(array('ok'=>false));
    }
    exit;
}

// AI Summary — Advanced change analysis
if (isset($_GET['summarize'])) {
    $sid = (int)$_GET['summarize'];
    $r2  = $pdo->prepare("SELECT c.*, p.page_url FROM changes c LEFT JOIN pages p ON c.page_id=p.id WHERE c.id=?");
    $r2->execute(array($sid));
    $r2  = $r2->fetch();
    if (!$r2) { echo json_encode(array('summary'=>'Not found', 'details'=>array())); exit; }

    $old2    = strip_tags($r2['old_content'] ?? '');
    $new2    = strip_tags($r2['new_content'] ?? '');
    $url     = $r2['page_url'] ?? '';
    $details = array();

    // ── What was ADDED (sentences in new not in old) ──────
    $oldSents = array_flip(preg_split('/(?<=[.!?])\s+/', strtolower($old2)));
    $newSents = preg_split('/(?<=[.!?])\s+/', $new2);
    $addedSents = array();
    foreach ($newSents as $s) {
        $s = trim($s);
        if (strlen($s) < 30) continue;
        if (!isset($oldSents[strtolower($s)])) {
            $addedSents[] = $s;
        }
    }

    // ── What was REMOVED (sentences in old not in new) ────
    $newSentsLow = array_flip(preg_split('/(?<=[.!?])\s+/', strtolower($new2)));
    $oldSents2   = preg_split('/(?<=[.!?])\s+/', $old2);
    $removedSents = array();
    foreach ($oldSents2 as $s) {
        $s = trim($s);
        if (strlen($s) < 30) continue;
        if (!isset($newSentsLow[strtolower($s)])) {
            $removedSents[] = $s;
        }
    }

    // ── Pattern detection ─────────────────────────────────
    $findings = array();

    if (preg_match('/(final result|result out|result declared|result released|merit list)/i', $new2)
        && !preg_match('/(final result|result out|result declared|result released|merit list)/i', $old2))
        $findings[] = array('type'=>'success', 'icon'=>'🎯', 'text'=>'Result declared/released');

    if (preg_match('/(admit card.*(out|available|download|released)|hall ticket.*(?:out|available))/i', $new2)
        && !preg_match('/(admit card.*(out|available|download))/i', $old2))
        $findings[] = array('type'=>'info', 'icon'=>'🎫', 'text'=>'Admit card now available');

    if (preg_match('/(answer key.*(out|released|available|download))/i', $new2)
        && !preg_match('/(answer key.*(out|released|available))/i', $old2))
        $findings[] = array('type'=>'info', 'icon'=>'📝', 'text'=>'Answer key released');

    if (preg_match('/(last date.*extend|date extended|extended.*last date)/i', $new2))
        $findings[] = array('type'=>'warning', 'icon'=>'📅', 'text'=>'Last date extended');

    if (preg_match('/(vacancy.*increas|increas.*vacanc|new vacancy)/i', $new2)
        && !preg_match('/(vacancy.*increas)/i', $old2))
        $findings[] = array('type'=>'success', 'icon'=>'📈', 'text'=>'Vacancy increased');

    if (preg_match('/(cut.?off.*(out|released|available))/i', $new2)
        && !preg_match('/(cut.?off.*(out|released))/i', $old2))
        $findings[] = array('type'=>'info', 'icon'=>'✂️', 'text'=>'Cut off released');

    if (preg_match('/(apply online.*(start|open|begin)|application.*(start|open))/i', $new2)
        && !preg_match('/(apply online.*(start|open))/i', $old2))
        $findings[] = array('type'=>'success', 'icon'=>'🔔', 'text'=>'Application started');

    if (preg_match('/(PET|physical test|physical efficiency).*(date|schedule|admit)/i', $new2)
        && !preg_match('/(PET|physical test).*date/i', $old2))
        $findings[] = array('type'=>'info', 'icon'=>'🏃', 'text'=>'PET/Physical test date updated');

    if (preg_match('/(document verification|DV date|DV schedule)/i', $new2)
        && !preg_match('/(document verification|DV date)/i', $old2))
        $findings[] = array('type'=>'info', 'icon'=>'📋', 'text'=>'Document verification scheduled');

    if (preg_match('/(score card|marks.*(out|available|released))/i', $new2)
        && !preg_match('/(score card|marks.*out)/i', $old2))
        $findings[] = array('type'=>'success', 'icon'=>'📊', 'text'=>'Score card/marks available');

    if (preg_match('/(syllabus.*(out|released)|exam pattern.*released)/i', $new2)
        && !preg_match('/(syllabus.*out)/i', $old2))
        $findings[] = array('type'=>'info', 'icon'=>'📚', 'text'=>'Syllabus released');

    // ── Size change ───────────────────────────────────────
    $oldLen = strlen($old2);
    $newLen = strlen($new2);
    $diff   = $newLen - $oldLen;

    if ($diff > 500)
        $details[] = array('icon'=>'➕', 'text'=>'Content expanded (+' . round($diff/1000,1) . 'K chars)');
    elseif ($diff < -500)
        $details[] = array('icon'=>'➖', 'text'=>'Content reduced (-' . round(abs($diff)/1000,1) . 'K chars)');

    // ── Added content preview ─────────────────────────────
    if (!empty($addedSents)) {
        $topAdded = array_slice($addedSents, 0, 3);
        foreach ($topAdded as $s)
            $details[] = array('icon'=>'🟢', 'text'=>mb_substr(trim($s), 0, 120) . (mb_strlen($s)>120?'...':''));
    }

    // ── Removed content preview ───────────────────────────
    if (!empty($removedSents)) {
        $topRemoved = array_slice($removedSents, 0, 2);
        foreach ($topRemoved as $s)
            $details[] = array('icon'=>'🔴', 'text'=>mb_substr(trim($s), 0, 120) . (mb_strlen($s)>120?'...':''));
    }

    // ── Dates changed ─────────────────────────────────────
    preg_match_all('/\b(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{4}|\d{1,2}\/\d{1,2}\/\d{4})\b/i', $new2, $nd);
    preg_match_all('/\b(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{4}|\d{1,2}\/\d{1,2}\/\d{4})\b/i', $old2, $od);
    $newDates     = array_unique($nd[0]);
    $oldDates     = array_flip(array_unique($od[0]));
    $changedDates = array();
    foreach ($newDates as $d) {
        if (!isset($oldDates[$d])) $changedDates[] = $d;
    }
    if ($changedDates)
        $details[] = array('icon'=>'📅', 'text'=>'New dates: ' . implode(', ', array_slice($changedDates, 0, 4)));

    // ── Build final summary ───────────────────────────────
    $summaryText = '';
    if (!empty($findings)) {
        $summaryText = implode(' | ', array_map(function($f){ return $f['icon'].' '.$f['text']; }, $findings));
    } elseif (!empty($details)) {
        $summaryText = $details[0]['icon'] . ' ' . $details[0]['text'];
    } else {
        $summaryText = '✏️ Minor content update';
    }

    echo json_encode(array(
        'summary'  => $summaryText,
        'findings' => $findings,
        'details'  => $details,
        'added_count'   => count($addedSents),
        'removed_count' => count($removedSents),
        'size_diff'     => $diff,
    ));
    exit;
}


// ── QUERY ────────────────────────────────────────────────
$where = ['1=1']; $params = [];
// Date filter
$where[] = 'DATE(c.detected_at) >= ?'; $params[] = $dateFrom;
$where[] = 'DATE(c.detected_at) <= ?'; $params[] = $dateTo;
// Default: show only unresolved (safe - if column missing, show all)
try {
    $pdo->query("SELECT resolved FROM changes LIMIT 1");
    $resolvedColExists = true;
} catch(Exception $e) {
    $resolvedColExists = false;
}
if ($resolvedColExists) {
    if (!$showResolved) {
        $where[] = '(c.resolved = 0 OR c.resolved IS NULL)';
    } else {
        $where[] = 'c.resolved = 1';
    }
}
if ($wid)    { $where[] = 'c.website_id = ?'; $params[] = $wid; }
if ($type)   { $where[] = 'c.change_type = ?'; $params[] = $type; }
if ($search) {
    $where[] = '(p.page_url LIKE ? OR w.website_name LIKE ? OR c.note LIKE ? OR c.old_content LIKE ? OR c.new_content LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereSQL = implode(' AND ', $where);

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM changes c LEFT JOIN pages p ON c.page_id=p.id LEFT JOIN websites w ON c.website_id=w.id WHERE $whereSQL");
$totalStmt->execute($params);
$total  = $totalStmt->fetchColumn();
$pages  = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$changesStmt = $pdo->prepare("
    SELECT c.*, p.page_url, w.website_name
    FROM changes c LEFT JOIN pages p ON c.page_id=p.id LEFT JOIN websites w ON c.website_id=w.id
    WHERE $whereSQL ORDER BY c.detected_at DESC LIMIT $perPage OFFSET $offset
");
$changesStmt->execute($params);
$changes = $changesStmt->fetchAll();

// Counts for tabs
try {
    $unresolvedCount = $pdo->prepare("SELECT COUNT(*) FROM changes c LEFT JOIN pages p ON c.page_id=p.id LEFT JOIN websites w ON c.website_id=w.id WHERE (c.resolved=0 OR c.resolved IS NULL)" . ($wid?" AND c.website_id=$wid":""));
    $unresolvedCount->execute();
    $uCount = $unresolvedCount->fetchColumn();
    $resolvedCount = $pdo->prepare("SELECT COUNT(*) FROM changes c WHERE c.resolved=1" . ($wid?" AND c.website_id=$wid":""));
    $resolvedCount->execute();
    $rCount = $resolvedCount->fetchColumn();
} catch(Exception $e) {
    // resolved column not yet created - run setup_resolved.php first
    $uCount = $pdo->query("SELECT COUNT(*) FROM changes")->fetchColumn();
    $rCount = 0;
}

$websites = $pdo->query("SELECT id, website_name FROM websites ORDER BY website_name")->fetchAll();

$ctMap = [
    'TITLE_CHANGED'   => ['ct-title',   'bi-cursor-text', 'Title Changed'],
    'META_CHANGED'    => ['ct-meta',    'bi-card-text',   'Meta Changed'],
    'H1_CHANGED'      => ['ct-h1',      'bi-type-h1',     'H1 Changed'],
    'CONTENT_CHANGED' => ['ct-content', 'bi-file-diff',   'Content Changed'],
];

pageHeader('Changes');
?>

<style>
.change-item{background:var(--bg2);border:1px solid var(--border);border-radius:12px;margin-bottom:10px;overflow:hidden;transition:border-color .2s}
.change-item:hover{border-color:rgba(124,92,252,.3)}
.change-item.resolved-item{opacity:.7;border-color:rgba(22,192,121,.2)}
.change-header{display:flex;align-items:center;gap:8px;padding:10px 14px;cursor:pointer;user-select:none;flex-wrap:wrap}
.change-header:hover{background:rgba(255,255,255,.02)}
.change-body{border-top:1px solid var(--border);display:none}
.change-body.open{display:block}
.chevron{transition:transform .2s;color:var(--dim);margin-left:auto}
.chevron.open{transform:rotate(180deg)}
.site-badge{background:rgba(124,92,252,.12);color:var(--accent);border:1px solid rgba(124,92,252,.2);border-radius:6px;padding:2px 8px;font-size:.72rem;font-weight:700;white-space:nowrap}
.page-link-pill{color:var(--blue);font-size:.78rem;text-decoration:underline;text-underline-offset:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:300px;display:inline-block}

/* Resolve button */
.btn-resolve{background:rgba(22,192,121,.15);border:1px solid rgba(22,192,121,.35);color:var(--green);border-radius:6px;padding:3px 10px;font-size:.75rem;font-weight:700;cursor:pointer;white-space:nowrap;text-decoration:none;transition:.15s}
.btn-resolve:hover{background:rgba(22,192,121,.3);color:var(--green)}
.resolved-badge{background:rgba(22,192,121,.15);color:var(--green);border:1px solid rgba(22,192,121,.3);border-radius:20px;padding:2px 10px;font-size:.7rem;font-weight:700}

/* Tabs */
.view-tabs{display:flex;gap:0;border-bottom:1px solid var(--border)}
.view-tab{padding:8px 16px;font-size:.8rem;font-weight:600;cursor:pointer;color:var(--dim);border-bottom:2px solid transparent;margin-bottom:-1px;transition:.15s}
.view-tab.active{color:#fff;border-bottom-color:var(--accent)}
.view-tab:hover{color:#fff}
.view-panel{display:none}
.view-panel.active{display:block}

/* Diff */
.diff-grid{display:grid;grid-template-columns:1fr 1fr}
.diff-head{font-size:.7rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:6px 14px;border-bottom:1px solid var(--border)}
.diff-head-old{background:rgba(239,68,68,.12);color:var(--red);border-right:1px solid var(--border)}
.diff-head-new{background:rgba(22,192,121,.12);color:var(--green)}
.diff-col{padding:14px;font-size:.84rem;line-height:1.75;word-break:break-word;white-space:pre-wrap;overflow-y:auto;max-height:420px}
.diff-col-old{background:rgba(239,68,68,.04);border-right:1px solid var(--border)}
.diff-col-new{background:rgba(22,192,121,.04)}
.diff-col-loading{display:flex;align-items:center;justify-content:center;padding:40px;color:var(--dim);font-size:.85rem}
mark.d-del{background:rgba(239,68,68,.28);color:#fca5a5;border-radius:3px;padding:1px 2px;text-decoration:line-through}
mark.d-ins{background:rgba(22,192,121,.25);color:#6ee7b7;border-radius:3px;padding:1px 2px;font-weight:700}

/* Preview */
.preview-wrap{display:grid;grid-template-columns:1fr 1fr}
.preview-col-old{border-right:1px solid var(--border)}
.preview-head{font-size:.7rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;padding:6px 14px;border-bottom:1px solid var(--border)}
.preview-head-old{background:rgba(239,68,68,.12);color:var(--red)}
.preview-head-new{background:rgba(22,192,121,.12);color:var(--green)}
.preview-frame{width:100%;height:540px;border:none;background:#fff;display:none}

/* Page tabs */
.page-tabs{display:flex;gap:0;border-bottom:1px solid var(--border);margin-bottom:0}
.page-tab{padding:9px 20px;font-size:.85rem;font-weight:600;cursor:pointer;color:var(--dim);border-bottom:3px solid transparent;margin-bottom:-1px;transition:.15s;display:flex;align-items:center;gap:6px}
.page-tab.active{color:#fff;border-bottom-color:var(--accent)}
.page-tab:hover{color:#fff}
.tab-count{font-size:.7rem;background:var(--bg3);border-radius:20px;padding:1px 7px;font-weight:700}
.page-tab.active .tab-count{background:var(--accent);color:#fff}
</style>

<div class="container-fluid py-4 px-4">

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Changes</li>
  </ol>
</nav>

<?php showFlash(); ?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h1 class="page-title"><i class="bi bi-activity me-2" style="color:var(--red)"></i>Detected Changes</h1>
    <p class="text-muted mb-0" style="font-size:.85rem">Tracking changes across monitored websites</p>
  </div>
  <a href="export_changes.php?date_from=<?=$dateFrom?>&date_to=<?=$dateTo?>&website_id=<?=$wid?>"
     class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-file-earmark-excel me-1" style="color:#16a34a"></i>Excel
  </a>
  <?php if (!$showResolved && $uCount > 0): ?>
  <a href="?clear_all=yes" class="btn btn-sm btn-outline-secondary"
     onclick="return confirm('Clear all unresolved changes?')">
    <i class="bi bi-trash3 me-1"></i>Clear
  </a>
  <?php endif; ?>
</div>

<!-- Resolved / Unresolved Tabs -->
<div class="page-tabs mb-3">
  <div class="page-tab <?= !$showResolved?'active':'' ?>"
       onclick="location.href='changes.php?website_id=<?=$wid?>&type=<?=urlencode($type)?>&search=<?=urlencode($search)?>'">
    <i class="bi bi-bell-fill" style="color:var(--red)"></i>
    Pending
    <span class="tab-count"><?= $uCount ?></span>
  </div>
  <div class="page-tab <?= $showResolved?'active':'' ?>"
       onclick="location.href='changes.php?show_resolved=1&website_id=<?=$wid?>&type=<?=urlencode($type)?>&search=<?=urlencode($search)?>'">
    <i class="bi bi-check-circle-fill" style="color:var(--green)"></i>
    Resolved
    <span class="tab-count"><?= $rCount ?></span>
  </div>
</div>

<!-- Filters -->
<!-- Quick date presets -->
<div class="d-flex gap-2 mb-2 flex-wrap">
  <span style="font-size:.78rem;color:var(--dim);align-self:center">Quick:</span>
  <?php
  $presets = [
    'Today'    => [date('Y-m-d'), date('Y-m-d')],
    'Yesterday'=> [date('Y-m-d',strtotime('-1 day')), date('Y-m-d',strtotime('-1 day'))],
    'Last 7 days'  => [date('Y-m-d',strtotime('-7 days')), date('Y-m-d')],
    'Last 15 days' => [date('Y-m-d',strtotime('-15 days')), date('Y-m-d')],
    'This Month'   => [date('Y-m-01'), date('Y-m-d')],
    'Since Jun 15' => ['2026-06-15', date('Y-m-d')],
  ];
  foreach($presets as $label => [$from, $to]):
    $active = ($dateFrom === $from && $dateTo === $to);
  ?>
  <a href="?date_from=<?=$from?>&date_to=<?=$to?>&website_id=<?=$wid?>&type=<?=urlencode($type)?>&show_resolved=<?=$showResolved?1:0?>"
     style="padding:3px 10px;border-radius:20px;font-size:.75rem;text-decoration:none;font-weight:600;
            background:<?=$active?'var(--accent)':'var(--bg3)'?>;
            color:<?=$active?'#fff':'var(--dim)'?>;
            border:1px solid <?=$active?'var(--accent)':'var(--border)'?>">
    <?=$label?>
  </a>
  <?php endforeach; ?>
</div>

<form method="get" class="card mb-3">
  <?php if($showResolved): ?><input type="hidden" name="show_resolved" value="1"><?php endif; ?>
  <input type="hidden" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
  <input type="hidden" name="date_to"   value="<?= htmlspecialchars($dateTo) ?>">
  <div class="card-body p-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label mb-1" style="font-size:.78rem">Website</label>
        <select name="website_id" class="form-select form-select-sm">
          <option value="">All Websites</option>
          <?php foreach ($websites as $w): ?>
          <option value="<?=$w['id']?>" <?=$wid==$w['id']?'selected':''?>><?=htmlspecialchars($w['website_name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1" style="font-size:.78rem">Change Type</label>
        <select name="type" class="form-select form-select-sm">
          <option value="">All Types</option>
          <option value="TITLE_CHANGED"   <?=$type==='TITLE_CHANGED'  ?'selected':''?>>Title Changed</option>
          <option value="META_CHANGED"    <?=$type==='META_CHANGED'   ?'selected':''?>>Meta Changed</option>
          <option value="H1_CHANGED"      <?=$type==='H1_CHANGED'     ?'selected':''?>>H1 Changed</option>
          <option value="CONTENT_CHANGED" <?=$type==='CONTENT_CHANGED'?'selected':''?>>Content Changed</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label mb-1" style="font-size:.78rem">Search URL / Website</label>
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Search..." value="<?=htmlspecialchars($search)?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1" style="font-size:.78rem">From Date</label>
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= htmlspecialchars($_GET['date_from'] ?? date('Y-m-d', strtotime('-15 days'))) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1" style="font-size:.78rem">To Date</label>
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= htmlspecialchars($_GET['date_to'] ?? date('Y-m-d')) ?>">
      </div>
      <div class="col-md-2 d-flex align-items-end gap-1">
        <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search me-1"></i>Filter</button>
        <a href="changes.php" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="bi bi-x-circle"></i></a>
      </div>
    </div>
  </div>
</form>

<?php if (!$changes): ?>
<div class="card">
  <div class="card-body text-center py-5" style="color:var(--dim)">
    <?php if ($showResolved): ?>
      <i class="bi bi-check-circle" style="font-size:3rem;color:var(--green)"></i>
      <p class="mt-3 mb-0 fw-bold">No resolved changes yet</p>
      <p class="small">Mark changes as resolved to see them here</p>
    <?php else: ?>
      <i class="bi bi-check-circle" style="font-size:3rem;color:var(--green)"></i>
      <p class="mt-3 mb-0 fw-bold">No pending changes!</p>
      <p class="small">All changes resolved or no scan run yet</p>
      <a href="../cron/scan_changes.php" class="btn btn-primary btn-sm">Run Scan Now</a>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>

<div class="d-flex gap-2 mb-3">
  <button onclick="toggleAll(true)"  class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrows-expand me-1"></i>Expand All</button>
  <button onclick="toggleAll(false)" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrows-collapse me-1"></i>Collapse All</button>
  <?php if (!$showResolved): ?>
  <span style="font-size:.8rem;color:var(--dim);align-self:center;margin-left:4px">
    <i class="bi bi-info-circle me-1"></i>Click ✅ Resolve to hide a change after you've reviewed it
  </span>
  <?php endif; ?>
</div>

<?php foreach ($changes as $c):
    $_ct = isset($ctMap[$c['change_type']]) ? $ctMap[$c['change_type']] : array('','bi-question','Unknown'); $cls=$_ct[0]; $icon=$_ct[1]; $label=$_ct[2];
    $pageUrl  = $c['page_url'] ?? '';
    $path     = parse_url($pageUrl, PHP_URL_PATH) ?: '/';
    $isResolved = !empty($c['resolved']);
?>
<div class="change-item <?= $isResolved?'resolved-item':'' ?>" id="ci-<?=$c['id']?>">

  <div class="change-header" onclick="toggleItem(<?=$c['id']?>)">
    <span style="color:var(--dim);font-size:.72rem;min-width:30px">#<?=$c['id']?></span>
    <span class="site-badge"><?=htmlspecialchars($c['website_name']??'—')?></span>
    <span class="change-type <?=$cls?>"><i class="bi <?=$icon?> me-1"></i><?=$label?></span>
    <?php
    // Category badge
    $cat = $c['category'] ?? '';
    $catColors = ['Result'=>'#16c079','Admit Card'=>'#60a5fa','Answer Key'=>'#a78bfa',
                  'Cut Off'=>'#f97316','Recruitment'=>'#facc15','Notification'=>'#ef4444'];
    $catColor = isset($catColors[$cat]) ? $catColors[$cat] : 'var(--dim)';
    if ($cat): ?>
    <span style="background:<?=$catColor?>22;color:<?=$catColor?>;border:1px solid <?=$catColor?>44;border-radius:4px;padding:1px 7px;font-size:.68rem;font-weight:700"><?=$cat?></span>
    <?php endif; ?>
    <?php
    // Priority stars
    $pri = (int)($c['priority_score'] ?? 5);
    $conf = (int)($c['confidence'] ?? 50);
    if ($pri >= 8): ?>
    <span style="color:#facc15;font-size:.75rem" title="Priority: <?=$pri?>/10">⭐<?=$pri>=9?'⭐':''?></span>
    <?php endif; ?>
    <a href="<?=htmlspecialchars($pageUrl)?>" target="_blank"
       class="page-link-pill" title="<?=htmlspecialchars($pageUrl)?>"
       onclick="event.stopPropagation()">
      <?=htmlspecialchars($path)?><i class="bi bi-box-arrow-up-right ms-1" style="font-size:.6rem"></i>
    </a>
    <span style="font-size:.72rem;color:var(--dim);margin-left:auto;white-space:nowrap">
      <?php
        $ts = strtotime($c['detected_at']);
        $now = time();
        $diff = $now - $ts;
        if ($diff < 3600) $rel = round($diff/60) . ' min ago';
        elseif ($diff < 86400) $rel = round($diff/3600) . ' hr ago';
        elseif ($diff < 172800) $rel = 'Yesterday';
        else $rel = '';
        echo date('d M Y', $ts) . '<br>';
        echo '<span style="color:var(--accent)">' . date('h:i A', $ts) . ' IST</span>';
        if ($rel) echo ' <span style="color:var(--dim);font-size:.65rem">(' . $rel . ')</span>';
      ?>
    </span>

    <?php if ($isResolved): ?>
      <!-- Resolved badge + Undo -->
      <span class="resolved-badge"><i class="bi bi-check2-circle me-1"></i>Resolved</span>
      <a href="?unresolve=<?=$c['id']?>&show_resolved=1"
         class="btn btn-sm btn-outline-secondary" style="padding:2px 8px;font-size:.72rem"
         onclick="event.stopPropagation();return confirm('Mark as unresolved?')" title="Undo resolve">
        <i class="bi bi-arrow-counterclockwise"></i> Undo
      </a>
    <?php else: ?>
      <!-- Resolve button -->
      <a href="?resolve=<?=$c['id']?>&website_id=<?=$wid?>&type=<?=urlencode($type)?>&search=<?=urlencode($search)?>&page=<?=$page?>"
         class="btn-resolve"
         onclick="event.stopPropagation()"
         title="Mark as resolved — hide until next change">
        <i class="bi bi-check2-circle me-1"></i>Resolve
      </a>
    <?php endif; ?>

    <a href="?delete_change=<?=$c['id']?><?=$wid?"&website_id=$wid":''?>"
       class="btn btn-sm btn-danger" style="padding:2px 7px"
       onclick="event.stopPropagation();return confirm('Permanently delete this record?')" title="Delete">
      <i class="bi bi-trash3" style="font-size:.72rem"></i>
    </a>
    <i class="bi bi-chevron-down chevron" id="chev-<?=$c['id']?>"></i>
  </div>

  <div class="change-body" id="cb-<?=$c['id']?>" data-id="<?=$c['id']?>" data-loaded="0">
    <!-- Tabs -->
    <div class="view-tabs">
      <div class="view-tab active" onclick="switchTab(<?=$c['id']?>,'text',this)">
        <i class="bi bi-file-diff me-1"></i>Text Diff
      </div>
      <div class="view-tab" onclick="switchTab(<?=$c['id']?>,'preview',this)">
        <i class="bi bi-layout-text-window me-1"></i>Page Preview
      </div>
    </div>

    <!-- TEXT DIFF -->
    <div class="view-panel active" id="panel-text-<?=$c['id']?>">
      <!-- Structured data if available -->
      <?php if (!empty($c['structured_data'])): ?>
      <?php $sd = json_decode($c['structured_data'], true) ?: []; ?>
      <?php if ($sd): ?>
      <div style="background:rgba(96,165,250,.08);border-bottom:1px solid var(--border);padding:8px 14px;display:flex;flex-wrap:wrap;gap:10px;font-size:.75rem">
        <?php foreach($sd as $k=>$v): if(!$v) continue; ?>
        <span style="color:var(--dim)"><?=ucfirst(str_replace('_',' ',$k))?>:</span>
        <span style="color:var(--accent);font-weight:600"><?=htmlspecialchars($v)?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
      <!-- Links added -->
      <?php if (!empty($c['links_added'])): ?>
      <?php $links = json_decode($c['links_added'], true) ?: []; ?>
      <?php if ($links): ?>
      <div style="background:rgba(22,192,121,.06);border-bottom:1px solid var(--border);padding:6px 14px;font-size:.75rem">
        <span style="color:var(--green);font-weight:700">🔗 New Links:</span>
        <?php foreach(array_slice($links,0,3) as $lnk): ?>
        <span style="color:#d1fae5;margin-left:8px">• <?=htmlspecialchars(substr($lnk,0,80))?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
      <div class="diff-grid">
        <div class="diff-head diff-head-old"><i class="bi bi-x-circle me-1"></i>Old Content <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:.68rem">— removed text highlighted</span></div>
        <div class="diff-head diff-head-new"><i class="bi bi-check-circle me-1"></i>New Content <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:.68rem">— added text highlighted</span></div>
      </div>
      <div class="diff-grid">
        <div class="diff-col diff-col-old diff-col-loading" id="diff-old-<?=$c['id']?>"><span style="color:var(--dim)">⏳ Loading...</span></div>
        <div class="diff-col diff-col-new diff-col-loading" id="diff-new-<?=$c['id']?>"><span style="color:var(--dim)">⏳ Loading...</span></div>
      </div>
    </div>

    <!-- PAGE PREVIEW -->
    <div class="view-panel" id="panel-preview-<?=$c['id']?>">
      <div class="preview-wrap">
        <div class="preview-col preview-col-old">
          <div class="preview-head preview-head-old"><i class="bi bi-x-circle me-1"></i>Old Snapshot</div>
          <div id="prev-load-old-<?=$c['id']?>" style="padding:30px;text-align:center;color:#888;font-family:sans-serif;font-size:.85rem;background:#fff">⏳ Loading...</div>
          <iframe id="preview-old-<?=$c['id']?>" class="preview-frame" src="about:blank"
                  data-src="page_preview.php?id=<?=$c['id']?>&mode=old"
                  onload="this.style.display='block';document.getElementById('prev-load-old-<?=$c['id']?>').style.display='none'"></iframe>
        </div>
        <div class="preview-col">
          <div class="preview-head preview-head-new"><i class="bi bi-check-circle me-1"></i>Live Page with Highlights</div>
          <div id="prev-load-new-<?=$c['id']?>" style="padding:30px;text-align:center;color:#888;font-family:sans-serif;font-size:.85rem;background:#fff">⏳ Loading live page...</div>
          <iframe id="preview-new-<?=$c['id']?>" class="preview-frame" src="about:blank"
                  data-src="page_preview.php?id=<?=$c['id']?>&mode=new"
                  onload="this.style.display='block';document.getElementById('prev-load-new-<?=$c['id']?>').style.display='none'"></iframe>
        </div>
      </div>
    </div>
  <!-- Note / Summary / History -->
  <div style="display:flex;align-items:center;gap:8px;padding:8px 14px;border-top:1px solid var(--border);background:var(--bg3);flex-wrap:wrap">
    <i class="bi bi-sticky" style="color:var(--yellow);flex-shrink:0"></i>
    <input type="text" id="note-<?=$c['id']?>"
           value="<?=htmlspecialchars($c['note']??'')?>"
           placeholder="Add a note..."
           style="flex:1;min-width:180px;background:var(--bg);border:1px solid var(--border);color:var(--txt);border-radius:6px;padding:4px 10px;font-size:.78rem"
           onkeydown="if(event.key==='Enter') saveNote(<?=$c['id']?>)">
    <button onclick="saveNote(<?=$c['id']?>)"
            style="background:rgba(250,204,21,.15);border:1px solid rgba(250,204,21,.3);color:var(--yellow);border-radius:6px;padding:3px 10px;font-size:.72rem;cursor:pointer;white-space:nowrap">
      <i class="bi bi-check2 me-1"></i>Save Note
    </button>
    <button onclick="getClaudeAnalysis(<?=$c['id']?>)"
            style="background:linear-gradient(135deg,rgba(124,92,252,.2),rgba(96,165,250,.2));border:1px solid rgba(124,92,252,.4);color:var(--accent);border-radius:6px;padding:3px 12px;font-size:.72rem;cursor:pointer;white-space:nowrap;font-weight:700"
            title="Exact analysis using Claude AI">
      <i class="bi bi-stars me-1"></i>Claude AI
    </button>
    <button onclick="sendTg(<?=$c['id']?>)"
            style="background:rgba(41,182,246,.1);border:1px solid rgba(41,182,246,.3);color:#29b6f6;border-radius:6px;padding:3px 10px;font-size:.72rem;cursor:pointer;white-space:nowrap"
            title="Send Telegram notification">
      <i class="bi bi-telegram me-1"></i>Notify
    </button>
    <button onclick="getSummary(<?=$c['id']?>)"
            style="background:rgba(96,165,250,.1);border:1px solid rgba(96,165,250,.25);color:var(--blue);border-radius:6px;padding:3px 10px;font-size:.72rem;cursor:pointer;white-space:nowrap"
            title="Quick rule-based summary (free)">
      <i class="bi bi-magic me-1"></i>Quick
    </button>
    <?php if(!empty($c['page_id'])): ?>
    <a href="page_history.php?page_id=<?=$c['page_id']?>"
       style="background:rgba(96,165,250,.1);border:1px solid rgba(96,165,250,.25);color:var(--blue);border-radius:6px;padding:3px 10px;font-size:.72rem;text-decoration:none;white-space:nowrap">
      <i class="bi bi-clock-history me-1"></i>History
    </a>
    <?php endif; ?>
    <div id="summ-<?=$c['id']?>" style="display:none;font-size:.75rem;color:var(--green);padding:2px 8px;background:rgba(22,192,121,.1);border-radius:6px;border:1px solid rgba(22,192,121,.2);flex:1"></div>
  </div>
  <div id="claude-<?=$c['id']?>" style="display:none;padding:8px 14px;border-top:1px solid var(--border)"></div>

  </div>
</div>
<?php endforeach; ?>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<nav class="mt-3">
  <ul class="pagination pagination-sm">
    <?php if($page>1): ?><li class="page-item"><a class="page-link" style="background:var(--bg2);border-color:var(--border);color:var(--txt)" href="?page=<?=$page-1?>&website_id=<?=$wid?>&type=<?=urlencode($type)?>&search=<?=urlencode($search)?>&show_resolved=<?=$showResolved?1:0?>">‹ Prev</a></li><?php endif; ?>
    <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?>
    <li class="page-item <?=$i==$page?'active':''?>"><a class="page-link" style="background:var(--bg2);border-color:var(--border);color:var(--txt)" href="?page=<?=$i?>&website_id=<?=$wid?>&type=<?=urlencode($type)?>&search=<?=urlencode($search)?>&show_resolved=<?=$showResolved?1:0?>"><?=$i?></a></li>
    <?php endfor; ?>
    <?php if($page<$pages): ?><li class="page-item"><a class="page-link" style="background:var(--bg2);border-color:var(--border);color:var(--txt)" href="?page=<?=$page+1?>&website_id=<?=$wid?>&type=<?=urlencode($type)?>&search=<?=urlencode($search)?>&show_resolved=<?=$showResolved?1:0?>">Next ›</a></li><?php endif; ?>
  </ul>
</nav>
<?php endif; ?>

<?php endif; ?>
</div>

<script>
function getClaudeAnalysis(id) {
    var el   = document.getElementById('claude-' + id);
    var note = document.getElementById('note-' + id);
    if (!el) { alert('Claude div not found. Please refresh page.'); return; }
    el.style.display = 'block';
    el.innerHTML = '<div style="padding:10px;color:var(--dim);font-size:.8rem"><span style="animation:spin .8s linear infinite;display:inline-block;margin-right:6px">⏳</span> Claude AI analyzing exact changes...</div>';

    fetch('ai_analysis.php?change_id=' + id)
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (d.error) {
            var errHtml = '<div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;padding:10px;font-size:.8rem">';
            errHtml += '<div style="color:var(--red);font-weight:700;margin-bottom:4px">❌ ' + d.error + '</div>';
            if (d.error === 'API_KEY_MISSING') {
                errHtml += '<div style="color:var(--dim);font-size:.75rem">Steps:<br>';
                errHtml += '1. <a href="https://console.anthropic.com/api-keys" target="_blank" style="color:var(--blue)">console.anthropic.com</a> par jaao<br>';
                errHtml += '2. API Key create karo<br>';
                errHtml += '3. <b>admin/ai_analysis.php</b> mein line 8 par key daalo<br>';
                errHtml += '<code>api_analysis.php mein API key set karein</code>';
                errHtml += '</div>';
            } else if (d.message) {
                errHtml += '<div style="color:var(--dim);font-size:.75rem">' + d.message + '</div>';
                if (d.help_url) errHtml += '<a href="' + d.help_url + '" target="_blank" style="color:var(--blue);font-size:.75rem">Get API Key →</a>';
            }
            errHtml += '</div>';
            el.innerHTML = errHtml;
            return;
        }

        var impColor = d.important ? '#facc15' : 'var(--dim)';
        var typeColors = {
            'result_out':'#16c079','admit_card':'#60a5fa','answer_key':'#a78bfa',
            'date_changed':'#facc15','vacancy_update':'#f97316','new_content':'#16c079',
            'content_removed':'#ef4444','minor_update':'#6b7280'
        };
        var typeColor = typeColors[d.change_type] || 'var(--dim)';

        var html = '<div style="background:var(--bg);border:1px solid rgba(124,92,252,.3);border-radius:10px;padding:12px;font-size:.82rem">';

        // Header
        html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap">';
        html += '<span style="background:rgba(124,92,252,.15);color:var(--accent);padding:2px 10px;border-radius:20px;font-size:.7rem;font-weight:700"><i class="bi bi-stars me-1"></i>Claude AI</span>';
        if (d.change_type) {
            html += '<span style="background:' + typeColor + '22;color:' + typeColor + ';padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700">' + d.change_type.replace(/_/g,' ').toUpperCase() + '</span>';
        }
        if (d.important) {
            html += '<span style="background:rgba(250,204,21,.15);color:#facc15;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700">⚡ IMPORTANT</span>';
        }
        html += '</div>';

        // Summary
        if (d.summary) {
            html += '<div style="font-weight:700;color:var(--txt);margin-bottom:4px;font-size:.88rem">📌 ' + d.summary + '</div>';
        }
        if (d.summary_hindi) {
            html += '<div style="color:var(--dim);margin-bottom:8px;font-size:.82rem">🇮🇳 ' + d.summary_hindi + '</div>';
        }

        // What ADDED
        if (d.what_added && d.what_added.length) {
            html += '<div style="margin-bottom:8px">';
            html += '<div style="font-size:.72rem;font-weight:700;color:var(--green);margin-bottom:4px;letter-spacing:.04em">✅ NAYA ADD HUA:</div>';
            d.what_added.forEach(function(item) {
                html += '<div style="color:#d1fae5;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #16c079;margin-bottom:2px">• ' + item + '</div>';
            });
            html += '</div>';
        }

        // What REMOVED
        if (d.what_removed && d.what_removed.length) {
            html += '<div style="margin-bottom:8px">';
            html += '<div style="font-size:.72rem;font-weight:700;color:var(--red);margin-bottom:4px;letter-spacing:.04em">❌ HATAYA GAYA:</div>';
            d.what_removed.forEach(function(item) {
                html += '<div style="color:#fca5a5;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #ef4444;margin-bottom:2px">• ' + item + '</div>';
            });
            html += '</div>';
        }

        // What CHANGED (old → new values)
        if (d.what_changed && d.what_changed.length) {
            html += '<div style="margin-bottom:8px">';
            html += '<div style="font-size:.72rem;font-weight:700;color:#f97316;margin-bottom:4px;letter-spacing:.04em">🔄 BADLA GAYA:</div>';
            d.what_changed.forEach(function(item) {
                html += '<div style="color:#fed7aa;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #f97316;margin-bottom:2px">• ' + item + '</div>';
            });
            html += '</div>';
        }

        // Key Info sections
        if (d.key_info) {
            if (d.key_info.new_links && d.key_info.new_links.length) {
                html += '<div style="margin-bottom:8px">';
                html += '<div style="font-size:.72rem;font-weight:700;color:#60a5fa;margin-bottom:4px">🔗 NAYE LINKS:</div>';
                d.key_info.new_links.forEach(function(lnk) {
                    html += '<div style="color:#bfdbfe;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #60a5fa;margin-bottom:2px">• ' + lnk + '</div>';
                });
                html += '</div>';
            }
            if (d.key_info.dates && d.key_info.dates.length) {
                html += '<div style="margin-bottom:8px">';
                html += '<div style="font-size:.72rem;font-weight:700;color:var(--yellow);margin-bottom:4px">📅 IMPORTANT DATES:</div>';
                d.key_info.dates.forEach(function(dt) {
                    html += '<div style="color:#fef08a;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #facc15;margin-bottom:2px">• ' + dt + '</div>';
                });
                html += '</div>';
            }
            if (d.key_info.numbers && d.key_info.numbers.length) {
                html += '<div style="margin-bottom:8px">';
                html += '<div style="font-size:.72rem;font-weight:700;color:#a78bfa;margin-bottom:4px">📊 NUMBERS/POSTS/FEES:</div>';
                d.key_info.numbers.forEach(function(n) {
                    html += '<div style="color:#ddd6fe;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #a78bfa;margin-bottom:2px">• ' + n + '</div>';
                });
                html += '</div>';
            }
            if (d.key_info.notices && d.key_info.notices.length) {
                html += '<div style="margin-bottom:8px">';
                html += '<div style="font-size:.72rem;font-weight:700;color:#f43f5e;margin-bottom:4px">📢 NOTICES:</div>';
                d.key_info.notices.forEach(function(n) {
                    html += '<div style="color:#fda4af;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #f43f5e;margin-bottom:2px">• ' + n + '</div>';
                });
                html += '</div>';
            }
        }

        // Action required
        if (d.action_required) {
            html += '<div style="background:rgba(250,204,21,.12);border:1px solid rgba(250,204,21,.3);border-radius:8px;padding:8px 12px;font-size:.82rem;color:#fef08a;margin-top:4px">';
            html += '<b>⚡ Kya karein:</b> ' + d.action_required;
            html += '</div>';
        }

        html += '</div>';
        el.innerHTML = html;

        // Auto-fill note
        if (note && !note.value && d.summary) {
            note.value = d.summary + (d.summary_hindi ? ' | ' + d.summary_hindi : '');
        }
    })
    .catch(function(err) {
        el.innerHTML = '<div style="color:var(--red);font-size:.78rem;padding:8px">❌ Network error: ' + err + '</div>';
    });
}

function sendTg(id) {
    fetch('send_telegram.php?change_id=' + id)
    .then(function(r){return r.json();})
    .then(function(d){
        if(d.ok) {
            var btn = event.target.closest('button');
            if(btn) { btn.style.background='rgba(22,192,121,.2)'; btn.innerHTML='<i class="bi bi-check2 me-1"></i>Sent!'; }
        } else {
            alert('Telegram error: ' + (d.error||'Check Settings → Telegram'));
        }
    }).catch(function(e){ alert('Error: '+e); });
}

function saveNote(id) {
    var val = document.getElementById('note-' + id).value;
    var fd = new FormData(); fd.append('note', val);
    fetch('changes.php?save_note=' + id, {method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
        var inp = document.getElementById('note-'+id);
        if(d.ok){inp.style.borderColor='#16c079';setTimeout(function(){inp.style.borderColor='';},1500);}
    }).catch(function(){});
}
function getSummary(id) {
    var el = document.getElementById('summ-'+id);
    el.style.display='block';
    el.innerHTML='<span style="opacity:.6">🔍 Analyzing...</span>';
    fetch('changes.php?summarize='+id)
    .then(function(r){return r.json();})
    .then(function(d){
        var html = '';
        // Main findings badges
        if (d.findings && d.findings.length) {
            d.findings.forEach(function(f){
                var bg = f.type==='success'?'rgba(22,192,121,.2)':f.type==='warning'?'rgba(250,204,21,.2)':'rgba(96,165,250,.2)';
                var col = f.type==='success'?'#16c079':f.type==='warning'?'#facc15':'#60a5fa';
                html += '<span style="display:inline-flex;align-items:center;gap:4px;background:'+bg+';color:'+col+';border-radius:6px;padding:3px 10px;font-size:.75rem;font-weight:700;margin:2px">'+f.icon+' '+f.text+'</span> ';
            });
            html += '<br>';
        }
        // Details
        if (d.details && d.details.length) {
            d.details.forEach(function(det){
                html += '<div style="font-size:.73rem;color:var(--dim);padding:1px 0">'+det.icon+' '+det.text+'</div>';
            });
        }
        // Stats
        var stats = [];
        if (d.added_count > 0)   stats.push('<span style="color:#16c079">+'+d.added_count+' sentences added</span>');
        if (d.removed_count > 0) stats.push('<span style="color:#ef4444">-'+d.removed_count+' sentences removed</span>');
        if (stats.length) html += '<div style="font-size:.7rem;margin-top:3px">'+stats.join(' &nbsp;')+'</div>';

        if (!html) html = '<span style="color:var(--dim);font-size:.75rem">✏️ Minor update</span>';
        el.innerHTML = html;

        // Auto-fill note
        var inp = document.getElementById('note-'+id);
        if (inp && !inp.value && d.summary) inp.value = d.summary;
    }).catch(function(e){ el.innerHTML='<span style="color:#ef4444">Error: '+e+'</span>'; });
}

function loadDiff(id){
    var body=document.getElementById('cb-'+id);
    if(!body) return;
    if(body.dataset.loaded==='1') return;
    body.dataset.loaded='1';

    var oEl=document.getElementById('diff-old-'+id);
    var nEl=document.getElementById('diff-new-'+id);

    fetch('changes.php?diff_id='+id)
    .then(function(r){
        if(!r.ok) throw new Error('HTTP '+r.status);
        return r.json();
    })
    .then(function(data){
        if(!oEl||!nEl) return;
        if(data.same){
            oEl.innerHTML='<span style="color:var(--dim);font-style:italic">Content appears identical after normalization</span>';
            nEl.innerHTML='<span style="color:var(--dim);font-style:italic">Content appears identical after normalization</span>';
        } else {
            if(data.old) { oEl.innerHTML=data.old; oEl.classList.remove('diff-col-loading'); }
            if(data.new) { nEl.innerHTML=data.new; nEl.classList.remove('diff-col-loading'); }
        }
    })
    .catch(function(err){
        if(oEl) oEl.innerHTML='<span style="color:#ef4444">Could not load diff: '+err+'</span>';
        if(nEl) nEl.innerHTML='<span style="color:#ef4444">Could not load diff: '+err+'</span>';
    });
}
function loadPreview(id){
    ['old','new'].forEach(function(mode){
        var f=document.getElementById('preview-'+mode+'-'+id);
        if(f&&f.src==='about:blank'&&f.dataset.src) f.src=f.dataset.src;
    });
}
function switchTab(id,tab,el){
    el.closest('.view-tabs').querySelectorAll('.view-tab').forEach(function(t){t.classList.remove('active');});
    el.classList.add('active');
    document.getElementById('panel-text-'+id).classList.toggle('active',tab==='text');
    document.getElementById('panel-preview-'+id).classList.toggle('active',tab==='preview');
    if(tab==='preview') loadPreview(id);
}
function toggleItem(id){
    var body=document.getElementById('cb-'+id);
    var chev=document.getElementById('chev-'+id);
    if(!body) { console.error('cb-'+id+' not found'); return; }
    var open=body.classList.toggle('open');
    if(chev) chev.classList.toggle('open',open);
    if(open) loadDiff(id);
}
function toggleAll(expand){
    document.querySelectorAll('.change-item').forEach(function(item){
        var id=item.id.replace('ci-','');
        document.getElementById('cb-'+id).classList.toggle('open',expand);
        document.getElementById('chev-'+id).classList.toggle('open',expand);
        if(expand) loadDiff(id);
    });
}
function getClaudeAnalysis(id) {
    var el   = document.getElementById('claude-' + id);
    var note = document.getElementById('note-' + id);
    if (!el) { alert('Claude div not found. Please refresh page.'); return; }
    el.style.display = 'block';
    el.innerHTML = '<div style="padding:10px;color:var(--dim);font-size:.8rem"><span style="animation:spin .8s linear infinite;display:inline-block;margin-right:6px">⏳</span> Claude AI analyzing exact changes...</div>';

    fetch('ai_analysis.php?change_id=' + id)
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (d.error) {
            var errHtml = '<div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;padding:10px;font-size:.8rem">';
            errHtml += '<div style="color:var(--red);font-weight:700;margin-bottom:4px">❌ ' + d.error + '</div>';
            if (d.error === 'API_KEY_MISSING') {
                errHtml += '<div style="color:var(--dim);font-size:.75rem">Steps:<br>';
                errHtml += '1. <a href="https://console.anthropic.com/api-keys" target="_blank" style="color:var(--blue)">console.anthropic.com</a> par jaao<br>';
                errHtml += '2. API Key create karo<br>';
                errHtml += '3. <b>admin/ai_analysis.php</b> mein line 8 par key daalo<br>';
                errHtml += '<code>api_analysis.php mein API key set karein</code>';
                errHtml += '</div>';
            } else if (d.message) {
                errHtml += '<div style="color:var(--dim);font-size:.75rem">' + d.message + '</div>';
                if (d.help_url) errHtml += '<a href="' + d.help_url + '" target="_blank" style="color:var(--blue);font-size:.75rem">Get API Key →</a>';
            }
            errHtml += '</div>';
            el.innerHTML = errHtml;
            return;
        }

        var impColor = d.important ? '#facc15' : 'var(--dim)';
        var typeColors = {
            'result_out':'#16c079','admit_card':'#60a5fa','answer_key':'#a78bfa',
            'date_changed':'#facc15','vacancy_update':'#f97316','new_content':'#16c079',
            'content_removed':'#ef4444','minor_update':'#6b7280'
        };
        var typeColor = typeColors[d.change_type] || 'var(--dim)';

        var html = '<div style="background:var(--bg);border:1px solid rgba(124,92,252,.3);border-radius:10px;padding:12px;font-size:.82rem">';

        // Header
        html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap">';
        html += '<span style="background:rgba(124,92,252,.15);color:var(--accent);padding:2px 10px;border-radius:20px;font-size:.7rem;font-weight:700"><i class="bi bi-stars me-1"></i>Claude AI</span>';
        if (d.change_type) {
            html += '<span style="background:' + typeColor + '22;color:' + typeColor + ';padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700">' + d.change_type.replace(/_/g,' ').toUpperCase() + '</span>';
        }
        if (d.important) {
            html += '<span style="background:rgba(250,204,21,.15);color:#facc15;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700">⚡ IMPORTANT</span>';
        }
        html += '</div>';

        // Summary
        if (d.summary) {
            html += '<div style="font-weight:700;color:var(--txt);margin-bottom:4px;font-size:.88rem">📌 ' + d.summary + '</div>';
        }
        if (d.summary_hindi) {
            html += '<div style="color:var(--dim);margin-bottom:8px;font-size:.82rem">🇮🇳 ' + d.summary_hindi + '</div>';
        }

        // What ADDED
        if (d.what_added && d.what_added.length) {
            html += '<div style="margin-bottom:8px">';
            html += '<div style="font-size:.72rem;font-weight:700;color:var(--green);margin-bottom:4px;letter-spacing:.04em">✅ NAYA ADD HUA:</div>';
            d.what_added.forEach(function(item) {
                html += '<div style="color:#d1fae5;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #16c079;margin-bottom:2px">• ' + item + '</div>';
            });
            html += '</div>';
        }

        // What REMOVED
        if (d.what_removed && d.what_removed.length) {
            html += '<div style="margin-bottom:8px">';
            html += '<div style="font-size:.72rem;font-weight:700;color:var(--red);margin-bottom:4px;letter-spacing:.04em">❌ HATAYA GAYA:</div>';
            d.what_removed.forEach(function(item) {
                html += '<div style="color:#fca5a5;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #ef4444;margin-bottom:2px">• ' + item + '</div>';
            });
            html += '</div>';
        }

        // What CHANGED (old → new values)
        if (d.what_changed && d.what_changed.length) {
            html += '<div style="margin-bottom:8px">';
            html += '<div style="font-size:.72rem;font-weight:700;color:#f97316;margin-bottom:4px;letter-spacing:.04em">🔄 BADLA GAYA:</div>';
            d.what_changed.forEach(function(item) {
                html += '<div style="color:#fed7aa;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #f97316;margin-bottom:2px">• ' + item + '</div>';
            });
            html += '</div>';
        }

        // Key Info sections
        if (d.key_info) {
            if (d.key_info.new_links && d.key_info.new_links.length) {
                html += '<div style="margin-bottom:8px">';
                html += '<div style="font-size:.72rem;font-weight:700;color:#60a5fa;margin-bottom:4px">🔗 NAYE LINKS:</div>';
                d.key_info.new_links.forEach(function(lnk) {
                    html += '<div style="color:#bfdbfe;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #60a5fa;margin-bottom:2px">• ' + lnk + '</div>';
                });
                html += '</div>';
            }
            if (d.key_info.dates && d.key_info.dates.length) {
                html += '<div style="margin-bottom:8px">';
                html += '<div style="font-size:.72rem;font-weight:700;color:var(--yellow);margin-bottom:4px">📅 IMPORTANT DATES:</div>';
                d.key_info.dates.forEach(function(dt) {
                    html += '<div style="color:#fef08a;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #facc15;margin-bottom:2px">• ' + dt + '</div>';
                });
                html += '</div>';
            }
            if (d.key_info.numbers && d.key_info.numbers.length) {
                html += '<div style="margin-bottom:8px">';
                html += '<div style="font-size:.72rem;font-weight:700;color:#a78bfa;margin-bottom:4px">📊 NUMBERS/POSTS/FEES:</div>';
                d.key_info.numbers.forEach(function(n) {
                    html += '<div style="color:#ddd6fe;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #a78bfa;margin-bottom:2px">• ' + n + '</div>';
                });
                html += '</div>';
            }
            if (d.key_info.notices && d.key_info.notices.length) {
                html += '<div style="margin-bottom:8px">';
                html += '<div style="font-size:.72rem;font-weight:700;color:#f43f5e;margin-bottom:4px">📢 NOTICES:</div>';
                d.key_info.notices.forEach(function(n) {
                    html += '<div style="color:#fda4af;font-size:.8rem;padding:2px 0 2px 12px;border-left:2px solid #f43f5e;margin-bottom:2px">• ' + n + '</div>';
                });
                html += '</div>';
            }
        }

        // Action required
        if (d.action_required) {
            html += '<div style="background:rgba(250,204,21,.12);border:1px solid rgba(250,204,21,.3);border-radius:8px;padding:8px 12px;font-size:.82rem;color:#fef08a;margin-top:4px">';
            html += '<b>⚡ Kya karein:</b> ' + d.action_required;
            html += '</div>';
        }

        html += '</div>';
        el.innerHTML = html;

        // Auto-fill note
        if (note && !note.value && d.summary) {
            note.value = d.summary + (d.summary_hindi ? ' | ' + d.summary_hindi : '');
        }
    })
    .catch(function(err) {
        el.innerHTML = '<div style="color:var(--red);font-size:.78rem;padding:8px">❌ Network error: ' + err + '</div>';
    });
}

function sendTg(id) {
    fetch('send_telegram.php?change_id=' + id)
    .then(function(r){return r.json();})
    .then(function(d){
        if(d.ok) {
            var btn = event.target.closest('button');
            if(btn) { btn.style.background='rgba(22,192,121,.2)'; btn.innerHTML='<i class="bi bi-check2 me-1"></i>Sent!'; }
        } else {
            alert('Telegram error: ' + (d.error||'Check Settings → Telegram'));
        }
    }).catch(function(e){ alert('Error: '+e); });
}

function saveNote(id) {
    var val = document.getElementById('note-' + id).value;
    var fd = new FormData();
    fd.append('note', val);
    fetch('changes.php?save_note=' + id, {method:'POST', body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
        if(d.ok) {
            var inp = document.getElementById('note-' + id);
            inp.style.borderColor = '#16c079';
            setTimeout(function(){ inp.style.borderColor = ''; }, 1500);
        }
    }).catch(function(){});
}

function getSummary(id) {
    var el = document.getElementById('summ-' + id);
    el.style.display = 'block';
    el.textContent = 'Analyzing...';
    fetch('changes.php?summarize=' + id)
    .then(function(r){return r.json();})
    .then(function(d){
        el.textContent = d.summary || 'Could not analyze';
        var inp = document.getElementById('note-' + id);
        if (inp && !inp.value) inp.value = d.summary;
    }).catch(function(){ el.textContent = 'Error'; });
}

// Auto-expand first item only (faster load)
var firstItem = document.querySelector('.change-item');
if (firstItem) {
    var firstId = firstItem.id.replace('ci-','');
    var fb = document.getElementById('cb-'+firstId);
    var fc = document.getElementById('chev-'+firstId);
    if(fb) fb.classList.add('open');
    if(fc) fc.classList.add('open');
    loadDiff(firstId);
}
</script>

<?php pageFooter(); ?>
