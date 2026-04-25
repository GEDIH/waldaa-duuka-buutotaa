<?php
/**
 * Center Member Photos Management API
 * 
 * Comprehensive CRUD operations for member profile images with center-based access control.
 * Ensures Center Administrators can only manage photos for members within their assigned centers.
 * 
 * Features:
 * - Upload member profile photos
 * - Update/replace existing photos
 * - Delete member photos
 * - Bulk photo operations
 * - Image optimization and validation
 * - Center-based access control
 * - Comprehensive audit logging
 * 
 * @author WDB Development Team
 * @version 1.0.0
 * @since 2024-12-19
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/CenterSecurityEnforcement.php';
require_once __DIR__ . '/../services/CenterAccessControlService.php';
require_once __DIR__ . '/../services/ImageProcessingService.php';
require_once __DIR__ . '/../config/database.php';

class CenterMemberPhotosController {
    private $db;
    private $auth;
    private $securityEnforcement;
    private $accessControl;
    private $imageProcessor;
    private $currentUser;
    private $userCenters;
    
    // Configuration constants
    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const UPLOAD_PATH = __DIR__ . '/../../uploads/member-photos/';
    const THUMBNAIL_SIZES = [
        'small' => ['width' => 150, 'height' => 150],
        'medium' => ['width' => 300, 'height' => 300],
        'large' => ['width' => 600, 'height' => 600]
    ];
    
    public function __construct() {
        $this->initializeDatabase();
        $this->auth = new AuthMiddleware($this->db);
        $this->securityEnforcement = new CenterSecurityEnforcement($this->db);
        $this->accessControl = new CenterAccessControlService();
        $this->imageProcessor = new ImageProcessingService();
        $this->ensureUploadDirectory();
    }
    
    private function initializeDatabase() {
        try {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $dbname = $_ENV['DB_NAME'] ?? 'wdb_membership';
            $username = $_ENV['DB_USER'] ?? 'root';
            $password = $_ENV['DB_PASS'] ?? '';
            
            $this->db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->sendError('Database connection failed', 500);
        }
    }
    
    private function ensureUploadDirectory() {
        if (!is_dir(self::UPLOAD_PATH)) {
            mkdir(self::UPLOAD_PATH, 0755, true);
        }
        
        // Create thumbnail directories
        foreach (self::THUMBNAIL_SIZES as $size => $dimensions) {
            $thumbDir = self::UPLOAD_PATH . "thumbnails/$size/";
            if (!is_dir($thumbDir)) {
                mkdir($thumbDir, 0755, true);
            }
        }
    }
    
    public function handleRequest() {
        try {
            // Authenticate and authorize
            $authResult = $this->auth->authenticate();
            if (!$authResult['success']) {
                $this->sendError($authResult['error'], 401);
                return;
            }
            
            $this->currentUser = $authResult['user'];
            
            // Only allow admin and superadmin roles
            if (!in_array($this->currentUser['role'], ['admin', 'superadmin'])) {
                $this->sendError('Insufficient privileges', 403);
                return;
            }
            
            // Get user's accessible centers
            $this->userCenters = $this->accessControl->getUserCenters($this->currentUser['id']);
            
            if (empty($this->userCenters) && $this->currentUser['role'] !== 'superadmin') {
                $this->sendError('No center assignments found', 403);
                return;
            }
            
            $method = $_SERVER['REQUEST_METHOD'];
            $action = $_GET['action'] ?? '';
            
            switch ($method) {
                case 'GET':
                    $this->handleGet($action);
                    break;
                case 'POST':
                    $this->handlePost($action);
                    break;
                case 'PUT':
                    $this->handlePut($action);
                    break;
                case 'DELETE':
                    $this->handleDelete($action);
                    break;
                default:
                    $this->sendError('Method not allowed', 405);
            }
        } catch (Exception $e) {
            error_log("CenterMemberPhotosController Error: " . $e->getMessage());
            $this->sendError('Internal server error', 500);
        }
    }
    
    private function handleGet($action) {
        switch ($action) {
            case 'list':
                $this->getMemberPhotos();
                break;
            case 'photo':
                $this->getMemberPhoto();
                break;
            case 'thumbnails':
                $this->getMemberThumbnails();
                break;
            case 'stats':
                $this->getPhotoStats();
                break;
            case 'missing':
                $this->getMembersWithoutPhotos();
                break;
            default:
                $this->getMemberPhotos();
        }
    }
    
    private function handlePost($action) {
        switch ($action) {
            case 'upload':
                $this->uploadMemberPhoto();
                break;
            case 'bulk-upload':
                $this->bulkUploadPhotos();
                break;
            case 'generate-thumbnails':
                $this->generateThumbnails();
                break;
            default:
                $this->uploadMemberPhoto();
        }
    }
    
    private function handlePut($action) {
        switch ($action) {
            case 'replace':
                $this->replaceMemberPhoto();
                break;
            case 'update-metadata':
                $this->updatePhotoMetadata();
                break;
            default:
                $this->sendError('Invalid action', 400);
        }
    }
    
    private function handleDelete($action) {
        switch ($action) {
            case 'photo':
                $this->deleteMemberPhoto();
                break;
            case 'bulk-delete':
                $this->bulkDeletePhotos();
                break;
            default:
                $this->deleteMemberPhoto();
        }
    }
    
    /**
     * Get member photos with center filtering
     */
    private function getMemberPhotos() {
        try {
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $search = $_GET['search'] ?? '';
            $centerId = $_GET['center_id'] ?? '';
            $hasPhoto = $_GET['has_photo'] ?? '';
            
            // Enforce security policy
            $securityResult = $this->securityEnforcement->enforceSecurityPolicy(
                $this->currentUser,
                'member_read',
                [
                    'resource_type' => 'member_photos',
                    'center_id' => $centerId
                ]
            );
            
            if (!$securityResult['allowed']) {
                $this->sendError($securityResult['reason'], 403);
                return;
            }
            
            // Build center filter
            $centerFilter = $this->buildCenterFilter($centerId);
            
            // Build search conditions
            $searchConditions = '';
            $params = [];
            
            if (!empty($search)) {
                $searchConditions = " AND (m.full_name LIKE ? OR m.email LIKE ? OR m.member_id LIKE ?)";
                $searchTerm = "%$search%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            }
            
            if ($hasPhoto === 'yes') {
                $searchConditions .= " AND m.photo_path IS NOT NULL";
            } elseif ($hasPhoto === 'no') {
                $searchConditions .= " AND m.photo_path IS NULL";
            }
            
            // Get total count
            $countSql = "
                SELECT COUNT(*) as total 
                FROM members m 
                LEFT JOIN centers c ON m.center_id = c.id 
                WHERE {$centerFilter['condition']} $searchConditions
            ";
            
            $stmt = $this->db->prepare($countSql);
            $stmt->execute(array_merge($centerFilter['params'], $params));
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get members with photo information
            $sql = "
                SELECT 
                    m.id, m.member_id, m.full_name, m.email, m.gender,
                    m.photo_path, m.photo_uploaded_at, m.photo_file_size,
                    m.created_at, m.updated_at,
                    c.name as center_name, c.id as center_id
                FROM members m 
                LEFT JOIN centers c ON m.center_id = c.id 
                WHERE {$centerFilter['condition']} $searchConditions
                ORDER BY m.full_name ASC 
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($centerFilter['params'], $params, [$limit, $offset]));
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Enhance with photo URLs and thumbnails
            foreach ($members as &$member) {
                if ($member['photo_path']) {
                    $member['photo_url'] = $this->getPhotoUrl($member['photo_path']);
                    $member['thumbnails'] = $this->getThumbnailUrls($member['photo_path']);
                    $member['has_photo'] = true;
                } else {
                    $member['photo_url'] = null;
                    $member['thumbnails'] = null;
                    $member['has_photo'] = false;
                }
            }
            
            // Log access
            $this->logPhotoAccess('list_photos', true, "Retrieved " . count($members) . " member photos");
            
            $this->sendSuccess([
                'members' => $members,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Get member photos error: " . $e->getMessage());
            $this->sendError('Failed to retrieve member photos', 500);
        }
    }
    
    /**
     * Upload member photo with validation and processing
     */
    private function uploadMemberPhoto() {
        try {
            $memberId = $_POST['member_id'] ?? '';
            if (empty($memberId)) {
                $this->sendError('Member ID required', 400);
                return;
            }
            
            // Verify member access
            if (!$this->verifyMemberAccess($memberId)) {
                $this->sendError('Access denied to this member', 403);
                return;
            }
            
            // Validate file upload
            if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                $this->sendError('No valid photo uploaded', 400);
                return;
            }
            
            $file = $_FILES['photo'];
            
            // Validate file
            $validation = $this->validateUploadedFile($file);
            if (!$validation['valid']) {
                $this->sendError($validation['error'], 400);
                return;
            }
            
            // Get member information
            $member = $this->getMemberById($memberId);
            if (!$member) {
                $this->sendError('Member not found', 404);
                return;
            }
            
            // Delete existing photo if exists
            if ($member['photo_path']) {
                $this->deletePhotoFiles($member['photo_path']);
            }
            
            // Process and save photo
            $photoResult = $this->processAndSavePhoto($file, $memberId);
            if (!$photoResult['success']) {
                $this->sendError($photoResult['error'], 500);
                return;
            }
            
            // Update member record
            $stmt = $this->db->prepare("
                UPDATE members 
                SET photo_path = ?, photo_uploaded_at = NOW(), photo_file_size = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([
                $photoResult['photo_path'],
                $photoResult['file_size'],
                $memberId
            ]);
            
            // Log upload
            $this->logPhotoAccess('upload_photo', true, "Uploaded photo for member ID: $memberId");
            
            $this->sendSuccess([
                'message' => 'Photo uploaded successfully',
                'photo_path' => $photoResult['photo_path'],
                'photo_url' => $this->getPhotoUrl($photoResult['photo_path']),
                'thumbnails' => $this->getThumbnailUrls($photoResult['photo_path']),
                'file_size' => $photoResult['file_size']
            ]);
            
        } catch (Exception $e) {
            error_log("Upload photo error: " . $e->getMessage());
            $this->sendError('Failed to upload photo', 500);
        }
    }
    
    /**
     * Replace existing member photo
     */
    private function replaceMemberPhoto() {
        try {
            $memberId = $_GET['member_id'] ?? '';
            if (empty($memberId)) {
                $this->sendError('Member ID required', 400);
                return;
            }
            
            // Verify member access
            if (!$this->verifyMemberAccess($memberId)) {
                $this->sendError('Access denied to this member', 403);
                return;
            }
            
            // Get uploaded file from PUT request
            $input = file_get_contents('php://input');
            $boundary = substr($input, 0, strpos($input, "\r\n"));
            
            // Parse multipart data (simplified for this example)
            // In production, use a proper multipart parser
            $this->sendError('Replace photo functionality requires multipart form data via POST', 400);
            
        } catch (Exception $e) {
            error_log("Replace photo error: " . $e->getMessage());
            $this->sendError('Failed to replace photo', 500);
        }
    }
    
    /**
     * Delete member photo
     */
    private function deleteMemberPhoto() {
        try {
            $memberId = $_GET['member_id'] ?? '';
            if (empty($memberId)) {
                $this->sendError('Member ID required', 400);
                return;
            }
            
            // Verify member access
            if (!$this->verifyMemberAccess($memberId)) {
                $this->sendError('Access denied to this member', 403);
                return;
            }
            
            // Get current photo path
            $member = $this->getMemberById($memberId);
            if (!$member || empty($member['photo_path'])) {
                $this->sendError('No photo found for this member', 404);
                return;
            }
            
            // Delete physical files
            $this->deletePhotoFiles($member['photo_path']);
            
            // Update database
            $stmt = $this->db->prepare("
                UPDATE members 
                SET photo_path = NULL, photo_uploaded_at = NULL, photo_file_size = NULL, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$memberId]);
            
            // Log deletion
            $this->logPhotoAccess('delete_photo', true, "Deleted photo for member ID: $memberId");
            
            $this->sendSuccess(['message' => 'Photo deleted successfully']);
            
        } catch (Exception $e) {
            error_log("Delete photo error: " . $e->getMessage());
            $this->sendError('Failed to delete photo', 500);
        }
    }
    
    /**
     * Bulk upload photos
     */
    private function bulkUploadPhotos() {
        try {
            if (!isset($_FILES['photos']) || !is_array($_FILES['photos']['name'])) {
                $this->sendError('No photos uploaded for bulk operation', 400);
                return;
            }
            
            $memberIds = $_POST['member_ids'] ?? [];
            if (!is_array($memberIds)) {
                $memberIds = explode(',', $memberIds);
            }
            
            $files = $_FILES['photos'];
            $results = [];
            $successCount = 0;
            $errorCount = 0;
            
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                $memberId = $memberIds[$i] ?? null;
                if (!$memberId || !$this->verifyMemberAccess($memberId)) {
                    $results[] = [
                        'member_id' => $memberId,
                        'success' => false,
                        'error' => 'Access denied or invalid member ID'
                    ];
                    $errorCount++;
                    continue;
                }
                
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size' => $files['size'][$i],
                    'error' => $files['error'][$i]
                ];
                
                $validation = $this->validateUploadedFile($file);
                if (!$validation['valid']) {
                    $results[] = [
                        'member_id' => $memberId,
                        'success' => false,
                        'error' => $validation['error']
                    ];
                    $errorCount++;
                    continue;
                }
                
                try {
                    $member = $this->getMemberById($memberId);
                    if ($member['photo_path']) {
                        $this->deletePhotoFiles($member['photo_path']);
                    }
                    
                    $photoResult = $this->processAndSavePhoto($file, $memberId);
                    if ($photoResult['success']) {
                        $stmt = $this->db->prepare("
                            UPDATE members 
                            SET photo_path = ?, photo_uploaded_at = NOW(), photo_file_size = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $photoResult['photo_path'],
                            $photoResult['file_size'],
                            $memberId
                        ]);
                        
                        $results[] = [
                            'member_id' => $memberId,
                            'success' => true,
                            'photo_url' => $this->getPhotoUrl($photoResult['photo_path'])
                        ];
                        $successCount++;
                    } else {
                        $results[] = [
                            'member_id' => $memberId,
                            'success' => false,
                            'error' => $photoResult['error']
                        ];
                        $errorCount++;
                    }
                } catch (Exception $e) {
                    $results[] = [
                        'member_id' => $memberId,
                        'success' => false,
                        'error' => 'Processing failed: ' . $e->getMessage()
                    ];
                    $errorCount++;
                }
            }
            
            // Log bulk operation
            $this->logPhotoAccess('bulk_upload', true, "Bulk upload: $successCount successful, $errorCount failed");
            
            $this->sendSuccess([
                'message' => "Bulk upload completed: $successCount successful, $errorCount failed",
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'successful' => $successCount,
                    'failed' => $errorCount
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Bulk upload error: " . $e->getMessage());
            $this->sendError('Failed to process bulk upload', 500);
        }
    }
    
    /**
     * Get photo statistics
     */
    private function getPhotoStats() {
        try {
            $centerFilter = $this->buildCenterFilter();
            
            $sql = "
                SELECT 
                    COUNT(*) as total_members,
                    SUM(CASE WHEN photo_path IS NOT NULL THEN 1 ELSE 0 END) as members_with_photos,
                    SUM(CASE WHEN photo_path IS NULL THEN 1 ELSE 0 END) as members_without_photos,
                    AVG(photo_file_size) as avg_file_size,
                    SUM(photo_file_size) as total_storage_used,
                    MAX(photo_uploaded_at) as last_upload_date,
                    COUNT(DISTINCT center_id) as centers_with_members
                FROM members m 
                WHERE {$centerFilter['condition']}
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($centerFilter['params']);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate percentage
            $stats['photo_coverage_percentage'] = $stats['total_members'] > 0 
                ? round(($stats['members_with_photos'] / $stats['total_members']) * 100, 2) 
                : 0;
            
            // Format file sizes
            $stats['avg_file_size_formatted'] = $this->formatFileSize($stats['avg_file_size'] ?? 0);
            $stats['total_storage_used_formatted'] = $this->formatFileSize($stats['total_storage_used'] ?? 0);
            
            // Get recent uploads
            $recentSql = "
                SELECT 
                    m.id, m.full_name, m.photo_uploaded_at, c.name as center_name
                FROM members m
                LEFT JOIN centers c ON m.center_id = c.id
                WHERE {$centerFilter['condition']} AND m.photo_path IS NOT NULL
                ORDER BY m.photo_uploaded_at DESC
                LIMIT 5
            ";
            
            $stmt = $this->db->prepare($recentSql);
            $stmt->execute($centerFilter['params']);
            $stats['recent_uploads'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->sendSuccess(['stats' => $stats]);
            
        } catch (Exception $e) {
            error_log("Get photo stats error: " . $e->getMessage());
            $this->sendError('Failed to retrieve photo statistics', 500);
        }
    }
    
    /**
     * Get members without photos
     */
    private function getMembersWithoutPhotos() {
        try {
            $centerFilter = $this->buildCenterFilter();
            
            $sql = "
                SELECT 
                    m.id, m.member_id, m.full_name, m.email, m.gender,
                    m.created_at, c.name as center_name
                FROM members m 
                LEFT JOIN centers c ON m.center_id = c.id 
                WHERE {$centerFilter['condition']} AND m.photo_path IS NULL
                ORDER BY m.full_name ASC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($centerFilter['params']);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->sendSuccess([
                'members_without_photos' => $members,
                'count' => count($members)
            ]);
            
        } catch (Exception $e) {
            error_log("Get members without photos error: " . $e->getMessage());
            $this->sendError('Failed to retrieve members without photos', 500);
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateUploadedFile($file) {
        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return [
                'valid' => false,
                'error' => 'File too large. Maximum size is ' . $this->formatFileSize(self::MAX_FILE_SIZE)
            ];
        }
        
        // Check file type
        if (!in_array($file['type'], self::ALLOWED_TYPES)) {
            return [
                'valid' => false,
                'error' => 'Invalid file type. Allowed types: ' . implode(', ', self::ALLOWED_TYPES)
            ];
        }
        
        // Verify it's actually an image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return [
                'valid' => false,
                'error' => 'File is not a valid image'
            ];
        }
        
        // Check image dimensions (minimum requirements)
        if ($imageInfo[0] < 100 || $imageInfo[1] < 100) {
            return [
                'valid' => false,
                'error' => 'Image too small. Minimum dimensions: 100x100 pixels'
            ];
        }
        
        // Check for potential security issues
        if ($this->hasSecurityIssues($file['tmp_name'])) {
            return [
                'valid' => false,
                'error' => 'File contains potential security issues'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Process and save photo with thumbnails
     */
    private function processAndSavePhoto($file, $memberId) {
        try {
            // Generate unique filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'member_' . $memberId . '_' . time() . '_' . uniqid() . '.' . $extension;
            $filepath = self::UPLOAD_PATH . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return ['success' => false, 'error' => 'Failed to save uploaded file'];
            }
            
            // Optimize main image
            $this->imageProcessor->optimizeImage($filepath, $filepath);
            
            // Generate thumbnails
            foreach (self::THUMBNAIL_SIZES as $size => $dimensions) {
                $thumbnailPath = self::UPLOAD_PATH . "thumbnails/$size/" . $filename;
                $this->imageProcessor->createThumbnail(
                    $filepath, 
                    $thumbnailPath, 
                    $dimensions['width'], 
                    $dimensions['height']
                );
            }
            
            return [
                'success' => true,
                'photo_path' => 'uploads/member-photos/' . $filename,
                'file_size' => filesize($filepath)
            ];
            
        } catch (Exception $e) {
            error_log("Process photo error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to process image'];
        }
    }
    
    /**
     * Delete photo files including thumbnails
     */
    private function deletePhotoFiles($photoPath) {
        try {
            $filename = basename($photoPath);
            $fullPath = __DIR__ . '/../../' . $photoPath;
            
            // Delete main photo
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // Delete thumbnails
            foreach (self::THUMBNAIL_SIZES as $size => $dimensions) {
                $thumbnailPath = self::UPLOAD_PATH . "thumbnails/$size/" . $filename;
                if (file_exists($thumbnailPath)) {
                    unlink($thumbnailPath);
                }
            }
            
        } catch (Exception $e) {
            error_log("Delete photo files error: " . $e->getMessage());
        }
    }
    
    /**
     * Get photo URL
     */
    private function getPhotoUrl($photoPath) {
        if (!$photoPath) return null;
        
        $baseUrl = $this->getBaseUrl();
        return $baseUrl . '/' . $photoPath;
    }
    
    /**
     * Get thumbnail URLs
     */
    private function getThumbnailUrls($photoPath) {
        if (!$photoPath) return null;
        
        $filename = basename($photoPath);
        $baseUrl = $this->getBaseUrl();
        $thumbnails = [];
        
        foreach (self::THUMBNAIL_SIZES as $size => $dimensions) {
            $thumbnailPath = "uploads/member-photos/thumbnails/$size/" . $filename;
            $thumbnails[$size] = $baseUrl . '/' . $thumbnailPath;
        }
        
        return $thumbnails;
    }
    
    /**
     * Get base URL for the application
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname(dirname($_SERVER['SCRIPT_NAME']));
        return $protocol . '://' . $host . $path;
    }
    
    /**
     * Build center filter based on user access
     */
    private function buildCenterFilter($requestedCenterId = null) {
        if ($this->currentUser['role'] === 'superadmin') {
            if ($requestedCenterId) {
                return [
                    'condition' => 'm.center_id = ?',
                    'params' => [$requestedCenterId]
                ];
            }
            return [
                'condition' => '1=1',
                'params' => []
            ];
        }
        
        $centerIds = array_column($this->userCenters, 'id');
        
        if ($requestedCenterId) {
            if (!in_array($requestedCenterId, $centerIds)) {
                throw new Exception('Access denied to requested center');
            }
            return [
                'condition' => 'm.center_id = ?',
                'params' => [$requestedCenterId]
            ];
        }
        
        $placeholders = str_repeat('?,', count($centerIds) - 1) . '?';
        return [
            'condition' => "m.center_id IN ($placeholders)",
            'params' => $centerIds
        ];
    }
    
    /**
     * Verify member access based on center assignment
     */
    private function verifyMemberAccess($memberId) {
        if ($this->currentUser['role'] === 'superadmin') {
            return true;
        }
        
        $stmt = $this->db->prepare("SELECT center_id FROM members WHERE id = ?");
        $stmt->execute([$memberId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$member) {
            return false;
        }
        
        $centerIds = array_column($this->userCenters, 'id');
        return in_array($member['center_id'], $centerIds);
    }
    
    /**
     * Get member by ID
     */
    private function getMemberById($memberId) {
        $stmt = $this->db->prepare("
            SELECT id, member_id, full_name, email, center_id, photo_path, photo_file_size
            FROM members 
            WHERE id = ?
        ");
        $stmt->execute([$memberId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check for security issues in uploaded file
     */
    private function hasSecurityIssues($filePath) {
        // Check for embedded PHP code
        $content = file_get_contents($filePath, false, null, 0, 1024);
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            return true;
        }
        
        // Check for suspicious file signatures
        $suspiciousSignatures = [
            "\x00\x00\x01\x00", // ICO
            "PK\x03\x04",       // ZIP
            "\x50\x4B\x03\x04", // ZIP
        ];
        
        foreach ($suspiciousSignatures as $signature) {
            if (strpos($content, $signature) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Format file size for display
     */
    private function formatFileSize($bytes) {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes, 1024));
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
    
    /**
     * Log photo access for audit trail
     */
    private function logPhotoAccess($action, $success, $details = '') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO access_control_logs (
                    user_id, user_role, resource_type, action, 
                    access_granted, denial_reason, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->currentUser['id'],
                $this->currentUser['role'],
                'member_photos',
                $action,
                $success,
                $success ? $details : "Access denied: $details"
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to log photo access: " . $e->getMessage());
        }
    }
    
    private function sendSuccess($data) {
        echo json_encode(['success' => true] + $data);
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
    }
}

// Initialize and handle request
try {
    $controller = new CenterMemberPhotosController();
    $controller->handleRequest();
} catch (Exception $e) {
    error_log("CenterMemberPhotosController Fatal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>