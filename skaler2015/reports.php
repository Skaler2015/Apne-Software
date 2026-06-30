<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = get_db_connection();

$pageTitle = 'Top Reports';
$pageSubtitle = 'Rankings and trends across all tools';
$activeNav = 'reports';

function fetchAllSafe($pdo, $sql) {
    if (!$pdo) return [];
    try { return $pdo->query($sql)->fetchAll(); } catch (Exception $e) { return []; }
}

$topUsed   = fetchAllSafe($pdo, "SELECT tool_name, icon, total_runs FROM tools ORDER BY total_runs DESC LIMIT 10");
$topViewed = fetchAllSafe($pdo, "SELECT tool_name, icon, total_views FROM tools ORDER BY total_views DESC LIMIT 10");
$leastUsed = fetchAllSafe($pdo, "SELECT tool_name, icon, total_runs FROM tools ORDER BY total_runs ASC LIMIT 10");

// Trending / fastest growing = compare runs in last 7 days vs the 7 days before that
$trending = fetchAllSafe($pdo, "
  SELECT t.tool_name, t.icon,
    (SELECT COUNT(*) FROM tool_runs r WHERE r.tool_id=t.id AND r.run_date >= CURDATE()-INTERVAL 7 DAY) as recent,
    (SELECT COUNT(*) FROM tool_runs r WHERE r.tool_id=t.id AND r.run_date >= CURDATE()-INTERVAL 14 DAY AND r.run_date < CURDATE()-INTERVAL 7 DAY) as previous
  FROM tools t
  HAVING recent > 0
  ORDER BY (recent - previous) DESC LIMIT 10
");

$recentlyPopular = fetchAllSafe($pdo, "
  SELECT t.tool_name, t.icon, COUNT(*) as c
  FROM tool_runs r JOIN tools t ON t.id = r.tool_id
  WHERE r.run_date >= CURDATE() - INTERVAL 3 DAY
  GROUP BY r.tool_id ORDER BY c DESC LIMIT 10
");

include __DIR__ . '/includes/header.php';
?>

<div class="filter-row">
  <a class="btn secondary" href="export.php?type=tools&range=all">⬇️ Export All Tools (CSV)</a>
  <a class="btn secondary" href="export.php?type=daily&range=90">⬇️ Export Daily Stats (CSV)</a>
</div>

<div class="panel-grid-2">
  <div class="panel-card">
    <h2>🏆 Top 10 Most Used</h2>
    <?php if (!$topUsed): ?><div class="empty-state">No data yet.</div><?php else: ?>
    <table><tr><th>#</th><th>Tool</th><th>Runs</th></tr>
      <?php foreach ($topUsed as $i => $r): ?>
      <tr><td><?= $i+1 ?></td><td><?= htmlspecialchars($r['icon']) ?> <?= htmlspecialchars($r['tool_name']) ?></td><td><strong><?= number_format($r['total_runs']) ?></strong></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

  <div class="panel-card">
    <h2>👁 Top 10 Most Viewed</h2>
    <?php if (!$topViewed): ?><div class="empty-state">No data yet.</div><?php else: ?>
    <table><tr><th>#</th><th>Tool</th><th>Views</th></tr>
      <?php foreach ($topViewed as $i => $r): ?>
      <tr><td><?= $i+1 ?></td><td><?= htmlspecialchars($r['icon']) ?> <?= htmlspecialchars($r['tool_name']) ?></td><td><strong><?= number_format($r['total_views']) ?></strong></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>

<div class="panel-grid-2">
  <div class="panel-card">
    <h2>🔥 Fastest Growing (last 7 vs previous 7 days)</h2>
    <?php if (!$trending): ?><div class="empty-state">Not enough data yet — check back after a couple weeks of traffic.</div><?php else: ?>
    <table><tr><th>Tool</th><th>This Week</th><th>Growth</th></tr>
      <?php foreach ($trending as $r):
        $growth = $r['previous'] > 0 ? round((($r['recent']-$r['previous'])/$r['previous'])*100) : ($r['recent']>0 ? 100 : 0);
      ?>
      <tr><td><?= htmlspecialchars($r['icon']) ?> <?= htmlspecialchars($r['tool_name']) ?></td><td><?= number_format($r['recent']) ?></td>
        <td><span class="badge <?= $growth>=0?'green':'gray' ?>"><?= $growth>=0?'+':'' ?><?= $growth ?>%</span></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

  <div class="panel-card">
    <h2>✨ Recently Popular (last 3 days)</h2>
    <?php if (!$recentlyPopular): ?><div class="empty-state">No data yet.</div><?php else: ?>
    <table><tr><th>Tool</th><th>Runs</th></tr>
      <?php foreach ($recentlyPopular as $r): ?>
      <tr><td><?= htmlspecialchars($r['icon']) ?> <?= htmlspecialchars($r['tool_name']) ?></td><td><?= number_format($r['c']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>

<div class="panel-card">
  <h2>📉 Least Used Tools (might need promotion or improvement)</h2>
  <?php if (!$leastUsed): ?><div class="empty-state">No data yet.</div><?php else: ?>
  <table><tr><th>Tool</th><th>Runs</th></tr>
    <?php foreach ($leastUsed as $r): ?>
    <tr><td><?= htmlspecialchars($r['icon']) ?> <?= htmlspecialchars($r['tool_name']) ?></td><td><?= number_format($r['total_runs']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
