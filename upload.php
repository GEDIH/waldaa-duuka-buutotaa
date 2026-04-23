<?php
/**
 * Waldaa Duuka Bu'ootaa - Photo Upload System
 * Server-side file upload handler with security measures
 */

// Security Headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS for local development (remove in production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configuration
define('UPLOAD_DIR', 'images/gallery/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('MAX_FILES_PER_REQUEST', 10);

// Database configuration (SQLite for simplicity)
define('DB_FILE', 'gallery.db');

/**
 * Initialize database
 */
function initDatabase() {
    try {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create gallery table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS gallery (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                title VARCHAR(255),
                description TEXT,
                file_size INTEGER,
                mime_type VARCHAR(100),
                upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT 1
            )
        ");
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate file security
 */
function validateFile($file) {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'Suura upload hin taane';
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = 'Suura guddaan 5MB gadi ta\'uu qaba';
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_TYPES)) {
        $errors[] = 'Bifa suura hin hayyamamu: ' . $mimeType;
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        $errors[] = 'Extension hin hayyamamu: ' . $extension;
    }
    
    // Additional security: Check file signature
    $handle = fopen($file['tmp_name'], 'rb');
    $signature = fread($handle, 8);
    fclose($handle);
    
    $validSignatures = [
        'jpeg' => ["\xFF\xD8\xFF"],
        'png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
        'gif' => ["GIF87a", "GIF89a"],
        'webp' => ["RIFF"]
    ];
    
    $isValidSignature = false;
    foreach ($validSignatures as $type => $signatures) {
        foreach ($signatures as $sig) {
            if (strpos($signature, $sig) === 0) {
                $isValidSignature = true;
                break 2;
            }
        }
    }
    
    if (!$isValidSignature) {
        $errors[] = 'File signature hin hayyamamu';
    }
    
    return $errors;
}

/**
 * Generate secure filename
 */
function generateSecureFilename($originalName) {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    return "wdb_{$timestamp}_{$random}.{$extension}";
}

/**
 * Create thumbnail
 */
function createThumbnail($sourcePath, $thumbnailPath, $maxWidth = 300, $maxHeight = 300) {
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) return false;
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $mimeType = $imageInfo['mime'];
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
    $newWidth = intval($sourceWidth * $ratio);
    $newHeight = intval($sourceHeight * $ratio);
    
    // Create source image
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) return false;
    
    // Create thumbnail
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and GIF
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
    
    // Save thumbnail
    $result = false;
    switch ($mimeType) {
        case 'image/jpeg':
            $result = imagejpeg($thumbnail, $thumbnailPath, 85);
            break;
        case 'image/png':
            $result = imagepng($thumbnail, $thumbnailPath, 8);
            break;
        case 'image/gif':
            $result = imagegif($thumbnail, $thumbnailPath);
            break;
    }
    
    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
    
    return $result;
}

/**
 * Main upload handler
 */
