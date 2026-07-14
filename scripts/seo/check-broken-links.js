/* Best-effort static check for broken internal links: every relative
   href/src in every HTML page must resolve to a real file on disk.
   Skips external links, mailto:/tel:, in-page anchors, and anything the
   page only builds at runtime via JavaScript (those are outside what a
   static scan can verify). */
const fs = require('fs');
const path = require('path');
const { ROOT } = require('./lib');

function findHtmlFiles() {
  const files = [];
  fs.readdirSync(ROOT).filter((f) => f.endsWith('.html')).forEach((f) => files.push(f));
  ['tools', 'category'].forEach((dir) => {
    const full = path.join(ROOT, dir);
    if (!fs.existsSync(full)) return;
    fs.readdirSync(full).filter((f) => f.endsWith('.html')).forEach((f) => files.push(path.join(dir, f)));
  });
  return files;
}

const ATTR_RE = /\s(?:href|src)=["']([^"']+)["']/g;

function isSkippable(url) {
  if (!url || url.startsWith('#')) return true;
  if (/^(https?:)?\/\//i.test(url)) return true;
  if (/^(mailto|tel|javascript|data):/i.test(url)) return true;
  /* JS template-literal interpolation matched inside a runtime-built string,
     not a real static href/src — e.g. href="${item.url}". */
  if (url.indexOf('${') !== -1) return true;
  return false;
}

function check() {
  const errors = [];
  let linksChecked = 0;

  findHtmlFiles().forEach((rel) => {
    /* Strip <script>/<style> contents first — JS/CSS source frequently
       contains text that looks like href="..."/src="..." (template
       literals, worker source strings, generated example markup) but
       isn't a real static link, and would otherwise produce false
       positives here. */
    const html = fs.readFileSync(path.join(ROOT, rel), 'utf8')
      .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '')
      .replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '');
    const baseDir = path.dirname(path.join(ROOT, rel));
    let m;
    ATTR_RE.lastIndex = 0;
    while ((m = ATTR_RE.exec(html))) {
      let url = m[1];
      if (isSkippable(url)) continue;
      url = url.split('#')[0].split('?')[0];
      if (!url) continue;
      linksChecked++;
      /* A leading "/" is root-relative to the site, not to the current file. */
      const target = url.startsWith('/') ? path.join(ROOT, url) : path.resolve(baseDir, url);
      if (!fs.existsSync(target)) errors.push(`${rel}: broken link to "${m[1]}"`);
    }
  });

  return { errors, linksChecked };
}

if (require.main === module) {
  const { errors, linksChecked } = check();
  console.log(`Checked ${linksChecked} internal link(s).`);
  if (errors.length) {
    console.error('\nBroken links found:');
    errors.forEach((e) => console.error('  - ' + e));
    process.exitCode = 1;
  } else {
    console.log('No broken internal links found.');
  }
}

module.exports = { check };
