<?php
/**
 * Ocayur Doctor Portal - Update Profile API
 * Updates doctor profile in both `doctors` and `users` tables
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/activity_logger.php';

function cleanPhone($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) > 10 && (substr($digits, 0, 2) === '91')) {
        $digits = substr($digits, 2);
    }
    return $digits;
}

// Read input (JSON or POST, with CLI stdin fallback)
$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    $rawInput = @file_get_contents('php://stdin');
}
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$doctorId = $input['doctor_id'] ?? $input['id'] ?? $_SESSION['doctor_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

$fullName = trim($input['fullName'] ?? $input['full_name'] ?? '');
$dob = trim($input['dateOfBirth'] ?? $input['date_of_birth'] ?? $input['dob'] ?? '');
$phone = trim($input['phoneNumber'] ?? $input['phone_number'] ?? $input['phone'] ?? '');
$gender = trim($input['gender'] ?? 'Male');
$email = trim($input['email'] ?? '');
$yoe = intval($input['yoe'] ?? $input['years_of_experience'] ?? 0);
$expertise = trim($input['expertise'] ?? '');
$area = trim($input['area'] ?? '');
$regNo = trim($input['registrationNumber'] ?? $input['registration_number'] ?? '');
$hprRegNo = trim($input['hprRegistrationNumber'] ?? $input['hpr_registration_number'] ?? '');

// Validation
if (empty($fullName)) {
    echo json_encode(['success' => false, 'message' => 'Full Name is required.']);
    exit();
}

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
    exit();
}

// Format DOB to MySQL YYYY-MM-DD
$formattedDOB = '1990-01-01';
if (!empty($dob)) {
    // If format is DD/MM/YYYY or DD-MM-YYYY
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dob, $matches)) {
        $formattedDOB = sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
    } else {
        $timestamp = strtotime($dob);
        if ($timestamp !== false) {
            $formattedDOB = date('Y-m-d', $timestamp);
        }
    }
}

// Normalize gender
$validGenders = ['Male', 'Female', 'Other'];
if (!in_array($gender, $validGenders)) {
    $gender = 'Male';
}

// If no doctorId, try to find doctor by session, email, or phone
if (empty($doctorId)) {
    $cleanP = cleanPhone($phone);
    $findQuery = "SELECT id FROM `doctors` WHERE `email` = ? OR `phone_number` = ? OR `phone_number` = ? LIMIT 1";
    $findStmt = mysqli_prepare($connection1, $findQuery);
    mysqli_stmt_bind_param($findStmt, "sss", $email, $phone, $cleanP);
    mysqli_stmt_execute($findStmt);
    $findRes = mysqli_stmt_get_result($findStmt);
    if ($row = mysqli_fetch_assoc($findRes)) {
        $doctorId = $row['id'];
    }
    mysqli_stmt_close($findStmt);
}

// If still no doctorId, grab first doctor or insert new
if (empty($doctorId)) {
    $resFirst = mysqli_query($connection1, "SELECT id FROM `doctors` ORDER BY id ASC LIMIT 1");
    if ($r = mysqli_fetch_assoc($resFirst)) {
        $doctorId = $r['id'];
    }
}

if (!empty($doctorId)) {
    // Preserve existing fields if partial update was sent (e.g. from checkout page)
    $currStmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `id` = ? LIMIT 1");
    if ($currStmt) {
        mysqli_stmt_bind_param($currStmt, "i", $doctorId);
        mysqli_stmt_execute($currStmt);
        $currDoc = mysqli_fetch_assoc(mysqli_stmt_get_result($currStmt));
        mysqli_stmt_close($currStmt);

        if ($currDoc) {
            if (empty($fullName) && !empty($currDoc['full_name'])) {
                $fullName = $currDoc['full_name'];
            }
            if (empty($phone) && !empty($currDoc['phone_number'])) {
                $phone = $currDoc['phone_number'];
            }
            if (empty($email) && !empty($currDoc['email'])) {
                $email = $currDoc['email'];
            }
            if (empty($dob) && !empty($currDoc['date_of_birth'])) {
                $formattedDOB = $currDoc['date_of_birth'];
            }
            if (empty($input['gender']) && !empty($currDoc['gender'])) {
                $gender = $currDoc['gender'];
            }
            if (!isset($input['yoe']) && !isset($input['years_of_experience']) && isset($currDoc['years_of_experience'])) {
                $yoe = intval($currDoc['years_of_experience']);
            }
            if (empty($expertise) && !empty($currDoc['expertise'])) {
                $expertise = $currDoc['expertise'];
            }
            if (empty($area) && !empty($currDoc['area'])) {
                $area = $currDoc['area'];
            }
            if (empty($regNo) && !empty($currDoc['registration_number'])) {
                $regNo = $currDoc['registration_number'];
            }
            if (empty($hprRegNo) && !empty($currDoc['hpr_registration_number'])) {
                $hprRegNo = $currDoc['hpr_registration_number'];
            }
        }
    }

    // 1. Update `doctors` table
    $updateQuery = "UPDATE `doctors` SET 
        `full_name` = ?, 
        `date_of_birth` = ?, 
        `phone_number` = ?, 
        `gender` = ?, 
        `email` = ?, 
        `years_of_experience` = ?, 
        `expertise` = ?, 
        `area` = ?, 
        `registration_number` = ?, 
        `hpr_registration_number` = ?
        WHERE `id` = ?";
    
    try {
        $stmt = mysqli_prepare($connection1, $updateQuery);
        mysqli_stmt_bind_param($stmt, "sssssissssi", 
            $fullName, 
            $formattedDOB, 
            $phone, 
            $gender, 
            $email, 
            $yoe, 
            $expertise, 
            $area, 
            $regNo, 
            $hprRegNo, 
            $doctorId
        );
        $executed = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$executed) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update doctor profile: ' . mysqli_error($connection1)
            ]);
            exit();
        }
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating doctor profile: ' . $e->getMessage()
        ]);
        exit();
    }

    // 2. Also attempt to sync `full_name` and `phone_number` to `users` table safely
    try {
        $cleanP = cleanPhone($phone);
        // Only update user's own record if phone number doesn't conflict with another user
        $checkPhone = mysqli_query($connection1, "SELECT id FROM `users` WHERE (`phone_number` = '" . mysqli_real_escape_string($connection1, $phone) . "' OR `phone_number` = '" . mysqli_real_escape_string($connection1, $cleanP) . "') AND `email` != '" . mysqli_real_escape_string($connection1, $email) . "'");
        
        if ($checkPhone && mysqli_num_rows($checkPhone) === 0) {
            $syncUserQuery = "UPDATE `users` SET `full_name` = ?, `phone_number` = ? WHERE `email` = ? OR `id` = ?";
            $stmtUser = mysqli_prepare($connection1, $syncUserQuery);
            mysqli_stmt_bind_param($stmtUser, "sssi", $fullName, $phone, $email, $userId);
            mysqli_stmt_execute($stmtUser);
            mysqli_stmt_close($stmtUser);
        } else {
            // Update just the full name in users table to avoid phone collision
            $syncNameQuery = "UPDATE `users` SET `full_name` = ? WHERE `email` = ? OR `id` = ?";
            $stmtUserName = mysqli_prepare($connection1, $syncNameQuery);
            mysqli_stmt_bind_param($stmtUserName, "ssi", $fullName, $email, $userId);
            mysqli_stmt_execute($stmtUserName);
            mysqli_stmt_close($stmtUserName);
        }
    } catch (Throwable $e) {
        // Non-critical: log and proceed with doctor profile update
    }

    // 3. Fetch fresh doctor profile
    $fetchStmt = mysqli_prepare($connection1, "SELECT * FROM `doctors` WHERE `id` = ? LIMIT 1");
    mysqli_stmt_bind_param($fetchStmt, "i", $doctorId);
    mysqli_stmt_execute($fetchStmt);
    $updatedDoc = mysqli_fetch_assoc(mysqli_stmt_get_result($fetchStmt));
    mysqli_stmt_close($fetchStmt);

    if ($updatedDoc) {
        $timestamp = strtotime($updatedDoc['date_of_birth']);
        $updatedDoc['date_of_birth_iso'] = date('Y-m-d', $timestamp);
        $updatedDoc['date_of_birth_display'] = date('d/m/Y', $timestamp);
        $_SESSION['doctor_profile'] = $updatedDoc;
    }

    recordActivity(
        $connection1,
        'Profile',
        'profile.updated',
        "Dr. {$fullName} updated their profile details",
        'Doctor',
        $doctorId,
        $fullName,
        [
            'phone' => $phone,
            'email' => $email,
            'expertise' => $expertise,
            'area' => $area,
            'years_of_experience' => $yoe
        ],
        'Doctor',
        $doctorId,
        $fullName
    );

    echo json_encode([
        'success' => true,
        'message' => 'Doctor profile updated successfully!',
        'doctor' => $updatedDoc
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Doctor profile could not be identified to update.'
    ]);
}
?>
