<?php
/**
 * Ocayur Doctor Portal - Save Prescription API
 * Saves complete prescription, medical history, diagnosis, medication list, and notes
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

$patientId = intval($input['patient_id'] ?? 0);
$appointmentId = intval($input['appointment_id'] ?? 0);
$patientName = trim($input['patient_name'] ?? '');
$diagnosis = trim($input['diagnosis_name'] ?? $input['diagnosis'] ?? 'Migraine');
$diagnosisDuration = trim($input['diagnosis_duration'] ?? $input['duration'] ?? '');
$symptoms = $input['symptoms'] ?? [];
$medications = $input['medications'] ?? [];
$examinationFindings = trim($input['examination_findings'] ?? '');
$notes = trim($input['notes'] ?? '');
$medicalHistory = trim($input['medical_history'] ?? '');

// Doctor Info
$doctorId = $_SESSION['doctor_id'] ?? $_SESSION['user_id'] ?? 1;
$doctorName = $_SESSION['doctor_name'] ?? $_SESSION['full_name'] ?? 'Doctor';
$doctorArea = $_SESSION['doctor_area'] ?? 'Ayurvedic Medicine';

// If patient info not provided, fetch from DB
if ($patientId > 0 && empty($patientName)) {
    $pStmt = mysqli_prepare($connection1, "SELECT `full_name` FROM `patients` WHERE `id` = ? LIMIT 1");
    if ($pStmt) {
        mysqli_stmt_bind_param($pStmt, "i", $patientId);
        mysqli_stmt_execute($pStmt);
        $pRes = mysqli_stmt_get_result($pStmt);
        if ($pRow = mysqli_fetch_assoc($pRes)) {
            $patientName = $pRow['full_name'];
        }
        mysqli_stmt_close($pStmt);
    }
}

if (empty($patientName)) {
    $patientName = 'Patient';
}

// Format structured prescription object
$prescriptionData = [
    'date' => date('d/m/Y'),
    'time' => date('h:i A'),
    'patient_id' => $patientId,
    'patient_name' => $patientName,
    'doctor_name' => $doctorName,
    'doctor_specialty' => $doctorArea,
    'medical_history' => $medicalHistory,
    'symptoms' => $symptoms,
    'diagnosis' => $diagnosis,
    'diagnosis_duration' => $diagnosisDuration,
    'medications' => $medications,
    'examination_findings' => $examinationFindings,
    'notes' => $notes,
    'phone' => '+91 7896543210',
    'support_email' => 'ocayursupport@gmail.com'
];

$prescriptionJson = json_encode($prescriptionData, JSON_UNESCAPED_UNICODE);

// Build human-readable text prescription summary
$prescriptionTextLines = [];
$prescriptionTextLines[] = "DIAGNOSIS: {$diagnosis} ({$diagnosisDuration})";
if (!empty($medications)) {
    $medStrings = [];
    foreach ($medications as $med) {
        $mName = $med['medication'] ?? '';
        $mDose = $med['dose'] ?? '';
        $mFreq = $med['frequency'] ?? '';
        $mDur = $med['duration'] ?? '';
        $mRem = $med['remarks'] ?? '';
        if (!empty($mName)) {
            $medStrings[] = "{$mName} | {$mDose} | {$mFreq} | {$mDur}" . (!empty($mRem) ? " ({$mRem})" : "");
        }
    }
    if (!empty($medStrings)) {
        $prescriptionTextLines[] = "MEDICATIONS: " . implode('; ', $medStrings);
    }
}
if (!empty($examinationFindings)) {
    $prescriptionTextLines[] = "FINDINGS: {$examinationFindings}";
}
if (!empty($notes)) {
    $prescriptionTextLines[] = "NOTES: {$notes}";
}

$prescriptionText = implode("\n", $prescriptionTextLines);

// 1. Insert or Update `prescriptions` table in MySQL
$symptomsStr = is_array($symptoms) ? json_encode($symptoms, JSON_UNESCAPED_UNICODE) : strval($symptoms);
$medicationsStr = json_encode($medications, JSON_UNESCAPED_UNICODE);
$rxCode = 'RX-' . date('ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
$today = date('Y-m-d');
$timeNow = date('h:i A');

$rxStmt = mysqli_prepare($connection1, "INSERT INTO `prescriptions` 
    (`prescription_code`, `patient_id`, `patient_name`, `doctor_id`, `doctor_name`, `doctor_specialty`, `doctor_phone`, `doctor_email`, `appointment_id`, `prescription_date`, `prescription_time`, `medical_history`, `symptoms`, `diagnosis`, `diagnosis_duration`, `medications`, `examination_findings`, `notes`) 
    VALUES (?, ?, ?, ?, ?, ?, '+91 7896543210', 'ocayursupport@gmail.com', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if ($rxStmt) {
    mysqli_stmt_bind_param($rxStmt, "sisisssissssssss", 
        $rxCode, $patientId, $patientName, $doctorId, $doctorName, $doctorArea,
        $appointmentId, $today, $timeNow, $medicalHistory, $symptomsStr, $diagnosis, $diagnosisDuration, $medicationsStr, $examinationFindings, $notes
    );
    mysqli_stmt_execute($rxStmt);
    mysqli_stmt_close($rxStmt);
}

// 2. Update appointment if appointment_id given, or insert a new record
$saved = false;
if ($appointmentId > 0) {
    $stmt = mysqli_prepare($connection1, "UPDATE `appointments` SET `prescription` = ?, `notes` = ? WHERE `id` = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssi", $prescriptionJson, $notes, $appointmentId);
        $saved = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
} elseif ($patientId > 0) {
    // Find latest appointment for patient
    $findStmt = mysqli_prepare($connection1, "SELECT `id` FROM `appointments` WHERE `patient_id` = ? ORDER BY `id` DESC LIMIT 1");
    if ($findStmt) {
        mysqli_stmt_bind_param($findStmt, "i", $patientId);
        mysqli_stmt_execute($findStmt);
        $fRes = mysqli_stmt_get_result($findStmt);
        if ($row = mysqli_fetch_assoc($fRes)) {
            $targetApptId = intval($row['id']);
            $uStmt = mysqli_prepare($connection1, "UPDATE `appointments` SET `prescription` = ?, `notes` = ? WHERE `id` = ?");
            if ($uStmt) {
                mysqli_stmt_bind_param($uStmt, "ssi", $prescriptionJson, $notes, $targetApptId);
                $saved = mysqli_stmt_execute($uStmt);
                mysqli_stmt_close($uStmt);
            }
        }
        mysqli_stmt_close($findStmt);
    }
}

// If no appointment exists, create one with prescription
if (!$saved && $patientId > 0) {
    $code = 'APT-' . date('ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
    $insStmt = mysqli_prepare($connection1, "INSERT INTO `appointments` (`appointment_id`, `patient_id`, `patient_name`, `doctor_id`, `doctor_name`, `appointment_date`, `appointment_time`, `prescription`, `status`, `notes`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Completed', ?)");
    if ($insStmt) {
        mysqli_stmt_bind_param($insStmt, "sisisssss", $code, $patientId, $patientName, $doctorId, $doctorName, $today, $timeNow, $prescriptionJson, $notes);
        $saved = mysqli_stmt_execute($insStmt);
        mysqli_stmt_close($insStmt);
    }
}

$actor = getCurrentActor('Doctor');
$docId = $actor['user_id'] ?: ($doctorId ?: null);
$docName = $actor['user_name'] ?: ($doctorName ?: 'Doctor');

recordActivity(
    $connection1,
    'Prescription',
    'prescription.saved',
    "Dr. {$docName} saved prescription {$rxCode} for {$patientName} (Diagnosis: {$diagnosis})",
    'Prescription',
    $appointmentId ?: $patientId,
    $rxCode,
    [
        'prescription_code' => $rxCode,
        'patient_id' => $patientId,
        'patient_name' => $patientName,
        'doctor_id' => $docId,
        'doctor_name' => $docName,
        'diagnosis' => $diagnosis,
        'appointment_id' => $appointmentId
    ],
    'Doctor',
    $docId,
    $docName
);

echo json_encode([
    'success' => true,
    'message' => 'Prescription added and saved successfully!',
    'prescription' => $prescriptionData
]);
?>
