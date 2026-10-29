<?php
/**
 * Ocayur Doctor Portal - Update Patient Status API
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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

$patientId = $input['patient_id'] ?? $input['id'] ?? null;
$newStatus = trim($input['status'] ?? 'Ongoing');

$validStatuses = ['Ongoing', 'Completed', 'Active', 'Inactive'];
if (!in_array($newStatus, $validStatuses)) {
    $newStatus = 'Ongoing';
}

if (empty($patientId)) {
    echo json_encode(['success' => false, 'message' => 'Patient ID is required.']);
    exit();
}

$pName = '';
$pCode = is_string($patientId) ? $patientId : '';
$pIdNum = is_numeric($patientId) ? intval($patientId) : 0;

$chk = mysqli_prepare($connection1, "SELECT `id`, `patient_id`, `full_name` FROM `patients` WHERE `id` = ? OR `patient_id` = ? LIMIT 1");
if ($chk) {
    $pIdLookup = intval($patientId);
    $pCodeLookup = (string)$patientId;
    mysqli_stmt_bind_param($chk, "is", $pIdLookup, $pCodeLookup);
    mysqli_stmt_execute($chk);
    mysqli_stmt_bind_result($chk, $fId, $fCode, $fName);
    if (mysqli_stmt_fetch($chk)) {
        $pIdNum = (int)$fId;
        $pCode = (string)$fCode;
        $pName = (string)$fName;
    }
    mysqli_stmt_close($chk);
}

// Update in `patients` table
$stmt = mysqli_prepare($connection1, "UPDATE `patients` SET `status` = ? WHERE `id` = ? OR `patient_id` = ?");
mysqli_stmt_bind_param($stmt, "sis", $newStatus, $patientId, $patientId);
$executed = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($executed) {
    $actor = getCurrentActor('Doctor');
    $displayName = $pName ?: ($pCode ?: "Patient #{$pIdNum}");

    recordActivity(
        $connection1,
        'Patient',
        'patient.status_changed',
        "Updated patient {$displayName} status to {$newStatus}",
        'Patient',
        $pIdNum,
        $displayName,
        [
            'patient_id' => $pCode,
            'new_status' => $newStatus
        ],
        'Doctor',
        $actor['user_id'],
        $actor['user_name']
    );

    echo json_encode([
        'success' => true,
        'message' => "Patient status updated to {$newStatus} successfully!",
        'status' => $newStatus
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update patient status: ' . mysqli_error($connection1)
    ]);
}
?>