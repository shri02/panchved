<?php
/**
 * Ocayur / Panchved - Cashfree Payment Gateway Configuration & API Utilities
 * 
 * Reads credentials dynamically from Azure App Service Environment Variables:
 * - CASHFREE_APP_ID (or CASHFREE_CLIENT_ID): Cashfree Merchant App ID / Client ID
 * - CASHFREE_SECRET_KEY (or CASHFREE_CLIENT_SECRET): Cashfree Merchant Secret Key
 * - CASHFREE_ENV (or CASHFREE_MODE): 'TEST' (default for sandbox) or 'PROD' (for production)
 * - CASHFREE_API_VERSION: Default '2023-08-01'
 */

if (!function_exists('getCashfreeEnv')) {
    function getCashfreeEnv($varNames, $default = '') {
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

if (!function_exists('getCashfreeBaseUrl')) {
    function getCashfreeBaseUrl($clientOrigin = '') {
        // Priority 1: Explicit URL override
        $explicit = getCashfreeEnv(['CASHFREE_BASE_URL', 'APP_URL', 'SITE_URL', 'BASE_URL']);
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

        // Priority 3: Server HTTP Headers / Azure Website Hostname
        $azureHost = getCashfreeEnv(['WEBSITE_HOSTNAME']);
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

if (!function_exists('getCashfreeConfig')) {
    function getCashfreeConfig($clientOrigin = '') {
        $appId = trim((string)getCashfreeEnv(['CASHFREE_APP_ID', 'CASHFREE_CLIENT_ID', 'CASHFREE_KEY', 'CF_APP_ID'], ''));
        $secretKey = trim((string)getCashfreeEnv(['CASHFREE_SECRET_KEY', 'CASHFREE_CLIENT_SECRET', 'CASHFREE_SECRET', 'CF_SECRET_KEY'], ''));
        
        $mode = strtolower(trim((string)getCashfreeEnv(['CASHFREE_ENV', 'CASHFREE_MODE', 'CF_ENV'], 'test')));
        $isLive = in_array($mode, ['prod', 'production', 'live'], true);

        $apiVersion = trim((string)getCashfreeEnv(['CASHFREE_API_VERSION'], '2023-08-01'));

        $baseUrl = getCashfreeBaseUrl($clientOrigin);
        $defaultCallback = $baseUrl . '/api/cashfree_response.php';
        $defaultWebhook = $baseUrl . '/api/cashfree_webhook.php';

        $apiBaseUrl = $isLive 
            ? 'https://api.cashfree.com/pg' 
            : 'https://sandbox.cashfree.com/pg';

        return [
            'app_id' => $appId,
            'secret_key' => $secretKey,
            'mode' => $isLive ? 'production' : 'sandbox',
            'is_live' => $isLive,
            'api_version' => $apiVersion,
            'api_base_url' => $apiBaseUrl,
            'orders_url' => $apiBaseUrl . '/orders',
            'base_url' => $baseUrl,
            'return_url' => $defaultCallback . '?order_id={order_id}',
            'callback_url' => $defaultCallback,
            'notify_url' => $defaultWebhook,
            'is_configured' => (!empty($appId) && !empty($secretKey))
        ];
    }
}

/**
 * Execute HTTP API request to Cashfree Payment Gateway
 * 
 * @param string $endpoint API path or full URL
 * @param string $method HTTP method (GET, POST, etc.)
 * @param array|null $payload Request data for POST/PUT
 * @param array|null $config Cashfree configuration array
 * @return array ['success' => bool, 'http_code' => int, 'data' => mixed, 'error' => string]
 */
if (!function_exists('cashfreeApiCall')) {
    function cashfreeApiCall($endpoint, $method = 'GET', $payload = null, $config = null) {
        if (!$config) {
            $config = getCashfreeConfig();
        }

        $url = (strpos($endpoint, 'http') === 0) ? $endpoint : ($config['api_base_url'] . '/' . ltrim($endpoint, '/'));

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-api-version: ' . $config['api_version'],
            'x-client-id: ' . $config['app_id'],
            'x-client-secret: ' . $config['secret_key']
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($payload)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($payload) ? $payload : json_encode($payload));
            }
        } elseif (strtoupper($method) !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            if (!empty($payload)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($payload) ? $payload : json_encode($payload));
            }
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!empty($curlError)) {
            return [
                'success' => false,
                'http_code' => $httpCode ?: 500,
                'data' => null,
                'raw' => $rawResponse,
                'error' => 'cURL Error: ' . $curlError
            ];
        }

        $decoded = json_decode($rawResponse, true);
        $isOk = ($httpCode >= 200 && $httpCode < 300);

        return [
            'success' => $isOk,
            'http_code' => $httpCode,
            'data' => $decoded !== null ? $decoded : $rawResponse,
            'raw' => $rawResponse,
            'error' => $isOk ? '' : ($decoded['message'] ?? ('HTTP Error: ' . $httpCode))
        ];
    }
}
