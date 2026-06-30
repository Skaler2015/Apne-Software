<?php
require_once 'includes/config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'includes/db.php';

// Safe session values
$userName = $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'Admin';

// Stats - with error handling
try {
    $totalWebsites   = $pdo->query("SELECT COUNT(*) FROM websites")->fetchColumn();
    $totalPages      = $pdo->query("SELECT COUNT(*) FROM pages")->fetchColumn();
    $totalChanges    = $pdo->query("SELECT COUNT(*) FROM changes")->fetchColumn();
    $changesToday    = $pdo->query("SELECT COUNT(*) FROM changes WHERE DATE(detected_at)=CURDATE()")->fetchColumn();
    $lastScan        = $pdo->query("SELECT MAX(last_scan) FROM pages")->fetchColumn();

    $recentChanges = $pdo->query("
        SELECT c.*, p.page_url, w.website_name
        FROM changes c
        LEFT JOIN pages p ON c.page_id = p.id
        LEFT JOIN websites w ON c.website_id = w.id
        ORDER BY c.detected_at DESC LIMIT 8
    ")->fetchAll();

    $websites = $pdo->query("
        SELECT w.*, COUNT(p.id) as page_count
        FROM websites w
        LEFT JOIN pages p ON w.id = p.website_id
        GROUP BY w.id ORDER BY w.id DESC LIMIT 6
    ")->fetchAll();

} catch(Exception $e) {
    $totalWebsites = $totalPages = $totalChanges = $changesToday = 0;
    $recentChanges = $websites = [];
    $lastScan = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — ChangeTracker Pro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--accent:#7c5cfc;--green:#16c079;--red:#ef4444;--yellow:#facc15;--blue:#60a5fa;--orange:#f97316;
      --bg:#0d1117;--bg2:#161b27;--bg3:#1e2533;--border:rgba(255,255,255,.1);--txt:#e6eaf2;--dim:rgba(230,234,242,.55);--muted:rgba(230,234,242,.3)}
*{box-sizing:border-box}
body{background:var(--bg);color:var(--txt);font-family:'Segoe UI',system-ui,sans-serif;min-height:100vh}
/* NAVBAR */
.navbar{background:var(--bg2)!important;border-bottom:1px solid var(--border);padding:10px 20px}
.navbar-brand{font-weight:800;font-size:1.1rem;color:#fff!important;text-decoration:none}
.navbar-brand span{color:var(--accent)}
.nav-link{color:var(--dim)!important;font-weight:500;padding:6px 12px!important;border-radius:8px;transition:.15s}
.nav-link:hover{color:#fff!important;background:rgba(255,255,255,.06)}
.nav-link.active{color:#fff!important;background:rgba(124,92,252,.15)}
/* CARDS */
.card{background:var(--bg2)!important;border:1px solid var(--border)!important;border-radius:14px;color:var(--txt)}
.card-header{background:var(--bg3)!important;border-bottom:1px solid var(--border)!important;font-weight:700;padding:12px 18px}
/* STAT CARDS */
.stat-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:22px 20px;transition:.2s;cursor:default}
.stat-card:hover{border-color:var(--accent);transform:translateY(-2px);box-shadow:0 8px 24px rgba(124,92,252,.15)}
.stat-num{font-size:2.2rem;font-weight:800;line-height:1}
.stat-label{font-size:.8rem;color:var(--dim);margin-top:6px}
.stat-icon{font-size:1.8rem;float:right;opacity:.25}
/* TABLE */
.table{color:var(--txt)!important}
.table>:not(caption)>*>*{background:transparent!important;color:var(--txt)!important;border-color:var(--border)!important;padding:10px 14px}
.table thead th{background:var(--bg3)!important;font-weight:700;font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:var(--dim)!important}
.table tbody tr:hover td{background:rgba(255,255,255,.03)!important}
/* BADGES */
.ct-badge{padding:3px 9px;border-radius:10px;font-size:.72rem;font-weight:700;white-space:nowrap}
.ct-title{background:rgba(250,204,21,.15);color:var(--yellow)}
.ct-meta{background:rgba(96,165,250,.15);color:var(--blue)}
.ct-h1{background:rgba(168,139,250,.15);color:#a78bfa}
.ct-content{background:rgba(239,68,68,.15);color:var(--red)}
.badge-active{background:rgba(22,192,121,.15);color:var(--green);border:1px solid rgba(22,192,121,.25);padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700}
.badge-paused{background:rgba(250,204,21,.12);color:var(--yellow);border:1px solid rgba(250,204,21,.25);padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700}
/* BTN */
.btn-primary{background:var(--accent);border-color:var(--accent)}
.btn-primary:hover{background:#6b5ee0;border-color:#6b5ee0}
.btn-outline-secondary{border-color:var(--border);color:var(--dim)}
.btn-outline-secondary:hover{background:var(--bg3);color:#fff;border-color:var(--border)}
/* MISC */
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:rgba(255,255,255,.12);border-radius:3px}
.site-row{padding:12px 18px;border-bottom:1px solid var(--border)}
.site-row:last-child{border-bottom:none}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg">
  <a class="navbar-brand" href="dashboard.php">🔍 Change<span>Tracker</span> Pro</a>
  <button class="navbar-toggler border-0 ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
    <i class="bi bi-list" style="color:#fff;font-size:1.4rem"></i>
  </button>
  <div class="collapse navbar-collapse ms-3" id="nav">
    <ul class="navbar-nav me-auto gap-1">
      <li><a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
      <li><a class="nav-link" href="admin/websites.php"><i class="bi bi-globe me-1"></i>Websites</a></li>
      <li><a class="nav-link" href="admin/changes.php"><i class="bi bi-activity me-1"></i>Changes</a></li>
      <li><a class="nav-link" href="admin/stats.php"><i class="bi bi-bar-chart me-1"></i>Stats</a></li>
      <li><a class="nav-link" href="admin/keywords.php"><i class="bi bi-tag me-1"></i>Keywords</a></li>
      <li><a class="nav-link" href="admin/schedule.php"><i class="bi bi-clock me-1"></i>Schedule</a></li>
    </ul>
    <ul class="navbar-nav ms-auto gap-1 align-items-center">
      <li><a class="nav-link" href="cron/discover_links.php"><i class="bi bi-link-45deg me-1"></i>Discover</a></li>
      <li><a class="nav-link" href="cron/full_scan.php"><i class="bi bi-arrow-repeat me-1"></i>Scan</a></li>
      <li>
        <span style="color:var(--dim);font-size:.82rem;padding:0 8px">
          <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($userName) ?>
        </span>
      </li>
      <li><a class="nav-link" style="color:var(--red)!important" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a></li>
    </ul>
  </div>
</nav>

<!-- MAIN CONTENT -->
<div class="container-fluid py-4 px-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h1 style="font-size:1.5rem;font-weight:800;margin:0">
        <i class="bi bi-speedometer2 me-2" style="color:var(--accent)"></i>Dashboard
      </h1>
      <p style="color:var(--dim);font-size:.83rem;margin:4px 0 0">
        Welcome back, <?= htmlspecialchars($userName) ?> &nbsp;·&nbsp; <?= date('d M Y, H:i') ?>
      </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="cron/discover_links.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-link-45deg me-1"></i>Discover Links
      </a>
      <a href="cron/full_scan.php" class="btn btn-sm btn-primary">
        <i class="bi bi-arrow-repeat me-1"></i>Run Scan
      </a>
      <a href="admin/add_website.php" class="btn btn-sm btn-success" style="background:var(--green);border-color:var(--green)">
        <i class="bi bi-plus-circle me-1"></i>Add Website
      </a>
    </div>
  </div>

  <!-- STAT CARDS -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <i class="bi bi-globe stat-icon"></i>
        <div class="stat-num" style="color:var(--accent)"><?= $totalWebsites ?></div>
        <div class="stat-label"><i class="bi bi-globe me-1"></i>Total Websites</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <i class="bi bi-file-earmark stat-icon"></i>
        <div class="stat-num" style="color:var(--green)"><?= $totalPages ?></div>
        <div class="stat-label"><i class="bi bi-file-earmark me-1"></i>Pages Tracked</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <i class="bi bi-activity stat-icon"></i>
        <div class="stat-num" style="color:var(--red)"><?= $totalChanges ?></div>
        <div class="stat-label"><i class="bi bi-activity me-1"></i>Total Changes</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <i class="bi bi-calendar-day stat-icon"></i>
        <div class="stat-num" style="color:var(--yellow)"><?= $changesToday ?></div>
        <div class="stat-label"><i class="bi bi-calendar-day me-1"></i>Changes Today</div>
      </div>
    </div>
  </div>

  <!-- MAIN ROW -->
  <div class="row g-4">

    <!-- Recent Changes -->
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><i class="bi bi-activity me-2" style="color:var(--red)"></i>Recent Changes</span>
          <a href="admin/changes.php" class="btn btn-sm btn-outline-secondary">View All</a>
        </div>
        <div class="card-body p-0">
          <?php if (!$recentChanges): ?>
            <div class="text-center py-5" style="color:var(--dim)">
              <i class="bi bi-check-circle" style="font-size:2.5rem;color:var(--green)"></i>
              <p class="mt-3 mb-1" style="font-weight:700">No changes detected yet</p>
              <p class="small mb-3">Add a website and run a scan</p>
              <a href="cron/full_scan.php" class="btn btn-sm btn-primary">Run Scan Now</a>
            </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead><tr>
                <th>Website</th><th>Type</th><th>Page</th><th>When</th>
              </tr></thead>
              <tbody>
              <?php
              $ctMap = [
                'TITLE_CHANGED'   => ['ct-title',  'Title'],
                'META_CHANGED'    => ['ct-meta',   'Meta'],
                'H1_CHANGED'      => ['ct-h1',     'H1'],
                'CONTENT_CHANGED' => ['ct-content','Content'],
              ];
              foreach ($recentChanges as $r):
                [$cls, $lbl] = $ctMap[$r['change_type']] ?? ['', 'Unknown'];
                $path = parse_url($r['page_url'] ?? '', PHP_URL_PATH) ?: '/';
              ?>
              <tr>
                <td style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($r['website_name'] ?? '—') ?></td>
                <td><span class="ct-badge <?= $cls ?>"><?= $lbl ?></span></td>
                <td style="font-size:.78rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                  <a href="<?= htmlspecialchars($r['page_url'] ?? '#') ?>" target="_blank" style="color:var(--blue)">
                    <?= htmlspecialchars($path) ?>
                  </a>
                </td>
                <td style="font-size:.78rem;color:var(--dim);white-space:nowrap">
                  <?= date('d M, H:i', strtotime($r['detected_at'])) ?>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Websites Panel -->
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><i class="bi bi-globe me-2" style="color:var(--accent)"></i>Websites</span>
          <a href="admin/add_website.php" class="btn btn-sm btn-primary btn-sm px-2">
            <i class="bi bi-plus-lg"></i>
          </a>
        </div>
        <div class="card-body p-0">
          <?php if (!$websites): ?>
            <div class="text-center py-4" style="color:var(--dim)">
              <i class="bi bi-globe" style="font-size:2rem"></i>
              <p class="mt-2 mb-2 small">No websites added yet</p>
              <a href="admin/add_website.php" class="btn btn-sm btn-primary">Add Website</a>
            </div>
          <?php else: ?>
            <?php foreach ($websites as $w): ?>
            <div class="site-row">
              <div class="d-flex align-items-center justify-content-between">
                <div style="min-width:0;flex:1;margin-right:8px">
                  <div style="font-weight:700;font-size:.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= htmlspecialchars($w['website_name']) ?>
                  </div>
                  <div style="font-size:.72rem;color:var(--dim)">
                    <?= $w['page_count'] ?> page<?= $w['page_count'] != 1 ? 's' : '' ?>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-1">
                  <span class="badge-<?= $w['status'] ?>"><?= ucfirst($w['status']) ?></span>
                  <a href="admin/edit_website.php?id=<?= $w['id'] ?>" class="btn btn-sm" style="padding:2px 7px;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:6px;color:var(--dim)" title="Edit">
                    <i class="bi bi-pencil" style="font-size:.72rem"></i>
                  </a>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <div style="padding:10px 18px">
              <a href="admin/websites.php" class="btn btn-sm btn-outline-secondary w-100">View All</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /row -->

  <!-- Last scan info -->
  <?php if ($lastScan): ?>
  <div class="mt-4" style="background:rgba(22,192,121,.07);border:1px solid rgba(22,192,121,.18);border-radius:10px;padding:10px 18px;font-size:.82rem;color:var(--green)">
    <i class="bi bi-clock-history me-2"></i>
    Last scan: <b><?= date('d M Y, h:i A', strtotime($lastScan)) . ' IST' ?></b>
  </div>
  <?php endif; ?>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
