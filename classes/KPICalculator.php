<?php
/**
 * KPI Calculator
 * Specialized class for calculating Key Performance Indicators with threshold-based alerting
 * Requirements: 1.1, 1.6, 1.7
 */

require_once __DIR__ . '/../api/config/database.php';

class KPICalculator
{
    private $db;
    private $conn;
    
    // Threshold configurations for alerts
    private $thresholds = [
        'payment_compliance_rate' => ['warning' => 70, 'critical' => 50],
        'member_growth_rate' => ['warning' => 2, 'critical' => 0],
        'collection_efficiency' => ['warning' => 60, 'critical' => 40],
        'engagement_rate' => ['warning' => 40, 'critical' => 20],
        'revenue_growth' => ['warning' => 0, 'critical' => -10]
    ];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * Calculate membership KPIs with threshold alerts
     */
    public function calculateMembershipKPIs($centerId = null, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $endDate ?? date('Y-m-d');
        
        $kpis = [
            'total_members' => $this->getTotalMembers($centerId),
            'active_members' => $this->getActiveMembers($centerId),
            'new_registrations_today' => $this->getNewRegistrations($centerId, date('Y-m-d'), date('Y-m-d')),
            'new_registrations_period' => $this->getNewRegistrations($centerId, $startDate, $endDate),
            'paid_members' => $this->getPaidMembers($centerId),
            'unpaid_members' => $this->getUnpaidMembers($centerId),
            'payment_compliance_rate' => 0,
            'gender_distribution' => $this->getGenderDistribution($centerId),
            'age_distribution' => $this->getAgeDistribution($centerId),
            'education_distribution' => $this->getEducationDistribution($centerId)
        ];
        
        // Calculate payment compliance rate
        if ($kpis['total_members'] > 0) {
            $kpis['payment_compliance_rate'] = round(
                ($kpis['paid_members'] / $kpis['total_members']) * 100, 
                2
            );
        }
        
        // Add threshold alerts
        $kpis['alerts'] = $this->checkThresholds('membership', $kpis);
        
        // Add trend indicators
        $kpis['trends'] = $this->calculateMembershipTrends($centerId);
        
        return $kpis;
    }
    
    /**
     * Calculate financial KPIs with threshold alerts
     */
    public function calculateFinancialKPIs($centerId = null, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $endDate ?? date('Y-m-d');
        
        $kpis = [
            'total_revenue' => $this->getTotalRevenue($centerId),
            'revenue_today' => $this->getRevenueByPeriod($centerId, date('Y-m-d'), date('Y-m-d')),
            'revenue_30d' => $this->getRevenueByPeriod($centerId, date('Y-m-d', strtotime('-30 days')), date('Y-m-d')),
            'revenue_period' => $this->getRevenueByPeriod($centerId, $startDate, $endDate),
            'pending_revenue' => $this->getPendingRevenue($centerId),
            'avg_contribution' => $this->getAverageContribution($centerId),
            'total_contributions' => $this->getTotalContributions($centerId),
            'paid_contributions' => $this->getPaidContributions($centerId),
            'collection_efficiency' => 0,
            'payment_method_distribution' => $this->getPaymentMethodDistribution($centerId)
        ];
        
        // Calculate collection efficiency
        if ($kpis['total_contributions'] > 0) {
            $kpis['collection_efficiency'] = round(
                ($kpis['paid_contributions'] / $kpis['total_contributions']) * 100,
                2
            );
        }
        
        // Add threshold alerts
        $kpis['alerts'] = $this->checkThresholds('financial', $kpis);
        
        // Add trend indicators
        $kpis['trends'] = $this->calculateFinancialTrends($centerId);
        
        return $kpis;
    }
    
    /**
     * Calculate growth KPIs with threshold alerts
     */
    public function calculateGrowthKPIs($centerId = null)
    {
        $currentMonth = date('Y-m');
        $previousMonth = date('Y-m', strtotime('-1 month'));
        
        $currentMembers = $this->getMembersByMonth($centerId, $currentMonth);
        $previousMembers = $this->getMembersByMonth($centerId, $previousMonth);
        
        $currentRevenue = $this->getRevenueByMonth($centerId, $currentMonth);
        $previousRevenue = $this->getRevenueByMonth($centerId, $previousMonth);
        
        $kpis = [
            'current_month_members' => $currentMembers,
            'previous_month_members' => $previousMembers,
            'member_growth' => $currentMembers - $previousMembers,
            'member_growth_percentage' => 0,
            'current_month_revenue' => $currentRevenue,
            'previous_month_revenue' => $previousRevenue,
            'revenue_growth' => $currentRevenue - $previousRevenue,
            'revenue_growth_percentage' => 0
        ];
        
        // Calculate growth percentages
        if ($previousMembers > 0) {
            $kpis['member_growth_percentage'] = round(
                (($currentMembers - $previousMembers) / $previousMembers) * 100,
                2
            );
        }
        
        if ($previousRevenue > 0) {
            $kpis['revenue_growth_percentage'] = round(
                (($currentRevenue - $previousRevenue) / $previousRevenue) * 100,
                2
            );
        }
        
        // Add threshold alerts
        $kpis['alerts'] = $this->checkThresholds('growth', $kpis);
        
        return $kpis;
    }
    
