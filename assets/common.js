// Common helper functions used across tools

// ---- Multi-category read helpers ----------------------------------------
// A tool can belong to more than one category. Its categories live in the
// `categories` array (baked into tools-data.json by assets/categorize.js);
// older/unmigrated entries fall back to the single `category` string. These
// helpers are self-contained so every tool page works without categorize.js.
function toolCategories(t){
  if(t && Array.isArray(t.categories) && t.categories.length) return t.categories;
  return t && t.category ? [t.category] : [];
}
function toolInCategory(t, catId){
  return toolCategories(t).indexOf(catId) !== -1;
}

function copyText(text, btnEl){
  navigator.clipboard.writeText(text).then(()=>{
    if(btnEl){
      const old = btnEl.textContent;
      btnEl.textContent = "✅ Copied";
      setTimeout(()=>{ btnEl.textContent = old; }, 1500);
    }
  });
}

function downloadText(filename, content, mime){
  mime = mime || "text/plain";
  const blob = new Blob([content], {type: mime});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

function downloadBlob(filename, blob){
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

function setupDropzone(zoneEl, inputEl, onFiles){
  zoneEl.addEventListener('click', ()=> inputEl.click());
  inputEl.addEventListener('change', e => onFiles(Array.from(e.target.files)));
  ['dragenter','dragover'].forEach(ev=>{
    zoneEl.addEventListener(ev, e=>{ e.preventDefault(); zoneEl.classList.add('drag'); });
  });
  ['dragleave','drop'].forEach(ev=>{
    zoneEl.addEventListener(ev, e=>{ e.preventDefault(); zoneEl.classList.remove('drag'); });
  });
  zoneEl.addEventListener('drop', e=>{
    const files = Array.from(e.dataTransfer.files);
    onFiles(files);
  });
}

function formatBytes(bytes){
  if(bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B','KB','MB','GB'];
  const i = Math.floor(Math.log(bytes)/Math.log(k));
  return parseFloat((bytes/Math.pow(k,i)).toFixed(2)) + ' ' + sizes[i];
}

// ---------- Tool usage tracking (view + run) ----------
// Sends data to backend/track.php. If the backend isn't deployed yet,
// or the database isn't reachable, this fails silently and never
// affects the tool itself.
(function(){
  function initTracking(){
    const match = window.location.pathname.match(/\/tools\/([a-z0-9-]+)\.html$/i);
    if(!match) return; // only track real tool pages

    const toolSlug = match[1];
    const scriptEl = document.querySelector('script[src*="common.js"]');
    const src = scriptEl ? scriptEl.getAttribute('src') : '../assets/common.js';
    const prefix = src.indexOf('../') === 0 ? '../' : '';
    const trackUrl = prefix + 'backend/track.php';

    function sendTrack(type){
      try{
        fetch(trackUrl, {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({
            type: type,
            tool_slug: toolSlug,
            referrer: document.referrer || '',
            landing_page: window.location.href
          }),
          keepalive: true
        }).catch(()=>{});
      }catch(e){}
    }

    sendTrack('view');

    // Any primary action button (Merge / Convert / Generate / Calculate / Download...)
    // inside the tool panel counts as a "run". Secondary/cancel/danger buttons are excluded.
    document.addEventListener('click', (e)=>{
      const btn = e.target.closest('.panel .btn:not(.secondary):not(.danger)');
      if(btn) sendTrack('run');
    });
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initTracking);
  } else {
    initTracking();
  }
})();

// ---------- Mega-menu navigation (auto-injected into .site-header on every page) ----------
(function(){
  function initMegaNav(){
    const header = document.querySelector('.site-header');
    if(!header || document.body.classList.contains('admin-body')) return;
    if(header.querySelector('.mega-nav')) return; // avoid double-init

    const scriptEl = document.querySelector('script[src*="common.js"]');
    const src = scriptEl ? scriptEl.getAttribute('src') : 'assets/common.js';
    const prefix = src.indexOf('../') === 0 ? '../' : '';

    // Detect if on a tool page
    const toolPageSlug = (()=>{
      const m = window.location.pathname.match(/\/tools\/([a-z0-9-]+)\.html$/i);
      return m ? m[1] : null;
    })();

    fetch(prefix + 'assets/tools-data.json', { cache: 'no-store' })
      .then(r => r.json())
      .then(data => {
        buildHeader(data);
        // Inject stepper + reviews on tool pages (after short delay for DOM settle)
        if (toolPageSlug) {
          setTimeout(() => injectStepperAndReviews(data, toolPageSlug, prefix), 100);
        }
      })
      .catch(()=>{});

    function matchTools(data, q){
      q = q.trim().toLowerCase();
      if(!q) return [];
      return data.tools.filter(t => t.published &&
        (t.name.toLowerCase().includes(q) || t.description.toLowerCase().includes(q))
      ).slice(0, 8);
    }

    function renderResultLinks(matches, prefix){
      if(!matches.length) return '<div class="search-no-match">No tools found</div>';
      return matches.map(t =>
        '<a href="'+prefix+t.url+'">'+t.icon+' <span>'+t.name+'</span></a>'
      ).join('');
    }

    function buildHeader(data){
      const inner = header.querySelector('.inner');
      const fileName = window.location.pathname.split('/').pop();
      let activeCategoryId = null;

      // ---------- Desktop horizontal nav with multi-column mega panels ----------
      const nav = document.createElement('div');
      nav.className = 'mega-nav';

      data.categories.forEach(cat=>{
        const toolsInCat = data.tools.filter(t=>toolInCategory(t,cat.id) && t.published);
        if(!toolsInCat.length) return;

        const matchingTool = toolsInCat.find(t => fileName && t.url.endsWith('/'+fileName));
        if(matchingTool) activeCategoryId = matchingTool.category || cat.id;

        const item = document.createElement('div');
        item.className = 'mega-nav-item';
        item.dataset.cat = cat.id;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'mega-nav-btn';
        btn.innerHTML = cat.icon + ' ' + cat.name + ' <span class="caret">▾</span>';
        btn.addEventListener('click', (e)=>{
          e.stopPropagation();
          const isOpen = item.classList.contains('open');
          document.querySelectorAll('.mega-nav-item.open').forEach(el=>el.classList.remove('open'));
          if(!isOpen) item.classList.add('open');
        });

        const panel = document.createElement('div');
        panel.className = 'mega-panel';
        const cols = toolsInCat.length > 10 ? 3 : (toolsInCat.length > 5 ? 2 : 1);
        panel.innerHTML =
          '<div class="mega-panel-grid" style="--cols:'+cols+'">' +
          toolsInCat.map(t=>'<a href="'+prefix+t.url+'">'+t.icon+' <span>'+t.name+'</span></a>').join('') +
          '</div>' +
          '<a class="mega-panel-viewall" href="'+prefix+'index.html#cat-'+cat.id+'">View all '+toolsInCat.length+' '+cat.name+' →</a>';

        item.appendChild(btn);
        item.appendChild(panel);
        nav.appendChild(item);
      });

      if(activeCategoryId){
        const activeBtn = nav.querySelector('.mega-nav-item[data-cat="'+activeCategoryId+'"] .mega-nav-btn');
        if(activeBtn) activeBtn.classList.add('active-cat');
      }

      // ---------- Inline search bar (desktop) ----------
      const searchWrap = document.createElement('div');
      searchWrap.className = 'header-search';
      searchWrap.innerHTML =
        '<input type="text" class="header-search-input" placeholder="🔍 Search tools...">' +
        '<div class="header-search-results"></div>';
      const searchInput = searchWrap.querySelector('.header-search-input');
      const resultsBox = searchWrap.querySelector('.header-search-results');
      searchInput.addEventListener('input', ()=>{
        const matches = matchTools(data, searchInput.value);
        if(!searchInput.value.trim()){ resultsBox.classList.remove('show'); resultsBox.innerHTML=''; return; }
        resultsBox.innerHTML = renderResultLinks(matches, prefix);
        resultsBox.classList.add('show');
      });
      searchInput.addEventListener('focus', ()=>{ if(searchInput.value.trim()) resultsBox.classList.add('show'); });

      // ---------- Hamburger (mobile only, shown via CSS) ----------
      const hamburger = document.createElement('button');
      hamburger.type = 'button';
      hamburger.className = 'mega-hamburger';
      hamburger.setAttribute('aria-label', 'Open menu');
      hamburger.innerHTML = '<span></span><span></span><span></span>';

      const backLink = inner.querySelector('.back-link');
      if(backLink){
        inner.insertBefore(nav, backLink);
        inner.insertBefore(searchWrap, backLink);
      } else {
        inner.appendChild(nav);
        inner.appendChild(searchWrap);
      }
      inner.appendChild(hamburger);

      // ---------- Mobile drawer ----------
      const overlay = document.createElement('div');
      overlay.className = 'drawer-overlay';
      const drawer = document.createElement('div');
      drawer.className = 'mobile-drawer';
      drawer.innerHTML =
        '<div class="drawer-head"><span class="drawer-title">Apne<b>Software</b></span><button type="button" class="drawer-close" aria-label="Close menu">✕</button></div>' +
        '<input type="text" class="drawer-search" placeholder="🔍 Search tools...">' +
        '<div class="drawer-results"></div>' +
        '<div class="drawer-categories"></div>';
      document.body.appendChild(overlay);
      document.body.appendChild(drawer);

      const drawerCatsWrap = drawer.querySelector('.drawer-categories');
      data.categories.forEach(cat=>{
        const toolsInCat = data.tools.filter(t=>toolInCategory(t,cat.id) && t.published);
        if(!toolsInCat.length) return;
        const sec = document.createElement('div');
        sec.className = 'drawer-cat' + (cat.id === activeCategoryId ? ' open' : '');
        sec.innerHTML =
          '<button type="button" class="drawer-cat-head">'+cat.icon+' '+cat.name+
          ' <span class="drawer-cat-count">'+toolsInCat.length+'</span><span class="drawer-caret">▾</span></button>' +
          '<div class="drawer-cat-body">' +
          toolsInCat.map(t=>'<a href="'+prefix+t.url+'">'+t.icon+' '+t.name+'</a>').join('') +
          '</div>';
        sec.querySelector('.drawer-cat-head').addEventListener('click', ()=> sec.classList.toggle('open'));
        drawerCatsWrap.appendChild(sec);
      });

      const drawerSearchInput = drawer.querySelector('.drawer-search');
      const drawerResults = drawer.querySelector('.drawer-results');
      drawerSearchInput.addEventListener('input', ()=>{
        const matches = matchTools(data, drawerSearchInput.value);
        if(!drawerSearchInput.value.trim()){ drawerResults.classList.remove('show'); drawerResults.innerHTML=''; return; }
        drawerResults.innerHTML = renderResultLinks(matches, prefix);
        drawerResults.classList.add('show');
      });

      function openDrawer(){
        drawer.classList.add('open');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
      }
      function closeDrawer(){
        drawer.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
      }
      hamburger.addEventListener('click', openDrawer);
      overlay.addEventListener('click', closeDrawer);
      drawer.querySelector('.drawer-close').addEventListener('click', closeDrawer);

      // ---------- Left sidebar (tool pages only, wide screens) ----------
      const toolPageMatch = window.location.pathname.match(/\/tools\/([a-z0-9-]+)\.html$/i);
      if (toolPageMatch) {
        buildLeftSidebar(data, prefix, activeCategoryId);
      }
    }

    function buildLeftSidebar(data, prefix, activeCategoryId) {
      const container = document.querySelector('body > .container');
      if (!container) return;

      const wrapper = document.createElement('div');
      wrapper.className = 'with-sidebar-layout';

      const sidebar = document.createElement('aside');
      sidebar.className = 'left-sidebar';
      sidebar.innerHTML = '<input type="text" class="left-sidebar-search" placeholder="Filter categories...">' +
        '<div class="lsb-cats"></div>';

      const lsbCats = sidebar.querySelector('.lsb-cats');
      data.categories.forEach(cat => {
        const toolsInCat = data.tools.filter(t => toolInCategory(t, cat.id) && t.published);
        if (!toolsInCat.length) return;

        const isActive = cat.id === activeCategoryId;
        const sec = document.createElement('div');
        sec.className = 'lsb-cat' + (isActive ? ' active-cat open' : '');
        sec.dataset.catName = cat.name.toLowerCase();
        sec.innerHTML =
          '<button type="button" class="lsb-cat-head">' + cat.icon + ' ' + cat.name +
          '<span class="lsb-cat-count">' + toolsInCat.length + '</span>' +
          '<span class="lsb-caret">▾</span></button>' +
          '<div class="lsb-cat-body">' +
          toolsInCat.map(t => '<a href="' + prefix + t.url + '">' + t.icon + ' ' + t.name + '</a>').join('') +
          '</div>';
        sec.querySelector('.lsb-cat-head').addEventListener('click', () => sec.classList.toggle('open'));
        lsbCats.appendChild(sec);
      });

      sidebar.querySelector('.left-sidebar-search').addEventListener('input', (e) => {
        const q = e.target.value.trim().toLowerCase();
        lsbCats.querySelectorAll('.lsb-cat').forEach(sec => {
          sec.classList.toggle('lsb-hidden', q !== '' && !sec.dataset.catName.includes(q));
        });
      });

      container.parentNode.insertBefore(wrapper, container);
      
      // Rename left sidebar class for new dual-sidebar CSS
      sidebar.className = 'tool-left-sidebar';
      
      wrapper.appendChild(sidebar);
      wrapper.appendChild(container);

      // ---------- Keep below-tool content in the main column ----------
      // About / FAQ / Related / Privacy sections are authored as siblings
      // after the tool <div class="container">. Move them INTO the container
      // so they share the tool's (wide) main-column width instead of showing
      // as a narrow centred strip on large screens. This runs for every tool,
      // so new tools get full-width content automatically. Fixed overlays
      // (toasts, floating buttons) move too but stay viewport-fixed.
      (function absorbBelowContent(){
        let sib = wrapper.nextElementSibling;
        while (sib) {
          const next = sib.nextElementSibling;
          const tag = sib.tagName;
          if (tag === 'FOOTER' || (sib.classList && sib.classList.contains('site-footer'))) break;
          if (!/^(SCRIPT|STYLE|LINK|NOSCRIPT|TEMPLATE)$/.test(tag)) {
            sib.style.maxWidth = 'none';       // fill the column, not a 1040px strip
            sib.style.marginLeft = '0';
            sib.style.marginRight = '0';
            container.appendChild(sib);
          }
          sib = next;
        }
      })();

      // Build RIGHT sidebar with related tools
      const rightSidebar = document.createElement('aside');
      rightSidebar.className = 'tool-right-sidebar';
      
      // Get current tool slug
      const slugMatch = window.location.pathname.match(/\/tools\/([a-z0-9-]+)\.html$/i);
      const currentSlug = slugMatch ? slugMatch[1] : '';
      
      // Find current tool's categories (a tool can belong to several)
      const currentTool = data.tools.find(t => t.id === currentSlug);
      const currentCats = currentTool ? toolCategories(currentTool) : [];

      // Related tools = share at least one category, exclude current
      const related = data.tools.filter(t =>
        t.published && t.id !== currentSlug &&
        toolCategories(t).some(c => currentCats.indexOf(c) !== -1)
      ).slice(0, 8);
      
      // Popular tools (show some across all categories)
      const popular = ['pdf-merge','image-compressor','qr-generator','word-counter','emi-calculator','password-generator','pdf-compress','image-resizer'];
      const popularTools = popular.map(id => data.tools.find(t => t.id === id)).filter(Boolean);

      // Tool Analytics card (loaded async from backend)
      rightSidebar.innerHTML = 
        '<div class="tsb-card" id="toolAnalyticsCard">' +
          '<h4>📊 This Tool Stats</h4>' +
          '<div id="toolStatsBox"><p style="font-size:.78rem;color:var(--text-dim)">Loading...</p></div>' +
        '</div>' +
        '<div class="tsb-card">' +
          '<h4>📂 Related Tools</h4>' +
          (related.length ? related.map(t => 
            '<a href="' + prefix + t.url + '" class="tsb-link">' + (t.icon||'🔧') + ' ' + t.name + '</a>'
          ).join('') : '<p style="font-size:.78rem;color:var(--text-dim)">No related tools.</p>') +
        '</div>' +
        '<div class="tsb-card">' +
          '<h4>⭐ Popular Tools</h4>' +
          popularTools.map(t => 
            '<a href="' + prefix + t.url + '" class="tsb-link">' + (t.icon||'🔧') + ' ' + t.name + '</a>'
          ).join('') +
        '</div>';

      // Load tool-specific analytics from backend
      (async function loadToolStats() {
        try {
          const res = await fetch(prefix + 'backend/tool_stats.php?slug=' + currentSlug, { cache: 'no-store' });
          const d = await res.json();
          const el = document.getElementById('toolStatsBox');
          if (!el) return;
          const fmt = n => Number(n || 0).toLocaleString('en-IN');
          if (d.total_views === 0 && d.total_runs === 0) {
            el.innerHTML = '<p style="font-size:.78rem;color:var(--text-dim)">No data yet — be the first to use this tool!</p>';
            return;
          }
          el.innerHTML =
            '<div class="tsb-stat"><span>👁️ Total Views</span><strong>' + fmt(d.total_views) + '</strong></div>' +
            '<div class="tsb-stat"><span>⚡ Total Uses</span><strong>' + fmt(d.total_runs) + '</strong></div>' +
            '<div class="tsb-stat"><span>📅 Today Views</span><strong>' + fmt(d.today_views) + '</strong></div>' +
            '<div class="tsb-stat"><span>🔥 Today Uses</span><strong>' + fmt(d.today_runs) + '</strong></div>' +
            '<div class="tsb-stat"><span>📆 Last 7 Days</span><strong>' + fmt(d.last_7d_views) + ' views</strong></div>' +
            '<div class="tsb-stat"><span>📆 Last 30 Days</span><strong>' + fmt(d.last_30d_views) + ' views</strong></div>' +
            (d.top_countries && d.top_countries.length ?
              '<div style="margin-top:8px;font-size:.75rem;font-weight:700;color:var(--text-dim)">🌍 Top Countries</div>' +
              d.top_countries.map(c => 
                '<div class="tsb-stat"><span>' + (c.country||'Unknown') + '</span><strong>' + fmt(c.cnt) + '</strong></div>'
              ).join('') : '');
        } catch(e) {
          const el = document.getElementById('toolStatsBox');
          if (el) el.innerHTML = '<p style="font-size:.78rem;color:var(--text-dim)">Stats unavailable.</p>';
        }
      })();
      
      wrapper.appendChild(rightSidebar);
    }

    document.addEventListener('click', (e)=>{
      document.querySelectorAll('.mega-nav-item.open').forEach(el=>el.classList.remove('open'));
      if(!e.target.closest('.header-search')){
        document.querySelectorAll('.header-search-results.show').forEach(el=>el.classList.remove('show'));
      }
    });
    document.addEventListener('keydown', (e)=>{
      if(e.key === 'Escape'){
        document.querySelectorAll('.mega-nav-item.open').forEach(el=>el.classList.remove('open'));
        const drawer = document.querySelector('.mobile-drawer.open');
        if(drawer){
          drawer.classList.remove('open');
          document.querySelector('.drawer-overlay').classList.remove('show');
          document.body.style.overflow = '';
        }
      }
    });
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initMegaNav);
  } else {
    initMegaNav();
  }
})();

// ══════════════════════════════════════════════════════════════
//  STEPPER + REVIEW SYSTEM — auto-injected on all tool pages
// ══════════════════════════════════════════════════════════════
function injectStepperAndReviews(toolsData, slug, prefix) {
  if (!slug) return;
  const tool = toolsData.tools.find(t => t.id === slug);
  if (!tool) return;

  // ── STEP DATA per category ─────────────────────────────────
  const STEPS = {
    pdf: [
      { n:1, title:'Upload PDF', desc:'Drag & drop or click to choose your PDF file' },
      { n:2, title:'Configure', desc:'Set options such as page range, rotation or compression' },
      { n:3, title:'Download Result', desc:'Click the action button and download your file' }
    ],
    image: [
      { n:1, title:'Upload Image', desc:'Drag & drop or click to choose your image file' },
      { n:2, title:'Set Options', desc:'Adjust quality, size, format or other settings' },
      { n:3, title:'Download Result', desc:'Click the action button and save your image' }
    ],
    text: [
      { n:1, title:'Paste Your Text', desc:'Type or paste the text you want to process' },
      { n:2, title:'Choose Settings', desc:'Select options like case, format or mode if needed' },
      { n:3, title:'Copy or Download', desc:'Click the action button and copy or download the result' }
    ],
    calculator: [
      { n:1, title:'Enter Values', desc:'Fill in the required numbers or details in the fields' },
      { n:2, title:'Click Calculate', desc:'Press the Calculate button to get your result instantly' },
      { n:3, title:'Read the Result', desc:'Your answer appears in the result section below' }
    ],
    developer: [
      { n:1, title:'Enter or Paste Input', desc:'Type or paste your code, text or data' },
      { n:2, title:'Choose Options', desc:'Select encoding, format or mode as needed' },
      { n:3, title:'Copy the Output', desc:'Click the action button and copy your result' }
    ]
  };

  const steps = STEPS[tool.category] || STEPS.text;

  // ── BUILD STEPPER HTML ─────────────────────────────────────
  const stepperEl = document.createElement('div');
  stepperEl.className = 'tool-stepper glass-card';
  stepperEl.setAttribute('aria-label', 'How to use ' + tool.name);
  stepperEl.innerHTML = `
    <div class="stepper-label">📋 How to Use ${tool.name}</div>
    <div class="stepper-steps">
      ${steps.map((s, i) => `
        <div class="stepper-step ${i === 0 ? 'active' : ''}">
          <div class="stepper-num">${s.n}</div>
          <div class="stepper-text">
            <span class="stepper-title">${s.title}</span>
            <span class="stepper-desc">${s.desc}</span>
          </div>
          ${i < steps.length - 1 ? '<div class="stepper-line"></div>' : ''}
        </div>
      `).join('')}
    </div>`;

  // Insert stepper before .tool-header
  const toolHeader = document.querySelector('.tool-header');
  if (toolHeader) {
    toolHeader.parentNode.insertBefore(stepperEl, toolHeader);
  }

  // ── BUILD REVIEW SECTION ───────────────────────────────────
  const reviewEl = document.createElement('div');
  reviewEl.className = 'tool-review-section glass-card';
  reviewEl.id = 'toolReviewSection';
  reviewEl.innerHTML = `
    <div class="review-heading">
      <h3>⭐ Rate & Review This Tool</h3>
      <p>Help others by sharing your experience with <strong>${tool.name}</strong></p>
    </div>

    <div class="review-stats-wrap" id="reviewStatsWrap" style="display:none">
      <div class="review-avg-big" id="reviewAvgBig">0.0</div>
      <div class="review-stars-display" id="reviewStarsDisplay"></div>
      <div class="review-total" id="reviewTotal">0 reviews</div>
      <div class="review-bars" id="reviewBars"></div>
    </div>

    <div class="review-form-wrap">
      <div class="star-picker" id="starPicker" aria-label="Rate this tool">
        ${[1,2,3,4,5].map(n => `<button class="star-btn" data-val="${n}" aria-label="${n} star${n>1?'s':''}" type="button">★</button>`).join('')}
      </div>
      <div class="review-rating-label" id="ratingLabel">Click to rate</div>

      <div class="review-fields">
        <input type="text" id="reviewName" placeholder="Your name (optional)" maxlength="80">
        <textarea id="reviewComment" placeholder="Share your experience (optional) — what did you use this tool for?" rows="3" maxlength="1000"></textarea>
      </div>
      <button class="btn review-submit-btn" id="reviewSubmitBtn" type="button">Submit Review</button>
      <p class="review-msg" id="reviewMsg"></p>
    </div>

    <div class="existing-reviews" id="existingReviews"></div>`;

  // Insert review section right after last .panel, before info-wrap
  // This keeps it inside .container = between the two sidebars
  const panels = document.querySelectorAll('.panel');
  const lastPanel = panels[panels.length - 1];
  const infoWrap = document.querySelector('.info-wrap');

  if (lastPanel) {
    // Find next sibling that isn't mobile-sticky or info-wrap
    let insertTarget = lastPanel.nextElementSibling;
    // Skip any non-content siblings (mobile sticky, scripts, etc.)
    while(insertTarget && (insertTarget.classList.contains('mobile-sticky') ||
          insertTarget.tagName === 'SCRIPT')) {
      insertTarget = insertTarget.nextElementSibling;
    }
    if(insertTarget && insertTarget !== infoWrap) {
      lastPanel.parentNode.insertBefore(reviewEl, insertTarget);
    } else if(infoWrap) {
      // Insert just before info-wrap — still inside container
      const container = document.querySelector('.container');
      if(container && container.contains(infoWrap)) {
        container.insertBefore(reviewEl, infoWrap);
      } else {
        lastPanel.insertAdjacentElement('afterend', reviewEl);
      }
    } else {
      lastPanel.insertAdjacentElement('afterend', reviewEl);
    }
  } else if (infoWrap) {
    const container = document.querySelector('.container');
    if(container) container.insertBefore(reviewEl, infoWrap);
    else infoWrap.parentNode.insertBefore(reviewEl, infoWrap);
  } else {
    document.body.appendChild(reviewEl);
  }

  // ── STAR PICKER ─────────────────────────────────────────────
  let selectedRating = 0;
  const STAR_LABELS = ['','Terrible','Poor','Average','Good','Excellent'];
  const stars = reviewEl.querySelectorAll('.star-btn');
  const ratingLabel = reviewEl.querySelector('#ratingLabel');

  function setStars(val, hover) {
    stars.forEach(s => {
      const v = +s.dataset.val;
      s.classList.toggle('filled', v <= val);
      s.classList.toggle('hover', hover && v <= val);
    });
    if (!hover && val > 0) ratingLabel.textContent = STAR_LABELS[val] + ' (' + val + '/5)';
    else if (val === 0) ratingLabel.textContent = 'Click to rate';
  }

  stars.forEach(s => {
    s.addEventListener('mouseenter', () => setStars(+s.dataset.val, true));
    s.addEventListener('mouseleave', () => setStars(selectedRating, false));
    s.addEventListener('click', () => {
      selectedRating = +s.dataset.val;
      setStars(selectedRating, false);
    });
  });

  // ── SUBMIT ──────────────────────────────────────────────────
  reviewEl.querySelector('#reviewSubmitBtn').addEventListener('click', async () => {
    const msg = reviewEl.querySelector('#reviewMsg');
    const comment = reviewEl.querySelector('#reviewComment').value.trim();
    const name = reviewEl.querySelector('#reviewName').value.trim();

    if (!selectedRating) { msg.textContent = '⚠️ Please select a star rating.'; msg.style.color='#E2615D'; return; }
    // Name and comment are optional — no validation required

    const btn = reviewEl.querySelector('#reviewSubmitBtn');
    btn.disabled = true; btn.textContent = 'Submitting...';

    try {
      const res = await fetch(prefix + 'backend/review.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ slug, tool_name: tool.name, rating: selectedRating, comment, name })
      });
      const data = await res.json();
      if (data.ok) {
        msg.textContent = '✅ ' + (data.message || 'Thank you for your review!');
        msg.style.color = '#1FAE7D';
        reviewEl.querySelector('#reviewComment').value = '';
        reviewEl.querySelector('#reviewName').value = '';
        selectedRating = 0; setStars(0, false);
        loadReviews();
      } else {
        msg.textContent = '❌ ' + data.error;
        msg.style.color = '#E2615D';
      }
    } catch(e) {
      msg.textContent = '❌ Could not submit. Please try again.';
      msg.style.color = '#E2615D';
    }
    btn.disabled = false; btn.textContent = 'Submit Review';
  });

  // ── LOAD REVIEWS ────────────────────────────────────────────
  async function loadReviews() {
    try {
      const res = await fetch(prefix + 'backend/review.php?slug=' + slug, {cache:'no-store'});
      const data = await res.json();
      if (!data.ok) return;

      const s = data.stats;
      const statsWrap = reviewEl.querySelector('#reviewStatsWrap');
      if (s && s.total > 0) {
        statsWrap.style.display = 'flex';
        reviewEl.querySelector('#reviewAvgBig').textContent = parseFloat(s.avg_rating).toFixed(1);
        reviewEl.querySelector('#reviewStarsDisplay').innerHTML = [1,2,3,4,5].map(n =>
          `<span class="${n <= Math.round(s.avg_rating) ? 'star-on' : 'star-off'}">★</span>`
        ).join('');
        reviewEl.querySelector('#reviewTotal').textContent = s.total + ' review' + (s.total>1?'s':'');
        const maxR = Math.max(s.r5,s.r4,s.r3,s.r2,s.r1,1);
        reviewEl.querySelector('#reviewBars').innerHTML = [5,4,3,2,1].map(n => `
          <div class="rating-bar-row">
            <span>${n}★</span>
            <div class="rating-bar-track"><div class="rating-bar-fill" style="width:${Math.round((s['r'+n]||0)/maxR*100)}%"></div></div>
            <span>${s['r'+n]||0}</span>
          </div>`).join('');
      }

      const container = reviewEl.querySelector('#existingReviews');
      if (data.reviews && data.reviews.length) {
        container.innerHTML = '<h4 style="color:rgba(255,255,255,.8);margin-bottom:12px">User Reviews</h4>' +
          data.reviews.map(r => `
            <div class="review-card">
              <div class="review-card-top">
                <div class="reviewer-avatar">${(r.reviewer_name||'A')[0].toUpperCase()}</div>
                <div>
                  <span class="reviewer-name">${r.reviewer_name||'Anonymous'}</span>
                  <span class="review-stars-small">${[1,2,3,4,5].map(n=>`<span>${n<=r.rating?'★':'☆'}</span>`).join('')}</span>
                </div>
                <span class="review-date">${new Date(r.created_at).toLocaleDateString('en-IN',{day:'numeric',month:'short',year:'numeric'})}</span>
              </div>
              ${r.comment ? '<p class="review-text">' + r.comment.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p>' : ''}
            </div>`).join('');
      } else {
        container.innerHTML = '<p style="color:rgba(255,255,255,.4);font-size:.85rem;text-align:center;padding:16px">No reviews yet — be the first to review this tool!</p>';
      }
    } catch(e) {}
  }

  loadReviews();
}

