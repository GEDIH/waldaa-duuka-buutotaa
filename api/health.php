<?php
/**
 * Health-check endpoint
 * GET /api/health.php  →  JSON with DB status, PHP version, etc.
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function sendHealth(array $payload, int $code = 200): void {
    ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit(0);
}

$result = [
    'status'      => 'ok',
    'timestamp'   => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'database'    => ['status' => 'unknown', 'host' => null, 'port' => null, 'name' => null],
    'checks'      => [],
];

// ── Database check ────────────────────────────────────────────────────────────
try {
    require_once __DIR__ . '/config/database.php';

    $pdo = Database::getInstance()->getConnection();

    // Basic query
    $row = $pdo->query("SELECT VERSION() AS ver, DATABASE() AS db")->fetch();

    $result['database'] = [
        'status'  => 'connected',
        'host'    => env('DB_HOST', 'localhost'),
        'port'    => (int) env('DB_PORT', 3306),
        'name'    => $row['db'],
        'version' => $row['ver'],
    ];
    $result['checks']['database'] = 'pass';

    // Check required tables exist
    $required = ['users','members','centers','audit_logs','registration_log'];
    $existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $missing  = array_diff($required, $existing);

    if (empty($missing)) {
        $result['checks']['tables'] = 'pass';
    } else {
        $result['checks']['tables']  = 'fail — missing: ' . implode(', ', $missing);
        $result['checks']['hint']    = 'Run: mysql -u root -p < sql/setup.sql';
        $result['status']            = 'degraded';
    }

} catch (Throwable $e) {
    $result['database']['status']  = 'error';
    $result['database']['message'] = $e->getMessage();
    $result['checks']['database']  = 'fail';
    $result['status']              = 'error';
    sendHealth($result, 503);
}

// ── Logs directory ────────────────────────────────────────────────────────────
$logsDir = dirname(__DIR__) . '/logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}
$result['checks']['logs_dir'] = is_writable($logsDir) ? 'pass' : 'not writable';

sendHealth($result, $result['status'] === 'ok' ? 200 : 503);
