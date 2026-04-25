<?php
/**
 * BackupScheduler Class
 * 
 * Manages backup scheduling and execution timing.
 * Handles schedule configuration, validation, and execution logic.
 * 
 * Requirements: 1.1, 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7
 */

require_once 'BackupEngine.php';

class BackupScheduler {
    private $db_connection;
    private $backup_engine;
    
    /**
     * Constructor
     * 
     * Requirements: 8.1
     * 
     * @param BackupEngine $backup_engine BackupEngine instance
     * @param PDO $db_connection Optional database connection
     */
    public function __construct(BackupEngine $backup_engine, $db_connection = null) {
        $this->backup_engine = $backup_engine;
        
        if ($db_connection === null) {
            // Create default connection
            try {
                $this->db_connection = new PDO(
                    'mysql:host=localhost;port=3306;dbname=wdb_membership',
                    'root',
                    '',
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (PDOException $e) {
                throw new Exception("Failed to connect to database: " . $e->getMessage());
            }
        } else {
            $this->db_connection = $db_connection;
        }
    }
    
    /**
     * Get current schedule configuration
     * 
     * Requirements: 8.1, 8.2, 8.3, 8.4, 8.5
     * 
     * @return array Configuration with 'frequency', 'time', 'day_of_week', 'day_of_month', 'retention_days'
     */
    public function getScheduleConfig(): array {
        try {
            $stmt = $this->db_connection->query("
                SELECT setting_key, setting_value 
                FROM backup_settings 
                WHERE setting_key IN (
                    'backup_frequency', 
                    'backup_time', 
                    'backup_day_of_week', 
                    'backup_day_of_month', 
                    'retention_days',
                    'email_notifications',
                    'last_backup_time'
                )
            ");
            
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Return structured configuration
            return [
                'frequency' => $settings['backup_frequency'] ?? 'daily',
                'time' => $settings['backup_time'] ?? '02:00',
                'day_of_week' => (int)($settings['backup_day_of_week'] ?? 0),
                'day_of_month' => (int)($settings['backup_day_of_month'] ?? 1),
                'retention_days' => (int)($settings['retention_days'] ?? 30),
                'email_notifications' => $settings['email_notifications'] ?? 'enabled',
                'last_backup_time' => $settings['last_backup_time'] ?? null
            ];
            
        } catch (PDOException $e) {
            throw new Exception("Failed to get schedule configuration: " . $e->getMessage());
        }
    }
    
    /**
     * Update schedule configuration
     * 
     * Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6
     * 
     * @param array $config Configuration array
     * @return array Result with 'success' and 'message'
     */
    public function updateScheduleConfig(array $config): array {
        try {
            // Validate configuration
            $validation = $this->validateScheduleConfig($config);
            
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Invalid configuration',
                    'errors' => $validation['errors']
                ];
            }
            
            // Prepare statement for updating settings
            $stmt = $this->db_connection->prepare("
                INSERT INTO backup_settings (setting_key, setting_value, updated_by) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $updated_by = $config['updated_by'] ?? 1;
            $updated_count = 0;
            
            // Update frequency
            if (isset($config['frequency'])) {
                $stmt->execute(['backup_frequency', $config['frequency'], $updated_by]);
                $updated_count++;
            }
            
            // Update time
            if (isset($config['time'])) {
                $stmt->execute(['backup_time', $config['time'], $updated_by]);
                $updated_count++;
            }
            
            // Update day_of_week (for weekly backups)
            if (isset($config['day_of_week'])) {
                $stmt->execute(['backup_day_of_week', (string)$config['day_of_week'], $updated_by]);
                $updated_count++;
            }
            
            // Update day_of_month (for monthly backups)
            if (isset($config['day_of_month'])) {
                $stmt->execute(['backup_day_of_month', (string)$config['day_of_month'], $updated_by]);
                $updated_count++;
            }
            
            // Update retention_days
            if (isset($config['retention_days'])) {
                $stmt->execute(['retention_days', (string)$config['retention_days'], $updated_by]);
                $updated_count++;
            }
            
            // Update email_notifications
            if (isset($config['email_notifications'])) {
                $stmt->execute(['email_notifications', $config['email_notifications'], $updated_by]);
                $updated_count++;
            }
            
            return [
                'success' => true,
                'message' => "Schedule configuration updated successfully",
                'updated_count' => $updated_count,
                'next_backup_time' => $this->getNextBackupTime()
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => "Failed to update schedule configuration: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate schedule configuration
     * 
     * Requirements: 8.6
     * 
     * @param array $config Configuration to validate
     * @return array Validation result with 'valid' and 'errors'
     */
    private function validateScheduleConfig(array $config): array {
        $errors = [];
        
        // Validate frequency
        if (isset($config['frequency'])) {
            $valid_frequencies = ['daily', 'weekly', 'monthly'];
            if (!in_array($config['frequency'], $valid_frequencies)) {
                $errors[] = "Invalid frequency. Must be one of: " . implode(', ', $valid_frequencies);
            }
        }
        
        // Validate time format (HH:MM)
        if (isset($config['time'])) {
            if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $config['time'])) {
                $errors[] = "Invalid time format. Must be HH:MM (24-hour format)";
            }
        }
        
        // Validate day_of_week (0-6, Sunday-Saturday)
        if (isset($config['day_of_week'])) {
            $day = (int)$config['day_of_week'];
            if ($day < 0 || $day > 6) {
                $errors[] = "Invalid day_of_week. Must be 0-6 (Sunday-Saturday)";
            }
        }
        
        // Validate day_of_month (1-31)
        if (isset($config['day_of_month'])) {
            $day = (int)$config['day_of_month'];
            if ($day < 1 || $day > 31) {
                $errors[] = "Invalid day_of_month. Must be 1-31";
            }
        }
        
        // Validate retention_days (minimum 1)
        if (isset($config['retention_days'])) {
            $days = (int)$config['retention_days'];
            if ($days < 1) {
                $errors[] = "Invalid retention_days. Must be at least 1";
            }
        }
        
        // Validate email_notifications
        if (isset($config['email_notifications'])) {
            $valid_values = ['enabled', 'disabled'];
            if (!in_array($config['email_notifications'], $valid_values)) {
                $errors[] = "Invalid email_notifications. Must be 'enabled' or 'disabled'";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    
    /**
     * Check if backup should run now
     * 
     * Requirements: 1.1, 8.7
     * 
     * @return bool True if backup should execute
     */
    public function shouldRunBackup(): bool {
        try {
            $config = $this->getScheduleConfig();
            $now = new DateTime();
            
            // Get last backup time
            $last_backup = $config['last_backup_time'];
            
            if (empty($last_backup)) {
                // No backup has run yet, should run now
                return true;
            }
            
            $last_backup_dt = new DateTime($last_backup);
            
            // Check based on frequency
            switch ($config['frequency']) {
                case 'daily':
                    return $this->shouldRunDailyBackup($now, $last_backup_dt, $config['time']);
                    
                case 'weekly':
                    return $this->shouldRunWeeklyBackup($now, $last_backup_dt, $config['time'], $config['day_of_week']);
                    
                case 'monthly':
                    return $this->shouldRunMonthlyBackup($now, $last_backup_dt, $config['time'], $config['day_of_month']);
                    
                default:
                    return false;
            }
            
        } catch (Exception $e) {
            error_log("BackupScheduler: Error checking if backup should run - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if daily backup should run
     * 
     * @param DateTime $now Current time
     * @param DateTime $last_backup Last backup time
     * @param string $scheduled_time Scheduled time (HH:MM)
     * @return bool True if should run
     */
    private function shouldRunDailyBackup(DateTime $now, DateTime $last_backup, string $scheduled_time): bool {
        // Parse scheduled time
        list($hour, $minute) = explode(':', $scheduled_time);
        
        // Create scheduled time for today
        $scheduled_today = clone $now;
        $scheduled_today->setTime((int)$hour, (int)$minute, 0);
        
        // If current time is past scheduled time today
        if ($now >= $scheduled_today) {
            // Check if last backup was before today's scheduled time
            if ($last_backup < $scheduled_today) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if weekly backup should run
     * 
     * @param DateTime $now Current time
     * @param DateTime $last_backup Last backup time
     * @param string $scheduled_time Scheduled time (HH:MM)
     * @param int $day_of_week Day of week (0-6, Sunday-Saturday)
     * @return bool True if should run
     */
    private function shouldRunWeeklyBackup(DateTime $now, DateTime $last_backup, string $scheduled_time, int $day_of_week): bool {
        // Check if today is the scheduled day
        if ((int)$now->format('w') !== $day_of_week) {
            return false;
        }
        
        // Parse scheduled time
        list($hour, $minute) = explode(':', $scheduled_time);
        
        // Create scheduled time for today
        $scheduled_today = clone $now;
        $scheduled_today->setTime((int)$hour, (int)$minute, 0);
        
        // If current time is past scheduled time today
        if ($now >= $scheduled_today) {
            // Check if last backup was before today's scheduled time
            if ($last_backup < $scheduled_today) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if monthly backup should run
     * 
     * @param DateTime $now Current time
     * @param DateTime $last_backup Last backup time
     * @param string $scheduled_time Scheduled time (HH:MM)
     * @param int $day_of_month Day of month (1-31)
     * @return bool True if should run
     */
    private function shouldRunMonthlyBackup(DateTime $now, DateTime $last_backup, string $scheduled_time, int $day_of_month): bool {
        // Check if today is the scheduled day of month
        $current_day = (int)$now->format('j');
        
        // Handle case where day_of_month is greater than days in current month
        $days_in_month = (int)$now->format('t');
        $target_day = min($day_of_month, $days_in_month);
        
        if ($current_day !== $target_day) {
            return false;
        }
        
        // Parse scheduled time
        list($hour, $minute) = explode(':', $scheduled_time);
        
        // Create scheduled time for today
        $scheduled_today = clone $now;
        $scheduled_today->setTime((int)$hour, (int)$minute, 0);
        
        // If current time is past scheduled time today
        if ($now >= $scheduled_today) {
            // Check if last backup was before today's scheduled time
            if ($last_backup < $scheduled_today) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get next scheduled backup time
     * 
     * Requirements: 8.7
     * 
     * @return string Datetime of next backup (Y-m-d H:i:s format)
     */
    public function getNextBackupTime(): string {
        try {
            $config = $this->getScheduleConfig();
            $now = new DateTime();
            
            switch ($config['frequency']) {
                case 'daily':
                    return $this->getNextDailyBackupTime($now, $config['time']);
                    
                case 'weekly':
                    return $this->getNextWeeklyBackupTime($now, $config['time'], $config['day_of_week']);
                    
                case 'monthly':
                    return $this->getNextMonthlyBackupTime($now, $config['time'], $config['day_of_month']);
                    
                default:
                    return 'Unknown';
            }
            
        } catch (Exception $e) {
            error_log("BackupScheduler: Error calculating next backup time - " . $e->getMessage());
            return 'Error';
        }
    }
    
    /**
     * Get next daily backup time
     * 
     * @param DateTime $now Current time
     * @param string $scheduled_time Scheduled time (HH:MM)
     * @return string Next backup datetime
     */
    private function getNextDailyBackupTime(DateTime $now, string $scheduled_time): string {
        list($hour, $minute) = explode(':', $scheduled_time);
        
        // Create scheduled time for today
        $next = clone $now;
        $next->setTime((int)$hour, (int)$minute, 0);
        
        // If scheduled time today has passed, move to tomorrow
        if ($next <= $now) {
            $next->modify('+1 day');
        }
        
        return $next->format('Y-m-d H:i:s');
    }
    
    /**
     * Get next weekly backup time
     * 
     * @param DateTime $now Current time
     * @param string $scheduled_time Scheduled time (HH:MM)
     * @param int $day_of_week Day of week (0-6)
     * @return string Next backup datetime
     */
    private function getNextWeeklyBackupTime(DateTime $now, string $scheduled_time, int $day_of_week): string {
        list($hour, $minute) = explode(':', $scheduled_time);
        
        $next = clone $now;
        $next->setTime((int)$hour, (int)$minute, 0);
        
        $current_day = (int)$now->format('w');
        
        if ($current_day === $day_of_week) {
            // Today is the scheduled day
            if ($next <= $now) {
                // Scheduled time has passed, move to next week
                $next->modify('+7 days');
            }
        } else {
            // Calculate days until next scheduled day
            $days_ahead = $day_of_week - $current_day;
            if ($days_ahead < 0) {
                $days_ahead += 7;
            }
            $next->modify("+{$days_ahead} days");
        }
        
        return $next->format('Y-m-d H:i:s');
    }
    
    /**
     * Get next monthly backup time
     * 
     * @param DateTime $now Current time
     * @param string $scheduled_time Scheduled time (HH:MM)
     * @param int $day_of_month Day of month (1-31)
     * @return string Next backup datetime
     */
    private function getNextMonthlyBackupTime(DateTime $now, string $scheduled_time, int $day_of_month): string {
        list($hour, $minute) = explode(':', $scheduled_time);
        
        $next = clone $now;
        $next->setTime((int)$hour, (int)$minute, 0);
        
        $current_day = (int)$now->format('j');
        $days_in_month = (int)$now->format('t');
        $target_day = min($day_of_month, $days_in_month);
        
        if ($current_day === $target_day) {
            // Today is the scheduled day
            if ($next <= $now) {
                // Scheduled time has passed, move to next month
                $next->modify('first day of next month');
                $next_days_in_month = (int)$next->format('t');
                $next_target_day = min($day_of_month, $next_days_in_month);
                $next->setDate((int)$next->format('Y'), (int)$next->format('m'), $next_target_day);
            }
        } elseif ($current_day < $target_day) {
            // Scheduled day is later this month
            $next->setDate((int)$next->format('Y'), (int)$next->format('m'), $target_day);
        } else {
            // Scheduled day has passed this month, move to next month
            $next->modify('first day of next month');
            $next_days_in_month = (int)$next->format('t');
            $next_target_day = min($day_of_month, $next_days_in_month);
            $next->setDate((int)$next->format('Y'), (int)$next->format('m'), $next_target_day);
        }
        
        return $next->format('Y-m-d H:i:s');
    }
    
    /**
     * Execute scheduled backup
     * 
     * Requirements: 1.1
     * 
     * @return array Backup result
     */
    public function executeScheduledBackup(): array {
        try {
            // Create backup using BackupEngine
            $result = $this->backup_engine->createBackup('scheduled', 0); // 0 = system user
            
            if ($result['success']) {
                // Update last backup time
                $this->updateLastBackupTime();
            }
            
            return $result;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => "Failed to execute scheduled backup: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update last backup time in settings
     * 
     * @return bool Success status
     */
    private function updateLastBackupTime(): bool {
        try {
            $stmt = $this->db_connection->prepare("
                INSERT INTO backup_settings (setting_key, setting_value) 
                VALUES ('last_backup_time', ?)
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            return $stmt->execute([date('Y-m-d H:i:s')]);
            
        } catch (PDOException $e) {
            error_log("BackupScheduler: Failed to update last backup time - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get schedule summary
     * 
     * @return array Summary with configuration and next backup time
     */
    public function getScheduleSummary(): array {
        try {
            $config = $this->getScheduleConfig();
            $next_backup = $this->getNextBackupTime();
            
            // Format frequency description
            $frequency_desc = $this->getFrequencyDescription($config);
            
            return [
                'success' => true,
                'frequency' => $config['frequency'],
                'frequency_description' => $frequency_desc,
                'time' => $config['time'],
                'retention_days' => $config['retention_days'],
                'email_notifications' => $config['email_notifications'],
                'last_backup_time' => $config['last_backup_time'],
                'next_backup_time' => $next_backup,
                'should_run_now' => $this->shouldRunBackup()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get human-readable frequency description
     * 
     * @param array $config Schedule configuration
     * @return string Description
     */
    private function getFrequencyDescription(array $config): string {
        switch ($config['frequency']) {
            case 'daily':
                return "Daily at {$config['time']}";
                
            case 'weekly':
                $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $day_name = $days[$config['day_of_week']] ?? 'Unknown';
                return "Weekly on {$day_name} at {$config['time']}";
                
            case 'monthly':
                $suffix = $this->getOrdinalSuffix($config['day_of_month']);
                return "Monthly on the {$config['day_of_month']}{$suffix} at {$config['time']}";
                
            default:
                return "Unknown frequency";
        }
    }
    
    /**
     * Get ordinal suffix for day number
     * 
     * @param int $day Day number
     * @return string Suffix (st, nd, rd, th)
     */
    private function getOrdinalSuffix(int $day): string {
        if ($day >= 11 && $day <= 13) {
            return 'th';
        }
        
        switch ($day % 10) {
            case 1: return 'st';
            case 2: return 'nd';
            case 3: return 'rd';
            default: return 'th';
        }
    }
}
