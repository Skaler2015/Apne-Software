<?php
require_once 'includes/config.php';
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
header('Location: login.php');
exit;
