<?php
/**
 * Ocayur Doctor Portal - Update Consultation Meet Link API
 * Updates or sets the Google Meet link for a consultation in the `appointments` table
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

function sendMeetResponse($success, $message, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge([
        'success' => (bool)$success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data), JSON_UNESCAPED_UNICODE);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sendMeetResponse(false, 'Only POST requests are allowed.', [], 405);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$targetId = intval($input['id'] ?? ($input['appointment_db_id'] ?? 0));
$appointmentCode = trim((string)($input['appointment_id'] ?? ($input['appointment_code'] ?? '')));
$rawMeetLink = trim((string)($input['meet_link'] ?? ($input['meeting_link'] ?? ($input['link'] ?? ''))));

if ($targetId <= 0 && empty($appointmentCode)) {
    sendMeetResponse(false, 'Missing appointment ID.', [], 400);
}

// Clean and normalize link format
$cleanMeetLink = '';
if (!empty($rawMeetLink)) {
    $cleanMeetLink = $rawMeetLink;
    if (!preg_match('/^https?:\/\//i', $cleanMeetLink)) {
        $cleanMeetLink = 'https://' . $cleanMeetLink;
    }
}

// Ensure database connection
if (!$connection1) {
    sendMeetResponse(false, 'Database connection unavailable.', [], 500);
}

// Resolve appointment details
$appDbId = 0;
$appCode = '';
$patientName = '';
$doctorName = '';
$doctorId = 0;

$chkQuery = "SELECT id, appointment_id, patient_name, doctor_name, doctor_id FROM appointments WHERE " . ($targetId > 0 ? "id = ?" : "appointment_id = ?") . " LIMIT 1";
if ($chkStmt = mysqli_prepare($connection1, $chkQuery)) {
    if ($targetId > 0) {
        mysqli_stmt_bind_param($chkStmt, "i", $targetId);
    } else {
        mysqli_stmt_bind_param($chkStmt, "s", $appointmentCode);
    }
    mysqli_stmt_execute($chkStmt);
    $res = mysqli_stmt_get_result($chkStmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $appDbId = (int)$row['id'];
        $appCode = (string)$row['appointment_id'];
        $patientName = (string)$row['patient_name'];
        $doctorName = (string)$row['doctor_name'];
        $doctorId = (int)$row['doctor_id'];
    }
    mysqli_stmt_close($chkStmt);
}

if ($appDbId <= 0 && empty($appCode)) {
    sendMeetResponse(false, 'Appointment not found in database.', [], 404);
}

// Ensure meet_link column exists
$colCheck = @mysqli_query($connection1, "SHOW COLUMNS FROM appointments LIKE 'meet_link'");
if ($colCheck && mysqli_num_rows($colCheck) === 0) {
    @mysqli_query($connection1, "ALTER TABLE appointments ADD COLUMN `meet_link` VARCHAR(255) NULL DEFAULT NULL AFTER `agenda`");
}

// Update meet link
$upQuery = "UPDATE appointments SET meet_link = ?, updated_at = NOW() WHERE id = ?";
$upStmt = mysqli_prepare($connection1, $upQuery);
if (!$upStmt) {
    sendMeetResponse(false, 'Failed to prepare update query: ' . mysqli_error($connection1), [], 500);
}

mysqli_stmt_bind_param($upStmt, "si", $cleanMeetLink, $appDbId);
$executed = mysqli_stmt_execute($upStmt);
$affected = mysqli_stmt_affected_rows($upStmt);
mysqli_stmt_close($upStmt);

if (!$executed) {
    sendMeetResponse(false, 'Database update failed: ' . mysqli_error($connection1), [], 500);
}

// Record activity
$actor = getCurrentActor('Doctor');
$docId = $actor['user_id'] ?: ($doctorId ?: null);
$docName = $actor['user_name'] ?: ($doctorName ?: 'Doctor');

$actionDesc = !empty($cleanMeetLink)
    ? "Doctor {$docName} updated Google Meet link for consultation " . ($appCode ?: "#{$appDbId}") . " (Patient: {$patientName})"
    : "Doctor {$docName} cleared Google Meet link for consultation " . ($appCode ?: "#{$appDbId}");

recordActivity(
    $connection1,
    'Consultation',
    'consultation.meet_link_updated',
    $actionDesc,
    'Consultation',
    $appDbId,
    $appCode ?: "#{$appDbId}",
    [
        'appointment_id' => $appCode,
        'patient_name' => $patientName,
        'meet_link' => $cleanMeetLink
    ],
    'Doctor',
    $docId,
    $docName
);

sendMeetResponse(true, !empty($cleanMeetLink) ? 'Meeting link saved successfully!' : 'Meeting link cleared.', [
    'appointment_id' => $appCode,
    'id' => $appDbId,
    'meet_link' => $cleanMeetLink,
    'has_meet_link' => !empty($cleanMeetLink)
]);

