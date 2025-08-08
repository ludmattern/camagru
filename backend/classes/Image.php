<?php

require_once 'Database.php';

/**
 * Image management class
 */
class Image {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new image
     */
    public function create($userId, $filename, $originalFilename = null, $filterUsed = null, $title = null, $description = null) {
        try {
            $fileInfo = $this->getFileInfo($filename);
            
            $stmt = $this->db->prepare("
                INSERT INTO images (user_id, filename, original_filename, filter_used, title, description, 
                                  file_size, mime_type, width, height) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $filename,
                $originalFilename,
                $filterUsed,
                $title,
                $description,
                $fileInfo['size'],
                $fileInfo['mime'],
                $fileInfo['width'],
                $fileInfo['height']
            ]);
            
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get image by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT i.*, u.username 
            FROM images i 
            JOIN users u ON i.user_id = u.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get images with pagination
     */
    public function getPublicImages($page = 1, $limit = 5) {
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->db->prepare("
            SELECT i.*, u.username 
            FROM images i 
            JOIN users u ON i.user_id = u.id 
            WHERE i.is_public = 1 
            ORDER BY i.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get total count of public images
     */
    public function getPublicImagesCount() {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM images WHERE is_public = 1");
        $result = $stmt->fetch();
        return $result['count'];
    }
    
    /**
     * Get user's images
     */
    public function getUserImages($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->db->prepare("
            SELECT * FROM images 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Delete image
     */
    public function delete($imageId, $userId) {
        try {
            // Check if user owns the image
            $stmt = $this->db->prepare("SELECT filename FROM images WHERE id = ? AND user_id = ?");
            $stmt->execute([$imageId, $userId]);
            $image = $stmt->fetch();
            
            if (!$image) {
                throw new Exception("Image not found or permission denied");
            }
            
            // Delete from database
            $stmt = $this->db->prepare("DELETE FROM images WHERE id = ? AND user_id = ?");
            $stmt->execute([$imageId, $userId]);
            
            // Delete file
            $filePath = __DIR__ . '/../../uploads/' . $image['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Add like to image
     */
    public function addLike($imageId, $userId) {
        try {
            $stmt = $this->db->prepare("INSERT IGNORE INTO likes (image_id, user_id) VALUES (?, ?)");
            $stmt->execute([$imageId, $userId]);
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Remove like from image
     */
    public function removeLike($imageId, $userId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM likes WHERE image_id = ? AND user_id = ?");
            $stmt->execute([$imageId, $userId]);
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Check if user liked image
     */
    public function hasUserLiked($imageId, $userId) {
        $stmt = $this->db->prepare("SELECT 1 FROM likes WHERE image_id = ? AND user_id = ?");
        $stmt->execute([$imageId, $userId]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Add comment to image
     */
    public function addComment($imageId, $userId, $content) {
        try {
            // Sanitize content
            $content = trim(strip_tags($content));
            if (empty($content)) {
                throw new Exception("Comment cannot be empty");
            }
            
            $stmt = $this->db->prepare("INSERT INTO comments (image_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$imageId, $userId, $content]);
            
            $commentId = $this->db->lastInsertId();
            
            // Send notification email to image owner
            $this->sendCommentNotification($imageId, $userId, $content);
            
            return $commentId;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get comments for image
     */
    public function getComments($imageId) {
        $stmt = $this->db->prepare("
            SELECT c.*, u.username 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.image_id = ? 
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$imageId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Delete comment
     */
    public function deleteComment($commentId, $userId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
            $stmt->execute([$commentId, $userId]);
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Process image with filter
     */
    public function processWithFilter($imagePath, $filterId) {
        try {
            // Get filter info
            $stmt = $this->db->prepare("SELECT * FROM filters WHERE id = ? AND is_active = 1");
            $stmt->execute([$filterId]);
            $filter = $stmt->fetch();
            
            if (!$filter) {
                throw new Exception("Filter not found");
            }
            
            $filterPath = __DIR__ . '/../../filters/' . $filter['filename'];
            if (!file_exists($filterPath)) {
                throw new Exception("Filter file not found");
            }
            
            // Load images
            $baseImage = $this->loadImage($imagePath);
            $filterImage = $this->loadImage($filterPath);
            
            if (!$baseImage || !$filterImage) {
                throw new Exception("Failed to load images");
            }
            
            // Get dimensions
            $baseWidth = imagesx($baseImage);
            $baseHeight = imagesy($baseImage);
            $filterWidth = imagesx($filterImage);
            $filterHeight = imagesy($filterImage);
            
            // Resize filter to fit base image if needed
            if ($filterWidth != $baseWidth || $filterHeight != $baseHeight) {
                $resizedFilter = imagecreatetruecolor($baseWidth, $baseHeight);
                imagealphablending($resizedFilter, false);
                imagesavealpha($resizedFilter, true);
                
                imagecopyresampled($resizedFilter, $filterImage, 0, 0, 0, 0, 
                                 $baseWidth, $baseHeight, $filterWidth, $filterHeight);
                
                imagedestroy($filterImage);
                $filterImage = $resizedFilter;
            }
            
            // Merge images
            imagealphablending($baseImage, true);
            imagecopy($baseImage, $filterImage, 0, 0, 0, 0, $baseWidth, $baseHeight);
            
            // Save processed image
            $filename = uniqid('img_') . '.png';
            $outputPath = __DIR__ . '/../../uploads/' . $filename;
            
            imagepng($baseImage, $outputPath);
            
            // Cleanup
            imagedestroy($baseImage);
            imagedestroy($filterImage);
            
            return $filename;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get available filters
     */
    public function getFilters() {
        $stmt = $this->db->query("SELECT * FROM filters WHERE is_active = 1 ORDER BY sort_order, name");
        return $stmt->fetchAll();
    }
    
    /**
     * Load image from file
     */
    private function loadImage($path) {
        $imageInfo = getimagesize($path);
        if (!$imageInfo) {
            return false;
        }
        
        switch ($imageInfo['mime']) {
            case 'image/jpeg':
                return imagecreatefromjpeg($path);
            case 'image/png':
                return imagecreatefrompng($path);
            case 'image/gif':
                return imagecreatefromgif($path);
            default:
                return false;
        }
    }
    
    /**
     * Get file information
     */
    private function getFileInfo($filename) {
        $filePath = __DIR__ . '/../../uploads/' . $filename;
        
        if (!file_exists($filePath)) {
            throw new Exception("File not found");
        }
        
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            throw new Exception("Invalid image file");
        }
        
        return [
            'size' => filesize($filePath),
            'mime' => $imageInfo['mime'],
            'width' => $imageInfo[0],
            'height' => $imageInfo[1]
        ];
    }
    
    /**
     * Send comment notification
     */
    private function sendCommentNotification($imageId, $commentUserId, $content) {
        try {
            // Get image owner info
            $stmt = $this->db->prepare("
                SELECT u.email, u.username, u.email_notifications, i.filename 
                FROM users u 
                JOIN images i ON u.id = i.user_id 
                WHERE i.id = ? AND u.email_notifications = 1 AND u.id != ?
            ");
            $stmt->execute([$imageId, $commentUserId]);
            $owner = $stmt->fetch();
            
            if (!$owner) {
                return; // No notification needed
            }
            
            // Get commenter username
            $stmt = $this->db->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$commentUserId]);
            $commenter = $stmt->fetch();
            
            require_once __DIR__ . '/../utils/EmailService.php';
            $emailService = new EmailService();
            
            $subject = "New comment on your photo";
            $imageUrl = $_ENV['APP_URL'] . "/image.php?id=" . $imageId;
            
            $body = "
            <h2>New Comment on Your Photo</h2>
            <p><strong>{$commenter['username']}</strong> commented on your photo:</p>
            <blockquote>{$content}</blockquote>
            <p><a href='$imageUrl'>View Photo</a></p>
            ";
            
            $emailService->send($owner['email'], $subject, $body);
        } catch (Exception $e) {
            // Don't throw exception for notification failures
            error_log("Failed to send comment notification: " . $e->getMessage());
        }
    }
}
