<?php
/**
 * Ocayur Doctor Portal - Session Check API
 * Returns active doctor/user session data
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sRole = strtolower(trim((string)($_SESSION['role'] ?? 'doctor')));
if ($sRole !== 'doctor' && !empty($_SESSION['role'])) {
    echo json_encode([
        'success' => false,
        'logged_in' => false,
        'message' => 'Access denied. Doctor session required.'
    ]);
    exit();
}

if (!empty($_SESSION['user_id']) || !empty($_SESSION['doctor_id'])) {
    $doctor = $_SESSION['doctor_profile'] ?? null;
    
    // Refresh doctor profile from DB if doctor_id exists
    if (!empty($_SESSION['doctor_id'])) {
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
?>
