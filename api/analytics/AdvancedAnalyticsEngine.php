<?php
/**
 * WDB Advanced Analytics Engine
 * 
 * Provides intelligent insights, predictive analytics, and comprehensive reporting
 * capabilities for the WDB Management System.
 * 
 * Features:
 * - Real-time KPI calculations
 * - Predictive modeling
 * - Trend analysis
 * - Automated insights generation
 * - Performance benchmarking
 */

require_once __DIR__ . '/../config/database.php';

class AdvancedAnalyticsEngine {
    private $db;
    private $cache = [];
    private $insights = [];
    
    public function __construct() {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }
    
    /**
     * Generate Executive Dashboard Data
     */
    public function getExecutiveDashboard($dateRange = '30_days') {
        $data = [
            'kpis' => $this->calculateExecutiveKPIs($dateRange),
            'trends' => $this->getTrendAnalysis($dateRange),
            'insights' => $this->generateAutomatedInsights($dateRange),
            'forecasts' => $this->getFinancialForecasts($dateRange),
            'alerts' => $this->getSystemAlerts()
        ];
        
        return $data;
    }
    
    /**
     * Calculate Executive KPIs
     */
    private function calculateExecutiveKPIs($dateRange) {
        $dateFilter = $this->getDateFilter($dateRange);
        
        // Total Active Members
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total_members,
                   COUNT(CASE WHEN created_at >= ? THEN 1 END) as new_members,
                   COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_members
            FROM users 
            WHERE status = 'active'
        ");
        $stmt->execute([$dateFilter['start']]);
        $memberStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Financial KPIs
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_contributions,
                SUM(amount) as total_revenue,
                AVG(amount) as avg_contribution,
                COUNT(DISTINCT member_id) as contributing_members
            FROM contributions 
            WHERE created_at >= ? AND status = 'confirmed'
        ");
        $stmt->execute([$dateFilter['start']]);
        $financialStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate growth rates
        $memberGrowthRate = $this->calculateGrowthRate('users', $dateRange);
        $revenueGrowthRate = $this->calculateGrowthRate('contributions', $dateRange, 'amount');
        
        return [
            'total_members' => (int)$memberStats['total_members'],
            'new_members' => (int)$memberStats['new_members'],
            'active_members' => (int)$memberStats['active_members'],
            'member_growth_rate' => $memberGrowthRate,
            'total_revenue' => (float)$financialStats['total_revenue'],
            'total_contributions' => (int)$financialStats['total_contributions'],
            'avg_contribution' => (float)$financialStats['avg_contribution'],
            'contributing_members' => (int)$financialStats['contributing_members'],
            'revenue_growth_rate' => $revenueGrowthRate,
            'member_engagement_rate' => $this->calculateEngagementRate(),
            'system_health_score' => $this->calculateSystemHealthScore()
        ];
    }
    
    /**
     * Get Trend Analysis
     */
    private function getTrendAnalysis($dateRange) {
        return [
            'member_trends' => $this->getMemberTrends($dateRange),
            'revenue_trends' => $this->getRevenueTrends($dateRange),
            'engagement_trends' => $this->getEngagementTrends($dateRange),
            'seasonal_patterns' => $this->getSeasonalPatterns()
        ];
    }
    
