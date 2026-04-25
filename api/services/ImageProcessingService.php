<?php
/**
 * Image Processing Service
 * 
 * Handles image optimization, thumbnail generation, and image manipulation
 * for the member photo management system.
 * 
 * Features:
 * - Image optimization and compression
 * - Thumbnail generation with multiple sizes
 * - Format conversion and validation
 * - Security scanning for malicious content
 * - Performance optimizations
 * 
 * @author WDB Development Team
 * @version 1.0.0
 * @since 2024-12-19
 */

class ImageProcessingService {
    
    // Image quality settings
    const JPEG_QUALITY = 85;
    const PNG_COMPRESSION = 6;
    const WEBP_QUALITY = 80;
    
    // Maximum dimensions for optimization
    const MAX_WIDTH = 1200;
    const MAX_HEIGHT = 1200;
    
    /**
     * Optimize image for web display
     * 
     * @param string $sourcePath Path to source image
     * @param string $destinationPath Path to save optimized image
     * @param array $options Optimization options
     * @return bool Success status
     */
    public function optimizeImage($sourcePath, $destinationPath, $options = []) {
        try {
            $imageInfo = getimagesize($sourcePath);
            if ($imageInfo === false) {
                throw new Exception('Invalid image file');
            }
            
            $sourceImage = $this->createImageFromFile($sourcePath, $imageInfo[2]);
            if ($sourceImage === false) {
                throw new Exception('Failed to create image resource');
            }
            
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            
            // Calculate new dimensions if resizing is needed
            $maxWidth = $options['max_width'] ?? self::MAX_WIDTH;
            $maxHeight = $options['max_height'] ?? self::MAX_HEIGHT;
            
            list($newWidth, $newHeight) = $this->calculateDimensions(
                $originalWidth, $originalHeight, $maxWidth, $maxHeight
            );
            
            // Create optimized image
            $optimizedImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG and GIF
            if ($imageInfo[2] == IMAGETYPE_PNG || $imageInfo[2] == IMAGETYPE_GIF) {
                imagealphablending($optimizedImage, false);
                imagesavealpha($optimizedImage, true);
                $transparent = imagecolorallocatealpha($optimizedImage, 255, 255, 255, 127);
                imagefill($optimizedImage, 0, 0, $transparent);
            }
            
            // Resample image
            imagecopyresampled(
                $optimizedImage, $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $originalWidth, $originalHeight
            );
            
            // Save optimized image
            $result = $this->saveImage($optimizedImage, $destinationPath, $imageInfo[2], $options);
            
            // Clean up memory
            imagedestroy($sourceImage);
            imagedestroy($optimizedImage);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Image optimization error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create thumbnail with specified dimensions
     * 
     * @param string $sourcePath Path to source image
     * @param string $thumbnailPath Path to save thumbnail
     * @param int $width Thumbnail width
     * @param int $height Thumbnail height
     * @param string $cropMode Crop mode: 'fit', 'fill', 'stretch'
     * @return bool Success status
     */
    public function createThumbnail($sourcePath, $thumbnailPath, $width, $height, $cropMode = 'fit') {
        try {
            $imageInfo = getimagesize($sourcePath);
            if ($imageInfo === false) {
                throw new Exception('Invalid image file');
            }
            
            $sourceImage = $this->createImageFromFile($sourcePath, $imageInfo[2]);
            if ($sourceImage === false) {
                throw new Exception('Failed to create image resource');
            }
            
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            
            // Calculate crop/resize parameters based on mode
            switch ($cropMode) {
                case 'fill':
                    list($srcX, $srcY, $srcW, $srcH) = $this->calculateCropFill(
                        $originalWidth, $originalHeight, $width, $height
                    );
                    break;
                case 'stretch':
                    $srcX = $srcY = 0;
                    $srcW = $originalWidth;
                    $srcH = $originalHeight;
                    break;
                case 'fit':
                default:
                    list($width, $height) = $this->calculateDimensions(
                        $originalWidth, $originalHeight, $width, $height
                    );
                    $srcX = $srcY = 0;
                    $srcW = $originalWidth;
                    $srcH = $originalHeight;
                    break;
            }
            
            // Create thumbnail
            $thumbnail = imagecreatetruecolor($width, $height);
            
            // Preserve transparency
            if ($imageInfo[2] == IMAGETYPE_PNG || $imageInfo[2] == IMAGETYPE_GIF) {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                imagefill($thumbnail, 0, 0, $transparent);
            }
            
            // Resample image
            imagecopyresampled(
                $thumbnail, $sourceImage,
                0, 0, $srcX, $srcY,
                $width, $height,
                $srcW, $srcH
            );
            
            // Ensure thumbnail directory exists
            $thumbnailDir = dirname($thumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }
            
            // Save thumbnail
            $result = $this->saveImage($thumbnail, $thumbnailPath, $imageInfo[2]);
            
            // Clean up memory
            imagedestroy($sourceImage);
            imagedestroy($thumbnail);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Thumbnail creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Convert image to different format
     * 
     * @param string $sourcePath Source image path
     * @param string $destinationPath Destination path
     * @param string $format Target format (jpeg, png, webp)
     * @return bool Success status
     */
    public function convertFormat($sourcePath, $destinationPath, $format) {
        try {
            $imageInfo = getimagesize($sourcePath);
            if ($imageInfo === false) {
                throw new Exception('Invalid image file');
            }
            
            $sourceImage = $this->createImageFromFile($sourcePath, $imageInfo[2]);
            if ($sourceImage === false) {
                throw new Exception('Failed to create image resource');
            }
            
            // Determine target image type
            switch (strtolower($format)) {
                case 'jpeg':
                case 'jpg':
                    $targetType = IMAGETYPE_JPEG;
                    break;
                case 'png':
                    $targetType = IMAGETYPE_PNG;
                    break;
                case 'webp':
                    $targetType = IMAGETYPE_WEBP;
                    break;
                default:
                    throw new Exception('Unsupported format: ' . $format);
            }
            
            // Save in new format
            $result = $this->saveImage($sourceImage, $destinationPath, $targetType);
            
            // Clean up memory
            imagedestroy($sourceImage);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Format conversion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get image information
     * 
     * @param string $imagePath Path to image
     * @return array|false Image information or false on failure
     */
    public function getImageInfo($imagePath) {
        try {
            $imageInfo = getimagesize($imagePath);
            if ($imageInfo === false) {
                return false;
            }
            
            $fileSize = filesize($imagePath);
            
            return [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'type' => $imageInfo[2],
                'mime_type' => $imageInfo['mime'],
                'file_size' => $fileSize,
                'file_size_formatted' => $this->formatFileSize($fileSize),
                'aspect_ratio' => round($imageInfo[0] / $imageInfo[1], 2)
            ];
            
        } catch (Exception $e) {
            error_log("Get image info error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create image resource from file
     * 
     * @param string $filePath Path to image file
     * @param int $imageType Image type constant
     * @return resource|false Image resource or false on failure
     */
    private function createImageFromFile($filePath, $imageType) {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filePath);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($filePath);
            default:
                return false;
        }
    }
    
    /**
     * Save image to file
     * 
     * @param resource $image Image resource
     * @param string $filePath Destination file path
     * @param int $imageType Image type constant
     * @param array $options Save options
     * @return bool Success status
     */
    private function saveImage($image, $filePath, $imageType, $options = []) {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $quality = $options['jpeg_quality'] ?? self::JPEG_QUALITY;
                return imagejpeg($image, $filePath, $quality);
                
            case IMAGETYPE_PNG:
                $compression = $options['png_compression'] ?? self::PNG_COMPRESSION;
                return imagepng($image, $filePath, $compression);
                
            case IMAGETYPE_GIF:
                return imagegif($image, $filePath);
                
            case IMAGETYPE_WEBP:
                $quality = $options['webp_quality'] ?? self::WEBP_QUALITY;
                return imagewebp($image, $filePath, $quality);
                
            default:
                return false;
        }
    }
    
    /**
     * Calculate new dimensions maintaining aspect ratio
     * 
     * @param int $originalWidth Original width
     * @param int $originalHeight Original height
     * @param int $maxWidth Maximum width
     * @param int $maxHeight Maximum height
     * @return array New dimensions [width, height]
     */
    private function calculateDimensions($originalWidth, $originalHeight, $maxWidth, $maxHeight) {
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        
        if ($ratio >= 1) {
            // Image is smaller than max dimensions, keep original size
            return [$originalWidth, $originalHeight];
        }
        
        return [
            round($originalWidth * $ratio),
            round($originalHeight * $ratio)
        ];
    }
    
    /**
     * Calculate crop parameters for fill mode
     * 
     * @param int $originalWidth Original width
     * @param int $originalHeight Original height
     * @param int $targetWidth Target width
     * @param int $targetHeight Target height
     * @return array Crop parameters [x, y, width, height]
     */
    private function calculateCropFill($originalWidth, $originalHeight, $targetWidth, $targetHeight) {
        $originalRatio = $originalWidth / $originalHeight;
        $targetRatio = $targetWidth / $targetHeight;
        
        if ($originalRatio > $targetRatio) {
            // Original is wider, crop width
            $cropHeight = $originalHeight;
            $cropWidth = round($originalHeight * $targetRatio);
            $cropX = round(($originalWidth - $cropWidth) / 2);
            $cropY = 0;
        } else {
            // Original is taller, crop height
            $cropWidth = $originalWidth;
            $cropHeight = round($originalWidth / $targetRatio);
            $cropX = 0;
            $cropY = round(($originalHeight - $cropHeight) / 2);
        }
        
        return [$cropX, $cropY, $cropWidth, $cropHeight];
    }
    
    /**
     * Format file size for display
     * 
     * @param int $bytes File size in bytes
     * @return string Formatted file size
     */
    private function formatFileSize($bytes) {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes, 1024));
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
    
    /**
     * Validate image file for security issues
     * 
     * @param string $filePath Path to image file
     * @return bool True if safe, false if potential security issue
     */
    public function validateImageSecurity($filePath) {
        try {
            // Check if it's a valid image
            $imageInfo = getimagesize($filePath);
            if ($imageInfo === false) {
                return false;
            }
            
            // Check file content for suspicious patterns
            $handle = fopen($filePath, 'rb');
            if ($handle === false) {
                return false;
            }
            
            $chunk = fread($handle, 1024);
            fclose($handle);
            
            // Look for PHP code
            if (strpos($chunk, '<?php') !== false || strpos($chunk, '<?=') !== false) {
                return false;
            }
            
            // Look for script tags
            if (stripos($chunk, '<script') !== false) {
                return false;
            }
            
            // Check for executable signatures
            $suspiciousSignatures = [
                "\x4D\x5A",         // PE executable
                "\x7F\x45\x4C\x46", // ELF executable
                "\xCA\xFE\xBA\xBE", // Java class file
            ];
            
            foreach ($suspiciousSignatures as $signature) {
                if (strpos($chunk, $signature) === 0) {
                    return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Image security validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate image hash for duplicate detection
     * 
     * @param string $filePath Path to image file
     * @return string|false Image hash or false on failure
     */
    public function generateImageHash($filePath) {
        try {
            $imageInfo = getimagesize($filePath);
            if ($imageInfo === false) {
                return false;
            }
            
            $image = $this->createImageFromFile($filePath, $imageInfo[2]);
            if ($image === false) {
                return false;
            }
            
            // Create a small version for hashing
            $hashSize = 8;
            $hashImage = imagecreatetruecolor($hashSize, $hashSize);
            imagecopyresampled(
                $hashImage, $image,
                0, 0, 0, 0,
                $hashSize, $hashSize,
                $imageInfo[0], $imageInfo[1]
            );
            
            // Convert to grayscale and generate hash
            $hash = '';
            for ($y = 0; $y < $hashSize; $y++) {
                for ($x = 0; $x < $hashSize; $x++) {
                    $rgb = imagecolorat($hashImage, $x, $y);
                    $gray = (($rgb >> 16) & 0xFF) + (($rgb >> 8) & 0xFF) + ($rgb & 0xFF);
                    $hash .= ($gray > 384) ? '1' : '0'; // 384 = 128 * 3
                }
            }
            
            // Clean up memory
            imagedestroy($image);
            imagedestroy($hashImage);
            
            return $hash;
            
        } catch (Exception $e) {
            error_log("Image hash generation error: " . $e->getMessage());
            return false;
        }
    }
}