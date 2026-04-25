<?php
/**
 * RestoreEngine Class
 * 
 * Safely restores database from backup files with rollback capability.
 * Creates pre-restore backups and handles restore failures gracefully.
 * 
 * Requirements: 7.1, 7.2, 7.3, 7.5, 7.6, 7.7
 */

require_once 'BackupEngine.php';
require_once 'StorageManager.php';

class RestoreEngine {
    private $db_host;
    private $db_name;
    private $db_user;
    private $db_pass;
    private $db_port;
    private $backup_engine;
    private $storage_manager;
    private $mysql_path;
    
    /**
     * Constructor
     * 
     * @param BackupEngine $backup_engine BackupEngine instance
     * @param StorageManager $storage_manager StorageManager instance
     * @param string $db_host Database host (default: localhost)
     * @param string $db_name Database name (default: wdb_membership)
     * @param string $db_user Database user (default: root)
     * @param string $db_pass Database password (default: empty)
     * @param int $db_port Database port (default: 3306)
     */
    public function __construct(
        BackupEngine $backup_engine,
        StorageManager $storage_manager,
        string $db_host = 'localhost',
        string $db_name = 'wdb_membership',
        string $db_user = 'root',
        string $db_pass = '',
        int $db_port = 3306
    ) {
        $this->backup_engine = $backup_engine;
        $this->storage_manager = $storage_manager;
        $this->db_host = $db_host;
        $this->db_name = $db_name;
        $this->db_user = $db_user;
        $this->db_pass = $db_pass;
        $this->db_port = $db_port;
        
        // Detect mysql executable path
        $this->mysql_path = $this->detectMysqlPath();
    }
    
