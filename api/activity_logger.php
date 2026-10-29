<?php
/**
 * Ocayur / Panchved - Unified Activity Logger Helper
 * Logs creation, update, deletion, status changes, and business events across the ecosystem.
 */

if (!function_exists('getClientIpAddress')) {
    function getClientIpAddress() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ipList = explode(',', $_SERVER[$key]);
                $ip = trim($ipList[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

if (!function_exists('getCurrentActor')) {
    function getCurrentActor() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        // 1. Check Admin session
        if (!empty($_SESSION['ocayur_user']) || !empty($_SESSION['panchved_user'])) {
            $admin = $_SESSION['ocayur_user'] ?? $_SESSION['panchved_user'];
            return [
                'type'  => 'Admin',
                'id'    => $admin['id'] ?? null,
                'name'  => $admin['full_name'] ?? ($admin['username'] ?? 'Admin'),
                'email' => $admin['email'] ?? null,
                'phone' => $admin['phone_number'] ?? null
            ];
        }
        // 2. Check Doctor session
        if (!empty($_SESSION['doctor_id']) || !empty($_SESSION['doctor_profile'])) {
            $doc = $_SESSION['doctor_profile'] ?? [];
            return [
                'type'  => 'Doctor',
                'id'    => $_SESSION['doctor_id'] ?? ($doc['id'] ?? null),
                'name'  => $doc['full_name'] ?? 'Doctor',
                'email' => $doc['email'] ?? null,
                'phone' => $doc['phone_number'] ?? null
            ];
        }
        // 3. Check Patient session
        if (!empty($_SESSION['user_id'])) {
            return [
                'type'  => 'Patient',
                'id'    => $_SESSION['user_id'] ?? null,
                'name'  => $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'Patient'),
                'email' => $_SESSION['email'] ?? null,
                'phone' => $_SESSION['phone_number'] ?? null
            ];
        }
        return [
            'type'  => 'System',
            'id'    => null,
            'name'  => 'System',
            'email' => null,
            'phone' => null
        ];
    }
}

if (!function_exists('recordActivity')) {
    /**
     * Record an audit activity into activity_logs table
     *
     * @param mysqli $conn Active MySQL database connection
     * @param array $payload Activity data
     * @return bool
     */
    function recordActivity($conn, array $payload) {
        if (!$conn) return false;

        try {
            date_default_timezone_set('Asia/Kolkata');
            @mysqli_query($conn, "SET time_zone = '+05:30'");

            $defaultActor = getCurrentActor();
            $actorType  = $payload['actor_type'] ?? $defaultActor['type'];
            $actorId    = isset($payload['actor_id']) && $payload['actor_id'] !== '' ? intval($payload['actor_id']) : $defaultActor['id'];
            $actorName  = substr(trim($payload['actor_name'] ?? $defaultActor['name']), 0, 150);
            $actorPhone = isset($payload['actor_phone']) ? substr(trim($payload['actor_phone']), 0, 30) : $defaultActor['phone'];
            $actorEmail = isset($payload['actor_email']) ? substr(trim($payload['actor_email']), 0, 150) : $defaultActor['email'];

            $module     = $payload['module'] ?? 'Account';
            $action     = substr(trim($payload['action'] ?? 'action.performed'), 0, 60);
            $entityType = substr(trim($payload['entity_type'] ?? ''), 0, 40);
            $entityId   = substr(trim((string)($payload['entity_id'] ?? '')), 0, 100);
            $entityName = isset($payload['entity_name']) ? substr(trim($payload['entity_name']), 0, 255) : null;

            $description = trim($payload['description'] ?? '');
            if ($description === '') {
                $description = "{$actorName} performed {$action} on {$entityType} {$entityId}";
            }
            $description = substr($description, 0, 500);

            $oldValues = isset($payload['old_values']) && $payload['old_values'] !== null
                ? json_encode($payload['old_values'], JSON_UNESCAPED_UNICODE)
                : null;
            $newValues = isset($payload['new_values']) && $payload['new_values'] !== null
                ? json_encode($payload['new_values'], JSON_UNESCAPED_UNICODE)
                : null;
            $metadata  = isset($payload['metadata']) && $payload['metadata'] !== null
                ? json_encode($payload['metadata'], JSON_UNESCAPED_UNICODE)
                : null;

            $ipAddress = getClientIpAddress();
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $createdAt = date('Y-m-d H:i:s');

            $query = "INSERT INTO `activity_logs` (
                `actor_type`, `actor_id`, `actor_name`, `actor_phone`, `actor_email`,
                `module`, `action`, `entity_type`, `entity_id`, `entity_name`,
                `description`, `old_values`, `new_values`, `metadata`,
                `ip_address`, `user_agent`, `created_at`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = @mysqli_prepare($conn, $query);
            if ($stmt) {
                mysqli_stmt_bind_param(
                    $stmt,
                    "sisssssssssssssss",
                    $actorType,
                    $actorId,
                    $actorName,
                    $actorPhone,
                    $actorEmail,
                    $module,
                    $action,
                    $entityType,
                    $entityId,
                    $entityName,
                    $description,
                    $oldValues,
                    $newValues,
                    $metadata,
                    $ipAddress,
                    $userAgent,
                    $createdAt
                );
                @mysqli_stmt_execute($stmt);
                @mysqli_stmt_close($stmt);
                return true;
            }
        } catch (Throwable $e) {
            // Silently suppress to prevent blocking core business operations
        }
        return false;
    }
}
?>
