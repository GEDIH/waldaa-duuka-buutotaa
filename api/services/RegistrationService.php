<?php
/**
 * RegistrationService
 * Creates a members row + a users row (for login) in one transaction.
 * All DDL (CREATE TABLE / ALTER TABLE) runs BEFORE beginTransaction()
 * to avoid MariaDB implicit-commit issues.
 */
class RegistrationService {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Public ────────────────────────────────────────────────────────────────

    public function registerMember(array $input): array {
        $errors = $this->validate($input);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // DDL must run outside any transaction
        $this->ensureSchema();

        try {
            $this->pdo->beginTransaction();

            $memberId  = $this->generateMemberId();
            $nameParts = explode(' ', trim($input['fullName'] ?? ''), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName  = $nameParts[1] ?? '';
            $fullName  = trim($firstName . ' ' . $lastName);

            $memberRowId = $this->insertMember($memberId, $firstName, $lastName, $fullName, $input);
            $userId      = $this->insertUser($memberId, $firstName, $lastName, $fullName, $input);

            // Link member row to user row
            $this->pdo->prepare("UPDATE members SET user_id = ? WHERE member_id = ?")
                      ->execute([$userId, $memberId]);

            $this->logRegistration($memberId, $input['email'] ?? '', $userId);

            $this->pdo->commit();

            return [
                'success' => true,
                'data' => [
                    'member_id'         => $memberId,
                    'member_record_id'  => $memberRowId,
                    'user_id'           => $userId,
                    'username'          => $input['username']
                                          ?? strtolower(str_replace(' ', '.', $fullName)),
                    'registration_date' => date('Y-m-d H:i:s'),
                ]
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('RegistrationService::registerMember — ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Registration failed: ' . $e->getMessage()]];
        }
    }

    // ── Validation ────────────────────────────────────────────────────────────

    private function validate(array $d): array {
        $errors = [];
        if (empty($d['fullName'])) $errors[] = 'Full name is required';
        if (empty($d['mobilePhone']) && empty($d['phone'])) $errors[] = 'Phone number is required';

        $email = strtolower(trim($d['email'] ?? ''));
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';

        $phone = $d['mobilePhone'] ?? $d['phone'] ?? '';
        if ($email && $this->colExists('email') && $this->rowExists('members', 'email', $email))
            $errors[] = 'Email already registered';
        if ($phone && $this->phoneExists($phone))
            $errors[] = 'Phone number already registered';

        return $errors;
    }

    private function phoneExists(string $phone): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM members WHERE mobile_phone = ? OR phone = ?"
        );
        $stmt->execute([$phone, $phone]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function colExists(string $col): bool {
        static $cols = null;
        if ($cols === null) {
            $cols = $this->pdo->query("SHOW COLUMNS FROM members")->fetchAll(PDO::FETCH_COLUMN);
        }
        return in_array($col, $cols);
    }

    private function rowExists(string $table, string $col, string $val): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` = ?");
        $stmt->execute([$val]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ── Member ID ─────────────────────────────────────────────────────────────

    private function generateMemberId(): string {
        $year = date('Y');
        $stmt = $this->pdo->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(member_id,'-',-1) AS UNSIGNED))
             FROM members WHERE member_id LIKE ?"
        );
        $stmt->execute(["WDB-{$year}-%"]);
        return sprintf('WDB-%s-%04d', $year, (int)($stmt->fetchColumn() ?? 0) + 1);
    }

    // ── Insert member row ─────────────────────────────────────────────────────

    private function insertMember(
        string $memberId, string $firstName, string $lastName,
        string $fullName, array $d
    ): int {
        $phone = $d['mobilePhone'] ?? $d['phone'] ?? '';
        $email = strtolower(trim($d['email'] ?? '')) ?: null;

        $stmt = $this->pdo->prepare("
            INSERT INTO members (
                member_id, full_name, first_name, last_name,
                mobile_phone, phone, email,
                gender, date_of_birth, marital_status,
                address, education_level, occupation, profession,
                country, region, state_region, sub_city, city,
                current_church, service_areas, service_interest,
                center, membership_plan, baptized,
                payment_status, membership_form_complete,
                registration_date, status, created_at
            ) VALUES (
                :member_id, :full_name, :first_name, :last_name,
                :mobile_phone, :phone, :email,
                :gender, :date_of_birth, :marital_status,
                :address, :education_level, :occupation, :profession,
                :country, :region, :state_region, :sub_city, :city,
                :current_church, :service_areas, :service_interest,
                :center, :membership_plan, 'yes',
                'unpaid', 0,
                NOW(), 'pending', NOW()
            )
        ");

        $serviceAreas = is_array($d['serviceAreas'] ?? null)
            ? implode(',', $d['serviceAreas'])
            : ($d['service_interest'] ?? null);

        $stmt->execute([
            ':member_id'        => $memberId,
            ':full_name'        => $fullName,
            ':first_name'       => $firstName,
            ':last_name'        => $lastName,
            ':mobile_phone'     => $phone,
            ':phone'            => $phone,
            ':email'            => $email,
            ':gender'           => $d['gender'] ?? null,
            ':date_of_birth'    => $d['dateOfBirth'] ?? null,
            ':marital_status'   => $d['maritalStatus'] ?? null,
            ':address'          => $d['currentAddress'] ?? $d['address'] ?? null,
            ':education_level'  => $d['educationLevel'] ?? null,
            ':occupation'       => $d['occupation'] ?? null,
            ':profession'       => $d['occupation'] ?? $d['profession'] ?? null,
            ':country'          => $d['country'] ?? 'Ethiopia',
            ':region'           => $d['region'] ?? null,
            ':state_region'     => $d['region'] ?? null,
            ':sub_city'         => $d['subCity'] ?? null,
            ':city'             => $d['subCity'] ?? $d['city'] ?? null,
            ':current_church'   => $d['currentChurch'] ?? null,
            ':service_areas'    => $serviceAreas,
            ':service_interest' => $serviceAreas,
            ':center'           => $d['preferredCenter'] ?? null,
            ':membership_plan'  => $d['membershipPlan'] ?? 'basic',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ── Insert user row ───────────────────────────────────────────────────────

    private function insertUser(
        string $memberId, string $firstName, string $lastName,
        string $fullName, array $d
    ): int {
        $phone    = $d['mobilePhone'] ?? $d['phone'] ?? '';
        $email    = strtolower(trim($d['email'] ?? '')) ?: null;
        $username = $d['username'] ?? strtolower(str_replace(' ', '.', $fullName));

        // Make username unique
        $base = $username; $i = 1;
        while ($this->rowExists('users', 'username', $username)) {
            $username = $base . $i++;
        }

        $hash = password_hash($d['password'] ?? $phone, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, email, password_hash, role, status, member_id, full_name, phone)
            VALUES (:username, :email, :hash, 'user', 'active', :member_id, :full_name, :phone)
        ");
        $stmt->execute([
            ':username'   => $username,
            ':email'      => $email,
            ':hash'       => $hash,
            ':member_id'  => $memberId,
            ':full_name'  => $fullName,
            ':phone'      => $phone,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    // ── Registration log ──────────────────────────────────────────────────────

    private function logRegistration(string $memberId, string $email, int $userId): void {
        $this->pdo->prepare("
            INSERT INTO registration_log (action, member_id, email, user_id, ip_address, user_agent, timestamp)
            VALUES ('member_registration', :mid, :email, :uid, :ip, :ua, NOW())
        ")->execute([
            ':mid'   => $memberId,
            ':email' => $email,
            ':uid'   => $userId,
            ':ip'    => $_SERVER['REMOTE_ADDR']     ?? 'unknown',
            ':ua'    => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
    }

    // ── Schema bootstrap ──────────────────────────────────────────────────────

    private function ensureSchema(): void {
        // users table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                username      VARCHAR(100) UNIQUE NOT NULL,
                email         VARCHAR(255) UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role          ENUM('user','admin','superadmin') DEFAULT 'user',
                status        ENUM('active','inactive','pending') DEFAULT 'active',
                member_id     VARCHAR(50),
                full_name     VARCHAR(200),
                phone         VARCHAR(30),
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_username  (username),
                INDEX idx_member_id (member_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // registration_log table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS registration_log (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                action     VARCHAR(50) NOT NULL,
                member_id  VARCHAR(50),
                email      VARCHAR(255),
                user_id    INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                timestamp  DATETIME NOT NULL,
                INDEX idx_member_id (member_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Relax NOT NULL constraints that block quick registration
        foreach ([
            "ALTER TABLE members MODIFY COLUMN gender        ENUM('male','female','other') NULL",
            "ALTER TABLE members MODIFY COLUMN address       TEXT NULL",
            "ALTER TABLE members MODIFY COLUMN baptized      ENUM('yes','no') NULL",
        ] as $sql) {
            try { $this->pdo->exec($sql); } catch (Exception $e) { /* ignore */ }
        }

        // Add any missing columns
        foreach ([
            "ALTER TABLE members ADD COLUMN IF NOT EXISTS full_name        VARCHAR(255) NULL AFTER member_id",
            "ALTER TABLE members ADD COLUMN IF NOT EXISTS occupation       VARCHAR(200) NULL",
            "ALTER TABLE members ADD COLUMN IF NOT EXISTS sub_city         VARCHAR(100) NULL",
            "ALTER TABLE members ADD COLUMN IF NOT EXISTS registration_date DATETIME NULL",
            "ALTER TABLE members ADD COLUMN IF NOT EXISTS membership_form_complete TINYINT(1) DEFAULT 0",
        ] as $sql) {
            try { $this->pdo->exec($sql); } catch (Exception $e) { /* ignore */ }
        }
    }
}
