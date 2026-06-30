<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

// Merge duplicates action
if (isset($_GET['merge_duplicates'])) {
    $merged = 0;
    try {
        // Find page_ids with multiple changes same day
        $dups = $pdo->query("
            SELECT page_id, DATE(detected_at) as day, COUNT(*) as cnt
            FROM changes
            WHERE resolved=0 OR resolved IS NULL
            GROUP BY page_id, DATE(detected_at)
            HAVING cnt > 1
        ")->fetchAll();

        foreach ($dups as $d) {
            // Keep the latest, delete rest
            $latest = $pdo->prepare("SELECT id FROM changes WHERE page_id=? AND DATE(detected_at)=? ORDER BY id DESC LIMIT 1");
            $latest->execute([$d['page_id'], $d['day']]);
            $keepId = $latest->fetchColumn();
            if ($keepId) {
                $del = $pdo->prepare("DELETE FROM changes WHERE page_id=? AND DATE(detected_at)=? AND id!=?");
                $del->execute([$d['page_id'], $d['day'], $keepId]);
                $merged += $del->rowCount();
            }
        }
        flash("✅ {$merged} duplicate changes merged!");
    } catch(Exception $e) { flash("Error: " . $e->getMessage(), 'danger'); }
    header('Location: analytics.php'); exit;
}

// Stats
try {
    $totalChanges = $pdo->query("SELECT COUNT(*) FROM changes")->fetchColumn();
    $todayChanges = $pdo->query("SELECT COUNT(*) FROM changes WHERE DATE(detected_at)=CURDATE()")->fetchColumn();

    // By category
    $byCat = $pdo->query("
        SELECT COALESCE(category,'Unknown') as cat, COUNT(*) as cnt
        FROM changes GROUP BY category ORDER BY cnt DESC
    ")->fetchAll();

    // By website
    $bySite = $pdo->query("
        SELECT w.website_name, COUNT(c.id) as cnt,
               AVG(c.confidence) as avg_conf,
               MAX(c.detected_at) as last_change
        FROM websites w LEFT JOIN changes c ON w.id=c.website_id
        GROUP BY w.id ORDER BY cnt DESC
    ")->fetchAll();

    // Last 14 days heatmap
    $heatmap = $pdo->query("
        SELECT DATE(detected_at) as day, COUNT(*) as cnt
        FROM changes
        WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        GROUP BY DATE(detected_at)
        ORDER BY day ASC
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Top changed pages
    $topPages = $pdo->query("
        SELECT p.page_url, w.website_name, COUNT(c.id) as cnt,
               MAX(c.detected_at) as last_change,
               MAX(c.category) as last_category
        FROM pages p
        LEFT JOIN changes c ON p.id=c.page_id
        LEFT JOIN websites w ON p.website_id=w.id
        GROUP BY p.id HAVING cnt > 0
        ORDER BY cnt DESC LIMIT 15
    ")->fetchAll();

    // Pending duplicates count
    $dupCount = $pdo->query("
        SELECT COUNT(*) FROM (
            SELECT page_id, DATE(detected_at), COUNT(*) as cnt
            FROM changes WHERE resolved=0 OR resolved IS NULL
            GROUP BY page_id, DATE(detected_at)
            HAVING cnt > 1
        ) t
    ")->fetchColumn();

    // Confidence distribution
    $confDist = $pdo->query("
        SELECT
            SUM(CASE WHEN confidence >= 80 THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN confidence >= 50 AND confidence < 80 THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN confidence < 50 THEN 1 ELSE 0 END) as low
        FROM changes
    ")->fetch();

} catch(Exception $e) {
    $totalChanges=$todayChanges=0; $byCat=$bySite=$topPages=[];
    $heatmap=[]; $dupCount=0; $confDist=['high'=>0,'medium'=>0,'low'=>0];
}

pageHeader('Analytics');
?>
<div class="container-fluid py-4 px-4">
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Analytics</li>
  </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h1 class="page-title mb-0">📊 Change Analytics</h1>
  <div class="d-flex gap-2">
    <?php if($dupCount > 0): ?>
    <a href="?merge_duplicates=1" class="btn btn-sm btn-warning"
       onclick="return confirm('Merge <?=$dupCount?> duplicate changes?')">
      <i class="bi bi-layers me-1"></i>Merge <?=$dupCount?> Duplicates
    </a>
    <?php endif; ?>
    <a href="../api/changes.php?date=<?=date('Y-m-d')?>" target="_blank" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-braces me-1"></i>API
    </a>
  </div>
</div>

<?php showFlash(); ?>

<!-- Top stats -->
<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['Total Changes',$totalChanges,'var(--accent)','bi-activity'],
    ['Today',$todayChanges,'var(--red)','bi-calendar-day'],
    ['High Confidence',$confDist['high']??0,'var(--green)','bi-check-circle'],
    ['Duplicates',$dupCount,'var(--yellow)','bi-layers'],
  ];
  foreach($cards as [$l,$v,$col,$ico]):
  ?>
  <div class="col-6 col-md-3">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center">
      <i class="bi <?=$ico?>" style="color:<?=$col?>;font-size:1.3rem"></i>
      <div style="font-size:2rem;font-weight:800;color:<?=$col?>;margin-top:4px"><?=$v?></div>
      <div style="font-size:.72rem;color:var(--dim)"><?=$l?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">

  <!-- 14-day heatmap -->
  <div class="col-md-8">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-calendar-week me-2"></i>Last 14 Days</div>
      <div class="card-body p-4">
        <?php
        $maxVal = $heatmap ? max($heatmap) : 1;
        $days = [];
        for ($i=13;$i>=0;$i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $days[$d] = $heatmap[$d] ?? 0;
        }
        ?>
        <div style="display:flex;align-items:flex-end;gap:4px;height:100px;margin-bottom:8px">
          <?php foreach($days as $day=>$cnt):
            $h = $maxVal>0 ? round(($cnt/$maxVal)*90) : 0;
            $isToday = $day===date('Y-m-d');
          ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px">
            <span style="font-size:.6rem;color:var(--dim)"><?=$cnt?:''?></span>
            <div title="<?=$day?>: <?=$cnt?> changes"
                 style="width:100%;background:<?=$isToday?'var(--accent)':'rgba(124,92,252,.5)'?>;border-radius:3px 3px 0 0;height:<?=max(3,$h)?>px;min-height:3px;cursor:default"></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:4px">
          <?php foreach(array_keys($days) as $day): ?>
          <div style="flex:1;text-align:center;font-size:.55rem;color:var(--dim)"><?=date('d/m',strtotime($day))?></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- By Category -->
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-tag me-2"></i>By Category</div>
      <div class="card-body p-3">
        <?php
        $catColors2 = ['Result'=>'#16c079','Admit Card'=>'#60a5fa','Answer Key'=>'#a78bfa',
                       'Cut Off'=>'#f97316','Recruitment'=>'#facc15','Notification'=>'#ef4444','Update'=>'#6b7280'];
        $totalCat   = array_sum(array_column($byCat,'cnt')) ?: 1;
        foreach ($byCat as $cat):
          $pct   = round($cat['cnt']/$totalCat*100);
          $color = $catColors2[$cat['cat']] ?? 'var(--dim)';
        ?>
        <div style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:3px">
            <span style="color:var(--txt)"><?=$cat['cat']?></span>
            <span style="color:<?=$color?>;font-weight:700"><?=$cat['cnt']?> (<?=$pct?>%)</span>
          </div>
          <div style="background:var(--bg3);border-radius:20px;height:6px;overflow:hidden">
            <div style="width:<?=$pct?>%;background:<?=$color?>;height:100%;border-radius:20px"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(!$byCat): ?><div style="color:var(--dim);text-align:center;padding:20px">No data</div><?php endif; ?>
      </div>
    </div>
  </div>

</div>

<div class="row g-4">

  <!-- Per website -->
  <div class="col-md-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-globe me-2"></i>By Website</div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr><th>Website</th><th class="text-center">Changes</th><th class="text-center">Avg Confidence</th><th>Last Change</th></tr></thead>
          <tbody>
          <?php foreach($bySite as $s): ?>
          <tr>
            <td style="font-weight:600;font-size:.85rem"><?=htmlspecialchars($s['website_name']??'—')?></td>
            <td class="text-center" style="font-weight:700;color:var(--accent)"><?=$s['cnt']?></td>
            <td class="text-center">
              <?php $conf=(int)($s['avg_conf']??0); $cc=$conf>=70?'var(--green)':($conf>=50?'var(--yellow)':'var(--red)'); ?>
              <span style="color:<?=$cc?>;font-weight:700"><?=$conf?>%</span>
            </td>
            <td style="font-size:.75rem;color:var(--dim)"><?=$s['last_change']?date('d M',strtotime($s['last_change'])):'—'?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Top changed pages -->
  <div class="col-md-7">
    <div class="card">
      <div class="card-header"><i class="bi bi-fire me-2" style="color:var(--red)"></i>Most Changed Pages</div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr><th>Page</th><th>Site</th><th>Category</th><th class="text-center">Changes</th><th>Last</th></tr></thead>
          <tbody>
          <?php foreach($topPages as $p):
            $path = parse_url($p['page_url']??'',PHP_URL_PATH)?:'/';
            $catC = $catColors2[$p['last_category']??''] ?? 'var(--dim)';
          ?>
          <tr>
            <td style="font-size:.78rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <a href="<?=htmlspecialchars($p['page_url']??'')?>" target="_blank" style="color:var(--blue);text-decoration:underline"><?=htmlspecialchars($path)?></a>
            </td>
            <td style="font-size:.75rem;color:var(--dim)"><?=htmlspecialchars($p['website_name']??'')?></td>
            <td><span style="font-size:.68rem;color:<?=$catC?>;font-weight:700"><?=htmlspecialchars($p['last_category']??'—')?></span></td>
            <td class="text-center"><span style="font-weight:700;color:var(--red)"><?=$p['cnt']?></span></td>
            <td style="font-size:.72rem;color:var(--dim)"><?=$p['last_change']?date('d M',strtotime($p['last_change'])):'—'?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$topPages): ?><tr><td colspan="5" style="text-align:center;color:var(--dim);padding:20px">No data yet</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- API Docs -->
<div class="card mt-4">
  <div class="card-header"><i class="bi bi-braces me-2"></i>API Endpoint</div>
  <div class="card-body p-3">
    <p style="font-size:.85rem;color:var(--dim)">Tracker data ko kisi bhi tool se access karo:</p>
    <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:12px;font-family:monospace;font-size:.8rem">
      <div><span style="color:var(--yellow)">GET</span> <span style="color:var(--blue)">https://apnesoftware.com/tracker/api/changes.php</span></div>
      <div style="margin-top:8px;color:var(--dim)">Parameters:</div>
      <div><span style="color:var(--accent)">?date=</span>2026-06-25 <span style="color:var(--dim)">— specific date (default: today)</span></div>
      <div><span style="color:var(--accent)">?type=</span>result <span style="color:var(--dim)">— result | admit | answer_key | cutoff | notification</span></div>
      <div><span style="color:var(--accent)">?limit=</span>20 <span style="color:var(--dim)">— max 100</span></div>
    </div>
    <div class="mt-2 d-flex gap-2">
      <a href="../api/changes.php" target="_blank" class="btn btn-sm btn-outline-secondary">Today's All</a>
      <a href="../api/changes.php?type=result" target="_blank" class="btn btn-sm btn-outline-secondary">Results Only</a>
      <a href="../api/changes.php?type=admit" target="_blank" class="btn btn-sm btn-outline-secondary">Admit Cards</a>
    </div>
  </div>
</div>

</div>
<?php pageFooter(); ?>
