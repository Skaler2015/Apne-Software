<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/layout.php';
require_once '../cron/content_filter.php';

$url = trim($_POST['url'] ?? $_GET['url'] ?? '');
$result = null;

if ($url && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true, CURLOPT_TIMEOUT=>15,
        CURLOPT_USERAGENT=>'Mozilla/5.0', CURLOPT_NOSIGNAL=>1]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($html && $code < 400) {
        $processed = SmartContentFilter::process($html, $url);
        $normalized = SmartContentFilter::normalize($processed);
        $fingerprint = SmartContentFilter::fingerprint($normalized);
        $changeType  = SmartContentFilter::detectChangeType('', $processed, $url);

        $result = [
            'original_size'   => strlen(strip_tags($html)),
            'processed_size'  => strlen($processed),
            'reduction'       => round((1 - strlen($processed)/max(1,strlen(strip_tags($html))))*100, 1),
            'processed'       => mb_substr($processed, 0, 1000),
            'fingerprint'     => mb_substr($fingerprint, 0, 300),
            'word_count'      => str_word_count($processed),
            'change_type'     => $changeType,
        ];
    } else {
        $result = ['error' => "HTTP {$code} or fetch failed"];
    }
}

pageHeader('Filter Test');
?>
<div class="container py-4" style="max-width:900px">
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Filter Test</li>
  </ol>
</nav>

<div class="d-flex align-items-center gap-3 mb-4">
  <div style="background:rgba(250,204,21,.15);border:1px solid rgba(250,204,21,.3);border-radius:12px;padding:10px 14px;font-size:1.4rem">🔬</div>
  <div>
    <h1 class="page-title">Content Filter Test</h1>
    <p class="text-muted mb-0" style="font-size:.85rem">Kisi bhi URL ka filtered content dekho — exactly wahi jo scan mein store hoga</p>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body p-4">
    <form method="post">
      <div class="d-flex gap-2">
        <input type="url" name="url" class="form-control"
               placeholder="https://sarkariresult.com.cm/ssc-je-2026"
               value="<?=htmlspecialchars($url)?>">
        <button type="submit" class="btn btn-primary" style="white-space:nowrap">
          <i class="bi bi-funnel me-1"></i>Test Filter
        </button>
      </div>
    </form>
  </div>
</div>

<?php if ($result): ?>
<?php if (isset($result['error'])): ?>
<div class="alert alert-danger"><?=$result['error']?></div>
<?php else: ?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-4">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:1.4rem;font-weight:800;color:var(--dim)"><?=number_format($result['original_size'])?></div>
      <div style="font-size:.72rem;color:var(--dim)">Original chars</div>
    </div>
  </div>
  <div class="col-4">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:1.4rem;font-weight:800;color:var(--green)"><?=number_format($result['processed_size'])?></div>
      <div style="font-size:.72rem;color:var(--dim)">After filter</div>
    </div>
  </div>
  <div class="col-4">
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:1.4rem;font-weight:800;color:var(--accent)"><?=$result['reduction']?>%</div>
      <div style="font-size:.72rem;color:var(--dim)">Noise removed</div>
    </div>
  </div>
</div>

<!-- Change type detected -->
<div class="card mb-3">
  <div class="card-header"><i class="bi bi-tag me-2"></i>Auto-Detected Change Type</div>
  <div class="card-body p-3">
    <?php $ct = $result['change_type']; ?>
    <span style="background:rgba(22,192,121,.15);color:var(--green);padding:4px 12px;border-radius:6px;font-weight:700">
      <?=$ct['type']?>
    </span>
    <span style="margin-left:12px;color:var(--dim);font-size:.85rem">
      Priority: <b style="color:var(--accent)"><?=$ct['priority']?>/10</b>
      &nbsp;·&nbsp;
      Important: <b style="color:<?=$ct['important']?'var(--red)':'var(--dim)'?>"><?=$ct['important']?'Yes':'No'?></b>
    </span>
  </div>
</div>

<!-- Processed content -->
<div class="card mb-3">
  <div class="card-header"><i class="bi bi-file-text me-2"></i>Processed Content (first 1000 chars)</div>
  <div class="card-body p-3">
    <div style="font-family:monospace;font-size:.78rem;line-height:1.6;color:var(--txt);white-space:pre-wrap;max-height:300px;overflow-y:auto">
<?=htmlspecialchars($result['processed'])?>...
    </div>
  </div>
</div>

<!-- Fingerprint -->
<div class="card">
  <div class="card-header"><i class="bi bi-fingerprint me-2"></i>Keyword Fingerprint (for comparison)</div>
  <div class="card-body p-3">
    <div style="font-family:monospace;font-size:.75rem;color:var(--dim);line-height:1.8;word-break:break-all">
      <?=htmlspecialchars($result['fingerprint'])?>
    </div>
    <div style="font-size:.75rem;color:var(--dim);margin-top:8px">
      <i class="bi bi-info-circle me-1"></i>
      Yeh fingerprint do scans ke beech compare hoti hai — sirf yahan differences change detect hone ka reason hoti hain
    </div>
  </div>
</div>

<?php endif; ?>
<?php endif; ?>
</div>
<?php pageFooter(); ?>
