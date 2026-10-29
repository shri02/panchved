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
$rawAppointmentId = trim(strval($input['appointment_id'] ?? ''));
$appointmentId = intval($rawAppointmentId);
$patientName = trim($input['patient_name'] ?? '');
$diagnosis = trim($input['diagnosis_name'] ?? $input['diagnosis'] ?? '');
$diagnosisDuration = trim($input['diagnosis_duration'] ?? $input['duration'] ?? '');
$symptoms = $input['symptoms'] ?? [];
$medications = $input['medications'] ?? [];
$examinationFindings = trim($input['examination_findings'] ?? '');
$notes = trim($input['notes'] ?? '');
$medicalHistory = trim($input['medical_history'] ?? '');

if (empty($diagnosis)) {
    echo json_encode([
        'success' => false,
        'message' => 'Diagnosis is required to issue a prescription.'
    ]);
    exit();
}

// Resolve exact appointment record by numeric id or alphanumeric code
$targetAppt = null;
if ($appointmentId > 0) {
    $aStmt = mysqli_prepare($connection1, "SELECT `id`, `patient_id`, `doctor_id`, `doctor_name` FROM `appointments` WHERE `id` = ? LIMIT 1");
    if ($aStmt) {
        mysqli_stmt_bind_param($aStmt, "i", $appointmentId);
        mysqli_stmt_execute($aStmt);
        $res = mysqli_stmt_get_result($aStmt);
        $targetAppt = mysqli_fetch_assoc($res);
        mysqli_stmt_close($aStmt);
    }
}
if (!$targetAppt && !empty($rawAppointmentId)) {
    $aStmt = mysqli_prepare($connection1, "SELECT `id`, `patient_id`, `doctor_id`, `doctor_name` FROM `appointments` WHERE `appointment_id` = ? LIMIT 1");
    if ($aStmt) {
        mysqli_stmt_bind_param($aStmt, "s", $rawAppointmentId);
        mysqli_stmt_execute($aStmt);
        $res = mysqli_stmt_get_result($aStmt);
        $targetAppt = mysqli_fetch_assoc($res);
        mysqli_stmt_close($aStmt);
    }
}

if ($targetAppt) {
    $appointmentId = intval($targetAppt['id']);
    if ($patientId <= 0 && !empty($targetAppt['patient_id'])) {
        $patientId = intval($targetAppt['patient_id']);
    }
}

// Doctor Info - dynamically resolve from session, appointment, or doctors table
$doctorId = $_SESSION['doctor_id'] ?? $_SESSION['user_id'] ?? ($targetAppt['doctor_id'] ?? 1);
$doctorName = $_SESSION['doctor_name'] ?? $_SESSION['full_name'] ?? ($targetAppt['doctor_name'] ?? 'Doctor');
$doctorArea = $_SESSION['doctor_area'] ?? 'Ayurvedic Medicine';
$doctorPhone = '+91 7896543210';
$doctorEmail = 'care@ocayur.com';

if ($doctorId > 0) {
    $dStmt = mysqli_prepare($connection1, "SELECT `full_name`, `expertise`, `area`, `phone_number`, `email` FROM `doctors` WHERE `id` = ? LIMIT 1");
    if ($dStmt) {
        mysqli_stmt_bind_param($dStmt, "i", $doctorId);
        mysqli_stmt_execute($dStmt);
        $dRes = mysqli_stmt_get_result($dStmt);
        if ($dRow = mysqli_fetch_assoc($dRes)) {
            if (!empty($dRow['full_name']) && ($doctorName === 'Doctor' || empty($doctorName))) {
                $doctorName = $dRow['full_name'];
            }
            if (!empty($dRow['area'])) {
                $doctorArea = $dRow['area'];
            } elseif (!empty($dRow['expertise'])) {
                $doctorArea = $dRow['expertise'];
            }
            if (!empty($dRow['phone_number'])) {
                $doctorPhone = $dRow['phone_number'];
            }
            if (!empty($dRow['email'])) {
                $doctorEmail = $dRow['email'];
            }
        }
        mysqli_stmt_close($dStmt);
    }
}

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

// Sanitize medications list: only keep rows where a medication name is entered
$cleanMedications = [];
if (is_array($medications)) {
    foreach ($medications as $med) {
        $mName = trim($med['medication'] ?? $med['name'] ?? '');
        if (!empty($mName)) {
            $cleanMedications[] = [
                'medication' => $mName,
                'dose' => trim($med['dose'] ?? ''),
                'frequency' => trim($med['frequency'] ?? ''),
                'duration' => trim($med['duration'] ?? ''),
                'remarks' => trim($med['remarks'] ?? '')
            ];
        }
    }
}
$medications = $cleanMedications;

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
    'phone' => $doctorPhone,
    'support_email' => $doctorEmail
];

$prescriptionJson = json_encode($prescriptionData, JSON_UNESCAPED_UNICODE);

// Build human-readable text prescription summary
$prescriptionTextLines = [];
$prescriptionTextLines[] = "DIAGNOSIS: {$diagnosis}" . (!empty($diagnosisDuration) ? " ({$diagnosisDuration})" : "");
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

// 1. Insert into `prescriptions` table in MySQL
$symptomsStr = is_array($symptoms) ? json_encode($symptoms, JSON_UNESCAPED_UNICODE) : strval($symptoms);
$medicationsStr = json_encode($medications, JSON_UNESCAPED_UNICODE);
$rxCode = 'RX-' . date('ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
$today = date('Y-m-d');
$timeNow = date('h:i A');

$rxStmt = mysqli_prepare($connection1, "INSERT INTO `prescriptions` 
    (`prescription_code`, `patient_id`, `patient_name`, `doctor_id`, `doctor_name`, `doctor_specialty`, `doctor_phone`, `doctor_email`, `appointment_id`, `prescription_date`, `prescription_time`, `medical_history`, `symptoms`, `diagnosis`, `diagnosis_duration`, `medications`, `examination_findings`, `notes`) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if ($rxStmt) {
    mysqli_stmt_bind_param($rxStmt, "sisissssissssssss", 
        $rxCode, $patientId, $patientName, $doctorId, $doctorName, $doctorArea,
        $doctorPhone, $doctorEmail,
        $appointmentId, $today, $timeNow, $medicalHistory, $symptomsStr, $diagnosis, $diagnosisDuration, $medicationsStr, $examinationFindings, $notes
    );
    mysqli_stmt_execute($rxStmt);
    mysqli_stmt_close($rxStmt);
}

// 2. Update appointment if appointment_id given, or link latest patient appointment
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