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

function sendPrescriptionResponse($success, $message, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge([
        'success' => (bool)$success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data), JSON_UNESCAPED_UNICODE);
    exit();
}

// Convert PHP warnings and notices to ErrorException so they don't corrupt JSON output
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        sendPrescriptionResponse(false, 'Only POST requests are allowed.', [], 405);
    }

    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/activity_logger.php';

    // Ensure database connection
    if (!isset($connection1) || !$connection1) {
        sendPrescriptionResponse(false, 'Database connection unavailable.', [], 500);
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $patientId = intval($input['patient_id'] ?? 0);
    // appointment_db_id: numeric PK sent directly (preferred, avoids string-parse ambiguity)
    $appointmentDbId = intval($input['appointment_db_id'] ?? 0);
    $rawAppointmentId = trim(strval($input['appointment_id'] ?? ''));
    // If numeric PK provided directly, use it; otherwise parse from alphanumeric code
    $appointmentId = ($appointmentDbId > 0) ? $appointmentDbId : intval($rawAppointmentId);
    $patientName = trim($input['patient_name'] ?? '');
    $diagnosis = trim($input['diagnosis_name'] ?? $input['diagnosis'] ?? '');
    $diagnosisDuration = trim($input['diagnosis_duration'] ?? $input['duration'] ?? '');
    $diagnosisType = trim($input['diagnosis_type'] ?? 'Provisional');
    $symptoms = $input['symptoms'] ?? [];
    $medications = $input['medications'] ?? [];
    $examinationFindings = trim($input['examination_findings'] ?? '');
    $examFindingsTags = is_array($input['exam_findings_tags'] ?? null) ? $input['exam_findings_tags'] : [];
    $notes = trim($input['notes'] ?? '');
    $medicalHistory = trim($input['medical_history'] ?? '');
    $vitals = is_array($input['vitals'] ?? null) ? $input['vitals'] : [];
    $clinicalHistory = is_array($input['clinical_history'] ?? null) ? $input['clinical_history'] : [];
    $investigations = is_array($input['investigations'] ?? null) ? $input['investigations'] : [];
    $labResults = is_array($input['lab_results'] ?? null) ? $input['lab_results'] : [];
    $procedures = is_array($input['procedures'] ?? null) ? $input['procedures'] : [];
    $advice = is_array($input['advice'] ?? null) ? $input['advice'] : [];
    $followUpDate = trim($input['follow_up_date'] ?? '');
    $followUpNotes = trim($input['follow_up_notes'] ?? '');
    $referral = is_array($input['referral'] ?? null) ? $input['referral'] : [];
    $doctorRegNo = trim($input['doctor_reg_no'] ?? '');
    $digitalSignature = trim($input['digital_signature'] ?? '');
    $includeQr = isset($input['include_qr']) ? (bool)$input['include_qr'] : true;

    if (empty($diagnosis)) {
        sendPrescriptionResponse(false, 'Diagnosis is required to issue a prescription.', [], 400);
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
                    'form' => trim($med['form'] ?? ''),
                    'dose' => trim($med['dose'] ?? ''),
                    'frequency' => trim($med['frequency'] ?? ''),
                    'timing' => trim($med['timing'] ?? ''),
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
        'doctor_reg_no' => $doctorRegNo,
        'digital_signature' => $digitalSignature,
        'include_qr' => $includeQr,
        'vitals' => $vitals,
        'clinical_history' => $clinicalHistory,
        'medical_history' => $medicalHistory,
        'symptoms' => $symptoms,
        'exam_findings_tags' => $examFindingsTags,
        'examination_findings' => $examinationFindings,
        'diagnosis' => $diagnosis,
        'diagnosis_duration' => $diagnosisDuration,
        'diagnosis_type' => $diagnosisType,
        'medications' => $medications,
        'investigations' => $investigations,
        'lab_results' => $labResults,
        'procedures' => $procedures,
        'advice' => $advice,
        'notes' => $notes,
        'follow_up_date' => $followUpDate,
        'follow_up_notes' => $followUpNotes,
        'referral' => $referral,
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

    // 1. Insert into `prescriptions` table in MySQL (inspecting available columns for forward/backward compatibility)
    $symptomsStr = is_array($symptoms) ? json_encode($symptoms, JSON_UNESCAPED_UNICODE) : strval($symptoms);
    $medicationsStr = json_encode($medications, JSON_UNESCAPED_UNICODE);
    $rxCode = 'RX-' . date('ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
    $today = date('Y-m-d');
    $timeNow = date('h:i A');

    $existingCols = [];
    $colRes = mysqli_query($connection1, "SHOW COLUMNS FROM `prescriptions`");
    if ($colRes) {
        while ($cRow = mysqli_fetch_assoc($colRes)) {
            $existingCols[$cRow['Field']] = true;
        }
    }

    $rxInsertFields = [
        ['col' => 'prescription_code', 'type' => 's', 'val' => $rxCode],
        ['col' => 'patient_id', 'type' => 'i', 'val' => $patientId],
        ['col' => 'patient_name', 'type' => 's', 'val' => $patientName],
        ['col' => 'doctor_id', 'type' => 'i', 'val' => $doctorId],
        ['col' => 'doctor_name', 'type' => 's', 'val' => $doctorName],
        ['col' => 'doctor_specialty', 'type' => 's', 'val' => $doctorArea],
        ['col' => 'doctor_phone', 'type' => 's', 'val' => $doctorPhone],
        ['col' => 'doctor_email', 'type' => 's', 'val' => $doctorEmail],
        ['col' => 'appointment_id', 'type' => 'i', 'val' => $appointmentId],
        ['col' => 'prescription_date', 'type' => 's', 'val' => $today],
        ['col' => 'prescription_time', 'type' => 's', 'val' => $timeNow],
        ['col' => 'medical_history', 'type' => 's', 'val' => $medicalHistory],
        ['col' => 'symptoms', 'type' => 's', 'val' => $symptomsStr],
        ['col' => 'diagnosis', 'type' => 's', 'val' => $diagnosis],
        ['col' => 'diagnosis_duration', 'type' => 's', 'val' => $diagnosisDuration],
        ['col' => 'medications', 'type' => 's', 'val' => $medicationsStr],
        ['col' => 'examination_findings', 'type' => 's', 'val' => $examinationFindings],
        ['col' => 'notes', 'type' => 's', 'val' => $notes],
    ];

    $optionalFields = [
        ['col' => 'vitals', 'type' => 's', 'val' => json_encode($vitals, JSON_UNESCAPED_UNICODE)],
        ['col' => 'clinical_history', 'type' => 's', 'val' => json_encode($clinicalHistory, JSON_UNESCAPED_UNICODE)],
        ['col' => 'diagnosis_type', 'type' => 's', 'val' => $diagnosisType],
        ['col' => 'exam_findings_tags', 'type' => 's', 'val' => json_encode($examFindingsTags, JSON_UNESCAPED_UNICODE)],
        ['col' => 'investigations', 'type' => 's', 'val' => json_encode($investigations, JSON_UNESCAPED_UNICODE)],
        ['col' => 'lab_results', 'type' => 's', 'val' => json_encode($labResults, JSON_UNESCAPED_UNICODE)],
        ['col' => 'procedures', 'type' => 's', 'val' => json_encode($procedures, JSON_UNESCAPED_UNICODE)],
        ['col' => 'advice', 'type' => 's', 'val' => json_encode($advice, JSON_UNESCAPED_UNICODE)],
        ['col' => 'follow_up_date', 'type' => 's', 'val' => (!empty($followUpDate) ? $followUpDate : null)],
        ['col' => 'follow_up_notes', 'type' => 's', 'val' => $followUpNotes],
        ['col' => 'referral', 'type' => 's', 'val' => json_encode($referral, JSON_UNESCAPED_UNICODE)],
        ['col' => 'doctor_reg_no', 'type' => 's', 'val' => $doctorRegNo],
        ['col' => 'digital_signature', 'type' => 's', 'val' => $digitalSignature],
        ['col' => 'include_qr', 'type' => 'i', 'val' => ($includeQr ? 1 : 0)],
    ];

    foreach ($optionalFields as $opt) {
        if (isset($existingCols[$opt['col']])) {
            $rxInsertFields[] = $opt;
        }
    }

    $colNames = array_map(fn($f) => '`' . $f['col'] . '`', $rxInsertFields);
    $placeholders = array_fill(0, count($rxInsertFields), '?');
    $bindTypes = implode('', array_map(fn($f) => $f['type'], $rxInsertFields));
    $bindVals = array_map(fn($f) => $f['val'], $rxInsertFields);

    $rxSql = "INSERT INTO `prescriptions` (" . implode(', ', $colNames) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $rxStmt = mysqli_prepare($connection1, $rxSql);
    if ($rxStmt) {
        mysqli_stmt_bind_param($rxStmt, $bindTypes, ...$bindVals);
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

    // Record activity (safely wrapped so log failure never breaks primary action)
    try {
        if (function_exists('getCurrentActor') && function_exists('recordActivity')) {
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
        }
    } catch (Throwable $logEx) {
        error_log("Prescription activity logging notice: " . $logEx->getMessage());
    }

    sendPrescriptionResponse(true, 'Prescription added and saved successfully!', [
        'prescription' => $prescriptionData
    ]);

} catch (mysqli_sql_exception $e) {
    sendPrescriptionResponse(false, 'Database Error: ' . $e->getMessage(), [
        'error_type' => 'mysqli_sql_exception',
        'sqlstate' => method_exists($e, 'getSqlState') ? $e->getSqlState() : null,
        'error_code' => $e->getCode(),
        'error_details' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
} catch (Throwable $e) {
    sendPrescriptionResponse(false, 'Server Error: ' . $e->getMessage(), [
        'error_type' => get_class($e),
        'error_code' => $e->getCode(),
        'error_details' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}
