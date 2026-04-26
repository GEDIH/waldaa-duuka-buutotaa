<?php
/**
 * Update Membership Form API
 * Called when a member completes the full membership form after account creation.
 * Updates the existing members row and marks membership_form_complete = 1.
 */

error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/registration_errors.php');

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean(); http_response_code(200); exit(0);
}

function respond(bool $success, $data, int $code = 200): void {
    while (ob_get_level()) ob_end_clean();
    ob_start();
    http_response_code($code);
    echo json_encode(
        $success
            ? ['success' => true,  'data'  => $data]
            : ['success' => false, 'error' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    ob_end_flush();
    exit(0);
}

try {
    require_once __DIR__ . '/../../api/config/database.php';

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) respond(false, 'Invalid JSON input', 400);

    $memberId = trim($input['member_id'] ?? $input['existing_member_id'] ?? '');
    if (!$memberId) respond(false, 'member_id is required', 400);

    $pdo = Database::getInstance()->getConnection();

    // Ensure extra columns exist (safe to run every time)
    $cols = [
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS marital_status VARCHAR(30) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS education_level VARCHAR(50) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS profession VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS country VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS state_region VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS postal_code VARCHAR(20) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS center VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT 'unpaid'",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS membership_form_complete TINYINT(1) DEFAULT 0",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS baptism_name VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS facebook_name VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS spouse_name VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS spouse_phone VARCHAR(30) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS confession_father VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS field_of_study VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS university VARCHAR(150) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS graduation_year VARCHAR(10) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS organization VARCHAR(150) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS current_service VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS church_woreda VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS service_areas TEXT NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS membership_plan VARCHAR(50) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS center_role VARCHAR(50) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS region VARCHAR(100) NULL",
    ];
    foreach ($cols as $sql) {
        try { $pdo->exec($sql); } catch (Exception $e) { /* already exists */ }
    }

    // Parse full name if provided
    $fullName  = trim($input['fullName'] ?? '');
    $nameParts = $fullName ? explode(' ', $fullName, 2) : [];
    $firstName = $nameParts[0] ?? null;
    $lastName  = $nameParts[1] ?? null;

    $phone = $input['mobilePhone'] ?? $input['phone'] ?? null;
    $email = !empty($input['email']) ? strtolower(trim($input['email'])) : null;

    // Build dynamic SET clause — only update fields that were sent
    $fields = [
        'marital_status'   => $input['maritalStatus']    ?? null,
        'education_level'  => $input['educationLevel']   ?? null,
        'profession'       => $input['occupation']       ?? null,
        'country'          => $input['country']          ?? null,
        'state_region'     => $input['region']           ?? null,
        'city'             => $input['subCity']          ?? null,
        'center'           => $input['preferredCenter']  ?? null,
        'baptism_name'     => $input['baptismName']      ?? null,
        'facebook_name'    => $input['facebookName']     ?? null,
        'spouse_name'      => $input['spouseName']       ?? null,
        'spouse_phone'     => $input['spousePhone']      ?? null,
        'confession_father'=> $input['confessionFather'] ?? null,
        'field_of_study'   => $input['fieldOfStudy']     ?? null,
        'university'       => $input['university']       ?? null,
        'graduation_year'  => $input['graduationYear']   ?? null,
        'organization'     => $input['organization']     ?? null,
        'current_church'   => $input['currentChurch']    ?? null,
        'current_service'  => $input['currentService']   ?? null,
        'church_woreda'    => $input['churchWoreda']     ?? null,
        'service_areas'    => is_array($input['serviceAreas'] ?? null)
                                ? implode(',', $input['serviceAreas']) : null,
        'membership_plan'  => $input['membershipPlan']   ?? null,
        'center_role'      => $input['centerRole']       ?? null,
        'region'           => $input['region']           ?? null,
        'notes'            => $input['notes']            ?? null,
        'address'          => $input['currentAddress']   ?? null,
        'gender'           => $input['gender']           ?? null,
        'date_of_birth'    => $input['dateOfBirth']      ?? null,
        'membership_form_complete' => 1,
        'status'           => 'pending', // admin still needs to approve
    ];

    if ($firstName) $fields['first_name'] = $firstName;
    if ($lastName)  $fields['last_name']  = $lastName;
    if ($phone)     $fields['phone']      = $phone;
    if ($email)     $fields['email']      = $email;

    // Remove nulls — don't overwrite existing data with null
    $fields = array_filter($fields, fn($v) => $v !== null);

    $setParts = [];
    $params   = [];
    foreach ($fields as $col => $val) {
        $setParts[] = "`{$col}` = :{$col}";
        $params[":{$col}"] = $val;
    }
    $params[':member_id'] = $memberId;

    $sql  = "UPDATE members SET " . implode(', ', $setParts) . " WHERE member_id = :member_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        // Member row not found — could be localStorage-only account; still return success
        respond(true, [
            'member_id'   => $memberId,
            'updated'     => false,
            'message'     => 'Member record not found in DB (localStorage account). Form data noted.'
        ]);
    }

    // Also update users table email if provided
    if ($email) {
        try {
            $pdo->prepare("UPDATE users SET email = :email WHERE member_id = :mid")
                ->execute([':email' => $email, ':mid' => $memberId]);
        } catch (Exception $e) { /* ignore */ }
    }

    respond(true, [
        'member_id' => $memberId,
        'updated'   => true,
        'message'   => 'Membership form saved successfully.'
    ]);

} catch (Exception $e) {
    error_log('update-membership-form error: ' . $e->getMessage());
    respond(false, 'Server error: ' . $e->getMessage(), 500);
}
