<?php
/**
 * Ocayur Doctor Portal - Get Appointments API
 * Fetches appointments dynamically from `appointments` table with tab filtering, search & pagination
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

date_default_timezone_set('Asia/Kolkata');

$tab = trim($_GET['tab'] ?? 'upcoming'); // 'upcoming' | 'history' | 'all'
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = max(1, intval($_GET['limit'] ?? 5));
$doctorId = intval($_GET['doctor_id'] ?? $_SESSION['doctor_id'] ?? 0);

// Current Local Time handling
$clientNow = trim($_GET['client_now'] ?? '');
if (!empty($clientNow) && strtotime($clientNow) !== false) {
    $nowStr = date('Y-m-d H:i:s', strtotime($clientNow));
} else {
    $nowStr = date('Y-m-d H:i:s');
}

// SQL expression to accurately compute appointment end/start datetime from date and time string
$dtExpr = "COALESCE(
    STR_TO_DATE(CONCAT(`a`.`appointment_date`, ' ', TRIM(SUBSTRING_INDEX(`a`.`appointment_time`, '-', -1))), '%Y-%m-%d %h:%i %p'),
    STR_TO_DATE(CONCAT(`a`.`appointment_date`, ' ', TRIM(SUBSTRING_INDEX(`a`.`appointment_time`, '-', 1))), '%Y-%m-%d %h:%i %p'),
    STR_TO_DATE(CONCAT(`a`.`appointment_date`, ' ', TRIM(`a`.`appointment_time`)), '%Y-%m-%d %H:%i:%s'),
    STR_TO_DATE(CONCAT(`a`.`appointment_date`, ' ', TRIM(`a`.`appointment_time`)), '%Y-%m-%d %H:%i'),
    CAST(CONCAT(`a`.`appointment_date`, ' 23:59:59') AS DATETIME)
)";

// Base where clauses
$whereClauses = [];
$params = [];
$types = '';

// 1. Doctor Filter
if ($doctorId > 0) {
    $whereClauses[] = "(`a`.`doctor_id` = ? OR `a`.`doctor_id` IS NULL OR `a`.`doctor_id` = 0)";
    $params[] = $doctorId;
    $types .= 'i';
}

// 2. Tab Condition (Upcoming after present time vs History in past)
if ($tab === 'upcoming') {
    $whereClauses[] = "`a`.`status` NOT IN ('Completed', 'Cancelled')";
    $whereClauses[] = "{$dtExpr} >= ?";
    $params[] = $nowStr;
    $types .= 's';
} elseif ($tab === 'history') {
    $whereClauses[] = "(`a`.`status` IN ('Completed', 'Cancelled') OR {$dtExpr} < ?)";
    $params[] = $nowStr;
    $types .= 's';
}

// 3. Search Condition
if (!empty($search)) {
    $searchWildcard = '%' . $search . '%';
    $whereClauses[] = "(`a`.`appointment_id` LIKE ? OR `a`.`patient_name` LIKE ? OR `a`.`agenda` LIKE ? OR `a`.`package_name` LIKE ? OR `p`.`full_name` LIKE ?)";
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $types .= 'sssss';
}

$whereSql = !empty($whereClauses) ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

// Count Total Query
$countQuery = "SELECT COUNT(*) as total FROM `appointments` `a` LEFT JOIN `patients` `p` ON (`a`.`patient_id` = `p`.`id`) {$whereSql}";
$stmtCount = mysqli_prepare($connection1, $countQuery);
if (!empty($params) && $stmtCount) {
    mysqli_stmt_bind_param($stmtCount, $types, ...$params);
}
if ($stmtCount) {
    mysqli_stmt_execute($stmtCount);
    $resCount = mysqli_stmt_get_result($stmtCount);
    $rowCount = mysqli_fetch_assoc($resCount);
    $totalItems = intval($rowCount['total'] ?? 0);
    mysqli_stmt_close($stmtCount);
} else {
    $resDirect = mysqli_query($connection1, $countQuery);
    $rowCount = $resDirect ? mysqli_fetch_assoc($resDirect) : null;
    $totalItems = intval($rowCount['total'] ?? 0);
}

// Order clause: upcoming in chronological queue order; history in reverse chronological order
$orderSql = ($tab === 'upcoming') 
    ? "ORDER BY `a`.`appointment_date` ASC, {$dtExpr} ASC, `a`.`id` ASC"
    : "ORDER BY `a`.`appointment_date` DESC, {$dtExpr} DESC, `a`.`id` DESC";

$totalPages = max(1, ceil($totalItems / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

// Fetch Paginated Records
$dataQuery = "SELECT 
                `a`.`id`,
                `a`.`appointment_id`,
                `a`.`patient_id`,
                `a`.`patient_name`,
                COALESCE(`p`.`patient_id`, CONCAT('P00', `a`.`patient_id`)) AS `patient_code`,
                COALESCE(NULLIF(`a`.`package_name`, ''), `p`.`package_name`, 'Stresscare') AS `package_name`,
                `a`.`appointment_date`,
                `a`.`appointment_time`,
                `a`.`duration`,
                `a`.`service_type`,
                `a`.`agenda`,
                `a`.`status`,
                `a`.`notes`,
                `a`.`doctor_name`
              FROM `appointments` `a`
              LEFT JOIN `patients` `p` ON (`a`.`patient_id` = `p`.`id` OR `a`.`patient_name` = `p`.`full_name`)
              {$whereSql}
              {$orderSql}
              LIMIT ?, ?";

$dataParams = $params;
$dataParams[] = $offset;
$dataParams[] = $limit;
$dataTypes = $types . 'ii';

$stmtData = mysqli_prepare($connection1, $dataQuery);
if ($stmtData) {
    mysqli_stmt_bind_param($stmtData, $dataTypes, ...$dataParams);
    mysqli_stmt_execute($stmtData);
    $resData = mysqli_stmt_get_result($stmtData);
} else {
    $resData = mysqli_query($connection1, "SELECT `a`.*, COALESCE(`p`.`patient_id`, CONCAT('P00', `a`.`patient_id`)) AS `patient_code` FROM `appointments` `a` LEFT JOIN `patients` `p` ON (`a`.`patient_id` = `p`.`id`) {$whereSql} {$orderSql} LIMIT {$offset}, {$limit}");
}

$appointments = [];

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

if ($resData) {
    while ($row = mysqli_fetch_assoc($resData)) {
        $rawDate = $row['appointment_date'] ?? '';
        $formattedDate = '2 Sept 2026';
        if (!empty($rawDate) && $rawDate !== '0000-00-00') {
            $formattedDate = date('j M Y', strtotime($rawDate));
        }

        $timeStr = $row['appointment_time'] ?: '8:00 AM - 8:45 AM';
        $duration = calculateDurationFromTime($timeStr, $row['duration'] ?: '45 mins');

        $agenda = $row['agenda'] ?: 'General Consultation & Review';
        $status = ucfirst(strtolower($row['status'] ?: 'Scheduled'));

        $appointments[] = [
            'id' => intval($row['id']),
            'appointment_id' => $row['appointment_id'] ?: ('ABC-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT)),
            'patient_id' => intval($row['patient_id'] ?: 1),
            'patient_code' => $row['patient_code'] ?: ('P00' . ($row['patient_id'] ?: 1)),
            'patient_name' => $row['patient_name'] ?: 'Patient',
            'package_name' => $row['package_name'] ?: 'Stresscare',
            'date_raw' => $rawDate,
            'date' => $formattedDate,
            'time' => $timeStr,
            'duration' => $duration,
            'agenda' => $agenda,
            'complaint' => $agenda,
            'status' => $status,
            'meet_link' => 'https://meet.google.com',
            'prescription_url' => 'add-prescription.html?patient_id=' . intval($row['patient_id'] ?: 1) . '&appointment_id=' . urlencode($row['appointment_id'] ?: ('ABC-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT))) . '&id=' . intval($row['id'])
        ];
    }
    if ($stmtData) {
        mysqli_stmt_close($stmtData);
    }
}

$startItem = $totalItems > 0 ? ($offset + 1) : 0;
$endItem = min($offset + $limit, $totalItems);

echo json_encode([
    'success' => true,
    'tab' => $tab,
    'appointments' => $appointments,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'items_per_page' => $limit,
        'total_items' => $totalItems,
        'start_item' => $startItem,
        'end_item' => $endItem
    ]
]);
?>
