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
        $this->ensureTablesExist();
    }

    // ── Public entry point ────────────────────────────────────────────────────

    public function registerMember(array $input): array {
        $errors = $this->validate($input);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $this->pdo->beginTransaction();

            $memberId = $this->generateMemberId();

            // Parse full name
            $nameParts  = explode(' ', trim($input['fullName'] ?? ''), 2);
            $firstName  = $nameParts[0] ?? '';
            $lastName   = $nameParts[1] ?? '';

            // 1. Insert into members table
            $memberRowId = $this->insertMember($memberId, $firstName, $lastName, $input);

            // 2. Insert into users table (for login)
            $userId = $this->insertUser($memberId, $firstName, $lastName, $input);

            // 3. Log the registration
            $this->logRegistration($memberId, $input['email'] ?? '', $userId);

            $this->pdo->commit();

            return [
                'success' => true,
                'data' => [
                    'member_id'       => $memberId,
                    'member_record_id'=> $memberRowId,
                    'user_id'         => $userId,
                    'username'        => $input['username'] ?? strtolower(str_replace(' ', '.', trim($input['fullName'] ?? ''))),
                    'registration_date' => date('Y-m-d H:i:s'),
                ]
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('RegistrationService error: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Registration failed: ' . $e->getMessage()]];
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function validate(array $d): array {
        $errors = [];

        if (empty($d['fullName']))   $errors[] = 'Full name is required';
        if (empty($d['email']))      $errors[] = 'Email is required';
        if (!empty($d['email']) && !filter_var($d['email'], FILTER_VALIDATE_EMAIL))
                                     $errors[] = 'Invalid email address';
        if (empty($d['mobilePhone']) && empty($d['phone']))
                                     $errors[] = 'Phone number is required';

        // Duplicate checks
        $phone = $d['mobilePhone'] ?? $d['phone'] ?? '';
        $email = strtolower(trim($d['email'] ?? ''));

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
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM members WHERE phone = ?");
        $stmt->execute([$phone]);
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
        $phone = $d['mobilePhone'] ?? $d['phone'] ?? '';
        $email = strtolower(trim($d['email'] ?? ''));

        $sql = "INSERT INTO members (
                    member_id, first_name, last_name, gender, date_of_birth,
                    phone, email, address, marital_status, education_level,
                    profession, country, state_region, city, postal_code,
                    center, payment_status, membership_form_complete,
                    baptized, service_interest, notes, status, created_at
                ) VALUES (
                    :member_id, :first_name, :last_name, :gender, :date_of_birth,
                    :phone, :email, :address, :marital_status, :education_level,
                    :profession, :country, :state_region, :city, :postal_code,
                    :center, 'unpaid', 0,
                    :baptized, :service_interest, :notes, 'pending', NOW()
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':member_id'       => $memberId,
            ':first_name'      => $firstName,
            ':last_name'       => $lastName,
            ':gender'          => $d['gender'] ?? null,
            ':date_of_birth'   => $d['dateOfBirth'] ?? null,
            ':phone'           => $phone,
            ':email'           => $email,
            ':address'         => $d['currentAddress'] ?? $d['address'] ?? '',
            ':marital_status'  => $d['maritalStatus'] ?? null,
            ':education_level' => $d['educationLevel'] ?? null,
            ':profession'      => $d['occupation'] ?? $d['profession'] ?? null,
            ':country'         => $d['country'] ?? '',
            ':state_region'    => $d['region'] ?? $d['state_region'] ?? '',
            ':city'            => $d['subCity'] ?? $d['city'] ?? '',
            ':postal_code'     => $d['postal_code'] ?? '',
            ':center'          => $d['preferredCenter'] ?? 'Default',
            ':baptized'        => 'yes',
            ':service_interest'=> is_array($d['serviceAreas'] ?? null)
                                    ? implode(',', $d['serviceAreas'])
                                    : ($d['service_interest'] ?? ''),
            ':notes'           => $d['notes'] ?? '',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function insertUser(string $memberId, string $firstName, string $lastName, array $d): int {
        // Ensure users table exists
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('user','admin','superadmin') DEFAULT 'user',
                status ENUM('active','inactive','pending') DEFAULT 'active',
                member_id VARCHAR(20),
                full_name VARCHAR(200),
                phone VARCHAR(30),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_member_id (member_id)
            )
        ");

        $phone    = $d['mobilePhone'] ?? $d['phone'] ?? '';
        $email    = strtolower(trim($d['email'] ?? ''));
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
            ':email'         => $email ?: null,
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
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS registration_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(50) NOT NULL,
                member_id VARCHAR(20),
                email VARCHAR(255),
                user_id INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                timestamp DATETIME NOT NULL,
                INDEX idx_member_id (member_id)
            )
        ");

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
