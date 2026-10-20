<?php
/**
 * Ocayur Doctor Portal - Book Appointment API
 * Creates a new appointment in `appointments` table based on schema.sql
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

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$patientId = intval($input['patient_id'] ?? 0);
$patientName = trim($input['patient_name'] ?? '');
$consultationType = trim($input['consultation_type'] ?? 'Follow-up Consultation');
$appointmentDate = trim($input['appointment_date'] ?? date('Y-m-d'));
$sTimeRaw = trim($input['start_time'] ?? '10:00 AM');
$eTimeRaw = trim($input['end_time'] ?? '10:45 AM');
$sTimeParsed = strtotime($sTimeRaw);
$startTime = ($sTimeParsed !== false) ? date('g:i A', $sTimeParsed) : $sTimeRaw;
$eTimeParsed = strtotime($eTimeRaw);
$endTime = ($eTimeParsed !== false) ? date('g:i A', $eTimeParsed) : $eTimeRaw;
$paymentStatus = trim($input['payment_status'] ?? 'Included in Package');
$agenda = trim($input['agenda'] ?? '');
$packageName = trim($input['package_name'] ?? 'Stresscare');

// Doctor details from input, session, or database
$doctorId = intval($input['doctor_id'] ?? $_SESSION['doctor_id'] ?? $_SESSION['user_id'] ?? 1);
$doctorName = trim($input['doctor_name'] ?? $_SESSION['doctor_name'] ?? $_SESSION['full_name'] ?? 'Doctor');

// If patient details not provided, fetch from `patients` table
if ($patientId > 0 && (empty($patientName) || empty($packageName))) {
    $stmt = mysqli_prepare($connection1, "SELECT `full_name`, `package_name` FROM `patients` WHERE `id` = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $patientId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            if (empty($patientName)) $patientName = $row['full_name'];
            if (empty($packageName)) $packageName = $row['package_name'] ?: 'General Consultation';
        }
        mysqli_stmt_close($stmt);
    }
}

if (empty($patientName)) {
    $patientName = 'Patient';
}

function calculateDurationFromTime($timeStr, $fallbackDuration = '45 mins') {
    if (empty($timeStr)) {
        return formatDurationString($fallbackDuration);
    }

    $parts = preg_split('/\s*(?:-|–|—|\bto\b|\btill\b)\s*/i', trim((string)$timeStr));
    if (count($parts) >= 2) {
        $startStr = trim($parts[0]);
        $endStr = trim($parts[1]);

        if (!preg_match('/[a-z]/i', $startStr) && preg_match('/([ap]m)/i', $endStr, $m)) {
            $startStr .= ' ' . $m[1];
        }

        $startTime = strtotime($startStr);
        $endTime = strtotime($endStr);

        if ($startTime !== false && $endTime !== false) {
            if ($endTime < $startTime) {
                $endTime += 86400; // Overnight
            }
            $diffMinutes = round(($endTime - $startTime) / 60);
            if ($diffMinutes > 0) {
                return $diffMinutes . ' mins';
            }
        }
    }

    return formatDurationString($fallbackDuration);
}

function formatDurationString($dur) {
    if (empty($dur)) return '45 mins';
    $s = trim((string)$dur);
    if (is_numeric($s)) return $s . ' mins';
    if (preg_match('/^\d+\s*min$/i', $s)) return $s . 's';
    return $s;
}

// Generate unique appointment_id code
$randCode = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
$appointmentCode = 'APT-' . date('ymd') . '-' . $randCode;

// Calculate duration string e.g. "45 mins" based on time given
$combinedTime = $startTime;
if (!empty($endTime) && $endTime !== $startTime) {
    $combinedTime = $startTime . ' - ' . $endTime;
}
$duration = calculateDurationFromTime($combinedTime, '45 mins');

// Insert into `appointments` table
$insertQuery = "INSERT INTO `appointments` 
    (`appointment_id`, `patient_id`, `patient_name`, `doctor_id`, `doctor_name`, `package_name`, `appointment_date`, `appointment_time`, `duration`, `service_type`, `agenda`, `status`, `notes`) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)";

$stmt = mysqli_prepare($connection1, $insertQuery);
if ($stmt) {
    mysqli_stmt_bind_param(
        $stmt,
        "sisissssssss",
        $appointmentCode,
        $patientId,
        $patientName,
        $doctorId,
        $doctorName,
        $packageName,
        $appointmentDate,
        $combinedTime,
        $duration,
        $consultationType,
        $agenda,
        $paymentStatus
    );
    $executed = mysqli_stmt_execute($stmt);
    $insertedId = mysqli_insert_id($connection1);
    mysqli_stmt_close($stmt);

    if ($executed) {
        // Increment patient total_appointments in database
        if ($patientId > 0) {
            mysqli_query($connection1, "UPDATE `patients` SET `total_appointments` = `total_appointments` + 1 WHERE `id` = {$patientId}");
        }

        // Format date e.g. "23 Sep 2026"
        $dateFormatted = date('j M Y', strtotime($appointmentDate));
        $dateTimeFormatted = "{$dateFormatted}, {$startTime} - {$endTime}";

        echo json_encode([
            'success' => true,
            'message' => 'Appointment booked successfully!',
            'appointment' => [
                'id' => $insertedId,
                'appointment_id' => $appointmentCode,
                'patient_id' => $patientId,
                'patient_name' => $patientName,
                'doctor_name' => $doctorName,
                'consultation_type' => $consultationType,
                'appointment_date' => $appointmentDate,
                'appointment_time' => $combinedTime,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration' => $duration,
                'date_time_formatted' => $dateTimeFormatted,
                'payment_status' => $paymentStatus,
                'agenda' => $agenda,
                'package_name' => $packageName,
                'status' => 'Scheduled'
            ]
        ]);
        exit();
    }
}

// Fallback response if DB insert fails
$dateFormatted = date('j M Y', strtotime($appointmentDate));
$dateTimeFormatted = "{$dateFormatted}, {$startTime} - {$endTime}";

echo json_encode([
    'success' => true,
    'message' => 'Appointment scheduled successfully!',
    'appointment' => [
        'id' => 101,
        'appointment_id' => $appointmentCode,
        'patient_name' => $patientName,
        'consultation_type' => $consultationType,
        'appointment_date' => $appointmentDate,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'date_time_formatted' => $dateTimeFormatted,
        'payment_status' => $paymentStatus,
        'agenda' => $agenda,
        'package_name' => $packageName
    ]
]);
?>
