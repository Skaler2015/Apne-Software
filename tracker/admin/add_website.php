<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['website_name'] ?? '');
    $url        = trim($_POST['website_url']  ?? '');
    $sitemapUrl = trim($_POST['sitemap_url']  ?? '');
    $status     = $_POST['status'] ?? 'active';
    $skipUrls   = trim($_POST['skip_urls']    ?? '');

    if (!$name) $errors[] = 'Website name is required.';
    if (!$url)  $errors[] = 'Website URL is required.';
    if ($url && !filter_var($url, FILTER_VALIDATE_URL)) $errors[] = 'Please enter a valid URL (include https://).';
    if (!in_array($status, ['active','paused'])) $status = 'active';

    if (!$errors) {
        $url = rtrim($url, '/');
        try {
            $pdo->prepare("INSERT INTO websites (website_name, website_url, sitemap_url, status) VALUES (?,?,?,?)")
                ->execute([$name, $url, $sitemapUrl, $status]);
            $newId = $pdo->lastInsertId();

            // Save skip/exclude URLs
            if ($skipUrls && $newId) {
                $lines = array_filter(array_map('trim', explode("\n", $skipUrls)));
                foreach ($lines as $line) {
                    if ($line) {
                        try {
                            $pdo->prepare("INSERT INTO excluded_pages (website_id, url_pattern, match_type, reason) VALUES (?,?,'contains','Added on website creation')")
                                ->execute([$newId, $line]);
                        } catch(Exception $e2) {}
                    }
                }
            }

            flash("✅ Website '{$name}' added!");
            header('Location: websites.php'); exit;
        } catch(Exception $e) {
            // Fallback without sitemap_url
            try {
                $pdo->prepare("INSERT INTO websites (website_name, website_url, status) VALUES (?,?,?)")
                    ->execute([$name, $url, $status]);
                $newId = $pdo->lastInsertId();
                try { $pdo->exec("ALTER TABLE websites ADD COLUMN sitemap_url VARCHAR(500) NULL"); } catch(Exception $e3){}
                if ($sitemapUrl) $pdo->prepare("UPDATE websites SET sitemap_url=? WHERE id=?")->execute([$sitemapUrl, $newId]);
                flash("✅ Website '{$name}' added!");
                header('Location: websites.php'); exit;
            } catch(Exception $e4) {
                $errors[] = "Error: " . $e4->getMessage();
            }
        }
    }
}

pageHeader('Add Website');
?>
<div class="container py-4" style="max-width:680px">

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="websites.php">Websites</a></li>
    <li class="breadcrumb-item active">Add Website</li>
  </ol>
</nav>

