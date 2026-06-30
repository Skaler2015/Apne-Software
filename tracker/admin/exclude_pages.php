<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

// Create table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS excluded_pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        website_id INT NULL,
        url_pattern VARCHAR(500) NOT NULL,
        match_type ENUM('exact','contains','starts_with') DEFAULT 'contains',
        reason VARCHAR(200) NULL,
        created_at DATETIME DEFAULT NOW()
    )");
} catch(Exception $e) {}

// Actions
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM excluded_pages WHERE id=?")->execute([(int)$_GET['delete']]);
    flash('Exclusion removed.'); header('Location: exclude_pages.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pattern  = trim($_POST['url_pattern'] ?? '');
    $wid      = (int)($_POST['website_id'] ?? 0);
    $type     = $_POST['match_type'] ?? 'contains';
    $reason   = trim($_POST['reason'] ?? '');

    if ($pattern) {
        $pdo->prepare("INSERT INTO excluded_pages (website_id, url_pattern, match_type, reason) VALUES (?,?,?,?)")
            ->execute([$wid ?: null, $pattern, $type, $reason]);
        flash("✅ Exclusion added!");
    }
    header('Location: exclude_pages.php'); exit;
}

// Also: mark existing pages as excluded directly
if (isset($_GET['exclude_page_id'])) {
    $pid = (int)$_GET['exclude_page_id'];
    $row = $pdo->prepare("SELECT page_url, website_id FROM pages WHERE id=?");
    $row->execute([$pid]);
    $row = $row->fetch();
    if ($row) {
        $pdo->prepare("INSERT IGNORE INTO excluded_pages (website_id, url_pattern, match_type, reason) VALUES (?,?,'exact','Manually excluded')")
            ->execute([$row['website_id'], $row['page_url']]);
        // Also mark in pages table
        try {
            $pdo->prepare("UPDATE pages SET excluded=1 WHERE id=?")->execute([$pid]);
        } catch(Exception $e) {}
        flash("✅ Page excluded from scan.");
    }
    header('Location: exclude_pages.php'); exit;
}

$exclusions = $pdo->query("
    SELECT e.*, w.website_name
    FROM excluded_pages e LEFT JOIN websites w ON e.website_id=w.id
    ORDER BY e.id DESC
")->fetchAll();

$websites = $pdo->query("SELECT id, website_name FROM websites ORDER BY website_name")->fetchAll();

pageHeader('Exclude Pages');
?>
<div class="container py-4" style="max-width:860px">
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Exclude Pages</li>
  </ol>
</nav>

<div class="d-flex align-items-center gap-3 mb-4">
  <div style="background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:10px 14px;font-size:1.4rem">🚫</div>
  <div>
    <h1 class="page-title">Exclude Pages from Scan</h1>
    <p class="text-muted mb-0" style="font-size:.85rem">In pages ko scan se bahar rakho — homepage, disclaimer, about etc.</p>
  </div>
</div>

<?php showFlash(); ?>

<!-- Add form -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-plus-circle me-2"></i>Add Exclusion</div>
  <div class="card-body p-4">
    <form method="post">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">URL / Pattern <span style="color:var(--red)">*</span></label>
          <input type="text" name="url_pattern" class="form-control"
                 placeholder="e.g. /disclaimer or sarkariresult.com.cm/about" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Match Type</label>
          <select name="match_type" class="form-select">
            <option value="exact">Exact URL</option>
            <option value="contains" selected>Contains</option>
            <option value="starts_with">Starts with</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Website</label>
          <select name="website_id" class="form-select">
            <option value="">All</option>
            <?php foreach($websites as $w): ?>
            <option value="<?=$w['id']?>"><?=htmlspecialchars($w['website_name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Reason</label>
          <input type="text" name="reason" class="form-control" placeholder="e.g. Homepage">
        </div>
        <div class="col-md-1 d-flex align-items-end">
          <button type="submit" class="btn btn-danger w-100">Add</button>
        </div>
      </div>
    </form>

    <!-- Quick add chips -->
    <div class="mt-3">
      <span style="font-size:.78rem;color:var(--dim)">Common exclusions:</span>
      <?php
      $common = [
        '/disclaimer' => 'Disclaimer',
        '/about' => 'About',
        '/contact' => 'Contact',
        '/privacy-policy' => 'Privacy Policy',
        '/sitemap' => 'Sitemap',
        'post-sitemap.xml' => 'Sitemap XML',
        '/category/' => 'Categories',
        '/tag/' => 'Tags',
        '/page/' => 'Pagination',
      ];
      foreach($common as $pattern => $label): ?>
      <button onclick="document.querySelector('[name=url_pattern]').value='<?=$pattern?>'"
              class="btn btn-sm btn-outline-secondary ms-1" style="font-size:.72rem;padding:2px 8px">
        <?=$label?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Exclusions list -->
<?php if (!$exclusions): ?>
<div class="card">
  <div class="card-body text-center py-4" style="color:var(--dim)">
    <i class="bi bi-slash-circle" style="font-size:2rem"></i>
    <p class="mt-2 mb-0">No exclusions yet</p>
    <p class="small">Add URLs or patterns you don't want to scan</p>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="card-header"><i class="bi bi-list-ul me-2"></i>Excluded Patterns (<?=count($exclusions)?>)</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>Pattern</th><th>Type</th><th>Website</th><th>Reason</th><th>Added</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach($exclusions as $e): ?>
        <tr>
          <td style="font-family:monospace;font-size:.8rem;color:var(--red)"><?=htmlspecialchars($e['url_pattern'])?></td>
          <td><span style="font-size:.72rem;background:var(--bg3);padding:2px 8px;border-radius:10px"><?=$e['match_type']?></span></td>
          <td style="font-size:.82rem"><?=htmlspecialchars($e['website_name']??'All')?></td>
          <td style="font-size:.8rem;color:var(--dim)"><?=htmlspecialchars($e['reason']??'')?></td>
          <td style="font-size:.75rem;color:var(--dim)"><?=date('d M',strtotime($e['created_at']))?></td>
          <td>
            <a href="?delete=<?=$e['id']?>" class="btn btn-sm btn-outline-danger" style="padding:2px 8px"
               onclick="return confirm('Remove this exclusion?')">
              <i class="bi bi-x" style="font-size:.8rem"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="mt-3 p-3" style="background:rgba(96,165,250,.08);border:1px solid rgba(96,165,250,.2);border-radius:10px;font-size:.82rem;color:var(--dim)">
  <i class="bi bi-info-circle me-1" style="color:var(--blue)"></i>
  <b style="color:var(--blue)">How it works:</b>
  <b>Exact</b> — sirf wahi exact URL skip hogi।
  <b>Contains</b> — URL mein yeh text ho to skip (e.g. "/category/" se sab category pages skip)।
  <b>Starts with</b> — URL is pattern se shuru ho to skip।
  Changes page se bhi kisi page ko directly exclude kar sakte ho।
</div>

</div>
<?php pageFooter(); ?>
