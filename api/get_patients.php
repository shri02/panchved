<?php
/**
 * Ocayur Doctor Portal - Get Patients API
 * Provides live patient directory, multi-parameter search & filtering, and database pagination
 * Queries the MySQL `patients` table directly based on schema.sql
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

if (!function_exists('getInitials')) {
    function getInitials($name) {
        $words = preg_split('/\s+/', trim($name));
        $initials = '';
        foreach ($words as $w) {
            if (!empty($w)) {
                $initials .= strtoupper($w[0]);
            }
        }
        return substr($initials, 0, 2) ?: 'PT';
    }
}

$search = trim($_GET['search'] ?? '');
$packageFilter = trim($_GET['package'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = max(1, intval($_GET['limit'] ?? 5)); // 5 items per page to match the UI screenshot

// 1. Identify doctor of login from session, GET parameters, or doctor profile
$doctorId = intval($_GET['doctor_id'] ?? $_SESSION['doctor_id'] ?? ($_SESSION['doctor_profile']['id'] ?? 0));
$doctorName = trim($_GET['doctor_name'] ?? $_SESSION['doctor_name'] ?? ($_SESSION['doctor_profile']['full_name'] ?? ''));
$doctorCode = trim($_GET['doctor_code'] ?? $_SESSION['doctor_code'] ?? ($_SESSION['doctor_profile']['doctorid'] ?? ''));
$doctorPhone = trim($_GET['phone'] ?? $_SESSION['phone_number'] ?? ($_SESSION['doctor_profile']['phone_number'] ?? ''));
$doctorEmail = trim($_GET['email'] ?? $_SESSION['email'] ?? ($_SESSION['doctor_profile']['email'] ?? ''));

// Lookup canonical doctor details in `doctors` table
$currentDoctor = null;
if ($doctorId > 0) {
    $dStmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `id` = ? LIMIT 1");
    if ($dStmt) {
        mysqli_stmt_bind_param($dStmt, "i", $doctorId);
        mysqli_stmt_execute($dStmt);
        $dRes = mysqli_stmt_get_result($dStmt);
        $currentDoctor = mysqli_fetch_assoc($dRes);
        mysqli_stmt_close($dStmt);
    }
}

if (!$currentDoctor && !empty($doctorCode)) {
    $dStmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `doctorid` = ? LIMIT 1");
    if ($dStmt) {
        mysqli_stmt_bind_param($dStmt, "s", $doctorCode);
        mysqli_stmt_execute($dStmt);
        $dRes = mysqli_stmt_get_result($dStmt);
        $currentDoctor = mysqli_fetch_assoc($dRes);
        mysqli_stmt_close($dStmt);
    }
}

if (!$currentDoctor && !empty($doctorName)) {
    $dStmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `full_name` = ? LIMIT 1");
    if ($dStmt) {
        mysqli_stmt_bind_param($dStmt, "s", $doctorName);
        mysqli_stmt_execute($dStmt);
        $dRes = mysqli_stmt_get_result($dStmt);
        $currentDoctor = mysqli_fetch_assoc($dRes);
        mysqli_stmt_close($dStmt);
    }
}

if (!$currentDoctor && !empty($doctorPhone)) {
    $cleanP = preg_replace('/\D+/', '', $doctorPhone);
    if (strlen($cleanP) === 12 && substr($cleanP, 0, 2) === '91') {
        $cleanP = substr($cleanP, 2);
    }
    $dStmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `phone_number` = ? OR `phone_number` = ? LIMIT 1");
    if ($dStmt) {
        mysqli_stmt_bind_param($dStmt, "ss", $doctorPhone, $cleanP);
        mysqli_stmt_execute($dStmt);
        $dRes = mysqli_stmt_get_result($dStmt);
        $currentDoctor = mysqli_fetch_assoc($dRes);
        mysqli_stmt_close($dStmt);
    }
}

if ($currentDoctor) {
    $doctorId = intval($currentDoctor['id']);
    $doctorName = trim($currentDoctor['full_name']);
    $doctorCode = trim($currentDoctor['doctorid'] ?? '');
}

// Strict requirement: If no doctor of login is identified, return empty patient directory
if ($doctorId <= 0 && empty($doctorName)) {
    echo json_encode([
        'success' => true,
        'patients' => [],
        'available_packages' => [],
        'available_statuses' => [],
        'pagination' => [
            'current_page' => 1,
            'total_pages' => 1,
            'items_per_page' => $limit,
            'total_items' => 0,
            'start_item' => 0,
            'end_item' => 0
        ]
    ]);
    exit();
}

// Doctor variations for matching
$cleanDocName = preg_replace('/^Dr\.?\s*/i', '', $doctorName);
$withDr = 'Dr. ' . $cleanDocName;
$withoutDr = $cleanDocName;
$likeDoc = '%' . $cleanDocName . '%';
$docIdStr = strval($doctorId);

