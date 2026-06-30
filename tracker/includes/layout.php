<?php
// Shared layout helpers
function pageHeader($title = '') {
    $appName = APP_NAME;
    $t = $title ? "$title — $appName" : $appName;
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
    <link rel='manifest' href='/tracker/manifest.json'>
    <meta name='theme-color' content='#7c3aed'>
    <meta name='mobile-web-app-capable' content='yes'>
    <meta name='apple-mobile-web-app-capable' content='yes'>
    <meta name='apple-mobile-web-app-status-bar-style' content='black-translucent'>
    <script>if('serviceWorker' in navigator){navigator.serviceWorker.register('/tracker/sw.js');}</script>
<meta name='viewport' content='width=device-width, initial-scale=1'>
<title>" . htmlspecialchars($t) . "</title>
<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
<link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css' rel='stylesheet'>
<style>
:root{
  --bg:#0d1117; --bg2:#161b27; --bg3:#1e2533;
  --border:rgba(255,255,255,.1);
  --accent:#7c5cfc; --green:#16c079; --red:#ef4444;
  --yellow:#facc15; --orange:#f97316; --blue:#60a5fa;
  --txt:#e6eaf2; --dim:rgba(230,234,242,.6);
}
body{background:var(--bg);color:var(--txt);font-family:'Segoe UI',system-ui,sans-serif;min-height:100vh}
.navbar{background:var(--bg2)!important;border-bottom:1px solid var(--border)}
.navbar-brand{font-weight:800;color:#fff!important}
.navbar-brand span{color:var(--accent)}
.nav-link{color:var(--dim)!important;font-weight:500;transition:.15s}
.nav-link:hover,.nav-link.active{color:#fff!important}
.card{background:var(--bg2)!important;border:1px solid var(--border)!important;border-radius:14px!important;color:var(--txt)}
.card-header{background:var(--bg3)!important;border-bottom:1px solid var(--border)!important;font-weight:700}
.table{color:var(--txt)!important}
.table>:not(caption)>*>*{background:transparent!important;color:var(--txt)!important;border-color:var(--border)!important}
.table thead th{background:var(--bg3)!important;font-weight:700;font-size:.82rem;text-transform:uppercase;letter-spacing:.05em}
.table tbody tr:hover td{background:rgba(255,255,255,.04)!important}
.btn-primary{background:var(--accent);border-color:var(--accent)}
.btn-primary:hover{background:#6b5ee0;border-color:#6b5ee0}
.btn-success{background:var(--green);border-color:var(--green);color:#fff}
.btn-success:hover{background:#12a868;border-color:#12a868}
.btn-danger{background:var(--red);border-color:var(--red)}
.btn-warning{background:var(--yellow);border-color:var(--yellow);color:#000}
.btn-info{background:var(--blue);border-color:var(--blue);color:#000}
.btn-secondary{background:var(--bg3);border-color:var(--border);color:var(--txt)}
.btn-secondary:hover{background:var(--bg2);color:#fff}
.btn-outline-secondary{border-color:var(--border);color:var(--dim)}
.btn-outline-secondary:hover{background:var(--bg3);color:#fff;border-color:var(--border)}
.form-control,.form-select{background:var(--bg3)!important;border:1px solid var(--border)!important;color:var(--txt)!important;border-radius:8px}
.form-control:focus,.form-select:focus{border-color:var(--accent)!important;box-shadow:0 0 0 3px rgba(124,92,252,.2)!important;color:var(--txt)!important}
.form-control::placeholder{color:rgba(230,234,242,.3)!important}
.form-label{color:var(--dim);font-weight:600;font-size:.88rem}
.alert-success{background:rgba(22,192,121,.15);border-color:rgba(22,192,121,.3);color:var(--green)}
.alert-danger{background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.3);color:var(--red)}
.alert-warning{background:rgba(250,204,21,.15);border-color:rgba(250,204,21,.3);color:var(--yellow)}
.alert-info{background:rgba(96,165,250,.15);border-color:rgba(96,165,250,.3);color:var(--blue)}
.badge-active{background:rgba(22,192,121,.2);color:var(--green);border:1px solid rgba(22,192,121,.3);padding:4px 10px;border-radius:20px;font-size:.72rem;font-weight:700}
.badge-paused{background:rgba(250,204,21,.15);color:var(--yellow);border:1px solid rgba(250,204,21,.3);padding:4px 10px;border-radius:20px;font-size:.72rem;font-weight:700}
.badge-error{background:rgba(239,68,68,.15);color:var(--red);border:1px solid rgba(239,68,68,.3);padding:4px 10px;border-radius:20px;font-size:.72rem;font-weight:700}
.stat-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:22px 20px;transition:.2s}
.stat-card:hover{border-color:var(--accent);transform:translateY(-2px)}
.stat-num{font-size:2rem;font-weight:800;line-height:1}
.stat-label{font-size:.8rem;color:var(--dim);margin-top:5px}
.change-type{padding:3px 10px;border-radius:12px;font-size:.72rem;font-weight:700;white-space:nowrap}
.ct-title{background:rgba(250,204,21,.15);color:var(--yellow)}
.ct-meta{background:rgba(96,165,250,.15);color:var(--blue)}
.ct-h1{background:rgba(168,139,250,.15);color:#a78bfa}
.ct-content{background:rgba(239,68,68,.15);color:var(--red)}
.content-box{max-width:300px;max-height:100px;overflow:auto;font-size:.75rem;font-family:monospace;white-space:pre-wrap;background:var(--bg);padding:6px 8px;border-radius:6px;border:1px solid var(--border)}
.page-title{font-size:1.4rem;font-weight:800;margin-bottom:0}
.breadcrumb-item a{color:var(--accent)}
.breadcrumb-item.active{color:var(--dim)}
.breadcrumb-item+.breadcrumb-item::before{color:var(--dim)}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:rgba(255,255,255,.12);border-radius:3px}
.modal-content{background:var(--bg2)!important;border:1px solid var(--border)!important;color:var(--txt)}
.modal-header{border-bottom-color:var(--border)!important}
.modal-footer{border-top-color:var(--border)!important}
.btn-close{filter:invert(1)}
</style>
</head>
<body>
<nav class='navbar navbar-expand-lg px-3'>
  <a class='navbar-brand' href='../dashboard.php'>🔍 Change<span>Tracker</span></a>
  <button class='navbar-toggler border-0' type='button' data-bs-toggle='collapse' data-bs-target='#navMenu'>
    <i class='bi bi-list' style='color:#fff;font-size:1.4rem'></i>
  </button>
  <div class='collapse navbar-collapse' id='navMenu'>
    <ul class='navbar-nav me-auto ms-3 gap-1'>
      <li class='nav-item'><a class='nav-link' href='../admin/smart_dashboard.php' style='color:#facc15;font-weight:700'><i class='bi bi-lightning-charge-fill me-1'></i>Smart</a></li>
      <li class='nav-item'><a class='nav-link' href='../dashboard.php'><i class='bi bi-speedometer2 me-1'></i>Dashboard</a></li>
      <li class='nav-item'><a class='nav-link' href='../admin/websites.php'><i class='bi bi-globe me-1'></i>Websites</a></li>
      <li class='nav-item'><a class='nav-link' href='../admin/changes.php'><i class='bi bi-activity me-1'></i>Changes</a></li>
      <li class='nav-item'><a class='nav-link' href='../admin/analytics.php'><i class='bi bi-bar-chart me-1'></i>Analytics</a></li>
      <li class='nav-item'><a class='nav-link' href='../admin/keywords.php'><i class='bi bi-tag me-1'></i>Keywords</a></li>
      <li class='nav-item'><a class='nav-link' href='../admin/cron_setup.php'><i class='bi bi-clock me-1'></i>Auto Scan</a></li>
      <li class='nav-item'><a class='nav-link' href='../admin/exclude_pages.php'><i class='bi bi-slash-circle me-1'></i>Exclude</a></li>
      <li class='nav-item'><a class='nav-link' href='../admin/filter_test.php'><i class='bi bi-funnel me-1'></i>Filter Test</a></li>
      <li class='nav-item'><a class='nav-link' href='../admin/settings.php'><i class='bi bi-gear me-1'></i>Settings</a></li>
    </ul>
    <ul class='navbar-nav ms-auto gap-1'>
      <li class='nav-item'><a class='nav-link' href='../cron/discover_links.php'><i class='bi bi-link-45deg me-1'></i>Discover</a></li>
      <li class='nav-item'><a class='nav-link' href='../cron/full_scan.php'><i class='bi bi-arrow-repeat me-1'></i>Scan</a></li>
      <li class='nav-item'><a class='nav-link text-danger' href='../logout.php'><i class='bi bi-box-arrow-right me-1'></i>Logout</a></li>
    </ul>
  </div>
</nav>
";
}

function pageFooter() {
    echo "
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'></script>
</body></html>";
}

function flash($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function showFlash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $icons = ['success'=>'bi-check-circle-fill','danger'=>'bi-x-circle-fill','warning'=>'bi-exclamation-triangle-fill','info'=>'bi-info-circle-fill'];
        $icon = $icons[$f['type']] ?? 'bi-info-circle-fill';
        echo "<div class='alert alert-{$f['type']} d-flex align-items-center gap-2 mb-4'>
            <i class='bi {$icon}'></i> " . htmlspecialchars($f['msg']) . "
        </div>";
    }
}
