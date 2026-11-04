<?php
/**
 * Ocayur Doctor Portal - Session Check API
 * Returns active doctor/user session data
 */

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

    require_once __DIR__ . '/database.php';

    $sRole = strtolower(trim((string)($_SESSION['role'] ?? 'doctor')));
    if ($sRole !== 'doctor' && !empty($_SESSION['role'])) {
        echo json_encode([
            'success' => false,
            'logged_in' => false,
            'message' => 'Access denied. Doctor session required.'
        ]);
        exit();
    }

    // Auto-restore doctor session from remember-me cookie if session is expired
    if (empty($_SESSION['doctor_id']) && empty($_SESSION['user_id']) && !empty($_COOKIE['ocayur_doctor_remember'])) {
        $salt = 'ocayur_doctor_remember_salt_2026';
        $parts = explode(':', $_COOKIE['ocayur_doctor_remember'], 3);
        if (count($parts) === 3) {
            $cookieDocId = (int)$parts[0];
            $cookieExpiry = (int)$parts[1];
            $cookieSig = $parts[2];

            if ($cookieExpiry > time() && $cookieDocId > 0 && isset($connection1) && $connection1) {
                $remStmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `id` = ? LIMIT 1");
                mysqli_stmt_bind_param($remStmt, "i", $cookieDocId);
                mysqli_stmt_execute($remStmt);
                $remRes = mysqli_stmt_get_result($remStmt);
                $docFromCookie = mysqli_fetch_assoc($remRes);
                mysqli_stmt_close($remStmt);

                if ($docFromCookie && (empty($docFromCookie['status']) || strtolower($docFromCookie['status']) !== 'inactive')) {
                    $expectedSig = hash_hmac('sha256', $cookieDocId . '|' . $cookieExpiry . '|' . ($docFromCookie['phone_number'] ?? ''), $salt);
                    if (hash_equals($expectedSig, $cookieSig)) {
                        // Valid remember cookie - restore session
                        $_SESSION['doctor_id'] = $docFromCookie['id'];
                        $_SESSION['doctor_code'] = $docFromCookie['doctorid'] ?? ('DOC' . str_pad($docFromCookie['id'], 6, '0', STR_PAD_LEFT));
                        $_SESSION['doctor_profile'] = $docFromCookie;
                        $_SESSION['role'] = 'Doctor';
                    }
                }
            }
        }
    }

    if (!empty($_SESSION['user_id']) || !empty($_SESSION['doctor_id'])) {
        $doctor = $_SESSION['doctor_profile'] ?? null;
        
        // Refresh doctor profile from DB if doctor_id exists
        if (!empty($_SESSION['doctor_id']) && isset($connection1) && $connection1) {
            $stmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `id` = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $_SESSION['doctor_id']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($docRow = mysqli_fetch_assoc($res)) {
                $doctor = $docRow;
                $dobRaw = $doctor['date_of_birth'] ?? '';
                if (!empty($dobRaw) && $dobRaw !== '0000-00-00') {
                    $timestamp = strtotime($dobRaw);
                    $doctor['date_of_birth_iso'] = date('Y-m-d', $timestamp);
                    $doctor['date_of_birth_display'] = date('d/m/Y', $timestamp);
                }
                $_SESSION['doctor_profile'] = $doctor;
            }
            mysqli_stmt_close($stmt);
        }

        if ($doctor && isset($doctor['password'])) {
            unset($doctor['password']);
        }

        echo json_encode([
            'success' => true,
            'logged_in' => true,
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? '',
            'role' => $_SESSION['role'] ?? 'Doctor',
            'doctor' => $doctor
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'logged_in' => false,
            'message' => 'No active session found.'
        ]);
    }

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'logged_in' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error_type' => 'mysqli_sql_exception',
        'error_code' => $e->getCode(),
        'sqlstate' => method_exists($e, 'getSqlState') ? $e->getSqlState() : null,
        'error_details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'logged_in' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'error_type' => get_class($e),
        'error_code' => $e->getCode(),
        'sqlstate' => null,
        'error_details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>