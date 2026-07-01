<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u246829578_Subhashkaler');
define('DB_USER', 'u246829578_Skaler2015');
define('DB_PASS', 'Apnesoftware@2507');
define('ADMIN_PASSWORD', 'Kaler@062026');
define('SITE_URL', 'https://apnesoftware.com');

// Keyword Rank Tracker — real Google results via Serper.dev.
// Get a free API key at https://serper.dev (generous free tier),
// then paste it below between the quotes.
define('SERPER_API_KEY', '');

function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            $pdo = false;
        }
    }
    return $pdo;
}
