<?php
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_id'])) {
    // Determine depth from root to redirect correctly
    $depth = substr_count(str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']), '/') - 1;
    $prefix = str_repeat('../', max(0, $depth - 1));
    header('Location: ' . $prefix . 'login.php');
    exit;
}
