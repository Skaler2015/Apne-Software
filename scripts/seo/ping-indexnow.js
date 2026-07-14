/* Notifies IndexNow-participating search engines (Bing, Yandex, Seznam,
   Naver — not Google, which doesn't support the protocol) that the site's
   URLs have changed, so they can re-crawl sooner instead of waiting for
   their next scheduled pass. No account/signup needed — IndexNow verifies
   ownership via the <key>.txt file already hosted at the site root.
   Best-effort: network failures here must never fail the deploy. */
const fs = require('fs');
const path = require('path');
const https = require('https');
const { ROOT, SITE_URL } = require('./lib');

function findKeyFile() {
  const file = fs.readdirSync(ROOT).find((f) => /^[0-9a-f]{32}\.txt$/i.test(f));
  return file ? { key: file.replace(/\.txt$/, ''), file } : null;
}

function urlsFromSitemap() {
  const xml = fs.readFileSync(path.join(ROOT, 'sitemap.xml'), 'utf8');
  const urls = [];
  const re = /<loc>([^<]+)<\/loc>/g;
  let m;
  while ((m = re.exec(xml))) urls.push(m[1]);
  return urls;
}

function ping(keyInfo, urlList) {
  const payload = JSON.stringify({
    host: 'apnesoftware.com',
    key: keyInfo.key,
    keyLocation: `${SITE_URL}/${keyInfo.file}`,
    urlList
  });
  return new Promise((resolve) => {
    const req = https.request(
      {
        hostname: 'api.indexnow.org',
        path: '/indexnow',
        method: 'POST',
        headers: { 'Content-Type': 'application/json; charset=utf-8', 'Content-Length': Buffer.byteLength(payload) },
        timeout: 15000
      },
      (res) => {
        resolve({ ok: res.statusCode >= 200 && res.statusCode < 300, status: res.statusCode });
      }
    );
    req.on('error', (e) => resolve({ ok: false, error: e.message }));
    req.on('timeout', () => { req.destroy(); resolve({ ok: false, error: 'timeout' }); });
    req.write(payload);
    req.end();
  });
}

if (require.main === module) {
  const keyInfo = findKeyFile();
  if (!keyInfo) {
    console.log('No IndexNow key file found at repo root — skipping ping.');
    process.exit(0);
  }
  const urls = urlsFromSitemap();
  ping(keyInfo, urls).then((result) => {
    if (result.ok) {
      console.log(`IndexNow: notified ${urls.length} URL(s) — HTTP ${result.status}.`);
    } else {
      console.log(`IndexNow: ping did not succeed (${result.error || 'HTTP ' + result.status}) — non-blocking, continuing.`);
    }
    process.exit(0); /* best-effort: never fail the deploy on this step */
  });
}

module.exports = { findKeyFile, urlsFromSitemap, ping };
