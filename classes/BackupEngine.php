<?php
/**
 * BackupEngine Class
 * 
 * Creates compressed SQL dumps of the MySQL database using mysqldump.
 * Handles backup creation, compression, and integration with StorageManager.
 * 
 * Requirements: 1.2, 1.3, 1.4, 1.6, 2.1, 2.4
 */

require_once 'StorageManager.php';

class BackupEngine {
    private $db_host;
    private $db_name;
    private $db_user;
    private $db_pass;
    private $db_port;
    private $storage_manager;
    private $mysqldump_path;
    private $encryption_enabled;
    private $encryption_key;
    
    /**
     * Constructor
     * 
     * @param StorageManager $storage_manager Storage manager instance
     * @param string $db_host Database host (default: localhost)
     * @param string $db_name Database name (default: wdb_membership)
     * @param string $db_user Database user (default: root)
     * @param string $db_pass Database password (default: empty)
     * @param int $db_port Database port (default: 3306)
     */
    /**
     * Constructor
     * 
     * @param StorageManager $storage_manager Storage manager instance
     * @param string $db_host Database host (default: localhost)
     * @param string $db_name Database name (default: wdb_membership)
     * @param string $db_user Database user (default: root)
     * @param string $db_pass Database password (default: empty)
     * @param int $db_port Database port (default: 3306)
     */
    public function __construct(
        StorageManager $storage_manager,
        string $db_host = 'localhost',
        string $db_name = 'wdb_membership',
        string $db_user = 'root',
        string $db_pass = '',
        int $db_port = 3306
    ) {
        $this->storage_manager = $storage_manager;
        $this->db_host = $db_host;
        $this->db_name = $db_name;
        $this->db_user = $db_user;
        $this->db_pass = $db_pass;
        $this->db_port = $db_port;

        // Detect mysqldump path
        $this->mysqldump_path = $this->detectMysqldumpPath();

        // Load encryption settings
        $this->loadEncryptionSettings();
    }

    
    /**
     * Detect mysqldump executable path
     * 
     * @return string Path to mysqldump
     */
    private function detectMysqldumpPath(): string {
        // Common paths for XAMPP on Windows
        $possible_paths = [
            'C:/xampp/mysql/bin/mysqldump.exe',
            'C:/xampp/mysql/bin/mysqldump',
            'mysqldump.exe',
            'mysqldump'
        ];
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Default to mysqldump in PATH
        return 'mysqldump';
    }
    
    /**
     * Load encryption settings from database
     * 
     * Requirements: 6.6
     */
    private function loadEncryptionSettings(): void {
        try {
            $pdo = new PDO(
                "mysql:host={$this->db_host};port={$this->db_port};dbname={$this->db_name}",
                $this->db_user,
                $this->db_pass
            );
            
            // Check if encryption is enabled
            $stmt = $pdo->prepare("SELECT setting_value FROM backup_settings WHERE setting_key = 'encryption_enabled'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->encryption_enabled = ($result && $result['setting_value'] === 'true');
            
            // Load encryption key if enabled
            if ($this->encryption_enabled) {
                $stmt = $pdo->prepare("SELECT setting_value FROM backup_settings WHERE setting_key = 'encryption_key'");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && !empty($result['setting_value'])) {
                    $this->encryption_key = $result['setting_value'];
                } else {
                    // Generate new encryption key if not exists
                    $this->encryption_key = $this->generateEncryptionKey();
                    $this->saveEncryptionKey($this->encryption_key);
                }
            } else {
                $this->encryption_key = null;
            }
            
        } catch (PDOException $e) {
            // Default to no encryption if settings table doesn't exist
            $this->encryption_enabled = false;
            $this->encryption_key = null;
        }
    }
    
    /**
     * Generate a secure encryption key
     * 
     * Requirements: 6.6
     * 
     * @return string Base64-encoded 256-bit key
     */
    private function generateEncryptionKey(): string {
        return base64_encode(random_bytes(32)); // 256 bits
    }
    