    /**
     * Get Member Trends
     */
    private function getMemberTrends($dateRange) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as new_registrations,
                SUM(COUNT(*)) OVER (ORDER BY DATE(created_at)) as cumulative_members
            FROM users 
            WHERE created_at >= ? AND status = 'active'
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $dateFilter = $this->getDateFilter($dateRange);
        $stmt->execute([$dateFilter['start']]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get Revenue Trends
     */
    private function getRevenueTrends($dateRange) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as contribution_count,
                SUM(amount) as daily_revenue,
                AVG(amount) as avg_contribution
            FROM contributions 
            WHERE created_at >= ? AND status = 'confirmed'
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $dateFilter = $this->getDateFilter($dateRange);
        $stmt->execute([$dateFilter['start']]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate Member Analytics
     */
    public function getMemberAnalytics($dateRange = '30_days') {
        return [
            'demographics' => $this->getMemberDemographics(),
            'lifecycle_analysis' => $this->getMemberLifecycleAnalysis(),
            'engagement_scoring' => $this->getEngagementScoring(),
            'retention_analysis' => $this->getRetentionAnalysis($dateRange),
            'churn_prediction' => $this->getChurnPrediction()
        ];
    }
    
    /**
     * Get Member Demographics
     */
    private function getMemberDemographics() {
        // Gender distribution - handle both members and users tables
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(m.gender, 'Not Specified') as gender, 
                COUNT(*) as count 
            FROM users u
            LEFT JOIN members m ON u.id = m.user_id
            WHERE u.status = 'active'
            GROUP BY COALESCE(m.gender, 'Not Specified')
        ");
        $stmt->execute();
        $genderDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Age distribution - handle both members and users tables
        $stmt = $this->db->prepare("
            SELECT 
                CASE 
                    WHEN m.date_of_birth IS NULL THEN 'Not Specified'
                    WHEN TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) < 25 THEN '18-24'
                    WHEN TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) < 35 THEN '25-34'
                    WHEN TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) < 45 THEN '35-44'
                    WHEN TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) < 55 THEN '45-54'
                    WHEN TIMESTAMPDIFF(YEAR, m.date_of_birth, CURDATE()) < 65 THEN '55-64'
                    ELSE '65+'
                END as age_group,
                COUNT(*) as count
            FROM users u
            LEFT JOIN members m ON u.id = m.user_id
            WHERE u.status = 'active'
            GROUP BY age_group
            ORDER BY age_group
        ");
        $stmt->execute();
        $ageDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Geographic distribution - handle both members and users tables
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(m.region, 'Not Specified') as region, 
                COUNT(*) as count 
            FROM users u
            LEFT JOIN members m ON u.id = m.user_id
            WHERE u.status = 'active'
            GROUP BY COALESCE(m.region, 'Not Specified')
            ORDER BY count DESC
        ");
        $stmt->execute();
        $geographicDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'gender_distribution' => $genderDistribution,
            'age_distribution' => $ageDistribution,
            'geographic_distribution' => $geographicDistribution
        ];
    }
    
    /**
     * Get Member Lifecycle Analysis
     */
    private function getMemberLifecycleAnalysis() {
        // Member registration trends over time
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as new_members,
                SUM(COUNT(*)) OVER (ORDER BY DATE(created_at)) as cumulative_members
            FROM users 
            WHERE status = 'active' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute();
        $registrationTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Member activity levels
        $stmt = $this->db->prepare("
            SELECT 
                CASE 
                    WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Highly Active'
                    WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Active'
                    WHEN last_login >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 'Moderately Active'
                    ELSE 'Inactive'
                END as activity_level,
                COUNT(*) as count
            FROM users 
            WHERE status = 'active'
            GROUP BY activity_level
        ");
        $stmt->execute();
        $activityLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'registration_trends' => $registrationTrends,
            'activity_levels' => $activityLevels
        ];
    }
    
    /**
     * Get Engagement Scoring
     */
    private function getEngagementScoring() {
        $stmt = $this->db->prepare("
            SELECT 
                u.id,
                u.username,
                u.email,
                DATEDIFF(NOW(), u.last_login) as days_since_login,
                COUNT(c.id) as total_contributions,
                COALESCE(SUM(c.amount), 0) as total_contributed,
                CASE 
                    WHEN DATEDIFF(NOW(), u.last_login) <= 7 AND COUNT(c.id) >= 4 THEN 10
                    WHEN DATEDIFF(NOW(), u.last_login) <= 14 AND COUNT(c.id) >= 2 THEN 8
                    WHEN DATEDIFF(NOW(), u.last_login) <= 30 AND COUNT(c.id) >= 1 THEN 6
                    WHEN DATEDIFF(NOW(), u.last_login) <= 60 THEN 4
                    WHEN DATEDIFF(NOW(), u.last_login) <= 90 THEN 2
                    ELSE 1
                END as engagement_score
            FROM users u
            LEFT JOIN contributions c ON u.id = c.member_id AND c.status = 'confirmed' 
                AND c.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            WHERE u.status = 'active'
            GROUP BY u.id
            ORDER BY engagement_score DESC
            LIMIT 100
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get Retention Analysis
     */
    private function getRetentionAnalysis($dateRange) {
        $dateFilter = $this->getDateFilter($dateRange);
        
        // Calculate retention rates by cohort
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as cohort_month,
                COUNT(*) as cohort_size,
                COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_members,
                ROUND((COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) / COUNT(*)) * 100, 2) as retention_rate
            FROM users 
            WHERE status = 'active' AND created_at >= ?
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY cohort_month
        ");
        $stmt->execute([$dateFilter['start']]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate Financial Analytics
     */
    public function getFinancialAnalytics($dateRange = '30_days') {
        return [
            'revenue_analysis' => $this->getRevenueAnalysis($dateRange),
            'contribution_patterns' => $this->getContributionPatterns($dateRange),
            'payment_method_analysis' => $this->getPaymentMethodAnalysis($dateRange),
            'financial_forecasting' => $this->getFinancialForecasting($dateRange),
            'member_value_analysis' => $this->getMemberValueAnalysis()
        ];
    }
    
    /**
     * Get Revenue Analysis
     */
    private function getRevenueAnalysis($dateRange) {
        $dateFilter = $this->getDateFilter($dateRange);
        
        $stmt = $this->db->prepare("
            SELECT 
                SUM(amount) as total_revenue,
                COUNT(*) as total_transactions,
                AVG(amount) as avg_transaction,
                COUNT(DISTINCT member_id) as unique_contributors,
                MIN(amount) as min_contribution,
                MAX(amount) as max_contribution,
                STDDEV(amount) as amount_stddev
            FROM contributions 
            WHERE created_at >= ? AND status = 'confirmed'
        ");
        $stmt->execute([$dateFilter['start']]);
        $revenueStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Revenue by contribution type
        $stmt = $this->db->prepare("
            SELECT 
                contribution_type,
                SUM(amount) as revenue,
                COUNT(*) as count,
                AVG(amount) as avg_amount
            FROM contributions 
            WHERE created_at >= ? AND status = 'confirmed'
            GROUP BY contribution_type
            ORDER BY revenue DESC
        ");
        $stmt->execute([$dateFilter['start']]);
        $revenueByType = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'summary' => $revenueStats,
            'by_type' => $revenueByType,
            'growth_rate' => $this->calculateGrowthRate('contributions', $dateRange, 'amount')
        ];
    }
    
    /**
     * Generate Predictive Analytics
     */
    public function getPredictiveAnalytics() {
        return [
            'churn_prediction' => $this->getChurnPrediction(),
            'revenue_forecast' => $this->getRevenueForecast(),
            'engagement_prediction' => $this->getEngagementPrediction(),
            'seasonal_forecasts' => $this->getSeasonalForecasts()
        ];
    }
    
    /**
     * Get Churn Prediction
     */
    private function getChurnPrediction() {
        $stmt = $this->db->prepare("
            SELECT 
                u.id,
                u.username,
                u.email,
                DATEDIFF(NOW(), u.last_login) as days_since_login,
                COUNT(c.id) as total_contributions,
                COALESCE(SUM(c.amount), 0) as total_contributed,
                DATEDIFF(NOW(), MAX(c.created_at)) as days_since_contribution,
                CASE 
                    WHEN DATEDIFF(NOW(), u.last_login) > 90 THEN 'High'
                    WHEN DATEDIFF(NOW(), u.last_login) > 60 THEN 'Medium'
                    WHEN DATEDIFF(NOW(), u.last_login) > 30 THEN 'Low'
                    ELSE 'Very Low'
                END as churn_risk
            FROM users u
            LEFT JOIN contributions c ON u.id = c.member_id AND c.status = 'confirmed'
            WHERE u.status = 'active'
            GROUP BY u.id
            HAVING churn_risk IN ('High', 'Medium')
            ORDER BY days_since_login DESC
            LIMIT 100
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate Automated Insights
     */
    private function generateAutomatedInsights($dateRange) {
        $insights = [];
        
        // Member growth insight
        $memberGrowth = $this->calculateGrowthRate('users', $dateRange);
        if ($memberGrowth > 10) {
            $insights[] = [
                'type' => 'positive',
                'title' => 'Strong Member Growth',
                'message' => "Member growth is {$memberGrowth}% above average, indicating successful outreach efforts.",
                'action' => 'Continue current marketing strategies'
            ];
        } elseif ($memberGrowth < -5) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Declining Member Growth',
                'message' => "Member growth has declined by {$memberGrowth}%. Consider reviewing outreach strategies.",
                'action' => 'Implement member acquisition campaigns'
            ];
        }
        
        // Revenue insight
        $revenueGrowth = $this->calculateGrowthRate('contributions', $dateRange, 'amount');
        if ($revenueGrowth > 15) {
            $insights[] = [
                'type' => 'positive',
                'title' => 'Excellent Revenue Growth',
                'message' => "Revenue has grown by {$revenueGrowth}%, exceeding expectations.",
                'action' => 'Maintain current contribution strategies'
            ];
        }
        
        // Engagement insight
        $engagementRate = $this->calculateEngagementRate();
        if ($engagementRate < 30) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Low Member Engagement',
                'message' => "Only {$engagementRate}% of members are actively engaged. Consider engagement initiatives.",
                'action' => 'Launch member engagement campaigns'
            ];
        }
        
        return $insights;
    }
    
    /**
     * Calculate Growth Rate
     */
    private function calculateGrowthRate($table, $dateRange, $column = null) {
        $dateFilter = $this->getDateFilter($dateRange);
        $previousPeriod = $this->getPreviousPeriod($dateRange);
        
        if ($column) {
            $selectClause = "SUM($column)";
            $whereClause = "WHERE status = 'confirmed'";
        } else {
            $selectClause = "COUNT(*)";
            $whereClause = "WHERE status = 'active'";
        }
        
        // Current period
        $stmt = $this->db->prepare("
            SELECT $selectClause as current_value 
            FROM $table 
            $whereClause AND created_at >= ? AND created_at < ?
        ");
        $stmt->execute([$dateFilter['start'], $dateFilter['end']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC)['current_value'] ?? 0;
        
        // Previous period
        $stmt = $this->db->prepare("
            SELECT $selectClause as previous_value 
            FROM $table 
            $whereClause AND created_at >= ? AND created_at < ?
        ");
        $stmt->execute([$previousPeriod['start'], $previousPeriod['end']]);
        $previous = $stmt->fetch(PDO::FETCH_ASSOC)['previous_value'] ?? 0;
        
        if ($previous == 0) return 0;
        
        return round((($current - $previous) / $previous) * 100, 2);
    }
    
    /**
     * Calculate Engagement Rate
     */
    private function calculateEngagementRate() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_users
            FROM users 
            WHERE status = 'active'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total_users'] == 0) return 0;
        
        return round(($result['active_users'] / $result['total_users']) * 100, 2);
    }
    
    /**
     * Calculate System Health Score
     */
    private function calculateSystemHealthScore() {
        // This is a simplified calculation - in reality, you'd include more metrics
        $engagementRate = $this->calculateEngagementRate();
        $errorRate = 0; // Would be calculated from error logs
        $uptime = 99.9; // Would be calculated from system monitoring
        
        $healthScore = ($engagementRate * 0.4) + ((100 - $errorRate) * 0.3) + ($uptime * 0.3);
        
        return round($healthScore, 1);
    }
    
    /**
     * Get Date Filter
     */
    private function getDateFilter($dateRange) {
        $end = date('Y-m-d H:i:s');
        
        switch ($dateRange) {
            case '7_days':
                $start = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case '30_days':
                $start = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            case '90_days':
                $start = date('Y-m-d H:i:s', strtotime('-90 days'));
                break;
            case '1_year':
                $start = date('Y-m-d H:i:s', strtotime('-1 year'));
                break;
            default:
                $start = date('Y-m-d H:i:s', strtotime('-30 days'));
        }
        
        return ['start' => $start, 'end' => $end];
    }
    
    /**
     * Get Previous Period
     */
    private function getPreviousPeriod($dateRange) {
        switch ($dateRange) {
            case '7_days':
                $start = date('Y-m-d H:i:s', strtotime('-14 days'));
                $end = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case '30_days':
                $start = date('Y-m-d H:i:s', strtotime('-60 days'));
                $end = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            case '90_days':
                $start = date('Y-m-d H:i:s', strtotime('-180 days'));
                $end = date('Y-m-d H:i:s', strtotime('-90 days'));
                break;
            case '1_year':
                $start = date('Y-m-d H:i:s', strtotime('-2 years'));
                $end = date('Y-m-d H:i:s', strtotime('-1 year'));
                break;
            default:
                $start = date('Y-m-d H:i:s', strtotime('-60 days'));
                $end = date('Y-m-d H:i:s', strtotime('-30 days'));
        }
        
        return ['start' => $start, 'end' => $end];
    }
    
    /**
     * Get System Alerts
     */
    private function getSystemAlerts() {
        $alerts = [];
        
        // Check for high churn risk members
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as high_risk_count 
            FROM users 
            WHERE status = 'active' 
            AND DATEDIFF(NOW(), last_login) > 90
        ");
        $stmt->execute();
        $highRiskCount = $stmt->fetch(PDO::FETCH_ASSOC)['high_risk_count'];
        
        if ($highRiskCount > 10) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'High Churn Risk',
                'message' => "{$highRiskCount} members haven't logged in for over 90 days",
                'action' => 'Review member engagement strategies'
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Get Engagement Trends
     */
    private function getEngagementTrends($dateRange) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(last_login) as date,
                COUNT(*) as active_users,
                COUNT(CASE WHEN DATEDIFF(NOW(), last_login) <= 7 THEN 1 END) as highly_active
            FROM users 
            WHERE status = 'active' AND last_login >= ?
            GROUP BY DATE(last_login)
            ORDER BY date
        ");
        $dateFilter = $this->getDateFilter($dateRange);
        $stmt->execute([$dateFilter['start']]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get Seasonal Patterns
     */
    private function getSeasonalPatterns() {
        $stmt = $this->db->prepare("
            SELECT 
                MONTH(created_at) as month,
                MONTHNAME(created_at) as month_name,
                COUNT(*) as registrations,
                AVG(COUNT(*)) OVER() as avg_registrations
            FROM users 
            WHERE status = 'active' AND created_at >= DATE_SUB(NOW(), INTERVAL 2 YEAR)
            GROUP BY MONTH(created_at), MONTHNAME(created_at)
            ORDER BY month
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get Contribution Patterns
     */
    private function getContributionPatterns($dateRange) {
        $dateFilter = $this->getDateFilter($dateRange);
        
        // Monthly contribution patterns
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as contribution_count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
            FROM contributions 
            WHERE created_at >= ? AND status = 'confirmed'
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute([$dateFilter['start']]);
        $monthlyPatterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Weekly patterns
        $stmt = $this->db->prepare("
            SELECT 
                DAYNAME(created_at) as day_name,
                DAYOFWEEK(created_at) as day_number,
                COUNT(*) as contribution_count,
                AVG(amount) as avg_amount
            FROM contributions 
            WHERE created_at >= ? AND status = 'confirmed'
            GROUP BY DAYOFWEEK(created_at), DAYNAME(created_at)
            ORDER BY day_number
        ");
        $stmt->execute([$dateFilter['start']]);
        $weeklyPatterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'monthly' => $monthlyPatterns,
            'weekly' => $weeklyPatterns
        ];
    }
    
    /**
     * Get Payment Method Analysis
     */
    private function getPaymentMethodAnalysis($dateRange) {
        $dateFilter = $this->getDateFilter($dateRange);
        
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(payment_method, 'Not Specified') as method,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
            FROM contributions 
            WHERE created_at >= ? AND status = 'confirmed'
            GROUP BY payment_method
            ORDER BY total_amount DESC
        ");
        $stmt->execute([$dateFilter['start']]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get Financial Forecasting
     */
    private function getFinancialForecasting($dateRange) {
        // Simple linear regression for revenue forecasting
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                SUM(amount) as daily_revenue
            FROM contributions 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
            AND status = 'confirmed'
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute();
        $historicalData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate trend and generate forecast
        $forecast = $this->calculateRevenueForecast($historicalData);
        
        return [
            'historical_data' => $historicalData,
            'forecast' => $forecast,
            'trend_analysis' => $this->analyzeTrend($historicalData)
        ];
    }
    
    /**
     * Calculate Revenue Forecast
     */
    private function calculateRevenueForecast($historicalData) {
        if (empty($historicalData)) {
            return [];
        }
        
        // Simple moving average forecast
        $recentData = array_slice($historicalData, -30); // Last 30 days
        $avgRevenue = array_sum(array_column($recentData, 'daily_revenue')) / count($recentData);
        
        $forecast = [];
        $baseDate = new DateTime();
        
        for ($i = 1; $i <= 30; $i++) {
            $forecastDate = clone $baseDate;
            $forecastDate->add(new DateInterval("P{$i}D"));
            
            // Add some seasonal variation
            $seasonalFactor = 1 + (sin(($i / 30) * 2 * pi()) * 0.1);
            $forecastValue = $avgRevenue * $seasonalFactor;
            
            $forecast[] = [
                'date' => $forecastDate->format('Y-m-d'),
                'forecasted_revenue' => round($forecastValue, 2),
                'confidence_upper' => round($forecastValue * 1.2, 2),
                'confidence_lower' => round($forecastValue * 0.8, 2)
            ];
        }
        
        return $forecast;
    }
    
    /**
     * Analyze Trend
     */
    private function analyzeTrend($data) {
        if (count($data) < 2) {
            return ['trend' => 'insufficient_data', 'slope' => 0];
        }
        
        $n = count($data);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        
        foreach ($data as $i => $point) {
            $x = $i;
            $y = (float)$point['daily_revenue'];
            
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        
        $trend = 'stable';
        if ($slope > 100) {
            $trend = 'increasing';
        } elseif ($slope < -100) {
            $trend = 'decreasing';
        }
        
        return [
            'trend' => $trend,
            'slope' => round($slope, 2),
            'direction' => $slope > 0 ? 'upward' : ($slope < 0 ? 'downward' : 'stable')
        ];
    }
    
    /**
     * Get Member Value Analysis
     */
    private function getMemberValueAnalysis() {
        $stmt = $this->db->prepare("
            SELECT 
                u.id,
                u.username,
                COUNT(c.id) as total_contributions,
                COALESCE(SUM(c.amount), 0) as lifetime_value,
                COALESCE(AVG(c.amount), 0) as avg_contribution,
                DATEDIFF(NOW(), u.created_at) as member_age_days,
                CASE 
                    WHEN COALESCE(SUM(c.amount), 0) > 10000 THEN 'High Value'
                    WHEN COALESCE(SUM(c.amount), 0) > 5000 THEN 'Medium Value'
                    WHEN COALESCE(SUM(c.amount), 0) > 1000 THEN 'Low Value'
                    ELSE 'New/Inactive'
                END as value_segment
            FROM users u
            LEFT JOIN contributions c ON u.id = c.member_id AND c.status = 'confirmed'
            WHERE u.status = 'active'
            GROUP BY u.id
            ORDER BY lifetime_value DESC
            LIMIT 100
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get Revenue Forecast (for predictive analytics)
     */
    private function getRevenueForecast() {
        // Get historical revenue data
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(amount) as monthly_revenue
            FROM contributions 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) 
            AND status = 'confirmed'
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute();
        $historicalData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate forecast for next 6 months
        $forecast = [];
        $baseDate = new DateTime('first day of next month');
        
        if (!empty($historicalData)) {
            $avgRevenue = array_sum(array_column($historicalData, 'monthly_revenue')) / count($historicalData);
            
            for ($i = 0; $i < 6; $i++) {
                $forecastDate = clone $baseDate;
                $forecastDate->add(new DateInterval("P{$i}M"));
                
                // Add growth trend
                $growthFactor = 1 + ($i * 0.02); // 2% monthly growth
                $forecastValue = $avgRevenue * $growthFactor;
                
                $forecast[] = [
                    'month' => $forecastDate->format('Y-m'),
                    'forecasted_revenue' => round($forecastValue, 2),
                    'confidence_interval' => [
                        'upper' => round($forecastValue * 1.15, 2),
                        'lower' => round($forecastValue * 0.85, 2)
                    ]
                ];
            }
        }
        
        return [
            'historical' => $historicalData,
            'forecast' => $forecast
        ];
    }
    
    /**
     * Get Engagement Prediction
     */
    private function getEngagementPrediction() {
        $stmt = $this->db->prepare("
            SELECT 
                u.id,
                u.username,
                DATEDIFF(NOW(), u.last_login) as days_since_login,
                COUNT(c.id) as recent_contributions,
                CASE 
                    WHEN DATEDIFF(NOW(), u.last_login) <= 7 THEN 'High'
                    WHEN DATEDIFF(NOW(), u.last_login) <= 30 THEN 'Medium'
                    WHEN DATEDIFF(NOW(), u.last_login) <= 90 THEN 'Low'
                    ELSE 'Very Low'
                END as predicted_engagement
            FROM users u
            LEFT JOIN contributions c ON u.id = c.member_id 
                AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND c.status = 'confirmed'
            WHERE u.status = 'active'
            GROUP BY u.id
            ORDER BY days_since_login
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get Seasonal Forecasts
     */
    private function getSeasonalForecasts() {
        // Analyze seasonal patterns from historical data
        $stmt = $this->db->prepare("
            SELECT 
                MONTH(created_at) as month,
                MONTHNAME(created_at) as month_name,
                AVG(amount) as avg_contribution,
                COUNT(*) as contribution_count,
                SUM(amount) as total_revenue
            FROM contributions 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 YEAR) 
            AND status = 'confirmed'
            GROUP BY MONTH(created_at), MONTHNAME(created_at)
            ORDER BY month
        ");
        $stmt->execute();
        $seasonalData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate seasonal forecast
        $currentMonth = (int)date('n');
        $forecast = [];
        
        foreach ($seasonalData as $data) {
            $monthNum = (int)$data['month'];
            if ($monthNum >= $currentMonth) {
                $forecast[] = [
                    'month' => $data['month_name'],
                    'predicted_revenue' => round($data['total_revenue'], 2),
                    'predicted_contributions' => round($data['contribution_count']),
                    'seasonal_factor' => round($data['total_revenue'] / (array_sum(array_column($seasonalData, 'total_revenue')) / 12), 2)
                ];
            }
        }
        
        return $forecast;
    }
    
    /**
     * Get Financial Forecasts
     */
    private function getFinancialForecasts($dateRange) {
        return [
            'revenue_forecast' => $this->getRevenueForecast(),
            'contribution_forecast' => $this->getContributionForecast(),
            'growth_projections' => $this->getGrowthProjections()
        ];
    }
    
    /**
     * Get Contribution Forecast
     */
    private function getContributionForecast() {
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as contribution_count,
                AVG(amount) as avg_amount
            FROM contributions 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
            AND status = 'confirmed'
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute();
        $historicalData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($historicalData)) {
            return [];
        }
        
        $avgCount = array_sum(array_column($historicalData, 'contribution_count')) / count($historicalData);
        $avgAmount = array_sum(array_column($historicalData, 'avg_amount')) / count($historicalData);
        
        $forecast = [];
        $baseDate = new DateTime('first day of next month');
        
        for ($i = 0; $i < 3; $i++) {
            $forecastDate = clone $baseDate;
            $forecastDate->add(new DateInterval("P{$i}M"));
            
            $forecast[] = [
                'month' => $forecastDate->format('Y-m'),
                'predicted_count' => round($avgCount * (1 + $i * 0.05)), // 5% growth
                'predicted_avg_amount' => round($avgAmount * (1 + $i * 0.02), 2) // 2% growth
            ];
        }
        
        return $forecast;
    }
    
    /**
     * Get Growth Projections
     */
    private function getGrowthProjections() {
        $memberGrowth = $this->calculateGrowthRate('users', '90_days');
        $revenueGrowth = $this->calculateGrowthRate('contributions', '90_days', 'amount');
        
        return [
            'member_growth_rate' => $memberGrowth,
            'revenue_growth_rate' => $revenueGrowth,
            'projected_members_6m' => $this->projectMemberGrowth(6),
            'projected_revenue_6m' => $this->projectRevenueGrowth(6)
        ];
    }
    
    /**
     * Project Member Growth
     */
    private function projectMemberGrowth($months) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as current_members FROM users WHERE status = 'active'");
        $stmt->execute();
        $currentMembers = $stmt->fetch(PDO::FETCH_ASSOC)['current_members'];
        
        $growthRate = $this->calculateGrowthRate('users', '90_days') / 100;
        $monthlyGrowthRate = $growthRate / 3; // Convert quarterly to monthly
        
        return round($currentMembers * pow(1 + $monthlyGrowthRate, $months));
    }
    
    /**
     * Project Revenue Growth
     */
    private function projectRevenueGrowth($months) {
        $stmt = $this->db->prepare("
            SELECT SUM(amount) as current_monthly_revenue 
            FROM contributions 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) 
            AND status = 'confirmed'
        ");
        $stmt->execute();
        $currentRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['current_monthly_revenue'] ?? 0;
        
        $growthRate = $this->calculateGrowthRate('contributions', '90_days', 'amount') / 100;
        $monthlyGrowthRate = $growthRate / 3; // Convert quarterly to monthly
        
        return round($currentRevenue * pow(1 + $monthlyGrowthRate, $months), 2);
    }
}
?>