// Doctor Association Clause:
// Patient should only show up if:
// 1. Patient package has the doctor of login (in patient_packages or assigned in packages table), OR
// 2. Patient made consultation directly with the doctor of login (in appointments table)
$doctorWhereClause = "(
    -- 1. Direct consultation or appointment with doctor of login
    EXISTS (
        SELECT 1 FROM appointments a 
        WHERE (a.patient_id = p.id OR (a.patient_name = p.full_name AND p.full_name != ''))
          AND (
              (a.doctor_id > 0 AND a.doctor_id = ?) 
              OR (a.doctor_name != '' AND (a.doctor_name = ? OR a.doctor_name = ? OR a.doctor_name LIKE ?))
          )
    )
    OR
    -- 2. Patient package booked with doctor of login (in patient_packages table)
    EXISTS (
        SELECT 1 FROM patient_packages pp
        WHERE (pp.patient_id = p.id OR (pp.patient_phone != '' AND pp.patient_phone = p.phone_number))
          AND (
              (pp.doctor_id > 0 AND pp.doctor_id = ?)
              OR (pp.doctor_name != '' AND (pp.doctor_name = ? OR pp.doctor_name = ? OR pp.doctor_name LIKE ?))
              OR EXISTS (
                  SELECT 1 FROM packages pkg 
                  WHERE (pkg.id = pp.package_id OR pkg.package_name = pp.package_name COLLATE utf8mb4_unicode_ci)
                    AND (
                        pkg.assigned_doctor IN (?, ?, ?, ?)
                        OR pkg.assigned_doctor_one IN (?, ?, ?, ?)
                        OR pkg.assigned_doctor_2 IN (?, ?, ?, ?)
                        OR pkg.assign_doctor_two IN (?, ?, ?, ?)
                        OR pkg.assigned_doctor LIKE ?
                        OR pkg.assigned_doctor_one LIKE ?
                    )
              )
          )
    )
    OR
    -- 3. Patient assigned package in patients table has doctor of login as assigned doctor in packages table
    EXISTS (
        SELECT 1 FROM packages pkg2
        WHERE pkg2.package_name = p.package_name COLLATE utf8mb4_unicode_ci
          AND p.package_name IS NOT NULL AND p.package_name != ''
          AND (
              pkg2.assigned_doctor IN (?, ?, ?, ?)
              OR pkg2.assigned_doctor_one IN (?, ?, ?, ?)
              OR pkg2.assigned_doctor_2 IN (?, ?, ?, ?)
              OR pkg2.assign_doctor_two IN (?, ?, ?, ?)
              OR pkg2.assigned_doctor LIKE ?
              OR pkg2.assigned_doctor_one LIKE ?
          )
    )
)";

$doctorParams = [
    // 1. appointments
    $doctorId, $withDr, $withoutDr, $likeDoc,
    // 2. patient_packages
    $doctorId, $withDr, $withoutDr, $likeDoc,
    // packages in patient_packages
    $withDr, $withoutDr, $doctorName, $docIdStr,
    $withDr, $withoutDr, $doctorName, $docIdStr,
    $withDr, $withoutDr, $doctorName, $docIdStr,
    $withDr, $withoutDr, $doctorName, $docIdStr,
    $likeDoc, $likeDoc,
    // 3. packages in patients.package_name
    $withDr, $withoutDr, $doctorName, $docIdStr,
    $withDr, $withoutDr, $doctorName, $docIdStr,
    $withDr, $withoutDr, $doctorName, $docIdStr,
    $withDr, $withoutDr, $doctorName, $docIdStr,
    $likeDoc, $likeDoc
];
$doctorTypes = "isss" . "isss" . "ssssssssssssssssss" . "ssssssssssssssssss";

// Build Dynamic SQL Query & Parameter Bindings
$whereClauses = [$doctorWhereClause];
$params = $doctorParams;
$types = $doctorTypes;

// 1. Search Query
if (!empty($search)) {
    $whereClauses[] = "(p.`full_name` LIKE ? OR p.`patient_id` LIKE ? OR p.`phone_number` LIKE ? OR p.`package_name` LIKE ?)";
    $searchTerm = "%" . $search . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ssss";
}

// 2. Package Filter (supports multiple comma-separated values)
if (!empty($packageFilter)) {
    $packages = array_map('trim', explode(',', $packageFilter));
    $placeholders = implode(',', array_fill(0, count($packages), '?'));
    $whereClauses[] = "p.`package_name` IN ($placeholders)";
    foreach ($packages as $pkg) {
        $params[] = $pkg;
        $types .= "s";
    }
}

// 3. Status Filter (supports multiple comma-separated values)
if (!empty($statusFilter)) {
    $statuses = array_map('trim', explode(',', $statusFilter));
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $whereClauses[] = "p.`status` IN ($placeholders)";
    foreach ($statuses as $st) {
        $params[] = $st;
        $types .= "s";
    }
}

$whereSql = "WHERE " . implode(" AND ", $whereClauses);

