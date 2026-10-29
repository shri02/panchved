<?php
/**
 * Ocayur Doctor Portal - Get Prescription API
 * Fetches structured prescription details dynamically from MySQL database
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

$patientId = intval($_GET['patient_id'] ?? $_GET['id'] ?? 0);
$appointmentId = intval($_GET['appointment_id'] ?? 0);
$prescriptionId = intval($_GET['prescription_id'] ?? 0);

// If only appointment_id provided, resolve patientId
if ($patientId <= 0 && $appointmentId > 0) {
    $apStmt = mysqli_prepare($connection1, "SELECT `patient_id` FROM `appointments` WHERE `id` = ? LIMIT 1");
    if ($apStmt) {
        mysqli_stmt_bind_param($apStmt, "i", $appointmentId);
        mysqli_stmt_execute($apStmt);
        $apRes = mysqli_stmt_get_result($apStmt);
        if ($apRow = mysqli_fetch_assoc($apRes)) {
            $patientId = intval($apRow['patient_id']);
        }
        mysqli_stmt_close($apStmt);
    }
}

// 1. Fetch Patient Info from `patients` table to ensure 100% dynamic patient data
$patientInfo = [
    'id' => $patientId,
    'full_name' => 'Patient',
    'gender' => '--',
    'age' => '--'
];

if ($patientId > 0) {
    $pStmt = mysqli_prepare($connection1, "SELECT `id`, `full_name`, `gender`, `age`, `dob` FROM `patients` WHERE `id` = ? LIMIT 1");
    if ($pStmt) {
        mysqli_stmt_bind_param($pStmt, "i", $patientId);
        mysqli_stmt_execute($pStmt);
        $pRes = mysqli_stmt_get_result($pStmt);
        if ($pRow = mysqli_fetch_assoc($pRes)) {
            $patientInfo['id'] = intval($pRow['id']);
            $patientInfo['full_name'] = $pRow['full_name'] ?: 'Patient';
            $patientInfo['gender'] = $pRow['gender'] ?: '--';
            $pAge = intval($pRow['age'] ?: 0);
            if ($pAge <= 0 && !empty($pRow['dob'])) {
                $bDate = new DateTime($pRow['dob']);
                $today = new DateTime();
                $pAge = $today->diff($bDate)->y;
            }
            $patientInfo['age'] = $pAge > 0 ? $pAge : '--';
        }
        mysqli_stmt_close($pStmt);
    }
}

// 2. Fetch all prescription records list for this patient to support consultation switching
$allPrescriptions = [];
if ($patientId > 0) {
    $listStmt = mysqli_prepare($connection1, "SELECT `id`, `prescription_code`, `prescription_date`, `prescription_time`, `diagnosis`, `diagnosis_duration`, `doctor_name` FROM `prescriptions` WHERE `patient_id` = ? ORDER BY `id` DESC");
    if ($listStmt) {
        mysqli_stmt_bind_param($listStmt, "i", $patientId);
        mysqli_stmt_execute($listStmt);
        $lRes = mysqli_stmt_get_result($listStmt);
        while ($lRow = mysqli_fetch_assoc($lRes)) {
            $formattedDate = $lRow['prescription_date'];
            if (!empty($lRow['prescription_date']) && $lRow['prescription_date'] !== '0000-00-00') {
                $ts = strtotime($lRow['prescription_date']);
                if ($ts && $ts > 0) {
                    $formattedDate = date('d/m/Y', $ts);
                }
            }
            $allPrescriptions[] = [
                'id' => intval($lRow['id']),
                'prescription_code' => $lRow['prescription_code'] ?: ('RX-' . str_pad($lRow['id'], 3, '0', STR_PAD_LEFT)),
                'date' => $formattedDate,
                'time' => $lRow['prescription_time'] ?: '',
                'diagnosis' => $lRow['diagnosis'] ?: 'General Consultation',
                'diagnosis_duration' => $lRow['diagnosis_duration'] ?: '',
                'doctor_name' => $lRow['doctor_name'] ?: 'Doctor'
            ];
        }
        mysqli_stmt_close($listStmt);
    }
}

// 3. Query specific `prescriptions` record from MySQL
$rxRecord = null;

if ($prescriptionId > 0) {
    $rxStmt = mysqli_prepare($connection1, "SELECT * FROM `prescriptions` WHERE `id` = ? LIMIT 1");
    if ($rxStmt) {
        mysqli_stmt_bind_param($rxStmt, "i", $prescriptionId);
        mysqli_stmt_execute($rxStmt);
        $rxRes = mysqli_stmt_get_result($rxStmt);
        $rxRecord = mysqli_fetch_assoc($rxRes);
        mysqli_stmt_close($rxStmt);
    }
} elseif ($appointmentId > 0) {
    $rxStmt = mysqli_prepare($connection1, "SELECT * FROM `prescriptions` WHERE `appointment_id` = ? ORDER BY `id` DESC LIMIT 1");
    if ($rxStmt) {
        mysqli_stmt_bind_param($rxStmt, "i", $appointmentId);
        mysqli_stmt_execute($rxStmt);
        $rxRes = mysqli_stmt_get_result($rxStmt);
        $rxRecord = mysqli_fetch_assoc($rxRes);
        mysqli_stmt_close($rxStmt);
    }
}

if (!$rxRecord && $patientId > 0) {
    $rxStmt = mysqli_prepare($connection1, "SELECT * FROM `prescriptions` WHERE `patient_id` = ? ORDER BY `id` DESC LIMIT 1");
    if ($rxStmt) {
        mysqli_stmt_bind_param($rxStmt, "i", $patientId);
        mysqli_stmt_execute($rxStmt);
        $rxRes = mysqli_stmt_get_result($rxStmt);
        $rxRecord = mysqli_fetch_assoc($rxRes);
        mysqli_stmt_close($rxStmt);
    }
}

// 4. Fallback: check `appointments` table for JSON prescription
if (!$rxRecord && $patientId > 0) {
    $aStmt = mysqli_prepare($connection1, "SELECT `prescription`, `notes`, `appointment_date`, `appointment_time`, `doctor_name` 
                                           FROM `appointments` 
                                           WHERE `patient_id` = ? AND `prescription` IS NOT NULL AND `prescription` != '' 
                                           ORDER BY `id` DESC LIMIT 1");
    if ($aStmt) {
        mysqli_stmt_bind_param($aStmt, "i", $patientId);
        mysqli_stmt_execute($aStmt);
        $aRes = mysqli_stmt_get_result($aStmt);
        if ($aRow = mysqli_fetch_assoc($aRes)) {
            $decoded = json_decode($aRow['prescription'], true);
            if (is_array($decoded)) {
                $rxRecord = [
                    'prescription_date' => $aRow['appointment_date'] ?: date('Y-m-d'),
                    'prescription_time' => $aRow['appointment_time'] ?: date('h:i A'),
                    'patient_name' => $patientInfo['full_name'],
                    'doctor_name' => $aRow['doctor_name'] ?: 'Doctor',
                    'medical_history' => $decoded['medical_history'] ?? '',
                    'symptoms' => is_array($decoded['symptoms'] ?? null) ? json_encode($decoded['symptoms']) : ($decoded['symptoms'] ?? ''),
                    'diagnosis' => $decoded['diagnosis'] ?? '',
                    'diagnosis_duration' => $decoded['diagnosis_duration'] ?? '',
                    'medications' => is_array($decoded['medications'] ?? null) ? json_encode($decoded['medications']) : null,
                    'examination_findings' => $decoded['examination_findings'] ?? '',
                    'notes' => $decoded['notes'] ?? $aRow['notes'] ?? '',
                    'doctor_specialty' => $decoded['doctor_specialty'] ?? 'Ayurvedic Medicine',
                    'doctor_phone' => $decoded['phone'] ?? '',
                    'doctor_email' => $decoded['email'] ?? $decoded['support_email'] ?? ''
                ];
            }
        }
        mysqli_stmt_close($aStmt);
    }
}

// If no prescription found at all, return has_prescriptions: false with patient details
if (!$rxRecord) {
    echo json_encode([
        'success' => true,
        'has_prescriptions' => false,
        'prescription' => null,
        'prescriptions_list' => [],
        'patient_info' => $patientInfo,
        'message' => 'No prescriptions have been issued for this patient yet.'
    ]);
    exit();
}

// Parse medications JSON from database
$medications = [];
if (!empty($rxRecord['medications'])) {
    $parsedMeds = json_decode($rxRecord['medications'], true);
    if (is_array($parsedMeds)) {
        $medications = $parsedMeds;
    }
}

// Format date e.g. "08/09/2026"
$rxRawDate = trim($rxRecord['prescription_date'] ?? '');
$rxDateFormatted = '--';
if (!empty($rxRawDate) && $rxRawDate !== '0000-00-00' && strpos($rxRawDate, '-0001') === false) {
    $ts = strtotime($rxRawDate);
    if ($ts && $ts > 0) {
        $rxDateFormatted = date('d/m/Y', $ts);
    }
}
if ($rxDateFormatted === '--') {
    $rxDateFormatted = date('d/m/Y');
}
$rxTimeFormatted = trim($rxRecord['prescription_time'] ?? '');
if (empty($rxTimeFormatted) || $rxTimeFormatted === '--') {
    $rxTimeFormatted = date('h:i A');
}

// Format/parse symptoms
$symptomsData = $rxRecord['symptoms'] ?? '';
if (!empty($symptomsData) && is_string($symptomsData)) {
    $trimmed = trim($symptomsData);
    if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
        $decodedSyms = json_decode($trimmed, true);
        if (is_array($decodedSyms)) {
            $symptomsData = $decodedSyms;
        }
    }
}

// Resolve real doctor contact info if placeholder was stored
$docPhone = $rxRecord['doctor_phone'] ?? '';
$docEmail = $rxRecord['doctor_email'] ?? '';
$docId = intval($rxRecord['doctor_id'] ?? 0);
if ($docId > 0 && (empty($docPhone) || $docPhone === '+91 7896543210' || empty($docEmail) || $docEmail === 'ocayursupport@gmail.com')) {
    $docQ = mysqli_query($connection1, "SELECT `phone_number`, `email` FROM `doctors` WHERE `id` = {$docId} LIMIT 1");
    if ($docQ && $dRow = mysqli_fetch_assoc($docQ)) {
        if (!empty($dRow['phone_number'])) $docPhone = $dRow['phone_number'];
        if (!empty($dRow['email'])) $docEmail = $dRow['email'];
    }
}

// Construct final dynamic prescription response
$responsePrescription = [
    'id' => intval($rxRecord['id'] ?? 0),
    'prescription_code' => $rxRecord['prescription_code'] ?? ($rxRecord['id'] ? ('RX-' . str_pad($rxRecord['id'], 3, '0', STR_PAD_LEFT)) : '--'),
    'date' => $rxDateFormatted,
    'time' => $rxTimeFormatted,
    'patient_id' => $patientId,
    'patient_name' => $rxRecord['patient_name'] ?? $patientInfo['full_name'],
    'gender' => $patientInfo['gender'],
    'age' => $patientInfo['age'],
    'medical_history' => $rxRecord['medical_history'] ?? 'None recorded',
    'symptoms' => $symptomsData,
    'diagnosis' => $rxRecord['diagnosis'] ?? 'General Consultation',
    'diagnosis_duration' => $rxRecord['diagnosis_duration'] ?? '',
    'medications' => $medications,
    'examination_findings' => $rxRecord['examination_findings'] ?? 'None recorded',
    'notes' => $rxRecord['notes'] ?? '',
    'doctor_name' => $rxRecord['doctor_name'] ?? 'Doctor',
    'doctor_specialty' => $rxRecord['doctor_specialty'] ?? 'Ayurvedic Medicine',
    'doctor_phone' => $docPhone ?: '+91 7896543210',
    'doctor_email' => $docEmail ?: 'care@ocayur.com'
];

echo json_encode([
    'success' => true,
    'has_prescriptions' => true,
    'prescription' => $responsePrescription,
    'prescriptions_list' => $allPrescriptions
]);
?>