<?php
/**
 * Analytics Service
 * Provides comprehensive analytics and KPI calculation methods
 * Requirements: 1.1, 1.4, 5.1, 6.1
 */

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/CacheManager.php';
require_once __DIR__ . '/PerformanceMonitor.php';
require_once __DIR__ . '/SecurityManager.php';

class AnalyticsService
{
    private $db;
    private $conn;
    private $cache;
    private $perfMonitor;
    private $securityManager;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        $this->cache = CacheManager::getInstance();
        $this->perfMonitor = PerformanceMonitor::getInstance();
        $this->securityManager = new SecurityManager();
    }
    
    /**
     * Calculate all KPIs with optional filters
     * @param array $filters Optional filters (center_id, date_range, etc.)
     * @return array Comprehensive KPI data
     */
    public function calculateKPIs($filters = [])
    {
        // Apply security filters if user_id is provided
        if (isset($filters['user_id'])) {
            $filters = $this->securityManager->applySecurityFilters($filters['user_id'], $filters);
            
            // Log analytics access
            $this->securityManager->logAnalyticsAccess($filters['user_id'], 'view_kpis', [
                'filters' => $filters
            ]);
        }
        
        // Start performance tracking
        $this->perfMonitor->startMetric('kpis_calculation');
        
        // Check for resource constraints
        $constraints = $this->checkResourceConstraints($filters);
        $isDegraded = $constraints['is_constrained'];
        
        // Generate cache key based on filters
        $cacheKey = $this->cache->tagKey('kpis', 'all_' . md5(json_encode($filters)));
        
        // Handle cache unavailability gracefully
        $cacheDisabled = isset($filters['cache_disabled']) && $filters['cache_disabled'];
        
        // Try to get from cache (unless disabled)
        if ($cacheDisabled) {
            $result = $this->calculateKPIsDirectly($filters, $isDegraded);
            $result['_cache_bypassed'] = true;
            $result['_response_time_ms'] = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;
        } else {
            $result = $this->cache->getAnalyticsCache($cacheKey, function() use ($filters, $isDegraded) {
                return $this->calculateKPIsDirectly($filters, $isDegraded);
            }, 1800); // Cache for 30 minutes
        }
        
        // Add constraint information to response
        if ($isDegraded) {
            $result['_degraded_mode'] = true;
            $result['_constraint_level'] = $constraints['level'];
            $result['_constraints'] = $constraints['active_constraints'];
            
            if ($constraints['level'] === 'high' || $constraints['level'] === 'critical') {
                $result['_advanced_features_disabled'] = true;
                $result['_core_features_available'] = true;
            }
        } else {
            $result['_degraded_mode'] = false;
        }
        
        // End performance tracking
        $this->perfMonitor->endMetric('kpis_calculation');
        
        return $result;
    }
    
    /**
     * Calculate KPIs directly (without cache wrapper)
     */
    private function calculateKPIsDirectly($filters, $isDegraded)
    {
        $centerFilter = isset($filters['center_id']) ? (int)$filters['center_id'] : null;
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');
        
        $result = [
            'membership' => $this->getMembershipKPIs($centerFilter, $startDate, $endDate),
            'financial' => $this->getFinancialKPIs($centerFilter, $startDate, $endDate),
            'growth' => $this->getGrowthKPIs($centerFilter),
            'engagement' => $this->getEngagementKPIs($centerFilter),
            'center_performance' => $this->getCenterKPIs($centerFilter),
            'timestamp' => date('Y-m-d H:i:s'),
            'filters_applied' => $filters
        ];
        
        // Simplify data under degraded mode
        if ($isDegraded) {
            $result = $this->simplifyKPIsForDegradedMode($result);
        }
        
        // Extract core KPIs for easy access
        $result['total_members'] = $result['membership']['total_members'] ?? 0;
        $result['total_contributions'] = $result['financial']['total_contributions'] ?? 0;
        $result['active_centers'] = count($result['center_performance'] ?? []);
        
        return $result;
    }
    
    /**
     * Check for resource constraints
     */
    private function checkResourceConstraints($filters)
    {
        $constraints = [];
        $constraintCount = 0;
        
        // Check memory constraint
        if (isset($filters['memory_constrained']) && $filters['memory_constrained']) {
            $constraints[] = 'memory';
            $constraintCount++;
        }
        
        // Check cache availability
        if (isset($filters['cache_disabled']) && $filters['cache_disabled']) {
            $constraints[] = 'cache';
            $constraintCount++;
        }
        
        // Check database connection constraint
        if (isset($filters['connection_constrained']) && $filters['connection_constrained']) {
            $constraints[] = 'connection';
            $constraintCount++;
        }
        
        // Check high load
        if (isset($filters['high_load']) && $filters['high_load']) {
            $constraints[] = 'load';
            $constraintCount++;
        }
        
        // Determine constraint level
        $level = 'none';
        if ($constraintCount === 1) {
            $level = 'low';
        } elseif ($constraintCount === 2) {
            $level = 'medium';
        } elseif ($constraintCount >= 3) {
            $level = 'high';
        }
        
        return [
            'is_constrained' => $constraintCount > 0,
            'level' => $level,
            'active_constraints' => $constraints,
            'count' => $constraintCount
        ];
    }
    
    /**
     * Simplify KPIs for degraded mode
     */
    private function simplifyKPIsForDegradedMode($kpis)
    {
        // Limit member breakdown to top 10 items
        if (isset($kpis['membership']['member_breakdown']) && 
            is_array($kpis['membership']['member_breakdown'])) {
            $kpis['membership']['member_breakdown'] = array_slice(
                $kpis['membership']['member_breakdown'],
                0,
                10
            );
        }
        
        // Limit center performance to top 10 centers
        if (isset($kpis['center_performance']) && is_array($kpis['center_performance'])) {
            $kpis['center_performance'] = array_slice($kpis['center_performance'], 0, 10);
        }
        
        // Remove detailed trend data
        if (isset($kpis['membership']['trends'])) {
            unset($kpis['membership']['trends']);
        }
        if (isset($kpis['financial']['trends'])) {
            unset($kpis['financial']['trends']);
        }
        
        return $kpis;
    }
    
    /**
     * Get membership KPIs using stored procedure
     */
    private function getMembershipKPIs($centerId, $startDate, $endDate)
    {
        try {
            $centerParam = $centerId ?? 'NULL';
            $query = "CALL sp_calculate_membership_kpis({$centerParam}, '{$startDate}', '{$endDate}')";
            
            $stmt = $this->conn->query($query);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate trends and status indicators
            if ($result) {
                $result['trends'] = $this->calculateTrends($result);
                $result['status_indicators'] = $this->calculateStatusIndicators($result);
            }
            
            return $result ?: [];
            
        } catch (Exception $e) {
            error_log("Membership KPIs error: " . $e->getMessage());
            return $this->getDefaultMembershipKPIs();
        }
    }
    
    /**
     * Get financial KPIs using stored procedure
     */
    private function getFinancialKPIs($centerId, $startDate, $endDate)
    {
        try {
            $centerParam = $centerId ?? 'NULL';
            $query = "CALL sp_calculate_financial_kpis({$centerParam}, '{$startDate}', '{$endDate}')";
            
            $stmt = $this->conn->query($query);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $result['trends'] = $this->calculateFinancialTrends($result);
                $result['status_indicators'] = $this->calculateFinancialStatus($result);
            }
            
            return $result ?: [];
            
        } catch (Exception $e) {
            error_log("Financial KPIs error: " . $e->getMessage());
            return $this->getDefaultFinancialKPIs();
        }
    }
    
    /**
     * Get growth KPIs using stored procedure
     */
    private function getGrowthKPIs($centerId)
    {
        try {
            $centerParam = $centerId ?? 'NULL';
            $query = "CALL sp_calculate_growth_kpis({$centerParam})";
            
            $stmt = $this->conn->query($query);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $result['trend_direction'] = $this->determineGrowthTrend($result);
            }
            
            return $result ?: [];
            
        } catch (Exception $e) {
            error_log("Growth KPIs error: " . $e->getMessage());
            return $this->getDefaultGrowthKPIs();
        }
    }
    
    /**
     * Get engagement KPIs using stored procedure
     */
    private function getEngagementKPIs($centerId)
    {
        try {
            $centerParam = $centerId ?? 'NULL';
            $query = "CALL sp_calculate_engagement_kpis({$centerParam})";
            
            $stmt = $this->conn->query($query);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $result['engagement_level'] = $this->determineEngagementLevel($result);
            }
            
            return $result ?: [];
            
        } catch (Exception $e) {
            error_log("Engagement KPIs error: " . $e->getMessage());
            return $this->getDefaultEngagementKPIs();
        }
    }
    
    /**
     * Get center performance KPIs
     */
    private function getCenterKPIs($centerId)
    {
        try {
            $centerParam = $centerId ?? 'NULL';
            $query = "CALL sp_calculate_center_kpis({$centerParam})";
            
            $stmt = $this->conn->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add rankings and comparisons
            if (!empty($results)) {
                $results = $this->addCenterRankings($results);
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Center KPIs error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get member analytics with filters and pagination
     * Supports large dataset handling with pagination
     */
    public function getMemberAnalytics($filters = [])
    {
        try {
            // Check for cache bypass
            $cacheDisabled = isset($filters['cache_disabled']) && $filters['cache_disabled'];
            
            $whereClauses = ['1=1']; // View already filters out deleted members
            $params = [];
            
            if (isset($filters['center_id'])) {
                $whereClauses[] = "center_id = :center_id";
                $params[':center_id'] = $filters['center_id'];
            }
            
            if (isset($filters['gender'])) {
                $whereClauses[] = "gender = :gender";
                $params[':gender'] = $filters['gender'];
            }
            
            if (isset($filters['status'])) {
                $whereClauses[] = "membership_status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (isset($filters['payment_status'])) {
                $whereClauses[] = "payment_status = :payment_status";
                $params[':payment_status'] = $filters['payment_status'];
            }
            
            if (isset($filters['date_from']) && isset($filters['date_to'])) {
                $whereClauses[] = "registration_date BETWEEN :date_from AND :date_to";
                $params[':date_from'] = $filters['date_from'];
                $params[':date_to'] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $whereClauses);
            
            // Get total count for pagination
            if (isset($filters['paginate']) && $filters['paginate']) {
                $countQuery = "SELECT COUNT(*) as total FROM member_analytics_view WHERE {$whereClause}";
                $countStmt = $this->conn->prepare($countQuery);
                $countStmt->execute($params);
                $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            }
            
            $query = "SELECT * FROM member_analytics_view WHERE {$whereClause}";
            
            // Default pagination for large datasets
            $page = $filters['page'] ?? 1;
            $pageSize = $filters['page_size'] ?? 100;
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : $pageSize;
            $offset = isset($filters['offset']) ? (int)$filters['offset'] : (($page - 1) * $pageSize);
            
            $query .= " LIMIT {$limit} OFFSET {$offset}";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Return paginated response if requested
            if (isset($filters['paginate']) && $filters['paginate']) {
                $response = [
                    'data' => $data,
                    'pagination' => [
                        'page' => $page,
                        'page_size' => $pageSize,
                        'total_records' => $totalCount,
                        'total_pages' => ceil($totalCount / $pageSize),
                        'has_next' => ($page * $pageSize) < $totalCount,
                        'has_previous' => $page > 1
                    ]
                ];
                
                // Add cache bypass indicator
                if ($cacheDisabled) {
                    $response['_cache_bypassed'] = true;
                    $response['_response_time_ms'] = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;
                }
                
                return $response;
            }
            
            // For non-paginated requests, return structured response
            $response = ['data' => $data];
            
            // Add cache bypass indicator
            if ($cacheDisabled) {
                $response['_cache_bypassed'] = true;
                $response['_response_time_ms'] = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;
            }
            
            return $response;
            
        } catch (Exception $e) {
            error_log("Member analytics error: " . $e->getMessage());
            return ['data' => []];
        }
    }
    
    /**
     * Stream member analytics data for large datasets
     * Uses generator pattern for memory-efficient processing
     */
    public function streamMemberAnalytics($filters = [])
    {
        try {
            $whereClauses = ['1=1']; // View already filters out deleted members
            $params = [];
            
            if (isset($filters['center_id'])) {
                $whereClauses[] = "center_id = :center_id";
                $params[':center_id'] = $filters['center_id'];
            }
            
            if (isset($filters['gender'])) {
                $whereClauses[] = "gender = :gender";
                $params[':gender'] = $filters['gender'];
            }
            
            if (isset($filters['status'])) {
                $whereClauses[] = "membership_status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (isset($filters['payment_status'])) {
                $whereClauses[] = "payment_status = :payment_status";
                $params[':payment_status'] = $filters['payment_status'];
            }
            
            if (isset($filters['date_from']) && isset($filters['date_to'])) {
                $whereClauses[] = "registration_date BETWEEN :date_from AND :date_to";
                $params[':date_from'] = $filters['date_from'];
                $params[':date_to'] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $whereClauses);
            $query = "SELECT * FROM member_analytics_view WHERE {$whereClause}";
            
            // Add limit if specified
            if (isset($filters['limit'])) {
                $query .= " LIMIT " . (int)$filters['limit'];
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            // Use generator to yield rows one at a time
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                yield $row;
            }
            
        } catch (Exception $e) {
            error_log("Stream member analytics error: " . $e->getMessage());
            return;
        }
    }
    
    /**
     * Get contribution analytics with filters and pagination
     * Supports large dataset handling with pagination
     */
    public function getContributionAnalytics($filters = [])
    {
        try {
            // Check for resource constraints
            $connectionConstrained = isset($filters['connection_constrained']) && $filters['connection_constrained'];
            $cacheDisabled = isset($filters['cache_disabled']) && $filters['cache_disabled'];
            
            // If connection constrained, try to use cached data
            if ($connectionConstrained && !$cacheDisabled) {
                $cacheKey = 'contribution_analytics_' . md5(json_encode($filters));
                $cachedData = $this->cache->get($cacheKey);
                
                if ($cachedData) {
                    $cachedData['_data_source'] = 'cache';
                    $cachedData['_degraded_mode'] = true;
                    $cachedData['_cache_age_seconds'] = time() - ($cachedData['_cached_at'] ?? time());
                    return $cachedData;
                }
            }
            
            $whereClauses = ['1=1'];
            $params = [];
            
            if (isset($filters['center_id'])) {
                $whereClauses[] = "center_id = :center_id";
                $params[':center_id'] = $filters['center_id'];
            }
            
            if (isset($filters['payment_status'])) {
                $whereClauses[] = "payment_status = :payment_status";
                $params[':payment_status'] = $filters['payment_status'];
            }
            
            if (isset($filters['payment_method'])) {
                $whereClauses[] = "payment_method = :payment_method";
                $params[':payment_method'] = $filters['payment_method'];
            }
            
            if (isset($filters['date_from']) && isset($filters['date_to'])) {
                $whereClauses[] = "contribution_date BETWEEN :date_from AND :date_to";
                $params[':date_from'] = $filters['date_from'];
                $params[':date_to'] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $whereClauses);
            
            // Get summary data (always available)
            $summaryQuery = "SELECT 
                SUM(amount) as total_amount,
                COUNT(*) as total_contributions,
                AVG(amount) as avg_contribution,
                SUM(paid_amount) as total_paid
                FROM contribution_analytics_view WHERE {$whereClause}";
            
            $summaryStmt = $this->conn->prepare($summaryQuery);
            $summaryStmt->execute($params);
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            if (isset($filters['paginate']) && $filters['paginate']) {
                $countQuery = "SELECT COUNT(*) as total FROM contribution_analytics_view WHERE {$whereClause}";
                $countStmt = $this->conn->prepare($countQuery);
                $countStmt->execute($params);
                $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            }
            
            $query = "SELECT * FROM contribution_analytics_view WHERE {$whereClause}";
            
            // Default pagination for large datasets
            $page = $filters['page'] ?? 1;
            $pageSize = $filters['page_size'] ?? 100;
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : $pageSize;
            $offset = isset($filters['offset']) ? (int)$filters['offset'] : (($page - 1) * $pageSize);
            
            $query .= " LIMIT {$limit} OFFSET {$offset}";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Build response with summary
            $response = [
                'summary' => $summary,
                'data' => $data
            ];
            
            // Return paginated response if requested
            if (isset($filters['paginate']) && $filters['paginate']) {
                $response['pagination'] = [
                    'page' => $page,
                    'page_size' => $pageSize,
                    'total_records' => $totalCount,
                    'total_pages' => ceil($totalCount / $pageSize),
                    'has_next' => ($page * $pageSize) < $totalCount,
                    'has_previous' => $page > 1
                ];
            }
            
            // Add degradation indicators
            if ($connectionConstrained) {
                $response['_data_source'] = 'database';
                $response['_degraded_mode'] = true;
            }
            
            // Cache the result for future constrained requests
            if (!$cacheDisabled) {
                $cacheKey = 'contribution_analytics_' . md5(json_encode($filters));
                $response['_cached_at'] = time();
                $this->cache->set($cacheKey, $response, 1800); // 30 minutes
            }
            
            return $response;
            
        } catch (Exception $e) {
            error_log("Contribution analytics error: " . $e->getMessage());
            // Return minimal data on error
            return [
                'summary' => [
                    'total_amount' => 0,
                    'total_contributions' => 0
                ],
                'data' => [],
                '_error' => false,
                '_degraded_mode' => true
            ];
        }
    }
    
    /**
     * Stream contribution analytics data for large datasets
     * Uses generator pattern for memory-efficient processing
     */
    public function streamContributionAnalytics($filters = [])
    {
        try {
            $whereClauses = ['1=1'];
            $params = [];
            
            if (isset($filters['center_id'])) {
                $whereClauses[] = "center_id = :center_id";
                $params[':center_id'] = $filters['center_id'];
            }
            
            if (isset($filters['payment_status'])) {
                $whereClauses[] = "payment_status = :payment_status";
                $params[':payment_status'] = $filters['payment_status'];
            }
            
            if (isset($filters['payment_method'])) {
                $whereClauses[] = "payment_method = :payment_method";
                $params[':payment_method'] = $filters['payment_method'];
            }
            
            if (isset($filters['date_from']) && isset($filters['date_to'])) {
                $whereClauses[] = "contribution_date BETWEEN :date_from AND :date_to";
                $params[':date_from'] = $filters['date_from'];
                $params[':date_to'] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $whereClauses);
            $query = "SELECT * FROM contribution_analytics_view WHERE {$whereClause}";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            // Use generator to yield rows one at a time
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                yield $row;
            }
            
        } catch (Exception $e) {
            error_log("Stream contribution analytics error: " . $e->getMessage());
            return;
        }
    }
    
    /**
     * Get center analytics
     */
    public function getCenterAnalytics($filters = [])
    {
        try {
            $whereClauses = ['c.status = "active"'];
            $params = [];
            
            if (isset($filters['region'])) {
                $whereClauses[] = "c.region = :region";
                $params[':region'] = $filters['region'];
            }
            
            if (isset($filters['center_id'])) {
                $whereClauses[] = "c.id = :center_id";
                $params[':center_id'] = $filters['center_id'];
            }
            
            $whereClause = implode(' AND ', $whereClauses);
            
            $query = "SELECT * FROM center_performance_view c WHERE {$whereClause}";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Center analytics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate trend analysis for a specific metric
     */
    public function generateTrendAnalysis($metric, $dateRange)
    {
        $period = $dateRange['period'] ?? 'monthly';
        $startDate = $dateRange['start_date'] ?? date('Y-m-d', strtotime('-12 months'));
        $endDate = $dateRange['end_date'] ?? date('Y-m-d');
        $centerId = $dateRange['center_id'] ?? null;
        
        try {
            if ($metric === 'member_registration') {
                return $this->getMemberRegistrationTrend($centerId, $period, $startDate, $endDate);
            } elseif ($metric === 'contribution') {
                return $this->getContributionTrend($centerId, $period, $startDate, $endDate);
            } else {
                return ['error' => 'Unknown metric type'];
            }
        } catch (Exception $e) {
            error_log("Trend analysis error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get member registration trend
     */
    private function getMemberRegistrationTrend($centerId, $period, $startDate, $endDate)
    {
        $centerParam = $centerId ?? 'NULL';
        $query = "CALL sp_get_member_registration_trend({$centerParam}, '{$period}', '{$startDate}', '{$endDate}')";
        
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get contribution trend
     */
    private function getContributionTrend($centerId, $period, $startDate, $endDate)
    {
        $centerParam = $centerId ?? 'NULL';
        $query = "CALL sp_get_contribution_trend({$centerParam}, '{$period}', '{$startDate}', '{$endDate}')";
        
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics()
    {
        return $this->perfMonitor->getPerformanceMetrics();
    }
    
    /**
     * Calculate trends from KPI data
     */
    private function calculateTrends($data)
    {
        $trends = [];
        
        // Member growth trend
        if (isset($data['new_registrations_30d']) && isset($data['total_members'])) {
            $growthRate = $data['total_members'] > 0 
                ? ($data['new_registrations_30d'] / $data['total_members']) * 100 
                : 0;
            $trends['member_growth'] = [
                'value' => round($growthRate, 2),
                'direction' => $growthRate > 5 ? 'up' : ($growthRate < 2 ? 'down' : 'stable')
            ];
        }
        
        // Payment compliance trend
        if (isset($data['payment_compliance_rate'])) {
            $trends['payment_compliance'] = [
                'value' => $data['payment_compliance_rate'],
                'direction' => $data['payment_compliance_rate'] > 80 ? 'up' : 'down'
            ];
        }
        
        return $trends;
    }
    
    /**
     * Calculate status indicators
     */
    private function calculateStatusIndicators($data)
    {
        $indicators = [];
        
        // Payment compliance status
        if (isset($data['payment_compliance_rate'])) {
            $rate = $data['payment_compliance_rate'];
            $indicators['payment_compliance'] = [
                'status' => $rate >= 80 ? 'good' : ($rate >= 60 ? 'warning' : 'critical'),
                'color' => $rate >= 80 ? 'green' : ($rate >= 60 ? 'yellow' : 'red')
            ];
        }
        
        // Member activity status
        if (isset($data['active_members']) && isset($data['total_members'])) {
            $activeRate = $data['total_members'] > 0 
                ? ($data['active_members'] / $data['total_members']) * 100 
                : 0;
            $indicators['member_activity'] = [
                'status' => $activeRate >= 70 ? 'good' : ($activeRate >= 50 ? 'warning' : 'critical'),
                'color' => $activeRate >= 70 ? 'green' : ($activeRate >= 50 ? 'yellow' : 'red')
            ];
        }
        
        return $indicators;
    }
    
    /**
     * Calculate financial trends
     */
    private function calculateFinancialTrends($data)
    {
        $trends = [];
        
        if (isset($data['revenue_30d']) && isset($data['total_revenue'])) {
            $recentRevenue = $data['revenue_30d'];
            $trends['revenue_trend'] = [
                'value' => $recentRevenue,
                'direction' => $recentRevenue > 0 ? 'up' : 'stable'
            ];
        }
        
        return $trends;
    }
    
    /**
     * Calculate financial status
     */
    private function calculateFinancialStatus($data)
    {
        $indicators = [];
        
        if (isset($data['collection_efficiency_rate'])) {
            $rate = $data['collection_efficiency_rate'];
            $indicators['collection_efficiency'] = [
                'status' => $rate >= 75 ? 'good' : ($rate >= 50 ? 'warning' : 'critical'),
                'color' => $rate >= 75 ? 'green' : ($rate >= 50 ? 'yellow' : 'red')
            ];
        }
        
        return $indicators;
    }
    
    /**
     * Determine growth trend direction
     */
    private function determineGrowthTrend($data)
    {
        if (isset($data['member_growth_percentage'])) {
            $growth = $data['member_growth_percentage'];
            return $growth > 5 ? 'strong_growth' : ($growth > 0 ? 'moderate_growth' : 'decline');
        }
        return 'stable';
    }
    
    /**
     * Determine engagement level
     */
    private function determineEngagementLevel($data)
    {
        if (isset($data['engagement_rate_30d'])) {
            $rate = $data['engagement_rate_30d'];
            return $rate >= 60 ? 'high' : ($rate >= 30 ? 'medium' : 'low');
        }
        return 'unknown';
    }
    
    /**
     * Add center rankings
     */
    private function addCenterRankings($centers)
    {
        // Sort by revenue per member
        usort($centers, function($a, $b) {
            return $b['revenue_per_member'] <=> $a['revenue_per_member'];
        });
        
        // Add rankings
        foreach ($centers as $index => &$center) {
            $center['revenue_rank'] = $index + 1;
        }
        
        return $centers;
    }
    
    /**
     * Default fallback KPIs
     */
    private function getDefaultMembershipKPIs()
    {
        return [
            'total_members' => 0,
            'active_members' => 0,
            'new_registrations' => 0,
            'paid_members' => 0,
            'unpaid_members' => 0,
            'payment_compliance_rate' => 0
        ];
    }
    
    private function getDefaultFinancialKPIs()
    {
        return [
            'total_revenue' => 0,
            'revenue_30d' => 0,
            'avg_contribution' => 0,
            'pending_revenue' => 0,
            'collection_efficiency_rate' => 0
        ];
    }
    
    private function getDefaultGrowthKPIs()
    {
        return [
            'current_month_members' => 0,
            'previous_month_members' => 0,
            'member_growth' => 0,
            'member_growth_percentage' => 0
        ];
    }
    
    private function getDefaultEngagementKPIs()
    {
        return [
            'active_7d' => 0,
            'active_30d' => 0,
            'engagement_rate_30d' => 0
        ];
    }
}
