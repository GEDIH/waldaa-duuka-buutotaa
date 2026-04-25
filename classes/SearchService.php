<?php
/**
 * SearchService - Advanced search functionality for analytics data
 * 
 * Provides global search across members, contributions, centers, and analytics data
 * with intelligent suggestions, auto-complete, and natural language query support
 */

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/CacheManager.php';

class SearchService
{
    private $db;
    private $cache;
    private $searchHistory = [];
    
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->cache = CacheManager::getInstance();
    }
    
    /**
     * Perform global search across all analytics data
     */
    public function globalSearch(string $query, array $options = []): array
    {
        $cacheKey = 'search:' . md5($query . json_encode($options));
        
        return $this->cache->getAnalyticsCache($cacheKey, function() use ($query, $options) {
            $results = [
                'query' => $query,
                'categories' => [],
                'total' => 0,
                'suggestions' => $this->generateSuggestions($query)
            ];
            
            $categories = $options['categories'] ?? ['members', 'contributions', 'centers', 'analytics'];
            $limit = $options['limit'] ?? 50;
            
            if (in_array('members', $categories)) {
                $results['categories']['members'] = $this->searchMembers($query, $limit);
                $results['total'] += count($results['categories']['members']);
            }
            
            if (in_array('contributions', $categories)) {
                $results['categories']['contributions'] = $this->searchContributions($query, $limit);
                $results['total'] += count($results['categories']['contributions']);
            }
            
            if (in_array('centers', $categories)) {
                $results['categories']['centers'] = $this->searchCenters($query, $limit);
                $results['total'] += count($results['categories']['centers']);
            }
            
            if (in_array('analytics', $categories)) {
                $results['categories']['analytics'] = $this->searchAnalytics($query, $limit);
                $results['total'] += count($results['categories']['analytics']);
            }
            
            $this->addToSearchHistory($query, $results['total']);
            
            return $results;
        }, 300);
    }
    
    /**
     * Search members by name, ID, email, phone
     */
    private function searchMembers(string $query, int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                member_id,
                CONCAT(first_name, ' ', middle_name, ' ', last_name) as full_name,
                email,
                phone,
                center_id,
                registration_date,
                status,
                'member' as result_type
            FROM members
            WHERE 
                member_id LIKE :query
                OR first_name LIKE :query
                OR middle_name LIKE :query
                OR last_name LIKE :query
                OR email LIKE :query
                OR phone LIKE :query
            ORDER BY registration_date DESC
            LIMIT :limit
        ");
        
        $searchTerm = "%{$query}%";
        $stmt->bindParam(':query', $searchTerm);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $this->highlightResults($stmt->fetchAll(PDO::FETCH_ASSOC), $query);
    }
    
    /**
     * Search contributions by member, amount, date
     */
    private function searchContributions(string $query, int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                c.contribution_id,
                c.member_id,
                CONCAT(m.first_name, ' ', m.last_name) as member_name,
                c.amount,
                c.contribution_date,
                c.payment_status,
                c.payment_method,
                'contribution' as result_type
            FROM contributions c
            JOIN members m ON c.member_id = m.member_id
            WHERE 
                c.contribution_id LIKE :query
                OR c.member_id LIKE :query
                OR m.first_name LIKE :query
                OR m.last_name LIKE :query
                OR c.amount LIKE :query
            ORDER BY c.contribution_date DESC
            LIMIT :limit
        ");
        
        $searchTerm = "%{$query}%";
        $stmt->bindParam(':query', $searchTerm);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $this->highlightResults($stmt->fetchAll(PDO::FETCH_ASSOC), $query);
    }
    
    /**
     * Search centers by name, location, code
     */
    private function searchCenters(string $query, int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                center_id,
                center_name,
                location,
                region,
                contact_person,
                contact_phone,
                'center' as result_type
            FROM centers
            WHERE 
                center_name LIKE :query
                OR location LIKE :query
                OR region LIKE :query
                OR contact_person LIKE :query
            LIMIT :limit
        ");
        
        $searchTerm = "%{$query}%";
        $stmt->bindParam(':query', $searchTerm);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $this->highlightResults($stmt->fetchAll(PDO::FETCH_ASSOC), $query);
    }
    
    /**
     * Search analytics data and KPIs
     */
    private function searchAnalytics(string $query, int $limit): array
    {
        $analyticsTerms = [
            'total members' => ['type' => 'kpi', 'metric' => 'total_members'],
            'new registrations' => ['type' => 'kpi', 'metric' => 'new_registrations'],
            'total contributions' => ['type' => 'kpi', 'metric' => 'total_contributions'],
            'active centers' => ['type' => 'kpi', 'metric' => 'active_centers'],
            'paid members' => ['type' => 'kpi', 'metric' => 'paid_members'],
            'unpaid members' => ['type' => 'kpi', 'metric' => 'unpaid_members'],
            'revenue' => ['type' => 'kpi', 'metric' => 'total_revenue'],
            'growth rate' => ['type' => 'kpi', 'metric' => 'growth_rate']
        ];
        
        $results = [];
        $queryLower = strtolower($query);
        
        foreach ($analyticsTerms as $term => $data) {
            if (strpos($term, $queryLower) !== false || strpos($queryLower, $term) !== false) {
                $results[] = [
                    'term' => $term,
                    'type' => $data['type'],
                    'metric' => $data['metric'],
                    'result_type' => 'analytics',
                    'description' => "View {$term} analytics and trends"
                ];
            }
        }
        
        return array_slice($results, 0, $limit);
    }
    
    /**
     * Generate intelligent search suggestions
     */
    private function generateSuggestions(string $query): array
    {
        if (strlen($query) < 2) {
            return [];
        }
        
        $suggestions = [];
        
        $stmt = $this->db->prepare("
            SELECT DISTINCT CONCAT(first_name, ' ', last_name) as suggestion
            FROM members
            WHERE first_name LIKE :query OR last_name LIKE :query
            LIMIT 5
        ");
        $searchTerm = "{$query}%";
        $stmt->bindParam(':query', $searchTerm);
        $stmt->execute();
        $suggestions = array_merge($suggestions, array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'suggestion'));
        
        return array_unique($suggestions);
    }
    
    /**
     * Highlight matching terms in search results
     */
    private function highlightResults(array $results, string $query): array
    {
        foreach ($results as &$result) {
            foreach ($result as $key => &$value) {
                if (is_string($value) && stripos($value, $query) !== false) {
                    $value = preg_replace(
                        '/(' . preg_quote($query, '/') . ')/i',
                        '<mark>$1</mark>',
                        $value
                    );
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Add search to history
     */
    private function addToSearchHistory(string $query, int $resultCount): void
    {
        $userId = $_SESSION['user_id'] ?? 'anonymous';
        
        $stmt = $this->db->prepare("
            INSERT INTO search_history (user_id, query, result_count, search_date)
            VALUES (:user_id, :query, :result_count, NOW())
        ");
        
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':query', $query);
        $stmt->bindParam(':result_count', $resultCount, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    /**
     * Get search history for user
     */
    public function getSearchHistory(int $limit = 10): array
    {
        $userId = $_SESSION['user_id'] ?? 'anonymous';
        
        $stmt = $this->db->prepare("
            SELECT query, result_count, search_date, COUNT(*) as frequency
            FROM search_history
            WHERE user_id = :user_id
            GROUP BY query
            ORDER BY search_date DESC, frequency DESC
            LIMIT :limit
        ");
        
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get search recommendations based on user behavior
     */
    public function getRecommendations(): array
    {
        $userId = $_SESSION['user_id'] ?? 'anonymous';
        
        $stmt = $this->db->prepare("
            SELECT query, COUNT(*) as frequency
            FROM search_history
            WHERE user_id = :user_id
            GROUP BY query
            ORDER BY frequency DESC
            LIMIT 5
        ");
        
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Parse natural language query
     */
    public function parseNaturalLanguageQuery(string $query): array
    {
        $parsed = [
            'intent' => 'search',
            'entities' => [],
            'filters' => []
        ];
        
        if (preg_match('/this (month|week|year)/i', $query, $matches)) {
            $parsed['filters']['date_range'] = $matches[1];
        }
        
        if (preg_match('/(from|in) ([A-Za-z\s]+)/i', $query, $matches)) {
            $parsed['filters']['location'] = trim($matches[2]);
        }
        
        if (preg_match('/(new|active|inactive) members/i', $query, $matches)) {
            $parsed['filters']['status'] = $matches[1];
        }
        
        if (preg_match('/(\d+)/i', $query, $matches)) {
            $parsed['entities']['number'] = $matches[1];
        }
        
        return $parsed;
    }
    
    /**
     * Execute natural language query
     */
    public function executeNaturalLanguageQuery(string $query): array
    {
        $parsed = $this->parseNaturalLanguageQuery($query);
        $options = [];
        
        if (isset($parsed['filters']['location'])) {
            $options['location'] = $parsed['filters']['location'];
        }
        
        if (isset($parsed['filters']['status'])) {
            $options['status'] = $parsed['filters']['status'];
        }
        
        if (isset($parsed['filters']['date_range'])) {
            $options['date_range'] = $parsed['filters']['date_range'];
        }
        
        return $this->globalSearch($query, $options);
    }
}
