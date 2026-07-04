<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Manage Tools';
$pageSubtitle = 'Add, edit, publish, or remove tools and categories — saves directly to the live site';
$activeNav = 'manage';

$jsonPath = __DIR__ . '/../assets/tools-data.json';
$toolsDataRaw = file_exists($jsonPath) ? file_get_contents($jsonPath) : '{"categories":[],"tools":[]}';
// Validate it's real JSON before embedding — fall back to empty shell if the file is somehow corrupted
json_decode($toolsDataRaw);
if (json_last_error() !== JSON_ERROR_NONE) {
    $toolsDataRaw = '{"categories":[],"tools":[]}';
}

include __DIR__ . '/includes/header.php';
?>
<style>
  .mt-tabs{display:flex;gap:8px;margin-bottom:18px}
  .mt-tab-btn{background:var(--surface-2);border:1px solid var(--border);color:var(--text-dim);padding:9px 16px;border-radius:9px;font-weight:700;font-size:.85rem;cursor:pointer}
  .mt-tab-btn.active{background:var(--accent);color:#fff;border-color:var(--accent)}
  .mt-tab-panel{display:none}
  .mt-tab-panel.active{display:block}
  .icon-btn{background:var(--surface-2);border:1px solid var(--border);border-radius:7px;width:30px;height:30px;cursor:pointer;font-size:.85rem;margin-left:4px}
  .icon-btn:hover{background:var(--border)}
  .icon-btn.danger:hover{background:rgba(239,68,68,.2);border-color:var(--danger)}
  .switch{position:relative;display:inline-block;width:38px;height:21px}
  .switch input{opacity:0;width:0;height:0}
  .switch .slider{position:absolute;inset:0;background:var(--surface-2);border:1px solid var(--border);border-radius:999px;cursor:pointer;transition:.2s}
  .switch .slider::before{content:"";position:absolute;width:15px;height:15px;left:2px;top:2px;background:#fff;border-radius:50%;transition:.2s}
  .switch input:checked + .slider{background:var(--accent-2);border-color:var(--accent-2)}
  .switch input:checked + .slider::before{transform:translateX(17px)}
  .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;z-index:200;padding:20px}
  .modal-overlay.open{display:flex}
  .modal-box{background:var(--surface-2);border:1px solid var(--border);border-radius:16px;padding:26px;width:100%;max-width:440px;max-height:90vh;overflow-y:auto}
  .modal-box h2{font-size:1.05rem;margin-bottom:18px}
  .field{margin-bottom:14px}
  .field label{display:block;font-size:.78rem;color:var(--text-dim);margin-bottom:6px;font-weight:600}
  .field input[type=text]{width:100%}
  .mt-row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .mt-catchecks{display:flex;flex-wrap:wrap;gap:8px}
  .mt-catchecks label{display:flex;align-items:center;gap:6px;background:var(--surface-2);border:1px solid var(--border);padding:7px 11px;border-radius:8px;font-size:.8rem;cursor:pointer;font-weight:600;color:var(--text)}
  .mt-catchecks label.checked{border-color:var(--accent-2);background:rgba(22,192,121,.12)}
  .mt-catchecks label.primary{border-color:var(--accent);background:rgba(124,92,252,.14)}
  .mt-catchecks input{width:auto!important;margin:0}
  .mt-catbadges{display:flex;flex-wrap:wrap;gap:3px}
  .btn.tiny{padding:2px 10px;font-size:.72rem}
  .modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}
  .toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(20px);background:var(--surface-2);border:1px solid var(--border);color:var(--text);padding:12px 22px;border-radius:10px;font-size:.85rem;opacity:0;pointer-events:none;transition:.25s;z-index:300}
  .toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
  .empty-row td{text-align:center;color:var(--text-dim);padding:24px}
  .index-status-select:focus{outline:none;box-shadow:0 0 0 2px rgba(124,92,252,.3)}
  .index-status-select option{background:#1a1a2e;color:#fff}
  /* Summary counts bar */
  .index-summary{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
  .index-pill{padding:5px 12px;border-radius:7px;font-size:.76rem;font-weight:700;cursor:pointer;border:1px solid transparent;transition:.15s}
  .index-pill:hover{opacity:.85}
  .index-pill.active{border-color:rgba(255,255,255,.4)!important}
</style>

<div class="panel-card" style="border-color:var(--accent-2);background:rgba(22,192,121,.06)">
  ✅ Changes here save directly to the live <code>assets/tools-data.json</code> on the server — no download/upload step needed anymore.
</div>

<div class="mt-tabs">
  <button class="mt-tab-btn active" data-tab="toolsTab">🧰 Tools</button>
  <button class="mt-tab-btn" data-tab="catTab">🗂️ Categories</button>
</div>

<div class="mt-tab-panel active" id="toolsTab">
  <div class="filter-row">
    <input type="text" id="toolSearch" placeholder="🔍 Search tools...">
    <select id="catFilter"><option value="">All Categories</option></select>
    <button class="btn secondary" id="autoCatAllBtn" title="Re-run auto-categorization on every tool">🪄 Auto-categorize all</button>
    <button class="btn" id="addToolBtn">➕ Add New Tool</button>
  </div>
  <div class="panel-card">
    <h2>🧰 Tools (<span id="toolCount">0</span>) &nbsp;<span style="font-size:.8rem;color:var(--text-dim);font-weight:400" id="indexSummary"></span></h2>
    <div class="index-summary" id="indexSummaryPills"></div>
    <table>
      <thead><tr>
        <th></th>
        <th>Name</th>
        <th>Category</th>
        <th>Published</th>
        <th>
          Index Status
          <select id="indexStatusFilter" style="margin-left:8px;padding:3px 8px;font-size:.72rem;border-radius:5px;background:var(--surface-2);border:1px solid var(--border);color:var(--text)">
            <option value="">All</option>
            <option value="not_submitted">Not Submitted</option>
            <option value="submitted_1">Submitted 01</option>
            <option value="submitted_2">Submitted 02</option>
            <option value="indexed">Indexed</option>
          </select>
        </th>
        <th style="text-align:right">Actions</th>
      </tr></thead>
      <tbody id="toolsTableBody"></tbody>
    </table>
  </div>
</div>

<div class="mt-tab-panel" id="catTab">
  <div class="filter-row">
    <button class="btn" id="addCatBtn">➕ Add New Category</button>
  </div>
  <div class="panel-card">
    <h2>🗂️ Categories</h2>
    <table>
      <thead><tr><th></th><th>Category Name</th><th>Total Tools</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody id="catTableBody"></tbody>
    </table>
  </div>
</div>

<!-- TOOL MODAL -->
<div class="modal-overlay" id="toolModal">
  <div class="modal-box">
    <h2 id="toolModalTitle">Add New Tool</h2>
    <input type="hidden" id="toolEditId">
    <div class="field"><label for="toolName">Tool Name *</label><input type="text" id="toolName" placeholder="e.g. Word Counter"></div>
    <div class="field"><label for="toolDesc">Short Description</label><input type="text" id="toolDesc" placeholder="e.g. Count words and characters"></div>
    <div class="field"><label for="toolIcon">Icon (emoji)</label><input type="text" id="toolIcon" placeholder="🔢"></div>
    <div class="field">
      <label>Categories *
        <button type="button" class="btn secondary tiny" id="toolAutoCat" style="margin-left:6px">🪄 Auto-detect</button>
      </label>
      <div id="toolCatChecks" class="mt-catchecks"></div>
      <div style="font-size:.72rem;color:var(--text-dim);margin-top:6px">A tool can belong to more than one category. The <strong>first checked</strong> is the primary category. Leave all unchecked and it will be auto-detected from the name &amp; description on save.</div>
    </div>
    <div class="field"><label for="toolUrl">Tool Page (URL) *</label><input type="text" id="toolUrl" placeholder="tools/my-new-tool.html"></div>
    <label style="display:flex;align-items:center;gap:8px;font-weight:400;color:var(--text);font-size:.85rem">
      <input type="checkbox" id="toolPublished" style="width:auto" checked> Keep published on website
    </label>
    <div class="modal-actions">
      <button class="btn secondary" id="toolCancelBtn">Cancel</button>
      <button class="btn" id="toolSaveBtn">Save</button>
    </div>
  </div>
</div>

<!-- CATEGORY MODAL -->
<div class="modal-overlay" id="catModal">
  <div class="modal-box">
    <h2 id="catModalTitle">Add New Category</h2>
    <input type="hidden" id="catEditId">
    <div class="field"><label for="catName">Category Name *</label><input type="text" id="catName" placeholder="e.g. Text Tools"></div>
    <div class="field"><label for="catIcon">Icon (emoji)</label><input type="text" id="catIcon" placeholder="📝"></div>
    <div class="modal-actions">
      <button class="btn secondary" id="catCancelBtn">Cancel</button>
      <button class="btn" id="catSaveBtn">Save</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<!-- Shared auto-categorization engine (single source of truth) -->
<script src="../assets/categorize.js"></script>
<script>
/* Data loaded via fetch — avoids all PHP/JS encoding issues */
let toolsData = {"categories":[],"tools":[]};

// Load tools data fresh from JSON file
fetch('../assets/tools-data.json?v=' + Date.now(), {cache:'no-store'})
  .then(r => r.json())
  .then(d => {
    toolsData = d;
    // Debug
    console.log('[ApneSoftware] Loaded', toolsData.tools.length, 'tools');
    renderAll();
  })
  .catch(e => {
    console.error('[ApneSoftware] Failed to load tools-data.json:', e);
    document.getElementById('toolsTableBody').innerHTML = '<tr><td colspan="6" style="color:#F87171;text-align:center;padding:20px">❌ Failed to load tools. Check browser console.</td></tr>';
  });

function showToast(msg){
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(()=> t.classList.remove('show'), 2500);
}

function slugify(str){
  let s = str.toLowerCase().trim().replace(/[^a-z0-9\s-]/g,'').replace(/\s+/g,'-').replace(/-+/g,'-');
  if(!s) s = 'item-' + Math.random().toString(36).slice(2,8);
  return s;
}

function uniqueId(base, existingIds){
  let id = slugify(base);
  let final = id, counter = 2;
  while(existingIds.includes(final)){ final = id + '-' + counter; counter++; }
  return final;
}

/* ---- Persist to the server. Every mutation calls this; UI re-renders optimistically first, this confirms it stuck. ---- */
async function saveData(){
  try{
    const res = await fetch('save_tools_data.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(toolsData)
    });
    const data = await res.json();
    if(data.ok){
      showToast('✅ Saved to live site');
    } else {
      showToast('❌ Save failed: ' + (data.msg || 'unknown error'));
    }
  }catch(err){
    showToast('❌ Could not reach server — check your connection');
  }
}

function renderAll(){
  renderCategoryOptions();
  renderToolsTable(); // render immediately with empty statuses
  loadIndexStatuses(); // load statuses async, re-renders when done
  renderCatTable();
}

/* ---- Tabs ---- */
document.querySelectorAll('.mt-tab-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    document.querySelectorAll('.mt-tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.mt-tab-panel').forEach(p=>p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.tab).classList.add('active');
  });
});

