<?php
/**
 * Ocayur Doctor Portal - Change Password API
 * Updates doctor password in `doctors` and `users` tables
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/activity_logger.php';

function cleanPhone($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) > 10 && (substr($digits, 0, 2) === '91')) {
        $digits = substr($digits, 2);
    }
    return $digits;
}

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    $rawInput = @file_get_contents('php://stdin');
}
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$oldPassword = trim($input['oldPassword'] ?? $input['old_password'] ?? '');
$newPassword = trim($input['newPassword'] ?? $input['new_password'] ?? '');
$confirmPassword = trim($input['confirmPassword'] ?? $input['confirm_password'] ?? '');

$doctorId = $input['doctor_id'] ?? $input['id'] ?? $_SESSION['doctor_id'] ?? null;
$phone = trim($input['phoneNumber'] ?? $input['phone_number'] ?? $input['phone'] ?? '');
$email = trim($input['email'] ?? '');

// Validation
if (empty($oldPassword)) {
    echo json_encode(['success' => false, 'message' => 'Please enter your current old password.']);
    exit();
}

if (empty($newPassword)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a new password.']);
    exit();
}

$hasLetter = preg_match('/[a-zA-Z]/', $newPassword);
$hasNumber = preg_match('/[0-9]/', $newPassword);
$hasSpecial = preg_match('/[^a-zA-Z0-9]/', $newPassword);

if (strlen($newPassword) < 8 || !$hasLetter || !$hasNumber || !$hasSpecial) {
    echo json_encode([
        'success' => false,
        'message' => 'The password should contain special characters, numbers, and alphabets, and it must be at least 8 digits long.'
    ]);
    exit();
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'New password and confirm password do not match.']);
    exit();
}

// Find doctor record
$doctor = null;

if (!empty($doctorId)) {
    $stmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `id` = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $doctorId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $doctor = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

if (!$doctor && !empty($phone)) {
    $cleanP = cleanPhone($phone);
    $stmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `phone_number` = ? OR `phone_number` = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ss", $phone, $cleanP);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $doctor = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

if (!$doctor && !empty($email)) {
    $stmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `email` = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $doctor = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

if (!$doctor) {
    // Fallback to active doctor
    $res = mysqli_query($connection1, "SELECT * FROM `doctors` ORDER BY `id` ASC LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $doctor = mysqli_fetch_assoc($res);
    }
}

if (!$doctor) {
    echo json_encode(['success' => false, 'message' => 'Doctor profile not found.']);
    exit();
}

$storedPassword = (string)($doctor['password'] ?? '');
$passwordMatch = false;

if ($storedPassword !== '') {
    if ($oldPassword === $storedPassword) {
        $passwordMatch = true;
    } elseif (strlen($storedPassword) >= 60 && password_verify($oldPassword, $storedPassword)) {
        $passwordMatch = true;
    }
} else {
    // If no password set yet, accept oldPassword
    $passwordMatch = true;
}

if (!$passwordMatch) {
    echo json_encode(['success' => false, 'message' => 'Old password is incorrect.']);
    exit();
}

// Update password in `doctors` table
$docId = $doctor['id'];
$updateStmt = mysqli_prepare($connection1, "UPDATE `doctors` SET `password` = ? WHERE `id` = ?");
mysqli_stmt_bind_param($updateStmt, "si", $newPassword, $docId);
$executed = mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

if (!$executed) {
    echo json_encode(['success' => false, 'message' => 'Failed to update password: ' . mysqli_error($connection1)]);
    exit();
}

// Sync password in `users` table if exists
try {
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $docPhone = $doctor['phone_number'] ?? '';
    $cleanDocPhone = cleanPhone($docPhone);
    $docEmail = $doctor['email'] ?? '';
    
    $updateUser = mysqli_prepare($connection1, "UPDATE `users` SET `password_hash` = ? WHERE `email` = ? OR `phone_number` = ? OR `phone_number` = ?");
    mysqli_stmt_bind_param($updateUser, "ssss", $newHash, $docEmail, $docPhone, $cleanDocPhone);
    mysqli_stmt_execute($updateUser);
    mysqli_stmt_close($updateUser);
} catch (Throwable $e) {}

$docDisplayName = $doctor['full_name'] ?? 'Doctor';
recordActivity(
    $connection1,
    'Auth',
    'auth.password_changed',
    "Dr. {$docDisplayName} changed their password",
    'Doctor',
    $docId,
    $docDisplayName,
    [
        'doctor_id' => $docId,
        'email' => $doctor['email'] ?? null,
        'phone_number' => $doctor['phone_number'] ?? null
    ],
    'Doctor',
    $docId,
    $docDisplayName
);

echo json_encode([
    'success' => true,
    'message' => 'Password updated successfully!'
]);
?>
