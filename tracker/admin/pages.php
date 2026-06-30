<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

$wid = (int)($_GET['website_id'] ?? 0);

// Delete page
if (isset($_GET['delete_page'])) {
    $pid = (int)$_GET['delete_page'];
    $pdo->prepare("DELETE FROM pages WHERE id=? AND website_id=?")->execute([$pid, $wid]);
    flash('Page removed.');
    header("Location: pages.php?website_id=$wid"); exit;
}

$website = null;
if ($wid) {
    $stmt = $pdo->prepare("SELECT * FROM websites WHERE id=?");
    $stmt->execute([$wid]);
    $website = $stmt->fetch();
}

$pages = $pdo->prepare("
    SELECT p.*,
           (SELECT COUNT(*) FROM changes c WHERE c.page_id=p.id) as change_count
    FROM pages p
    WHERE p.website_id=?
    ORDER BY p.id DESC
");
$pages->execute([$wid]);
$pages = $pages->fetchAll();

pageHeader('Pages — ' . ($website['website_name'] ?? ''));
?>
<div class="container-fluid py-4 px-4">

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="websites.php">Websites</a></li>
    <li class="breadcrumb-item active">Pages</li>
  </ol>
</nav>

<?php showFlash(); ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h1 class="page-title"><i class="bi bi-file-earmark-text me-2" style="color:var(--green)"></i>
      Pages — <?= htmlspecialchars($website['website_name'] ?? '') ?>
    </h1>
    <p class="text-muted mb-0" style="font-size:.85rem"><?= count($pages) ?> pages tracked</p>
  </div>
  <div class="d-flex gap-2">
    <a href="../cron/discover_links.php?wid=<?= $wid ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-link-45deg me-1"></i>Re-discover
    </a>
    <a href="../cron/scan_changes.php?wid=<?= $wid ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-arrow-repeat me-1"></i>Scan This Site
    </a>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>#</th><th>Page URL</th><th>Title</th>
          <th class="text-center">Changes</th><th>Last Scan</th><th>Status</th><th>Action</th>
        </tr></thead>
        <tbody>
        <?php if (!$pages): ?>
        <tr><td colspan="7" class="text-center py-4" style="color:var(--dim)">
          No pages discovered yet. <a href="../cron/discover_links.php">Discover Links</a> first.
        </td></tr>
        <?php endif; ?>
        <?php foreach ($pages as $p): ?>
        <tr>
          <td style="color:var(--dim);font-size:.8rem"><?= $p['id'] ?></td>
          <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.82rem">
            <a href="<?= htmlspecialchars($p['page_url']) ?>" target="_blank" style="color:var(--blue)">
              <?= htmlspecialchars(parse_url($p['page_url'], PHP_URL_PATH) ?: '/') ?>
            </a>
          </td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.82rem">
            <?= htmlspecialchars($p['title'] ?: '—') ?>
          </td>
          <td class="text-center">
            <?php if ($p['change_count'] > 0): ?>
              <span style="color:var(--red);font-weight:700"><?= $p['change_count'] ?></span>
            <?php else: ?>
              <span style="color:var(--dim)">0</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.78rem;color:var(--dim);white-space:nowrap">
            <?= $p['last_scan'] ? date('d M, H:i', strtotime($p['last_scan'])) : 'Never' ?>
          </td>
          <td><span class="badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
          <td>
            <a href="pages.php?website_id=<?= $wid ?>&delete_page=<?= $p['id'] ?>"
               class="btn btn-sm btn-danger"
               onclick="return confirm('Remove this page from tracking?')">
              <i class="bi bi-trash3"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>
<?php pageFooter(); ?>
