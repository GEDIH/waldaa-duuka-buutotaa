<?php
/**
 * Production Health Check Script
 * Monitors system health and returns status for monitoring tools
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Only allow access from localhost or monitoring IPs
$allowedIPs = ['127.0.0.1', '::1', 'YOUR_MONITORING_IP_HERE'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($clientIP, $allowedIPs)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

$healthStatus = [
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => 'healthy',
    'checks' => []
];

// Check 1: Database connectivity
try {
    $pdo = new PDO("mysql:host=localhost;dbname=wdb_membership_prod;charset=utf8mb4", 
                   $_ENV['DB_USER'] ?? 'wdb_prod_user', 
                   $_ENV['DB_PASS'] ?? '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT 1");
    $healthStatus['checks']['database'] = [
        'status' => 'healthy',
        'response_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
        'message' => 'Database connection successful'
    ];
} catch (Exception $e) {
    $healthStatus['status'] = 'unhealthy';
    $healthStatus['checks']['database'] = [
        'status' => 'unhealthy',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
}

// Check 2: Disk space
$diskFree = disk_free_space('/var/www/html/wdb');
$diskTotal = disk_total_space('/var/www/html/wdb');
$diskUsagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;

$healthStatus['checks']['disk_space'] = [
    'status' => $diskUsagePercent < 90 ? 'healthy' : 'warning',
    'usage_percent' => round($diskUsagePercent, 2),
    'free_space_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
    'message' => $diskUsagePercent < 90 ? 'Disk space OK' : 'Low disk space warning'
];

if ($diskUsagePercent >= 95) {
    $healthStatus['status'] = 'unhealthy';
    $healthStatus['checks']['disk_space']['status'] = 'unhealthy';
}

// Check 3: Upload directory writable
$uploadDir = '/var/www/html/wdb/uploads/photos/';
$isWritable = is_writable($uploadDir);

$healthStatus['checks']['upload_directory'] = [
    'status' => $isWritable ? 'healthy' : 'unhealthy',
    'path' => $uploadDir,
    'message' => $isWritable ? 'Upload directory writable' : 'Upload directory not writable'
];

if (!$isWritable) {
    $healthStatus['status'] = 'unhealthy';
}

// Check 4: SSL Certificate (if HTTPS)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $healthStatus['checks']['ssl'] = [
        'status' => 'healthy',
        'message' => 'HTTPS enabled'
    ];
} else {
    $healthStatus['checks']['ssl'] = [
        'status' => 'warning',
        'message' => 'HTTPS not detected'
    ];
}

// Check 5: PHP version and extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'gd', 'json', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

$healthStatus['checks']['php'] = [
    'status' => empty($missingExtensions) ? 'healthy' : 'unhealthy',
    'version' => PHP_VERSION,
    'missing_extensions' => $missingExtensions,
    'message' => empty($missingExtensions) ? 'PHP configuration OK' : 'Missing required extensions'
];

if (!empty($missingExtensions)) {
    $healthStatus['status'] = 'unhealthy';
}

// Check 6: Memory usage
$memoryUsage = memory_get_usage(true);
$memoryLimit = ini_get('memory_limit');
$memoryLimitBytes = return_bytes($memoryLimit);
$memoryUsagePercent = ($memoryUsage / $memoryLimitBytes) * 100;

$healthStatus['checks']['memory'] = [
    'status' => $memoryUsagePercent < 80 ? 'healthy' : 'warning',
    'usage_percent' => round($memoryUsagePercent, 2),
    'usage_mb' => round($memoryUsage / 1024 / 1024, 2),
    'limit' => $memoryLimit,
    'message' => $memoryUsagePercent < 80 ? 'Memory usage OK' : 'High memory usage'
];

// Check 7: Log file sizes
$logFiles = [
    '/var/log/apache2/error.log',
    '/var/log/php/error.log',
    '/var/www/html/wdb/logs/application.log'
];

$logStatus = 'healthy';
$logMessages = [];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        $size = filesize($logFile);
        $sizeMB = $size / 1024 / 1024;
        
        if ($sizeMB > 100) { // Log file larger than 100MB
            $logStatus = 'warning';
            $logMessages[] = basename($logFile) . ' is large (' . round($sizeMB, 2) . 'MB)';
        }
    }
}

$healthStatus['checks']['logs'] = [
    'status' => $logStatus,
    'message' => empty($logMessages) ? 'Log files OK' : implode(', ', $logMessages)
];

// Overall health determination
if ($healthStatus['status'] === 'healthy') {
    foreach ($healthStatus['checks'] as $check) {
        if ($check['status'] === 'unhealthy') {
            $healthStatus['status'] = 'unhealthy';
            break;
        } elseif ($check['status'] === 'warning' && $healthStatus['status'] === 'healthy') {
            $healthStatus['status'] = 'warning';
        }
    }
}

// Set appropriate HTTP status code
switch ($healthStatus['status']) {
    case 'healthy':
        http_response_code(200);
        break;
    case 'warning':
        http_response_code(200); // Still operational
        break;
    case 'unhealthy':
        http_response_code(503); // Service unavailable
        break;
}

echo json_encode($healthStatus, JSON_PRETTY_PRINT);

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
}
?>