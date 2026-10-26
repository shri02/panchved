<?php
/**
 * Ocayur Doctor Portal - Get Workshops API
 * Fetches "My Workshops" (registered only) and "All Workshops" from MySQL
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once __DIR__ . '/database.php';

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

$type = trim((string)($input['type'] ?? ($_GET['type'] ?? 'my_workshops')));

$doctorId = intval(
    $input['doctor_id'] ?? $input['id'] ?? $input['d_id'] ??
    $_GET['doctor_id'] ?? $_GET['id'] ?? $_GET['d_id'] ??
    $_SESSION['doctor_id'] ?? 0
);

$email = trim((string)(
    $input['email'] ?? $input['user_email'] ?? $input['d_email'] ?? $input['email_addr'] ??
    $_GET['email'] ?? $_GET['user_email'] ?? $_GET['d_email'] ?? $_GET['email_addr'] ??
    $_SESSION['email'] ?? ''
));

$phone = trim((string)(
    $input['phone'] ?? $input['user_phone'] ?? $input['d_phone'] ?? $input['phone_no'] ??
    $_GET['phone'] ?? $_GET['user_phone'] ?? $_GET['d_phone'] ?? $_GET['phone_no'] ??
    $_SESSION['phone_number'] ?? ''
));

function cleanPhone($phone) {
    $digits = preg_replace('/\D/', '', (string)$phone);
    if (strlen($digits) > 10 && (substr($digits, 0, 2) === '91')) {
        $digits = substr($digits, 2);
    }
    return $digits;
}

// If doctorId is not provided, try to find doctor from email or phone
if ($doctorId === 0 && (!empty($email) || !empty($phone))) {
    $cleanP = cleanPhone($phone);
    $stmt = mysqli_prepare($connection1, "SELECT `id` FROM `doctors` WHERE `email` = ? OR `phone_number` = ? OR `phone_number` = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sss", $email, $phone, $cleanP);
        mysqli_stmt_execute($stmt);
        $resDoc = mysqli_stmt_get_result($stmt);
        if ($rowDoc = mysqli_fetch_assoc($resDoc)) {
            $doctorId = intval($rowDoc['id']);
        }
        mysqli_stmt_close($stmt);
    }
}

function formatWorkshopDate($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00') return '3 September, Wednesday';
    $ts = strtotime($dateStr);
    if (!$ts) return '3 September, Wednesday';
    return date('j F, l', $ts);
}

function formatDetailDate($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00') return '2 September 2026';
    $ts = strtotime($dateStr);
    if (!$ts) return '2 September 2026';
    return date('j F Y', $ts);
}

$workshops = [];

if ($type === 'my_workshops') {
    // ONLY fetch workshops that have been registered by this doctor in workshop_bookings
    if ($doctorId > 0 || !empty($email) || !empty($phone)) {
        $cleanP = cleanPhone($phone);
        $query = "SELECT DISTINCT w.*, b.booking_id, b.booking_date 
                  FROM `workshops` w
                  INNER JOIN `workshop_bookings` b ON w.id = b.workshop_id
                  WHERE (b.doctor_id = ? OR (b.doctor_email = ? AND ? != '') OR (b.doctor_phone = ? AND ? != '') OR (b.doctor_phone = ? AND ? != '')) 
                    AND b.payment_status = 'Success'
                    AND (w.attendee_type = 'Doctor' OR w.attendee_type = 'Both' OR w.attendee_type = 'All' OR w.attendee_type IS NULL OR w.attendee_type = '')
                  ORDER BY b.id DESC";
        
        $stmt = mysqli_prepare($connection1, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "issssss", $doctorId, $email, $email, $phone, $phone, $cleanP, $cleanP);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $feeNum = floatval($row['fee'] > 0 ? $row['fee'] : ($row['price'] > 0 ? $row['price'] : 799.00));
                    $feeDisplay = "₹" . number_format($feeNum, 0, '', '') . "/- (One Day Workshop)";
                    
                    $workshops[] = [
                        'id' => intval($row['id']),
                        'workshop_id' => $row['workshop_id'],
                        'booking_id' => $row['booking_id'],
                        'title' => $row['title'],
                        'attendee_type' => $row['attendee_type'] ?? 'Doctor',
                        'speaker' => $row['speaker'] ?: $row['instructor'],
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
            }
            mysqli_stmt_close($stmt);
        }
    }
    // If no bookings exist for the doctor, $workshops is empty []

} else {
    // "all_workshops" tab: fetch all doctor workshops available for registration
    $query = "SELECT * FROM `workshops` WHERE `status` != 'Cancelled' AND (attendee_type = 'Doctor' OR attendee_type = 'Both' OR attendee_type = 'All' OR attendee_type IS NULL OR attendee_type = '') AND attendee_type != 'Patient' ORDER BY `id` ASC";
    $res = mysqli_query($connection1, $query);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $feeNum = floatval($row['fee'] > 0 ? $row['fee'] : ($row['price'] > 0 ? $row['price'] : 799.00));
            $feeDisplay = "₹" . number_format($feeNum, 0, '', '') . "/- (One Day Workshop)";
            
            $workshops[] = [
                'id' => intval($row['id']),
                'workshop_id' => $row['workshop_id'],
                'title' => $row['title'],
                'attendee_type' => $row['attendee_type'] ?? 'Doctor',
                'speaker' => $row['speaker'] ?: $row['instructor'],
                'speaker_role' => $row['speaker_role'] ?: 'Ayurveda Physician',
                'fee' => $feeNum,
                'fee_display' => $feeDisplay,
                'date' => formatWorkshopDate($row['date']),
                'raw_date' => $row['date'],
                'time' => $row['time'] ?: '8:00 AM',
                'duration' => $row['duration_text'] ?? $row['duration'] ?? '90 mins',
                'meet_link' => $row['meet_link'] ?: 'meet.google.com/abc-xyz-pqrs',
                'image_url' => $row['image_url'] ?: 'assets/courses/course_business.jpg',
                'about' => $row['about'],
                'status' => $row['status']
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'type' => $type,
    'count' => count($workshops),
    'workshops' => $workshops
]);
?>
