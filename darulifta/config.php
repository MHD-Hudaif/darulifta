<?php
ob_start();

// Session save path
$session_save_path = sys_get_temp_dir();
if (!is_writable($session_save_path)) {
    error_log("config.php: Session save path ($session_save_path) is not writable");
    die('Session configuration error.');
}
ini_set('session.save_path', $session_save_path);

// Session configuration
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_secure' => false, // HTTP on localhost
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict'
        ]);
    }
} catch (Exception $e) {
    error_log("config.php: Session start error: " . $e->getMessage());
    die('Session configuration error.');
}

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'darulifta_local');
define('DB_USER', 'root');
define('DB_PASS', '');

// Database connection
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("config.php: Database connection error: " . $e->getMessage());
    die('Database connection failed.');
}
?>