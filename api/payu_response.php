<?php
/**
 * Ocayur Doctor Portal - PayU Payment Response & Webhook Handler
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/payu_config.php';

$postData = $_POST;
if (empty($postData)) {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $postData = $decoded;
        } else {
            parse_str($raw, $postData);
        }
    }
}
if (empty($postData)) {
    $postData = $_GET;
} else {
    foreach ($_GET as $gk => $gv) {
        if (!isset($postData[$gk]) || $postData[$gk] === '') {
            $postData[$gk] = $gv;
        }
    }
}

$config = getPayUConfig();
$salt = $config['salt'];

$txnid = trim((string)($postData['txnid'] ?? ($_GET['txnid'] ?? ($_SESSION['last_payu_txnid'] ?? ''))));
$pending = $_SESSION['payu_pending'][$txnid] ?? null;

$isSimulation = (!$config['is_live'] && isset($_GET['simulate_success']) && $_GET['simulate_success'] == '1');

$status = strtolower(trim((string)($postData['status'] ?? ($_GET['status'] ?? ''))));
if ($isSimulation || (empty($status) && isset($postData['unmappedstatus']) && in_array(strtolower($postData['unmappedstatus']), ['captured', 'success', 'successful']))) {
    $status = 'success';
}
if (empty($status)) {
    $status = 'failed';
}

$amount = floatval($postData['amount'] ?? ($_GET['amt'] ?? ($pending['amount'] ?? 0.00)));
$productInfo = trim((string)($postData['productinfo'] ?? ($pending['item_title'] ?? 'Ocayur Doctor Workshop')));
$firstName = trim((string)($postData['firstname'] ?? ($pending['doctor_name'] ?? 'Doctor')));
$email = trim((string)($postData['email'] ?? ($pending['doctor_email'] ?? '')));
$phone = trim((string)($postData['phone'] ?? ($pending['doctor_phone'] ?? '')));
$mihpayid = trim((string)($postData['mihpayid'] ?? ($isSimulation ? ('SIM_' . time()) : '')));
$bankRefNum = trim((string)($postData['bank_ref_num'] ?? ($isSimulation ? ('SIM_REF_' . rand(100000, 999999)) : '')));
$errorMsg = trim((string)(
    $postData['error'] 
    ?? ($postData['error_Message'] 
    ?? ($postData['errorMessage'] 
    ?? ($postData['field9'] 
    ?? ($postData['unmappedstatus'] ?? ''))))
));

$udf1 = trim((string)($postData['udf1'] ?? 'doctor_workshop'));
$udf2 = intval($postData['udf2'] ?? ($pending['workshop_id'] ?? 0)); // workshop_id
$udf3 = intval($postData['udf3'] ?? ($pending['doctor_id'] ?? 1)); // doctor_id
$udf4 = trim((string)($postData['udf4'] ?? ($pending['doctor_name'] ?? ''))); // doctor_name

$hashValid = false;
if (!empty($salt) && !empty($postData['hash'])) {
    $hashValid = verifyPayUResponseHash($postData, $salt);
}
if (!$hashValid && !$config['is_live']) {
    if ($isSimulation || $status === 'success' || (isset($postData['unmappedstatus']) && in_array(strtolower($postData['unmappedstatus']), ['captured', 'success']))) {
        $hashValid = true;
    }
}

$isSuccess = ($status === 'success' && $hashValid && !empty($txnid));
$bookingCode = '#' . rand(100000, 999999);
$formattedAmount = '₹' . number_format($amount, 2);

// Log PayU callback for diagnostics
@file_put_contents(
    __DIR__ . '/payu_debug.log',
    "[" . date('Y-m-d H:i:s') . "] METHOD=" . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . 
    " HTTPS=" . (isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'off') . 
    " PROTO=" . ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'none') . 
    " POST_COUNT=" . count($_POST) . 
    " TXNID=" . ($postData['txnid'] ?? 'empty') . 
    " STATUS=" . ($postData['status'] ?? 'empty') . 
    " HASH_VALID=" . ($hashValid ? 'true' : 'false') . 
    " RAW=" . json_encode($postData) . "\n",
    FILE_APPEND
);

if ($isSuccess && $connection1 && $udf2 > 0) {
    // Insert into workshop_bookings
    $insertStmt = mysqli_prepare($connection1, "INSERT INTO `workshop_bookings` 
        (`booking_id`, `workshop_id`, `doctor_id`, `doctor_name`, `doctor_email`, `doctor_phone`, `amount`, `payment_method`, `payment_status`)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'PayU', 'Success')");
    if ($insertStmt) {
        $docFullName = !empty($udf4) ? $udf4 : $firstName;
        mysqli_stmt_bind_param($insertStmt, "siisssd", 
            $bookingCode, $udf2, $udf3, $docFullName, $email, $phone, $amount
        );
        mysqli_stmt_execute($insertStmt);
        mysqli_stmt_close($insertStmt);
    }

    // Increment workshop registrations
    mysqli_query($connection1, "UPDATE `workshops` SET `registrations` = `registrations` + 1 WHERE `id` = $udf2");
}

$doctorBase = getPayUBaseUrl();
$redirectTarget = $doctorBase . '/courses/courses.html';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $isSuccess ? 'Payment Successful - Ocayur Doctor Portal' : 'Payment Incomplete - Ocayur Doctor Portal'; ?></title>
  <link rel="icon" type="image/png" href="../assets/favicon.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; margin: 0; }
    .card { background: #ffffff; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08); max-width: 480px; width: 100%; padding: 36px 28px; text-align: center; border: 1px solid #e2e8f0; }
    .badge { width: 68px; height: 68px; border-radius: 50%; margin: 0 auto 18px; display: flex; align-items: center; justify-content: center; }
    .badge.success { background: #ecfdf5; color: #10b981; }
    .badge.failed { background: #fef2f2; color: #ef4444; }
    h1 { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
    p { font-size: 14px; color: #64748b; line-height: 1.5; margin-bottom: 20px; }
    .box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 18px; margin-bottom: 24px; text-align: left; }
    .row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 13.5px; border-bottom: 1px dashed #e2e8f0; }
    .row:last-child { border-bottom: none; font-weight: 700; color: #0f766e; }
    .btn { display: block; width: 100%; padding: 13px 20px; background: #0f766e; color: #fff; text-decoration: none; font-weight: 600; font-size: 14.5px; border-radius: 10px; border: none; cursor: pointer; box-sizing: border-box; }
    .btn:hover { background: #115e59; }
  </style>
</head>
<body>
  <div class="card">
    <?php if ($isSuccess): ?>
      <div class="badge success">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      </div>
      <h1>Workshop Registration Confirmed!</h1>
      <p>Your payment of <strong><?php echo htmlspecialchars($formattedAmount); ?></strong> was received successfully.</p>
      <div class="box">
        <div class="row"><span>Booking ID</span><strong><?php echo htmlspecialchars($bookingCode); ?></strong></div>
        <div class="row"><span>Workshop</span><strong><?php echo htmlspecialchars($productInfo); ?></strong></div>
        <div class="row"><span>PayU Txn ID</span><strong><?php echo htmlspecialchars($txnid); ?></strong></div>
        <div class="row"><span>Amount</span><strong><?php echo htmlspecialchars($formattedAmount); ?></strong></div>
      </div>
      <a href="<?php echo htmlspecialchars($redirectTarget); ?>" class="btn">Return to Workshops</a>
    <?php else: ?>
      <div class="badge failed">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </div>
      <h1>Payment Not Completed</h1>
      <div style="font-size:14px; color:#4b5563; line-height:1.5; margin: 10px 0 20px 0;">
        <?php 
          if (!empty($errorMsg)) {
            if (stripos($errorMsg, 'circuit breaker') !== false || stripos($errorMsg, 'uat-onboarding') !== false) {
              echo '<strong style="color:#b91c1c;">PayU Sandbox Error:</strong> ' . htmlspecialchars($errorMsg) . '<br><br>' .
                   '<div style="font-size:12px; background:#f1f5f9; padding:10px; border-radius:6px; text-align:left;">' .
                   '• PayU Sandbox test server could not fetch salt for your merchant key.<br>' .
                   '• If you entered a Live Production Key with <code>PAYU_ENV=test</code>, PayU Sandbox cannot find it.<br>' .
                   '• Standard PayU Sandbox credentials: Key: <code>gtKFFx</code> | Salt: <code>eCwWELxi</code>.' .
                   '</div>';
            } else {
              echo htmlspecialchars($errorMsg);
            }
          } elseif (empty($postData) || empty($txnid)) {
            echo 'No payment transaction data was received from the gateway. If your payment was completed, please return to your portal.';
          } elseif (!$hashValid && !empty($salt)) {
            echo 'Security signature verification failed. Please contact support if your account was debited.';
          } else {
            echo 'The transaction was cancelled or declined.';
          }
        ?>
      </div>
      <?php if (!$config['is_live'] && !empty($txnid)): ?>
      <div style="margin: 0 0 16px 0; padding: 12px; background: #ecfdf5; border: 1px dashed #10b981; border-radius: 8px;">
        <div style="font-size: 12px; color: #047857; margin-bottom: 8px;">Developer Sandbox Mode:</div>
        <a href="<?php echo htmlspecialchars('payu_response.php?' . http_build_query([
          'simulate_success' => '1',
          'txnid' => $txnid,
          'service' => 'doctor_workshop',
          'amt' => sprintf('%.2f', $amount)
        ])); ?>" class="btn" style="background: #059669; padding: 8px 14px; font-size: 12px; display: inline-block;">
          ✓ Simulate Payment Success (Test Mode)
        </a>
      </div>
      <?php endif; ?>
      <a href="javascript:history.back()" class="btn">Try Again</a>
    <?php endif; ?>
  </div>
</body>
</html>
