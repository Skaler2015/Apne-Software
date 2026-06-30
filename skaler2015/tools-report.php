<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db_connection();

$pageTitle = 'Tools Report';
$pageSubtitle = 'Views and runs for every tool — sortable and searchable';
$activeNav = 'tools';

$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'runs';
$range = $_GET['range'] ?? '30';

$rangeMap = ['7' => 7, '30' => 30, '90' => 90, 'all' => null];
$days = $rangeMap[$range] ?? 30;

$sortMap = [
    'runs' => 't.total_runs',
    'views' => 't.total_views',
    'name' => 't.tool_name',
    'category' => 't.category',
];
$sortCol = $sortMap[$sort] ?? 't.total_runs';

$rows = [];
if ($pdo) {
    try {
        if ($days === null) {
            $sql = "SELECT t.id, t.tool_slug, t.tool_name, t.category, t.icon, t.total_views, t.total_runs
                    FROM tools t WHERE t.tool_name LIKE :q ORDER BY $sortCol DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['q' => "%$search%"]);
        } else {
            $sql = "SELECT t.id, t.tool_slug, t.tool_name, t.category, t.icon,
                       (SELECT COUNT(*) FROM tool_views v WHERE v.tool_id = t.id AND v.view_date >= CURDATE() - INTERVAL :days DAY) as total_views,
                       (SELECT COUNT(*) FROM tool_runs r WHERE r.tool_id = t.id AND r.run_date >= CURDATE() - INTERVAL :days2 DAY) as total_runs
                    FROM tools t WHERE t.tool_name LIKE :q ORDER BY $sortCol DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['q' => "%$search%", 'days' => $days, 'days2' => $days]);
        }
        $rows = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('tools.php query failed: ' . $e->getMessage());
    }
}

include __DIR__ . '/includes/header.php';
?>

<form class="filter-row" method="GET">
  <input type="text" name="q" placeholder="🔍 Search tool name..." value="<?= htmlspecialchars($search) ?>">
  <select name="range" onchange="this.form.submit()">
    <option value="7" <?= $range==='7'?'selected':'' ?>>Last 7 Days</option>
    <option value="30" <?= $range==='30'?'selected':'' ?>>Last 30 Days</option>
    <option value="90" <?= $range==='90'?'selected':'' ?>>Last 90 Days</option>
    <option value="all" <?= $range==='all'?'selected':'' ?>>All Time</option>
  </select>
  <select name="sort" onchange="this.form.submit()">
    <option value="runs" <?= $sort==='runs'?'selected':'' ?>>Sort by Runs</option>
    <option value="views" <?= $sort==='views'?'selected':'' ?>>Sort by Views</option>
    <option value="name" <?= $sort==='name'?'selected':'' ?>>Sort by Name</option>
    <option value="category" <?= $sort==='category'?'selected':'' ?>>Sort by Category</option>
  </select>
  <button class="btn" type="submit">Apply</button>
  <a class="btn secondary" href="export.php?type=tools&range=<?= urlencode($range) ?>">⬇️ Export CSV</a>
</form>

<div class="panel-card">
  <h2>🛠 All Tools (<?= count($rows) ?>)</h2>
  <?php if (!$rows): ?>
    <div class="empty-state">No data yet — once visitors start using tools, they'll show up here.</div>
  <?php else: ?>
  <table>
    <tr><th>Tool</th><th>Category</th><th>Views</th><th>Runs</th><th>Conversion</th></tr>
    <?php foreach ($rows as $r):
      $conv = $r['total_views'] > 0 ? round($r['total_runs'] / $r['total_views'] * 100, 1) : 0;
    ?>
    <tr>
      <td><?= htmlspecialchars($r['icon']) ?> <?= htmlspecialchars($r['tool_name']) ?></td>
      <td><span class="badge gray"><?= htmlspecialchars($r['category']) ?></span></td>
      <td><?= number_format($r['total_views']) ?></td>
      <td><strong><?= number_format($r['total_runs']) ?></strong></td>
      <td><span class="badge <?= $conv >= 30 ? 'green' : 'purple' ?>"><?= $conv ?>%</span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
