<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Real-Time';
$pageSubtitle = 'Live visitors and the most recent activity (auto-refreshes every 8 seconds)';
$activeNav = 'realtime';
include __DIR__ . '/includes/header.php';
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="icon"><span class="live-dot"></span>Live</div>
    <div class="num" id="liveCount">—</div>
    <div class="lbl">Active visitors (last 5 min)</div>
  </div>
</div>

<div class="panel-card">
  <h2>🕐 Last 100 Activities</h2>
  <div id="activityFeed" style="max-height:520px;overflow-y:auto">
    <div class="empty-state">Loading...</div>
  </div>
</div>

<script>
function timeAgo(dateStr){
  const seconds = Math.floor((new Date() - new Date(dateStr.replace(' ','T'))) / 1000);
  if(seconds < 60) return seconds + 's ago';
  if(seconds < 3600) return Math.floor(seconds/60) + 'm ago';
  if(seconds < 86400) return Math.floor(seconds/3600) + 'h ago';
  return Math.floor(seconds/86400) + 'd ago';
}

async function refreshFeed(){
  try{
    const res = await fetch('realtime_feed.php', { cache: 'no-store' });
    const data = await res.json();
    document.getElementById('liveCount').textContent = data.live_count;

    const feed = document.getElementById('activityFeed');
    if(!data.activities.length){
      feed.innerHTML = '<div class="empty-state">No activity yet.</div>';
      return;
    }
    feed.innerHTML = data.activities.map(a => `
      <div style="display:flex;align-items:center;gap:12px;padding:10px 4px;border-bottom:1px solid var(--border);font-size:.85rem">
        <span class="badge ${a.type === 'run' ? 'green' : 'purple'}">${a.type === 'run' ? '🔄 Run' : '👁 View'}</span>
        <span>${a.icon || ''} ${a.tool_name}</span>
        <span style="color:var(--text-dim);margin-left:auto">${a.country || 'Unknown'} · ${a.device_type}</span>
        <span style="color:var(--text-dim);min-width:70px;text-align:right">${timeAgo(a.created_at)}</span>
      </div>
    `).join('');
  }catch(e){
    console.error('Realtime feed error', e);
  }
}

refreshFeed();
setInterval(refreshFeed, 8000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