// 4. Count Total Records matching criteria directly in SQL
$countSql = "SELECT COUNT(*) as total FROM `patients` p $whereSql";
$countStmt = mysqli_prepare($connection1, $countSql);

$totalItems = 0;
if ($countStmt) {
    if (!empty($types)) {
        mysqli_stmt_bind_param($countStmt, $types, ...$params);
    }
    mysqli_stmt_execute($countStmt);
    $countRes = mysqli_stmt_get_result($countStmt);
    if ($countRow = mysqli_fetch_assoc($countRes)) {
        $totalItems = intval($countRow['total']);
    }
    mysqli_stmt_close($countStmt);
}

// Calculate pagination
$totalPages = max(1, ceil($totalItems / $limit));
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

// 5. Fetch Paginated Records directly from MySQL
$dataSql = "SELECT p.* FROM `patients` p $whereSql ORDER BY p.`id` ASC LIMIT ? OFFSET ?";
$dataStmt = mysqli_prepare($connection1, $dataSql);

$patients = [];
if ($dataStmt) {
    $dataTypes = $types . "ii";
    $dataParams = array_merge($params, [$limit, $offset]);
    mysqli_stmt_bind_param($dataStmt, $dataTypes, ...$dataParams);
    mysqli_stmt_execute($dataStmt);
    $dataRes = mysqli_stmt_get_result($dataStmt);

    while ($row = mysqli_fetch_assoc($dataRes)) {
        $name = !empty($row['full_name']) ? $row['full_name'] : 'Patient';
        $age = intval($row['age'] ?? 0);
        if ($age <= 0 && !empty($row['dob'])) {
            $birthDate = new DateTime($row['dob']);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
        }

        $pCode = !empty($row['patient_id']) ? $row['patient_id'] : ('P' . str_pad($row['id'], 3, '0', STR_PAD_LEFT));
        $pkg = !empty($row['package_name']) ? $row['package_name'] : '-';
        $status = ucfirst(strtolower($row['status'] ?: 'Ongoing'));

        $patients[] = [
            'id' => intval($row['id']),
            'patient_id' => $pCode,
            'full_name' => $name,
            'initials' => getInitials($name),
            'age' => $age > 0 ? $age : '-',
            'phone_number' => $row['phone_number'] ?? '',
            'email' => $row['email'] ?? '',
            'gender' => $row['gender'] ?: 'Male',
            'package_name' => $pkg,
            'total_appointments' => intval($row['total_appointments'] ?? 0),
            'status' => $status,
            'status_class' => (strtolower($status) === 'completed') ? 'progress-completed' : 'progress-ongoing'
        ];
    }
    mysqli_stmt_close($dataStmt);
}

// Fetch all available packages for filter options strictly for this doctor's patients
$pkgQuery = "SELECT DISTINCT p.`package_name` FROM `patients` p WHERE $doctorWhereClause AND p.`package_name` IS NOT NULL AND p.`package_name` != '' ORDER BY p.`package_name` ASC";
$pkgStmt = mysqli_prepare($connection1, $pkgQuery);
$availablePackages = [];
if ($pkgStmt) {
    mysqli_stmt_bind_param($pkgStmt, $doctorTypes, ...$doctorParams);
    mysqli_stmt_execute($pkgStmt);
    $pkgRes = mysqli_stmt_get_result($pkgStmt);
    while ($pRow = mysqli_fetch_assoc($pkgRes)) {
        $pkgName = trim($pRow['package_name']);
        if (!empty($pkgName) && !in_array($pkgName, $availablePackages)) {
            $availablePackages[] = $pkgName;
        }
    }
    mysqli_stmt_close($pkgStmt);
}

// Fetch all available statuses for filter options strictly for this doctor's patients
$statusQuery = "SELECT DISTINCT p.`status` FROM `patients` p WHERE $doctorWhereClause AND p.`status` IS NOT NULL AND p.`status` != '' ORDER BY p.`status` ASC";
$statusStmt = mysqli_prepare($connection1, $statusQuery);
$availableStatuses = [];
if ($statusStmt) {
    mysqli_stmt_bind_param($statusStmt, $doctorTypes, ...$doctorParams);
    mysqli_stmt_execute($statusStmt);
    $statusRes = mysqli_stmt_get_result($statusStmt);
    while ($sRow = mysqli_fetch_assoc($statusRes)) {
        $stName = ucfirst(strtolower(trim($sRow['status'])));
        if (!empty($stName) && !in_array($stName, $availableStatuses)) {
            $availableStatuses[] = $stName;
        }
    }
    mysqli_stmt_close($statusStmt);
}

$startItem = $totalItems > 0 ? ($offset + 1) : 0;
$endItem = min($offset + $limit, $totalItems);

echo json_encode([
    'success' => true,
    'patients' => $patients,
    'available_packages' => $availablePackages,
    'available_statuses' => $availableStatuses,
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

