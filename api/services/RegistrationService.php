<?php
/**
 * RegistrationService
 * Handles full member registration — creates both a users row (for login)
 * and a members row (for membership data).
 */

class RegistrationService {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
        // Note: ensureTablesExist() is called inside registerMember()
        // before the transaction starts, not here — DDL causes implicit commits
    }

    // ── Public entry point ────────────────────────────────────────────────────

    public function registerMember(array $input): array {
        $errors = $this->validate($input);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Run schema migrations BEFORE starting a transaction
        // (DDL statements like ALTER TABLE cause implicit commits)
        $this->ensureTablesExist();

        try {
            $this->pdo->beginTransaction();

            $memberId = $this->generateMemberId();

            $nameParts = explode(' ', trim($input['fullName'] ?? ''), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName  = $nameParts[1] ?? '';

            $memberRowId = $this->insertMember($memberId, $firstName, $lastName, $input);
            $userId      = $this->insertUser($memberId, $firstName, $lastName, $input);
            $this->logRegistration($memberId, $input['email'] ?? '', $userId);

            $this->pdo->commit();

            return [
                'success' => true,
                'data' => [
                    'member_id'        => $memberId,
                    'member_record_id' => $memberRowId,
                    'user_id'          => $userId,
                    'username'         => $input['username'] ?? strtolower(str_replace(' ', '.', trim($input['fullName'] ?? ''))),
                    'registration_date'=> date('Y-m-d H:i:s'),
                ]
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('RegistrationService error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Registration failed: ' . $e->getMessage()]];
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function validate(array $d): array {
        $errors = [];

        if (empty($d['fullName']))   $errors[] = 'Full name is required';

        // Email is optional but must be valid if provided
        $email = strtolower(trim($d['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }

        if (empty($d['mobilePhone']) && empty($d['phone']))
                                     $errors[] = 'Phone number is required';

        // Duplicate checks — only if values are present
        $phone = $d['mobilePhone'] ?? $d['phone'] ?? '';
        if ($email && $this->emailExists($email))   $errors[] = 'Email already registered';
        if ($phone && $this->phoneExists($phone))   $errors[] = 'Phone number already registered';

        return $errors;
    }

    private function emailExists(string $email): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM members WHERE email = ?");
        $stmt->execute([$email]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function phoneExists(string $phone): bool {
        // Check both column names — schema may use either
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM members WHERE mobile_phone = ? OR phone = ?"
        );
        $stmt->execute([$phone, $phone]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function generateMemberId(): string {
        $year = date('Y');
        $stmt = $this->pdo->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(member_id, '-', -1) AS UNSIGNED))
             FROM members WHERE member_id LIKE ?"
        );
        $stmt->execute(["WDB-{$year}-%"]);
        $max = (int)($stmt->fetchColumn() ?? 0);
        return sprintf('WDB-%s-%04d', $year, $max + 1);
    }

    private function insertMember(string $memberId, string $firstName, string $lastName, array $d): int {
        $phone    = $d['mobilePhone'] ?? $d['phone'] ?? '';
        $email    = strtolower(trim($d['email'] ?? '')) ?: null;
        $fullName = trim($firstName . ' ' . $lastName);

        // Detect which phone column(s) exist in the actual table
        $cols = $this->pdo->query("SHOW COLUMNS FROM members")->fetchAll(PDO::FETCH_COLUMN);

        $hasMobilePhone = in_array('mobile_phone', $cols);
        $hasPhone       = in_array('phone', $cols);
        $hasFullName    = in_array('full_name', $cols);
        $hasFirstName   = in_array('first_name', $cols);

        // Build column/value lists dynamically so we never reference a missing column
        $insertCols = ['member_id', 'status', 'membership_form_complete', 'payment_status', 'created_at'];
        $insertVals = [':member_id', "'pending'", '0', "'unpaid'", 'NOW()'];
        $params     = [':member_id' => $memberId];

        // Name columns
        if ($hasFullName) {
            $insertCols[] = 'full_name';  $insertVals[] = ':full_name';
            $params[':full_name'] = $fullName;
        }
        if ($hasFirstName) {
            $insertCols[] = 'first_name'; $insertVals[] = ':first_name';
            $insertCols[] = 'last_name';  $insertVals[] = ':last_name';
            $params[':first_name'] = $firstName;
            $params[':last_name']  = $lastName;
        }

        // Phone columns
        if ($hasMobilePhone) {
            $insertCols[] = 'mobile_phone'; $insertVals[] = ':mobile_phone';
            $params[':mobile_phone'] = $phone;
        }
        if ($hasPhone) {
            $insertCols[] = 'phone'; $insertVals[] = ':phone';
            $params[':phone'] = $phone;
        }

        // Optional columns — only add if they exist in the table
        $optionals = [
            'email'          => $email,
            'gender'         => $d['gender'] ?? null,
            'date_of_birth'  => $d['dateOfBirth'] ?? null,
            'address'        => $d['currentAddress'] ?? $d['address'] ?? null,
            'marital_status' => $d['maritalStatus'] ?? null,
            'education_level'=> $d['educationLevel'] ?? null,
            'profession'     => $d['occupation'] ?? $d['profession'] ?? null,
            'occupation'     => $d['occupation'] ?? null,
            'country'        => $d['country'] ?? null,
            'state_region'   => $d['region'] ?? $d['state_region'] ?? null,
            'region'         => $d['region'] ?? null,
            'city'           => $d['subCity'] ?? $d['city'] ?? null,
            'sub_city'       => $d['subCity'] ?? null,
            'postal_code'    => $d['postal_code'] ?? null,
            'center'         => $d['preferredCenter'] ?? null,
            'wdb_center'     => $d['preferredCenter'] ?? null,
            'baptized'       => 'yes',
            'service_interest' => is_array($d['serviceAreas'] ?? null)
                                    ? implode(',', $d['serviceAreas'])
                                    : ($d['service_interest'] ?? null),
            'service_areas'  => is_array($d['serviceAreas'] ?? null)
                                    ? implode(',', $d['serviceAreas']) : null,
            'notes'          => $d['notes'] ?? null,
            'membership_plan'=> $d['membershipPlan'] ?? null,
        ];

        foreach ($optionals as $col => $val) {
            if ($val !== null && $val !== '' && in_array($col, $cols)
                && !in_array($col, $insertCols)) {
                $key          = ':opt_' . $col;
                $insertCols[] = $col;
                $insertVals[] = $key;
                $params[$key] = $val;
            }
        }

        $sql = 'INSERT INTO members (' . implode(', ', $insertCols) . ') '
             . 'VALUES (' . implode(', ', $insertVals) . ')';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$this->pdo->lastInsertId();
    }

    private function insertUser(string $memberId, string $firstName, string $lastName, array $d): int {
        // users table is created in ensureTablesExist() — no DDL here
        $phone    = $d['mobilePhone'] ?? $d['phone'] ?? '';
        $email    = strtolower(trim($d['email'] ?? '')) ?: null;
        $fullName = trim($firstName . ' ' . $lastName);

        // Build username: prefer provided, else derive from name
        $username = $d['username'] ?? strtolower(str_replace(' ', '.', $fullName));
        // Make unique if taken
        $base = $username;
        $i    = 1;
        while ($this->usernameExists($username)) {
            $username = $base . $i++;
        }

        // Password: use provided or phone as default
        $rawPassword  = $d['password'] ?? $phone;
        $passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, email, password_hash, role, status, member_id, full_name, phone)
            VALUES (:username, :email, :password_hash, 'user', 'active', :member_id, :full_name, :phone)
        ");
        $stmt->execute([
            ':username'      => $username,
            ':email'         => $email ?: null,   // NULL instead of empty string (UNIQUE constraint)
            ':password_hash' => $passwordHash,
            ':member_id'     => $memberId,
            ':full_name'     => $fullName,
            ':phone'         => $phone,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function usernameExists(string $username): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function logRegistration(string $memberId, string $email, int $userId): void {
        // registration_log table is created in ensureTablesExist() — no DDL here
        $stmt = $this->pdo->prepare("
            INSERT INTO registration_log (action, member_id, email, user_id, ip_address, user_agent, timestamp)
            VALUES ('member_registration', :member_id, :email, :user_id, :ip, :ua, NOW())
        ");
        $stmt->execute([
            ':member_id' => $memberId,
            ':email'     => $email,
            ':user_id'   => $userId,
            ':ip'        => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':ua'        => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
    }

    // ── Schema migration ──────────────────────────────────────────────────────

    private function ensureTablesExist(): void {
        // Create users table if missing
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) UNIQUE NOT NULL,
                email VARCHAR(255) UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('user','admin','superadmin') DEFAULT 'user',
                status ENUM('active','inactive','pending') DEFAULT 'active',
                member_id VARCHAR(50),
                full_name VARCHAR(200),
                phone VARCHAR(30),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_username (username),
                INDEX idx_member_id (member_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Create registration_log table if missing
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS registration_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(50) NOT NULL,
                member_id VARCHAR(50),
                email VARCHAR(255),
                user_id INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                timestamp DATETIME NOT NULL,
                INDEX idx_member_id (member_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Relax NOT NULL constraints that block quick registration
        $relaxCols = [
            "ALTER TABLE members MODIFY COLUMN gender   ENUM('male','female','other') NULL",
            "ALTER TABLE members MODIFY COLUMN address  TEXT NULL",
            "ALTER TABLE members MODIFY COLUMN baptized ENUM('yes','no') NULL",
        ];
        foreach ($relaxCols as $sql) {
            try { $this->pdo->exec($sql); } catch (Exception $e) { /* ignore */ }
        }

        // Add missing columns to members table if they don't exist
        $alterations = [
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
            "ALTER TABLE members ADD COLUMN IF NOT EXISTS registration_date DATETIME NULL",
        ];

        foreach ($alterations as $sql) {
            try { $this->pdo->exec($sql); } catch (Exception $e) {
                // Column may already exist — ignore
            }
        }
    }
}
