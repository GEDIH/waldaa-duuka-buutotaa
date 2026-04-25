<?php
/**
 * Dashboard Analytics Data API
 * Provides real-time data for dashboard charts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    require_once __DIR__ . '/../config/database.php';
    
    $db = Database::getInstance()->getConnection();
    
    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => []
    ];
    
    // Get member growth data (last 6 months)
    $memberGrowthQuery = "
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM members 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ";
    
    $stmt = $db->prepare($memberGrowthQuery);
    $stmt->execute();
    $memberGrowthData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format for Chart.js
    $memberGrowthLabels = [];
    $memberGrowthValues = [];
    
    foreach ($memberGrowthData as $row) {
        $memberGrowthLabels[] = date('M Y', strtotime($row['month'] . '-01'));
        $memberGrowthValues[] = (int)$row['count'];
    }
    
    $response['data']['memberGrowth'] = [
        'labels' => $memberGrowthLabels,
        'datasets' => [[
            'label' => 'New Members',
            'data' => $memberGrowthValues,
            'borderColor' => '#667eea',
            'backgroundColor' => '#667eea20',
            'tension' => 0.4,
            'fill' => true
        ]]
    ];
    
    // Get contribution trends (last 4 weeks)
    $contributionTrendsQuery = "
        SELECT 
            WEEK(created_at) as week,
            SUM(amount) as total
        FROM contributions 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 4 WEEK)
        AND status = 'confirmed'
        GROUP BY WEEK(created_at)
        ORDER BY week ASC
    ";
    
    $stmt = $db->prepare($contributionTrendsQuery);
    $stmt->execute();
    $contributionTrendsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $contributionLabels = [];
    $contributionValues = [];
    
    foreach ($contributionTrendsData as $row) {
        $contributionLabels[] = 'Week ' . $row['week'];
        $contributionValues[] = (float)$row['total'];
    }
    
    $response['data']['contributionTrends'] = [
        'labels' => $contributionLabels,
        'datasets' => [[
            'label' => 'Contributions (ETB)',
            'data' => $contributionValues,
            'backgroundColor' => [
                '#10b981',
                '#3b82f6',
                '#f59e0b',
                '#764ba2'
            ]
        ]]
    ];
    
    // Get center performance data
    $centerPerformanceQuery = "
        SELECT 
            c.name as center_name,
            COUNT(m.id) as member_count
        FROM centers c
        LEFT JOIN members m ON c.id = m.center_id
        WHERE m.status = 'active'
        GROUP BY c.id, c.name
        ORDER BY member_count DESC
        LIMIT 10
    ";
    
    $stmt = $db->prepare($centerPerformanceQuery);
    $stmt->execute();
    $centerPerformanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $centerLabels = [];
    $centerValues = [];
    
    foreach ($centerPerformanceData as $row) {
        $centerLabels[] = $row['center_name'];
        $centerValues[] = (int)$row['member_count'];
    }
    
    $response['data']['centerPerformance'] = [
        'labels' => $centerLabels,
        'datasets' => [[
            'label' => 'Active Members',
            'data' => $centerValues,
            'backgroundColor' => '#667eea80',
            'borderColor' => '#667eea',
            'borderWidth' => 2
        ]]
    ];
    
    // Get daily activity data (last 7 days)
    $activityQuery = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as activity_count
        FROM (
            SELECT created_at FROM members WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            UNION ALL
            SELECT created_at FROM contributions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ) as combined_activity
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ";
    
    $stmt = $db->prepare($activityQuery);
    $stmt->execute();
    $activityData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $activityLabels = [];
    $activityValues = [];
    
    foreach ($activityData as $row) {
        $activityLabels[] = date('D', strtotime($row['date']));
        $activityValues[] = (int)$row['activity_count'];
    }
    
    $response['data']['activity'] = [
        'labels' => $activityLabels,
        'datasets' => [[
            'label' => 'Daily Activity',
            'data' => $activityValues,
            'borderColor' => '#10b981',
            'backgroundColor' => '#10b98120',
            'tension' => 0.4,
            'fill' => true
        ]]
    ];
    
    // Get summary statistics
    $summaryQueries = [
        'total_members' => "SELECT COUNT(*) as count FROM members WHERE status = 'active'",
        'total_contributions' => "SELECT SUM(amount) as total FROM contributions WHERE status = 'confirmed'",
        'new_members_today' => "SELECT COUNT(*) as count FROM members WHERE DATE(created_at) = CURDATE()",
        'active_centers' => "SELECT COUNT(DISTINCT center_id) as count FROM members WHERE status = 'active'"
    ];
    
    $response['data']['summary'] = [];
    
    foreach ($summaryQueries as $key => $query) {
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['data']['summary'][$key] = $result['count'] ?? $result['total'] ?? 0;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch analytics data',
        'message' => $e->getMessage()
    ]);
}
?>