<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

function getSetting($pdo, $key, $default='') {
    try {
        $s = $pdo->prepare("SELECT setting_value FROM tracker_settings WHERE setting_key=?");
        $s->execute([$key]);
        $val = $s->fetchColumn();
        return $val !== false ? $val : $default;
    } catch(Exception $e) { return $default; }
}

function sendTelegram($token, $chatId, $message) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $ch  = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'chat_id'    => $chatId,
            'text'       => $message,
            'parse_mode' => 'HTML',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

$token  = getSetting($pdo, 'telegram_bot_token');
$chatId = getSetting($pdo, 'telegram_chat_id');
$enabled = getSetting($pdo, 'telegram_enabled', '0');

if (!$token || !$chatId) {
    echo json_encode(['ok'=>false,'error'=>'Token or Chat ID not configured']);
    exit;
}

// Test message
if (isset($_GET['test'])) {
    $result = sendTelegram($token, $chatId, "🔔 <b>ChangeTracker Test</b>\n\nTelegram notifications are working!\n\n✅ Bot connected successfully.");
    echo json_encode(['ok'=>$result['ok']??false,'error'=>$result['description']??'']);
    exit;
}

// Send notification for a specific change
$cid = (int)($_GET['change_id'] ?? 0);
if (!$cid) { echo json_encode(['ok'=>false,'error'=>'No change_id']); exit; }

$row = $pdo->prepare("
    SELECT c.*, p.page_url, w.website_name
    FROM changes c
    LEFT JOIN pages p ON c.page_id=p.id
    LEFT JOIN websites w ON c.website_id=w.id
    WHERE c.id=?
");
$row->execute([$cid]);
$row = $row->fetch();

if (!$row) { echo json_encode(['ok'=>false,'error'=>'Change not found']); exit; }

$typeEmoji = [
    'CONTENT_CHANGED' => '📝',
    'TITLE_CHANGED'   => '📌',
    'META_CHANGED'    => '🔍',
    'H1_CHANGED'      => '📋',
];

$emoji   = $typeEmoji[$row['change_type']] ?? '🔔';
$site    = htmlspecialchars($row['website_name'] ?? '');
$url     = $row['page_url'] ?? '';
$note    = $row['note'] ?? '';
$time    = date('d M Y, h:i A', strtotime($row['detected_at']));

$msg = "{$emoji} <b>Page Change Detected!</b>\n\n";
$msg .= "🌐 <b>Site:</b> {$site}\n";
$msg .= "🔗 <b>Page:</b> {$url}\n";
$msg .= "⏰ <b>Time:</b> {$time} IST\n";
if ($note) $msg .= "\n💡 <b>Summary:</b> {$note}\n";
$msg .= "\n<a href='https://apnesoftware.com/tracker/admin/changes.php'>View in Tracker →</a>";

$result = sendTelegram($token, $chatId, $msg);
echo json_encode(['ok'=>$result['ok']??false,'error'=>$result['description']??'']);
