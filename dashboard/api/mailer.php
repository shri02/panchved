<?php
/**
 * Standalone SMTP Mailer for Gmail (No external dependencies required)
 * Uses STARTTLS on port 587 or SSL on port 465 with Google App Passwords
 */

class SimpleSmtpMailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $timeout;

    public function __construct(array $config) {
        $this->host = $config['host'] ?? 'smtp.gmail.com';
        $this->port = (int)($config['port'] ?? 587);
        $this->username = trim($config['username'] ?? '');
        $this->password = str_replace(' ', '', trim($config['password'] ?? ''));
        $this->fromEmail = trim($config['from_email'] ?? $this->username);
        $this->fromName = $config['from_name'] ?? 'Ocayur Healthcare';
        $this->timeout = (int)($config['timeout'] ?? 15);
    }

    /**
     * Send an HTML email via SMTP
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $htmlBody HTML content
     * @param string|null &$error Out parameter for error message
     * @return bool True on success, false on failure
     */
    public function send($to, $subject, $htmlBody, &$error = null): bool {
        $to = trim($to);

        if (empty($this->username) || empty($this->password) || strpos($this->username, '@') === false) {
            $error = "SMTP credentials not configured. Please enter your Gmail and 16-character App Password in smtp_config.php.";
            error_log("[Mailer Error] " . $error);
            return false;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid recipient email address.";
            return false;
        }

        $isSSL = ($this->port === 465);
        $protocol = $isSSL ? 'ssl://' : 'tcp://';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $socket = @stream_socket_client(
            $protocol . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            $error = "Could not connect to SMTP server: {$errstr} ({$errno})";
            error_log("[Mailer Error] " . $error);
            return false;
        }

        stream_set_timeout($socket, $this->timeout);

        // 1. Initial banner
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) !== '220') {
            $error = "Server did not accept connection: " . $response;
            fclose($socket);
            return false;
        }

        // 2. Initial EHLO
        $this->sendCommand($socket, "EHLO " . gethostname());
        $response = $this->readResponse($socket);

        // 3. STARTTLS for port 587
        if ($this->port === 587) {
            $this->sendCommand($socket, "STARTTLS");
            $response = $this->readResponse($socket);
            if (substr($response, 0, 3) !== '220') {
                $error = "STARTTLS failed: " . $response;
                fclose($socket);
                return false;
            }

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                $error = "TLS handshake negotiation failed.";
                fclose($socket);
                return false;
            }

            // EHLO again after TLS handshake
            $this->sendCommand($socket, "EHLO " . gethostname());
            $response = $this->readResponse($socket);
        }

        // 4. AUTH LOGIN
        $this->sendCommand($socket, "AUTH LOGIN");
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) !== '334') {
            $error = "AUTH LOGIN rejected: " . $response;
            fclose($socket);
            return false;
        }

        // Send Base64 Username
        $this->sendCommand($socket, base64_encode($this->username));
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) !== '334') {
            $error = "Username rejected: " . $response;
            fclose($socket);
            return false;
        }

        // Send Base64 Password
        $this->sendCommand($socket, base64_encode($this->password));
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) !== '235') {
            $error = "Gmail SMTP authentication failed. Please check your Gmail address and 16-character Google App Password.";
            error_log("[Mailer Error] " . $error . " Response: " . $response);
            fclose($socket);
            return false;
        }

        // 5. MAIL FROM
        $this->sendCommand($socket, "MAIL FROM:<" . $this->fromEmail . ">");
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) !== '250') {
            $error = "MAIL FROM rejected: " . $response;
            fclose($socket);
            return false;
        }

        // 6. RCPT TO
        $this->sendCommand($socket, "RCPT TO:<" . $to . ">");
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) !== '250') {
            $error = "RCPT TO rejected: " . $response;
            fclose($socket);
            return false;
        }

        // 7. DATA
        $this->sendCommand($socket, "DATA");
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) !== '354') {
            $error = "DATA command rejected: " . $response;
            fclose($socket);
            return false;
        }

        // 8. Construct MIME Email Headers & Body
        $headers = [];
        $headers[] = "Date: " . date('r');
        $headers[] = "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <" . $this->fromEmail . ">";
        $headers[] = "To: <" . $to . ">";
        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: base64";

        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $message .= ".\r\n";

        fwrite($socket, $message);
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) !== '250') {
            $error = "Sending message content failed: " . $response;
            fclose($socket);
            return false;
        }

        // 9. QUIT
        $this->sendCommand($socket, "QUIT");
        fclose($socket);
        return true;
    }

    private function sendCommand($socket, string $cmd): void {
        fwrite($socket, $cmd . "\r\n");
    }

    private function readResponse($socket): string {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            // Complete reply ends with code + space (e.g. "250 OK")
            if (strlen($line) >= 4 && substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return trim($data);
    }
}
