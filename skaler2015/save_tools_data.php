<?php
require_once __DIR__ . '/includes/auth.php'; // must be logged in
header('Content-Type: application/json');

function respond($ok, $msg = '') {
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['categories']) || !isset($input['tools']) ||
    !is_array($input['categories']) || !is_array($input['tools'])) {
    http_response_code(400);
    respond(false, 'Invalid data shape');
}

// Sanity limits so a corrupted request can't silently wipe the file
if (count($input['tools']) === 0 || count($input['categories']) === 0) {
    http_response_code(400);
    respond(false, 'Refusing to save empty categories/tools list — looks like a mistake');
}
if (count($input['tools']) > 2000 || count($input['categories']) > 200) {
    http_response_code(400);
    respond(false, 'Data too large — looks like a mistake');
}

$jsonPath = __DIR__ . '/../assets/tools-data.json';
$json = json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    http_response_code(500);
    respond(false, 'Could not encode JSON');
}

$fp = @fopen($jsonPath, 'c+');
if (!$fp || !flock($fp, LOCK_EX)) {
    http_response_code(500);
    respond(false, 'Could not open tools-data.json for writing — check file permissions');
}
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, $json);
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

respond(true, 'Saved');
