<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db_connection();

$pageTitle = 'Categories';
$pageSubtitle = 'Performance broken down by tool category';
$activeNav = 'categories';

$rows = [];
if ($pdo) {
    try {
        $stmt = $pdo->query(
            "SELECT category,
                    COUNT(*) as tool_count,
                    COALESCE(SUM(total_views),0) as total_views,
                    COALESCE(SUM(total_runs),0) as total_runs
             FROM tools GROUP BY category ORDER BY total_runs DESC"
        );
        $rows = $stmt->fetchAll();

        // top tool per category
        foreach ($rows as &$row) {
            $stmt2 = $pdo->prepare("SELECT tool_name, icon FROM tools WHERE category = ? ORDER BY total_runs DESC LIMIT 1");
            $stmt2->execute([$row['category']]);
            $top = $stmt2->fetch();
            $row['top_tool'] = $top ? $top['icon'] . ' ' . $top['tool_name'] : '—';
        }
        unset($row);
    } catch (Exception $e) {
        error_log('categories.php query failed: ' . $e->getMessage());
    }
}

$catIcons = ['pdf' => '📄', 'image' => '🖼️', 'text' => '📝', 'calculator' => '🧮', 'developer' => '🛠️'];

include __DIR__ . '/includes/header.php';
?>

<div class="panel-card">
  <h2>📁 Category Performance</h2>
  <?php if (!$rows): ?>
    <div class="empty-state">No data yet.</div>
  <?php else: ?>
  <table>
    <tr><th>Category</th><th>Tools</th><th>Views</th><th>Runs</th><th>Top Tool</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= $catIcons[$r['category']] ?? '📁' ?> <?= htmlspecialchars(ucfirst($r['category'])) ?></td>
      <td><?= number_format($r['tool_count']) ?></td>
      <td><?= number_format($r['total_views']) ?></td>
      <td><strong><?= number_format($r['total_runs']) ?></strong></td>
      <td><?= htmlspecialchars($r['top_tool']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<div class="panel-card">
  <h2>📊 Runs by Category</h2>
  <canvas id="catChart" height="100"></canvas>
</div>

<script>
new Chart(document.getElementById('catChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($rows, 'category')) ?>,
    datasets: [{ label: 'Runs', data: <?= json_encode(array_map('intval', array_column($rows, 'total_runs'))) ?>, backgroundColor: '#7C5CFC', borderRadius: 8 }]
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
