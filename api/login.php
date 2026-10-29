<?php
/**
 * Ocayur Doctor Portal - Strict Doctor Authentication API
 * Authenticates doctors strictly using their registered phone number and password from the `doctors` table
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

// Helper: normalize phone number (e.g. remove spaces, dashes, extract 10 digits)
function cleanPhone($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) > 10 && (substr($digits, 0, 2) === '91')) {
        $digits = substr($digits, 2);
    }
    return $digits;
}

// Helper: detect client IP address
function getClientIpAddress() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ipList = explode(',', $_SERVER[$key]);
            $ip = trim($ipList[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// Helper: record login timing into login_logs
function recordLoginLog($conn, $userType, $userId, $identifier, $name, $phone, $email, $status = 'Success') {
    if (!$conn) return false;
    $clientIp = getClientIpAddress();
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    $query = "INSERT INTO `login_logs` (`user_type`, `user_id`, `identifier`, `name`, `phone`, `email`, `ip_address`, `user_agent`, `status`, `login_time`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    if ($stmt = @mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "sisssssss", $userType, $userId, $identifier, $name, $phone, $email, $clientIp, $userAgent, $status);
        @mysqli_stmt_execute($stmt);
        @mysqli_stmt_close($stmt);
        return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. POST required.'
    ]);
    exit();
}

// Read input (supports JSON payload from HTTP/fetch or CLI stdin, or POST form data)
$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    $rawInput = @file_get_contents('php://stdin');
}
$inputData = json_decode($rawInput, true);

if (!is_array($inputData)) {
    $inputData = $_POST;
}

$phoneInput = trim($inputData['phoneNumber'] ?? $inputData['phone_number'] ?? $inputData['phone'] ?? $inputData['email'] ?? $inputData['username'] ?? '');
$passwordInput = trim($inputData['password'] ?? '');

if (empty($phoneInput) || empty($passwordInput)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter both your registered phone number and password.'
    ]);
    exit();
}

$cleanPhoneNum = cleanPhone($phoneInput);

// 1. Look up doctor strictly from `doctors` table by registered phone number, email or doctorid
$docQuery = "SELECT * FROM `doctors` WHERE `phone_number` = ? OR `phone_number` = ? OR `email` = ? OR `doctorid` = ? LIMIT 1";
$stmtDoc = mysqli_prepare($connection1, $docQuery);
mysqli_stmt_bind_param($stmtDoc, "ssss", $phoneInput, $cleanPhoneNum, $phoneInput, $phoneInput);
mysqli_stmt_execute($stmtDoc);
$docRes = mysqli_stmt_get_result($stmtDoc);
$doctor = mysqli_fetch_assoc($docRes);
mysqli_stmt_close($stmtDoc);

// If no doctor record found directly in `doctors` table
if (!$doctor) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid credentials. Please check your credentials.'
    ]);
    exit();
}

// 2. Doctor found in `doctors` table - check account status
if (isset($doctor['status']) && strtolower($doctor['status']) === 'inactive') {
    echo json_encode([
        'success' => false,
        'message' => 'Your doctor account is inactive. Please contact the administrator.'
    ]);
    exit();
}

// 3. Verify password strictly against `doctors` password column
$storedPassword = (string)($doctor['password'] ?? '');
$passwordValid = false;

if ($storedPassword !== '') {
    if ($passwordInput === $storedPassword) {
        $passwordValid = true;
    } elseif (strlen($storedPassword) >= 60 && password_verify($passwordInput, $storedPassword)) {
        $passwordValid = true;
    }
}

if (!$passwordValid) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid credentials. Please check your credentials.'
    ]);
    exit();
}

// Format doctor dates for UI
$dobRaw = $doctor['date_of_birth'] ?? '';
if (!empty($dobRaw) && $dobRaw !== '0000-00-00') {
    $timestamp = strtotime($dobRaw);
    $doctor['date_of_birth_iso'] = date('Y-m-d', $timestamp);
    $doctor['date_of_birth_display'] = date('d/m/Y', $timestamp);
} else {
    $doctor['date_of_birth_iso'] = '1990-01-01';
    $doctor['date_of_birth_display'] = '01/01/1990';
}

// 4. Save in PHP Session
$_SESSION['doctor_id'] = $doctor['id'];
$_SESSION['doctor_code'] = $doctor['doctorid'] ?? ('DOC' . str_pad($doctor['id'], 6, '0', STR_PAD_LEFT));
$_SESSION['doctor_profile'] = $doctor;
$_SESSION['role'] = 'Doctor';

// 5. Log login timing into `login_logs`
$docCode = $doctor['doctorid'] ?? ('DOC' . str_pad($doctor['id'], 6, '0', STR_PAD_LEFT));
recordLoginLog($connection1, 'Doctor', (int)$doctor['id'], $docCode, $doctor['full_name'], $doctor['phone_number'], $doctor['email'], 'Success');

// Update last_login if column exists in doctors table
if (!empty($doctor['id'])) {
    @mysqli_query($connection1, "UPDATE `doctors` SET `last_login` = NOW() WHERE `id` = " . (int)$doctor['id']);
}

// Remove sensitive password before returning JSON
unset($doctor['password']);

echo json_encode([
    'success' => true,
    'message' => 'Login successful! Welcome, ' . $doctor['full_name'] . '.',
    'doctor' => $doctor
]);
?>
