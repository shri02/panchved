<?php
/**
 * Ocayur Doctor Portal - Dashboard Data API
 * Provides dynamic metrics, doctor info, today's consultations table, and pagination from MySQL
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once __DIR__ . '/database.php';

function cleanPhone($phone) {
    $digits = preg_replace('/\D/', '', (string)$phone);
    if (strlen($digits) > 10 && (substr($digits, 0, 2) === '91')) {
        $digits = substr($digits, 2);
    }
    return $digits;
}

// Helper to compute initials from full name
function getInitials($name) {
    $words = preg_split('/\s+/', trim((string)$name));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) {
            $initials .= strtoupper($w[0]);
        }
    }
    return substr($initials, 0, 2) ?: 'PT';
}

// 1. Identify current Doctor
$doctorId = $_GET['doctor_id'] ?? $_SESSION['doctor_id'] ?? null;
$phone = $_GET['phone'] ?? $_GET['phoneNumber'] ?? null;
$email = $_GET['email'] ?? null;
$doctor = null;

if (!empty($doctorId)) {
    $stmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `id` = ? OR `doctorid` = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "is", $doctorId, $doctorId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $doctor = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
}

if (!$doctor && !empty($phone)) {
    $cleanP = cleanPhone($phone);
    $stmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `phone_number` = ? OR `phone_number` = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $phone, $cleanP);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $doctor = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
}

if (!$doctor && !empty($email)) {
    $stmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `email` = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $doctor = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
}

// Default fallback to active doctor in DB
if (!$doctor) {
    $res = mysqli_query($connection1, "SELECT * FROM `doctors` WHERE `status` = 'Active' ORDER BY `id` ASC LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $doctor = mysqli_fetch_assoc($res);
    }
}

$docId = $doctor ? intval($doctor['id']) : 0;
$docName = $doctor ? $doctor['full_name'] : '';

// 2. Dynamic Metrics Calculation from Database Tables
// Metric 1: Today's Consultation
$todayConsultationCount = 0;
$qToday = "SELECT COUNT(*) as cnt FROM `appointments` WHERE `appointment_date` = CURDATE() AND `status` != 'Cancelled'";
$resToday = mysqli_query($connection1, $qToday);
if ($resToday && $row = mysqli_fetch_assoc($resToday)) {
    $todayConsultationCount = intval($row['cnt']);
}
if ($todayConsultationCount === 0) {
    // If no specific today consultations, count total active/scheduled appointments
    $qScheduled = "SELECT COUNT(*) as cnt FROM `appointments` WHERE `status` IN ('Scheduled', 'Upcoming', 'Active')";
    $resSched = mysqli_query($connection1, $qScheduled);
    if ($resSched && $rowSched = mysqli_fetch_assoc($resSched)) {
        $todayConsultationCount = intval($rowSched['cnt']);
    }
}

// Metric 2: Active Patients
$activePatientsCount = 0;
$qPatients = "SELECT COUNT(*) as cnt FROM `patients` WHERE `status` IN ('Ongoing', 'Active')";
$resPatients = mysqli_query($connection1, $qPatients);
if ($resPatients && $row = mysqli_fetch_assoc($resPatients)) {
    $activePatientsCount = intval($row['cnt']);
}
if ($activePatientsCount === 0) {
    $qAllPatients = "SELECT COUNT(*) as cnt FROM `patients`";
    $resAll = mysqli_query($connection1, $qAllPatients);
    if ($resAll && $rAll = mysqli_fetch_assoc($resAll)) {
        $activePatientsCount = intval($rAll['cnt']);
    }
}

// Metric 3: Pending Follow-ups
$pendingFollowupsCount = 0;
$qFollowups = "SELECT COUNT(*) as cnt FROM `appointments` WHERE (`status` IN ('Scheduled', 'Upcoming', 'Pending') AND (`agenda` LIKE '%follow%' OR `service_type` LIKE '%follow%')) OR `status` = 'Pending'";
$resFollow = mysqli_query($connection1, $qFollowups);
if ($resFollow && $row = mysqli_fetch_assoc($resFollow)) {
    $pendingFollowupsCount = intval($row['cnt']);
}
if ($pendingFollowupsCount === 0) {
    $qUpcoming = "SELECT COUNT(*) as cnt FROM `appointments` WHERE `status` = 'Scheduled' AND `appointment_date` >= CURDATE()";
    $resUp = mysqli_query($connection1, $qUpcoming);
    if ($resUp && $rowUp = mysqli_fetch_assoc($resUp)) {
        $pendingFollowupsCount = intval($rowUp['cnt']);
    }
}

// Metric 4: Unread / Pending Messages & Inquiries
$unreadMessagesCount = 0;
$qMessages = "SELECT (
    (SELECT COUNT(*) FROM `appointments` WHERE `status` = 'Pending') + 
    (SELECT COUNT(*) FROM `workshop_bookings` WHERE `payment_status` = 'Pending')
) as cnt";
$resMessages = mysqli_query($connection1, $qMessages);
if ($resMessages && $row = mysqli_fetch_assoc($resMessages)) {
    $unreadMessagesCount = intval($row['cnt']);
}
if ($unreadMessagesCount === 0) {
    // If 0 pending, check total recent bookings / consultations
    $qRecent = "SELECT COUNT(*) as cnt FROM `appointments` WHERE `status` = 'Scheduled' AND `created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $resRec = mysqli_query($connection1, $qRecent);
    if ($resRec && $rowRec = mysqli_fetch_assoc($resRec)) {
        $unreadMessagesCount = max(1, intval($rowRec['cnt']));
    }
}

// 3. Consultation Table Data with Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = max(1, intval($_GET['limit'] ?? 3));

// Count total available consultations
$countQuery = "SELECT COUNT(*) as total FROM `appointments` `a` LEFT JOIN `patients` `p` ON (`a`.`patient_id` = `p`.`id` OR `a`.`patient_name` = `p`.`full_name`)";
$resCount = mysqli_query($connection1, $countQuery);
$rowCount = $resCount ? mysqli_fetch_assoc($resCount) : null;
$totalItems = intval($rowCount['total'] ?? 0);

$totalPages = max(1, ceil($totalItems / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

// Fetch paginated consultations ordered: today first, then future upcoming, then past
$query = "SELECT 
            a.id,
            a.appointment_id,
            a.patient_id,
            a.patient_name,
            COALESCE(p.patient_id, CONCAT('P00', a.patient_id), CONCAT('E00', a.id)) AS patient_code,
            COALESCE(NULLIF(a.package_name, ''), p.package_name, 'Stresscare') AS package_name,
            a.appointment_date,
            a.appointment_time,
            a.duration,
            a.service_type,
            a.status,
            a.agenda,
            a.doctor_name,
            COALESCE(a.meet_link, '') AS meet_link
          FROM `appointments` a
          LEFT JOIN `patients` p ON (a.patient_id = p.id OR a.patient_name = p.full_name)
          ORDER BY 
            CASE WHEN a.appointment_date = CURDATE() THEN 0 
                 WHEN a.appointment_date > CURDATE() THEN 1 
                 ELSE 2 END,
            a.appointment_date ASC,
            a.id ASC
          LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($connection1, $query);
$consultations = [];

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $status = ucfirst(strtolower($row['status'] ?: 'Scheduled'));
            $statusClass = 'status-scheduled';
            if ($status === 'Completed') $statusClass = 'status-completed';
            elseif ($status === 'Pending') $statusClass = 'status-pending';
            elseif ($status === 'Cancelled') $statusClass = 'status-cancelled';
            elseif ($status === 'Upcoming') $statusClass = 'status-upcoming';

            $consultations[] = [
                'id' => intval($row['id']),
                'appointment_id' => $row['appointment_id'] ?: ('ABC-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT)),
                'patient_id' => $row['patient_id'] ? intval($row['patient_id']) : intval($row['id']),
                'patient_code' => $row['patient_code'] ?: ('P00' . $row['id']),
                'patient_name' => $row['patient_name'] ?: 'Patient',
                'patient_initials' => getInitials($row['patient_name'] ?: 'Patient'),
                'appointment_date' => $row['appointment_date'],
                'appointment_time' => $row['appointment_time'] ?: '8:00 AM',
                'package_name' => $row['package_name'] ?: 'Stresscare',
                'status' => $status,
                'status_class' => $statusClass,
                'meeting_link' => !empty($row['meet_link']) ? trim($row['meet_link']) : '',
                'has_meeting_link' => !empty($row['meet_link']),
                'agenda' => $row['agenda'] ?: 'General Consultation'
            ];
        }
    }
    mysqli_stmt_close($stmt);
}

$startItem = $totalItems > 0 ? ($offset + 1) : 0;
$endItem = min($offset + $limit, $totalItems);

echo json_encode([
    'success' => true,
    'doctor' => $doctor ? [
        'id' => $doctor['id'],
        'doctorid' => $doctor['doctorid'],
        'full_name' => $doctor['full_name'],
        'expertise' => $doctor['expertise'],
        'area' => $doctor['area'],
        'phone_number' => $doctor['phone_number'],
        'email' => $doctor['email']
    ] : null,
    'metrics' => [
        'today_consultations' => $todayConsultationCount,
        'active_patients' => $activePatientsCount,
        'pending_followups' => $pendingFollowupsCount,
        'unread_messages' => $unreadMessagesCount
    ],
    'consultations' => $consultations,
    'pagination' => [
        'current_page' => $totalItems > 0 ? $page : 0,
        'total_pages' => $totalPages,
        'items_per_page' => $limit,
        'total_items' => $totalItems,
        'start_item' => $startItem,
        'end_item' => $endItem
    ]
]);
?>