function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(overlay=>{
  overlay.addEventListener('click', e=>{ if(e.target === overlay) closeModal(overlay.id); });
});

/* ---- Multi-category helpers ---- */
// A tool can belong to several categories. Read them from `categories`, falling
// back to the legacy single `category` field for any not-yet-migrated entry.
function catIdsOf(tool){
  if(tool && Array.isArray(tool.categories) && tool.categories.length) return tool.categories.slice();
  return tool && tool.category ? [tool.category] : [];
}
function toolInCat(tool, catId){ return catIdsOf(tool).indexOf(catId) !== -1; }

/* Render the category checkbox group inside the tool modal. `selected` is an
   ordered list — the first entry is treated as the primary category. Selected
   categories are shown first so the primary stays first on save. */
function renderCatChecks(selected){
  selected = (selected || []).filter(Boolean);
  const wrap = document.getElementById('toolCatChecks');
  const allIds = toolsData.categories.map(c => c.id);
  const ordered = selected.filter(id => allIds.includes(id))
    .concat(allIds.filter(id => !selected.includes(id)));
  wrap.innerHTML = ordered.map((id, idx) => {
    const c = toolsData.categories.find(x => x.id === id);
    if(!c) return '';
    const on = selected.includes(id);
    const isPrimary = on && selected[0] === id;
    const cls = (on ? 'checked ' : '') + (isPrimary ? 'primary' : '');
    return `<label class="${cls.trim()}" data-cat="${id}">
      <input type="checkbox" value="${id}" ${on ? 'checked' : ''} onchange="onCatCheckToggle()"> ${c.icon} ${c.name}</label>`;
  }).join('');
}
function getCheckedCats(){
  return [...document.querySelectorAll('#toolCatChecks input:checked')].map(i => i.value);
}
// Re-render so ordering/primary highlight follows the current selection.
function onCatCheckToggle(){ renderCatChecks(getCheckedCats()); }

