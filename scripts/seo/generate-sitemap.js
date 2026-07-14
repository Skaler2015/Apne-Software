/* Regenerates sitemap.xml from assets/tools-data.json + the static/category
   pages on disk, with a real per-file lastmod pulled from git history.
   Run this before every deploy so the sitemap never goes stale. */
const fs = require('fs');
const path = require('path');
const { ROOT, SITE_URL, readToolsData, STATIC_PAGES, listCategoryPages, gitLastModDate } = require('./lib');

function urlEntry(loc, lastmod, changefreq, priority) {
  return `  <url><loc>${SITE_URL}/${loc}</loc><lastmod>${lastmod}</lastmod><changefreq>${changefreq}</changefreq><priority>${priority}</priority></url>`;
}

function generate() {
  const data = readToolsData();
  const lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

  STATIC_PAGES.forEach((p) => {
    const loc = p.file === 'index.html' ? '' : p.file;
    lines.push(urlEntry(loc, gitLastModDate(p.file), p.changefreq, p.priority));
  });

  listCategoryPages().forEach((rel) => {
    lines.push(urlEntry(rel, gitLastModDate(rel), 'weekly', '0.9'));
  });

  data.tools
    .filter((t) => t.published !== false)
    .forEach((t) => {
      lines.push(urlEntry(t.url, gitLastModDate(t.url), 'monthly', '0.8'));
    });

  lines.push('</urlset>');
  return lines.join('\n') + '\n';
}

if (require.main === module) {
  const xml = generate();
  fs.writeFileSync(path.join(ROOT, 'sitemap.xml'), xml);
  const urlCount = (xml.match(/<url>/g) || []).length;
  console.log(`sitemap.xml written — ${urlCount} URLs.`);
}

module.exports = { generate };
