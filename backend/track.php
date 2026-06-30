<?php
// ============================================================
// POST /backend/track.php
// Body (JSON): { "type": "view" | "run", "tool_slug": "pdf-merge", "referrer": "...", "landing_page": "..." }
// Called automatically by assets/common.js on every tool page.
// Always responds quickly and never throws an error the visitor
// would see — if anything goes wrong, it just logs and exits.
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // static site + backend on same domain in production; kept open for local testing

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/geoip.php';
require_once __DIR__ . '/lib/useragent.php';

function respond($ok, $msg = '') {
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
    exit;
}

$pdo = get_db_connection();
if (!$pdo) {
    respond(false, 'db_unavailable');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(false, 'invalid_body');
}

$type = $input['type'] ?? '';
$toolSlug = trim($input['tool_slug'] ?? '');
$referrer = trim($input['referrer'] ?? '');
$landingPage = trim($input['landing_page'] ?? '');

if (!in_array($type, ['view', 'run'], true) || $toolSlug === '') {
    respond(false, 'missing_fields');
}

try {
    // Look up the tool (must already exist — run sync_tools.php after adding new tools)
    $stmt = $pdo->prepare("SELECT id FROM tools WHERE tool_slug = ?");
    $stmt->execute([$toolSlug]);
    $tool = $stmt->fetch();
    if (!$tool) {
        respond(false, 'unknown_tool');
    }
    $toolId = $tool['id'];

    $ip = get_visitor_ip();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $uaInfo = parse_user_agent($ua);
    $geo = get_geoip($pdo, $ip);
    $today = date('Y-m-d');

    if ($type === 'view') {
        $referrerSource = detect_referrer_source($referrer);
        $stmt = $pdo->prepare(
            "INSERT INTO tool_views
             (tool_id, ip_address, country, region, city, browser, os, device_type, referrer_source, referrer_url, landing_page, user_agent, view_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $toolId, $ip, $geo['country'], $geo['region'], $geo['city'],
            $uaInfo['browser'], $uaInfo['os'], $uaInfo['device_type'],
            $referrerSource, $referrer ?: null, $landingPage ?: null, $ua, $today
        ]);
        $pdo->prepare("UPDATE tools SET total_views = total_views + 1 WHERE id = ?")->execute([$toolId]);
        update_daily_stats($pdo, $today, 'view', $uaInfo['device_type']);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO tool_runs (tool_id, ip_address, country, region, city, browser, os, device_type, run_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $toolId, $ip, $geo['country'], $geo['region'], $geo['city'],
            $uaInfo['browser'], $uaInfo['os'], $uaInfo['device_type'], $today
        ]);
        $pdo->prepare("UPDATE tools SET total_runs = total_runs + 1 WHERE id = ?")->execute([$toolId]);
        update_daily_stats($pdo, $today, 'run', $uaInfo['device_type']);
    }

    respond(true);
} catch (Exception $e) {
    error_log('track.php error: ' . $e->getMessage());
    respond(false, 'server_error');
}

function update_daily_stats($pdo, $date, $type, $deviceType) {
    $viewInc = $type === 'view' ? 1 : 0;
    $runInc = $type === 'run' ? 1 : 0;
    $mobileInc = $deviceType === 'mobile' ? 1 : 0;
    $desktopInc = $deviceType === 'desktop' ? 1 : 0;
    $tabletInc = $deviceType === 'tablet' ? 1 : 0;

    $stmt = $pdo->prepare(
        "INSERT INTO daily_stats (stat_date, total_views, total_runs, mobile_views, desktop_views, tablet_views)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           total_views = total_views + VALUES(total_views),
           total_runs = total_runs + VALUES(total_runs),
           mobile_views = mobile_views + VALUES(mobile_views),
           desktop_views = desktop_views + VALUES(desktop_views),
           tablet_views = tablet_views + VALUES(tablet_views)"
    );
    $stmt->execute([$date, $viewInc, $runInc, $mobileInc, $desktopInc, $tabletInc]);
}