    /**
     * Detect mysql executable path
     * 
     * @return string Path to mysql
     */
    private function detectMysqlPath(): string {
        // Common paths for XAMPP on Windows
        $possible_paths = [
            'C:/xampp/mysql/bin/mysql.exe',
            'C:/xampp/mysql/bin/mysql',
            'mysql.exe',
            'mysql'
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Default to mysql in PATH
        return 'mysql';
    }
    
    /**
     * Set custom mysql path
     * 
     * @param string $path Path to mysql executable
     */
    public function setMysqlPath(string $path): void {
        $this->mysql_path = $path;
    }
    
    /**
     * Preview backup contents
     * Shows first 100 lines of SQL from backup
     * 
     * Requirements: 7.1
     * 
     * @param string $backup_id Backup ID to preview
     * @return array Result with 'success', 'metadata', 'preview'
     */
    public function previewBackup(string $backup_id): array {
        try {
            // Get backup metadata
            $metadata = $this->storage_manager->getBackupMetadata($backup_id);
            
            if (!$metadata) {
                throw new Exception("Backup not found: $backup_id");
            }
            
            // Get backup file path
            $backup_path = $this->storage_manager->getBackupPath($backup_id);
            
            if (!file_exists($backup_path)) {
                throw new Exception("Backup file not found: $backup_path");
            }
            
            // Check if backup is encrypted
            $file_to_read = $backup_path;
            $temp_decrypted = null;
            
            if (isset($metadata['encrypted']) && $metadata['encrypted'] === true) {
                // Decrypt to temp file first
                $temp_decrypted = sys_get_temp_dir() . '/preview_decrypt_' . time() . '_' . rand(1000, 9999) . '.gz';
                
                $decrypt_result = $this->backup_engine->decryptFile($backup_path, $temp_decrypted);
                
                if (!$decrypt_result['success']) {
                    throw new Exception("Failed to decrypt backup for preview: " . $decrypt_result['error']);
                }
                
                $file_to_read = $temp_decrypted;
            }
            
            // Decompress and read first 100 lines
            $preview_lines = [];
            $gz = gzopen($file_to_read, 'rb');
            
            // Clean up decrypted temp file if it exists
            if ($temp_decrypted && file_exists($temp_decrypted)) {
                @unlink($temp_decrypted);
            }
            
            if (!$gz) {
                throw new Exception("Failed to open backup file for preview");
            }
            
            $line_count = 0;
            while (!gzeof($gz) && $line_count < 100) {
                $line = gzgets($gz);
                if ($line !== false) {
                    $preview_lines[] = rtrim($line);
                    $line_count++;
                }
            }
            
            gzclose($gz);
            
            return [
                'success' => true,
                'backup_id' => $backup_id,
                'metadata' => $metadata,
                'preview' => implode("\n", $preview_lines),
                'preview_lines' => $line_count,
                'total_size' => $metadata['file_size'],
                'total_size_human' => $metadata['file_size_human'] ?? $this->formatBytes($metadata['file_size'])
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create pre-restore backup
     * Creates a backup of current database state before restore
     * 
     * Requirements: 7.2
     * 
     * @return array Result with 'success', 'backup_id', 'message'
     */
    public function createPreRestoreBackup(): array {
        try {
            // Create backup with special type
            $result = $this->backup_engine->createBackup('pre-restore', 1);
            
            if (!$result['success']) {
                throw new Exception("Failed to create pre-restore backup: " . ($result['error'] ?? 'Unknown error'));
            }
            
            return [
                'success' => true,
                'backup_id' => $result['backup_id'],
                'file_path' => $result['file_path'],
                'size' => $result['size'],
                'size_human' => $result['size_human'],
                'message' => 'Pre-restore backup created successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Restore database from backup
     * Decompresses backup and executes SQL statements
     * 
     * Requirements: 7.3, 7.5
     * 
     * @param string $backup_id Backup ID to restore
     * @param bool $create_pre_restore Whether to create pre-restore backup (default: true)
     * @return array Result with 'success', 'message', 'pre_restore_backup_id'
     */
    public function restoreFromBackup(string $backup_id, bool $create_pre_restore = true): array {
        $pre_restore_backup_id = null;
        $temp_sql = null;
        
        try {
            // Step 1: Validate backup exists
            $metadata = $this->storage_manager->getBackupMetadata($backup_id);
            
            if (!$metadata) {
                throw new Exception("Backup not found: $backup_id");
            }
            
            $backup_path = $this->storage_manager->getBackupPath($backup_id);
            
            if (!file_exists($backup_path)) {
                throw new Exception("Backup file not found: $backup_path");
            }
            
            // Step 2: Create pre-restore backup
            if ($create_pre_restore) {
                $pre_restore_result = $this->createPreRestoreBackup();
                
                if (!$pre_restore_result['success']) {
                    throw new Exception("Failed to create pre-restore backup: " . $pre_restore_result['error']);
                }
                
                $pre_restore_backup_id = $pre_restore_result['backup_id'];
            }
            
            // Step 3: Decrypt backup file if encrypted
            $file_to_decompress = $backup_path;
            $temp_decrypted = null;
            
            if (isset($metadata['encrypted']) && $metadata['encrypted'] === true) {
                $temp_decrypted = sys_get_temp_dir() . '/decrypt_' . time() . '_' . rand(1000, 9999) . '.gz';
                
                $decrypt_result = $this->backup_engine->decryptFile($backup_path, $temp_decrypted);
                
                if (!$decrypt_result['success']) {
                    throw new Exception("Failed to decrypt backup: " . $decrypt_result['error']);
                }
                
                $file_to_decompress = $temp_decrypted;
            }
            
            // Step 4: Decompress backup file
            $temp_sql = sys_get_temp_dir() . '/restore_' . time() . '_' . rand(1000, 9999) . '.sql';
            
            $decompress_result = $this->backup_engine->decompressFile($file_to_decompress, $temp_sql);
            
            // Clean up decrypted temp file if it exists
            if ($temp_decrypted && file_exists($temp_decrypted)) {
                @unlink($temp_decrypted);
            }
            
            if (!$decompress_result['success']) {
                throw new Exception("Failed to decompress backup: " . $decompress_result['error']);
            }
            
            // Step 5: Execute SQL restore
            $restore_result = $this->executeSqlRestore($temp_sql);
            
            if (!$restore_result['success']) {
                // Restore failed - attempt rollback if we have pre-restore backup
                if ($pre_restore_backup_id) {
                    $rollback_result = $this->rollback($pre_restore_backup_id);
                    
                    throw new Exception(
                        "Restore failed: " . $restore_result['error'] . 
                        ". Rollback " . ($rollback_result['success'] ? 'successful' : 'failed')
                    );
                } else {
                    throw new Exception("Restore failed: " . $restore_result['error']);
                }
            }
            
            // Clean up temp file
            if ($temp_sql && file_exists($temp_sql)) {
                @unlink($temp_sql);
            }
            
            return [
                'success' => true,
                'backup_id' => $backup_id,
                'pre_restore_backup_id' => $pre_restore_backup_id,
                'database' => $metadata['database'],
                'restored_at' => date('Y-m-d H:i:s'),
                'message' => 'Database restored successfully from backup: ' . $backup_id
            ];
            
        } catch (Exception $e) {
            // Clean up temp file on error
            if ($temp_sql && file_exists($temp_sql)) {
                @unlink($temp_sql);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'pre_restore_backup_id' => $pre_restore_backup_id
            ];
        }
    }
    
    /**
     * Execute SQL restore
     * Runs mysql command to import SQL file
     * 
     * @param string $sql_file Path to SQL file
     * @return array Result with 'success' and optional 'error'
     */
    private function executeSqlRestore(string $sql_file): array {
        try {
            if (!file_exists($sql_file)) {
                throw new Exception("SQL file not found: $sql_file");
            }
            
            // Build mysql command
            $command = $this->buildMysqlCommand($sql_file);
            
            // Execute mysql import
            $output = [];
            $return_var = 0;
            exec($command, $output, $return_var);
            
            if ($return_var !== 0) {
                throw new Exception("MySQL import failed with exit code: $return_var. Output: " . implode("\n", $output));
            }
            
            return [
                'success' => true,
                'message' => 'SQL restore executed successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Build mysql command for restore
     * 
     * @param string $sql_file Path to SQL file
     * @return string Command string
     */
    private function buildMysqlCommand(string $sql_file): string {
        $command = "\"{$this->mysql_path}\"";
        $command .= " --user=\"{$this->db_user}\"";
        
        if (!empty($this->db_pass)) {
            $command .= " --password=\"{$this->db_pass}\"";
        }
        
        $command .= " --host=\"{$this->db_host}\"";
        $command .= " --port={$this->db_port}";
        $command .= " \"{$this->db_name}\"";
        $command .= " < \"{$sql_file}\"";
        
        return $command;
    }
    
    /**
     * Rollback to pre-restore backup
     * Restores database to state before failed restore
     * 
     * Requirements: 7.5
     * 
     * @param string $pre_restore_backup_id Pre-restore backup ID
     * @return array Result with 'success' and 'message'
     */
    public function rollback(string $pre_restore_backup_id): array {
        try {
            // Restore from pre-restore backup without creating another pre-restore backup
            $result = $this->restoreFromBackup($pre_restore_backup_id, false);
            
            if (!$result['success']) {
                throw new Exception("Rollback failed: " . $result['error']);
            }
            
            return [
                'success' => true,
                'message' => 'Successfully rolled back to pre-restore backup: ' . $pre_restore_backup_id,
                'backup_id' => $pre_restore_backup_id
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get restore progress
     * Estimates progress based on file size processed
     * 
     * @param string $sql_file Path to SQL file being restored
     * @return array Progress information
     */
    public function getRestoreProgress(string $sql_file): array {
        try {
            if (!file_exists($sql_file)) {
                return [
                    'success' => false,
                    'error' => 'SQL file not found'
                ];
            }
            
            $total_size = filesize($sql_file);
            
            // This is a simplified progress tracker
            // In a real implementation, you'd track actual progress during restore
            return [
                'success' => true,
                'total_size' => $total_size,
                'total_size_human' => $this->formatBytes($total_size),
                'status' => 'in_progress'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate backup integrity before restore
     * Checks if backup file is valid and can be decompressed
     * 
     * @param string $backup_id Backup ID to validate
     * @return array Validation result
     */
    public function validateBackup(string $backup_id): array {
        try {
            $metadata = $this->storage_manager->getBackupMetadata($backup_id);
            
            if (!$metadata) {
                throw new Exception("Backup not found: $backup_id");
            }
            
            $backup_path = $this->storage_manager->getBackupPath($backup_id);
            
            if (!file_exists($backup_path)) {
                throw new Exception("Backup file not found");
            }
            
            // Check if backup is encrypted
            $file_to_validate = $backup_path;
            $temp_decrypted = null;
            
            if (isset($metadata['encrypted']) && $metadata['encrypted'] === true) {
                // Decrypt to temp file first
                $temp_decrypted = sys_get_temp_dir() . '/validate_decrypt_' . time() . '_' . rand(1000, 9999) . '.gz';
                
                $decrypt_result = $this->backup_engine->decryptFile($backup_path, $temp_decrypted);
                
                if (!$decrypt_result['success']) {
                    throw new Exception("Failed to decrypt backup: " . $decrypt_result['error']);
                }
                
                $file_to_validate = $temp_decrypted;
            }
            
            // Try to open the gzip file
            $gz = gzopen($file_to_validate, 'rb');
            
            // Clean up decrypted temp file if it exists
            if ($temp_decrypted && file_exists($temp_decrypted)) {
                @unlink($temp_decrypted);
            }
            if (!$gz) {
                throw new Exception("Backup file is corrupted or not a valid gzip file");
            }
            
            // Read first few bytes to verify it's SQL
            $first_line = gzgets($gz);
            gzclose($gz);
            
            if ($first_line === false) {
                throw new Exception("Backup file is empty or corrupted");
            }
            
            // Check if it looks like SQL
            $is_sql = (
                strpos($first_line, '--') === 0 || 
                strpos($first_line, '/*') === 0 ||
                stripos($first_line, 'CREATE') !== false ||
                stripos($first_line, 'INSERT') !== false
            );
            
            if (!$is_sql) {
                throw new Exception("Backup file does not appear to contain valid SQL");
            }
            
            return [
                'success' => true,
                'valid' => true,
                'backup_id' => $backup_id,
                'file_size' => $metadata['file_size'],
                'created_at' => $metadata['created_at'],
                'database' => $metadata['database'],
                'message' => 'Backup is valid and ready for restore'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
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
}
