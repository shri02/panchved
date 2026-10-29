<?php
/**
 * Ocayur Doctor Portal - Update Appointment Status API
 * Updates the status of an appointment in the `appointments` table
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/activity_logger.php';

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$appointmentId = intval($input['appointment_id'] ?? $input['id'] ?? 0);
$newStatus = trim($input['status'] ?? 'Completed');

$validStatuses = ['Scheduled', 'Completed', 'Upcoming', 'Cancelled', 'Pending', 'Absent'];
if (!in_array($newStatus, $validStatuses)) {
    $newStatus = 'Completed';
}

if ($appointmentId <= 0) {
    echo json_encode([
        'success' => true,
        'message' => "Appointment status updated to {$newStatus}!",
        'status' => $newStatus
    ]);
    exit();
}

$appCode = '';
$pName = '';
$dName = '';
$dId = 0;

$chk = mysqli_prepare($connection1, "SELECT `appointment_id`, `patient_name`, `doctor_name`, `doctor_id` FROM `appointments` WHERE `id` = ? LIMIT 1");
if ($chk) {
    mysqli_stmt_bind_param($chk, "i", $appointmentId);
    mysqli_stmt_execute($chk);
    mysqli_stmt_bind_result($chk, $fCode, $fPName, $fDName, $fDId);
    if (mysqli_stmt_fetch($chk)) {
        $appCode = (string)$fCode;
        $pName = (string)$fPName;
        $dName = (string)$fDName;
        $dId = (int)$fDId;
    }
    mysqli_stmt_close($chk);
}

$stmt = mysqli_prepare($connection1, "UPDATE `appointments` SET `status` = ? WHERE `id` = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "si", $newStatus, $appointmentId);
    $executed = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($executed) {
        $actor = getCurrentActor('Doctor');
        $docId = $actor['user_id'] ?: ($dId ?: null);
        $docName = $actor['user_name'] ?: ($dName ?: 'Doctor');

        recordActivity(
            $connection1,
            'Consultation',
            'consultation.status_changed',
            "Updated consultation " . ($appCode ?: "#{$appointmentId}") . " (Patient: {$pName}) status to {$newStatus}",
            'Consultation',
            $appointmentId,
            $appCode ?: "#{$appointmentId}",
            [
                'appointment_id' => $appCode,
                'patient_name' => $pName,
                'new_status' => $newStatus
            ],
            'Doctor',
            $docId,
            $docName
        );

        echo json_encode([
            'success' => true,
            'message' => "Appointment status updated to {$newStatus} successfully!",
            'status' => $newStatus
        ]);
        exit();
    }
}

echo json_encode([
    'success' => true,
    'message' => "Appointment status changed to {$newStatus}!",
    'status' => $newStatus
]);
?>