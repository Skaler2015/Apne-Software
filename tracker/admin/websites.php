<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

// Handle DELETE
if (isset($_GET['delete']) && isset($_GET['confirm'])) {
    $did = (int)$_GET['delete'];
    $w = $pdo->prepare("SELECT website_name FROM websites WHERE id=?");
    $w->execute([$did]);
    $wname = $w->fetchColumn();
    if ($wname) {
        $pdo->prepare("DELETE FROM websites WHERE id=?")->execute([$did]);
        flash("Website '{$wname}' deleted successfully.");
    } else {
        flash('Website not found.', 'danger');
    }
    header('Location: websites.php'); exit;
}

// Handle PAUSE/ACTIVATE toggle
if (isset($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    $cur = $pdo->prepare("SELECT status FROM websites WHERE id=?");
    $cur->execute([$tid]);
    $curStatus = $cur->fetchColumn();
    $newStatus = $curStatus === 'active' ? 'paused' : 'active';
    $pdo->prepare("UPDATE websites SET status=? WHERE id=?")->execute([$newStatus, $tid]);
    flash("Website status changed to " . ucfirst($newStatus) . ".");
    header('Location: websites.php'); exit;
}

// Get all websites with stats
$websites = $pdo->query("
    SELECT w.*,
           COUNT(DISTINCT p.id) as page_count,
           COUNT(DISTINCT c.id) as change_count,
           MAX(p.last_scan) as last_scan
    FROM websites w
    LEFT JOIN pages p ON w.id = p.website_id
    LEFT JOIN changes c ON w.id = c.website_id
    GROUP BY w.id
    ORDER BY w.id DESC
")->fetchAll();

pageHeader('Websites');
?>
<div class="container-fluid py-4 px-4">

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Websites</li>
  </ol>
</nav>

<?php showFlash(); ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h1 class="page-title"><i class="bi bi-globe me-2" style="color:var(--accent)"></i>Tracked Websites</h1>
    <p class="text-muted mb-0" style="font-size:.85rem"><?= count($websites) ?> website<?= count($websites) !== 1 ? 's' : '' ?> total</p>
  </div>
  <a href="add_website.php" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i>Add Website
  </a>
</div>

<?php if (!$websites): ?>
<div class="card">
  <div class="card-body text-center py-5" style="color:var(--dim)">
    <i class="bi bi-globe" style="font-size:3rem"></i>
    <p class="mt-3 mb-2" style="font-weight:700">No websites added yet</p>
    <p class="mb-3 small">Add your first website to start monitoring changes</p>
    <a href="add_website.php" class="btn btn-primary">
      <i class="bi bi-plus-circle me-1"></i>Add Website
    </a>
  </div>
</div>

<?php else: ?>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Website</th>
            <th>URL</th>
            <th class="text-center">Pages</th>
            <th class="text-center">Changes</th>
            <th>Last Scan</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($websites as $w): ?>
        <tr>
          <td style="color:var(--muted);font-size:.82rem"><?= $w['id'] ?></td>
          <td>
            <div style="font-weight:700"><?= htmlspecialchars($w['website_name']) ?></div>
            <div style="font-size:.72rem;color:var(--dim)"><?= date('d M Y', strtotime($w['created_at'])) ?></div>
          </td>
          <td>
            <a href="<?= htmlspecialchars($w['website_url']) ?>" target="_blank"
               style="color:var(--blue);font-size:.82rem;max-width:200px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= htmlspecialchars($w['website_url']) ?>
              <i class="bi bi-box-arrow-up-right ms-1" style="font-size:.7rem"></i>
            </a>
          </td>
          <td class="text-center">
            <span style="font-weight:700;color:var(--green)"><?= $w['page_count'] ?></span>
          </td>
          <td class="text-center">
            <?php if ($w['change_count'] > 0): ?>
              <span style="font-weight:700;color:var(--red)"><?= $w['change_count'] ?></span>
            <?php else: ?>
              <span style="color:var(--dim)">0</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem;color:var(--dim);white-space:nowrap">
            <?= $w['last_scan'] ? date('d M, H:i', strtotime($w['last_scan'])) : '<span style="color:var(--muted)">Never</span>' ?>
          </td>
          <td>
            <span class="badge-<?= $w['status'] ?>"><?= ucfirst($w['status']) ?></span>
          </td>
          <td>
            <div class="d-flex gap-1">
              <!-- Edit -->
              <a href="edit_website.php?id=<?= $w['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <!-- Toggle status -->
              <a href="websites.php?toggle=<?= $w['id'] ?>"
                 class="btn btn-sm <?= $w['status']==='active' ? 'btn-warning' : 'btn-success' ?>"
                 title="<?= $w['status']==='active' ? 'Pause' : 'Activate' ?>"
                 onclick="return confirm('Change status of this website?')">
                <i class="bi bi-<?= $w['status']==='active' ? 'pause-fill' : 'play-fill' ?>"></i>
              </a>
              <!-- Pages -->
              <a href="pages.php?website_id=<?= $w['id'] ?>" class="btn btn-sm btn-info" title="View Pages">
                <i class="bi bi-file-earmark-text"></i>
              </a>
              <!-- Delete -->
              <a href="websites.php?delete=<?= $w['id'] ?>&confirm=1"
                 class="btn btn-sm btn-danger" title="Delete"
                 onclick="return confirm('DELETE <?= addslashes($w['website_name']) ?> and ALL its data?\n\nThis cannot be undone!')">
                <i class="bi bi-trash3"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php endif; ?>
</div>
<?php pageFooter(); ?>