/* ---- Category dropdowns ---- */
function renderCategoryOptions(){
  const catFilterSel = document.getElementById('catFilter');
  const currentFilter = catFilterSel.value;
  catFilterSel.innerHTML = '<option value="">All Categories</option>' +
    toolsData.categories.map(c => `<option value="${c.id}">${c.icon} ${c.name}</option>`).join('');
  catFilterSel.value = currentFilter;
}

/* ---- Tools table ---- */
// Index status config
const INDEX_STATUS_OPTIONS = [
  {val:'not_submitted', label:'Not Submitted', color:'#6B7280'},
  {val:'submitted_1',   label:'Submitted 01',  color:'#F59E0B'},
  {val:'submitted_2',   label:'Submitted 02',  color:'#3B82F6'},
  {val:'indexed',       label:'Indexed ✓',     color:'#10B981'},
];
let indexStatuses = {}; // {slug: status}

// Load index statuses from backend
async function loadIndexStatuses(){
  try{
    const r = await fetch('../backend/index_status.php', {cache:'no-store'});
    if(r.ok){
      const d = await r.json();
      if(d && d.ok) indexStatuses = d.statuses || {};
    }
  }catch(e){
    // Backend unavailable — continue without index statuses
  } finally {
    renderToolsTable(); // always render, with or without statuses
  }
}

