/*!
 * ApneSoftware — auto-categorization engine (shared, single source of truth)
 * ---------------------------------------------------------------------------
 * A tool can belong to MORE THAN ONE category. This module derives a tool's
 * categories from its name + description using a keyword rule set, so a newly
 * published tool is automatically placed in every category it fits — no need to
 * re-publish the same tool separately per category.
 *
 * Used by:
 *   - tools/build-categories (Node) to stamp `categories` into tools-data.json
 *   - skaler2015/manage-tools.php admin panel (auto-suggest + bulk apply)
 *
 * Render-side code (index.html, common.js) only READS the baked `categories`
 * array via ApneCat.categoriesOf() / ApneCat.inCategory() — it does not need the
 * rule engine at runtime.
 *
 * Matching: each keyword matches as a whole token (alphanumeric boundaries), so
 * "age" does NOT match "image"/"average" and "tax" does NOT match "syntax".
 * Spaces inside a keyword match any run of whitespace.
 */
(function (global) {
  'use strict';

  // category id -> keyword list. Order here defines the order extra categories
  // are appended in. Keep ids in sync with tools-data.json `categories`.
  var RULES = {
    text: [
      'word', 'words', 'character', 'characters', 'letter', 'letters', 'uppercase',
      'lowercase', 'case', 'lorem', 'ipsum', 'paragraph', 'paragraphs', 'readability',
      'citation', 'slug', 'sentence', 'spell', 'grammar', 'speech', 'markdown', 'reverse',
      'find and replace', 'sort', 'duplicate line', 'cleaner', 'clean up', 'fancy text',
      'essay', 'notepad', 'number to words', 'words to number', 'docx', 'plain text',
      'typing', 'proofread', 'translate', 'transcript', 'ocr', 'extract text'
    ],
    image: [
      'image', 'images', 'photo', 'photos', 'picture', 'png', 'jpg', 'jpeg', 'webp', 'gif',
      'heic', 'favicon', 'icon', 'resize', 'crop', 'pixel', 'pixelate', 'exif', 'watermark',
      'meme', 'collage', 'filter', 'filters', 'blur', 'brightness', 'contrast', 'dpi',
      'palette', 'passport', 'swatch', 'logo', 'stamp', 'thumbnail', 'rgb', 'hsl', 'cmyk',
      'colour', 'color picker', 'color palette', 'gradient', 'qr code', 'barcode', 'scan'
    ],
    pdf: [
      'pdf', 'document', 'documents', 'docx', 'xlsx', 'spreadsheet', 'bates', 'booklet',
      'certificate', 'resume', 'cv', 'invoice', 'billing', 'letter template', 'to pdf',
      'page numbers', 'redact', 'fillable', 'form filler', 'annotate'
    ],
    calculator: [
      'calculator', 'calculate', 'percentage', 'percent', 'discount', 'tax', 'gst', 'emi',
      'loan', 'interest', 'sip', 'swp', 'deposit', 'provident fund', 'ppf', 'pf', 'hra',
      'gratuity', 'salary', 'ctc', 'bmi', 'bmr', 'calorie', 'tip', 'ratio', 'average', 'mean',
      'median', 'deviation', 'variance', 'roi', 'cagr', 'profit', 'loss', 'break even',
      'lcm', 'hcf', 'water intake', 'working days', 'unit converter', 'compound', 'stock',
      'body fat', 'maturity', 'corpus', 'age', 'currency', 'math'
    ],
    developer: [
      'json', 'xml', 'yaml', 'html', 'css', 'javascript', 'base64', 'base32', 'hash', 'md5',
      'sha', 'sha256', 'crc32', 'uuid', 'regex', 'epoch', 'unix', 'timestamp', 'minify',
      'minifier', 'beautify', 'beautifier', 'escape', 'escaping', 'api', 'jwt', 'cron', 'code',
      'encode', 'decode', 'encoder', 'decoder', 'developer', 'binary', 'hex', 'hexadecimal',
      'password', 'http headers', 'http header', 'css gradient', 'cli'
    ],
    seo: [
      'seo', 'meta tag', 'meta title', 'meta description', 'meta robots', 'keyword',
      'keywords', 'serp', 'sitemap', 'robots.txt', 'schema markup', 'structured data',
      'open graph', 'og tag', 'twitter card', 'canonical', 'ranking', 'rank tracker',
      'search results', 'rich results', 'keyword density', 'backlink', 'google search'
    ]
  };

  var _compiled = null;
  function compiled() {
    if (_compiled) return _compiled;
    _compiled = {};
    Object.keys(RULES).forEach(function (cat) {
      _compiled[cat] = RULES[cat].map(function (kw) {
        var esc = kw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g, '\\s+');
        return new RegExp('(?:^|[^a-z0-9])' + esc + '(?:[^a-z0-9]|$)', 'i');
      });
    });
    return _compiled;
  }

  // Pure keyword result for a name/description — ordered by RULES key order.
  function suggest(name, description) {
    var hay = ' ' + String(name || '').toLowerCase() + ' ' +
              String(description || '').toLowerCase() + ' ';
    var pats = compiled();
    var out = [];
    Object.keys(pats).forEach(function (cat) {
      if (pats[cat].some(function (re) { return re.test(hay); })) out.push(cat);
    });
    return out;
  }

  // Full categories for a tool: its declared primary category first (guaranteed),
  // then any additional keyword-matched categories. Never returns an empty list.
  function autoCategories(tool) {
    tool = tool || {};
    var matched = suggest(tool.name, tool.description);
    var out = [];
    var primary = tool.category || tool.categories && tool.categories[0];
    if (primary) out.push(primary);
    matched.forEach(function (c) { if (out.indexOf(c) === -1) out.push(c); });
    if (!out.length) out = ['developer'];
    return out;
  }

  // ---- Render-side read helpers (no rule engine needed) ----
  // The categories a tool currently belongs to, from its stored data.
  function categoriesOf(tool) {
    if (tool && Array.isArray(tool.categories) && tool.categories.length) {
      return tool.categories;
    }
    return tool && tool.category ? [tool.category] : [];
  }
  function inCategory(tool, catId) {
    return categoriesOf(tool).indexOf(catId) !== -1;
  }

  var api = {
    RULES: RULES,
    suggest: suggest,
    autoCategories: autoCategories,
    categoriesOf: categoriesOf,
    inCategory: inCategory
  };

  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  global.ApneCat = api;
})(typeof window !== 'undefined' ? window : this);
