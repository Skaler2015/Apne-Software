<?php
/**
 * Keyword Rank Tracker endpoint.
 * Receives a domain + keywords, queries real Google results via Serper.dev
 * (server-side, so the API key stays private), and returns each keyword's
 * organic position for that domain (top 100).
 *
 * POST JSON: { "domain": "...", "keywords": ["...", ...], "gl": "in", "hl": "en" }
 * Response : { "domain": "...", "results": [ { keyword, position, url, page, ... } ] }
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

// Light anti-abuse: only serve requests coming from our own site.
$ref = ($_SERVER['HTTP_REFERER'] ?? '') . ' ' . ($_SERVER['HTTP_ORIGIN'] ?? '');
if (stripos($ref, 'apnesoftware.com') === false && stripos($ref, 'localhost') === false) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$cfg = __DIR__ . '/config.php';
if (file_exists($cfg)) require_once $cfg;

$API_KEY = defined('SERPER_API_KEY') ? SERPER_API_KEY : '';
if ($API_KEY === '') {
    echo json_encode(['error' => 'Serper API key not configured. Add SERPER_API_KEY in backend/config.php — get a free key at serper.dev.']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true) ?: [];
$domain   = isset($body['domain']) ? trim($body['domain']) : '';
$keywords = (isset($body['keywords']) && is_array($body['keywords'])) ? $body['keywords'] : [];
$gl       = isset($body['gl']) ? preg_replace('/[^a-z]/', '', strtolower($body['gl'])) : 'in';
$hl       = isset($body['hl']) ? preg_replace('/[^a-z]/', '', strtolower($body['hl'])) : 'en';

if ($domain === '' || count($keywords) === 0) {
    echo json_encode(['error' => 'Provide a domain and at least one keyword.']);
    exit;
}

// Normalize the target domain: drop protocol, path and a leading www.
$domain = strtolower($domain);
$domain = preg_replace('#^https?://#', '', $domain);
$domain = preg_replace('#/.*$#', '', $domain);
$domain = preg_replace('#^www\.#', '', $domain);
$domain = trim($domain);

// Sanitize + cap keywords (protects the API quota).
$keywords = array_map('trim', $keywords);
$keywords = array_values(array_filter($keywords, function ($k) { return $k !== ''; }));
$keywords = array_slice($keywords, 0, 50);

$results = [];
foreach ($keywords as $kw) {
    $results[] = check_keyword($kw, $domain, $gl, $hl, $API_KEY);
}

echo json_encode(['domain' => $domain, 'results' => $results]);

/**
 * Query Serper.dev for one keyword and locate the target domain.
 */
function check_keyword($kw, $domain, $gl, $hl, $key)
{
    $payload = json_encode(['q' => $kw, 'num' => 100, 'gl' => $gl, 'hl' => $hl]);

    $ch = curl_init('https://google.serper.dev/search');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['X-API-KEY: ' . $key, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $errNo = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errNo)                    return ['keyword' => $kw, 'error' => 'Request failed: ' . $err];
    if ($code === 401 || $code === 403) return ['keyword' => $kw, 'error' => 'Invalid or unauthorized API key'];
    if ($code === 429)             return ['keyword' => $kw, 'error' => 'Rate limit / quota exceeded'];
    if ($code >= 400)              return ['keyword' => $kw, 'error' => 'API error (HTTP ' . $code . ')'];

    $data    = json_decode($resp, true);
    $organic = isset($data['organic']) && is_array($data['organic']) ? $data['organic'] : [];

    foreach ($organic as $item) {
        $link = isset($item['link']) ? $item['link'] : '';
        $host = strtolower(parse_url($link, PHP_URL_HOST) ?: '');
        $host = preg_replace('#^www\.#', '', $host);
        // Match the exact domain or any of its subdomains.
        $isMatch = ($host === $domain)
            || (strlen($host) > strlen($domain) && substr($host, -(strlen($domain) + 1)) === '.' . $domain);
        if ($isMatch) {
            $pos = isset($item['position']) ? intval($item['position']) : 0;
            return [
                'keyword'  => $kw,
                'position' => $pos ?: null,
                'url'      => $link,
                'page'     => $pos ? (int) ceil($pos / 10) : null,
                'title'    => isset($item['title']) ? $item['title'] : '',
            ];
        }
    }

    return ['keyword' => $kw, 'position' => null, 'url' => null, 'page' => null, 'note' => 'Not in top 100'];
}
