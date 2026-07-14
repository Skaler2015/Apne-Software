/* Checks every tool page for the meta tags every page on this site is
   expected to carry: title, meta description, canonical link, and the
   Open Graph / Twitter Card pair. Missing tags are a real, silent SEO
   loss (broken social previews, duplicate-content risk), so this fails
   loud in CI instead of only being caught by chance. */
const fs = require('fs');
const path = require('path');
const { ROOT } = require('./lib');

const REQUIRED = [
  { name: 'title', test: (html) => /<title>[^<]+<\/title>/i.test(html) },
  { name: 'meta description', test: (html) => /<meta\s+name=["']description["']\s+content=["'][^"']+["']/i.test(html) },
  { name: 'canonical link', test: (html) => /<link\s+rel=["']canonical["']\s+href=["'][^"']+["']/i.test(html) },
  { name: 'og:title', test: (html) => /<meta\s+property=["']og:title["']/i.test(html) },
  { name: 'og:description', test: (html) => /<meta\s+property=["']og:description["']/i.test(html) },
  { name: 'og:url', test: (html) => /<meta\s+property=["']og:url["']/i.test(html) },
  { name: 'twitter:card', test: (html) => /<meta\s+name=["']twitter:card["']/i.test(html) }
];

function validate() {
  const dir = path.join(ROOT, 'tools');
  const files = fs.readdirSync(dir).filter((f) => f.endsWith('.html'));
  const errors = [];

  files.forEach((f) => {
    const html = fs.readFileSync(path.join(dir, f), 'utf8');
    REQUIRED.forEach((req) => {
      if (!req.test(html)) errors.push(`tools/${f}: missing ${req.name}`);
    });
  });

  return { errors, checked: files.length };
}

if (require.main === module) {
  const { errors, checked } = validate();
  console.log(`Checked meta tags on ${checked} tool page(s).`);
  if (errors.length) {
    console.error('\nMissing tags found:');
    errors.forEach((e) => console.error('  - ' + e));
    process.exitCode = 1;
  } else {
    console.log('Every tool page has all required meta tags.');
  }
}

module.exports = { validate };
