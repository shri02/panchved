<?php
/**
 * Ocayur / Panchved - Cashfree Asynchronous Webhook Handler (Doctor Portal)
 * 
 * Receives webhook events from Cashfree (e.g., PAYMENT_SUCCESS_WEBHOOK)
 * and safely ensures MySQL booking fulfillment if redirect return did not fire.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/cashfree_config.php';
require_once __DIR__ . '/activity_logger.php';

set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    $rawPayload = file_get_contents('php://input');
    $payload = json_decode($rawPayload, true);

    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['status' => 'ignored', 'message' => 'Empty or non-JSON payload']);
        exit();
    }

    $eventType = strtoupper(trim((string)($payload['type'] ?? '')));
    $orderData = $payload['data']['order'] ?? [];
    $paymentData = $payload['data']['payment'] ?? [];

    $orderId = trim((string)($orderData['order_id'] ?? ''));
    $paymentStatus = strtoupper(trim((string)($paymentData['payment_status'] ?? '')));
    $cfPaymentId = (string)($paymentData['cf_payment_id'] ?? '');
    $bankRef = (string)($paymentData['bank_reference'] ?? '');

    if ($eventType === 'PAYMENT_SUCCESS_WEBHOOK' || $paymentStatus === 'SUCCESS') {
        if (!empty($orderId) && isset($connection1) && $connection1) {
            // Update transaction table
            $upd = @mysqli_prepare($connection1, "UPDATE `payu_transactions` 
                SET `payment_status` = 'Success', `mihpayid` = ?, `bank_ref_num` = ?, `payment_method` = 'Cashfree', `updated_at` = NOW() 
                WHERE `txnid` = ?");
            if ($upd) {
                @mysqli_stmt_bind_param($upd, "sss", $cfPaymentId, $bankRef, $orderId);
                @mysqli_stmt_execute($upd);
                @mysqli_stmt_close($upd);
            }
        }
    }

    http_response_code(200);
    echo json_encode(['status' => 'acknowledged', 'order_id' => $orderId]);
    exit();

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error_type' => 'mysqli_sql_exception',
        'sqlstate' => method_exists($e, 'getSqlState') ? $e->getSqlState() : null,
        'error_code' => $e->getCode(),
        'error_details' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error_type' => get_class($e),
        'error_code' => $e->getCode(),
        'error_details' => $e->getMessage()
    ]);
}
