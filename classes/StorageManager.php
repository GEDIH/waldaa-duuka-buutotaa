<?php
/**
 * StorageManager Class
 * 
 * Manages backup file storage, organization, and retention.
 * Handles directory structure creation, metadata management, and file operations.
 * 
 * Requirements: 1.4, 1.5, 1.6, 3.1, 3.2, 3.5, 4.1, 4.2, 4.3, 4.4, 4.5
 */

class StorageManager {
    private $backup_root;
    private $retention_days;
    
    /**
     * Constructor
     * 
     * @param string $backup_root Root directory for backup storage
     * @param int $retention_days Number of days to keep backups
     */
    public function __construct(string $backup_root, int $retention_days = 30) {
        $this->backup_root = rtrim($backup_root, '/\\');
        $this->retention_days = $retention_days;
        
        // Ensure backup root exists with secure permissions
        $this->ensureBackupRootExists();
    }
    
    /**
     * Ensure backup root directory exists with secure permissions
     * Creates directory with 0700 permissions (owner read/write/execute only)
     * 
     * Requirements: 4.4, 4.5
     */
    private function ensureBackupRootExists(): void {
        if (!file_exists($this->backup_root)) {
            if (!mkdir($this->backup_root, 0700, true)) {
                throw new Exception("Failed to create backup root directory: {$this->backup_root}");
            }
        }
        
        // Set secure permissions on existing directory
        chmod($this->backup_root, 0700);
    }
    
    /**
     * Generate directory path for a given date
     * Creates YYYY/MM/DD directory structure
     * 
     * Requirements: 4.2
     * 
     * @param string $date Date in Y-m-d format (default: today)
     * @return string Full directory path
     */
    public function getDateDirectory(string $date = null): string {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            throw new Exception("Invalid date format. Expected Y-m-d, got: $date");
        }
        
        list($year, $month, $day) = $parts;
        
