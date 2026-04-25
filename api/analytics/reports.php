<?php
/**
 * Analytics Reports API
 * Requirements: 4.1, 4.2, 4.3
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../services/ReportGenerator.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

session_start();

$auth = new Auth();
$reportGen = new ReportGenerator();

if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'templates';

switch ($action) {
    case 'generate':
        $templateId = $_POST['template_id'] ?? 'executive_summary';
        $parameters = [
            'start_date' => $_POST['start_date'] ?? date('Y-m-01'),
            'end_date' => $_POST['end_date'] ?? date('Y-m-d'),
            'title' => $_POST['title'] ?? null
        ];
        $result = $reportGen->generateReport($templateId, $parameters);
        echo json_encode($result);
        break;
        
    case 'templates':
        $result = $reportGen->getTemplates();
        echo json_encode($result);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
