<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:20px;color:#c00;background:#fff">
    <b>Session expired.</b> <a href="../login.php" target="_top">Login again</a></body></html>';
    exit;
}
require_once '../includes/config.php';
require_once '../includes/db.php';

$id   = (int)(isset($_GET['id'])   ? $_GET['id']   : 0);
$mode =       isset($_GET['mode']) ? $_GET['mode']  : 'new';

if (!$id) { echo '<p style="padding:20px;color:red">Invalid ID</p>'; exit; }
$stmt = $pdo->prepare("SELECT c.*, p.page_url FROM changes c LEFT JOIN pages p ON c.page_id=p.id WHERE c.id=?");
$stmt->execute(array($id));
$row = $stmt->fetch();
if (!$row) { echo '<p style="padding:20px;color:red">Not found</p>'; exit; }

$pageUrl = isset($row['page_url']) ? $row['page_url'] : '';
$oldRaw  = isset($row['old_content']) ? $row['old_content'] : '';
$newRaw  = isset($row['new_content']) ? $row['new_content'] : '';

function deepClean($text) {
    $text = preg_replace('/<[^>]+>/', ' ', $text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\b(class|id|style|href|src|alt|title)\s*=\s*["\'][^"\']*["\']/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

$oldText = deepClean($oldRaw);
$newText = deepClean($newRaw);

function fetchPage($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_NOSIGNAL       => 1,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    return $html ? $html : '';
}

function getChangedWords($oldText, $newText) {
    $stopWords = array('home','menu','more','skip','content','admit','card','syllabus',
        'answer','contact','privacy','policy','disclaimer','download','whatsapp','telegram',
        'instagram','youtube','facebook','copyright','rights','reserved','mobile','apps',
        'search','nbsp','next','prev','back','close','open','login','register','about',
        'terms','naukri','alert','free','jobalert','government','govt','state','central',
        'bank','railway','police','defence','teaching','engineering','mock','test','upcoming',
        'employment','general','female','legal','without','website','official','candidates',
        'check','details','apply','post','date','last','also','view','click','here','given',
        'below','above','please','note','important','this','that','with','from','have',
        'will','your','their','into','which','when','what','been','were','sarkari','result',
        'latest','admission','online','form','notification');
    preg_match_all('/[a-zA-Z][a-zA-Z0-9]{2,}/u', strtolower($oldText), $oM);
    preg_match_all('/[a-zA-Z][a-zA-Z0-9]{2,}/u', strtolower($newText), $nM);
    $oldWords = array_flip(array_unique($oM[0]));
    $newWords = array_flip(array_unique($nM[0]));
    $added = array(); $removed = array();
    foreach ($newWords as $w => $v) {
        if (strlen($w)<4 || in_array($w,$stopWords)) continue;
        if (!isset($oldWords[$w])) $added[] = $w;
    }
    foreach ($oldWords as $w => $v) {
        if (strlen($w)<4 || in_array($w,$stopWords)) continue;
        if (!isset($newWords[$w])) $removed[] = $w;
    }
    return array('added'=>array_values(array_slice($added,0,60)), 'removed'=>array_values(array_slice($removed,0,60)));
}

// ── COMMON INJECT STYLES & SCRIPT ───────────────────────
function getHighlightScript($addedJson, $removedJson, $mode) {
    $barColor  = $mode === 'new' ? '#7c3aed' : '#ef4444';
    $barTitle  = $mode === 'new' ? '&#128269; Live Page — Changes Highlighted' : '&#128203; Old Snapshot — Removed text highlighted';
    $addedDot  = $mode === 'new' ? '<span style="display:flex;align-items:center;gap:3px"><span style="width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block;flex-shrink:0"></span><span style="font-size:11px">Added</span></span>' : '';
    $removedDot = '<span style="display:flex;align-items:center;gap:3px"><span style="width:8px;height:8px;border-radius:50%;background:#ef4444;display:inline-block;flex-shrink:0"></span><span style="font-size:11px">Removed</span></span>';

    return "
<style>
.ct-add{background:#bbf7d0!important;color:#14532d!important;border-radius:3px;padding:0 3px;outline:2px solid #22c55e;font-weight:700}
.ct-rem{background:#fee2e2!important;color:#991b1b!important;border-radius:3px;padding:0 3px;text-decoration:line-through}
.ct-current{outline:3px solid #f59e0b!important;background:#fef9c3!important;color:#78350f!important}
#ct-bar{position:fixed;top:0;left:0;right:0;z-index:2147483647;background:#0f172a;color:#e2e8f0;
        font-family:'Segoe UI',sans-serif;font-size:12px;padding:5px 12px;
        display:flex;align-items:center;gap:10px;flex-wrap:wrap;
        border-bottom:3px solid {$barColor};box-shadow:0 2px 10px rgba(0,0,0,.7)}
#ct-nav{display:flex;align-items:center;gap:5px;margin-left:auto}
#ct-nav button{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);
               color:#fff;padding:2px 9px;border-radius:4px;cursor:pointer;font-size:11px}
</style>
<div id='ct-bar'>
  <b style='font-size:12px;white-space:nowrap'>{$barTitle}</b>
  {$addedDot}
  {$removedDot}
  <div id='ct-nav'>
    <span id='ct-info' style='font-size:11px;color:rgba(255,255,255,.7)'>scanning...</span>
    <button onclick='ctNav(-1)'>&#8593;</button>
    <button onclick='ctNav(1)'>&#8595;</button>
  </div>
</div>
<script>
(function(){
  var added   = {$addedJson};
  var removed = {$removedJson};
  var hlNodes = []; var cur = -1;
  function esc(s){ return s.replace(/[-\/\\\\^$*+?.()|[\\]{}]/g,'\\\\$&'); }
  function hl(node) {
    var text = node.textContent;
    if(!text||text.trim().length<2) return;
    var safe=text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    var out=safe; var hit=false;
    for(var a=0;a<added.length;a++){
      if(!added[a]||added[a].length<3) continue;
      var rA=new RegExp('(?<![a-zA-Z0-9])('+esc(added[a])+')(?![a-zA-Z0-9])','gi');
      if(rA.test(out)){out=out.replace(rA,'<span class=\"ct-add\" data-hl=\"1\">\$1</span>');hit=true;}
    }
    for(var r=0;r<removed.length;r++){
      if(!removed[r]||removed[r].length<3) continue;
      var rR=new RegExp('(?<![a-zA-Z0-9])('+esc(removed[r])+')(?![a-zA-Z0-9])','gi');
      if(rR.test(out)){out=out.replace(rR,'<span class=\"ct-rem\" data-hl=\"1\">\$1</span>');hit=true;}
    }
    if(hit&&node.parentNode){var w=document.createElement('span');w.innerHTML=out;node.parentNode.replaceChild(w,node);}
  }
  function run(){
    if(!document.body) return;
    document.body.style.paddingTop='34px';
    if(!added.length&&!removed.length){document.getElementById('ct-info').textContent='no changes';return;}
    var walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT,
      {acceptNode:function(n){
        var p=n.parentNode;if(!p)return 2;
        var t=(p.tagName||'').toUpperCase();
        if(['SCRIPT','STYLE','NOSCRIPT','TEXTAREA','INPUT','SELECT'].indexOf(t)>=0)return 2;
        if(!n.textContent.trim())return 3;
        return 1;
      }},false);
    var nodes=[];var nd;
    while((nd=walker.nextNode()))nodes.push(nd);
    for(var i=0;i<nodes.length;i++){try{hl(nodes[i]);}catch(e){}}
    hlNodes=Array.prototype.slice.call(document.querySelectorAll('[data-hl]'));
    var info=document.getElementById('ct-info');
    if(!hlNodes.length){info.textContent='no highlights';}
    else{info.textContent=hlNodes.length+' changes';setTimeout(function(){ctNav(1);},400);}
  }
  window.ctNav=function(dir){
    if(!hlNodes.length)return;
    if(cur>=0)hlNodes[cur].classList.remove('ct-current');
    cur=(cur+dir+hlNodes.length)%hlNodes.length;
    hlNodes[cur].classList.add('ct-current');
    hlNodes[cur].scrollIntoView({behavior:'smooth',block:'center'});
    document.getElementById('ct-info').textContent=(cur+1)+'/'+hlNodes.length;
  };
  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',run);}
  else{setTimeout(run,150);}
})();
</script>";
}

$diff        = getChangedWords($oldText, $newText);
$addedJson   = json_encode($diff['added']);
$removedJson = json_encode($diff['removed']);

// ── NEW MODE: Live page with highlights ──────────────────
if ($mode === 'new') {
    $html = fetchPage($pageUrl);
    if (!$html) {
        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:30px;color:#555;background:#fff">';
        echo '<p>&#9888; Could not fetch live page. <a href="'.htmlspecialchars($pageUrl).'" target="_top">Open directly &#8599;</a></p>';
        echo '</body></html>';
        exit;
    }
    $scheme = parse_url($pageUrl, PHP_URL_SCHEME);
    $host   = parse_url($pageUrl, PHP_URL_HOST);
    $base   = $scheme . '://' . $host;
    $html   = preg_replace('/(href|src|action)="\/([^"]*)"/', '$1="'.$base.'/$2"', $html);
    $html   = preg_replace("/(href|src|action)='\/([^']*)'/", '$1=\''.$base.'/$2\'', $html);
    $html   = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);

    $inject = getHighlightScript($addedJson, $removedJson, 'new');

    if (strpos($html, '</body>') !== false) {
        $html = str_replace('</body>', $inject . '</body>', $html);
    } else { $html .= $inject; }
    echo $html;
    exit;
}

// ── OLD MODE: Fetch the SAME page but highlight removed words ──
// Strategy: fetch live page, but highlight REMOVED words (what was there before)
// This shows the page layout + what was previously there

// First try to fetch live page for old snapshot rendering
$liveHtml = fetchPage($pageUrl);

if ($liveHtml) {
    // Use LIVE page HTML but highlight REMOVED words (what existed in old content)
    $scheme = parse_url($pageUrl, PHP_URL_SCHEME);
    $host   = parse_url($pageUrl, PHP_URL_HOST);
    $base   = $scheme . '://' . $host;
    $liveHtml = preg_replace('/(href|src|action)="\/([^"]*)"/', '$1="'.$base.'/$2"', $liveHtml);
    $liveHtml = preg_replace("/(href|src|action)='\/([^']*)'/", '$1=\''.$base.'/$2\'', $liveHtml);
    $liveHtml = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $liveHtml);

    // For old mode: show removed words (in old but not in new) + added words inversed
    $oldDiff = getChangedWords($newText, $oldText); // Reversed — what's in OLD not in NEW
    $oldAddedJson   = json_encode($oldDiff['added']);   // Words only in old content
    $oldRemovedJson = json_encode(array());              // Nothing to "remove" in old view

    $inject = getHighlightScript($oldAddedJson, $oldRemovedJson, 'old');

    if (strpos($liveHtml, '</body>') !== false) {
        $liveHtml = str_replace('</body>', $inject . '</body>', $liveHtml);
    } else { $liveHtml .= $inject; }

    echo $liveHtml;

} else {
    // Fallback: styled text rendering if live page unavailable
    $removedWords = $diff['removed'];
    $sentences = preg_split('/(?<=[.!?\n])\s+/', $oldText);
    $body = '';
    foreach ($sentences as $sent) {
        $sent = trim($sent);
        if (!$sent || strlen($sent) < 3) continue;
        $safe = htmlspecialchars($sent);
        foreach ($removedWords as $w) {
            if (strlen($w) < 4) continue;
            $re   = '/(?<![a-zA-Z0-9])(' . preg_quote(htmlspecialchars($w), '/') . ')(?![a-zA-Z0-9])/i';
            $safe = preg_replace($re, '<span class="ct-rem">$1</span>', $safe);
        }
        $plain = strip_tags($sent);
        $isH = strlen($plain)<100 && preg_match('/recruitment|result|admit|syllabus|vacancy|notification|answer\s*key|\d{4}/i', $plain);
        $body .= $isH ? "<h3>{$safe}</h3>" : "<p>{$safe}</p>";
    }

    $inject = getHighlightScript('[]', json_encode($removedWords), 'old');

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
*{box-sizing:border-box}
body{font-family:"Segoe UI",sans-serif;font-size:13.5px;line-height:1.8;color:#1e293b;
     margin:0;padding:38px 16px 16px;background:#fff}
h3{font-size:.88rem;font-weight:700;margin:14px 0 4px;color:#0f172a;
   border-left:3px solid #ef4444;padding:3px 8px;background:#fff5f5;border-radius:0 4px 4px 0}
p{margin:0 0 8px;color:#334155}
.ct-rem{background:#fee2e2;color:#991b1b;border-radius:3px;padding:0 3px;text-decoration:line-through;font-weight:600}
</style>
</head><body>
'.($body ?: '<em style="color:#94a3b8;padding:20px;display:block">No content stored</em>').'
'.$inject.'
</body></html>';
}
