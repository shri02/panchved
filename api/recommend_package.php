<?php
/**
 * Ocayur Doctor Portal - Recommend Package API
 * Saves a package recommendation for a specific patient in `recommended_packages` table
 * Integrates directly with schema.sql and the patient portal
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

// Ensure table exists as defined in schema.sql
$createTableSql = "CREATE TABLE IF NOT EXISTS `recommended_packages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `patient_id` INT(11) DEFAULT NULL,
  `patient_phone` VARCHAR(20) DEFAULT NULL,
  `package_id` INT(11) DEFAULT NULL,
  `package_name` VARCHAR(150) NOT NULL,
  `doctor_id` INT(11) DEFAULT NULL,
  `doctor_name` VARCHAR(150) NOT NULL DEFAULT 'Doctor',
  `recommendation_note` TEXT DEFAULT NULL,
  `status` ENUM('Recommended', 'Accepted', 'Dismissed') NOT NULL DEFAULT 'Recommended',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rec_patient` (`patient_id`),
  KEY `idx_rec_phone` (`patient_phone`),
  KEY `idx_rec_pkg` (`package_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
mysqli_query($connection1, $createTableSql);

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$patientIdInput = trim($input['patient_id'] ?? '');
$packageIdInput = trim($input['package_id'] ?? '');
$packageNameInput = trim($input['package_name'] ?? '');
$customNote = trim($input['recommendation_note'] ?? '');

// Doctor info resolution
$doctorId = intval($input['doctor_id'] ?? $_SESSION['doctor_id'] ?? $_SESSION['user_id'] ?? 1);
$doctorName = trim($input['doctor_name'] ?? $_SESSION['doctor_name'] ?? $_SESSION['full_name'] ?? '');

if (empty($doctorName)) {
    // Attempt to lookup doctor from database
    $docRes = mysqli_query($connection1, "SELECT `id`, `full_name` FROM `doctors` WHERE `status` = 'Active' ORDER BY `id` ASC LIMIT 1");
    if ($docRes && ($docRow = mysqli_fetch_assoc($docRes))) {
        $doctorName = $docRow['full_name'];
        if ($doctorId <= 0) {
            $doctorId = intval($docRow['id']);
        }
    } else {
        $doctorName = 'Doctor';
    }
}

// Validation
if (empty($patientIdInput)) {
    echo json_encode([
        'success' => false,
        'message' => 'Patient ID is required.'
    ]);
    exit();
}

if (empty($packageIdInput) && empty($packageNameInput)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please select a health package to recommend.'
    ]);
    exit();
}

// 1. Resolve Patient
$patient = null;
$pSql = "SELECT `id`, `patient_id`, `full_name`, `phone_number`, `email`, `package_name` FROM `patients` WHERE `id` = ? OR `patient_id` = ? LIMIT 1";
if ($pStmt = mysqli_prepare($connection1, $pSql)) {
    $pIdInt = intval($patientIdInput);
    mysqli_stmt_bind_param($pStmt, "is", $pIdInt, $patientIdInput);
    mysqli_stmt_execute($pStmt);
    $pRes = mysqli_stmt_get_result($pStmt);
    $patient = mysqli_fetch_assoc($pRes);
    mysqli_stmt_close($pStmt);
}

if (!$patient) {
    echo json_encode([
        'success' => false,
        'message' => 'Patient record not found in system.'
    ]);
    exit();
}

$patientDbId = intval($patient['id']);
$patientName = $patient['full_name'];
$patientPhone = trim((string)$patient['phone_number']);

// 2. Resolve Package
$package = null;
if (!empty($packageIdInput)) {
    $pkgSql = "SELECT `id`, `package_id`, `package_name`, `price`, `duration`, `category`, `short_description` FROM `packages` WHERE `id` = ? OR `package_id` = ? LIMIT 1";
    if ($pkgStmt = mysqli_prepare($connection1, $pkgSql)) {
        $pkgIdInt = intval($packageIdInput);
        mysqli_stmt_bind_param($pkgStmt, "is", $pkgIdInt, $packageIdInput);
        mysqli_stmt_execute($pkgStmt);
        $pkgRes = mysqli_stmt_get_result($pkgStmt);
        $package = mysqli_fetch_assoc($pkgRes);
        mysqli_stmt_close($pkgStmt);
    }
}

if (!$package && !empty($packageNameInput)) {
    $pkgSql2 = "SELECT `id`, `package_id`, `package_name`, `price`, `duration`, `category`, `short_description` FROM `packages` WHERE `package_name` = ? LIMIT 1";
    if ($pkgStmt2 = mysqli_prepare($connection1, $pkgSql2)) {
        mysqli_stmt_bind_param($pkgStmt2, "s", $packageNameInput);
        mysqli_stmt_execute($pkgStmt2);
        $pkgRes2 = mysqli_stmt_get_result($pkgStmt2);
        $package = mysqli_fetch_assoc($pkgRes2);
        mysqli_stmt_close($pkgStmt2);
    }
}

if (!$package) {
    echo json_encode([
        'success' => false,
        'message' => 'Selected package not found in packages directory.'
    ]);
    exit();
}

$packageDbId = intval($package['id']);
$finalPackageName = $package['package_name'];

// 3. Prepare Recommendation Note
if (empty($customNote)) {
    $customNote = "Recommended by {$doctorName} following clinical assessment for personalized recovery and wellness.";
}

// 4. Insert into `recommended_packages` table
$insertSql = "INSERT INTO `recommended_packages` (
    `patient_id`, 
    `patient_phone`, 
    `package_id`, 
    `package_name`, 
    `doctor_id`, 
    `doctor_name`, 
    `recommendation_note`, 
    `status`, 
    `created_at`
) VALUES (?, ?, ?, ?, ?, ?, ?, 'Recommended', NOW())";

$recInsertStmt = mysqli_prepare($connection1, $insertSql);
if (!$recInsertStmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error preparing recommendation: ' . mysqli_error($connection1)
    ]);
    exit();
}

mysqli_stmt_bind_param(
    $recInsertStmt, 
    "isisiss", 
    $patientDbId, 
    $patientPhone, 
    $packageDbId, 
    $finalPackageName, 
    $doctorId, 
    $doctorName, 
    $customNote
);

$executed = mysqli_stmt_execute($recInsertStmt);
if ($executed) {
    $newRecommendationId = mysqli_insert_id($connection1);

    $actor = getCurrentActor('Doctor');
    $docId = $actor['user_id'] ?: ($doctorId ?: null);
    $docName = $actor['user_name'] ?: ($doctorName ?: 'Doctor');

    recordActivity(
        $connection1,
        'Package',
        'package.recommended',
        "Dr. {$docName} recommended package '{$finalPackageName}' to {$patientName}",
        'Package',
        $packageDbId,
        $finalPackageName,
        [
            'recommendation_id' => $newRecommendationId,
            'package_id' => $packageDbId,
            'package_name' => $finalPackageName,
            'patient_id' => $patientDbId,
            'patient_name' => $patientName,
            'doctor_id' => $docId,
            'doctor_name' => $docName,
            'recommendation_note' => $customNote
        ],
        'Doctor',
        $docId,
        $docName
    );
    mysqli_stmt_close($recInsertStmt);

    echo json_encode([
        'success' => true,
        'message' => "Package \"{$finalPackageName}\" recommended to {$patientName} successfully!",
        'data' => [
            'recommendation_id' => $newRecommendationId,
            'patient_id' => $patientDbId,
            'patient_name' => $patientName,
            'patient_phone' => $patientPhone,
            'package_id' => $packageDbId,
            'package_name' => $finalPackageName,
            'doctor_id' => $doctorId,
            'doctor_name' => $doctorName,
            'recommendation_note' => $customNote,
            'status' => 'Recommended',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    $err = mysqli_stmt_error($recInsertStmt);
    mysqli_stmt_close($recInsertStmt);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save recommendation in database: ' . $err
    ]);
}
?>
