<?php
/**
 * Ocayur Doctor Portal - Delete / Remove Prescription API
 * Removes prescription from appointments table and prescriptions table
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

function sendDeleteRxResponse($success, $message, $data = [], $code = 200) {
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
        sendDeleteRxResponse(false, 'Only POST requests are allowed.', [], 405);
    }

    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/activity_logger.php';

    // Ensure database connection
    if (!isset($connection1) || !$connection1) {
        sendDeleteRxResponse(false, 'Database connection unavailable.', [], 500);
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $targetId = intval($input['id'] ?? ($input['appointment_db_id'] ?? 0));
    $appointmentCode = trim((string)($input['appointment_id'] ?? ($input['appointment_code'] ?? '')));
    $prescriptionId = intval($input['prescription_id'] ?? 0);
    $patientId = intval($input['patient_id'] ?? 0);

    if ($targetId <= 0 && empty($appointmentCode) && $prescriptionId <= 0) {
        sendDeleteRxResponse(false, 'Missing appointment or prescription identifier.', [], 400);
    }

    // Resolve appointment details for logging
    $resolvedApptId = 0;
    $resolvedApptCode = '';
    $patientName = '';
    $doctorName = '';
    $doctorId = 0;

    $chkQuery = "SELECT id, appointment_id, patient_id, patient_name, doctor_name, doctor_id FROM appointments WHERE " . ($targetId > 0 ? "id = ?" : "appointment_id = ?") . " LIMIT 1";
    if ($targetId > 0 || !empty($appointmentCode)) {
        $chkStmt = mysqli_prepare($connection1, $chkQuery);
        if ($chkStmt) {
            if ($targetId > 0) {
                mysqli_stmt_bind_param($chkStmt, "i", $targetId);
            } else {
                mysqli_stmt_bind_param($chkStmt, "s", $appointmentCode);
            }
            mysqli_stmt_execute($chkStmt);
            $res = mysqli_stmt_get_result($chkStmt);
            if ($row = mysqli_fetch_assoc($res)) {
                $resolvedApptId = (int)$row['id'];
                $resolvedApptCode = (string)$row['appointment_id'];
                $patientName = (string)$row['patient_name'];
                $doctorName = (string)$row['doctor_name'];
                $doctorId = (int)$row['doctor_id'];
                if ($patientId <= 0 && !empty($row['patient_id'])) {
                    $patientId = (int)$row['patient_id'];
                }
            }
            mysqli_stmt_close($chkStmt);
        }
    }

    // 1. Clear prescription in appointments table
    if ($resolvedApptId > 0 || $targetId > 0 || !empty($appointmentCode)) {
        $uQuery = "UPDATE `appointments` SET `prescription` = NULL WHERE " . (($resolvedApptId > 0 || $targetId > 0) ? "`id` = ?" : "`appointment_id` = ?");
        $uStmt = mysqli_prepare($connection1, $uQuery);
        if ($uStmt) {
            $bindVal = ($resolvedApptId > 0) ? $resolvedApptId : ($targetId > 0 ? $targetId : $appointmentCode);
            if (is_int($bindVal)) {
                mysqli_stmt_bind_param($uStmt, "i", $bindVal);
            } else {
                mysqli_stmt_bind_param($uStmt, "s", $bindVal);
            }
            mysqli_stmt_execute($uStmt);
            mysqli_stmt_close($uStmt);
        }
    }

    // 2. Remove matching record from prescriptions table if table exists
    try {
        if ($prescriptionId > 0) {
            $delStmt = mysqli_prepare($connection1, "DELETE FROM `prescriptions` WHERE `id` = ? LIMIT 1");
            if ($delStmt) {
                mysqli_stmt_bind_param($delStmt, "i", $prescriptionId);
                mysqli_stmt_execute($delStmt);
                mysqli_stmt_close($delStmt);
            }
        } elseif ($resolvedApptId > 0 || $targetId > 0) {
            $dbId = $resolvedApptId ?: $targetId;
            $delStmt = mysqli_prepare($connection1, "DELETE FROM `prescriptions` WHERE `appointment_id` = ?");
            if ($delStmt) {
                mysqli_stmt_bind_param($delStmt, "i", $dbId);
                mysqli_stmt_execute($delStmt);
                mysqli_stmt_close($delStmt);
            }
        }
    } catch (mysqli_sql_exception $delEx) {
        error_log("Notice: delete from prescriptions table: " . $delEx->getMessage());
    }

    // Record Activity
    try {
        if (function_exists('getCurrentActor') && function_exists('recordActivity')) {
            $actor = getCurrentActor('Doctor');
            $docId = $actor['user_id'] ?: ($doctorId ?: null);
            $docName = $actor['user_name'] ?: ($doctorName ?: 'Doctor');

            $identifier = $resolvedApptCode ?: ($appointmentCode ?: "#{$resolvedApptId}");
            $desc = "Doctor {$docName} removed prescription for consultation {$identifier}" . (!empty($patientName) ? " (Patient: {$patientName})" : "");

            recordActivity(
                $connection1,
                'Prescription',
                'prescription.removed',
                $desc,
                'Prescription',
                $resolvedApptId ?: $targetId,
                $identifier,
                [
                    'appointment_id' => $identifier,
                    'patient_name' => $patientName,
                    'doctor_id' => $docId,
                    'doctor_name' => $docName
                ],
                'Doctor',
                $docId,
                $docName
            );
        }
    } catch (Throwable $logEx) {
        error_log("Prescription removal activity log notice: " . $logEx->getMessage());
    }

    sendDeleteRxResponse(true, 'Prescription removed successfully.', [
        'appointment_id' => $resolvedApptCode ?: $appointmentCode,
        'id' => $resolvedApptId ?: $targetId
    ]);

} catch (mysqli_sql_exception $e) {
    sendDeleteRxResponse(false, 'Database Error: ' . $e->getMessage(), [
        'error_type' => 'mysqli_sql_exception',
        'sqlstate' => method_exists($e, 'getSqlState') ? $e->getSqlState() : null,
        'error_code' => $e->getCode(),
        'error_details' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
} catch (Throwable $e) {
    sendDeleteRxResponse(false, 'Server Error: ' . $e->getMessage(), [
        'error_type' => get_class($e),
        'error_code' => $e->getCode(),
        'error_details' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}

