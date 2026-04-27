<?php
/**
 * Public Centers List API
 * Returns all active WDB centers — no authentication required.
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: public, max-age=300'); // cache 5 min

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean(); http_response_code(200); exit(0);
}

function sendJson(array $payload, int $code = 200): void {
    ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

try {
    require_once dirname(__DIR__) . '/config/database.php';
    $pdo = Database::getInstance()->getConnection();

    $stmt = $pdo->query("
        SELECT id, name, code, city, region, country, location, status
        FROM   centers
        WHERE  status = 'active'
        ORDER  BY name ASC
    ");
    $centers = $stmt->fetchAll();

    sendJson(['success' => true, 'data' => $centers]);

} catch (Throwable $e) {
    error_log('centers-list error: ' . $e->getMessage());
    sendJson(['success' => false, 'error' => $e->getMessage()], 500);
}
