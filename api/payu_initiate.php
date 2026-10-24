<?php
/**
 * Ocayur / Panchved - PayU Payment Gateway Initiation Endpoint (Doctor Portal)
 * 
 * Prepares the PayU Hosted Checkout parameters & SHA-512 hash for workshop enrollments.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/payu_config.php';

function sendInitiateResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => (bool)$success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendInitiateResponse(false, 'Only POST requests are allowed.', [], 405);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$clientOrigin = trim((string)($input['origin_url'] ?? ($input['client_origin'] ?? '')));
$config = getPayUConfig($clientOrigin);

if (!$config['is_configured']) {
    sendInitiateResponse(false, 'PayU credentials not found. Please ensure PAYU_MERCHANT_KEY and PAYU_SALT are set in Azure App Service Configuration.', [
        'is_configured' => false
    ], 500);
}

$workshopId = intval($input['workshop_id'] ?? ($input['item_id'] ?? 1));
$doctorId = intval($_SESSION['doctor_id'] ?? ($input['doctor_id'] ?? 1));
$doctorName = trim((string)($input['doctor_name'] ?? ($_SESSION['doctor_name'] ?? ($_SESSION['full_name'] ?? 'Doctor'))));
$doctorEmail = trim((string)($input['doctor_email'] ?? ($_SESSION['doctor_email'] ?? ($_SESSION['email'] ?? ''))));
$doctorPhone = trim((string)($input['doctor_phone'] ?? ($_SESSION['doctor_phone'] ?? ($_SESSION['phone_number'] ?? ''))));
$amount = isset($input['amount']) && is_numeric($input['amount']) ? (float)$input['amount'] : 0.00;
$serviceType = strtolower(trim((string)($input['service_type'] ?? 'workshop')));

// Fetch workshop details from database if available
$workshopTitle = 'Ayurveda Workshop';
if ($connection1 && $workshopId > 0) {
    try {
        if ($stmt = @mysqli_prepare($connection1, "SELECT `id`, `title`, `price`, `fee` FROM `workshops` WHERE `id` = ? LIMIT 1")) {
            @mysqli_stmt_bind_param($stmt, "i", $workshopId);
            @mysqli_stmt_execute($stmt);
            $res = @mysqli_stmt_get_result($stmt);
            if ($row = @mysqli_fetch_assoc($res)) {
                $workshopTitle = $row['title'] ?? $workshopTitle;
                if ($amount <= 0) {
                    $amount = floatval(!empty($row['fee']) && $row['fee'] > 0 ? $row['fee'] : ($row['price'] ?? 500.00));
                }
            }
            @mysqli_stmt_close($stmt);
        }
    } catch (Throwable $e) {
        // Fallback silently if query fails
    }
}

if ($amount <= 0) {
    $amount = 500.00;
}
$formattedAmount = sprintf("%.2f", $amount);

// Clean first name (PayU strictly requires letters only for firstname)
$nameParts = preg_split('/\s+/', trim($doctorName));
$firstName = !empty($nameParts[0]) ? preg_replace('/[^a-zA-Z]/', '', $nameParts[0]) : 'Doctor';
if (empty($firstName)) {
    $firstName = 'Doctor';
}

// Clean phone digits
$cleanPhone = preg_replace('/[^\d]/', '', $doctorPhone);
if (strlen($cleanPhone) === 12 && substr($cleanPhone, 0, 2) === '91') {
    $cleanPhone = substr($cleanPhone, 2);
}

$productInfo = 'Workshop: ' . substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $workshopTitle), 0, 80);
$txnid = 'TXN_DOC_WKS_' . date('YmdHis') . '_' . mt_rand(1000, 9999);

// Prepare UDF parameters
$udf1 = 'workshop';
$udf2 = (string)$workshopId;
$udf3 = (string)$doctorId;
$udf4 = substr($doctorName, 0, 50);
$udf5 = json_encode(['role' => 'doctor', 'workshop_id' => $workshopId, 'title' => $workshopTitle]);

// Pre-record in payu_transactions table if available
if ($connection1) {
    try {
        $ins = @mysqli_prepare($connection1, "INSERT INTO `payu_transactions` 
            (`txnid`, `service_type`, `item_id`, `item_title`, `patient_name`, `patient_email`, `patient_phone`, `amount`, `payment_method`, `payment_status`, `created_at`)
            VALUES (?, 'workshop', ?, ?, ?, ?, ?, ?, 'PayU', 'Pending', NOW())
            ON DUPLICATE KEY UPDATE `payment_status` = 'Pending', `amount` = VALUES(`amount`)");
        if ($ins) {
            $amtVal = floatval($formattedAmount);
            $wsIdStr = (string)$workshopId;
            @mysqli_stmt_bind_param($ins, "ssssssd",
                $txnid, $wsIdStr, $productInfo, $doctorName, $doctorEmail, $cleanPhone, $amtVal
            );
            @mysqli_stmt_execute($ins);
            @mysqli_stmt_close($ins);
        }
    } catch (Throwable $txErr) {
        // Continue even if pre-recording has an issue
    }
}

// Save pending state in session
$_SESSION['last_payu_txnid'] = $txnid;
if (!isset($_SESSION['payu_pending']) || !is_array($_SESSION['payu_pending'])) {
    $_SESSION['payu_pending'] = [];
}
$_SESSION['payu_pending'][$txnid] = [
    'service_type' => 'workshop',
    'workshop_id' => $workshopId,
    'item_id' => $workshopId,
    'item_title' => $productInfo,
    'doctor_id' => $doctorId,
    'doctor_name' => $doctorName,
    'doctor_email' => $doctorEmail,
    'doctor_phone' => $cleanPhone,
    'amount' => $formattedAmount
];

// Calculate hash
$hashParams = [
    'key' => $config['merchant_key'],
    'txnid' => $txnid,
    'amount' => $formattedAmount,
    'productinfo' => $productInfo,
    'firstname' => $firstName,
    'email' => $doctorEmail,
    'udf1' => $udf1,
    'udf2' => $udf2,
    'udf3' => $udf3,
    'udf4' => $udf4,
    'udf5' => $udf5
];
$hash = generatePayUHash($hashParams, $config['salt']);

// Build payload parameters to POST to PayU
$payuPayload = [
    'key' => $config['merchant_key'],
    'txnid' => $txnid,
    'amount' => $formattedAmount,
    'productinfo' => $productInfo,
    'firstname' => $firstName,
    'email' => $doctorEmail,
    'phone' => $cleanPhone,
    'surl' => $config['success_url'],
    'furl' => $config['failure_url'],
    'hash' => $hash,
    'service_provider' => 'payu_paisa',
    'udf1' => $udf1,
    'udf2' => $udf2,
    'udf3' => $udf3,
    'udf4' => $udf4,
    'udf5' => $udf5
];

sendInitiateResponse(true, 'Payment initiated successfully.', [
    'action_url' => $config['endpoint'],
    'txnid' => $txnid,
    'params' => $payuPayload
]);
