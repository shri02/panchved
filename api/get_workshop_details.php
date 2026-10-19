<?php
/**
 * Ocayur Doctor Portal - Get Workshop Details API
 * Fetches single workshop details dynamically from MySQL database based on schema.sql
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

$id = intval($_GET['id'] ?? 1);
$code = trim($_GET['workshop_id'] ?? '');

$workshop = null;

if ($id > 0) {
    $stmt = mysqli_prepare($connection1, "SELECT * FROM `workshops` WHERE `id` = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $workshop = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
} elseif (!empty($code)) {
    $stmt = mysqli_prepare($connection1, "SELECT * FROM `workshops` WHERE `workshop_id` = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $code);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $workshop = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
}

if (!$workshop) {
    $res = mysqli_query($connection1, "SELECT * FROM `workshops` WHERE `status` != 'Cancelled' AND (attendee_type = 'Doctor' OR attendee_type = 'Both' OR attendee_type = 'All' OR attendee_type IS NULL OR attendee_type = '') AND attendee_type != 'Patient' ORDER BY `id` ASC LIMIT 1");
    if ($res) {
        $workshop = mysqli_fetch_assoc($res);
    }
}

function formatDetailDate($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00') return '2 September 2026';
    $ts = strtotime($dateStr);
    if (!$ts) return '2 September 2026';
    return date('j F Y', $ts);
}

function getInitials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach ($parts as $p) {
        $clean = preg_replace('/[^a-zA-Z]/', '', $p);
        if ($clean && strtolower($clean) !== 'dr') {
            $initials .= strtoupper($clean[0]);
        }
    }
    return substr($initials, 0, 2) ?: 'IN';
}

$speaker = $workshop['speaker'] ?: ($workshop['instructor'] ?: 'Instructor');
$feeNum = floatval($workshop['fee'] > 0 ? $workshop['fee'] : ($workshop['price'] > 0 ? $workshop['price'] : 5999.00));
$durationText = trim($workshop['duration_text'] ?? '');

if (stripos($durationText, 'month') !== false || $feeNum >= 1000) {
    $feeDisplay = "₹" . number_format($feeNum, 0, '', '') . " / month";
} else if ($durationText && stripos($durationText, 'min') === false) {
    $feeDisplay = "₹" . number_format($feeNum, 0, '', '') . " / " . $durationText;
} else {
    $feeDisplay = "₹" . number_format($feeNum, 0, '', '');
}

// Dynamic subtitle from short_description or about text
$subtitle = $workshop['short_description'] ?? $workshop['subtitle'] ?? '';
if (empty($subtitle) && !empty($workshop['about'])) {
    $sentences = explode('.', $workshop['about']);
    $subtitle = trim($sentences[0]) . '.';
}
if (empty($subtitle)) {
    $subtitle = 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit.';
}

echo json_encode([
    'success' => true,
    'workshop' => [
        'id' => intval($workshop['id'] ?? 1),
        'workshop_id' => $workshop['workshop_id'] ?? 'WS-001',
        'title' => $workshop['title'] ?? 'Mindset For Business World Of Ayurveda',
        'attendee_type' => $workshop['attendee_type'] ?? 'Doctor',
        'checkout_title' => $workshop['checkout_title'] ?? $workshop['title'] ?? 'Mindset For Business World Of Ayurveda',
        'subtitle' => $subtitle,
        'speaker_name' => $speaker,
        'speaker_role' => $workshop['speaker_role'] ?: 'Ayurveda Physician',
        'speaker_initials' => getInitials($speaker),
        'speaker_bio' => $workshop['speaker_bio'] ?: 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu.',
        'date' => formatDetailDate($workshop['date']),
        'raw_date' => $workshop['date'],
        'time' => $workshop['time'] ?: '8:00 AM',
        'duration' => $durationText ?: '90 mins',
        'fee' => $feeNum,
        'fee_display' => $feeDisplay,
        'about' => $workshop['about'] ?: 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet.',
        'image_url' => $workshop['image_url'] ?: 'assets/courses/course_business.jpg',
        'meet_link' => $workshop['meet_link'] ?: 'meet.google.com/abc-xyz-pqrs',
        'status' => $workshop['status'] ?: 'Upcoming'
    ]
]);
?>