function handleUpload() {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => 'POST request qofa hayyamama'];
    }
    
    // Initialize database
    $pdo = initDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    // Create upload directory if it doesn't exist
    if (!file_exists(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            return ['success' => false, 'message' => 'Upload directory uumuu hin dandeenye'];
        }
    }
    
    // Create thumbnails directory
    $thumbDir = UPLOAD_DIR . 'thumbnails/';
    if (!file_exists($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }
    
    // Check if files were uploaded
    if (!isset($_FILES['photos']) || empty($_FILES['photos']['name'][0])) {
        return ['success' => false, 'message' => 'Suura hin filatamne'];
    }
    
    $files = $_FILES['photos'];
    $fileCount = count($files['name']);
    
    // Check file count limit
    if ($fileCount > MAX_FILES_PER_REQUEST) {
        return ['success' => false, 'message' => "Suura {$fileCount} ol fe'uu hin dandeessu"];
    }
    
    $uploadedFiles = [];
    $errors = [];
    
    // Process each file
    for ($i = 0; $i < $fileCount; $i++) {
        $file = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i]
        ];
        
        // Skip empty files
        if (empty($file['name'])) continue;
        
        // Validate file
        $validationErrors = validateFile($file);
        if (!empty($validationErrors)) {
            $errors[] = $file['name'] . ': ' . implode(', ', $validationErrors);
            continue;
        }
        
        // Generate secure filename
        $secureFilename = generateSecureFilename($file['name']);
        $uploadPath = UPLOAD_DIR . $secureFilename;
        $thumbnailPath = $thumbDir . 'thumb_' . $secureFilename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Create thumbnail
            createThumbnail($uploadPath, $thumbnailPath);
            
            // Get additional data
            $title = isset($_POST['titles'][$i]) ? trim($_POST['titles'][$i]) : '';
            $description = isset($_POST['descriptions'][$i]) ? trim($_POST['descriptions'][$i]) : '';
            
            // Save to database
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO gallery (filename, original_name, title, description, file_size, mime_type) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $secureFilename,
                    $file['name'],
                    $title,
                    $description,
                    $file['size'],
                    $file['type']
                ]);
                
                $uploadedFiles[] = [
                    'id' => $pdo->lastInsertId(),
                    'filename' => $secureFilename,
                    'original_name' => $file['name'],
                    'title' => $title,
                    'description' => $description,
                    'thumbnail' => 'thumb_' . $secureFilename
                ];
                
            } catch (PDOException $e) {
                error_log("Database insert error: " . $e->getMessage());
                $errors[] = $file['name'] . ': Database error';
                // Clean up uploaded file
                unlink($uploadPath);
                if (file_exists($thumbnailPath)) {
                    unlink($thumbnailPath);
                }
            }
        } else {
            $errors[] = $file['name'] . ': Upload failed';
        }
    }
    
    // Prepare response
    $response = [
        'success' => !empty($uploadedFiles),
        'uploaded_count' => count($uploadedFiles),
        'total_count' => $fileCount,
        'files' => $uploadedFiles
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    
    if (empty($uploadedFiles)) {
        $response['message'] = 'Suura tokkollee upload hin taane';
    } else {
        $response['message'] = count($uploadedFiles) . ' suura milkaa\'inaan upload ta\'e';
    }
    
    return $response;
}

/**
 * Get gallery images
 */
function getGalleryImages() {
    $pdo = initDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM gallery WHERE is_active = 1 ORDER BY upload_date DESC");
        $stmt->execute();
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'images' => $images,
            'count' => count($images)
        ];
    } catch (PDOException $e) {
        error_log("Database select error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

/**
 * Delete image
 */
function deleteImage($imageId) {
    $pdo = initDatabase();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        // Get image info
        $stmt = $pdo->prepare("SELECT filename FROM gallery WHERE id = ? AND is_active = 1");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$image) {
            return ['success' => false, 'message' => 'Suura hin argamne'];
        }
        
        // Delete files
        $imagePath = UPLOAD_DIR . $image['filename'];
        $thumbnailPath = UPLOAD_DIR . 'thumbnails/thumb_' . $image['filename'];
        
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        if (file_exists($thumbnailPath)) {
            unlink($thumbnailPath);
        }
        
        // Mark as deleted in database
        $stmt = $pdo->prepare("UPDATE gallery SET is_active = 0 WHERE id = ?");
        $stmt->execute([$imageId]);
        
        return ['success' => true, 'message' => 'Suura haqame'];
        
    } catch (PDOException $e) {
        error_log("Database delete error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

// Main execution
try {
    $action = $_GET['action'] ?? 'upload';
    
    switch ($action) {
        case 'upload':
            $response = handleUpload();
            break;
            
        case 'gallery':
            $response = getGalleryImages();
            break;
            
        case 'delete':
            $imageId = $_POST['image_id'] ?? 0;
            $response = deleteImage($imageId);
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
    
} catch (Exception $e) {
    error_log("Upload system error: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => 'System error occurred'
    ];
}

// Return JSON response
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>