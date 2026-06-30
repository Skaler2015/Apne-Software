<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db_connection();

$pageTitle = 'Dashboard';
$pageSubtitle = 'Overview of all activity across ApneSoftware.com';
$activeNav = 'dashboard';

function scalar($pdo, $sql, $params = []) {
    if (!$pdo) return 0;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

$totalVisitors  = scalar($pdo, "SELECT COUNT(DISTINCT ip_address) FROM tool_views");
$totalRuns      = scalar($pdo, "SELECT COALESCE(SUM(total_runs),0) FROM tools");
$todayVisitors  = scalar($pdo, "SELECT COUNT(DISTINCT ip_address) FROM tool_views WHERE view_date = CURDATE()");
$todayRuns      = scalar($pdo, "SELECT COUNT(*) FROM tool_runs WHERE run_date = CURDATE()");
$weeklyUsage    = scalar($pdo, "SELECT COALESCE(SUM(total_views+total_runs),0) FROM daily_stats WHERE stat_date >= CURDATE() - INTERVAL 7 DAY");
$monthlyUsage   = scalar($pdo, "SELECT COALESCE(SUM(total_views+total_runs),0) FROM daily_stats WHERE stat_date >= CURDATE() - INTERVAL 30 DAY");
$yearlyUsage    = scalar($pdo, "SELECT COALESCE(SUM(total_views+total_runs),0) FROM daily_stats WHERE stat_date >= CURDATE() - INTERVAL 365 DAY");
$uniqueUsers30d = scalar($pdo, "SELECT COUNT(DISTINCT ip_address) FROM tool_views WHERE view_date >= CURDATE() - INTERVAL 30 DAY");
$mobileUsers    = scalar($pdo, "SELECT COUNT(DISTINCT ip_address) FROM tool_views WHERE device_type='mobile'");
$desktopUsers   = scalar($pdo, "SELECT COUNT(DISTINCT ip_address) FROM tool_views WHERE device_type='desktop'");

// Last 30 days for the line chart
$dailyRows = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT stat_date, total_views, total_runs FROM daily_stats WHERE stat_date >= CURDATE() - INTERVAL 30 DAY ORDER BY stat_date ASC");
        $dailyRows = $stmt->fetchAll();
    } catch (Exception $e) {}
}
$chartLabels = array_column($dailyRows, 'stat_date');
$chartViews  = array_map('intval', array_column($dailyRows, 'total_views'));
$chartRuns   = array_map('intval', array_column($dailyRows, 'total_runs'));

// Device breakdown (all-time)
$deviceCounts = ['desktop' => 0, 'mobile' => 0, 'tablet' => 0, 'other' => 0];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT device_type, COUNT(*) c FROM tool_views GROUP BY device_type");
        foreach ($stmt->fetchAll() as $row) {
            $deviceCounts[$row['device_type']] = (int) $row['c'];
        }
    } catch (Exception $e) {}
}

include __DIR__ . '/includes/header.php';
?>

<?php if (!$pdo): ?>
  <div class="panel-card" style="border-color:#F59E0B">
    ⚠️ Could not connect to the database. Check <code>backend/config.php</code> for the correct host / database name / username / password.
  </div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat-card"><div class="icon">👥</div><div class="num"><?= number_format($totalVisitors) ?></div><div class="lbl">Total Visitors (all-time)</div></div>
  <div class="stat-card"><div class="icon">🔄</div><div class="num"><?= number_format($totalRuns) ?></div><div class="lbl">Total Tool Runs</div></div>
  <div class="stat-card"><div class="icon">📅</div><div class="num"><?= number_format($todayVisitors) ?></div><div class="lbl">Today's Visitors</div></div>
  <div class="stat-card"><div class="icon">📅</div><div class="num"><?= number_format($todayRuns) ?></div><div class="lbl">Today's Tool Runs</div></div>
  <div class="stat-card"><div class="icon">📈</div><div class="num"><?= number_format($weeklyUsage) ?></div><div class="lbl">Last 7 Days Usage</div></div>
  <div class="stat-card"><div class="icon">📈</div><div class="num"><?= number_format($monthlyUsage) ?></div><div class="lbl">Last 30 Days Usage</div></div>
  <div class="stat-card"><div class="icon">📈</div><div class="num"><?= number_format($yearlyUsage) ?></div><div class="lbl">Last 365 Days Usage</div></div>
  <div class="stat-card"><div class="icon">🌍</div><div class="num"><?= number_format($uniqueUsers30d) ?></div><div class="lbl">Unique Users (30d)</div></div>
  <div class="stat-card"><div class="icon">📱</div><div class="num"><?= number_format($mobileUsers) ?></div><div class="lbl">Mobile Users</div></div>
  <div class="stat-card"><div class="icon">💻</div><div class="num"><?= number_format($desktopUsers) ?></div><div class="lbl">Desktop Users</div></div>
</div>

<div class="panel-grid-2">
  <div class="panel-card">
    <h2>📈 Views & Runs — Last 30 Days</h2>
    <canvas id="dailyChart" height="180"></canvas>
  </div>
  <div class="panel-card">
    <h2>📱 Device Breakdown</h2>
    <canvas id="deviceChart" height="180"></canvas>
  </div>
</div>

<script>
const dailyCtx = document.getElementById('dailyChart');
new Chart(dailyCtx, {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [
      { label: 'Views', data: <?= json_encode($chartViews) ?>, borderColor: '#7C5CFC', backgroundColor: 'rgba(124,92,252,.15)', fill: true, tension: .3 },
      { label: 'Runs', data: <?= json_encode($chartRuns) ?>, borderColor: '#16C079', backgroundColor: 'rgba(22,192,121,.12)', fill: true, tension: .3 }
    ]
  },
  options: {
    plugins: { legend: { labels: { color: '#f1f1f6' } } },
    scales: {
      x: { ticks: { color: '#8d8da3' }, grid: { color: '#28283a' } },
      y: { ticks: { color: '#8d8da3' }, grid: { color: '#28283a' } }
    }
  }
});

const deviceCtx = document.getElementById('deviceChart');
new Chart(deviceCtx, {
  type: 'doughnut',
  data: {
    labels: ['Desktop', 'Mobile', 'Tablet', 'Other'],
    datasets: [{
      data: [<?= $deviceCounts['desktop'] ?>, <?= $deviceCounts['mobile'] ?>, <?= $deviceCounts['tablet'] ?>, <?= $deviceCounts['other'] ?>],
      backgroundColor: ['#7C5CFC', '#16C079', '#F59E0B', '#8d8da3']
    }]
  },
  options: { plugins: { legend: { position: 'bottom', labels: { color: '#f1f1f6' } } } }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
