<?php
/**
 * Save Signup Data API
 * Stores signup data to database BEFORE redirecting to Authorize.net payment
 * This creates a backup in case user doesn't click "Continue" after payment
 */
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../_bootstrap.php';

// Rate limiting
require_once __DIR__ . '/../_rate_limit.php';
rate_limit('save-signup-data');

function bailout(int $code, string $msg, array $extra = []): never {
    http_response_code($code);
    echo json_encode(['status'=>'error','error'=>$msg] + $extra);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bailout(405, 'method_not_allowed');
}

try {
    // Get JSON payload
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        bailout(400, 'invalid_json');
    }

    // Generate unique session ID for this signup
    $sessionId = bin2hex(random_bytes(32));

    // Extract data
    $memberName = trim($data['member_name'] ?? '');
    $memberEmail = trim($data['member_email'] ?? '');
    $memberPhone = trim($data['member_phone'] ?? '');
    $memberDob = trim($data['member_dob'] ?? '');
    $emergencyName = trim($data['emergency_contact_name'] ?? '');
    $emergencyPhone = trim($data['emergency_contact_phone'] ?? '');
    $membershipPlan = trim($data['membership_plan'] ?? '');
    $monthlyFee = isset($data['monthly_fee']) ? (float)$data['monthly_fee'] : 0.0;
    $photoFilename = trim($data['photo_filename'] ?? '');

    // Store waiver data as JSON
    $waiverData = json_encode($data['waiver_data'] ?? []);

    // Validation
    if (empty($memberName)) {
        bailout(400, 'member_name_required');
    }
    if (empty($memberEmail) || !filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) {
        bailout(400, 'valid_email_required');
    }
    if (empty($membershipPlan)) {
        bailout(400, 'membership_plan_required');
    }

    // Save to database
    $pdo = pdo();
    $stmt = $pdo->prepare("
        INSERT INTO pending_signups (
            session_id,
            member_name,
            member_email,
            member_phone,
            member_dob,
            emergency_contact_name,
            emergency_contact_phone,
            membership_plan,
            monthly_fee,
            waiver_data,
            photo_filename,
            status
        ) VALUES (
            :session_id,
            :member_name,
            :member_email,
            :member_phone,
            :member_dob,
            :emergency_name,
            :emergency_phone,
            :membership_plan,
            :monthly_fee,
            :waiver_data,
            :photo_filename,
            'pending'
        )
    ");

    $stmt->execute([
        ':session_id' => $sessionId,
        ':member_name' => $memberName,
        ':member_email' => $memberEmail,
        ':member_phone' => $memberPhone,
        ':member_dob' => $memberDob ?: null,
        ':emergency_name' => $emergencyName,
        ':emergency_phone' => $emergencyPhone,
        ':membership_plan' => $membershipPlan,
        ':monthly_fee' => $monthlyFee,
        ':waiver_data' => $waiverData,
        ':photo_filename' => $photoFilename
    ]);

    echo json_encode([
        'status' => 'success',
        'session_id' => $sessionId,
        'message' => 'Signup data saved'
    ]);

} catch (PDOException $e) {
    error_log("save-signup-data.php PDO Error: " . $e->getMessage());
    bailout(500, 'database_error');
} catch (Exception $e) {
    error_log("save-signup-data.php Error: " . $e->getMessage());
    bailout(500, 'server_error');
}
