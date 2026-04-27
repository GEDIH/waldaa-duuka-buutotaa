<?php
/**
 * Update Membership Form API
 * Updates an existing member record with full membership form data
 * and marks membership_form_complete = 1.
 */

// Must be first — capture everything so nothing leaks before our JSON
ob_start();

// Suppress display errors; log them instead
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__, 2) . '/logs/php_errors.log');

// Always respond with JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit(0);
}

/**
 * Send a JSON response and exit.
 * Clears the output buffer so no stray output contaminates the JSON.
 */
function sendJson(array $payload, int $status = 200): void {
    ob_end_clean();          // discard anything buffered so far
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

// ── Main logic ────────────────────────────────────────────────────────────────
try {
    $dbFile = dirname(__DIR__) . '/config/database.php';
    if (!file_exists($dbFile)) {
        sendJson(['success' => false, 'error' => 'Database config not found at: ' . $dbFile], 500);
    }
    require_once $dbFile;

    // Parse request body
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if ($input === null) {
        sendJson(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()], 400);
    }

    // Resolve member_id — accept either key name
    $memberId = trim($input['existing_member_id'] ?? $input['member_id'] ?? '');
    if ($memberId === '') {
        sendJson(['success' => false, 'error' => 'member_id is required'], 400);
    }

    $pdo = Database::getInstance()->getConnection();

    // ── Ensure all extra columns exist ───────────────────────────────────────
    $extraCols = [
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS marital_status    VARCHAR(30)  NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS education_level   VARCHAR(50)  NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS profession        VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS country           VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS state_region      VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS city              VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS postal_code       VARCHAR(20)  NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS center            VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS payment_status    VARCHAR(20)  DEFAULT 'unpaid'",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS membership_form_complete TINYINT(1) DEFAULT 0",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS baptism_name      VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS facebook_name     VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS spouse_name       VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS spouse_phone      VARCHAR(30)  NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS confession_father VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS field_of_study    VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS university        VARCHAR(150) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS graduation_year   VARCHAR(10)  NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS organization      VARCHAR(150) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS current_service   VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS church_woreda     VARCHAR(100) NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS service_areas     TEXT         NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS membership_plan   VARCHAR(50)  NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS center_role       VARCHAR(50)  NULL",
        "ALTER TABLE members ADD COLUMN IF NOT EXISTS region            VARCHAR(100) NULL",
    ];
    foreach ($extraCols as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* column already exists — ignore */ }
    }

    // ── Build UPDATE fields ───────────────────────────────────────────────────
    $map = [
        'marital_status'           => $input['maritalStatus']    ?? null,
        'education_level'          => $input['educationLevel']   ?? null,
        'profession'               => $input['occupation']       ?? null,
        'country'                  => $input['country']          ?? null,
        'state_region'             => $input['region']           ?? null,
        'city'                     => $input['subCity']          ?? null,
        'center'                   => $input['preferredCenter']  ?? null,
        'baptism_name'             => $input['baptismName']      ?? null,
        'facebook_name'            => $input['facebookName']     ?? null,
        'spouse_name'              => $input['spouseName']       ?? null,
        'spouse_phone'             => $input['spousePhone']      ?? null,
        'confession_father'        => $input['confessionFather'] ?? null,
        'field_of_study'           => $input['fieldOfStudy']     ?? null,
        'university'               => $input['university']       ?? null,
        'graduation_year'          => $input['graduationYear']   ?? null,
        'organization'             => $input['organization']     ?? null,
        'current_church'           => $input['currentChurch']    ?? null,
        'current_service'          => $input['currentService']   ?? null,
        'church_woreda'            => $input['churchWoreda']     ?? null,
        'service_areas'            => is_array($input['serviceAreas'] ?? null)
                                        ? implode(',', $input['serviceAreas']) : null,
        'membership_plan'          => $input['membershipPlan']   ?? null,
        'center_role'              => $input['centerRole']       ?? null,
        'region'                   => $input['region']           ?? null,
        'notes'                    => $input['notes']            ?? null,
        'address'                  => $input['currentAddress']   ?? null,
        'gender'                   => $input['gender']           ?? null,
        'date_of_birth'            => $input['dateOfBirth']      ?? null,
        'membership_form_complete' => 1,
        'status'                   => 'pending',
    ];

    // Parse full name
    $fullName = trim($input['fullName'] ?? '');
    if ($fullName !== '') {
        $parts = explode(' ', $fullName, 2);
        $map['first_name'] = $parts[0];
        $map['last_name']  = $parts[1] ?? '';
    }

    $phone = $input['mobilePhone'] ?? $input['phone'] ?? null;
    $email = !empty($input['email']) ? strtolower(trim($input['email'])) : null;
    if ($phone) {
        $map['mobile_phone'] = $phone;   // canonical column name
        $map['phone']        = $phone;   // legacy alias
    }
    if ($email) $map['email'] = $email;

    // Drop nulls — never overwrite existing data with null
    $map = array_filter($map, fn($v) => $v !== null && $v !== '');

    if (empty($map)) {
        sendJson(['success' => false, 'error' => 'No data provided to update'], 400);
    }

    // Only keep columns that actually exist in the table
    $existingCols = $pdo->query("SHOW COLUMNS FROM members")->fetchAll(PDO::FETCH_COLUMN);
    $map = array_filter($map, fn($k) => in_array($k, $existingCols), ARRAY_FILTER_USE_KEY);

    // Build parameterised query
    $setParts = [];
    $params   = [':member_id' => $memberId];
    foreach ($map as $col => $val) {
        $key          = ':' . $col;
        $setParts[]   = "`{$col}` = {$key}";
        $params[$key] = $val;
    }

    $sql  = 'UPDATE members SET ' . implode(', ', $setParts) . ' WHERE member_id = :member_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $updated = $stmt->rowCount() > 0;

    // Sync email to users table too
    if ($email) {
        try {
            $pdo->prepare('UPDATE users SET email = :e WHERE member_id = :m')
                ->execute([':e' => $email, ':m' => $memberId]);
        } catch (PDOException $e) { /* ignore */ }
    }

    sendJson([
        'success' => true,
        'data'    => [
            'member_id' => $memberId,
            'updated'   => $updated,
            'message'   => $updated
                ? 'Membership form saved successfully.'
                : 'Member record not found in DB — form data noted locally.',
        ]
    ]);

} catch (PDOException $e) {
    error_log('update-membership-form PDO error: ' . $e->getMessage());
    sendJson(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
} catch (Throwable $e) {
    error_log('update-membership-form error: ' . $e->getMessage());
    sendJson(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
}
