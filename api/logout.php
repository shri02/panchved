<?php
/**
 * Ocayur Doctor Portal - Logout API
 * Destroys session and clears cookies
 */

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

$reqMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($reqMethod === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Clear persistent remember cookie
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    setcookie('ocayur_doctor_remember', '', time() - 42000, '/', '', $isSecure, true);

    @session_destroy();

    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully.'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'error_type' => get_class($e),
        'error_code' => $e->getCode(),
        'sqlstate' => null,
        'error_details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>