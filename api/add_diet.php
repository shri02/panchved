<?php
/**
 * Ocayur Doctor Portal - Add Diet Plan API
 * Saves patient diet recommendations, handles PDF diet chart uploads,
 * and maintains database records and JSON cache.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

// Support both FormData (multipart/form-data) and JSON payloads
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$input = is_array($jsonInput) ? $jsonInput : $_POST;

$patientId = intval($input['patient_id'] ?? ($_POST['patient_id'] ?? 0));
$notes = trim((string)($input['notes'] ?? ($_POST['notes'] ?? '')));

$doctorId = intval($_SESSION['user_id'] ?? $_SESSION['doctor_id'] ?? 0);
$doctorName = trim((string)($_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'Dr. Nidhi Jha'));

$uploadedPdfFileName = null;
$originalFileName = null;
$fileSizeBytes = 0;
$pdfRelPath = null;

// Handle PDF file upload if provided
if (isset($_FILES['diet_pdf']) && is_array($_FILES['diet_pdf']) && ($_FILES['diet_pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['diet_pdf'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the server limit.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the form limit.',
            UPLOAD_ERR_PARTIAL    => 'The file upload was only partially completed.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
        ];
        echo json_encode([
            'success' => false,
            'message' => $uploadErrors[$file['error']] ?? 'File upload failed.'
        ]);
        exit();
    }

    $originalFileName = basename($file['name']);
    $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

    if ($fileExtension !== 'pdf') {
        echo json_encode([
            'success' => false,
            'message' => 'Only PDF files (.pdf) are allowed.'
        ]);
        exit();
    }

    // Limit 15MB
    if ($file['size'] > 15 * 1024 * 1024) {
        echo json_encode([
            'success' => false,
            'message' => 'PDF file size exceeds maximum limit of 15MB.'
        ]);
        exit();
    }

    $uploadDir = __DIR__ . '/../uploads/diets';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $cleanBaseName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($originalFileName, PATHINFO_FILENAME));
    $uploadedPdfFileName = 'diet_' . ($patientId > 0 ? $patientId : 'p') . '_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6) . '.pdf';
    $targetPath = $uploadDir . '/' . $uploadedPdfFileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to move uploaded PDF to server directory.'
        ]);
        exit();
    }

    $fileSizeBytes = (int)filesize($targetPath);
    $pdfRelPath = 'uploads/diets/' . $uploadedPdfFileName;

    // Mirror copy to patient portal directories so both portals have immediate local access
    $patientCandidateDirs = [
        __DIR__ . '/../../panchved-patient/uploads/diets',
        __DIR__ . '/../../../panchved-patient/uploads/diets',
        dirname(__DIR__, 2) . '/panchved-patient/uploads/diets',
        dirname(__DIR__, 3) . '/panchved-patient/uploads/diets'
    ];
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $doc = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
        $patientCandidateDirs[] = $doc . '/panchved-patient/uploads/diets';
        $patientCandidateDirs[] = $doc . '/patient/uploads/diets';
        $patientCandidateDirs[] = $doc . '/../panchved-patient/uploads/diets';
        $patientCandidateDirs[] = $doc . '/../patient/uploads/diets';
    }

    foreach ($patientCandidateDirs as $pDir) {
        if (!is_dir($pDir)) {
            @mkdir($pDir, 0777, true);
        }
        if (is_dir($pDir)) {
            @copy($targetPath, $pDir . '/' . $uploadedPdfFileName);
        }
    }
}

// Validate that either notes or a PDF file was provided
if (empty($notes) && empty($uploadedPdfFileName)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please provide diet plan notes or attach a PDF chart.'
    ]);
    exit();
}

// Fetch patient name if patientId is provided
$patientName = 'Patient';
if ($patientId > 0) {
    $stmt = mysqli_prepare($connection1, "SELECT `full_name`, `patient_id` FROM `patients` WHERE `id` = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $patientId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $patientName = $row['full_name'];
            if (!empty($row['patient_id'])) {
                $patientCode = $row['patient_id'];
            }
        }
        mysqli_stmt_close($stmt);
    }
}

$now = date('Y-m-d H:i:s');

// 1. Insert into MySQL `patient_diets` table if the table exists
$tableCheck = mysqli_query($connection1, "SHOW TABLES LIKE 'patient_diets'");
$tableExists = ($tableCheck && mysqli_num_rows($tableCheck) > 0);

if ($tableExists && $patientId > 0) {
    $insStmt = mysqli_prepare($connection1, "INSERT INTO `patient_diets` (`patient_id`, `doctor_id`, `doctor_name`, `notes`, `pdf_path`, `file_name`, `file_size`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($insStmt) {
        mysqli_stmt_bind_param($insStmt, "iissssiss", $patientId, $doctorId, $doctorName, $notes, $pdfRelPath, $originalFileName, $fileSizeBytes, $now, $now);
        mysqli_stmt_execute($insStmt);
        mysqli_stmt_close($insStmt);
    }
}

// 2. Persist to synchronized JSON storage (accessible by both doctor portal and patient portal)
$jsonDir = __DIR__ . '/../uploads/diets';
if (!is_dir($jsonDir)) {
    @mkdir($jsonDir, 0777, true);
}
$jsonFile = $jsonDir . '/diets_data.json';
$allDiets = [];
if (file_exists($jsonFile)) {
    $existing = json_decode(file_get_contents($jsonFile), true);
    if (is_array($existing)) {
        $allDiets = $existing;
    }
}

$dietRecord = [
    'patient_id' => $patientId,
    'patient_name' => $patientName,
    'doctor_name' => $doctorName,
    'notes' => $notes,
    'has_pdf' => !empty($uploadedPdfFileName),
    'pdf_filename' => $uploadedPdfFileName,
    'pdf_url' => $pdfRelPath,
    'original_filename' => $originalFileName,
    'file_size' => $fileSizeBytes,
    'updated_at' => $now
];

$allDiets[(string)$patientId] = $dietRecord;
file_put_contents($jsonFile, json_encode($allDiets, JSON_PRETTY_PRINT));

// Mirror JSON file to patient portal directory
$patientPortalJsonDir = __DIR__ . '/../../panchved-patient/uploads/diets';
if (is_dir($patientPortalJsonDir)) {
    @file_put_contents($patientPortalJsonDir . '/diets_data.json', json_encode($allDiets, JSON_PRETTY_PRINT));
}

echo json_encode([
    'success' => true,
    'message' => "Diet plan successfully saved for {$patientName}!",
    'data' => [
        'patient_id' => $patientId,
        'patient_name' => $patientName,
        'notes' => $notes,
        'has_pdf' => !empty($uploadedPdfFileName),
        'pdf_url' => $pdfRelPath,
        'original_filename' => $originalFileName,
        'file_size' => $fileSizeBytes,
        'created_at' => $now
    ]
]);
?>