// Save index status for one tool
async function saveIndexStatus(slug, status){
  try{
    await fetch('../backend/index_status.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({slug, status})
    });
    showToast('Index status saved ✓');
  }catch(e){ showToast('Save failed!'); }
}

// Copy tool URL to clipboard
function copyToolUrl(url){
  const fullUrl = 'https://apnesoftware.com/' + url;
  navigator.clipboard.writeText(fullUrl).then(()=>{
    showToast('URL copied: ' + fullUrl);
  }).catch(()=>{
    prompt('Copy this URL:', fullUrl);
  });
}

function renderToolsTable(){
  const tbody = document.getElementById('toolsTableBody');
  const search = document.getElementById('toolSearch').value.toLowerCase();
  const catFilter = document.getElementById('catFilter').value;
  const indexFilterEl = document.getElementById('indexStatusFilter');
  const indexFilter = indexFilterEl ? indexFilterEl.value : '';

  let rows = toolsData.tools.filter(t =>
    (!search || t.name.toLowerCase().includes(search)) &&
    (!catFilter || toolInCat(t, catFilter)) &&
    (!indexFilter || (indexStatuses[t.id] || 'not_submitted') === indexFilter)
  );
  document.getElementById('toolCount').textContent = toolsData.tools.length;

  if(!rows.length){
    tbody.innerHTML = `<tr class="empty-row"><td colspan="6">No tools found</td></tr>`;
    return;
  }

  tbody.innerHTML = rows.map(tool => {
    const catLabel = '<span class="mt-catbadges">' + catIdsOf(tool).map((id, i) => {
      const c = toolsData.categories.find(x => x.id === id);
      const label = c ? `${c.icon} ${c.name}` : id;
      // Primary category (first) gets a highlighted badge.
      return `<span class="badge ${i === 0 ? 'purple' : 'gray'}">${label}</span>`;
    }).join(' ') + '</span>';
    const currentStatus = indexStatuses[tool.id] || 'not_submitted';
    const statusInfo = INDEX_STATUS_OPTIONS.find(o => o.val === currentStatus) || INDEX_STATUS_OPTIONS[0];
    const toolUrl = 'https://apnesoftware.com/' + tool.url;

    const statusOptions = INDEX_STATUS_OPTIONS.map(o =>
      `<option value="${o.val}" ${o.val === currentStatus ? 'selected' : ''}>${o.label}</option>`
    ).join('');

    return `
      <tr>
        <td style="font-size:1.2rem">${tool.icon || '🔧'}</td>
        <td>
          <strong style="font-size:.88rem">${tool.name}</strong>
          <div style="font-size:.72rem;color:var(--text-dim);margin-top:2px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${toolUrl}</div>
        </td>
        <td>${catLabel}</td>
        <td>
          <label class="switch">
            <input type="checkbox" ${tool.published ? 'checked' : ''} onchange="togglePublish('${tool.id}')">
            <span class="slider"></span>
          </label>
        </td>
        <td>
          <select class="index-status-select" data-slug="${tool.id}"
            style="border-left:3px solid ${statusInfo.color};padding:5px 8px;border-radius:7px;font-size:.78rem;font-weight:700;background:var(--surface-2);border-top:1px solid var(--border);border-right:1px solid var(--border);border-bottom:1px solid var(--border);color:var(--text);cursor:pointer;min-width:130px"
            onchange="handleIndexStatusChange(this)">
            ${statusOptions}
          </select>
        </td>
        <td style="text-align:right;white-space:nowrap">
          <button class="icon-btn" title="Copy URL" onclick="copyToolUrl('${tool.url}')" style="background:rgba(22,192,121,.1);border-color:rgba(22,192,121,.3)" >🔗</button>
          <button class="icon-btn" title="Edit" onclick="editTool('${tool.id}')">✏️</button>
          <button class="icon-btn danger" title="Delete" onclick="deleteTool('${tool.id}')">🗑️</button>
        </td>
      </tr>`;
  }).join('');

  // Render summary pills
  renderIndexSummary();
}

