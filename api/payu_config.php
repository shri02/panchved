<?php
/**
 * Ocayur / Panchved - PayU Payment Gateway Configuration & Hash Utilities
 * 
 * Reads credentials dynamically from Azure App Service Environment Variables:
 * - PAYU_ENV: 'test' (default) or 'prod'
 * - PAYU_MERCHANT_KEY: Test or Live Merchant Key
 * - PAYU_SALT: Test or Live Merchant Salt
 */

if (!function_exists('getPayUEnv')) {
    function getPayUEnv($varNames, $default = '') {
        if (!is_array($varNames)) {
            $varNames = [$varNames];
        }
        foreach ($varNames as $name) {
            $val = getenv($name);
            if ($val !== false && trim((string)$val) !== '') {
                return trim((string)$val);
            }
            if (isset($_ENV[$name]) && trim((string)$_ENV[$name]) !== '') {
                return trim((string)$_ENV[$name]);
            }
            if (isset($_SERVER[$name]) && trim((string)$_SERVER[$name]) !== '') {
                return trim((string)$_SERVER[$name]);
            }
        }
        return $default;
    }
}

if (!function_exists('getPayUBaseUrl')) {
    function getPayUBaseUrl($clientOrigin = '') {
        // Priority 1: Explicit URL override
        $explicit = getPayUEnv(['PAYU_BASE_URL', 'APP_URL', 'SITE_URL', 'BASE_URL']);
        if (!empty($explicit) && preg_match('/^https?:\/\//i', $explicit)) {
            return rtrim($explicit, '/');
        }

        // Priority 2: Client Origin passed from frontend (if valid URL)
        if (!empty($clientOrigin) && preg_match('/^https?:\/\//i', $clientOrigin)) {
            $parsed = parse_url($clientOrigin);
            if (!empty($parsed['host'])) {
                $cScheme = $parsed['scheme'] ?? 'https';
                $cHost = $parsed['host'] . (!empty($parsed['port']) ? ':' . $parsed['port'] : '');
                $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
                $apiPos = strrpos($scriptPath, '/api/');
                $baseFolder = ($apiPos !== false) ? substr($scriptPath, 0, $apiPos) : '';
                return rtrim($cScheme . '://' . $cHost . ($baseFolder ? '/' . ltrim($baseFolder, '/') : ''), '/');
            }
        }

        // Priority 3: Server HTTP Headers
        $azureHost = getPayUEnv(['WEBSITE_HOSTNAME']);
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? $azureHost);
        if (strpos($host, ',') !== false) {
            $parts = explode(',', $host);
            $host = trim($parts[0]);
        }
        if (empty($host)) {
            $host = $azureHost ?: 'localhost';
        }

        $hostOnly = preg_replace('/:\d+$/', '', $host);
        $isLocal = ($hostOnly === 'localhost' || $hostOnly === '127.0.0.1' || $hostOnly === '::1');

        $isHttps = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') ||
                   (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
                   (isset($_SERVER['HTTP_X_ARR_SSL'])) ||
                   (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
                   !$isLocal;

        $scheme = $isHttps ? 'https' : 'http';
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $apiPos = strrpos($scriptPath, '/api/');
        $baseFolder = ($apiPos !== false) ? substr($scriptPath, 0, $apiPos) : '';

        return rtrim($scheme . '://' . $host . ($baseFolder ? '/' . ltrim($baseFolder, '/') : ''), '/');
    }
}

if (!function_exists('getPayUConfig')) {
    function getPayUConfig($clientOrigin = '') {
        $merchantKey = trim((string)getPayUEnv(['PAYU_MERCHANT_KEY', 'PAYU_KEY', 'PAYU_MERCHANT_ID'], ''));
        $salt = trim((string)getPayUEnv(['PAYU_SALT', 'PAYU_MERCHANT_SALT'], ''));
        $mode = strtolower(getPayUEnv(['PAYU_ENV', 'PAYU_MODE'], 'test'));
        $isLive = in_array($mode, ['prod', 'production', 'live'], true);

        $baseUrl = getPayUBaseUrl($clientOrigin);
        $defaultCallback = $baseUrl . '/api/payu_response.php';

        $endpoint = $isLive 
            ? 'https://secure.payu.in/_payment' 
            : 'https://test.payu.in/_payment';

        return [
            'merchant_key' => $merchantKey,
            'salt' => $salt,
            'mode' => $isLive ? 'production' : 'test',
            'is_live' => $isLive,
            'endpoint' => $endpoint,
            'base_url' => $baseUrl,
            'success_url' => $defaultCallback,
            'failure_url' => $defaultCallback,
            'is_configured' => (!empty($merchantKey) && !empty($salt))
        ];
    }
}

/**
 * Generate PayU Outbound Request SHA-512 Hash
 * Formula: sha512(key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||SALT)
 */
if (!function_exists('generatePayUHash')) {
    function generatePayUHash($params, $salt) {
        $key = trim((string)($params['key'] ?? ''));
        $txnid = trim((string)($params['txnid'] ?? ''));
        $amount = sprintf("%.2f", (float)($params['amount'] ?? 0));
        $productinfo = trim((string)($params['productinfo'] ?? ''));
        $firstname = trim((string)($params['firstname'] ?? ''));
        $email = trim((string)($params['email'] ?? ''));
        $udf1 = trim((string)($params['udf1'] ?? ''));
        $udf2 = trim((string)($params['udf2'] ?? ''));
        $udf3 = trim((string)($params['udf3'] ?? ''));
        $udf4 = trim((string)($params['udf4'] ?? ''));
        $udf5 = trim((string)($params['udf5'] ?? ''));
        $salt = trim((string)$salt);

        $hashSequence = "{$key}|{$txnid}|{$amount}|{$productinfo}|{$firstname}|{$email}|{$udf1}|{$udf2}|{$udf3}|{$udf4}|{$udf5}||||||{$salt}";
        return strtolower(hash('sha512', $hashSequence));
    }
}

/**
 * Verify PayU Inbound Response SHA-512 Hash
 * Formula:
 * With additionalCharges:
 *   sha512(additionalCharges|SALT|status||||||udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key)
 * Without additionalCharges:
 *   sha512(SALT|status||||||udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key)
 */
if (!function_exists('verifyPayUResponseHash')) {
    function verifyPayUResponseHash($postData, $salt) {
        $receivedHash = strtolower(trim((string)($postData['hash'] ?? '')));
        if (empty($receivedHash)) {
            return false;
        }

        $key = trim((string)($postData['key'] ?? ''));
        $txnid = trim((string)($postData['txnid'] ?? ''));
        $rawAmount = trim((string)($postData['amount'] ?? ''));
        $formattedAmount = sprintf("%.2f", (float)($postData['amount'] ?? 0));
        $floatAmount = (string)(float)($postData['amount'] ?? 0);
        $productinfo = trim((string)($postData['productinfo'] ?? ''));
        $firstname = trim((string)($postData['firstname'] ?? ''));
        $email = trim((string)($postData['email'] ?? ''));
        $status = trim((string)($postData['status'] ?? ''));
        $udf1 = trim((string)($postData['udf1'] ?? ''));
        $udf2 = trim((string)($postData['udf2'] ?? ''));
        $udf3 = trim((string)($postData['udf3'] ?? ''));
        $udf4 = trim((string)($postData['udf4'] ?? ''));
        $udf5 = trim((string)($postData['udf5'] ?? ''));
        $salt = trim((string)$salt);

        $additionalCharges = isset($postData['additionalCharges']) ? trim((string)$postData['additionalCharges']) : '';

        // Test multiple variations of amount representation that PayU might send
        $amountFormats = array_unique([$rawAmount, $formattedAmount, $floatAmount]);

        foreach ($amountFormats as $amt) {
            if (!empty($additionalCharges)) {
                $hashSequence = "{$additionalCharges}|{$salt}|{$status}||||||{$udf5}|{$udf4}|{$udf3}|{$udf2}|{$udf1}|{$email}|{$firstname}|{$productinfo}|{$amt}|{$txnid}|{$key}";
            } else {
                $hashSequence = "{$salt}|{$status}||||||{$udf5}|{$udf4}|{$udf3}|{$udf2}|{$udf1}|{$email}|{$firstname}|{$productinfo}|{$amt}|{$txnid}|{$key}";
            }

            $calculatedHash = strtolower(hash('sha512', $hashSequence));
            if (hash_equals($calculatedHash, $receivedHash)) {
                return true;
            }
        }

        return false;
    }
}
