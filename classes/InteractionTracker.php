<?php
/**
 * Interaction Tracker
 * 
 * Tracks user interaction patterns and usage analytics for dashboard optimization
 * 
 * Feature: wdb-advanced-analytics
 * Task: 17.1 - Implement user interaction tracking
 * Requirements: 11.7
 */

class InteractionTracker {
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
     * Track user interaction
     * 
     * @param int $userId User ID
     * @param string $interactionType Type of interaction
     * @param array $metadata Additional interaction data
     * @return bool Success status
     */
    public function trackInteraction(int $userId, string $interactionType, array $metadata = []): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_interactions 
                (user_id, interaction_type, component_id, action, metadata, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            return $stmt->execute([
                $userId,
                $interactionType,
                $metadata['component_id'] ?? null,
                $metadata['action'] ?? null,
                json_encode($metadata)
            ]);
        } catch (Exception $e) {
            error_log("Interaction tracking error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user interaction patterns
     * 
     * @param int $userId User ID
     * @param int $days Number of days to analyze
     * @return array Interaction patterns
     */
    public function getUserInteractionPatterns(int $userId, int $days = 30): array {
        $stmt = $this->db->prepare("
            SELECT 
                interaction_type,
                component_id,
                action,
                COUNT(*) as interaction_count
            FROM user_interactions
            WHERE user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY interaction_type, component_id, action
            ORDER BY interaction_count DESC
        ");
        
        $stmt->execute([$userId, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get most used components
     * 
     * @param int $userId User ID
     * @param int $limit Number of components to return
     * @return array Most used components
     */
    public function getMostUsedComponents(int $userId, int $limit = 10): array {
        $stmt = $this->db->prepare("
            SELECT 
                component_id,
                COUNT(*) as usage_count,
                MAX(created_at) as last_used
            FROM user_interactions
            WHERE user_id = ?
            AND component_id IS NOT NULL
            GROUP BY component_id
            ORDER BY usage_count DESC
            LIMIT ?
        ");
        
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get interaction heatmap data
     * 
     * @param int $userId User ID
     * @param int $days Number of days to analyze
     * @return array Heatmap data
     */
    public function getInteractionHeatmap(int $userId, int $days = 7): array {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                HOUR(created_at) as hour,
                COUNT(*) as interaction_count
            FROM user_interactions
            WHERE user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at), HOUR(created_at)
            ORDER BY date, hour
        ");
        
        $stmt->execute([$userId, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get dashboard layout recommendations
     * 
     * @param int $userId User ID
     * @return array Layout recommendations
     */
    public function getDashboardLayoutRecommendations(int $userId): array {
        // Get most used components
        $mostUsed = $this->getMostUsedComponents($userId, 5);
        
        // Get interaction patterns
        $patterns = $this->getUserInteractionPatterns($userId, 30);
        
        // Generate recommendations
        $recommendations = [
            'prioritize_components' => array_column($mostUsed, 'component_id'),
            'suggested_layout' => $this->generateOptimalLayout($mostUsed, $patterns),
            'usage_insights' => $this->generateUsageInsights($patterns),
            'optimization_score' => $this->calculateOptimizationScore($userId)
        ];
        
        return $recommendations;
    }
    
    /**
     * Generate optimal layout based on usage
     * 
     * @param array $mostUsed Most used components
     * @param array $patterns Interaction patterns
     * @return array Optimal layout configuration
     */
    private function generateOptimalLayout(array $mostUsed, array $patterns): array {
        $layout = [
            'top_row' => [],
            'middle_section' => [],
            'bottom_section' => []
        ];
        
        // Place most used components in top row
        foreach (array_slice($mostUsed, 0, 3) as $component) {
            $layout['top_row'][] = $component['component_id'];
        }
        
        // Place moderately used components in middle
        foreach (array_slice($mostUsed, 3, 4) as $component) {
            $layout['middle_section'][] = $component['component_id'];
        }
        
        // Less used components in bottom
        foreach (array_slice($mostUsed, 7) as $component) {
            $layout['bottom_section'][] = $component['component_id'];
        }
        
        return $layout;
    }
    
    /**
     * Generate usage insights
     * 
     * @param array $patterns Interaction patterns
     * @return array Usage insights
     */
    private function generateUsageInsights(array $patterns): array {
        $insights = [];
        
        // Identify most frequent interactions
        if (!empty($patterns)) {
            $topPattern = $patterns[0];
            $insights[] = [
                'type' => 'most_frequent',
                'message' => "You frequently use {$topPattern['interaction_type']} ({$topPattern['interaction_count']} times)",
                'recommendation' => 'Consider pinning this component for quick access'
            ];
        }
        
        // Identify underutilized features
        $allComponents = ['kpi_cards', 'charts', 'filters', 'reports', 'exports'];
        $usedComponents = array_column($patterns, 'component_id');
        $unused = array_diff($allComponents, $usedComponents);
        
        if (!empty($unused)) {
            $insights[] = [
                'type' => 'underutilized',
                'message' => 'You haven\'t used: ' . implode(', ', $unused),
                'recommendation' => 'Explore these features to get more insights'
            ];
        }
        
        return $insights;
    }
    
    /**
     * Calculate optimization score
     * 
     * @param int $userId User ID
     * @return float Optimization score (0-100)
     */
    private function calculateOptimizationScore(int $userId): float {
        // Get total interactions
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total_interactions
            FROM user_interactions
            WHERE user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$userId]);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total_interactions'];
        
        // Get unique components used
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT component_id) as unique_components
            FROM user_interactions
            WHERE user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND component_id IS NOT NULL
        ");
        $stmt->execute([$userId]);
        $unique = $stmt->fetch(PDO::FETCH_ASSOC)['unique_components'];
        
        // Calculate score based on engagement and feature utilization
        $engagementScore = min(($total / 100) * 50, 50); // Max 50 points for engagement
        $utilizationScore = min(($unique / 10) * 50, 50); // Max 50 points for feature utilization
        
        return round($engagementScore + $utilizationScore, 2);
    }
    
    /**
     * Get aggregated interaction statistics
     * 
     * @param int $days Number of days to analyze
     * @return array Aggregated statistics
     */
    public function getAggregatedStatistics(int $days = 30): array {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT user_id) as active_users,
                COUNT(*) as total_interactions,
                AVG(interactions_per_user) as avg_interactions_per_user,
                component_id,
                COUNT(*) as component_usage
            FROM (
                SELECT 
                    user_id,
                    component_id,
                    COUNT(*) as interactions_per_user
                FROM user_interactions
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY user_id, component_id
            ) as user_stats
            GROUP BY component_id
            ORDER BY component_usage DESC
        ");
        
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Track session duration
     * 
     * @param int $userId User ID
     * @param int $duration Session duration in seconds
     * @return bool Success status
     */
    public function trackSessionDuration(int $userId, int $duration): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_sessions 
                (user_id, duration_seconds, created_at)
                VALUES (?, ?, NOW())
            ");
            
            return $stmt->execute([$userId, $duration]);
        } catch (Exception $e) {
            error_log("Session tracking error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get average session duration
     * 
     * @param int $userId User ID
     * @param int $days Number of days to analyze
     * @return float Average session duration in seconds
     */
    public function getAverageSessionDuration(int $userId, int $days = 30): float {
        $stmt = $this->db->prepare("
            SELECT AVG(duration_seconds) as avg_duration
            FROM user_sessions
            WHERE user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        $stmt->execute([$userId, $days]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['avg_duration'] ?? 0.0;
    }
}
