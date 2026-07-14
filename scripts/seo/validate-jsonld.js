/* Scans every HTML page for <script type="application/ld+json"> blocks and
   makes sure each one is valid, parseable JSON. Catches a broken/edited
   schema block before it ships (Google and AI answer engines silently
   ignore malformed JSON-LD, so this fails loud in CI instead). */
const fs = require('fs');
const path = require('path');
const { ROOT } = require('./lib');

function findHtmlFiles() {
  const files = [];
  const rootFiles = fs.readdirSync(ROOT).filter((f) => f.endsWith('.html'));
  rootFiles.forEach((f) => files.push(f));
  ['tools', 'category'].forEach((dir) => {
    const full = path.join(ROOT, dir);
    if (!fs.existsSync(full)) return;
    fs.readdirSync(full).filter((f) => f.endsWith('.html')).forEach((f) => files.push(path.join(dir, f)));
  });
  return files;
}

const LD_RE = /<script[^>]*type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi;

function validate() {
  const errors = [];
  let pagesWithSchema = 0;
  let totalBlocks = 0;

  findHtmlFiles().forEach((rel) => {
    const html = fs.readFileSync(path.join(ROOT, rel), 'utf8');
    let m;
    let fileBlocks = 0;
    LD_RE.lastIndex = 0;
    while ((m = LD_RE.exec(html))) {
      fileBlocks++;
      totalBlocks++;
      const raw = m[1];
      /* Some SEO utility tools (meta-tag-generator, schema-markup-generator, ...)
         legitimately contain a JS template literal that *renders example*
         <script type="application/ld+json"> markup as text for the user —
         that string match isn't a real schema block, so skip it rather than
         report a false "invalid JSON" error. */
      if (raw.indexOf('${') !== -1) continue;
      try {
        const parsed = JSON.parse(raw);
        const hasType = !!parsed['@type'];
        const hasGraph = Array.isArray(parsed['@graph']) && parsed['@graph'].every((n) => n['@type']);
        if (!hasType && !hasGraph) errors.push(`${rel}: JSON-LD block #${fileBlocks} has no "@type" (and no valid "@graph").`);
      } catch (e) {
        errors.push(`${rel}: JSON-LD block #${fileBlocks} is not valid JSON — ${e.message}`);
      }
    }
    if (fileBlocks > 0) pagesWithSchema++;
  });

  return { errors, pagesWithSchema, totalBlocks };
}

if (require.main === module) {
  const { errors, pagesWithSchema, totalBlocks } = validate();
  console.log(`Checked JSON-LD on ${pagesWithSchema} page(s), ${totalBlocks} block(s) total.`);
  if (errors.length) {
    console.error('\nJSON-LD problems found:');
    errors.forEach((e) => console.error('  - ' + e));
    process.exitCode = 1;
  } else {
    console.log('All JSON-LD blocks are valid.');
  }
}

module.exports = { validate, findHtmlFiles };
