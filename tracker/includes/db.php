<?php
require_once __DIR__ . '/config.php';
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Exception $e) {
    die("<div style='padding:20px;background:#fee;color:#c00;font-family:sans-serif'>
        <b>Database Error:</b> " . htmlspecialchars($e->getMessage()) . "
    </div>");
}
