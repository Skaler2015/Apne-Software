/* Shared helpers for the SEO/GEO automation scripts. Pure Node, no deps. */
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..', '..');
const SITE_URL = 'https://apnesoftware.com';

function readToolsData() {
  return JSON.parse(fs.readFileSync(path.join(ROOT, 'assets', 'tools-data.json'), 'utf8'));
}

/* Static, hand-authored top-level pages that belong in the sitemap (excludes
   utility files like 404.html and the Google verification file). */
const STATIC_PAGES = [
  { file: 'index.html', priority: '1.0', changefreq: 'weekly' },
  { file: 'blog.html', priority: '0.6', changefreq: 'monthly' },
  { file: 'about.html', priority: '0.4', changefreq: 'yearly' },
  { file: 'contact.html', priority: '0.4', changefreq: 'yearly' },
  { file: 'privacy.html', priority: '0.4', changefreq: 'yearly' },
  { file: 'terms.html', priority: '0.4', changefreq: 'yearly' },
  { file: 'disclaimer.html', priority: '0.4', changefreq: 'yearly' }
];

function listCategoryPages() {
  const dir = path.join(ROOT, 'category');
  if (!fs.existsSync(dir)) return [];
  return fs.readdirSync(dir).filter((f) => f.endsWith('.html')).map((f) => 'category/' + f);
}

/* Real last-modified date per file, taken from git history so the sitemap
   never carries a stale, hand-typed date. Falls back to today if a file is
   new/uncommitted (e.g. in a dry-run before the first commit). */
function gitLastModDate(relPath) {
  try {
    const out = execSync(`git log -1 --format=%cs -- "${relPath}"`, { cwd: ROOT, stdio: ['pipe', 'pipe', 'ignore'] })
      .toString()
      .trim();
    if (out) return out;
  } catch (e) { /* not a git repo / no history yet */ }
  return new Date().toISOString().slice(0, 10);
}

function listOrphanToolFiles(toolsData) {
  const listed = new Set(toolsData.tools.map((t) => path.basename(t.url)));
  const dir = path.join(ROOT, 'tools');
  const actual = fs.readdirSync(dir).filter((f) => f.endsWith('.html'));
  return actual.filter((f) => !listed.has(f));
}

module.exports = { ROOT, SITE_URL, readToolsData, STATIC_PAGES, listCategoryPages, gitLastModDate, listOrphanToolFiles };
