<?php
/**
 * Admin Photo Deletion API
 * 
 * This endpoint allows administrators to delete photos for any member.
 * Handles both database cleanup and file system cleanup.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config/database.php';

/**
 * Security logging function
 */
function logSecurityEvent($type, $message, $memberId = null) {
    $logDir = __DIR__ . '/../logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $logEntry = sprintf(
        "[%s] %s | Member: %s | IP: %s | UA: %s | Message: %s\n",
        $timestamp,
        $type,
        $memberId ?? 'N/A',
        $ip,
        $userAgent,
        $message
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

try {
    // Get member_id from POST or JSON body
    $memberId = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $memberId = $_POST['member_id'] ?? null;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $memberId = $input['member_id'] ?? null;
    }
    
    if (!$memberId) {
        http_response_code(400);
        throw new Exception('Member ID is required');
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Get member record with photo information
    $stmt = $db->prepare("
        SELECT id, user_id, member_id, photo, photo_path 
        FROM members 
        WHERE member_id = ? OR id = ?
    ");
    $stmt->execute([$memberId, $memberId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        logSecurityEvent('INVALID_MEMBER', 'Photo deletion attempt with invalid member ID: ' . $memberId);
        http_response_code(404);
        throw new Exception('Member not found');
    }
    
    // Check if member has a photo
    if (empty($member['photo']) && empty($member['photo_path'])) {
        http_response_code(400);
        throw new Exception('Member does not have a photo to delete');
    }
    
    // File System Cleanup
    $filesDeleted = 0;
    $fileErrors = [];
    
    // Delete photo file from uploads/member-photos directory
    if (!empty($member['photo_path'])) {
        $photoPath = __DIR__ . '/../' . $member['photo_path'];
        
        if (file_exists($photoPath)) {
            if (unlink($photoPath)) {
                $filesDeleted++;
            } else {
                $fileErrors[] = 'Failed to delete main photo file';
            }
        }
        
        // Delete associated thumbnail files
        $pathInfo = pathinfo($photoPath);
        $thumbnailSizes = [32, 48, 150];
        
        foreach ($thumbnailSizes as $size) {
            $thumbPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb_' . $size . '.' . $pathInfo['extension'];
            if (file_exists($thumbPath)) {
                if (unlink($thumbPath)) {
                    $filesDeleted++;
                } else {
                    $fileErrors[] = "Failed to delete {$size}px thumbnail";
                }
            }
        }
    }
    
    // Database Cleanup
    // Check if photo_uploaded_at column exists
    $stmt = $db->query("SHOW COLUMNS FROM members LIKE 'photo_uploaded_at'");
    $hasUploadedAt = $stmt->fetch() !== false;
    
    if ($hasUploadedAt) {
        $stmt = $db->prepare("
            UPDATE members 
            SET photo = NULL, photo_path = NULL, photo_uploaded_at = NULL 
            WHERE id = ?
        ");
    } else {
        $stmt = $db->prepare("
            UPDATE members 
            SET photo = NULL, photo_path = NULL 
            WHERE id = ?
        ");
    }
    
    $stmt->execute([$member['id']]);
    
    // Verify database update succeeded
    if ($stmt->rowCount() === 0) {
        logSecurityEvent('DB_UPDATE_FAILED', 'Failed to clear photo data in database', $member['member_id']);
        throw new Exception('Failed to remove photo from database');
    }
    
    // Log successful deletion
    logSecurityEvent(
        'ADMIN_PHOTO_DELETE_SUCCESS', 
        "Photo deleted successfully by admin. Files deleted: {$filesDeleted}", 
        $member['member_id']
    );
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Photo deleted successfully',
        'data' => [
            'member_id' => $member['member_id'],
            'files_deleted' => $filesDeleted,
            'file_errors' => $fileErrors,
            'deleted_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    // Use appropriate HTTP status codes
    $statusCode = http_response_code();
    if ($statusCode === 200) {
        $statusCode = 400; // Default to 400 if not set
        http_response_code($statusCode);
    }
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
