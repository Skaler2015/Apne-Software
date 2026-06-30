/* ═══════════════════════════════════════════════════════════════
   CODE PREVIEW TOOL — ApneSoftware
   script.js — Full functionality for both modules
═══════════════════════════════════════════════════════════════ */

'use strict';

/* ─── STATE ─────────────────────────────────────── */
let cmHTML, cmCSS, cmJS;
let currentModule = 'editor';
let currentEditorTab = 'html';
let isSplitMode = false;
let isVerticalLayout = false;
let consoleCollapsed = false;
let consoleCount = 0;
let autoRunTimer = null;
let wpFiles = {};       // filename → { content, type, size }
let wpBlobURLs = {};    // filename → blob URL (for assets)
let currentWpHTML = '';
let currentViewport = 'desktop';
let allFileNodes = [];  // for search
let sourceModal, pasteModal;

/* ─── DEFAULT CODE ───────────────────────────────── */
const DEFAULT_HTML = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Page</title>
</head>
<body>
  <div class="container">
    <h1>Hello, World! 👋</h1>
    <p>Edit the HTML, CSS, and JavaScript tabs to see your changes live.</p>
    <button onclick="greet()">Click me!</button>
  </div>
</body>
</html>`;

const DEFAULT_CSS = `* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Segoe UI', system-ui, sans-serif;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
}
.container {
  background: white;
  border-radius: 16px;
  padding: 40px;
  text-align: center;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
  max-width: 500px;
  width: 90%;
}
h1 {
  font-size: 2rem;
  color: #1a1a2e;
  margin-bottom: 12px;
}
p {
  color: #666;
  line-height: 1.6;
  margin-bottom: 24px;
}
button {
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
  border: none;
  padding: 12px 28px;
  border-radius: 30px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}
button:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(102,126,234,0.4);
}`;

const DEFAULT_JS = `function greet() {
  const messages = [
    'You clicked the button! 🎉',
    'Keep coding! 💻',
    'You are awesome! ⭐',
    'ApneSoftware loves you! 💜'
  ];
  const random = messages[Math.floor(Math.random() * messages.length)];
  alert(random);
  console.log('Button clicked!', random);
}

// Log a welcome message
console.log('Welcome to Code Preview Tool by ApneSoftware! 🚀');
console.log('Edit HTML, CSS, JS and press Ctrl+Enter to run.');`;

/* ─── INIT ───────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initEditors();
  loadSavedCode();
  runCode();
  initResizeHandle();
  initKeyboardShortcuts();
  initTheme();
  initModals();
  createToastContainer();
  setTimeout(updateStats, 200);
});

/* ─── CODEMIRROR INIT ────────────────────────────── */
function initEditors() {
  const commonOpts = {
    lineNumbers: true,
    matchBrackets: true,
    autoCloseBrackets: true,
    styleActiveLine: true,
    indentWithTabs: false,
    tabSize: 2,
    indentUnit: 2,
    lineWrapping: false,
    extraKeys: {
      'Ctrl-/': 'toggleComment',
      'Ctrl-Enter': () => runCode(),
      'Ctrl-S': () => saveCode(),
      'Alt-F': () => formatCode(),
      Tab: cm => { if (cm.somethingSelected()) cm.indentSelection('add'); else cm.replaceSelection('  '); }
    }
  };
  const theme = () => document.body.classList.contains('dark-mode') ? 'dracula' : 'default';

  cmHTML = CodeMirror.fromTextArea(document.getElementById('htmlEditor'), {
    ...commonOpts, mode: 'htmlmixed', theme: theme(), lint: false
  });
  cmCSS = CodeMirror.fromTextArea(document.getElementById('cssEditor'), {
    ...commonOpts, mode: 'css', theme: theme()
  });
  cmJS = CodeMirror.fromTextArea(document.getElementById('jsEditor'), {
    ...commonOpts, mode: 'javascript', theme: theme()
  });

  [cmHTML, cmCSS, cmJS].forEach(cm => {
    cm.on('change', () => {
      clearTimeout(autoRunTimer);
      autoRunTimer = setTimeout(() => { saveCode(); updateStats(); }, 600);
    });
  });
}

/* ─── LOAD / SAVE ────────────────────────────────── */
function loadSavedCode() {
  const html = localStorage.getItem('cp_html');
  const css  = localStorage.getItem('cp_css');
  const js   = localStorage.getItem('cp_js');
  cmHTML.setValue(html !== null ? html : DEFAULT_HTML);
  cmCSS.setValue(css  !== null ? css  : DEFAULT_CSS);
  cmJS.setValue(js   !== null ? js   : DEFAULT_JS);
}

function saveCode() {
  localStorage.setItem('cp_html', cmHTML.getValue());
  localStorage.setItem('cp_css',  cmCSS.getValue());
  localStorage.setItem('cp_js',   cmJS.getValue());
  showToast('Session saved', 'success');
}

/* ─── RUN CODE ───────────────────────────────────── */
function runCode() {
  const status = document.getElementById('previewStatus');
  status.textContent = 'Running…';
  status.className = 'preview-status running';

  const html = cmHTML.getValue();
  const css  = cmCSS.getValue();
  const js   = cmJS.getValue();

  const combined = buildPreviewDoc(html, css, js);
  const frame = document.getElementById('previewFrame');

  try {
    frame.srcdoc = combined;
    status.textContent = 'Live';
    status.className = 'preview-status';
  } catch(e) {
    status.textContent = 'Error';
    status.className = 'preview-status error';
  }

  // Intercept console from iframe
  setupConsoleBridge(frame);
}

function buildPreviewDoc(html, css, js) {
  // If HTML has full doc structure, inject CSS+JS into it
  if (/<html[\s>]/i.test(html)) {
    let doc = html;
    if (css.trim()) {
      doc = doc.replace('</head>', `<style>\n${css}\n</style>\n</head>`);
    }
    if (js.trim()) {
      const safeJs = wrapConsole(js);
      doc = doc.replace('</body>', `<script>\n${safeJs}\n<\/script>\n</body>`);
    }
    return doc;
  }
  // Otherwise wrap in a full document
  return `<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>${css}</style>
