/* Regenerates llms.txt — a curated, structured summary of the site for AI
   crawlers/answer engines (ChatGPT, Perplexity, Claude, Gemini) to read,
   following the emerging llms.txt convention (llmstxt.org). Kept in sync
   with assets/tools-data.json on every deploy, same as the sitemap. */
const fs = require('fs');
const path = require('path');
const { ROOT, SITE_URL, readToolsData } = require('./lib');

function generate() {
  const data = readToolsData();
  const byCategory = {};
  data.categories.forEach((c) => { byCategory[c.id] = { name: c.name, tools: [] }; });

  const other = { name: 'Other Tools', tools: [] };
  data.tools
    .filter((t) => t.published !== false)
    .forEach((t) => {
      const catId = t.category || (t.categories && t.categories[0]);
      (byCategory[catId] || other).tools.push(t);
    });

  const lines = [];
  lines.push('# ApneSoftware');
  lines.push('');
  lines.push('> Free, browser-only tools — text, image, PDF, calculators/converters, developer and SEO utilities. Everything runs client-side in the browser; no file or form data is uploaded to a server. No AI is used inside the tools themselves. Bilingual (Hindi + English) content on every page.');
  lines.push('');

  data.categories.forEach((c) => {
    const group = byCategory[c.id];
    if (!group || !group.tools.length) return;
    lines.push(`## ${group.name}`);
    lines.push('');
    group.tools
      .slice()
      .sort((a, b) => a.name.localeCompare(b.name))
      .forEach((t) => {
        lines.push(`- [${t.name}](${SITE_URL}/${t.url}): ${t.description}`);
      });
    lines.push('');
  });

  if (other.tools.length) {
    lines.push(`## ${other.name}`);
    lines.push('');
    other.tools
      .slice()
      .sort((a, b) => a.name.localeCompare(b.name))
      .forEach((t) => {
        lines.push(`- [${t.name}](${SITE_URL}/${t.url}): ${t.description}`);
      });
    lines.push('');
  }

  lines.push('## More');
  lines.push('');
  lines.push(`- [All tools](${SITE_URL}/index.html)`);
  lines.push(`- [About](${SITE_URL}/about.html)`);
  lines.push(`- [Privacy policy](${SITE_URL}/privacy.html)`);
  lines.push('');

  return lines.join('\n');
}

if (require.main === module) {
  const txt = generate();
  fs.writeFileSync(path.join(ROOT, 'llms.txt'), txt);
  const toolCount = (txt.match(/^- \[/gm) || []).length;
  console.log(`llms.txt written — ${toolCount} tool links.`);
}

module.exports = { generate };