function handleIndexStatusChange(selectEl){
  const slug = selectEl.dataset.slug;
  const status = selectEl.value;
  const statusInfo = INDEX_STATUS_OPTIONS.find(o => o.val === status);
  // Update border color
  selectEl.style.borderLeftColor = statusInfo ? statusInfo.color : '#6B7280';
  // Update in memory
  indexStatuses[slug] = status;
  // Auto-save to backend
  saveIndexStatus(slug, status);
}

function renderIndexSummary(){
  const pContainer = document.getElementById('indexSummaryPills');
  if(!pContainer) return;
  const counts = {not_submitted:0, submitted_1:0, submitted_2:0, indexed:0};
  toolsData.tools.forEach(t => {
    const s = indexStatuses[t.id] || 'not_submitted';
    counts[s] = (counts[s]||0) + 1;
  });
  const colors = {not_submitted:'#6B7280', submitted_1:'#F59E0B', submitted_2:'#3B82F6', indexed:'#10B981'};
  const labels = {not_submitted:'Not Submitted', submitted_1:'Submitted 01', submitted_2:'Submitted 02', indexed:'Indexed ✓'};
  pContainer.innerHTML = Object.keys(counts).map(k =>
    `<span class="index-pill" style="background:${colors[k]}22;color:${colors[k]};border-color:${colors[k]}44"
      onclick="document.getElementById('indexStatusFilter').value='${k}';renderToolsTable()">
      ${labels[k]}: <strong>${counts[k]}</strong>
    </span>`
  ).join('') +
  `<span class="index-pill" style="background:rgba(255,255,255,.06);color:rgba(255,255,255,.6)"
    onclick="document.getElementById('indexStatusFilter').value='';renderToolsTable()">
    Show All: <strong>${toolsData.tools.length}</strong>
  </span>`;
}

function togglePublish(id){
  const tool = toolsData.tools.find(t => t.id === id);
  if(tool){ tool.published = !tool.published; saveData(); }
}

document.getElementById('toolSearch').addEventListener('input', renderToolsTable);
document.getElementById('catFilter').addEventListener('change', renderToolsTable);
const _isf = document.getElementById('indexStatusFilter');
if(_isf) _isf.addEventListener('change', renderToolsTable);

document.getElementById('addToolBtn').addEventListener('click', ()=>{
  document.getElementById('toolModalTitle').textContent = 'Add New Tool';
  document.getElementById('toolEditId').value = '';
  document.getElementById('toolName').value = '';
  document.getElementById('toolDesc').value = '';
  document.getElementById('toolIcon').value = '';
  document.getElementById('toolUrl').value = 'tools/';
  document.getElementById('toolPublished').checked = true;
  renderCatChecks([]); // start empty — auto-detected on save if left blank
  openModal('toolModal');
});