    /**
     * Save encryption key to database
     * 
     * Requirements: 6.6
     * 
     * @param string $key Encryption key
     */
    private function saveEncryptionKey(string $key): void {
        try {
            $pdo = new PDO(
                "mysql:host={$this->db_host};port={$this->db_port};dbname={$this->db_name}",
                $this->db_user,
                $this->db_pass
            );
            
            $stmt = $pdo->prepare("
                INSERT INTO backup_settings (setting_key, setting_value, updated_by)
                VALUES ('encryption_key', ?, 1)
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$key, $key]);
            
        } catch (PDOException $e) {
            // Silently fail - encryption will be disabled
        }
    }
    
    /**
     * Encrypt a file using AES-256-CBC
     * 
     * Requirements: 6.6
     * 
     * @param string $source_file Source file path
     * @param string $dest_file Destination encrypted file path
     * @return array Result with 'success' and optional 'error'
     */
    private function encryptFile(string $source_file, string $dest_file): array {
        try {
            if (!$this->encryption_enabled || empty($this->encryption_key)) {
                return [
                    'success' => false,
                    'error' => 'Encryption is not enabled or key is missing'
                ];
            }
            
            if (!file_exists($source_file)) {
                return [
                    'success' => false,
                    'error' => "Source file not found: $source_file"
                ];
            }
            
            // Read source file
            $data = file_get_contents($source_file);
            if ($data === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to read source file'
                ];
            }
            
            // Decode the base64 key
            $key = base64_decode($this->encryption_key);
            
            // Generate random IV (Initialization Vector)
            $iv_length = openssl_cipher_iv_length('aes-256-cbc');
            $iv = openssl_random_pseudo_bytes($iv_length);
            
            // Encrypt the data
            $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
            
            if ($encrypted === false) {
                return [
                    'success' => false,
                    'error' => 'Encryption failed'
                ];
            }
            
            // Prepend IV to encrypted data (needed for decryption)
            $encrypted_with_iv = $iv . $encrypted;
            
            // Write encrypted data to destination file
            if (file_put_contents($dest_file, $encrypted_with_iv) === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to write encrypted file'
                ];
            }
            
            return [
                'success' => true,
                'encrypted_size' => filesize($dest_file)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Decrypt a file using AES-256-CBC
     * 
     * Requirements: 6.6
     * 
     * @param string $source_file Source encrypted file path
     * @param string $dest_file Destination decrypted file path
     * @return array Result with 'success' and optional 'error'
     */
    public function decryptFile(string $source_file, string $dest_file): array {
        try {
            if (!$this->encryption_enabled || empty($this->encryption_key)) {
                return [
                    'success' => false,
                    'error' => 'Encryption is not enabled or key is missing'
                ];
            }
            
            if (!file_exists($source_file)) {
                return [
                    'success' => false,
                    'error' => "Source file not found: $source_file"
                ];
            }
            
            // Read encrypted file
            $encrypted_with_iv = file_get_contents($source_file);
            if ($encrypted_with_iv === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to read encrypted file'
                ];
            }
            
            // Decode the base64 key
            $key = base64_decode($this->encryption_key);
            
            // Extract IV from the beginning of the file
            $iv_length = openssl_cipher_iv_length('aes-256-cbc');
            $iv = substr($encrypted_with_iv, 0, $iv_length);
            $encrypted = substr($encrypted_with_iv, $iv_length);
            
            // Decrypt the data
            $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
            
            if ($decrypted === false) {
                return [
                    'success' => false,
                    'error' => 'Decryption failed - invalid key or corrupted file'
                ];
            }
            
            // Write decrypted data to destination file
            if (file_put_contents($dest_file, $decrypted) === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to write decrypted file'
                ];
            }
            
            return [
                'success' => true,
                'decrypted_size' => filesize($dest_file)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if encryption is enabled
     * 
     * @return bool True if encryption is enabled
     */
    public function isEncryptionEnabled(): bool {
        return $this->encryption_enabled;
    }
    
    /**
     * Set custom mysqldump path
     * 
     * @param string $path Path to mysqldump executable
     */
    public function setMysqldumpPath(string $path): void {
        $this->mysqldump_path = $path;
    }
    
    /**
     * Get database version information
     * 
     * Requirements: 1.6
     * 
     * @return string MySQL version
     */
    public function getDatabaseVersion(): string {
        try {
            $pdo = new PDO(
                "mysql:host={$this->db_host};port={$this->db_port};dbname={$this->db_name}",
                $this->db_user,
                $this->db_pass
            );
            
            $stmt = $pdo->query('SELECT VERSION()');
            $version = $stmt->fetchColumn();
            
            return $version ?: 'Unknown';
            
        } catch (PDOException $e) {
            return 'Error: ' . $e->getMessage();
        }
    }
    
    /**
     * Estimate backup size before creation
     * Calculates approximate size based on database tables
     * 
     * Requirements: 1.2
     * 
     * @return int Estimated size in bytes
     */
    public function estimateBackupSize(): int {
        try {
            $pdo = new PDO(
                "mysql:host={$this->db_host};port={$this->db_port};dbname={$this->db_name}",
                $this->db_user,
                $this->db_pass
            );
            
            $stmt = $pdo->query("
                SELECT SUM(data_length + index_length) as size
                FROM information_schema.TABLES
                WHERE table_schema = '{$this->db_name}'
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Return estimated size (actual compressed size will be smaller)
            return (int)($result['size'] ?? 0);
            
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Get database connection for testing
     * 
     * @return PDO|null PDO connection or null on failure
     */
    public function testConnection(): ?PDO {
        try {
            $pdo = new PDO(
                "mysql:host={$this->db_host};port={$this->db_port};dbname={$this->db_name}",
                $this->db_user,
                $this->db_pass
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            return $pdo;
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Get list of tables in database
     * 
     * @return array Array of table names
     */
    public function getDatabaseTables(): array {
        try {
            $pdo = $this->testConnection();
            
            if (!$pdo) {
                return [];
            }
            
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            return $tables ?: [];
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get database statistics
     * 
     * @return array Statistics array
     */
    public function getDatabaseStats(): array {
        try {
            $pdo = $this->testConnection();
            
            if (!$pdo) {
                return [
                    'success' => false,
                    'error' => 'Database connection failed'
                ];
            }
            
            $tables = $this->getDatabaseTables();
            $version = $this->getDatabaseVersion();
            $estimated_size = $this->estimateBackupSize();
            
            // Count total rows
            $total_rows = 0;
            foreach ($tables as $table) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $total_rows += $stmt->fetchColumn();
            }
            
            return [
                'success' => true,
                'database' => $this->db_name,
                'version' => $version,
                'tables_count' => count($tables),
                'tables' => $tables,
                'total_rows' => $total_rows,
                'estimated_size' => $estimated_size,
                'estimated_size_human' => $this->formatBytes($estimated_size)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
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

    /**
     * Create a new backup
     * Creates SQL dump, compresses it, and stores with metadata
     * 
     * Requirements: 1.2, 1.3, 1.4
     * 
     * @param string $type Backup type (manual|scheduled)
     * @param int $created_by User ID who created the backup
     * @return array Result with 'success', 'backup_id', 'file_path', 'size'
     */
    public function createBackup(string $type = 'manual', int $created_by = 1): array {
        try {
            // Test database connection first
            if (!$this->testConnection()) {
                throw new Exception("Database connection failed");
            }
            
            // Create temporary file for SQL dump
            $temp_sql = sys_get_temp_dir() . '/backup_' . time() . '_' . rand(1000, 9999) . '.sql';
            
            // Build mysqldump command
            $command = $this->buildMysqldumpCommand($temp_sql);
            
            // Execute mysqldump
            $output = [];
            $return_var = 0;
            exec($command, $output, $return_var);
            
            if ($return_var !== 0) {
                throw new Exception("mysqldump failed with exit code: $return_var. Output: " . implode("\n", $output));
            }
            
            // Verify SQL file was created
            if (!file_exists($temp_sql) || filesize($temp_sql) === 0) {
                throw new Exception("SQL dump file was not created or is empty");
            }
            
            $sql_size = filesize($temp_sql);
            
            // Compress the SQL file
            $temp_gz = $temp_sql . '.gz';
            $compress_result = $this->compressFile($temp_sql, $temp_gz);
            
            if (!$compress_result['success']) {
                @unlink($temp_sql);
                throw new Exception("Compression failed: " . $compress_result['error']);
            }
            
            // Delete uncompressed SQL file
            @unlink($temp_sql);
            
            $gz_size = filesize($temp_gz);
            $compression_ratio = $sql_size > 0 ? round($gz_size / $sql_size, 2) : 0;
            
            // Encrypt the compressed file if encryption is enabled
            $final_file = $temp_gz;
            $encrypted = false;
            
            if ($this->encryption_enabled && !empty($this->encryption_key)) {
                $temp_encrypted = $temp_gz . '.enc';
                $encrypt_result = $this->encryptFile($temp_gz, $temp_encrypted);
                
                if ($encrypt_result['success']) {
                    @unlink($temp_gz); // Delete unencrypted file
                    $final_file = $temp_encrypted;
                    $encrypted = true;
                } else {
                    // Log encryption failure but continue with unencrypted backup
                    error_log("Backup encryption failed: " . $encrypt_result['error']);
                }
            }
            
            $final_size = filesize($final_file);
            
            // Prepare metadata
            $metadata = [
                'created_at' => date('Y-m-d H:i:s'),
                'database' => $this->db_name,
                'db_version' => $this->getDatabaseVersion(),
                'file_size' => $final_size,
                'uncompressed_size' => $sql_size,
                'compressed' => true,
                'compression_ratio' => $compression_ratio,
                'encrypted' => $encrypted,
                'type' => $type,
                'created_by' => $created_by,
                'tables_count' => count($this->getDatabaseTables()),
                'checksum' => hash_file('sha256', $final_file)
            ];
            
            // Store backup using StorageManager
            $store_result = $this->storage_manager->storeBackup($final_file, $metadata);
            
            if (!$store_result['success']) {
                @unlink($final_file);
                throw new Exception("Failed to store backup: " . ($store_result['error'] ?? 'Unknown error'));
            }
            
            return [
                'success' => true,
                'backup_id' => $store_result['backup_id'],
                'file_path' => $store_result['file_path'],
                'size' => $final_size,
                'size_human' => $this->formatBytes($final_size),
                'uncompressed_size' => $sql_size,
                'uncompressed_size_human' => $this->formatBytes($sql_size),
                'compression_ratio' => $compression_ratio,
                'encrypted' => $encrypted,
                'tables_count' => $metadata['tables_count'],
                'type' => $type,
                'created_at' => $metadata['created_at']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Build mysqldump command
     * 
     * Requirements: 1.2
     * 
     * @param string $output_file Output SQL file path
     * @return string Command string
     */
    private function buildMysqldumpCommand(string $output_file): string {
        $command = "\"{$this->mysqldump_path}\"";
        $command .= " --user=\"{$this->db_user}\"";
        
        if (!empty($this->db_pass)) {
            $command .= " --password=\"{$this->db_pass}\"";
        }
        
        $command .= " --host=\"{$this->db_host}\"";
        $command .= " --port={$this->db_port}";
        $command .= " --single-transaction";
        $command .= " --quick";
        $command .= " --lock-tables=false";
        $command .= " --add-drop-table";
        $command .= " --routines";
        $command .= " --triggers";
        $command .= " \"{$this->db_name}\"";
        $command .= " > \"{$output_file}\"";
        
        return $command;
    }
    
    /**
     * Compress file using gzip
     * 
     * Requirements: 1.3
     * 
     * @param string $source_file Source file path
     * @param string $dest_file Destination .gz file path
     * @return array Result with 'success' and optional 'error'
     */
    private function compressFile(string $source_file, string $dest_file): array {
        try {
            if (!file_exists($source_file)) {
                return [
                    'success' => false,
                    'error' => "Source file not found: $source_file"
                ];
            }
            
            // Open source file for reading
            $source = fopen($source_file, 'rb');
            if (!$source) {
                return [
                    'success' => false,
                    'error' => "Failed to open source file"
                ];
            }
            
            // Open destination file for writing (gzip compressed)
            $dest = gzopen($dest_file, 'wb9'); // 9 = maximum compression
            if (!$dest) {
                fclose($source);
                return [
                    'success' => false,
                    'error' => "Failed to create compressed file"
                ];
            }
            
            // Read and compress in chunks
            while (!feof($source)) {
                $chunk = fread($source, 8192); // 8KB chunks
                gzwrite($dest, $chunk);
            }
            
            fclose($source);
            gzclose($dest);
            
            return [
                'success' => true,
                'compressed_size' => filesize($dest_file)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Decompress gzip file
     * 
     * @param string $source_file Source .gz file path
     * @param string $dest_file Destination file path
     * @return array Result with 'success' and optional 'error'
     */
    public function decompressFile(string $source_file, string $dest_file): array {
        try {
            if (!file_exists($source_file)) {
                return [
                    'success' => false,
                    'error' => "Source file not found: $source_file"
                ];
            }
            
            // Open compressed file for reading
            $source = gzopen($source_file, 'rb');
            if (!$source) {
                return [
                    'success' => false,
                    'error' => "Failed to open compressed file"
                ];
            }
            
            // Open destination file for writing
            $dest = fopen($dest_file, 'wb');
            if (!$dest) {
                gzclose($source);
                return [
                    'success' => false,
                    'error' => "Failed to create destination file"
                ];
            }
            
            // Read and decompress in chunks
            while (!gzeof($source)) {
                $chunk = gzread($source, 8192); // 8KB chunks
                fwrite($dest, $chunk);
            }
            
            gzclose($source);
            fclose($dest);
            
            return [
                'success' => true,
                'decompressed_size' => filesize($dest_file)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
