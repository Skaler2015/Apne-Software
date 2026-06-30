<?php
// Include this at the very top of every protected page in /skaler2015/
session_start();
require_once __DIR__ . '/../../backend/config.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
