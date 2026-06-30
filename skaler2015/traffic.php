<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db_connection();

$pageTitle = 'Traffic Sources';
$pageSubtitle = 'Where your visitors are coming from';
$activeNav = 'traffic';

$sources = [];
$landingPages = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT referrer_source, COUNT(*) c FROM tool_views GROUP BY referrer_source ORDER BY c DESC");
        $sources = $stmt->fetchAll();

        $stmt2 = $pdo->query(
            "SELECT t.tool_name, t.icon, COUNT(*) c FROM tool_views v
             JOIN tools t ON t.id = v.tool_id
             GROUP BY v.tool_id ORDER BY c DESC LIMIT 10"
        );
        $landingPages = $stmt2->fetchAll();
    } catch (Exception $e) {
        error_log('traffic.php query failed: ' . $e->getMessage());
    }
}

$sourceLabels = ['google' => '🔍 Google', 'bing' => '🔍 Bing', 'yahoo' => '🔍 Yahoo', 'duckduckgo' => '🔍 DuckDuckGo',
    'social' => '📱 Social Media', 'direct' => '⌨️ Direct', 'internal' => '🔁 Internal', 'referral' => '🔗 Other Referral'];

include __DIR__ . '/includes/header.php';
?>

<div class="panel-grid-2">
  <div class="panel-card">
    <h2>🔗 Traffic Sources</h2>
    <?php if (!$sources): ?><div class="empty-state">No traffic data yet.</div><?php else: ?>
    <table><tr><th>Source</th><th>Visits</th></tr>
      <?php foreach ($sources as $r): ?>
      <tr><td><?= $sourceLabels[$r['referrer_source']] ?? htmlspecialchars($r['referrer_source']) ?></td><td><?= number_format($r['c']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

  <div class="panel-card">
    <h2>🚪 Top Entry Pages (most-viewed tools)</h2>
    <?php if (!$landingPages): ?><div class="empty-state">No data yet.</div><?php else: ?>
    <table><tr><th>Tool</th><th>Entry Visits</th></tr>
      <?php foreach ($landingPages as $r): ?>
      <tr><td><?= htmlspecialchars($r['icon']) ?> <?= htmlspecialchars($r['tool_name']) ?></td><td><?= number_format($r['c']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>

<div class="panel-card">
  <h2>📊 Source Breakdown</h2>
  <canvas id="srcChart" height="100"></canvas>
</div>

<script>
new Chart(document.getElementById('srcChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map(fn($r)=>$sourceLabels[$r['referrer_source']] ?? $r['referrer_source'], $sources)) ?>,
    datasets: [{ label: 'Visits', data: <?= json_encode(array_map('intval', array_column($sources, 'c'))) ?>, backgroundColor: '#7C5CFC', borderRadius: 8 }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      x: { ticks: { color: '#8d8da3' }, grid: { display: false } },
      y: { ticks: { color: '#8d8da3' }, grid: { color: '#28283a' } }
    }
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
