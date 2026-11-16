<?php
/**
 * Ocayur / Panchved - Cashfree Payment Gateway Initiation Endpoint (Doctor Portal)
 * 
 * Creates an order via Cashfree PG Orders API (v2023-08-01) and returns payment_session_id
 * for opening the Cashfree web checkout.
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

function sendCashfreeInitResponse($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => (bool)$success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data), JSON_UNESCAPED_UNICODE);
    exit();
}

// Convert PHP warnings and notices to ErrorException so they don't corrupt JSON output
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        sendCashfreeInitResponse(false, 'Only POST requests are allowed.', [], 405);
    }

    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/cashfree_config.php';

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $clientOrigin = trim((string)($input['origin_url'] ?? ($input['client_origin'] ?? '')));
    $config = getCashfreeConfig($clientOrigin);

    if (!$config['is_configured']) {
        sendCashfreeInitResponse(false, 'Cashfree credentials not configured. Please ensure CASHFREE_APP_ID and CASHFREE_SECRET_KEY are set in Azure App Service Configuration.', [
            'is_configured' => false,
            'required_variables' => ['CASHFREE_APP_ID', 'CASHFREE_SECRET_KEY', 'CASHFREE_ENV']
        ], 500);
    }

    $serviceType = strtolower(trim((string)($input['service_type'] ?? 'workshop')));
    $workshopId = intval($input['workshop_id'] ?? ($input['item_id'] ?? ($input['id'] ?? 1)));
    $doctorId = intval($_SESSION['doctor_id'] ?? ($input['doctor_id'] ?? 1));
    $doctorName = trim((string)($input['doctor_name'] ?? ($_SESSION['doctor_name'] ?? ($_SESSION['full_name'] ?? 'Doctor'))));
    $doctorEmail = trim((string)($input['doctor_email'] ?? ($_SESSION['doctor_email'] ?? ($_SESSION['email'] ?? ''))));
    $doctorPhone = trim((string)($input['doctor_phone'] ?? ($_SESSION['doctor_phone'] ?? ($_SESSION['phone_number'] ?? ''))));
    $amount = isset($input['amount']) && is_numeric($input['amount']) ? (float)$input['amount'] : 0.00;
    $workshopTitle = trim((string)($input['workshop_title'] ?? ($input['title'] ?? 'Ayurveda Workshop')));

    // Fetch workshop details from database if available
    if (isset($connection1) && $connection1 && $workshopId > 0) {
        try {
            $stmt = @mysqli_prepare($connection1, "SELECT `id`, `title`, `price`, `fee` FROM `workshops` WHERE `id` = ? LIMIT 1");
            if ($stmt) {
                @mysqli_stmt_bind_param($stmt, "i", $workshopId);
                @mysqli_stmt_execute($stmt);
                $res = @mysqli_stmt_get_result($stmt);
                if ($row = @mysqli_fetch_assoc($res)) {
                    if (empty($input['workshop_title']) && !empty($row['title'])) {
                        $workshopTitle = $row['title'];
                    }
                    if ($amount <= 0) {
                        $amount = floatval(!empty($row['fee']) && $row['fee'] > 0 ? $row['fee'] : ($row['price'] ?? 500.00));
                    }
                }
                @mysqli_stmt_close($stmt);
            }
        } catch (mysqli_sql_exception $e) {
            // DB lookup warning fallback
        } catch (Throwable $e) {
            // Non-fatal fallback
        }
    }

    if ($amount <= 0) {
        $amount = 500.00;
    }
    $formattedAmount = round($amount, 2);

    // Sanitize customer details for Cashfree
    $cleanPhone = preg_replace('/[^\d]/', '', $doctorPhone);
    if (strlen($cleanPhone) === 12 && substr($cleanPhone, 0, 2) === '91') {
        $cleanPhone = substr($cleanPhone, 2);
    }
    if (strlen($cleanPhone) !== 10) {
        $cleanPhone = '9999999999';
    }

    if (empty($doctorEmail) || !filter_var($doctorEmail, FILTER_VALIDATE_EMAIL)) {
        $doctorEmail = 'doctor_' . ($doctorId > 0 ? $doctorId : 'user') . '@panchved.com';
    }

    $cleanName = preg_replace('/[^a-zA-Z0-9\s.]/', '', $doctorName);
    if (empty(trim($cleanName))) {
        $cleanName = 'Doctor';
    }

    // Cashfree order_id max length: 45 characters, alphanumeric, hyphen, underscore
    $orderId = 'CF_WKS_' . date('YmdHis') . '_' . mt_rand(1000, 9999);
    $customerId = 'doc_' . ($doctorId > 0 ? $doctorId : '1') . '_' . substr(md5($doctorEmail . $cleanPhone), 0, 8);
    $orderNote = 'Workshop: ' . substr(preg_replace('/[^a-zA-Z0-9\s_\-]/', '', $workshopTitle), 0, 80);

    // Build Cashfree API request payload
    $orderPayload = [
        'order_id' => $orderId,
        'order_amount' => $formattedAmount,
        'order_currency' => 'INR',
        'customer_details' => [
            'customer_id' => $customerId,
            'customer_name' => substr($cleanName, 0, 50),
            'customer_email' => $doctorEmail,
            'customer_phone' => $cleanPhone
        ],
        'order_meta' => [
            'return_url' => $config['callback_url'] . '?order_id={order_id}',
            'notify_url' => $config['notify_url']
        ],
        'order_note' => $orderNote,
        'order_tags' => [
            'service_type' => 'workshop',
            'item_id' => (string)$workshopId,
            'doctor_id' => (string)$doctorId,
            'doctor_name' => substr($cleanName, 0, 40)
        ]
    ];

    // Call Cashfree API to create the order
    $apiResult = cashfreeApiCall('/orders', 'POST', $orderPayload, $config);

    if (!$apiResult['success']) {
        $errorMsg = $apiResult['error'];
        $details = $apiResult['data'];
        if (is_array($details) && !empty($details['message'])) {
            $errorMsg = $details['message'];
        }
        sendCashfreeInitResponse(false, 'Cashfree Order Creation Failed: ' . $errorMsg, [
            'api_error' => $errorMsg,
            'api_response' => $details,
            'http_code' => $apiResult['http_code']
        ], 502);
    }

    $resData = $apiResult['data'];
    $paymentSessionId = $resData['payment_session_id'] ?? '';

    if (empty($paymentSessionId)) {
        sendCashfreeInitResponse(false, 'Cashfree did not return a payment_session_id.', [
            'api_response' => $resData
        ], 502);
    }

    // Pre-record in transactions table if available
    if (isset($connection1) && $connection1) {
        try {
            $ins = @mysqli_prepare($connection1, "INSERT INTO `payu_transactions` 
                (`txnid`, `service_type`, `item_id`, `item_title`, `patient_name`, `patient_email`, `patient_phone`, `amount`, `payment_method`, `payment_status`, `created_at`)
                VALUES (?, 'workshop', ?, ?, ?, ?, ?, ?, 'Cashfree', 'Pending', NOW())
                ON DUPLICATE KEY UPDATE `payment_status` = 'Pending', `amount` = VALUES(`amount`), `payment_method` = 'Cashfree'");
            if ($ins) {
                $amtVal = floatval($formattedAmount);
                $wsIdStr = (string)$workshopId;
                @mysqli_stmt_bind_param($ins, "ssssssd",
                    $orderId, $wsIdStr, $orderNote, $cleanName, $doctorEmail, $cleanPhone, $amtVal
                );
                @mysqli_stmt_execute($ins);
                @mysqli_stmt_close($ins);
            }
        } catch (mysqli_sql_exception $e) {
            // Continue even if pre-recording table has issue
        } catch (Throwable $txErr) {
            // Continue safely
        }
    }

    // Save pending state in session
    $_SESSION['last_cashfree_order_id'] = $orderId;
    if (!isset($_SESSION['cashfree_pending']) || !is_array($_SESSION['cashfree_pending'])) {
        $_SESSION['cashfree_pending'] = [];
    }
    $_SESSION['cashfree_pending'][$orderId] = [
        'service_type' => 'workshop',
        'workshop_id' => $workshopId,
        'item_id' => $workshopId,
        'item_title' => $orderNote,
        'doctor_id' => $doctorId,
        'doctor_name' => $cleanName,
        'doctor_email' => $doctorEmail,
        'doctor_phone' => $cleanPhone,
        'amount' => $formattedAmount,
        'payment_session_id' => $paymentSessionId
    ];

    sendCashfreeInitResponse(true, 'Payment initiated successfully.', [
        'payment_session_id' => $paymentSessionId,
        'order_id' => $orderId,
        'cf_environment' => $config['mode'], // "sandbox" or "production"
        'amount' => $formattedAmount,
        'return_url' => $config['callback_url'] . '?order_id=' . urlencode($orderId)
    ]);

} catch (mysqli_sql_exception $e) {
    sendCashfreeInitResponse(false, 'Database Error: ' . $e->getMessage(), [
        'error_type' => 'mysqli_sql_exception',
        'sqlstate' => method_exists($e, 'getSqlState') ? $e->getSqlState() : null,
        'error_code' => $e->getCode(),
        'error_details' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
} catch (Throwable $e) {
    sendCashfreeInitResponse(false, 'Server Error: ' . $e->getMessage(), [
        'error_type' => get_class($e),
        'error_code' => $e->getCode(),
        'error_details' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}