</head><body>
${html}
<script>${wrapConsole(js)}<\/script>
</body></html>`;
}

function wrapConsole(js) {
  return `
window.addEventListener('error', function(e) {
  window.parent.postMessage({type:'cp_error',msg:e.message,line:e.lineno},'*');
});
(function(){
  var orig = {log:console.log,error:console.error,warn:console.warn,info:console.info};
  ['log','error','warn','info'].forEach(function(m){
    console[m] = function(){
      var args = Array.from(arguments).map(function(a){ try{return JSON.stringify(a);}catch(e){return String(a);} });
      window.parent.postMessage({type:'cp_console',method:m,msg:args.join(' ')},'*');
      orig[m].apply(console,arguments);
    };
  });
})();
try {
${js}
} catch(e) {
  window.parent.postMessage({type:'cp_error',msg:e.message},'*');
}`;
}

function setupConsoleBridge(frame) {
  window.removeEventListener('message', handleIframeMessage);
  window.addEventListener('message', handleIframeMessage);
}

function handleIframeMessage(e) {
  if (!e.data || !e.data.type) return;
  if (e.data.type === 'cp_console') addConsoleEntry(e.data.method, e.data.msg);
  if (e.data.type === 'cp_error') { addConsoleEntry('error', e.data.msg); document.getElementById('previewStatus').textContent = 'Error'; document.getElementById('previewStatus').className = 'preview-status error'; }
}

/* ─── CONSOLE ────────────────────────────────────── */
function addConsoleEntry(method, msg) {
  const body = document.getElementById('consoleBody');
  const welcome = body.querySelector('.console-welcome');
  if (welcome) welcome.remove();

  const icons = { log: 'bi-chevron-right', error: 'bi-x-circle-fill', warn: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
  const line = document.createElement('div');
  line.className = `console-line cl-${method}`;
  line.innerHTML = `<i class="bi ${icons[method]||icons.log} cl-icon"></i><span class="cl-msg">${escapeHtml(String(msg))}</span>`;
  body.appendChild(line);
  body.scrollTop = body.scrollHeight;

  consoleCount++;
  const badge = document.getElementById('consoleBadge');
  badge.textContent = consoleCount;
  badge.style.display = 'inline-flex';
}

function clearConsole() {
  const body = document.getElementById('consoleBody');
  body.innerHTML = '<div class="console-welcome"><i class="bi bi-info-circle"></i> Console cleared.</div>';
  consoleCount = 0;
  document.getElementById('consoleBadge').style.display = 'none';
}

function toggleConsole() {
  const panel = document.getElementById('consolePanel');
  const chevron = document.getElementById('consoleChevron');
  consoleCollapsed = !consoleCollapsed;
  panel.classList.toggle('collapsed', consoleCollapsed);
  chevron.className = consoleCollapsed ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
}

/* ─── EDITOR TABS ────────────────────────────────── */
function switchEditorTab(tab) {
  currentEditorTab = tab;
  document.querySelectorAll('.etab').forEach(t => {
    t.classList.toggle('active', t.dataset.tab === tab);
  });

  const containers = document.getElementById('editorContainers');
  isSplitMode = (tab === 'all');

  if (isSplitMode) {
    containers.classList.add('split-mode');
    document.querySelectorAll('.editor-wrap').forEach(w => w.classList.add('active'));
  } else {
    containers.classList.remove('split-mode');
    document.querySelectorAll('.editor-wrap').forEach(w => {
      w.classList.toggle('active', w.id === `${tab}-editor-wrap`);
    });
  }

  // Refresh CodeMirror
  setTimeout(() => { cmHTML.refresh(); cmCSS.refresh(); cmJS.refresh(); }, 50);
}

/* ─── LAYOUT TOGGLE ──────────────────────────────── */
function toggleLayout() {
  isVerticalLayout = !isVerticalLayout;
  const area = document.getElementById('editorArea');
  area.classList.toggle('layout-vertical', isVerticalLayout);
  setTimeout(() => { cmHTML.refresh(); cmCSS.refresh(); cmJS.refresh(); }, 50);
}

/* ─── RESIZE HANDLE ──────────────────────────────── */
function initResizeHandle() {
  const handle = document.getElementById('resizeHandle');
  const editorsPanel = document.getElementById('editorsPanel');
  const editorArea = document.getElementById('editorArea');
  let isResizing = false, startX, startY, startW, startH;

  handle.addEventListener('mousedown', e => {
    isResizing = true;
    startX = e.clientX;
    startY = e.clientY;
    startW = editorsPanel.offsetWidth;
    startH = editorsPanel.offsetHeight;
    handle.classList.add('dragging');
    document.body.style.userSelect = 'none';
    document.body.style.cursor = isVerticalLayout ? 'ns-resize' : 'ew-resize';
  });

  document.addEventListener('mousemove', e => {
    if (!isResizing) return;
    if (isVerticalLayout) {
      const dy = e.clientY - startY;
      const newH = Math.max(120, Math.min(startH + dy, editorArea.offsetHeight - 120));
      editorsPanel.style.height = newH + 'px';
    } else {
      const dx = e.clientX - startX;
      const newW = Math.max(240, Math.min(startW + dx, editorArea.offsetWidth - 240));
      editorsPanel.style.width = newW + 'px';
    }
    cmHTML.refresh(); cmCSS.refresh(); cmJS.refresh();
  });

  document.addEventListener('mouseup', () => {
    if (!isResizing) return;
    isResizing = false;
    handle.classList.remove('dragging');
    document.body.style.userSelect = '';
    document.body.style.cursor = '';
  });

  // Touch support
  handle.addEventListener('touchstart', e => {
    const t = e.touches[0];
    isResizing = true; startX = t.clientX; startY = t.clientY;
    startW = editorsPanel.offsetWidth; startH = editorsPanel.offsetHeight;
  }, { passive: true });
  document.addEventListener('touchmove', e => {
    if (!isResizing) return;
    const t = e.touches[0];
    if (isVerticalLayout) {
      const dy = t.clientY - startY;
      editorsPanel.style.height = Math.max(120, startH + dy) + 'px';
    } else {
      const dx = t.clientX - startX;
      editorsPanel.style.width = Math.max(240, startW + dx) + 'px';
    }
    cmHTML.refresh(); cmCSS.refresh(); cmJS.refresh();
  }, { passive: true });
  document.addEventListener('touchend', () => { isResizing = false; });
}

/* ─── ACTIONS ────────────────────────────────────── */
function refreshPreview() { runCode(); }

function clearAllCode() {
  if (!confirm('Clear all editors? This cannot be undone.')) return;
  cmHTML.setValue(''); cmCSS.setValue(''); cmJS.setValue('');
  document.getElementById('previewFrame').srcdoc = '';
  clearConsole();
  updateStats();
  showToast('Editors cleared', 'info');
}

function formatCode() {
  // Basic JS formatter: indentation fix
  try {
    const html = cmHTML.getValue();
    const css  = cmCSS.getValue();
    const js   = cmJS.getValue();
    cmHTML.setValue(html);
    cmCSS.setValue(css);
    cmJS.setValue(js);
    cmHTML.execCommand('selectAll');
    cmHTML.indentSelection('smart');
    showToast('Code formatted', 'success');
  } catch(e) { showToast('Format error', 'error'); }
}

function copyAllCode() {
  const combined = buildPreviewDoc(cmHTML.getValue(), cmCSS.getValue(), cmJS.getValue());
  navigator.clipboard.writeText(combined).then(() => showToast('Code copied!', 'success')).catch(() => showToast('Copy failed', 'error'));
}

function downloadHTML() {
  const combined = buildPreviewDoc(cmHTML.getValue(), cmCSS.getValue(), cmJS.getValue());
  const blob = new Blob([combined], { type: 'text/html' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'code-preview-' + Date.now() + '.html';
  a.click();
  URL.revokeObjectURL(a.href);
  showToast('File downloaded!', 'success');
}

function openInNewTab() {
  const combined = buildPreviewDoc(cmHTML.getValue(), cmCSS.getValue(), cmJS.getValue());
  const blob = new Blob([combined], { type: 'text/html' });
  const url = URL.createObjectURL(blob);
  window.open(url, '_blank');
  setTimeout(() => URL.revokeObjectURL(url), 5000);
}

function toggleFullscreenPreview() {
  const panel = document.getElementById('previewPanel');
  panel.classList.toggle('fullscreen');
  const icon = document.querySelector('#fsBtn i, .preview-actions .tbtn-sm:last-child i');
  if (panel.classList.contains('fullscreen')) {
    document.addEventListener('keydown', exitFsOnEsc);
    if(icon) icon.className = 'bi bi-fullscreen-exit';
  } else {
    document.removeEventListener('keydown', exitFsOnEsc);
    if(icon) icon.className = 'bi bi-fullscreen';
  }
}
function exitFsOnEsc(e) { if (e.key === 'Escape') toggleFullscreenPreview(); }

/* ─── STATS ──────────────────────────────────────── */
function updateStats() {
  const all = cmHTML.getValue() + ' ' + cmCSS.getValue() + ' ' + cmJS.getValue();
  const words = all.trim() ? all.trim().split(/\s+/).length : 0;
  const chars = cmHTML.getValue().length + cmCSS.getValue().length + cmJS.getValue().length;
  document.getElementById('wordCount').textContent = words.toLocaleString();
  document.getElementById('charCount').textContent = chars.toLocaleString();
}

/* ─── KEYBOARD SHORTCUTS ─────────────────────────── */
function initKeyboardShortcuts() {
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); runCode(); }
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); saveCode(); }
  });
}

/* ─── THEME ──────────────────────────────────────── */
function initTheme() {
  const saved = localStorage.getItem('cp_theme') || 'dark';
  applyTheme(saved);
  document.getElementById('themeToggle').addEventListener('click', () => {
    const current = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
    applyTheme(current === 'dark' ? 'light' : 'dark');
  });
}

function applyTheme(theme) {
  const body = document.body;
  body.classList.toggle('dark-mode', theme === 'dark');
  body.classList.toggle('light-mode', theme === 'light');
  const icon = document.getElementById('themeIcon');
  icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
  localStorage.setItem('cp_theme', theme);

  const cmTheme = theme === 'dark' ? 'dracula' : 'default';
  if (cmHTML) { cmHTML.setOption('theme', cmTheme); cmCSS.setOption('theme', cmTheme); cmJS.setOption('theme', cmTheme); }
}

/* ─── MODULE SWITCH ──────────────────────────────── */
function switchModule(mod) {
  currentModule = mod;
  document.getElementById('module-editor').classList.toggle('active', mod === 'editor');
  document.getElementById('module-preview').classList.toggle('active', mod === 'preview');
  document.getElementById('module-editor').hidden = (mod !== 'editor');
  document.getElementById('module-preview').hidden = (mod !== 'preview');
  document.getElementById('tab-editor').classList.toggle('active', mod === 'editor');
  document.getElementById('tab-preview').classList.toggle('active', mod === 'preview');
  if (mod === 'editor') setTimeout(() => { cmHTML.refresh(); cmCSS.refresh(); cmJS.refresh(); }, 50);
}

/* ─── MODULE 2: WEBSITE PREVIEW ─────────────────── */

function handleDragOver(e) {
  e.preventDefault();
  document.getElementById('wpDropZone').classList.add('drag-over');
}
function handleDragLeave(e) {
  if (!document.getElementById('wpDropZone').contains(e.relatedTarget))
    document.getElementById('wpDropZone').classList.remove('drag-over');
}
function handleDrop(e) {
  e.preventDefault();
  document.getElementById('wpDropZone').classList.remove('drag-over');
  const files = e.dataTransfer.files;
  if (!files.length) return;
  const f = files[0];
  if (f.name.endsWith('.zip')) processZipFile(f);
  else if (f.name.match(/\.html?$/i)) processHTMLFile(f);
  else showToast('Please drop a ZIP or HTML file', 'error');
}

function handleZipUpload(e) { if (e.target.files[0]) processZipFile(e.target.files[0]); }
function handleHTMLUpload(e) { if (e.target.files[0]) processHTMLFile(e.target.files[0]); }

function processZipFile(file) {
  showToast('Extracting ZIP…', 'info');
  if (file.size > 100 * 1024 * 1024) { showToast('ZIP is too large (max 100MB)', 'error'); return; }
  const reader = new FileReader();
  reader.onload = async e => {
    try {
      const zip = await JSZip.loadAsync(e.target.result);
      wpFiles = {};
      let totalSize = 0;
      const promises = [];
      zip.forEach((path, entry) => {
        if (entry.dir) return;
        const name = path.startsWith('/') ? path.slice(1) : path;
        promises.push(entry.async('uint8array').then(data => {
          wpFiles[name] = { data, size: data.length, type: getMimeType(name) };
          totalSize += data.length;
        }));
      });
      await Promise.all(promises);

      if (!Object.keys(wpFiles).length) { showToast('ZIP appears to be empty', 'error'); return; }

      // Find entry point
      const entryPoint = findEntryPoint();
      if (!entryPoint) { showToast('No index.html/default.html found in ZIP', 'error'); renderFileTree(); return; }

      updateProjectInfo(file.name, Object.keys(wpFiles).length, totalSize);
      renderFileTree();
      renderWebsite(entryPoint);
      showToast(`ZIP loaded! (${Object.keys(wpFiles).length} files)`, 'success');
    } catch(err) {
      console.error(err);
      showToast('Failed to extract ZIP: ' + err.message, 'error');
    }
  };
  reader.readAsArrayBuffer(file);
}

function processHTMLFile(file) {
  const reader = new FileReader();
  reader.onload = e => {
    wpFiles = {};
    const name = file.name;
    const enc = new TextEncoder();
    const data = enc.encode(e.target.result);
    wpFiles[name] = { data, size: data.length, type: 'text/html', content: e.target.result };
    updateProjectInfo(file.name, 1, data.length);
    renderFileTree();
    renderWebsite(name);
    showToast('HTML file loaded!', 'success');
  };
  reader.readAsText(file);
}

function openPasteModal() {
  if (pasteModal) pasteModal.show();
}
function loadPastedCode() {
  const code = document.getElementById('pasteCodeInput').value.trim();
  if (!code) { showToast('Please paste some HTML code', 'error'); return; }
  wpFiles = {};
  const enc = new TextEncoder();
  wpFiles['index.html'] = { data: enc.encode(code), size: code.length, type: 'text/html', content: code };
  updateProjectInfo('Pasted Code', 1, code.length);
  renderFileTree();
  renderWebsite('index.html');
  if (pasteModal) pasteModal.hide();
  showToast('HTML loaded from paste!', 'success');
}

function findEntryPoint() {
  const candidates = ['index.html', 'index.htm', 'default.html', 'default.htm', 'home.html', 'home.htm'];
  // Check root level first
  for (const c of candidates) {
    if (wpFiles[c]) return c;
  }
  // Check one level deep
  for (const key of Object.keys(wpFiles)) {
    if (key.split('/').length === 2 && candidates.includes(key.split('/').pop())) return key;
  }
  // Fallback: any html file
  return Object.keys(wpFiles).find(k => k.match(/\.html?$/i)) || null;
}

function renderWebsite(entryPoint) {
  document.getElementById('wpUploadArea').style.display = 'none';
  document.getElementById('wpPreviewArea').style.display = 'flex';
  document.getElementById('wpAssetBar').style.display = 'flex';

  // Build blob URL map for all assets
  for (const [name, file] of Object.entries(wpFiles)) {
    if (wpBlobURLs[name]) URL.revokeObjectURL(wpBlobURLs[name]);
    const mime = file.type || getMimeType(name);
    const blob = new Blob([file.data], { type: mime });
    wpBlobURLs[name] = URL.createObjectURL(blob);
  }

  const fileData = wpFiles[entryPoint];
  let htmlContent = fileData.content;
  if (!htmlContent) {
    const dec = new TextDecoder('utf-8');
    htmlContent = dec.decode(fileData.data);
  }
  currentWpHTML = htmlContent;

  // Rewrite asset URLs to blob URLs
  const rewritten = rewriteAssetURLs(htmlContent, entryPoint);

  const frame = document.getElementById('wpFrame');
  frame.srcdoc = rewritten;

  analyzeAssets(htmlContent, entryPoint);
  setViewport(currentViewport);
  document.getElementById('wpPreviewArea').style.display = 'flex';
}

function rewriteAssetURLs(html, basePath) {
  const baseDir = basePath.includes('/') ? basePath.substring(0, basePath.lastIndexOf('/') + 1) : '';
  return html.replace(/(src|href)=["']([^"'#?]+)["']/gi, (match, attr, url) => {
    if (url.startsWith('http') || url.startsWith('//') || url.startsWith('data:')) return match;
    const normalized = (baseDir + url).replace(/\/\.\//g, '/').replace(/[^/]+\/\.\.\//g, '');
    const key = Object.keys(wpFiles).find(k => k === normalized || k.endsWith('/' + url) || k === url);
    if (key && wpBlobURLs[key]) return `${attr}="${wpBlobURLs[key]}"`;
    return match;
  });
}

function analyzeAssets(html, entryPoint) {
  const cssLinks = (html.match(/<link[^>]+rel=["']stylesheet["'][^>]*>/gi) || []).length + (html.match(/<style[\s>]/gi) || []).length;
  const jsScripts = (html.match(/<script[^>]*src=/gi) || []).length + (html.match(/<script[\s>]/gi) || []).length;
  const images = (html.match(/<img[^>]+src=/gi) || []).length;

  const hasCSS = cssLinks > 0 || Object.keys(wpFiles).some(k => k.endsWith('.css'));
  const hasJS  = jsScripts > 0 || Object.keys(wpFiles).some(k => k.endsWith('.js'));
  const hasImg = images > 0 || Object.keys(wpFiles).some(k => k.match(/\.(png|jpg|jpeg|gif|svg|webp|ico)$/i));

  setAssetStatus('assetHTML', true);
  setAssetStatus('assetCSS', hasCSS);
  setAssetStatus('assetJS', hasJS);
  setAssetStatus('assetIMG', hasImg);
}

function setAssetStatus(id, exists) {
  const el = document.getElementById(id);
  el.classList.toggle('ok', exists);
  el.classList.toggle('na', !exists);
}

function renderFileTree() {
  const tree = document.getElementById('fileTree');
  allFileNodes = [];
  if (!Object.keys(wpFiles).length) {
    tree.innerHTML = '<div class="wp-empty-state"><i class="bi bi-folder2"></i><p>No project loaded</p></div>';
    return;
  }

  const folders = {};
  const rootFiles = [];

  for (const [name, file] of Object.entries(wpFiles)) {
    const parts = name.split('/');
    if (parts.length === 1) {
      rootFiles.push({ name, file });
    } else {
      const folder = parts[0];
      if (!folders[folder]) folders[folder] = [];
      folders[folder].push({ name, file });
    }
  }

  let html = '';
  // Root files
  for (const { name, file } of rootFiles) {
    html += buildFileNode(name, name, file);
    allFileNodes.push(name);
  }
  // Folders
  for (const [folder, files] of Object.entries(folders)) {
    html += `<div class="ft-folder"><i class="bi bi-folder2-fill"></i><span class="ft-name">${escapeHtml(folder)}</span></div>`;
    html += `<div class="ft-children">`;
    for (const { name, file } of files) {
      const shortName = name.split('/').pop();
      html += buildFileNode(name, shortName, file);
      allFileNodes.push(name);
    }
    html += `</div>`;
  }
  tree.innerHTML = html;
}

function buildFileNode(fullName, displayName, file) {
  const icon = getFileIcon(fullName);
  const cls = getFileClass(fullName);
  const sizeStr = file.size < 1024 ? file.size + 'B' : Math.round(file.size/1024) + 'KB';
  return `<div class="ft-file ${cls}" onclick="viewFile('${escapeAttr(fullName)}')" title="${escapeHtml(fullName)}">
    <i class="bi ${icon}"></i>
    <span class="ft-name">${escapeHtml(displayName)}</span>
    <span class="ft-size">${sizeStr}</span>
  </div>`;
}

function getFileIcon(name) {
  if (name.match(/\.html?$/i)) return 'bi-filetype-html';
  if (name.match(/\.css$/i)) return 'bi-filetype-css';
  if (name.match(/\.js$/i)) return 'bi-filetype-js';
  if (name.match(/\.(jpg|jpeg|png|gif|webp|svg|ico)$/i)) return 'bi-file-image';
  if (name.match(/\.json$/i)) return 'bi-filetype-json';
  if (name.match(/\.(woff|woff2|ttf|eot)$/i)) return 'bi-fonts';
  return 'bi-file-earmark';
}
function getFileClass(name) {
  if (name.match(/\.html?$/i)) return 'ft-html';
  if (name.match(/\.css$/i)) return 'ft-css';
  if (name.match(/\.js$/i)) return 'ft-js';
  if (name.match(/\.(jpg|jpeg|png|gif|webp|svg|ico)$/i)) return 'ft-img';
  return '';
}

function viewFile(name) {
  document.querySelectorAll('.ft-file').forEach(f => f.classList.remove('active'));
  event.currentTarget && event.currentTarget.classList.add('active');

  const file = wpFiles[name];
  if (!file) return;

  if (name.match(/\.(jpg|jpeg|png|gif|webp|ico)$/i)) {
    const blob = new Blob([file.data]);
    const url = URL.createObjectURL(blob);
    window.open(url, '_blank');
    return;
  }

  let content = file.content;
  if (!content) { const dec = new TextDecoder('utf-8'); content = dec.decode(file.data); }

  document.getElementById('sourceFileName').textContent = name;
  document.getElementById('sourceCodeView').textContent = content;
  if (sourceModal) sourceModal.show();

  // If it's an HTML file, render it
  if (name.match(/\.html?$/i)) renderWebsite(name);
}

function viewSource() {
  const entry = findEntryPoint();
  if (!entry) return;
  viewFile(entry);
}
function copySource() {
  const entry = findEntryPoint();
  if (!entry) return;
  const file = wpFiles[entry];
  let content = file.content;
  if (!content) { const dec = new TextDecoder('utf-8'); content = dec.decode(file.data); }
  navigator.clipboard.writeText(content).then(() => showToast('Source copied!', 'success')).catch(() => showToast('Copy failed', 'error'));
}
function copyModalSource() {
  const text = document.getElementById('sourceCodeView').textContent;
  navigator.clipboard.writeText(text).then(() => showToast('Copied!', 'success')).catch(() => showToast('Failed', 'error'));
}

function reloadWebsite() { const e = findEntryPoint(); if(e) renderWebsite(e); }

function downloadProject() {
  if (!Object.keys(wpFiles).length) { showToast('No project loaded', 'error'); return; }
  // Download entry HTML
  const entry = findEntryPoint();
  if (!entry) return;
  const file = wpFiles[entry];
  let content = file.content;
  if (!content) { const dec = new TextDecoder('utf-8'); content = dec.decode(file.data); }
  const blob = new Blob([content], { type: 'text/html' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = entry.split('/').pop();
  a.click();
  URL.revokeObjectURL(a.href);
  showToast('Downloaded ' + entry, 'success');
}

function openWebsiteNewTab() {
  const entry = findEntryPoint();
  if (!entry || !wpBlobURLs[entry]) return;
  window.open(wpBlobURLs[entry], '_blank');
}

function clearProject() {
  if (Object.keys(wpFiles).length && !confirm('Clear the current project?')) return;
  wpFiles = {};
  Object.values(wpBlobURLs).forEach(u => URL.revokeObjectURL(u));
  wpBlobURLs = {};
  currentWpHTML = '';
  document.getElementById('wpUploadArea').style.display = 'flex';
  document.getElementById('wpPreviewArea').style.display = 'none';
  document.getElementById('wpAssetBar').style.display = 'none';
  document.getElementById('wpProjectInfo').style.display = 'none';
  renderFileTree();
  document.getElementById('zipUpload').value = '';
  document.getElementById('htmlUpload').value = '';
  showToast('Project cleared', 'info');
}

/* ─── VIEWPORT ───────────────────────────────────── */
function setViewport(vp) {
  currentViewport = vp;
  document.querySelectorAll('.vp-btn').forEach(b => b.classList.toggle('active', b.dataset.vp === vp));
  const wrap = document.getElementById('wpIframeWrap');
  const container = document.getElementById('wpIframeContainer');
  const label = document.getElementById('wpViewportLabel');
  wrap.className = 'wp-iframe-wrap';
  container.className = 'wp-iframe-container';
  if (vp === 'desktop') { wrap.classList.add('vp-desktop'); label.textContent = 'Desktop (100%)'; }
  else if (vp === 'tablet') { wrap.classList.add('vp-tablet'); label.textContent = 'Tablet (768px)'; }
  else if (vp === 'mobile') { wrap.classList.add('vp-mobile'); label.textContent = 'Mobile (390px)'; }
  else if (vp === 'fullscreen') { container.classList.add('vp-fullscreen'); label.textContent = 'Fullscreen'; }
}

function toggleSidebar() {
  document.getElementById('wpSidebar').classList.toggle('collapsed');
}

/* ─── FILE SEARCH ────────────────────────────────── */
function searchFiles() {
  const q = document.getElementById('fileSearch').value.toLowerCase();
  document.querySelectorAll('.ft-file').forEach(el => {
    const name = el.title.toLowerCase();
    el.style.display = (!q || name.includes(q)) ? '' : 'none';
  });
  document.querySelectorAll('.ft-folder').forEach(el => {
    el.style.display = '';
  });
}

/* ─── PROJECT INFO ───────────────────────────────── */
function updateProjectInfo(name, count, size) {
  const info = document.getElementById('wpProjectInfo');
  info.style.display = 'block';
  document.getElementById('wpFileName').textContent = name.length > 18 ? name.slice(0,15)+'…' : name;
  document.getElementById('wpFileCount').textContent = count + ' file' + (count !== 1 ? 's' : '');
  document.getElementById('wpFileSize').textContent = size < 1024*1024 ? Math.round(size/1024) + ' KB' : (size/1024/1024).toFixed(1) + ' MB';
}

/* ─── MODALS ─────────────────────────────────────── */
function initModals() {
  const sourceEl = document.getElementById('sourceModal');
  const pasteEl  = document.getElementById('pasteModal');
  if (sourceEl && typeof bootstrap !== 'undefined') sourceModal = new bootstrap.Modal(sourceEl);
  if (pasteEl  && typeof bootstrap !== 'undefined') pasteModal  = new bootstrap.Modal(pasteEl);
}

/* ─── TOAST ──────────────────────────────────────── */
function createToastContainer() {
  if (!document.getElementById('toastContainer')) {
    const c = document.createElement('div');
    c.id = 'toastContainer';
    c.className = 'toast-container';
    document.body.appendChild(c);
  }
}
function showToast(msg, type = 'info') {
  const container = document.getElementById('toastContainer');
  const icons = { success: '✅', error: '❌', info: 'ℹ️', warn: '⚠️' };
  const toast = document.createElement('div');
  toast.className = `toast-msg ${type}`;
  toast.innerHTML = `<span>${icons[type]||''}</span><span>${escapeHtml(msg)}</span>`;
  container.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; setTimeout(() => toast.remove(), 300); }, 2800);
}

/* ─── FAQ ────────────────────────────────────────── */
function toggleFaq(btn) {
  const item = btn.parentElement;
  const wasOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
  if (!wasOpen) item.classList.add('open');
}

/* ─── MIME TYPES ─────────────────────────────────── */
function getMimeType(filename) {
  const ext = filename.split('.').pop().toLowerCase();
  const types = {
    html:'text/html', htm:'text/html', css:'text/css', js:'application/javascript',
    json:'application/json', xml:'text/xml', txt:'text/plain',
    png:'image/png', jpg:'image/jpeg', jpeg:'image/jpeg', gif:'image/gif',
    svg:'image/svg+xml', webp:'image/webp', ico:'image/x-icon',
    woff:'font/woff', woff2:'font/woff2', ttf:'font/ttf', eot:'application/vnd.ms-fontobject',
    mp4:'video/mp4', mp3:'audio/mpeg', pdf:'application/pdf'
  };
  return types[ext] || 'application/octet-stream';
}

/* ─── UTILS ──────────────────────────────────────── */
function escapeHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function escapeAttr(str) {
  return String(str).replace(/'/g,"\\'").replace(/"/g,'&quot;');
}