    /**
     * Calculate engagement KPIs with threshold alerts
     */
    public function calculateEngagementKPIs($centerId = null)
    {
        $kpis = [
            'active_7d' => $this->getActiveUsersByDays($centerId, 7),
            'active_30d' => $this->getActiveUsersByDays($centerId, 30),
            'active_90d' => $this->getActiveUsersByDays($centerId, 90),
            'inactive_members' => $this->getInactiveMembers($centerId),
            'engagement_rate_30d' => 0,
            'members_with_service_areas' => $this->getMembersWithServiceAreas($centerId),
            'service_area_participation_rate' => 0
        ];
        
        $totalMembers = $this->getTotalMembers($centerId);
        
        // Calculate engagement rate
        if ($totalMembers > 0) {
            $kpis['engagement_rate_30d'] = round(
                ($kpis['active_30d'] / $totalMembers) * 100,
                2
            );
            
            $kpis['service_area_participation_rate'] = round(
                ($kpis['members_with_service_areas'] / $totalMembers) * 100,
                2
            );
        }
        
        // Add threshold alerts
        $kpis['alerts'] = $this->checkThresholds('engagement', $kpis);
        
        return $kpis;
    }
    
    /**
     * Calculate center-specific KPIs
     */
    public function calculateCenterKPIs($centerId = null)
    {
        try {
            $query = "SELECT * FROM center_performance_view";
            
            if ($centerId) {
                $query .= " WHERE id = :center_id";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([':center_id' => $centerId]);
                $centers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $this->conn->query($query);
                $centers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Add alerts for each center
            foreach ($centers as &$center) {
                $center['alerts'] = $this->checkThresholds('center', $center);
            }
            
            return $centers;
            
        } catch (Exception $e) {
            error_log("Center KPIs calculation error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check thresholds and generate alerts
     */
    private function checkThresholds($kpiType, $kpis)
    {
        $alerts = [];
        
        switch ($kpiType) {
            case 'membership':
                if (isset($kpis['payment_compliance_rate'])) {
                    $rate = $kpis['payment_compliance_rate'];
                    if ($rate < $this->thresholds['payment_compliance_rate']['critical']) {
                        $alerts[] = [
                            'type' => 'critical',
                            'metric' => 'payment_compliance_rate',
                            'message' => "Payment compliance rate is critically low: {$rate}%",
                            'threshold' => $this->thresholds['payment_compliance_rate']['critical'],
                            'current_value' => $rate
                        ];
                    } elseif ($rate < $this->thresholds['payment_compliance_rate']['warning']) {
                        $alerts[] = [
                            'type' => 'warning',
                            'metric' => 'payment_compliance_rate',
                            'message' => "Payment compliance rate is below target: {$rate}%",
                            'threshold' => $this->thresholds['payment_compliance_rate']['warning'],
                            'current_value' => $rate
                        ];
                    }
                }
                break;
                
            case 'financial':
                if (isset($kpis['collection_efficiency'])) {
                    $rate = $kpis['collection_efficiency'];
                    if ($rate < $this->thresholds['collection_efficiency']['critical']) {
                        $alerts[] = [
                            'type' => 'critical',
                            'metric' => 'collection_efficiency',
                            'message' => "Collection efficiency is critically low: {$rate}%",
                            'threshold' => $this->thresholds['collection_efficiency']['critical'],
                            'current_value' => $rate
                        ];
                    } elseif ($rate < $this->thresholds['collection_efficiency']['warning']) {
                        $alerts[] = [
                            'type' => 'warning',
                            'metric' => 'collection_efficiency',
                            'message' => "Collection efficiency is below target: {$rate}%",
                            'threshold' => $this->thresholds['collection_efficiency']['warning'],
                            'current_value' => $rate
                        ];
                    }
                }
                break;
                
            case 'growth':
                if (isset($kpis['member_growth_percentage'])) {
                    $rate = $kpis['member_growth_percentage'];
                    if ($rate < $this->thresholds['member_growth_rate']['critical']) {
                        $alerts[] = [
                            'type' => 'critical',
                            'metric' => 'member_growth_rate',
                            'message' => "Member growth is negative: {$rate}%",
                            'threshold' => $this->thresholds['member_growth_rate']['critical'],
                            'current_value' => $rate
                        ];
                    } elseif ($rate < $this->thresholds['member_growth_rate']['warning']) {
                        $alerts[] = [
                            'type' => 'warning',
                            'metric' => 'member_growth_rate',
                            'message' => "Member growth is below target: {$rate}%",
                            'threshold' => $this->thresholds['member_growth_rate']['warning'],
                            'current_value' => $rate
                        ];
                    }
                }
                break;
                
            case 'engagement':
                if (isset($kpis['engagement_rate_30d'])) {
                    $rate = $kpis['engagement_rate_30d'];
                    if ($rate < $this->thresholds['engagement_rate']['critical']) {
                        $alerts[] = [
                            'type' => 'critical',
                            'metric' => 'engagement_rate',
                            'message' => "Member engagement is critically low: {$rate}%",
                            'threshold' => $this->thresholds['engagement_rate']['critical'],
                            'current_value' => $rate
                        ];
                    } elseif ($rate < $this->thresholds['engagement_rate']['warning']) {
                        $alerts[] = [
                            'type' => 'warning',
                            'metric' => 'engagement_rate',
                            'message' => "Member engagement is below target: {$rate}%",
                            'threshold' => $this->thresholds['engagement_rate']['warning'],
                            'current_value' => $rate
                        ];
                    }
                }
                break;
                
            case 'center':
                if (isset($kpis['payment_compliance_percentage'])) {
                    $rate = $kpis['payment_compliance_percentage'];
                    if ($rate < $this->thresholds['payment_compliance_rate']['warning']) {
                        $alerts[] = [
                            'type' => 'warning',
                            'metric' => 'payment_compliance',
                            'message' => "Center payment compliance is low: {$rate}%",
                            'current_value' => $rate
                        ];
                    }
                }
                break;
        }
        
        return $alerts;
    }
    
    // Helper methods for data retrieval
    
    private function getTotalMembers($centerId = null)
    {
        $query = "SELECT COUNT(*) as count FROM members WHERE status != 'deleted'";
        if ($centerId) {
            $query .= " AND center_id = :center_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getActiveMembers($centerId = null)
    {
        $query = "SELECT COUNT(*) as count FROM members WHERE status = 'active'";
        if ($centerId) {
            $query .= " AND center_id = :center_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getNewRegistrations($centerId, $startDate, $endDate)
    {
        $query = "SELECT COUNT(*) as count FROM members 
                  WHERE membership_date BETWEEN :start_date AND :end_date 
                  AND status != 'deleted'";
        
        $params = [':start_date' => $startDate, ':end_date' => $endDate];
        
        if ($centerId) {
            $query .= " AND center_id = :center_id";
            $params[':center_id'] = $centerId;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getPaidMembers($centerId = null)
    {
        $query = "SELECT COUNT(*) as count FROM members WHERE payment_status = 'paid' AND status != 'deleted'";
        if ($centerId) {
            $query .= " AND center_id = :center_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getUnpaidMembers($centerId = null)
    {
        $query = "SELECT COUNT(*) as count FROM members WHERE payment_status = 'unpaid' AND status != 'deleted'";
        if ($centerId) {
            $query .= " AND center_id = :center_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getTotalRevenue($centerId = null)
    {
        $query = "SELECT COALESCE(SUM(amount), 0) as total FROM contributions 
                  WHERE payment_status = 'paid' AND status = 'active'";
        if ($centerId) {
            $query .= " AND center_id = :center_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        return (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    private function getRevenueByPeriod($centerId, $startDate, $endDate)
    {
        $query = "SELECT COALESCE(SUM(amount), 0) as total FROM contributions 
                  WHERE payment_status = 'paid' AND status = 'active'
                  AND contribution_date BETWEEN :start_date AND :end_date";
        
        $params = [':start_date' => $startDate, ':end_date' => $endDate];
        
        if ($centerId) {
            $query .= " AND center_id = :center_id";
            $params[':center_id'] = $centerId;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    private function getPendingRevenue($centerId = null)
    {
        $query = "SELECT COALESCE(SUM(amount), 0) as total FROM contributions 
                  WHERE payment_status IN ('pending', 'partial') AND status = 'active'";
        if ($centerId) {
            $query .= " AND center_id = :center_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        return (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    private function getAverageContribution($centerId = null)
    {
        $query = "SELECT COALESCE(AVG(amount), 0) as avg FROM contributions 
                  WHERE payment_status = 'paid' AND status = 'active'";
        if ($centerId) {
            $query .= " AND center_id = :center_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        return (float)$stmt->fetch(PDO::FETCH_ASSOC)['avg'];
    }
    
    private function getTotalContributions($centerId = null)
    {
        $query = "SELECT COUNT(*) as count FROM contributions WHERE status = 'active'";
        if ($centerId) {
            $query .= " AND center_id = :center_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getPaidContributions($centerId = null)
    {
        $query = "SELECT COUNT(*) as count FROM contributions WHERE payment_status = 'paid' AND status = 'active'";
        if ($centerId) {
            $query .= " AND center_id = :center_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getMembersByMonth($centerId, $month)
    {
        $query = "SELECT COUNT(*) as count FROM members 
                  WHERE DATE_FORMAT(membership_date, '%Y-%m') = :month 
                  AND status != 'deleted'";
        
        $params = [':month' => $month];
        
        if ($centerId) {
            $query .= " AND center_id = :center_id";
            $params[':center_id'] = $centerId;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getRevenueByMonth($centerId, $month)
    {
        $query = "SELECT COALESCE(SUM(amount), 0) as total FROM contributions 
                  WHERE DATE_FORMAT(contribution_date, '%Y-%m') = :month 
                  AND payment_status = 'paid' AND status = 'active'";
        
        $params = [':month' => $month];
        
        if ($centerId) {
            $query .= " AND center_id = :center_id";
            $params[':center_id'] = $centerId;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    private function getActiveUsersByDays($centerId, $days)
    {
        $query = "SELECT COUNT(DISTINCT m.id) as count FROM members m
                  LEFT JOIN users u ON m.user_id = u.id
                  WHERE u.last_login >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                  AND m.status != 'deleted'";
        
        $params = [':days' => $days];
        
        if ($centerId) {
            $query .= " AND m.center_id = :center_id";
            $params[':center_id'] = $centerId;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getInactiveMembers($centerId = null)
    {
        $query = "SELECT COUNT(DISTINCT m.id) as count FROM members m
                  LEFT JOIN users u ON m.user_id = u.id
                  WHERE (u.last_login < DATE_SUB(CURDATE(), INTERVAL 90 DAY) OR u.last_login IS NULL)
                  AND m.status != 'deleted'";
        
        if ($centerId) {
            $query .= " AND m.center_id = :center_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getMembersWithServiceAreas($centerId = null)
    {
        $query = "SELECT COUNT(DISTINCT msp.member_id) as count FROM member_service_preferences msp
                  JOIN members m ON msp.member_id = m.id
                  WHERE m.status != 'deleted'";
        
        if ($centerId) {
            $query .= " AND m.center_id = :center_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getGenderDistribution($centerId = null)
    {
        $query = "SELECT gender, COUNT(*) as count FROM members WHERE status != 'deleted'";
        if ($centerId) {
            $query .= " AND center_id = :center_id";
        }
        $query .= " GROUP BY gender";
        
        if ($centerId) {
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getAgeDistribution($centerId = null)
    {
        $query = "SELECT 
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'under_18'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 25 THEN '18_25'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 26 AND 35 THEN '26_35'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 36 AND 45 THEN '36_45'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 46 AND 55 THEN '46_55'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 56 AND 65 THEN '56_65'
                        ELSE 'over_65'
                    END as age_group,
                    COUNT(*) as count
                  FROM members 
                  WHERE status != 'deleted' AND date_of_birth IS NOT NULL";
        
        if ($centerId) {
            $query .= " AND center_id = :center_id";
        }
        $query .= " GROUP BY age_group";
        
        if ($centerId) {
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getEducationDistribution($centerId = null)
    {
        $query = "SELECT education_level, COUNT(*) as count FROM members WHERE status != 'deleted'";
        if ($centerId) {
            $query .= " AND center_id = :center_id";
        }
        $query .= " GROUP BY education_level";
        
        if ($centerId) {
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getPaymentMethodDistribution($centerId = null)
    {
        $query = "SELECT payment_method, COUNT(*) as count FROM contributions 
                  WHERE payment_status = 'paid' AND status = 'active'";
        if ($centerId) {
            $query .= " AND center_id = :center_id";
        }
        $query .= " GROUP BY payment_method";
        
        if ($centerId) {
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':center_id' => $centerId]);
        } else {
            $stmt = $this->conn->query($query);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function calculateMembershipTrends($centerId)
    {
        // Get last 3 months data for trend calculation
        $trends = [];
        for ($i = 0; $i < 3; $i++) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $trends[$month] = $this->getMembersByMonth($centerId, $month);
        }
        return $trends;
    }
    
    private function calculateFinancialTrends($centerId)
    {
        // Get last 3 months revenue for trend calculation
        $trends = [];
        for ($i = 0; $i < 3; $i++) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $trends[$month] = $this->getRevenueByMonth($centerId, $month);
        }
        return $trends;
    }
}
