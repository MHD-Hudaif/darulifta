<?php
ob_start();
error_log("logout.php: Script started");

try {
    require_once 'config.php';
    error_log("logout.php: config.php included successfully");
} catch (Exception $e) {
    error_log("logout.php: Failed to include config.php: " . $e->getMessage());
    die("Configuration error. Please contact support.");
}

// Log current session state
error_log("logout.php: Session before clear: " . print_r($_SESSION, true));

// Clear session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
error_log("logout.php: Session cleared");

// Redirect to home.php
header('Location: home.php');
exit;
?>