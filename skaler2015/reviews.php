<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
require_once '../backend/config.php';

$pdo = get_db_connection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($action === 'approve') {
            $pdo->prepare("UPDATE tool_reviews SET status='approved' WHERE id=?")->execute([$id]);
        } elseif ($action === 'spam') {
            $pdo->prepare("UPDATE tool_reviews SET status='spam' WHERE id=?")->execute([$id]);
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM tool_reviews WHERE id=?")->execute([$id]);
        }
    }
    header('Location: reviews.php?status=' . ($_GET['status'] ?? 'all') . '&msg=done');
    exit;
}

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$toolFilter   = trim($_GET['tool'] ?? '');
$dateFilter   = trim($_GET['date'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 25;

// Build query
$where = [];
$params = [];
if ($statusFilter !== 'all') { $where[] = "status=?"; $params[] = $statusFilter; }
if ($toolFilter) { $where[] = "tool_slug LIKE ?"; $params[] = "%$toolFilter%"; }
if ($dateFilter) { $where[] = "DATE(created_at)=?"; $params[] = $dateFilter; }
$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tool_reviews $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

// Fetch
$stmt = $pdo->prepare(
    "SELECT * FROM tool_reviews $whereStr ORDER BY created_at DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Stats
$stats = $pdo->query("SELECT
    COUNT(*) as total,
    SUM(status='approved') as approved,
    SUM(status='pending') as pending,
    SUM(status='spam') as spam,
    AVG(rating) as avg_rating
    FROM tool_reviews")->fetch();

// Tool list for filter
$tools = $pdo->query("SELECT DISTINCT tool_slug, tool_name FROM tool_reviews ORDER BY tool_slug")->fetchAll();
?>
<style>
.rev-table{width:100%;border-collapse:collapse;font-size:.85rem}
.rev-table th{background:rgba(255,255,255,.07);padding:10px 12px;text-align:left;font-size:.78rem;font-weight:700;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid rgba(255,255,255,.1)}
.rev-table td{padding:11px 12px;border-bottom:1px solid rgba(255,255,255,.07);vertical-align:top}
.rev-table tr:hover td{background:rgba(255,255,255,.03)}
.stars{color:#FBBF24;font-size:.95rem}
.tag{display:inline-block;padding:2px 8px;border-radius:6px;font-size:.7rem;font-weight:700}
.tag-approved{background:rgba(22,192,121,.2);color:#34D399}
.tag-pending{background:rgba(251,191,36,.15);color:#FBBF24}
.tag-spam{background:rgba(239,68,68,.2);color:#F87171}
.act-btn{padding:4px 10px;border-radius:6px;border:none;cursor:pointer;font-size:.72rem;font-weight:700;margin-right:4px;transition:.15s}
.act-approve{background:rgba(22,192,121,.2);color:#34D399}
.act-spam{background:rgba(251,191,36,.15);color:#FBBF24}
.act-del{background:rgba(239,68,68,.2);color:#F87171}
.act-btn:hover{opacity:.8;transform:scale(.97)}
.filter-form{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;align-items:flex-end}
.filter-form select,.filter-form input{padding:8px 12px;border-radius:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);color:#fff;font-size:.82rem}
.rev-comment{color:rgba(255,255,255,.8);line-height:1.5;max-width:340px}
.slug-link{color:#A78BFA;font-weight:600;font-size:.78rem}
.reviewer{font-weight:700;color:#fff;font-size:.82rem}
.rev-date{color:rgba(255,255,255,.45);font-size:.75rem;white-space:nowrap}
.stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:24px}
.stat-pill{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:14px;text-align:center}
.stat-pill .n{font-size:1.5rem;font-weight:800;color:#A78BFA}
.stat-pill .l{font-size:.73rem;color:rgba(255,255,255,.5);margin-top:2px}
.pagination{display:flex;gap:6px;margin-top:18px;justify-content:center}
.pag-btn{padding:6px 12px;border-radius:7px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.7);font-size:.8rem;text-decoration:none;transition:.15s}
.pag-btn.active,.pag-btn:hover{background:rgba(124,92,252,.3);border-color:rgba(124,92,252,.5);color:#A78BFA}
.msg-ok{background:rgba(22,192,121,.15);border:1px solid rgba(22,192,121,.3);border-radius:8px;padding:8px 14px;color:#34D399;font-size:.83rem;margin-bottom:14px}
</style>
<?php
$pageTitle    = 'Reviews';
$pageSubtitle = 'User ratings and comments for all tools';
$activeNav    = 'reviews';
include 'includes/header.php';
?>
<div class="admin-content">

    <?php if(isset($_GET['msg'])): ?>
    <div class="msg-ok">✅ Action completed successfully.</div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-pill"><div class="n"><?= number_format($stats['total']) ?></div><div class="l">Total Reviews</div></div>
      <div class="stat-pill"><div class="n" style="color:#34D399"><?= $stats['approved'] ?></div><div class="l">Approved</div></div>
      <div class="stat-pill"><div class="n" style="color:#FBBF24"><?= $stats['pending'] ?></div><div class="l">Pending</div></div>
      <div class="stat-pill"><div class="n" style="color:#F87171"><?= $stats['spam'] ?></div><div class="l">Spam</div></div>
      <div class="stat-pill"><div class="n" style="color:#FBBF24"><?= number_format((float)$stats['avg_rating'],1) ?>★</div><div class="l">Avg Rating</div></div>
    </div>

    <!-- Filters -->
    <form method="GET" class="filter-form">
      <div>
        <label style="font-size:.75rem;color:rgba(255,255,255,.5);display:block;margin-bottom:4px">Status</label>
        <select name="status" onchange="this.form.submit()">
          <option value="all" <?=!$statusFilter||$statusFilter==='all'?'selected':''?>>All Status</option>
          <option value="approved" <?=$statusFilter==='approved'?'selected':''?>>Approved</option>
          <option value="pending" <?=$statusFilter==='pending'?'selected':''?>>Pending</option>
          <option value="spam" <?=$statusFilter==='spam'?'selected':''?>>Spam</option>
        </select>
      </div>
      <div>
        <label style="font-size:.75rem;color:rgba(255,255,255,.5);display:block;margin-bottom:4px">Tool</label>
        <select name="tool" onchange="this.form.submit()">
          <option value="">All Tools</option>
          <?php foreach($tools as $t): ?>
          <option value="<?=htmlspecialchars($t['tool_slug'])?>" <?=$toolFilter===$t['tool_slug']?'selected':''?>>
            <?=htmlspecialchars($t['tool_name']?:$t['tool_slug'])?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label style="font-size:.75rem;color:rgba(255,255,255,.5);display:block;margin-bottom:4px">Date</label>
        <input type="date" name="date" value="<?=htmlspecialchars($dateFilter)?>" onchange="this.form.submit()">
      </div>
      <a href="reviews.php" style="padding:8px 14px;border-radius:8px;background:rgba(255,255,255,.08);color:rgba(255,255,255,.6);font-size:.8rem;text-decoration:none;align-self:flex-end">Clear</a>
    </form>

    <!-- Table -->
    <div class="admin-table-wrap">
      <table class="rev-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Tool</th>
            <th>Reviewer</th>
            <th>Rating</th>
            <th>Comment</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($reviews)): ?>
          <tr><td colspan="7" style="text-align:center;color:rgba(255,255,255,.3);padding:30px">No reviews found.</td></tr>
          <?php else: ?>
          <?php foreach($reviews as $r): ?>
          <tr>
            <td class="rev-date">
              <?= date('d M Y', strtotime($r['created_at'])) ?><br>
              <span style="font-size:.7rem"><?= date('h:i A', strtotime($r['created_at'])) ?></span>
            </td>
            <td>
              <a href="<?= '../tools/'.$r['tool_slug'].'.html' ?>" target="_blank" class="slug-link">
                <?= htmlspecialchars($r['tool_name'] ?: $r['tool_slug']) ?>
              </a>
            </td>
            <td>
              <span class="reviewer"><?= htmlspecialchars($r['reviewer_name'] ?: 'Anonymous') ?></span>
            </td>
            <td><span class="stars"><?= str_repeat('★',$r['rating']) ?><span style="color:rgba(255,255,255,.2)"><?= str_repeat('★',5-$r['rating']) ?></span></span></td>
            <td><p class="rev-comment"><?= nl2br(htmlspecialchars($r['comment'])) ?></p></td>
            <td><span class="tag tag-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
            <td>
              <form method="POST" style="display:inline">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <?php if($r['status'] !== 'approved'): ?>
                <button name="action" value="approve" class="act-btn act-approve">✓ Approve</button>
                <?php endif; ?>
                <?php if($r['status'] !== 'spam'): ?>
                <button name="action" value="spam" class="act-btn act-spam">⚑ Spam</button>
                <?php endif; ?>
                <button name="action" value="delete" class="act-btn act-del" onclick="return confirm('Delete this review?')">✕ Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if($totalPages > 1): ?>
    <div class="pagination">
      <?php for($i=1;$i<=$totalPages;$i++): ?>
      <a href="?status=<?=$statusFilter?>&tool=<?=$toolFilter?>&date=<?=$dateFilter?>&p=<?=$i?>" class="pag-btn <?=$i===$page?'active':''?>"><?=$i?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

</div>
<?php include 'includes/footer.php'; ?>