// ══════════════════════════════════════════════════════════════
//  PREMIUM TOOL ENHANCEMENT — Auto-injected on all tool pages
//  Upgrades dropzones, adds trust badges, injects progress UI
// ══════════════════════════════════════════════════════════════
(function premiumToolEnhancement(){
  if(!document.querySelector('.tool-header')) return; // not a tool page

  /* ── 1. ENHANCE ALL DROPZONES ── */
  document.querySelectorAll('.dropzone').forEach(dz => {
    if(dz.dataset.enhanced) return;
    dz.dataset.enhanced = '1';

    // Detect file type from input accept attribute
    const input = dz.querySelector('input[type=file]');
    const accept = input ? input.getAttribute('accept') : '';
    let icon = '📁', label = 'Drop Files Here', sub = 'or click to browse your device';
    let meta = '🔒 Secure · No Upload to Server';

    if(accept && accept.includes('pdf')){ icon='📄'; label='Drop PDF File Here'; meta='📄 PDF Only · 🔒 Secure Processing · No Server Upload'; }
    else if(accept && (accept.includes('image') || accept.includes('png') || accept.includes('jpg'))){ icon='🖼️'; label='Drop Image File Here'; meta='🖼️ JPG/PNG/WebP · 🔒 Secure · No Upload'; }
    else if(accept && accept.includes('video')){ icon='🎬'; label='Drop Video File Here'; meta='🎬 Video Files · 🔒 Secure Processing'; }
    else if(accept && accept.includes('audio')){ icon='🎵'; label='Drop Audio File Here'; meta='🎵 Audio Files · 🔒 Secure Processing'; }
    else if(accept && accept.includes('csv')){ icon='📊'; label='Drop CSV File Here'; meta='📊 CSV Files · 🔒 Secure Processing'; }

    // Get original text if any
    const origP = dz.querySelector('p');
    if(origP){ const t = origP.textContent.trim(); if(t.length > 10) sub = t; }

    // Replace dropzone inner content
    const origInput = dz.querySelector('input[type=file]');
    if(origInput) origInput.remove();

    dz.innerHTML = `
      <span class="prem-upload-icon">${icon}</span>
      <div class="prem-upload-title">${label}</div>
      <div class="prem-upload-sub">${sub.replace(/📤/g,'').replace(/Drag & drop/gi,'Drag & drop').substring(0,80)}</div>
      <button class="prem-upload-cta" type="button">+ Choose File</button>
      <div class="prem-upload-meta">
        ${meta.split('·').map(m=>`<span>${m.trim()}</span>`).join('')}
      </div>`;

    // Re-add hidden input
    if(origInput){
      origInput.style.display = 'none';
      dz.appendChild(origInput);
      dz.querySelector('.prem-upload-cta').addEventListener('click', e => {
        e.stopPropagation();
        origInput.click();
      });
    }
  });

  /* ── 2. INJECT PROGRESS OVERLAY ── */
  if(!document.getElementById('premProgressOverlay')){
    const overlay = document.createElement('div');
    overlay.className = 'prem-progress-overlay';
    overlay.id = 'premProgressOverlay';
    overlay.style.display = 'none';
    overlay.innerHTML = `
      <div class="prem-progress-box">
        <span class="prem-prog-icon" id="premProgIcon">⏳</span>
        <div class="prem-prog-title" id="premProgTitle">Processing...</div>
        <div class="prem-prog-sub" id="premProgSub">Please wait</div>
        <div class="prem-prog-bar-wrap"><div class="prem-prog-bar" id="premProgBar"></div></div>
        <div class="prem-prog-pct" id="premProgPct">0%</div>
      </div>`;
    document.body.appendChild(overlay);
  }

  /* ── 3. INJECT TRUST BADGES above info-wrap ── */
  const infoWrap = document.querySelector('.info-wrap');
  if(infoWrap && !document.querySelector('.prem-trust-row')){
    const trustDiv = document.createElement('div');
    trustDiv.innerHTML = `
      <div class="prem-sep"><span>🔒 Privacy & Security</span></div>
      <div class="prem-trust-row">
        <div class="prem-trust-card"><span class="ti">🔒</span><div class="tt">Secure Processing</div><div class="ts">All in your browser</div></div>
        <div class="prem-trust-card"><span class="ti">🗑</span><div class="tt">No Files Stored</div><div class="ts">Files never leave device</div></div>
        <div class="prem-trust-card"><span class="ti">☁</span><div class="tt">No Account Needed</div><div class="ts">100% free forever</div></div>
        <div class="prem-trust-card"><span class="ti">⚡</span><div class="tt">Instant Results</div><div class="ts">Browser-powered speed</div></div>
      </div>`;
    infoWrap.parentNode.insertBefore(trustDiv, infoWrap);
  }

})();

/* Global premium progress helpers — usable in any tool */
function premShowProgress(icon, title, sub, pct){
  const el = document.getElementById('premProgressOverlay');
  if(!el) return;
  document.getElementById('premProgIcon').textContent = icon || '⏳';
  document.getElementById('premProgTitle').textContent = title || 'Processing...';
  document.getElementById('premProgSub').textContent = sub || 'Please wait';
  premSetProgress(pct || 0);
  el.style.display = 'flex';
}
function premSetProgress(pct){
  const bar = document.getElementById('premProgBar');
  const pctEl = document.getElementById('premProgPct');
  if(bar) bar.style.width = pct + '%';
  if(pctEl) pctEl.textContent = pct + '%';
}
function premHideProgress(){
  const el = document.getElementById('premProgressOverlay');
  if(el) el.style.display = 'none';
}
function premToast(msg, duration){
  const t = document.createElement('div');
  t.className = 'prem-toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), duration || 3000);
}

