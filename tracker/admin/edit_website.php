<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { flash('Invalid website ID.','danger'); header('Location: websites.php'); exit; }

$website = $pdo->prepare("SELECT * FROM websites WHERE id = ?");
$website->execute([$id]);
$website = $website->fetch();
if (!$website) { flash('Website not found.','danger'); header('Location: websites.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['website_name'] ?? '');
    $url        = trim($_POST['website_url']  ?? '');
    $sitemapUrl = trim($_POST['sitemap_url']  ?? '');
    $status     = $_POST['status'] ?? 'active';

    if (!$name) $errors[] = 'Website name is required.';
    if (!$url)  $errors[] = 'Website URL is required.';
    if ($url && !filter_var($url, FILTER_VALIDATE_URL)) $errors[] = 'Please enter a valid URL (include https://).';
    if (!in_array($status, ['active','paused'])) $status = 'active';

    if (!$errors) {
        $url = rtrim($url, '/');
        $skipUrls = trim($_POST['skip_urls'] ?? '');
        try {
            $pdo->prepare("UPDATE websites SET website_name=?, website_url=?, sitemap_url=?, status=? WHERE id=?")
                ->execute([$name, $url, $sitemapUrl, $status, $id]);
            // Update skip URLs
            try {
                $pdo->prepare("DELETE FROM excluded_pages WHERE website_id=?")->execute([$id]);
                $lines = array_filter(array_map('trim', explode("
", $skipUrls)));
                foreach ($lines as $line) {
                    if ($line) {
                        $pdo->prepare("INSERT INTO excluded_pages (website_id, url_pattern, match_type, reason) VALUES (?,?,'contains','Saved from website settings')")
                            ->execute([$id, $line]);
                    }
                }
            } catch(Exception $eSkip) {}
            flash("✅ Website updated!");
            header('Location: websites.php'); exit;
        } catch(Exception $e) {
            // sitemap_url column might not exist yet — try without it
            try {
                $pdo->prepare("UPDATE websites SET website_name=?, website_url=?, status=? WHERE id=?")
                    ->execute([$name, $url, $status, $id]);
                // Try to add column and update again
                try { $pdo->exec("ALTER TABLE websites ADD COLUMN sitemap_url VARCHAR(500) NULL"); } catch(Exception $e2){}
                $pdo->prepare("UPDATE websites SET sitemap_url=? WHERE id=?")->execute([$sitemapUrl, $id]);
                flash("✅ Website updated!");
                header('Location: websites.php'); exit;
            } catch(Exception $e3) {
                $errors[] = "Save error: " . $e3->getMessage();
            }
        }
    }

    $website['website_name'] = $_POST['website_name'];
    $website['website_url']  = $_POST['website_url'];
    $website['sitemap_url']  = $_POST['sitemap_url'];
    $website['status']       = $_POST['status'];
}

$pageCount   = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE website_id=?");   $pageCount->execute([$id]);
$changeCount = $pdo->prepare("SELECT COUNT(*) FROM changes WHERE website_id=?"); $changeCount->execute([$id]);
$lastScanSt  = $pdo->prepare("SELECT MAX(last_scan) FROM pages WHERE website_id=?"); $lastScanSt->execute([$id]);
$lastScanVal = $lastScanSt->fetchColumn();

pageHeader('Edit Website');
?>
<div class="container py-4" style="max-width:700px">

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="websites.php">Websites</a></li>
    <li class="breadcrumb-item active">Edit Website</li>
  </ol>
</nav>

<div class="d-flex align-items-center gap-3 mb-4">
  <div style="background:rgba(22,192,121,.15);border:1px solid rgba(22,192,121,.3);border-radius:12px;padding:10px 14px;font-size:1.4rem">✏️</div>
  <div>
    <h1 class="page-title">Edit Website</h1>
    <p class="text-muted mb-0" style="font-size:.85rem">#<?= $id ?> — <?= htmlspecialchars($website['website_name']) ?></p>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-4">
    <div class="stat-card text-center">
      <div class="stat-num" style="font-size:1.5rem;color:var(--green)"><?= $pageCount->fetchColumn() ?></div>
      <div class="stat-label">Pages</div>
    </div>
  </div>
  <div class="col-4">
    <div class="stat-card text-center">
      <div class="stat-num" style="font-size:1.5rem;color:var(--red)"><?= $changeCount->fetchColumn() ?></div>
      <div class="stat-label">Changes</div>
    </div>
  </div>
  <div class="col-4">
    <div class="stat-card text-center">
      <div class="stat-num" style="font-size:.85rem;color:var(--blue)">
        <?= $lastScanVal ? date('d M', strtotime($lastScanVal)) : 'Never' ?>
      </div>
      <div class="stat-label">Last Scan</div>
    </div>
  </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger mb-3">
  <?php foreach ($errors as $e): ?><div><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-body p-4">
    <form method="post">

      <div class="mb-4">
        <label class="form-label">Website Name <span style="color:var(--red)">*</span></label>
        <input type="text" name="website_name" class="form-control"
               value="<?= htmlspecialchars($website['website_name']) ?>" required>
      </div>

      <div class="mb-4">
        <label class="form-label">Website URL <span style="color:var(--red)">*</span></label>
        <input type="url" name="website_url" class="form-control"
               value="<?= htmlspecialchars($website['website_url']) ?>" required>
        <div class="form-text" style="color:var(--dim)">
          <i class="bi bi-exclamation-triangle me-1" style="color:var(--yellow)"></i>
          Changing URL will keep existing pages. Run "Discover Links" again after changing URL.
        </div>
      </div>

      <!-- SITEMAP URL -->
      <div class="mb-4">
        <label class="form-label">
          <i class="bi bi-map me-1" style="color:var(--accent)"></i>
          Sitemap URL
          <span style="background:rgba(22,192,121,.15);color:var(--green);border-radius:10px;padding:1px 8px;font-size:.7rem;font-weight:700;margin-left:4px">
            Recommended
          </span>
        </label>
        <input type="url" name="sitemap_url" class="form-control"
               value="<?= htmlspecialchars($website['sitemap_url'] ?? '') ?>"
               placeholder="https://example.com/post-sitemap.xml">
        <div class="form-text" style="color:var(--dim)">
          Direct sitemap XML URL — naye pages jaldi discover honge. Agar sitemap index hai to <code>sitemap_index.xml</code> daalein.
        </div>
        <?php
        // Show helpful suggestions based on website URL
        $wUrl = $website['website_url'] ?? '';
        $suggestions = [
            $wUrl . '/post-sitemap.xml',
            $wUrl . '/sitemap_index.xml',
            $wUrl . '/sitemap.xml',
        ];
        ?>
        <div class="mt-2" style="font-size:.75rem;color:var(--dim)">
          <span>Common URLs:</span>
          <?php foreach($suggestions as $sug): ?>
          <a href="#" onclick="document.querySelector('[name=sitemap_url]').value='<?= htmlspecialchars($sug) ?>'; return false;"
             style="color:var(--blue);margin-left:8px;text-decoration:underline">
            <?= htmlspecialchars(basename($sug)) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Skip/Exclude URLs -->
      <div class="mb-4">
        <label class="form-label">
          <i class="bi bi-slash-circle me-1" style="color:var(--red)"></i>
          Skip These URLs
          <span style="color:var(--dim);font-size:.75rem;font-weight:400;margin-left:4px">(optional)</span>
        </label>
        <?php
        // Get existing exclusions for this website
        $excls = [];
        try {
            $exRows = $pdo->prepare("SELECT url_pattern FROM excluded_pages WHERE website_id=? OR website_id IS NULL");
            $exRows->execute([$id]);
            $excls = $exRows->fetchAll(PDO::FETCH_COLUMN);
        } catch(Exception $e) {}
        $existingSkips = implode("
", $excls);
        ?>
        <textarea name="skip_urls" class="form-control" rows="3"
                  placeholder="/disclaimer&#10;/about&#10;/category/&#10;/tag/"
                  style="font-family:monospace;font-size:.82rem"><?= htmlspecialchars($existingSkips) ?></textarea>
        <div class="form-text" style="color:var(--dim)">
          Ek line mein ek URL/pattern — in pages ko scan se bahar rakha jaayega।
        </div>
        <div class="mt-2" style="font-size:.75rem">
          <span style="color:var(--dim)">Quick add:</span>
          <?php foreach(['/disclaimer','/about','/contact','/category/','/tag/','/page/'] as $p): ?>
          <a href="#" onclick="addSkip('<?=$p?>');return false"
             style="color:var(--blue);margin-left:6px;text-decoration:underline"><?=$p?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active" <?= ($website['status']==='active'||$website['status']==='1') ? 'selected' : '' ?>>✅ Active</option>
          <option value="paused" <?= $website['status']==='paused' ? 'selected' : '' ?>>⏸️ Paused</option>
        </select>
      </div>

      <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-success">
          <i class="bi bi-check-circle me-1"></i>Save Changes
        </button>
        <a href="websites.php" class="btn btn-secondary">Cancel</a>
        <a href="websites.php?delete=<?= $id ?>&confirm=1" class="btn btn-danger ms-auto"
           onclick="return confirm('Delete this website and ALL its pages and changes? This cannot be undone.')">
          <i class="bi bi-trash3 me-1"></i>Delete Website
        </a>
      </div>

    </form>
  </div>
</div>

</div>
<script>
function addSkip(pattern) {
    var ta = document.querySelector('[name=skip_urls]');
    var cur = ta.value.trim();
    if (cur.indexOf(pattern) === -1) {
        ta.value = cur ? cur + '
' + pattern : pattern;
    }
}
</script>
<?php pageFooter(); ?>
