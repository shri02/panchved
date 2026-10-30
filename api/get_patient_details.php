<?php
/**
 * Ocayur Doctor Portal - Get Patient Details & Appointments API
 * Fetches full patient profile, clinical metrics, appointments, and health progress history
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

$patientId = intval($_GET['id'] ?? 0);
$patientCode = trim($_GET['patient_id'] ?? '');

// 1. Fetch Patient Record
$patient = null;
if ($patientId > 0) {
    $stmt = mysqli_prepare($connection1, "SELECT * FROM `patients` WHERE `id` = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $patientId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $patient = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
} elseif (!empty($patientCode)) {
    $stmt = mysqli_prepare($connection1, "SELECT * FROM `patients` WHERE `patient_id` = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $patientCode);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $patient = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

// Fallback to first patient ONLY if no patient was requested at all
if (!$patient) {
    if ($patientId <= 0 && empty($patientCode)) {
        $res = mysqli_query($connection1, "SELECT * FROM `patients` ORDER BY `id` ASC LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $patient = mysqli_fetch_assoc($res);
        }
    }
}

if (!$patient) {
    echo json_encode([
        'success' => false,
        'message' => 'Patient not found'
    ]);
    exit();
}

// Dynamic values from patient record or clean fallback
$id = intval($patient['id'] ?? 0);
$patientCode = $patient['patient_id'] ?? ($id > 0 ? ('P' . str_pad($id, 3, '0', STR_PAD_LEFT)) : '--');
$fullName = !empty($patient['full_name']) ? $patient['full_name'] : 'Patient';
$age = !empty($patient['age']) ? intval($patient['age']) : '--';
$gender = !empty($patient['gender']) ? $patient['gender'] : '--';
$phone = !empty($patient['phone_number']) ? $patient['phone_number'] : '--';
$email = !empty($patient['email']) ? $patient['email'] : '--';
$package = !empty($patient['package_name']) ? $patient['package_name'] : '--';
$status = !empty($patient['status']) ? $patient['status'] : 'Ongoing';

// 2. Fetch Appointments for this Patient from `appointments` table
$appointments = [];
if ($id > 0 || !empty($fullName)) {
    $stmtAppt = mysqli_prepare($connection1, "SELECT * FROM `appointments` WHERE `patient_id` = ? OR `patient_name` = ? ORDER BY `appointment_date` DESC, `id` DESC");
    if ($stmtAppt) {
        mysqli_stmt_bind_param($stmtAppt, "is", $id, $fullName);
        mysqli_stmt_execute($stmtAppt);
        $resAppt = mysqli_stmt_get_result($stmtAppt);
        while ($row = mysqli_fetch_assoc($resAppt)) {
            $formattedDate = !empty($row['appointment_date']) ? date('j M Y', strtotime($row['appointment_date'])) : '--';
            $appointments[] = [
                'id' => intval($row['id']),
                'appointment_id' => $row['appointment_id'] ?: ('ABC-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT)),
                'date' => $formattedDate,
                'time' => $row['appointment_time'] ?: '--',
                'package' => $row['package_name'] ?: ($package !== '--' ? $package : 'General Consultation'),
                'status' => ucfirst(strtolower($row['status'] ?: 'Scheduled')),
                'status_class' => (strtolower($row['status'] ?? '') === 'completed') ? 'status-completed' : 'status-scheduled',
                'agenda' => $row['agenda'] ?? '',
                'service_type' => $row['service_type'] ?? 'Consultation'
            ];
        }
        mysqli_stmt_close($stmtAppt);
    }
}

// Pagination for appointments
$page = max(1, intval($_GET['page'] ?? 1));
$limit = max(1, intval($_GET['limit'] ?? 3)); // 3 per page matching design
$totalItems = count($appointments);
$totalPages = max(1, ceil($totalItems / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;
$paginatedAppts = array_slice($appointments, $offset, $limit);

$pendingFollowups = 0;
foreach ($appointments as $apptItem) {
    if (strtolower($apptItem['status'] ?? '') === 'scheduled') {
        $pendingFollowups++;
    }
}

// 3. Dynamic Health Progress from `health_metrics` table
$healthProgress = [
    'dates' => [],
    'weight' => [
        'current' => '--',
        'values' => [],
        'unit' => 'kgs'
    ],
    'abdomen_girth' => [
        'current' => '--',
        'values' => [],
        'unit' => 'cm'
    ],
    'blood_pressure' => [
        'current' => '--',
        'values' => [],
        'unit' => 'mm Hg'
    ],
    'blood_sugar' => [
        'current' => '--',
        'values' => [],
        'unit' => 'mg/dL'
    ]
];

$chkHmTable = mysqli_query($connection1, "SHOW TABLES LIKE 'health_metrics'");
if ($chkHmTable && mysqli_num_rows($chkHmTable) > 0 && ($id > 0 || !empty($phone))) {
    $cleanPhone = preg_replace('/[^0-9]/', '', (string)$phone);
    $stmtHm = mysqli_prepare($connection1, "SELECT `id`, `weight`, `abdomen`, `bp`, `sugar`, `recorded_date` 
        FROM `health_metrics` 
        WHERE (`patient_id` = ? AND ? > 0) OR (`patient_phone` = ? AND ? != '') 
        ORDER BY `recorded_date` ASC, `id` ASC");
    if ($stmtHm) {
        mysqli_stmt_bind_param($stmtHm, "iiss", $id, $id, $cleanPhone, $cleanPhone);
        mysqli_stmt_execute($stmtHm);
        $resHm = mysqli_stmt_get_result($stmtHm);
        
        $hmReadings = [];
        while ($hmRow = mysqli_fetch_assoc($resHm)) {
            $hmReadings[] = $hmRow;
        }
        mysqli_stmt_close($stmtHm);

        if (!empty($hmReadings)) {
            foreach ($hmReadings as $hr) {
                $dLabel = !empty($hr['recorded_date']) ? date('d M', strtotime($hr['recorded_date'])) : '--';
                $healthProgress['dates'][] = $dLabel;
                $healthProgress['weight']['values'][] = ($hr['weight'] !== null && $hr['weight'] !== '') ? (float)$hr['weight'] : null;
                $healthProgress['abdomen_girth']['values'][] = ($hr['abdomen'] !== null && $hr['abdomen'] !== '') ? (float)$hr['abdomen'] : null;
                $healthProgress['blood_pressure']['values'][] = ($hr['bp'] !== null && $hr['bp'] !== '') ? trim($hr['bp']) : null;
                $healthProgress['blood_sugar']['values'][] = ($hr['sugar'] !== null && $hr['sugar'] !== '') ? (float)$hr['sugar'] : null;
            }

            // Latest recorded values for "current" metric badges
            for ($k = count($hmReadings) - 1; $k >= 0; $k--) {
                $r = $hmReadings[$k];
                if ($healthProgress['weight']['current'] === '--' && $r['weight'] !== null && $r['weight'] !== '') {
                    $healthProgress['weight']['current'] = (float)$r['weight'] . ' kgs';
                }
                if ($healthProgress['abdomen_girth']['current'] === '--' && $r['abdomen'] !== null && $r['abdomen'] !== '') {
                    $healthProgress['abdomen_girth']['current'] = (float)$r['abdomen'] . ' cm';
                }
                if ($healthProgress['blood_pressure']['current'] === '--' && $r['bp'] !== null && trim($r['bp']) !== '') {
                    $bpVal = trim($r['bp']);
                    $healthProgress['blood_pressure']['current'] = $bpVal . (stripos($bpVal, 'mm') === false ? ' mm Hg' : '');
                }
                if ($healthProgress['blood_sugar']['current'] === '--' && $r['sugar'] !== null && $r['sugar'] !== '') {
                    $healthProgress['blood_sugar']['current'] = (float)$r['sugar'] . ' mg/dL';
                }
            }
        }
    }
}

// 4. Dynamic unread messages count from `chat_messages` table
$unreadMessages = 0;
$chkChat = mysqli_query($connection1, "SHOW TABLES LIKE 'chat_messages'");
if ($chkChat && mysqli_num_rows($chkChat) > 0 && $id > 0) {
    $stmtChat = mysqli_prepare($connection1, "SELECT COUNT(*) FROM `chat_messages` WHERE `patient_id` = ? AND `sender_type` = 'patient' AND `is_seen` = 0");
    if ($stmtChat) {
        mysqli_stmt_bind_param($stmtChat, "i", $id);
        mysqli_stmt_execute($stmtChat);
        mysqli_stmt_bind_result($stmtChat, $unreadMessages);
        mysqli_stmt_fetch($stmtChat);
        mysqli_stmt_close($stmtChat);
    }
}

echo json_encode([
    'success' => true,
    'patient' => [
        'id' => $id,
        'patient_id' => $patientCode,
        'full_name' => $fullName,
        'age' => $age,
        'gender' => $gender,
        'phone_number' => $phone,
        'email' => $email,
        'package_name' => $package,
        'status' => $status
    ],
    'metrics' => [
        'total_appointments' => $totalItems,
        'pending_followups' => $pendingFollowups,
        'unread_messages' => intval($unreadMessages)
    ],
    'health_progress' => $healthProgress,
    'appointments' => $paginatedAppts,
    'all_appointments' => $appointments,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'items_per_page' => $limit,
        'total_items' => $totalItems,
        'start_item' => $totalItems > 0 ? ($offset + 1) : 0,
        'end_item' => min($offset + $limit, $totalItems)
    ]
]);
?>