function editTool(id){
  const tool = toolsData.tools.find(t => t.id === id);
  if(!tool) return;
  document.getElementById('toolModalTitle').textContent = 'Edit Tool';
  document.getElementById('toolEditId').value = tool.id;
  document.getElementById('toolName').value = tool.name;
  document.getElementById('toolDesc').value = tool.description || '';
  document.getElementById('toolIcon').value = tool.icon || '';
  renderCatChecks(catIdsOf(tool));
  document.getElementById('toolUrl').value = tool.url;
  document.getElementById('toolPublished').checked = !!tool.published;
  openModal('toolModal');
}

function deleteTool(id){
  const tool = toolsData.tools.find(t => t.id === id);
  if(!tool) return;
  if(!confirm(`Delete the tool "${tool.name}"? This removes it from the live site immediately.`)) return;
  toolsData.tools = toolsData.tools.filter(t => t.id !== id);
  renderToolsTable();
  saveData();
}

document.getElementById('toolCancelBtn').addEventListener('click', ()=> closeModal('toolModal'));

/* Compute categories for a name/description using the shared engine. Returns a
   primary-first list; falls back to the current selection or first category. */
function autoDetectCats(name, desc, fallbackSelected){
  let cats = (window.ApneCat && typeof window.ApneCat.suggest === 'function')
    ? window.ApneCat.suggest(name, desc) : [];
  if(!cats.length) cats = (fallbackSelected || []).slice();
  if(!cats.length && toolsData.categories.length) cats = [toolsData.categories[0].id];
  return cats;
}

// 🪄 Auto-detect button inside the tool modal
document.getElementById('toolAutoCat').addEventListener('click', ()=>{
  const name = document.getElementById('toolName').value.trim();
  const desc = document.getElementById('toolDesc').value.trim();
  if(!name && !desc){ showToast('⚠️ Enter a name/description first'); return; }
  const cats = autoDetectCats(name, desc, getCheckedCats());
  renderCatChecks(cats);
  showToast('🪄 Auto-detected ' + cats.length + ' categor' + (cats.length === 1 ? 'y' : 'ies'));
});

document.getElementById('toolSaveBtn').addEventListener('click', ()=>{
  const editId = document.getElementById('toolEditId').value;
  const name = document.getElementById('toolName').value.trim();
  const desc = document.getElementById('toolDesc').value.trim();
  const icon = document.getElementById('toolIcon').value.trim() || '🔧';
  const url = document.getElementById('toolUrl').value.trim();
  const published = document.getElementById('toolPublished').checked;

  if(!name){ showToast('⚠️ Please enter a tool name'); return; }
  if(!url){ showToast('⚠️ Please enter the tool URL/page'); return; }
  if(!toolsData.categories.length){ showToast('⚠️ Please create a category first'); return; }

  // Categories: use the checked boxes; if none chosen, auto-detect from name/desc.
  let categories = getCheckedCats();
  if(!categories.length) categories = autoDetectCats(name, desc, []);
  const category = categories[0]; // primary (kept for backward compatibility)

  if(editId){
    const tool = toolsData.tools.find(t => t.id === editId);
    Object.assign(tool, { name, description: desc, icon, category, categories, url, published });
  } else {
    const existingIds = toolsData.tools.map(t => t.id);
    const id = uniqueId(name, existingIds);
    toolsData.tools.push({ id, name, description: desc, icon, category, categories, url, published });
  }
  renderToolsTable();
  closeModal('toolModal');
  saveData();
});

/* ---- Bulk: re-run auto-categorization on every tool ---- */
document.getElementById('autoCatAllBtn').addEventListener('click', ()=>{
  if(!(window.ApneCat && typeof window.ApneCat.autoCategories === 'function')){
    showToast('❌ Categorization engine not loaded'); return;
  }
  if(!confirm('Re-run auto-categorization on all ' + toolsData.tools.length + ' tools?\n\n' +
    'Each tool keeps its current primary category and gains any additional categories that match its name & description.')) return;
  let changed = 0;
  toolsData.tools.forEach(t => {
    const before = JSON.stringify(catIdsOf(t));
    const cats = window.ApneCat.autoCategories(t); // primary guaranteed first
    t.category = cats[0];
    t.categories = cats;
    if(JSON.stringify(cats) !== before) changed++;
  });
  renderToolsTable();
  saveData();
  showToast('🪄 Auto-categorized — ' + changed + ' tool(s) updated');
});

