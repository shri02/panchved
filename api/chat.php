<?php
/**
 * Ocayur Doctor Portal - One-Way / Simple Text Chat API with Read Receipts
 * Endpoint: /api/chat.php
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($requestMethod === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/database.php';

date_default_timezone_set('Asia/Kolkata');
if (isset($connection1) && $connection1) {
    mysqli_query($connection1, "SET time_zone = '+05:30'");
}

function sendJson($success, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data));
    exit();
}

// Check if chat_messages table exists
$chkTable = mysqli_query($connection1, "SHOW TABLES LIKE 'chat_messages'");
$hasTable = ($chkTable && mysqli_num_rows($chkTable) > 0);

if (!$hasTable) {
    sendJson(false, 'Database table `chat_messages` does not exist yet. Please execute chat_schema.sql on your database.', [
        'table_missing' => true,
        'sql_required' => "CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` INT(11) NOT NULL,
  `patient_id` INT(11) NOT NULL,
  `sender_type` ENUM('doctor', 'patient') NOT NULL,
  `message` TEXT NOT NULL,
  `is_seen` TINYINT(1) NOT NULL DEFAULT 0,
  `seen_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_pair` (`doctor_id`, `patient_id`, `created_at`),
  KEY `idx_unread` (`doctor_id`, `patient_id`, `sender_type`, `is_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ], 200);
}

// Parse raw input if POST
$postData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawPost = file_get_contents('php://input');
    $decodedPost = json_decode($rawPost, true);
    $postData = is_array($decodedPost) ? $decodedPost : $_POST;
}

// Resolve active Doctor ID
$doctorId = 0;
if (!empty($_GET['doctor_id'])) {
    $doctorId = (int)$_GET['doctor_id'];
} elseif (!empty($postData['doctor_id'])) {
    $doctorId = (int)$postData['doctor_id'];
} elseif (!empty($_POST['doctor_id'])) {
    $doctorId = (int)$_POST['doctor_id'];
} elseif (!empty($_SESSION['doctor_id'])) {
    $doctorId = (int)$_SESSION['doctor_id'];
} elseif (!empty($_SESSION['doctor_profile']['id'])) {
    $doctorId = (int)$_SESSION['doctor_profile']['id'];
}

// Fallback: check by doctor_name if sent
$docNameParam = trim($_GET['doctor_name'] ?? ($postData['doctor_name'] ?? ''));
if ($doctorId <= 0 && !empty($docNameParam)) {
    $qDocN = mysqli_query($connection1, "SELECT id FROM doctors WHERE full_name = '" . mysqli_real_escape_string($connection1, $docNameParam) . "' OR full_name LIKE '%" . mysqli_real_escape_string($connection1, $docNameParam) . "%' LIMIT 1");
    if ($qDocN && $dNR = mysqli_fetch_assoc($qDocN)) {
        $doctorId = (int)$dNR['id'];
    }
}

// Fallback to primary active doctor in DB if not found
if ($doctorId <= 0) {
    $qDoc = mysqli_query($connection1, "SELECT id, full_name, expertise FROM doctors WHERE status = 'Active' ORDER BY id ASC LIMIT 1");
    if ($qDoc && $docRow = mysqli_fetch_assoc($qDoc)) {
        $doctorId = (int)$docRow['id'];
        $doctorProfile = $docRow;
    } else {
        $doctorId = 1;
    }
}

// Fetch doctor details
$docInfo = null;
$stmtDoc = mysqli_prepare($connection1, "SELECT id, doctorid, full_name, expertise, area, email, phone_number FROM doctors WHERE id = ? LIMIT 1");
if ($stmtDoc) {
    mysqli_stmt_bind_param($stmtDoc, "i", $doctorId);
    mysqli_stmt_execute($stmtDoc);
    $resDoc = mysqli_stmt_get_result($stmtDoc);
    $docInfo = mysqli_fetch_assoc($resDoc);
    mysqli_stmt_close($stmtDoc);
}

if (!$docInfo) {
    $docInfo = [
        'id' => $doctorId,
        'doctorid' => 'DOC000001',
        'full_name' => 'Dr. Nidhi Jha',
        'expertise' => 'Ayurveda Physician'
    ];
}

// Helper: Format message dates for chat bubbles
function formatChatMessage($row) {
    $createdTs = strtotime($row['created_at']);
    $dateLabel = date('Y-m-d', $createdTs);
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    if ($dateLabel === $today) {
        $displayDate = 'Today';
    } elseif ($dateLabel === $yesterday) {
        $displayDate = 'Yesterday';
    } else {
        $displayDate = date('d M Y', $createdTs);
    }
    
    $seenAtDisplay = null;
    if (!empty($row['seen_at'])) {
        $seenTs = strtotime($row['seen_at']);
        $seenAtDisplay = date('g:i A', $seenTs);
    }

    return [
        'id' => (int)$row['id'],
        'doctor_id' => (int)$row['doctor_id'],
        'patient_id' => (int)$row['patient_id'],
        'sender_type' => $row['sender_type'], // 'doctor' or 'patient'
        'message' => $row['message'],
        'is_seen' => (int)$row['is_seen'],
        'seen_at' => $row['seen_at'],
        'seen_at_display' => $seenAtDisplay,
        'time' => date('g:i A', $createdTs),
        'date' => $displayDate,
        'raw_date' => $dateLabel,
        'created_at' => $row['created_at']
    ];
}

// ============================================================================
// 1. POST: Send Text Message (Doctor -> Patient)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true);
    if (!is_array($inputData)) {
        $inputData = $_POST;
    }

    $patientId = (int)($inputData['patient_id'] ?? 0);
    $messageText = trim((string)($inputData['message'] ?? ''));

    if ($patientId <= 0) {
        sendJson(false, 'Invalid patient ID specified.', [], 400);
    }
    if ($messageText === '') {
        sendJson(false, 'Message cannot be empty.', [], 400);
    }

    // Verify patient exists
    $patStmt = mysqli_prepare($connection1, "SELECT id, patient_id, full_name FROM patients WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($patStmt, "i", $patientId);
    mysqli_stmt_execute($patStmt);
    $patRes = mysqli_stmt_get_result($patStmt);
    $patientData = mysqli_fetch_assoc($patRes);
    mysqli_stmt_close($patStmt);

    if (!$patientData) {
        sendJson(false, 'Patient not found.', [], 404);
    }

    // Insert new message as 'doctor', initially is_seen = 0
    $insertQuery = "INSERT INTO `chat_messages` (`doctor_id`, `patient_id`, `sender_type`, `message`, `is_seen`, `created_at`) VALUES (?, ?, 'doctor', ?, 0, NOW())";
    $insStmt = mysqli_prepare($connection1, $insertQuery);
    if (!$insStmt) {
        sendJson(false, 'Failed to prepare message insertion: ' . mysqli_error($connection1), [], 500);
    }

    mysqli_stmt_bind_param($insStmt, "iis", $doctorId, $patientId, $messageText);
    $executed = mysqli_stmt_execute($insStmt);
    $newMsgId = mysqli_insert_id($connection1);
    mysqli_stmt_close($insStmt);

    if ($executed && $newMsgId > 0) {
        // Fetch inserted message
        $qFetch = mysqli_query($connection1, "SELECT * FROM `chat_messages` WHERE `id` = {$newMsgId} LIMIT 1");
        $newRow = mysqli_fetch_assoc($qFetch);

        sendJson(true, 'Message sent successfully. Read receipt will update when patient refreshes.', [
            'message_item' => formatChatMessage($newRow),
            'patient' => $patientData
        ]);
    } else {
        sendJson(false, 'Failed to send message.', [], 500);
    }
}

// ============================================================================
// 2. GET: Retrieve Chat Thread OR Patient Conversations List
// ============================================================================
$patientIdParam = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$action = trim($_GET['action'] ?? '');

// If specific patient is selected and action is not 'patients_list'
if ($action !== 'patients_list' && $patientIdParam > 0) {
    // 2.1 Fetch Patient Details
    $patStmt = mysqli_prepare($connection1, "SELECT id, patient_id, full_name, phone_number, package_name, status, gender, age FROM patients WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($patStmt, "i", $patientIdParam);
    mysqli_stmt_execute($patStmt);
    $patRes = mysqli_stmt_get_result($patStmt);
    $patientInfo = mysqli_fetch_assoc($patRes);
    mysqli_stmt_close($patStmt);

    if (!$patientInfo) {
        sendJson(false, 'Patient record not found.', [], 404);
    }

    // Generate Initials
    $nameParts = preg_split('/\s+/', trim($patientInfo['full_name']));
    $initials = '';
    foreach ($nameParts as $np) {
        if (!empty($np)) $initials .= strtoupper($np[0]);
    }
    $patientInfo['initials'] = substr($initials, 0, 2) ?: 'PT';

    // 2.2 READ RECEIPT UPDATE:
    // Mark all unseen messages sent by the PATIENT as seen by this DOCTOR
    $updateSeen = "UPDATE `chat_messages` 
                   SET `is_seen` = 1, `seen_at` = NOW() 
                   WHERE `doctor_id` = ? AND `patient_id` = ? AND `sender_type` = 'patient' AND `is_seen` = 0";
    if ($uStmt = mysqli_prepare($connection1, $updateSeen)) {
        mysqli_stmt_bind_param($uStmt, "ii", $doctorId, $patientIdParam);
        mysqli_stmt_execute($uStmt);
        mysqli_stmt_close($uStmt);
    }

    // 2.3 Fetch Chat Logs between this Doctor and Patient
    $chatQuery = "SELECT id, doctor_id, patient_id, sender_type, message, is_seen, seen_at, created_at 
                  FROM `chat_messages` 
                  WHERE `doctor_id` = ? AND `patient_id` = ? 
                  ORDER BY `created_at` ASC, `id` ASC";
    
    $messages = [];
    if ($stmtChat = mysqli_prepare($connection1, $chatQuery)) {
        mysqli_stmt_bind_param($stmtChat, "ii", $doctorId, $patientIdParam);
        mysqli_stmt_execute($stmtChat);
        $resChat = mysqli_stmt_get_result($stmtChat);
        while ($cRow = mysqli_fetch_assoc($resChat)) {
            $messages[] = formatChatMessage($cRow);
        }
        mysqli_stmt_close($stmtChat);
    }

    // Unread count from patient for badge display
    $unseenCount = 0;
    foreach ($messages as $m) {
        if ($m['sender_type'] === 'doctor' && $m['is_seen'] === 0) {
            $unseenCount++;
        }
    }

    sendJson(true, 'Chat logs loaded successfully', [
        'doctor' => $docInfo,
        'patient' => $patientInfo,
        'messages' => $messages,
        'messages_count' => count($messages),
        'doctor_unseen_by_patient_count' => $unseenCount
    ]);
}

// ============================================================================
// 2.4 If no patient_id or action=patients_list: Return list of patients with chat previews
// STRICT: Only return patients related to this doctor (via chats, appointments, or packages)
// ============================================================================
$patientsList = [];
$targetPatientId = isset($_GET['target_patient_id']) ? (int)$_GET['target_patient_id'] : (isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0);
$escapedDocName = mysqli_real_escape_string($connection1, $docInfo['full_name'] ?? '');

$whereClauses = [];
$whereClauses[] = "EXISTS (SELECT 1 FROM chat_messages cm WHERE cm.doctor_id = {$doctorId} AND cm.patient_id = p.id)";
if (!empty($escapedDocName)) {
    $whereClauses[] = "EXISTS (SELECT 1 FROM appointments a WHERE (a.doctor_id = {$doctorId} OR a.doctor_name = '{$escapedDocName}') AND (a.patient_id = p.id OR a.patient_name = p.full_name))";
    $whereClauses[] = "EXISTS (SELECT 1 FROM patient_packages pp WHERE (pp.doctor_id = {$doctorId} OR pp.doctor_name = '{$escapedDocName}') AND (pp.patient_id = p.id OR pp.patient_phone = p.phone_number))";
    $whereClauses[] = "EXISTS (SELECT 1 FROM recommended_packages rp WHERE (rp.doctor_id = {$doctorId} OR rp.doctor_name = '{$escapedDocName}') AND (rp.patient_id = p.id OR rp.patient_phone = p.phone_number))";
} else {
    $whereClauses[] = "EXISTS (SELECT 1 FROM appointments a WHERE a.doctor_id = {$doctorId} AND (a.patient_id = p.id OR a.patient_name = p.full_name))";
    $whereClauses[] = "EXISTS (SELECT 1 FROM patient_packages pp WHERE pp.doctor_id = {$doctorId} AND (pp.patient_id = p.id OR pp.patient_phone = p.phone_number))";
    $whereClauses[] = "EXISTS (SELECT 1 FROM recommended_packages rp WHERE rp.doctor_id = {$doctorId} AND (rp.patient_id = p.id OR rp.patient_phone = p.phone_number))";
}
if ($targetPatientId > 0) {
    $whereClauses[] = "p.id = {$targetPatientId}";
}

$pQuery = "SELECT id, patient_id, full_name, phone_number, package_name, status 
           FROM patients p 
           WHERE " . implode(" OR ", $whereClauses) . " 
           ORDER BY (
             SELECT COALESCE(MAX(created_at), '2000-01-01') 
             FROM chat_messages cm2 
             WHERE cm2.doctor_id = {$doctorId} AND cm2.patient_id = p.id
           ) DESC, p.id ASC";
$pRes = mysqli_query($connection1, $pQuery);

if ($pRes) {
    while ($pRow = mysqli_fetch_assoc($pRes)) {
        $pId = (int)$pRow['id'];

        // Initials
        $nameParts = preg_split('/\s+/', trim($pRow['full_name']));
        $inits = '';
        foreach ($nameParts as $np) {
            if (!empty($np)) $inits .= strtoupper($np[0]);
        }
        $pRow['initials'] = substr($inits, 0, 2) ?: 'PT';

        // Last message & unseen count for this patient with this doctor
        $lastMsgQuery = "SELECT message, sender_type, is_seen, created_at FROM `chat_messages` 
                         WHERE `doctor_id` = {$doctorId} AND `patient_id` = {$pId} 
                         ORDER BY `created_at` DESC, `id` DESC LIMIT 1";
        $lmRes = mysqli_query($connection1, $lastMsgQuery);
        $lastMsg = ($lmRes && mysqli_num_rows($lmRes) > 0) ? mysqli_fetch_assoc($lmRes) : null;

        // Unread incoming from patient
        $unseenQuery = "SELECT COUNT(*) as unread_count FROM `chat_messages` 
                        WHERE `doctor_id` = {$doctorId} AND `patient_id` = {$pId} AND `sender_type` = 'patient' AND `is_seen` = 0";
        $unRes = mysqli_query($connection1, $unseenQuery);
        $unreadCount = ($unRes && $uRow = mysqli_fetch_assoc($unRes)) ? (int)$uRow['unread_count'] : 0;

        $pRow['last_message'] = $lastMsg ? $lastMsg['message'] : 'No messages yet';
        $pRow['last_message_sender'] = $lastMsg ? $lastMsg['sender_type'] : '';
        $pRow['last_message_time'] = $lastMsg ? date('g:i A', strtotime($lastMsg['created_at'])) : '';
        $pRow['last_message_date'] = $lastMsg ? date('d M', strtotime($lastMsg['created_at'])) : '';
        $pRow['unread_count'] = $unreadCount;

        $patientsList[] = $pRow;
    }
}

sendJson(true, 'Patients conversations list loaded', [
    'doctor' => $docInfo,
    'patients' => $patientsList
]);
