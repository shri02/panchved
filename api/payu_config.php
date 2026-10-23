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
                return $val;
            }
            if (isset($_ENV[$k]) && $_ENV[$k] !== '') {
                return $_ENV[$k];
            }
            if (isset($_SERVER[$k]) && $_SERVER[$k] !== '') {
                return $_SERVER[$k];
            }
        }
        return $default;
    }
}

if (!function_exists('getPayUBaseUrl')) {
    function getPayUBaseUrl() {
        // Priority 1: Check APP_URL / SITE_URL / BASE_URL, ignoring default Azure app service domains
        $envUrl = getPayUEnv(['APP_URL', 'SITE_URL', 'BASE_URL']);
        if (!empty($envUrl) && stripos($envUrl, 'azurewebsites.net') === false) {
            if (!preg_match('/^https?:\/\//i', $envUrl)) {
                $envUrl = 'https://' . $envUrl;
            }
            return rtrim($envUrl, '/');
        }

        // Priority 2: Check incoming request host from browser (if not Azure default domain or localhost)
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '');
        if (strpos($host, ',') !== false) {
            $hostParts = explode(',', $host);
            $host = trim($hostParts[0]);
        }
        if (!empty($host) && stripos($host, 'azurewebsites.net') === false && stripos($host, 'localhost') === false) {
            $isHttps = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') ||
                       (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
                       (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            $scheme = $isHttps ? 'https' : 'http';

            $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $apiPos = strrpos($scriptPath, '/api/');
            if ($apiPos !== false) {
                $basePath = substr($scriptPath, 0, $apiPos);
                return $scheme . '://' . $host . ($basePath ? '/' . ltrim($basePath, '/') : '');
            }

            return $scheme . '://' . $host;
        }

        // Priority 3: Fallback explicitly to official Doctor Portal domain
        return 'https://doctor.ocayur.com';
    }
}

if (!function_exists('getPayUConfig')) {
    function getPayUConfig() {
        $merchantKey = getPayUEnv(['PAYU_MERCHANT_KEY', 'PAYU_KEY', 'PAYU_MERCHANT_ID'], '');
        $salt = getPayUEnv(['PAYU_SALT', 'PAYU_MERCHANT_SALT'], '');
        $mode = strtolower(getPayUEnv(['PAYU_ENV', 'PAYU_MODE'], 'test'));
        $isLive = in_array($mode, ['prod', 'production', 'live']);

        $baseUrl = getPayUBaseUrl();

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

        $hashSequence = "{$key}|{$txnid}|{$amount}|{$productinfo}|{$firstname}|{$email}|{$udf1}|{$udf2}|{$udf3}|{$udf4}|{$udf5}||||||{$salt}";
        return strtolower(hash('sha512', $hashSequence));
    }
}

if (!function_exists('verifyPayUResponseHash')) {
    function verifyPayUResponseHash($postData, $salt) {
        $receivedHash = trim((string)($postData['hash'] ?? ''));
        if (empty($receivedHash)) {
            return false;
        }

        $key = trim((string)($postData['key'] ?? ''));
        $txnid = trim((string)($postData['txnid'] ?? ''));
        $amount = sprintf("%.2f", (float)($postData['amount'] ?? 0));
        $productinfo = trim((string)($postData['productinfo'] ?? ''));
        $firstname = trim((string)($postData['firstname'] ?? ''));
        $email = trim((string)($postData['email'] ?? ''));
        $status = trim((string)($postData['status'] ?? ''));
        $udf1 = trim((string)($postData['udf1'] ?? ''));
        $udf2 = trim((string)($postData['udf2'] ?? ''));
        $udf3 = trim((string)($postData['udf3'] ?? ''));
        $udf4 = trim((string)($postData['udf4'] ?? ''));
        $udf5 = trim((string)($postData['udf5'] ?? ''));

        $additionalCharges = isset($postData['additionalCharges']) ? trim((string)$postData['additionalCharges']) : '';

        if (!empty($additionalCharges)) {
            $hashSequence = "{$additionalCharges}|{$salt}|{$status}||||||{$udf5}|{$udf4}|{$udf3}|{$udf2}|{$udf1}|{$email}|{$firstname}|{$productinfo}|{$amount}|{$txnid}|{$key}";
        } else {
            $hashSequence = "{$salt}|{$status}||||||{$udf5}|{$udf4}|{$udf3}|{$udf2}|{$udf1}|{$email}|{$firstname}|{$productinfo}|{$amount}|{$txnid}|{$key}";
        }

        $calculatedHash = strtolower(hash('sha512', $hashSequence));
        return hash_equals($calculatedHash, strtolower($receivedHash));
    }
}