<div class="d-flex align-items-center gap-3 mb-4">
  <div style="background:rgba(124,92,252,.15);border:1px solid rgba(124,92,252,.3);border-radius:12px;padding:10px 14px;font-size:1.4rem">🌐</div>
  <div>
    <h1 class="page-title">Add Website</h1>
    <p class="text-muted mb-0" style="font-size:.85rem">Add a new website for change monitoring</p>
  </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
  <?php foreach ($errors as $e): ?>
  <div><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-body p-4">
    <form method="post">

      <!-- Website Name -->
      <div class="mb-4">
        <label class="form-label">Website Name <span style="color:var(--red)">*</span></label>
        <input type="text" name="website_name" class="form-control"
               placeholder="e.g. Sarkari Result"
               value="<?= htmlspecialchars($_POST['website_name'] ?? '') ?>" required>
        <div class="form-text" style="color:var(--dim)">A friendly name to identify this website</div>
      </div>

      <!-- Website URL -->
      <div class="mb-4">
        <label class="form-label">Website URL <span style="color:var(--red)">*</span></label>
        <input type="url" name="website_url" class="form-control" id="websiteUrl"
               placeholder="https://example.com"
               value="<?= htmlspecialchars($_POST['website_url'] ?? '') ?>"
               oninput="updateSuggestions(this.value)"
               required>
        <div class="form-text" style="color:var(--dim)">Full URL including https://</div>
      </div>

      <!-- Sitemap URL -->
      <div class="mb-4">
        <label class="form-label">
          <i class="bi bi-map me-1" style="color:var(--accent)"></i>
          Sitemap URL
          <span style="background:rgba(22,192,121,.15);color:var(--green);border-radius:10px;padding:1px 8px;font-size:.7rem;font-weight:700;margin-left:4px">Recommended</span>
        </label>
        <input type="url" name="sitemap_url" class="form-control" id="sitemapUrl"
               placeholder="https://example.com/post-sitemap.xml"
               value="<?= htmlspecialchars($_POST['sitemap_url'] ?? '') ?>">
        <div class="form-text" style="color:var(--dim)">
          Sitemap XML URL — naye posts jaldi discover honge
        </div>
        <div class="mt-2" style="font-size:.75rem;color:var(--dim)">
          Quick fill:
          <a href="#" onclick="fillSitemap('/post-sitemap.xml');return false" style="color:var(--blue);margin-left:6px">post-sitemap.xml</a>
          <a href="#" onclick="fillSitemap('/sitemap_index.xml');return false" style="color:var(--blue);margin-left:8px">sitemap_index.xml</a>
          <a href="#" onclick="fillSitemap('/sitemap.xml');return false" style="color:var(--blue);margin-left:8px">sitemap.xml</a>
        </div>
      </div>

      <!-- Skip/Exclude URLs -->
      <div class="mb-4">
        <label class="form-label">
          <i class="bi bi-slash-circle me-1" style="color:var(--red)"></i>
          Skip These URLs
          <span style="color:var(--dim);font-size:.75rem;font-weight:400;margin-left:4px">(optional)</span>
        </label>
        <textarea name="skip_urls" class="form-control" rows="3"
                  placeholder="/disclaimer&#10;/about&#10;/contact&#10;/category/&#10;/tag/"
                  style="font-family:monospace;font-size:.82rem"><?= htmlspecialchars($_POST['skip_urls'] ?? '') ?></textarea>
        <div class="form-text" style="color:var(--dim)">
          Ek line mein ek URL ya pattern — in pages ko scan nahi kiya jaayega।
          <b>Contains</b> match hoti hai — e.g. <code>/category/</code> se sab category pages skip।
        </div>
        <div class="mt-2" style="font-size:.75rem">
          <span style="color:var(--dim)">Common:</span>
          <?php
          $common = ['/disclaimer','/about','/contact','/privacy-policy','/category/','/tag/','/page/','/sitemap'];
          foreach($common as $p):
          ?>
          <a href="#" onclick="addSkip('<?=$p?>');return false"
             style="color:var(--blue);margin-left:6px;text-decoration:underline"><?=$p?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Status -->
      <div class="mb-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>✅ Active — Scan this website</option>
          <option value="paused" <?= ($_POST['status'] ?? '') === 'paused' ? 'selected' : '' ?>>⏸️ Paused — Skip for now</option>
        </select>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-plus-circle me-1"></i>Add Website
        </button>
        <a href="websites.php" class="btn btn-secondary">Cancel</a>
      </div>

    </form>
  </div>
</div>

</div>

<script>
function updateSuggestions(val) {
    // Auto-suggest sitemap based on URL
    try {
        var base = new URL(val);
        var origin = base.origin;
        document.getElementById('sitemapUrl').placeholder = origin + '/post-sitemap.xml';
    } catch(e) {}
}

function fillSitemap(suffix) {
    var urlVal = document.getElementById('websiteUrl').value;
    if (!urlVal) { alert('Please enter Website URL first'); return; }
    try {
        var base = new URL(urlVal);
        document.getElementById('sitemapUrl').value = base.origin + suffix;
    } catch(e) {
        document.getElementById('sitemapUrl').value = urlVal.replace(/\/$/, '') + suffix;
    }
}

function addSkip(pattern) {
    var ta = document.querySelector('[name=skip_urls]');
    var cur = ta.value.trim();
    if (cur.indexOf(pattern) === -1) {
        ta.value = cur ? cur + '\n' + pattern : pattern;
    }
}
</script>

<?php pageFooter(); ?>
