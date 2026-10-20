<?php
/**
 * Ocayur Doctor Portal - Get Protocol Details API
 * Fetches specific protocol guidelines based on schema.sql
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

$packageId = intval($_GET['id'] ?? 0);
$packageCode = trim($_GET['package'] ?? $_GET['package_id'] ?? '');

$package = null;
if ($packageId > 0) {
    $stmt = mysqli_prepare($connection1, "SELECT * FROM `packages` WHERE `id` = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $packageId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $package = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
} elseif (!empty($packageCode)) {
    // Try matching package_id or package_name or slug
    $stmt = mysqli_prepare($connection1, "SELECT * FROM `packages` WHERE `package_id` = ? OR `package_name` LIKE ? LIMIT 1");
    $likeCode = "%" . str_replace('-', ' ', $packageCode) . "%";
    mysqli_stmt_bind_param($stmt, "ss", $packageCode, $likeCode);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $package = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

if (!$package) {
    // If no specific package ID provided, default to the first active package in the database
    $res = mysqli_query($connection1, "SELECT * FROM `packages` ORDER BY `id` ASC LIMIT 1");
    if ($res) {
        $package = mysqli_fetch_assoc($res);
    }
}

if (!$package) {
    echo json_encode([
        'success' => false,
        'message' => 'Protocol package not found'
    ]);
    exit();
}

$protocolName = $package['package_name'] ?? 'Protocol Details';
$dietHydration = trim((string)($package['diet_hydration'] ?? ''));
$yogaPhysio = trim((string)($package['yoga_physio'] ?? ''));
$ayurvedaDincharya = trim((string)($package['ayurveda_dinacharya'] ?? ''));
$dailyActivity = trim((string)($package['daily_activity'] ?? ''));
$patientMonitoring = trim((string)($package['patient_monitoring'] ?? ''));
$followupReview = trim((string)($package['followup_review'] ?? ''));

echo json_encode([
    'success' => true,
    'protocol' => [
        'id' => intval($package['id']),
        'package_id' => $package['package_id'] ?? '',
        'package_name' => $protocolName,
        'category' => $package['category'] ?? '',
        'duration' => $package['duration'] ?? '',
        'price' => floatval($package['price'] ?? 0.00),
        'enrollments' => intval($package['enrollments'] ?? 0),
        'short_description' => $package['short_description'] ?? '',
        'overview' => $package['overview'] ?? '',
        'benefits' => $package['benefits'] ?? '',
        'included' => $package['included'] ?? '',
        'diet_hydration' => $dietHydration,
        'yoga_physio' => $yogaPhysio,
        'ayurveda_dinacharya' => $ayurvedaDincharya,
        'daily_activity' => $dailyActivity,
        'patient_monitoring' => $patientMonitoring,
        'followup_review' => $followupReview
    ]
]);
?>
