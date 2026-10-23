<?php
/**
 * SMTP Configuration for Gmail Service
 * 
 * Supports Azure App Service Environment Variables (Application Settings):
 * - SMTP_USERNAME (or GMAIL_USERNAME)
 * - SMTP_PASSWORD (or GMAIL_PASSWORD)
 * - SMTP_FROM_EMAIL
 * - SMTP_FROM_NAME
 * - SMTP_HOST (defaults to smtp.gmail.com)
 * - SMTP_PORT (defaults to 587)
 */

if (!function_exists('getSmtpEnv')) {
    function getSmtpEnv($keys, $default = '') {
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

$username = getSmtpEnv(['SMTP_USERNAME', 'GMAIL_USERNAME', 'SMTP_USER'], 'drnidhi@gmail.com');
$password = getSmtpEnv(['SMTP_PASSWORD', 'GMAIL_PASSWORD', 'GMAIL_APP_PASSWORD', 'SMTP_PASS'], 'your-app-password-here');
$fromEmail = getSmtpEnv(['SMTP_FROM_EMAIL', 'GMAIL_FROM_EMAIL'], $username);
$fromName = getSmtpEnv(['SMTP_FROM_NAME', 'GMAIL_FROM_NAME'], 'Ocayur Healthcare');

return [
    'host' => getSmtpEnv('SMTP_HOST', 'smtp.gmail.com'),
    'port' => (int)getSmtpEnv('SMTP_PORT', 587),
    'encryption' => getSmtpEnv('SMTP_ENCRYPTION', 'tls'),
    'username' => $username,
    'password' => $password,
    'from_email' => $fromEmail,
    'from_name' => $fromName,
    'timeout' => 15
];
