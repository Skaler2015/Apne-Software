<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db_connection();

$pageTitle = 'Users & Devices';
$pageSubtitle = 'Where your visitors come from and what they use';
$activeNav = 'users';

function topRows($pdo, $col, $limit = 10) {
    if (!$pdo) return [];
    try {
        $stmt = $pdo->prepare("SELECT $col as label, COUNT(*) as c FROM tool_views WHERE $col IS NOT NULL AND $col != '' GROUP BY $col ORDER BY c DESC LIMIT $limit");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) { return []; }
}

$countries = topRows($pdo, 'country');
$regions   = topRows($pdo, 'region');
$cities    = topRows($pdo, 'city');
$browsers  = topRows($pdo, 'browser');
$oses      = topRows($pdo, 'os');

include __DIR__ . '/includes/header.php';
?>

<div class="panel-grid-2">
  <div class="panel-card">
    <h2>🌍 Top Countries</h2>
    <?php if (!$countries): ?><div class="empty-state">No location data yet.</div><?php else: ?>
    <table><tr><th>Country</th><th>Visits</th></tr>
      <?php foreach ($countries as $r): ?>
      <tr><td><?= htmlspecialchars($r['label']) ?></td><td><?= number_format($r['c']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

  <div class="panel-card">
    <h2>📍 Top Cities</h2>
    <?php if (!$cities): ?><div class="empty-state">No location data yet.</div><?php else: ?>
    <table><tr><th>City</th><th>Visits</th></tr>
      <?php foreach ($cities as $r): ?>
      <tr><td><?= htmlspecialchars($r['label']) ?></td><td><?= number_format($r['c']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>

<div class="panel-grid-2">
  <div class="panel-card">
    <h2>🏙 Top States / Regions</h2>
    <?php if (!$regions): ?><div class="empty-state">No location data yet.</div><?php else: ?>
    <table><tr><th>State / Region</th><th>Visits</th></tr>
      <?php foreach ($regions as $r): ?>
      <tr><td><?= htmlspecialchars($r['label']) ?></td><td><?= number_format($r['c']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

  <div class="panel-card">
    <h2>🧭 Browsers</h2>
    <canvas id="browserChart" height="160"></canvas>
  </div>
</div>

<div class="panel-card">
  <h2>🖥 Operating Systems</h2>
  <canvas id="osChart" height="90"></canvas>
</div>

<script>
new Chart(document.getElementById('browserChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($browsers, 'label')) ?>,
    datasets: [{ data: <?= json_encode(array_map('intval', array_column($browsers, 'c'))) ?>,
      backgroundColor: ['#7C5CFC','#16C079','#F59E0B','#EF4444','#3B82F6','#EC4899'] }]
  },
  options: { plugins: { legend: { position: 'bottom', labels: { color: '#f1f1f6', boxWidth: 12 } } } }
});

new Chart(document.getElementById('osChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($oses, 'label')) ?>,
    datasets: [{ label: 'Visits', data: <?= json_encode(array_map('intval', array_column($oses, 'c'))) ?>, backgroundColor: '#16C079', borderRadius: 8 }]
  },
  options: {
    indexAxis: 'y',
    plugins: { legend: { display: false } },
    scales: {
      x: { ticks: { color: '#8d8da3' }, grid: { color: '#28283a' } },
      y: { ticks: { color: '#8d8da3' }, grid: { display: false } }
    }
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