/* ---- Category table ---- */
function renderCatTable(){
  const tbody = document.getElementById('catTableBody');
  if(!toolsData.categories.length){
    tbody.innerHTML = `<tr class="empty-row"><td colspan="4">No categories yet</td></tr>`;
    return;
  }
  tbody.innerHTML = toolsData.categories.map((cat, i) => {
    const count = toolsData.tools.filter(t => toolInCat(t, cat.id)).length;
    return `
      <tr>
        <td>${cat.icon || '🗂️'}</td>
        <td><strong>${cat.name}</strong></td>
        <td>${count}</td>
        <td style="text-align:right">
          <button class="icon-btn" title="Move up" onclick="moveCat(${i},-1)" ${i===0?'disabled':''}>⬆️</button>
          <button class="icon-btn" title="Move down" onclick="moveCat(${i},1)" ${i===toolsData.categories.length-1?'disabled':''}>⬇️</button>
          <button class="icon-btn" title="Edit" onclick="editCat('${cat.id}')">✏️</button>
          <button class="icon-btn danger" title="Delete" onclick="deleteCat('${cat.id}')">🗑️</button>
        </td>
      </tr>`;
  }).join('');
}

function moveCat(index, dir){
  const j = index + dir;
  if(j < 0 || j >= toolsData.categories.length) return;
  [toolsData.categories[index], toolsData.categories[j]] = [toolsData.categories[j], toolsData.categories[index]];
  renderCatTable();
  saveData();
}

document.getElementById('addCatBtn').addEventListener('click', ()=>{
  document.getElementById('catModalTitle').textContent = 'Add New Category';
  document.getElementById('catEditId').value = '';
  document.getElementById('catName').value = '';
  document.getElementById('catIcon').value = '';
  openModal('catModal');
});

function editCat(id){
  const cat = toolsData.categories.find(c => c.id === id);
  if(!cat) return;
  document.getElementById('catModalTitle').textContent = 'Edit Category';
  document.getElementById('catEditId').value = cat.id;
  document.getElementById('catName').value = cat.name;
  document.getElementById('catIcon').value = cat.icon || '';
  openModal('catModal');
}

function deleteCat(id){
  const cat = toolsData.categories.find(c => c.id === id);
  if(!cat) return;
  const count = toolsData.tools.filter(t => toolInCat(t, id)).length;
  const warning = count > 0
    ? `This category has ${count} tool(s) in it. Deleting it will remove it from those tools (they keep their other categories). Delete anyway?`
    : `Delete the category "${cat.name}"?`;
  if(!confirm(warning)) return;
  toolsData.categories = toolsData.categories.filter(c => c.id !== id);
  // Strip the deleted category from every tool; repair primary if needed.
  toolsData.tools.forEach(t => {
    let cats = catIdsOf(t).filter(cid => cid !== id);
    if(!cats.length && toolsData.categories.length) cats = [toolsData.categories[0].id];
    t.categories = cats;
    t.category = cats[0];
  });
  renderAll();
  saveData();
}

document.getElementById('catCancelBtn').addEventListener('click', ()=> closeModal('catModal'));
document.getElementById('catSaveBtn').addEventListener('click', ()=>{
  const editId = document.getElementById('catEditId').value;
  const name = document.getElementById('catName').value.trim();
  const icon = document.getElementById('catIcon').value.trim() || '🗂️';
  if(!name){ showToast('⚠️ Please enter a category name'); return; }

  if(editId){
    const cat = toolsData.categories.find(c => c.id === editId);
    Object.assign(cat, { name, icon });
  } else {
    const existingIds = toolsData.categories.map(c => c.id);
    const id = uniqueId(name, existingIds);
    toolsData.categories.push({ id, name, icon });
  }
  renderAll();
  closeModal('catModal');
  saveData();
});

// Debug: verify data loaded
console.log('[ApneSoftware] toolsData loaded:', toolsData.tools ? toolsData.tools.length + ' tools' : 'ERROR - no tools array');
renderAll();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
