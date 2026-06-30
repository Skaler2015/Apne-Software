<?php
// Expects $pageTitle, $pageSubtitle, $activeNav to be set before including this file
$navItems = [
    'manage'     => ['manage-tools.php', '🛠', 'Manage Tools'],
    'dashboard'  => ['index.php', '📊', 'Dashboard'],
    'tools'      => ['tools-report.php', '📈', 'Tools Report'],
    'categories' => ['categories.php', '📁', 'Categories'],
    'users'      => ['users.php', '🌍', 'Users & Devices'],
    'traffic'    => ['traffic.php', '🔗', 'Traffic Sources'],
    'realtime'   => ['realtime.php', '🟢', 'Real-Time'],
    'reports'    => ['reports.php', '🏆', 'Top Reports'],
    'reviews'    => ['reviews.php', '⭐', 'Reviews'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> | ApneSoftware Admin</title>
<link rel="stylesheet" href="assets/dashboard.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
</head>
<body>

<aside class="dash-sidebar">
  <div class="dash-logo">Apne<span>Software</span><br><small style="color:var(--text-dim);font-weight:500;font-size:.7rem">Admin Panel</small></div>
  <nav class="dash-nav">
    <?php foreach ($navItems as $key => $item): ?>
      <a href="<?= $item[0] ?>" class="<?= ($activeNav ?? '') === $key ? 'active' : '' ?>"><?= $item[1] ?> <?= $item[2] ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="dash-sidebar-footer">
    <a href="<?= SITE_URL ?>" target="_blank">🌐 View Live Site</a>
    <a href="logout.php">🚪 Logout</a>
  </div>
</aside>

<main class="dash-main">
  <div class="dash-topbar">
    <div>
      <h1><?= htmlspecialchars($pageTitle ?? '') ?></h1>
      <?php if (!empty($pageSubtitle)): ?><p><?= htmlspecialchars($pageSubtitle) ?></p><?php endif; ?>
    </div>
  </div>
