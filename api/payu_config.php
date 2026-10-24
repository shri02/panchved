<?php
/**
 * Ocayur Doctor Portal - PayU Payment Gateway Configuration
 * 
 * Supports Azure App Service Environment Variables (Application Settings):
 * - PAYU_MERCHANT_KEY (or PAYU_KEY)
 * - PAYU_SALT
 * - PAYU_ENV (or PAYU_MODE: 'test' / 'prod' / 'live' - defaults to 'test')
 * - PAYU_SUCCESS_URL (optional override for success callback)
 * - PAYU_FAILURE_URL (optional override for failure callback)
 * - APP_URL (optional base URL override e.g. https://doctor.ocayur.com)
 */

if (!function_exists('getPayUEnv')) {
    function getPayUEnv($keys, $default = '') {
        foreach ((array)$keys as $k) {
            $val = getenv($k);
            if ($val !== false && $val !== '') {
                return trim($val);
            }
            if (isset($_ENV[$k]) && $_ENV[$k] !== '') {
                return trim($_ENV[$k]);
            }
            if (isset($_SERVER[$k]) && $_SERVER[$k] !== '') {
                return trim($_SERVER[$k]);
            }
        }
        return $default;
    }
}

if (!function_exists('getPayUBaseUrl')) {
    function getPayUBaseUrl($clientOrigin = '') {
        // Priority 1: Check explicit environment variable (APP_URL / SITE_URL / BASE_URL)
        $envUrl = getPayUEnv(['APP_URL', 'SITE_URL', 'BASE_URL']);
        if (!empty($envUrl)) {
            if (!preg_match('/^https?:\/\//i', $envUrl)) {
                $envUrl = 'https://' . $envUrl;
            }
            return rtrim($envUrl, '/');
        }

        // Priority 2: Use client origin passed from browser if available
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

        // Priority 3: Check HTTP Referer or Origin header
        $refOrOrigin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
        if (!empty($refOrOrigin) && preg_match('/^https?:\/\//i', $refOrOrigin)) {
            $parsed = parse_url($refOrOrigin);
            if (!empty($parsed['host'])) {
                $cScheme = $parsed['scheme'] ?? 'https';
                $cHost = $parsed['host'] . (!empty($parsed['port']) ? ':' . $parsed['port'] : '');
                
                $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
                $apiPos = strrpos($scriptPath, '/api/');
                $baseFolder = ($apiPos !== false) ? substr($scriptPath, 0, $apiPos) : '';
                return rtrim($cScheme . '://' . $cHost . ($baseFolder ? '/' . ltrim($baseFolder, '/') : ''), '/');
            }
        }

        // Priority 4: Dynamic detection from server host headers
        $azureHost = getPayUEnv(['WEBSITE_HOSTNAME']);
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? $azureHost);
        if (strpos($host, ',') !== false) {
            $hostParts = explode(',', $host);
            $host = trim($hostParts[0]);
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
        $isLive = in_array($mode, ['prod', 'production', 'live']);

        // In test mode, fallback to PayU official public sandbox credentials if not specified
        if (!$isLive) {
            if (empty($merchantKey)) {
                $merchantKey = 'gtKFFx';
            }
            if (empty($salt)) {
                $salt = 'eCwWELxi';
            }
        }

        $baseUrl = getPayUBaseUrl($clientOrigin);

        $defaultSuccessUrl = $baseUrl . '/api/payu_response.php';
        $defaultFailureUrl = $baseUrl . '/api/payu_response.php';

        $successUrl = getPayUEnv(['PAYU_SUCCESS_URL', 'PAYU_SURL'], $defaultSuccessUrl);
        $failureUrl = getPayUEnv(['PAYU_FAILURE_URL', 'PAYU_FURL'], $defaultFailureUrl);

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
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'is_configured' => (!empty($merchantKey) && !empty($salt) && $merchantKey !== 'YOUR_PAYU_KEY' && $salt !== 'YOUR_PAYU_SALT')
        ];
    }
}

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
