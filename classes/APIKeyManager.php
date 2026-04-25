<?php
/**
 * API Key Manager
 * 
 * Manages API keys for external analytics access
 * Provides key generation, validation, and revocation
 * 
 * Feature: wdb-advanced-analytics
 * Requirements: 8.3
 */

class APIKeyManager {
    private $db;
    
    public function __construct($db = null) {
        if ($db === null) {
            require_once __DIR__ . '/../api/config/database.php';
            $this->db = Database::getInstance()->getConnection();
        } else {
            $this->db = $db;
        }
    }
    
    /**
     * Generate new API key
     * 
     * @param int $userId User ID
     * @param string $name Key name
     * @param string $description Key description
     * @param string|null $expiresAt Expiration date (optional)
     * @return array API key data
     */
    public function generateKey(
        int $userId,
        string $name,
        string $description = '',
        ?string $expiresAt = null
    ): array {
        // Generate secure API key
        $apiKey = $this->generateSecureKey();
        
        // Insert into database
        $stmt = $this->db->prepare("
            INSERT INTO api_keys 
            (user_id, api_key, name, description, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $apiKey,
            $name,
            $description,
            $expiresAt
        ]);
        
        return [
            'id' => $this->db->lastInsertId(),
            'api_key' => $apiKey,
            'name' => $name,
            'description' => $description,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Generate secure API key
     * 
     * @return string API key
     */
    private function generateSecureKey(): string {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Validate API key
     * 
     * @param string $apiKey API key
     * @return array|null Key data or null if invalid
     */
    public function validateKey(string $apiKey): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM api_keys 
            WHERE api_key = ? AND is_active = 1
        ");
        $stmt->execute([$apiKey]);
        $keyData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$keyData) {
            return null;
        }
        
        // Check expiration
        if ($keyData['expires_at'] && strtotime($keyData['expires_at']) < time()) {
            return null;
        }
        
        // Update last used timestamp
        $this->updateLastUsed($apiKey);
        
        return $keyData;
    }
    
    /**
     * Update last used timestamp
     * 
     * @param string $apiKey API key
     */
    private function updateLastUsed(string $apiKey) {
        $stmt = $this->db->prepare("
            UPDATE api_keys 
            SET last_used_at = NOW() 
            WHERE api_key = ?
        ");
        $stmt->execute([$apiKey]);
    }
    
    /**
     * Revoke API key
     * 
     * @param string $apiKey API key
     * @return bool Success status
     */
    public function revokeKey(string $apiKey): bool {
        $stmt = $this->db->prepare("
            UPDATE api_keys 
            SET is_active = 0 
            WHERE api_key = ?
        ");
        return $stmt->execute([$apiKey]);
    }
    
    /**
     * Get user API keys
     * 
     * @param int $userId User ID
     * @return array API keys
     */
    public function getUserKeys(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT id, api_key, name, description, is_active, 
                   created_at, expires_at, last_used_at
            FROM api_keys 
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get API key usage statistics
     * 
     * @param string $apiKey API key
     * @param int $days Number of days to analyze
     * @return array Usage statistics
     */
    public function getKeyUsageStats(string $apiKey, int $days = 30): array {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_requests,
                COUNT(DISTINCT DATE(created_at)) as active_days,
                COUNT(DISTINCT endpoint) as unique_endpoints,
                AVG(response_time) as avg_response_time
            FROM api_access_log
            WHERE api_key = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$apiKey, $days]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get requests by endpoint
        $stmt = $this->db->prepare("
            SELECT endpoint, COUNT(*) as count
            FROM api_access_log
            WHERE api_key = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY endpoint
            ORDER BY count DESC
        ");
        $stmt->execute([$apiKey, $days]);
        $stats['by_endpoint'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get requests by day
        $stmt = $this->db->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM api_access_log
            WHERE api_key = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$apiKey, $days]);
        $stats['by_day'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
    
    /**
     * Clean expired keys
     * 
     * @return int Number of keys cleaned
     */
    public function cleanExpiredKeys(): int {
        $stmt = $this->db->prepare("
            UPDATE api_keys 
            SET is_active = 0 
            WHERE expires_at < NOW() AND is_active = 1
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
