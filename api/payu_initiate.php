<?php
/**
 * Ocayur Doctor Portal - PayU Payment Initiation Endpoint
 * Handles workshop registration checkout for doctors.
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

function sendPayUResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendPayUResponse(false, 'Only POST requests are allowed.', [], 405);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$workshopId = intval($input['workshop_id'] ?? 1);
$doctorId = intval($_SESSION['doctor_id'] ?? $input['doctor_id'] ?? 1);
$doctorName = trim($input['doctor_name'] ?? $_SESSION['doctor_name'] ?? $_SESSION['full_name'] ?? 'Doctor');
$doctorEmail = trim($input['doctor_email'] ?? $_SESSION['doctor_email'] ?? $_SESSION['email'] ?? '');
$doctorPhone = trim($input['doctor_phone'] ?? $_SESSION['doctor_phone'] ?? $_SESSION['phone_number'] ?? '');
$amount = floatval($input['amount'] ?? 0.00);

// Fetch workshop info
$stmt = mysqli_prepare($connection1, "SELECT * FROM `workshops` WHERE `id` = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $workshopId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$workshop = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$workshop) {
    sendPayUResponse(false, 'Workshop not found.', [], 404);
}

$workshopTitle = $workshop['title'] ?? 'Ayurveda Workshop';
if ($amount <= 0) {
    $amount = floatval($workshop['fee'] > 0 ? $workshop['fee'] : ($workshop['price'] ?? 500.00));
}
$formattedAmount = sprintf("%.2f", $amount);

$clientOrigin = trim((string)($input['origin_url'] ?? ($input['client_origin'] ?? '')));
$config = getPayUConfig($clientOrigin);
$txnId = 'TXN_DOC_WKS_' . date('YmdHis') . '_' . rand(1000, 9999);

$nameParts = preg_split('/\s+/', trim($doctorName));
$firstName = !empty($nameParts[0]) ? preg_replace('/[^a-zA-Z]/', '', $nameParts[0]) : 'Doctor';
if (empty($firstName)) $firstName = 'Doctor';

$productInfo = 'Workshop: ' . substr($workshopTitle, 0, 80);
$udf1 = 'doctor_workshop';
$udf2 = (string)$workshopId;
$udf3 = (string)$doctorId;
$udf4 = substr($doctorName, 0, 50);
$udf5 = json_encode(['role' => 'doctor', 'ws_id' => $workshopId]);

if (!$config['is_configured']) {
    sendPayUResponse(false, 'PayU credentials are not configured in App Service environment variables. Please add PAYU_MERCHANT_KEY and PAYU_SALT.', [
        'payu_configured' => false,
        'environment_keys_needed' => [
            'PAYU_MERCHANT_KEY' => 'Your PayU Merchant Key',
            'PAYU_SALT' => 'Your PayU Merchant Salt',
            'PAYU_ENV' => 'test or prod'
        ],
        'details' => [
            'workshop_id' => $workshopId,
            'title' => $workshopTitle,
            'amount' => $formattedAmount
        ]
    ], 200);
}

$hashParams = [
    'key' => $config['merchant_key'],
    'txnid' => $txnId,
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

$payuHash = generatePayUHash($hashParams, $config['salt']);

// Save pending state in session
$_SESSION['last_payu_txnid'] = $txnId;
if (!isset($_SESSION['payu_pending']) || !is_array($_SESSION['payu_pending'])) {
    $_SESSION['payu_pending'] = [];
}
$_SESSION['payu_pending'][$txnId] = [
    'service_type' => 'doctor_workshop',
    'workshop_id' => $workshopId,
    'doctor_id' => $doctorId,
    'doctor_name' => $doctorName,
    'doctor_email' => $doctorEmail,
    'doctor_phone' => $doctorPhone,
    'amount' => $amount,
    'item_title' => $productInfo
];

$cbParams = http_build_query([
    'txnid' => $txnId,
    'service' => 'doctor_workshop',
    'amt' => $formattedAmount
]);
$surlWithParams = $config['success_url'] . (strpos($config['success_url'], '?') === false ? '?' : '&') . $cbParams;
$furlWithParams = $config['failure_url'] . (strpos($config['failure_url'], '?') === false ? '?' : '&') . $cbParams;

$payuPayload = [
    'key' => $config['merchant_key'],
    'txnid' => $txnId,
    'amount' => $formattedAmount,
    'productinfo' => $productInfo,
    'firstname' => $firstName,
    'email' => $doctorEmail,
    'phone' => $doctorPhone,
    'surl' => $surlWithParams,
    'furl' => $furlWithParams,
    'hash' => $payuHash,
    'service_provider' => 'payu_paisa',
    'udf1' => $udf1,
    'udf2' => $udf2,
    'udf3' => $udf3,
    'udf4' => $udf4,
    'udf5' => $udf5
];

sendPayUResponse(true, 'PayU payment session initialized.', [
    'payu_configured' => true,
    'action_url' => $config['endpoint'],
    'txnid' => $txnId,
    'amount' => $formattedAmount,
    'productinfo' => $productInfo,
    'mode' => $config['mode'],
    'params' => $payuPayload
], 200);
