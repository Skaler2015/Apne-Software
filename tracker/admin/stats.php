<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

// Stats data
$totalChanges = $pdo->query("SELECT COUNT(*) FROM changes")->fetchColumn();
$totalPages   = $pdo->query("SELECT COUNT(*) FROM pages")->fetchColumn();
$todayChanges = $pdo->query("SELECT COUNT(*) FROM changes WHERE DATE(detected_at)=CURDATE()")->fetchColumn();
$weekChanges  = $pdo->query("SELECT COUNT(*) FROM changes WHERE detected_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();
$resolved     = $pdo->query("SELECT COUNT(*) FROM changes WHERE resolved=1")->fetchColumn() ?: 0;
$pending      = $pdo->query("SELECT COUNT(*) FROM changes WHERE resolved=0 OR resolved IS NULL")->fetchColumn();

// Changes per day (last 14 days)
$dailyData = $pdo->query("
    SELECT DATE(detected_at) as day, COUNT(*) as cnt
    FROM changes
    WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(detected_at)
    ORDER BY day ASC
")->fetchAll();

// Per website
$siteData = $pdo->query("
    SELECT w.website_name, COUNT(c.id) as cnt,
           SUM(CASE WHEN c.resolved=1 THEN 1 ELSE 0 END) as res
    FROM websites w LEFT JOIN changes c ON w.id=c.website_id
    GROUP BY w.id ORDER BY cnt DESC
")->fetchAll();

// Per change type
$typeData = $pdo->query("
    SELECT change_type, COUNT(*) as cnt
    FROM changes GROUP BY change_type ORDER BY cnt DESC
")->fetchAll();

// Most active pages
$activePages = $pdo->query("
    SELECT p.page_url, COUNT(c.id) as cnt, w.website_name
    FROM pages p
    LEFT JOIN changes c ON p.id=c.page_id
    LEFT JOIN websites w ON p.website_id=w.id
    GROUP BY p.id HAVING cnt > 0
    ORDER BY cnt DESC LIMIT 10
")->fetchAll();

// Scan log
try {
    $scanLog = $pdo->query("SELECT * FROM scan_log ORDER BY ran_at DESC LIMIT 7")->fetchAll();
} catch(Exception $e) { $scanLog = []; }

pageHeader('Stats Dashboard');
?>
<style>
.stat-big { font-size:2.4rem; font-weight:800; line-height:1; }
.chart-bar { background:var(--accent); border-radius:4px 4px 0 0; min-width:18px; transition:.3s; cursor:default; }
.chart-bar:hover { background:#a78bfa; }
.bar-wrap { display:flex; align-items:flex-end; gap:3px; height:80px; }
</style>

<div class="container-fluid py-4 px-4">
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Stats</li>
  </ol>
</nav>

<div class="d-flex align-items-center gap-3 mb-4">
  <div style="background:rgba(96,165,250,.15);border:1px solid rgba(96,165,250,.3);border-radius:12px;padding:10px 14px;font-size:1.4rem">📊</div>
  <h1 class="page-title mb-0">Stats Dashboard</h1>
</div>

<!-- Top stats -->
<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['Total Changes', $totalChanges, 'var(--accent)', 'bi-activity'],
    ['Pending', $pending, 'var(--red)', 'bi-bell-fill'],
    ['Resolved', $resolved, 'var(--green)', 'bi-check-circle-fill'],
    ['Today', $todayChanges, 'var(--yellow)', 'bi-calendar-day'],
    ['This Week', $weekChanges, 'var(--blue)', 'bi-calendar-week'],
    ['Pages Tracked', $totalPages, '#a78bfa', 'bi-file-earmark'],
  ];
  foreach($cards as [$lbl,$val,$col,$ico]):
  ?>
  <div class="col-6 col-md-2">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:18px 14px;transition:.2s" onmouseover="this.style.borderColor='<?=$col?>'" onmouseout="this.style.borderColor=''">
      <i class="bi <?=$ico?>" style="color:<?=$col?>;font-size:1.3rem"></i>
      <div class="stat-big" style="color:<?=$col?>;margin-top:6px"><?=$val?></div>
      <div style="font-size:.72rem;color:var(--dim);margin-top:4px"><?=$lbl?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">

  <!-- Daily chart -->
  <div class="col-md-8">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Changes — Last 14 Days</div>
      <div class="card-body p-4">
        <?php if($dailyData): ?>
        <?php $maxVal = max(array_column($dailyData,'cnt')) ?: 1; ?>
        <div style="display:flex;align-items:flex-end;gap:4px;height:100px;margin-bottom:8px">
          <?php foreach($dailyData as $d):
            $h = round(($d['cnt']/$maxVal)*90);
          ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px">
            <span style="font-size:.6rem;color:var(--dim)"><?=$d['cnt']?></span>
            <div title="<?=$d['day']?>: <?=$d['cnt']?> changes"
                 style="width:100%;background:var(--accent);border-radius:3px 3px 0 0;height:<?=$h?>px;min-height:3px;transition:.3s;cursor:default"
                 onmouseover="this.style.background='#a78bfa'" onmouseout="this.style.background='var(--accent)'"></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:4px">
          <?php foreach($dailyData as $d): ?>
          <div style="flex:1;text-align:center;font-size:.55rem;color:var(--dim)"><?=date('d/m',strtotime($d['day']))?></div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="color:var(--dim);text-align:center;padding:30px">No data yet</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Change types pie-like -->
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart me-2"></i>By Type</div>
      <div class="card-body p-3">
        <?php
        $typeColors = ['CONTENT_CHANGED'=>'var(--red)','TITLE_CHANGED'=>'var(--yellow)','H1_CHANGED'=>'#a78bfa','META_CHANGED'=>'var(--blue)'];
        $typeLabels = ['CONTENT_CHANGED'=>'Content','TITLE_CHANGED'=>'Title','H1_CHANGED'=>'H1','META_CHANGED'=>'Meta'];
        $totalT = array_sum(array_column($typeData,'cnt')) ?: 1;
        foreach($typeData as $t):
          $pct = round($t['cnt']/$totalT*100);
          $col = $typeColors[$t['change_type']] ?? 'var(--dim)';
          $lbl = $typeLabels[$t['change_type']] ?? $t['change_type'];
        ?>
        <div style="margin-bottom:12px">
          <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:4px">
            <span style="color:var(--txt)"><?=$lbl?></span>
            <span style="color:<?=$col?>;font-weight:700"><?=$t['cnt']?> (<?=$pct?>%)</span>
          </div>
          <div style="background:var(--bg3);border-radius:20px;height:6px;overflow:hidden">
            <div style="width:<?=$pct?>%;background:<?=$col?>;height:100%;border-radius:20px;transition:width .5s"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(!$typeData): ?><div style="color:var(--dim);text-align:center;padding:20px">No data</div><?php endif; ?>
      </div>
    </div>
  </div>

</div>

<div class="row g-4">
  <!-- Per website -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-globe me-2"></i>Changes by Website</div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr><th>Website</th><th class="text-center">Total</th><th class="text-center">Resolved</th><th class="text-center">Pending</th></tr></thead>
          <tbody>
          <?php foreach($siteData as $s):
            $pend = $s['cnt'] - ($s['res']??0);
          ?>
          <tr>
            <td style="font-weight:600;font-size:.85rem"><?=htmlspecialchars($s['website_name']??'—')?></td>
            <td class="text-center" style="font-weight:700;color:var(--accent)"><?=$s['cnt']?></td>
            <td class="text-center" style="color:var(--green)"><?=$s['res']??0?></td>
            <td class="text-center" style="color:var(--red)"><?=$pend?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$siteData): ?><tr><td colspan="4" class="text-center" style="color:var(--dim);padding:20px">No data</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Most active pages -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-fire me-2" style="color:var(--red)"></i>Most Changed Pages</div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr><th>Page</th><th>Site</th><th class="text-center">Changes</th></tr></thead>
          <tbody>
          <?php foreach($activePages as $p):
            $path = parse_url($p['page_url']??'', PHP_URL_PATH) ?: '/';
          ?>
          <tr>
            <td style="font-size:.78rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <a href="<?=htmlspecialchars($p['page_url']??'')?>" target="_blank"
                 style="color:var(--blue);text-decoration:underline">
                <?=htmlspecialchars($path)?>
              </a>
            </td>
            <td style="font-size:.75rem;color:var(--dim)"><?=htmlspecialchars($p['website_name']??'')?></td>
            <td class="text-center"><span style="font-weight:700;color:var(--red)"><?=$p['cnt']?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$activePages): ?><tr><td colspan="3" class="text-center" style="color:var(--dim);padding:20px">No data</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

</div>
<?php pageFooter(); ?>
