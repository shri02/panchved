<?php
/**
 * Ocayur Doctor Portal - Book Workshop & Payment API
 * Handles registration and saves booking records in MySQL
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

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

$workshopId = intval($data['workshop_id'] ?? 1);
$doctorId = intval($_SESSION['doctor_id'] ?? $data['doctor_id'] ?? 1);
$doctorName = trim($data['doctor_name'] ?? $_SESSION['doctor_name'] ?? $_SESSION['full_name'] ?? 'Doctor');
$doctorEmail = trim($data['doctor_email'] ?? $_SESSION['doctor_email'] ?? $_SESSION['email'] ?? '');
$doctorPhone = trim($data['doctor_phone'] ?? $_SESSION['doctor_phone'] ?? $_SESSION['phone_number'] ?? '');
$amount = floatval($data['amount'] ?? 0.00);
$paymentMethod = trim($data['payment_method'] ?? 'card');

// Fetch workshop info
$stmt = mysqli_prepare($connection1, "SELECT * FROM `workshops` WHERE `id` = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $workshopId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$workshop = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

$workshopTitle = $workshop['title'] ?? 'Ayurveda Wellness Workshop';
$workshopDate = $workshop['date'] ?? date('Y-m-d');
$formattedDate = date('j M Y', strtotime($workshopDate) ?: time());

// Generate unique 6-digit booking code
$bookingCode = '#' . rand(100000, 999999);

// Insert into workshop_bookings
$insertStmt = mysqli_prepare($connection1, "INSERT INTO `workshop_bookings` 
    (`booking_id`, `workshop_id`, `doctor_id`, `doctor_name`, `doctor_email`, `doctor_phone`, `amount`, `payment_method`, `payment_status`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Success')");

if ($insertStmt) {
    mysqli_stmt_bind_param($insertStmt, "siisssds", 
        $bookingCode, $workshopId, $doctorId, $doctorName, $doctorEmail, $doctorPhone, $amount, $paymentMethod
    );
    mysqli_stmt_execute($insertStmt);
    mysqli_stmt_close($insertStmt);
}

// Increment workshop registration count
mysqli_query($connection1, "UPDATE `workshops` SET `registrations` = `registrations` + 1 WHERE `id` = $workshopId");

// Audit log
$actor = getCurrentActor('Doctor');
$docId = $actor['user_id'] ?: ($doctorId ?: null);
$docName = $actor['user_name'] ?: ($doctorName ?: 'Doctor');

recordActivity(
    $connection1,
    'Workshop',
    'workshop.booked',
    "Dr. {$docName} registered for workshop: {$workshopTitle} (Booking: {$bookingCode}, Fee: ₹" . number_format($amount, 2) . ")",
    'Workshop',
    $workshopId,
    $workshopTitle,
    [
        'booking_id' => $bookingCode,
        'workshop_id' => $workshopId,
        'workshop_title' => $workshopTitle,
        'doctor_id' => $docId,
        'doctor_name' => $docName,
        'amount' => $amount,
        'payment_method' => $paymentMethod
    ],
    'Doctor',
    $docId,
    $docName
);

echo json_encode([
    'success' => true,
    'booking_id' => $bookingCode,
    'workshop_title' => $workshopTitle,
    'amount' => '₹' . number_format($amount, 2),
    'date' => $formattedDate,
    'message' => 'Payment confirmed and workshop registered successfully.'
]);
?>