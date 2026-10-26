<?php
/**
 * Ocayur Doctor Portal - Get My Workshops API
 * Fetches workshops that have been registered/booked by the logged-in doctor
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once __DIR__ . '/database.php';

// Safe date formatter
function formatWorkshopDate($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00') return '3 September, Wednesday';
    $ts = strtotime($dateStr);
    if (!$ts) return '3 September, Wednesday';
    return date('j F, l', $ts);
}

function cleanPhone($phone) {
    $digits = preg_replace('/\D/', '', (string)$phone);
    if (strlen($digits) > 10 && (substr($digits, 0, 2) === '91')) {
        $digits = substr($digits, 2);
    }
    return $digits;
}

// Support JSON body, POST, and GET parameters
$input = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
    if (empty($input)) {
        $input = $_POST;
    }
}

$doctorId = 0;
if (!empty($input['doctor_id'])) {
    $doctorId = (int)$input['doctor_id'];
} elseif (!empty($input['id'])) {
    $doctorId = (int)$input['id'];
} elseif (!empty($input['d_id'])) {
    $doctorId = (int)$input['d_id'];
} elseif (!empty($_GET['doctor_id'])) {
    $doctorId = (int)$_GET['doctor_id'];
} elseif (!empty($_GET['id'])) {
    $doctorId = (int)$_GET['id'];
} elseif (!empty($_GET['d_id'])) {
    $doctorId = (int)$_GET['d_id'];
} elseif (!empty($_SESSION['doctor_id'])) {
    $doctorId = (int)$_SESSION['doctor_id'];
}

$rawEmail = $input['email'] ?? $input['user_email'] ?? $input['d_email'] ?? $input['email_addr'] ??
    $_GET['email'] ?? $_GET['user_email'] ?? $_GET['d_email'] ?? $_GET['email_addr'] ??
    ($_SESSION['email'] ?? '');
$email = trim((string)$rawEmail);

$rawPhone = $input['phone'] ?? $input['user_phone'] ?? $input['d_phone'] ?? $input['phone_no'] ??
    $_GET['phone'] ?? $_GET['user_phone'] ?? $_GET['d_phone'] ?? $_GET['phone_no'] ??
    ($_SESSION['phone_number'] ?? '');
$phone = trim((string)$rawPhone);

// Diagnostic response container
$debugInfo = [
    'doctor_id' => $doctorId,
    'email' => $email,
    'phone' => $phone,
    'steps' => []
];

$workshops = [];

try {
    if (!$connection1) {
        throw new Exception("Database connection is not initialized.");
    }

    $debugInfo['steps'][] = "Database connected successfully";

    // Check if workshop_bookings table exists
    $tableCheck = @mysqli_query($connection1, "SHOW TABLES LIKE 'workshop_bookings'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        $debugInfo['steps'][] = "Table workshop_bookings does not exist yet";
        echo json_encode([
            'success' => true,
            'count' => 0,
            'workshops' => [],
            'debug' => $debugInfo,
            'message' => 'Table workshop_bookings not found. Please verify schema.'
        ]);
        exit();
    }
    $debugInfo['steps'][] = "Table workshop_bookings exists";

    // If doctorId is missing, check doctors table
    if ($doctorId === 0 && (!empty($email) || !empty($phone))) {
        $cleanP = cleanPhone($phone);
        $escapedEmail = mysqli_real_escape_string($connection1, $email);
        $escapedPhone = mysqli_real_escape_string($connection1, $phone);
        $escapedCleanP = mysqli_real_escape_string($connection1, $cleanP);

        $docQ = "SELECT `id` FROM `doctors` WHERE `email` = '$escapedEmail' OR `phone_number` = '$escapedPhone' OR `phone_number` = '$escapedCleanP' LIMIT 1";
        $resDoc = @mysqli_query($connection1, $docQ);
        if ($resDoc && $rowDoc = mysqli_fetch_assoc($resDoc)) {
            $doctorId = (int)$rowDoc['id'];
            $debugInfo['steps'][] = "Found doctor ID $doctorId from doctors table";
        } else {
            $debugInfo['steps'][] = "Doctor not found in doctors table";
        }
    }

    // Now query enrolled workshops
    $cleanP = cleanPhone($phone);
    $conditions = [];

    if ($doctorId > 0) {
        $conditions[] = "b.doctor_id = " . intval($doctorId);
    }
    if (!empty($email)) {
        $conditions[] = "b.doctor_email = '" . mysqli_real_escape_string($connection1, $email) . "'";
    }
    if (!empty($phone)) {
        $conditions[] = "b.doctor_phone = '" . mysqli_real_escape_string($connection1, $phone) . "'";
    }
    if (!empty($cleanP)) {
        $conditions[] = "b.doctor_phone = '" . mysqli_real_escape_string($connection1, $cleanP) . "'";
    }

    if (empty($conditions)) {
        $debugInfo['steps'][] = "No doctor credentials provided; returning empty bookings";
    } else {
        $whereMatch = "(" . implode(" OR ", $conditions) . ")";
        $query = "SELECT DISTINCT w.*, b.booking_id, b.booking_date 
                  FROM `workshops` w
                  INNER JOIN `workshop_bookings` b ON w.id = b.workshop_id
                  WHERE $whereMatch
                    AND b.payment_status = 'Success'
                    AND (w.attendee_type = 'Doctor' OR w.attendee_type = 'Both' OR w.attendee_type = 'All' OR w.attendee_type IS NULL OR w.attendee_type = '')
                  ORDER BY b.id DESC";

        $debugInfo['query'] = $query;
        $res = @mysqli_query($connection1, $query);

        if (!$res) {
            $debugInfo['mysql_error'] = mysqli_error($connection1);
            $debugInfo['mysql_errno'] = mysqli_errno($connection1);
            throw new Exception("Query execution failed: " . mysqli_error($connection1));
        }

        while ($row = mysqli_fetch_assoc($res)) {
            $feeNum = floatval($row['fee'] > 0 ? $row['fee'] : ($row['price'] > 0 ? $row['price'] : 799.00));
            $feeDisplay = "₹" . number_format($feeNum, 0, '', '') . "/- (One Day Workshop)";

            $workshops[] = [
                'id' => intval($row['id']),
                'workshop_id' => $row['workshop_id'],
                'booking_id' => $row['booking_id'],
                'title' => $row['title'],
                'attendee_type' => $row['attendee_type'] ?? 'Doctor',
                'speaker' => $row['speaker'] ?: ($row['instructor'] ?? 'Ayurveda Physician'),
                'speaker_role' => $row['speaker_role'] ?: 'Ayurveda Physician',
                'fee' => $feeNum,
                'fee_display' => $feeDisplay,
                'date' => formatWorkshopDate($row['date']),
                'raw_date' => $row['date'],
                'time' => $row['time'] ?: '8:00 AM',
                'duration' => $row['duration_text'] ?? $row['duration'] ?? '90 mins',
                'meet_link' => $row['meet_link'] ?: 'meet.google.com/abc-xyz-pqrs',
                'image_url' => $row['image_url'] ?: 'assets/courses/course_business.jpg',
                'about' => $row['about'] ?: 'Comprehensive clinical Ayurveda workshop.',
                'status' => $row['status']
            ];
        }
        $debugInfo['steps'][] = "Found " . count($workshops) . " registered workshops";
    }

    echo json_encode([
        'success' => true,
        'count' => count($workshops),
        'workshops' => $workshops,
        'debug' => $debugInfo
    ]);

} catch (Throwable $e) {
    // Return clean 200 JSON with error payload so Azure never turns it into a 404
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'error_caught' => true,
        'error_message' => $e->getMessage(),
        'error_file' => basename($e->getFile()),
        'error_line' => $e->getLine(),
        'debug' => $debugInfo
    ]);
}