        return $this->backup_root . DIRECTORY_SEPARATOR . 
               $year . DIRECTORY_SEPARATOR . 
               $month . DIRECTORY_SEPARATOR . 
               $day;
    }
    
    /**
     * Create directory structure for a given date
     * Creates YYYY/MM/DD directories with secure permissions
     * 
     * Requirements: 4.2, 4.4
     * 
     * @param string $date Date in Y-m-d format (default: today)
     * @return string Full directory path
     */
    public function createDateDirectory(string $date = null): string {
        $dir = $this->getDateDirectory($date);
        
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0700, true)) {
                throw new Exception("Failed to create date directory: $dir");
            }
        }
        
        // Ensure secure permissions on all parent directories
        $this->setSecurePermissions($dir);
        
        return $dir;
    }
    
    /**
     * Set secure permissions (0700) on directory and all parent directories
     * 
     * Requirements: 4.4
     * 
     * @param string $dir Directory path
     */
    private function setSecurePermissions(string $dir): void {
        $current = $dir;
        
        while ($current !== $this->backup_root && $current !== dirname($current)) {
            if (file_exists($current) && is_dir($current)) {
                chmod($current, 0700);
            }
            $current = dirname($current);
        }
    }
    
    /**
     * Generate backup ID from timestamp
     * Format: backup_YYYY-MM-DD_HH-MM-SS
     * 
     * Requirements: 1.4
     * 
     * @param int $timestamp Unix timestamp (default: now)
     * @return string Backup ID
     */
    public function generateBackupId(int $timestamp = null): string {
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        return 'backup_' . date('Y-m-d_H-i-s', $timestamp);
    }
    
    /**
     * Parse backup ID to extract date and time
     * 
     * @param string $backup_id Backup ID
     * @return array Array with 'date' and 'time' keys
     */
    public function parseBackupId(string $backup_id): array {
        // Remove 'backup_' prefix
        $parts = str_replace('backup_', '', $backup_id);
        
        // Split into date and time
        $datetime = explode('_', $parts);
        
        if (count($datetime) !== 2) {
            throw new Exception("Invalid backup ID format: $backup_id");
        }
        
        return [
            'date' => $datetime[0],
            'time' => str_replace('-', ':', $datetime[1]),
            'datetime' => $datetime[0] . ' ' . str_replace('-', ':', $datetime[1])
        ];
    }
    
    /**
     * Get full file path for a backup
     * 
     * Requirements: 4.2
     * 
     * @param string $backup_id Backup ID
     * @return string Full file path
     */
    public function getBackupFilePath(string $backup_id): string {
        $parsed = $this->parseBackupId($backup_id);
        $dir = $this->getDateDirectory($parsed['date']);
        
        return $dir . DIRECTORY_SEPARATOR . $backup_id . '.sql.gz';
    }
    
    /**
     * Get full file path for backup metadata
     * 
     * Requirements: 4.3
     * 
     * @param string $backup_id Backup ID
     * @return string Full metadata file path
     */
    public function getMetadataFilePath(string $backup_id): string {
        $parsed = $this->parseBackupId($backup_id);
        $dir = $this->getDateDirectory($parsed['date']);
        
        return $dir . DIRECTORY_SEPARATOR . $backup_id . '.meta.json';
    }
    
    /**
     * Get backup root directory
     * 
     * @return string Backup root path
     */
    public function getBackupRoot(): string {
        return $this->backup_root;
    }
    
    /**
     * Get retention days setting
     * 
     * @return int Retention days
     */
    public function getRetentionDays(): int {
        return $this->retention_days;
    }
    
    /**
     * Set retention days
     * 
     * @param int $days Number of days to keep backups
     */
    public function setRetentionDays(int $days): void {
        if ($days < 1) {
            throw new Exception("Retention days must be at least 1");
        }
        
        $this->retention_days = $days;
    }

    /**
     * Store backup file with metadata
     * Moves temporary backup file to organized storage and creates metadata file
     * 
     * Requirements: 1.4, 1.6, 4.2, 4.3
     * 
     * @param string $temp_file Path to temporary backup file
     * @param array $metadata Backup metadata
     * @return array Result with 'success', 'backup_id', 'file_path'
     */
    public function storeBackup(string $temp_file, array $metadata): array {
        try {
            // Validate temp file exists
            if (!file_exists($temp_file)) {
                throw new Exception("Temporary backup file not found: $temp_file");
            }
            
            // Generate backup ID
            $backup_id = $this->generateBackupId();
            
            // Create date directory
            $parsed = $this->parseBackupId($backup_id);
            $dir = $this->createDateDirectory($parsed['date']);
            
            // Get destination paths
            $backup_path = $this->getBackupFilePath($backup_id);
            $metadata_path = $this->getMetadataFilePath($backup_id);
            
            // Move backup file to destination
            if (!rename($temp_file, $backup_path)) {
                throw new Exception("Failed to move backup file to: $backup_path");
            }
            
            // Add backup_id to metadata
            $metadata['backup_id'] = $backup_id;
            $metadata['file_path'] = $backup_path;
            
            // Create metadata file
            if (!$this->saveMetadata($backup_id, $metadata)) {
                // Cleanup backup file if metadata creation fails
                @unlink($backup_path);
                throw new Exception("Failed to create metadata file");
            }
            
            return [
                'success' => true,
                'backup_id' => $backup_id,
                'file_path' => $backup_path,
                'metadata_path' => $metadata_path
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Save metadata to JSON file
     * 
     * Requirements: 1.6, 4.3
     * 
     * @param string $backup_id Backup ID
     * @param array $metadata Metadata array
     * @return bool Success status
     */
    private function saveMetadata(string $backup_id, array $metadata): bool {
        $metadata_path = $this->getMetadataFilePath($backup_id);
        
        // Ensure required fields exist
        $required_fields = ['backup_id', 'created_at', 'database', 'db_version', 
                           'file_size', 'compressed', 'type', 'created_by'];
        
        foreach ($required_fields as $field) {
            if (!isset($metadata[$field])) {
                $metadata[$field] = null;
            }
        }
        
        // Add human-readable file size
        if (isset($metadata['file_size'])) {
            $metadata['file_size_human'] = $this->formatBytes($metadata['file_size']);
        }
        
        $json = json_encode($metadata, JSON_PRETTY_PRINT);
        
        if ($json === false) {
            return false;
        }
        
        return file_put_contents($metadata_path, $json) !== false;
    }
    
    /**
     * Get backup metadata
     * 
     * Requirements: 1.6, 4.3
     * 
     * @param string $backup_id Backup ID
     * @return array|null Metadata array or null if not found
     */
    public function getBackupMetadata(string $backup_id): ?array {
        $metadata_path = $this->getMetadataFilePath($backup_id);
        
        if (!file_exists($metadata_path)) {
            return null;
        }
        
        $json = file_get_contents($metadata_path);
        
        if ($json === false) {
            return null;
        }
        
        $metadata = json_decode($json, true);
        
        return $metadata ?: null;
    }
    
    /**
     * Get backup file path
     * Returns path if file exists, null otherwise
     * 
     * Requirements: 4.2
     * 
     * @param string $backup_id Backup ID
     * @return string|null File path or null if not found
     */
    public function getBackupPath(string $backup_id): ?string {
        $path = $this->getBackupFilePath($backup_id);
        
        return file_exists($path) ? $path : null;
    }
    
    /**
     * Format bytes to human-readable size
     * 
     * @param int $bytes File size in bytes
     * @param int $precision Decimal precision
     * @return string Formatted size
     */
    private function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * List all backups with metadata
     * Returns backups sorted by date in descending order (newest first)
     * 
     * Requirements: 3.1, 3.2
     * 
     * @param string $start_date Optional start date filter (Y-m-d)
     * @param string $end_date Optional end date filter (Y-m-d)
     * @return array Array of backup objects
     */
    public function listBackups(string $start_date = null, string $end_date = null): array {
        $backups = [];
        
        // Scan backup root for year directories
        if (!is_dir($this->backup_root)) {
            return $backups;
        }
        
        $this->scanBackupDirectory($this->backup_root, $backups, $start_date, $end_date);
        
        // Sort by creation date descending (newest first)
        usort($backups, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        
        return $backups;
    }
    
    /**
     * Recursively scan backup directory for backup files
     * 
     * @param string $dir Directory to scan
     * @param array &$backups Reference to backups array
     * @param string $start_date Optional start date filter
     * @param string $end_date Optional end date filter
     */
    private function scanBackupDirectory(string $dir, array &$backups, 
                                        string $start_date = null, 
                                        string $end_date = null): void {
        $items = @scandir($dir);
        
        if ($items === false) {
            return;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($path)) {
                // Recursively scan subdirectories
                $this->scanBackupDirectory($path, $backups, $start_date, $end_date);
            } elseif (is_file($path) && preg_match('/^backup_.*\.sql\.gz$/', $item)) {
                // Found a backup file
                $backup_id = str_replace('.sql.gz', '', $item);
                
                // Get metadata
                $metadata = $this->getBackupMetadata($backup_id);
                
                if ($metadata) {
                    // Apply date range filter
                    if ($this->isInDateRange($metadata['created_at'], $start_date, $end_date)) {
                        // Add file status
                        $metadata['status'] = file_exists($path) ? 'available' : 'missing';
                        $metadata['file_path'] = $path;
                        
                        $backups[] = $metadata;
                    }
                }
            }
        }
    }
    
    /**
     * Check if date is within range
     * 
     * Requirements: 3.5
     * 
     * @param string $date Date to check
     * @param string $start_date Start date (inclusive)
     * @param string $end_date End date (inclusive)
     * @return bool True if in range
     */
    private function isInDateRange(string $date, string $start_date = null, 
                                   string $end_date = null): bool {
        if ($start_date !== null && $date < $start_date) {
            return false;
        }
        
        if ($end_date !== null && $date > $end_date) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Calculate total storage used by backups
     * 
     * @return int Total bytes used
     */
    public function getTotalStorageUsed(): int {
        $total = 0;
        $backups = $this->listBackups();
        
        foreach ($backups as $backup) {
            if (isset($backup['file_size'])) {
                $total += $backup['file_size'];
            }
        }
        
        return $total;
    }
    
    /**
     * Get storage statistics
     * 
     * @return array Statistics array
     */
    public function getStorageStats(): array {
        $backups = $this->listBackups();
        $total_size = 0;
        $oldest = null;
        $newest = null;
        
        foreach ($backups as $backup) {
            if (isset($backup['file_size'])) {
                $total_size += $backup['file_size'];
            }
            
            if ($oldest === null || $backup['created_at'] < $oldest) {
                $oldest = $backup['created_at'];
            }
            
            if ($newest === null || $backup['created_at'] > $newest) {
                $newest = $backup['created_at'];
            }
        }
        
        return [
            'total_backups' => count($backups),
            'total_size' => $total_size,
            'total_size_human' => $this->formatBytes($total_size),
            'oldest_backup' => $oldest,
            'newest_backup' => $newest,
            'backup_root' => $this->backup_root,
            'retention_days' => $this->retention_days
        ];
    }

    /**
     * Delete specific backup
     * Removes backup file and metadata file
     * 
     * Requirements: 1.5, 3.6
     * 
     * @param string $backup_id Backup ID to delete
     * @return array Result with 'success' and 'message'
     */
    public function deleteBackup(string $backup_id): array {
        try {
            $backup_path = $this->getBackupFilePath($backup_id);
            $metadata_path = $this->getMetadataFilePath($backup_id);
            
            $deleted_files = [];
            
            // Delete backup file
            if (file_exists($backup_path)) {
                if (unlink($backup_path)) {
                    $deleted_files[] = 'backup file';
                } else {
                    throw new Exception("Failed to delete backup file: $backup_path");
                }
            }
            
            // Delete metadata file
            if (file_exists($metadata_path)) {
                if (unlink($metadata_path)) {
                    $deleted_files[] = 'metadata file';
                } else {
                    throw new Exception("Failed to delete metadata file: $metadata_path");
                }
            }
            
            if (empty($deleted_files)) {
                return [
                    'success' => false,
                    'message' => "Backup not found: $backup_id"
                ];
            }
            
            // Try to remove empty parent directories
            $this->cleanupEmptyDirectories($backup_path);
            
            return [
                'success' => true,
                'message' => "Deleted backup: $backup_id",
                'deleted_files' => $deleted_files
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up empty parent directories
     * Removes empty date directories after backup deletion
     * 
     * @param string $file_path Path to deleted file
     */
    private function cleanupEmptyDirectories(string $file_path): void {
        $dir = dirname($file_path);
        
        // Don't delete backup root
        while ($dir !== $this->backup_root && $dir !== dirname($dir)) {
            $items = @scandir($dir);
            
            // If directory only contains . and .., it's empty
            if ($items && count($items) === 2) {
                @rmdir($dir);
                $dir = dirname($dir);
            } else {
                break;
            }
        }
    }
    
    /**
     * Apply retention policy
     * Deletes backups older than retention_days
     * 
     * Requirements: 1.5
     * 
     * @return array Result with 'deleted_count' and 'deleted_ids'
     */
    public function applyRetentionPolicy(): array {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$this->retention_days} days"));
        
        $backups = $this->listBackups();
        $deleted_ids = [];
        $deleted_count = 0;
        
        foreach ($backups as $backup) {
            if ($backup['created_at'] < $cutoff_date) {
                $result = $this->deleteBackup($backup['backup_id']);
                
                if ($result['success']) {
                    $deleted_ids[] = $backup['backup_id'];
                    $deleted_count++;
                }
            }
        }
        
        return [
            'deleted_count' => $deleted_count,
            'deleted_ids' => $deleted_ids,
            'cutoff_date' => $cutoff_date,
            'retention_days' => $this->retention_days
        ];
    }
    
    /**
     * Apply retention policy by count
     * Keeps only the N most recent backups
     * 
     * Requirements: 1.5
     * 
     * @param int $keep_count Number of backups to keep
     * @return array Result with 'deleted_count' and 'deleted_ids'
     */
    public function applyRetentionPolicyByCount(int $keep_count): array {
        if ($keep_count < 1) {
            throw new Exception("Keep count must be at least 1");
        }
        
        $backups = $this->listBackups();
        $deleted_ids = [];
        $deleted_count = 0;
        
        // Backups are already sorted newest first
        // Delete backups beyond the keep count
        for ($i = $keep_count; $i < count($backups); $i++) {
            $result = $this->deleteBackup($backups[$i]['backup_id']);
            
            if ($result['success']) {
                $deleted_ids[] = $backups[$i]['backup_id'];
                $deleted_count++;
            }
        }
        
        return [
            'deleted_count' => $deleted_count,
            'deleted_ids' => $deleted_ids,
            'keep_count' => $keep_count,
            'total_backups_before' => count($backups),
            'total_backups_after' => count($backups) - $deleted_count
        ];
    }
}
