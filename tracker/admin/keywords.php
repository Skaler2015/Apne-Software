<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';

// Setup keywords table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS keywords (
        id INT AUTO_INCREMENT PRIMARY KEY,
        keyword VARCHAR(200) NOT NULL,
        website_id INT NULL,
        alert_type ENUM('any','added','removed') DEFAULT 'any',
        is_active TINYINT(1) DEFAULT 1,
        match_count INT DEFAULT 0,
        created_at DATETIME DEFAULT NOW()
    )");
} catch(Exception $e) {}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM keywords WHERE id=?")->execute([(int)$_GET['delete']]);
    flash('Keyword deleted.'); header('Location: keywords.php'); exit;
}
if (isset($_GET['toggle'])) {
    $k = $pdo->prepare("SELECT is_active FROM keywords WHERE id=?");
    $k->execute([(int)$_GET['toggle']]);
    $cur = $k->fetchColumn();
    $pdo->prepare("UPDATE keywords SET is_active=? WHERE id=?")->execute([$cur?0:1,(int)$_GET['toggle']]);
    header('Location: keywords.php'); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $kw  = trim($_POST['keyword'] ?? '');
    $wid = (int)($_POST['website_id'] ?? 0);
    $at  = $_POST['alert_type'] ?? 'any';
    if ($kw) {
        $pdo->prepare("INSERT INTO keywords (keyword,website_id,alert_type) VALUES(?,?,?)")
            ->execute([$kw, $wid?:null, $at]);
        flash("✅ Keyword '{$kw}' added!");
    }
    header('Location: keywords.php'); exit;
}

$keywords = $pdo->query("
    SELECT k.*, w.website_name
    FROM keywords k LEFT JOIN websites w ON k.website_id=w.id
    ORDER BY k.id DESC
")->fetchAll();
$websites = $pdo->query("SELECT id,website_name FROM websites ORDER BY website_name")->fetchAll();

pageHeader('Keyword Filters');
?>
<div class="container py-4" style="max-width:800px">
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Keyword Filters</li>
  </ol>
</nav>
<div class="d-flex align-items-center gap-3 mb-4">
  <div style="background:rgba(250,204,21,.15);border:1px solid rgba(250,204,21,.3);border-radius:12px;padding:10px 14px;font-size:1.4rem">🏷️</div>
  <div>
    <h1 class="page-title">Keyword Filters</h1>
    <p class="text-muted mb-0" style="font-size:.85rem">Sirf specific keywords wale changes dekho</p>
  </div>
</div>
<?php showFlash(); ?>

<!-- Add form -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-plus-circle me-2"></i>Add Keyword</div>
  <div class="card-body p-4">
    <form method="post">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">Keyword <span style="color:var(--red)">*</span></label>
          <input type="text" name="keyword" class="form-control" placeholder="e.g. result, admit card, vacancy" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Website</label>
          <select name="website_id" class="form-select">
            <option value="">All Websites</option>
            <?php foreach($websites as $w): ?>
            <option value="<?=$w['id']?>"><?=htmlspecialchars($w['website_name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Alert on</label>
          <select name="alert_type" class="form-select">
            <option value="any">Any change</option>
            <option value="added">Word added</option>
            <option value="removed">Word removed</option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" class="btn btn-primary w-100">Add</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Quick add chips -->
<div class="mb-3">
  <span style="font-size:.78rem;color:var(--dim)">Quick add:</span>
  <?php foreach(['result','admit card','answer key','vacancy','apply online','last date','notification','syllabus','cut off'] as $q): ?>
  <button onclick="document.querySelector('[name=keyword]').value='<?=$q?>'" 
          class="btn btn-sm btn-outline-secondary ms-1" style="font-size:.72rem;padding:2px 8px">
    <?=$q?>
  </button>
  <?php endforeach; ?>
</div>

<!-- Keywords list -->
<?php if(!$keywords): ?>
<div class="card"><div class="card-body text-center py-4" style="color:var(--dim)">
  <i class="bi bi-tag" style="font-size:2rem"></i>
  <p class="mt-2 mb-0">No keywords added yet</p>
</div></div>
<?php else: ?>
<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr>
        <th>Keyword</th><th>Website</th><th>Alert On</th><th>Matches</th><th>Status</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach($keywords as $k): ?>
      <tr>
        <td style="font-weight:700">
          <i class="bi bi-tag me-1" style="color:var(--yellow)"></i>
          <?=htmlspecialchars($k['keyword'])?>
        </td>
        <td style="font-size:.82rem"><?=htmlspecialchars($k['website_name']??'All')?></td>
        <td>
          <span style="font-size:.75rem;padding:2px 8px;border-radius:10px;
            background:<?=$k['alert_type']==='added'?'rgba(22,192,121,.15)':($k['alert_type']==='removed'?'rgba(239,68,68,.15)':'rgba(96,165,250,.15)')?>">
            <?=ucfirst($k['alert_type'])?>
          </span>
        </td>
        <td style="font-weight:700;color:var(--accent)"><?=$k['match_count']?></td>
        <td>
          <a href="?toggle=<?=$k['id']?>" class="badge-<?=$k['is_active']?'active':'paused'?>">
            <?=$k['is_active']?'Active':'Paused'?>
          </a>
        </td>
        <td>
          <a href="?delete=<?=$k['id']?>" class="btn btn-sm btn-danger" style="padding:2px 7px"
             onclick="return confirm('Delete keyword?')">
            <i class="bi bi-trash3" style="font-size:.72rem"></i>
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- How it works -->
<div class="mt-4 p-3" style="background:rgba(96,165,250,.08);border:1px solid rgba(96,165,250,.2);border-radius:10px;font-size:.82rem;color:var(--dim)">
  <i class="bi bi-info-circle me-1" style="color:var(--blue)"></i>
  <b style="color:var(--blue)">How it works:</b> Changes page par "Keyword Filter" toggle karoge to sirf un changes mein se dikhega jisme yeh keywords hain. 
  Next scan se keyword matching automatically track hogi.
</div>
</div>
<?php pageFooter(); ?>
