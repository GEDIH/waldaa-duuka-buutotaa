<?php
/**
 * Scheduled Reports API
 * Manages scheduled report configurations
 * Requirements: 4.4
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../classes/ReportGenerator.php';

// Initialize report generator
$reportGenerator = new ReportGenerator();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($reportGenerator, $action);
            break;
            
        case 'POST':
            handlePost($reportGenerator, $action);
            break;
            
        case 'PUT':
            handlePut($reportGenerator, $action);
            break;
            
        case 'DELETE':
            handleDelete($reportGenerator, $action);
            break;
            
        default:
            throw new Exception("Method not allowed");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Handle GET requests
 */
function handleGet($reportGenerator, $action) {
    switch ($action) {
        case 'list':
            // Get all scheduled reports
            $filters = [];
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (isset($_GET['template'])) {
                $filters['template'] = $_GET['template'];
            }
            
            $schedules = $reportGenerator->getScheduledReports($filters);
            
            echo json_encode([
                'success' => true,
                'schedules' => $schedules,
                'count' => count($schedules)
            ]);
            break;
            
        case 'templates':
            // Get available templates
            $templates = $reportGenerator->getAvailableTemplates();
            
            echo json_encode([
                'success' => true,
                'templates' => $templates
            ]);
            break;
            
        case 'due':
            // Get due reports (admin only)
            $dueReports = $reportGenerator->getDueScheduledReports();
            
            echo json_encode([
                'success' => true,
                'due_reports' => $dueReports,
                'count' => count($dueReports)
            ]);
            break;
            
        default:
            throw new Exception("Invalid action");
    }
}

/**
 * Handle POST requests
 */
function handlePost($reportGenerator, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }
    
    switch ($action) {
        case 'create':
            // Create new scheduled report
            $config = [
                'template' => $input['template'] ?? null,
                'frequency' => $input['frequency'] ?? null,
                'recipients' => $input['recipients'] ?? [],
                'parameters' => $input['parameters'] ?? [],
                'time' => $input['time'] ?? '09:00'
            ];
            
            $result = $reportGenerator->scheduleReport($config);
            
            echo json_encode([
                'success' => true,
                'schedule' => $result,
                'message' => 'Scheduled report created successfully'
            ]);
            break;
            
        case 'test':
            // Test report generation (without scheduling)
            $scheduleId = $input['schedule_id'] ?? null;
            if (!$scheduleId) {
                throw new Exception("Schedule ID required");
            }
            
            $schedules = $reportGenerator->getScheduledReports(['status' => 'active']);
            $schedule = null;
            
            foreach ($schedules as $s) {
                if ($s['id'] == $scheduleId) {
                    $schedule = $s;
                    break;
                }
            }
            
            if (!$schedule) {
                throw new Exception("Schedule not found");
            }
            
            $result = $reportGenerator->processScheduledReport($schedule);
            
            echo json_encode([
                'success' => $result['success'],
                'result' => $result,
                'message' => $result['success'] ? 'Report generated and sent successfully' : 'Report generation failed'
            ]);
            break;
            
        default:
            throw new Exception("Invalid action");
    }
}

/**
 * Handle PUT requests
 */
function handlePut($reportGenerator, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }
    
    switch ($action) {
        case 'update':
            // Update scheduled report
            $scheduleId = $input['schedule_id'] ?? null;
            if (!$scheduleId) {
                throw new Exception("Schedule ID required");
            }
            
            $updates = [];
            if (isset($input['frequency'])) {
                $updates['frequency'] = $input['frequency'];
            }
            if (isset($input['recipients'])) {
                $updates['recipients'] = $input['recipients'];
            }
            if (isset($input['parameters'])) {
                $updates['parameters'] = $input['parameters'];
            }
            if (isset($input['status'])) {
                $updates['status'] = $input['status'];
            }
            
            $success = $reportGenerator->updateScheduledReport($scheduleId, $updates);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Scheduled report updated successfully' : 'Failed to update scheduled report'
            ]);
            break;
            
        default:
            throw new Exception("Invalid action");
    }
}

/**
 * Handle DELETE requests
 */
function handleDelete($reportGenerator, $action) {
    switch ($action) {
        case 'delete':
            // Delete scheduled report
            $scheduleId = $_GET['schedule_id'] ?? null;
            if (!$scheduleId) {
                throw new Exception("Schedule ID required");
            }
            
            $success = $reportGenerator->deleteScheduledReport($scheduleId);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Scheduled report deleted successfully' : 'Failed to delete scheduled report'
            ]);
            break;
            
        default:
            throw new Exception("Invalid action");
    }
}
