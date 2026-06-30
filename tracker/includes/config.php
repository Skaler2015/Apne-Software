<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u246829578_websitetracker');
define('DB_USER', 'u246829578_Trackeruser');
define('DB_PASS', 'Kaler@062026');
define('APP_NAME', 'Website Change Tracker Pro');

// Set Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) session_start();
