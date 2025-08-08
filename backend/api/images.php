<?php
require_once '../../backend/config.php';

header('Content-Type: application/json');

try {
    $imageService = new Image();
    
    if (isGet()) {
        // Get images
        $userImages = isset($_GET['user_images']);
        $imageId = $_GET['id'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 10))); // Max 50 per page
        
        if ($imageId) {
            // Get specific image
            $image = $imageService->getById($imageId);
            if (!$image) {
                throw new Exception("Image not found");
            }
            successResponse($image);
            
        } elseif ($userImages) {
            // Get current user's images
            $userId = AuthMiddleware::requireAuth();
            $images = $imageService->getUserImages($userId, $page, $limit);
            successResponse($images);
            
        } else {
            // Get public images
            $images = $imageService->getPublicImages($page, $limit);
            $totalImages = $imageService->getPublicImagesCount();
            $totalPages = ceil($totalImages / $limit);
            
            successResponse([
                'images' => $images,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_images' => $totalImages,
                    'per_page' => $limit
                ]
            ]);
        }
        
    } elseif (isPost()) {
        // Create new image (handled by upload.php)
        throw new Exception("Use upload.php for creating images");
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update image
        $userId = AuthMiddleware::requireAuth();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $imageId = $input['id'] ?? null;
        $title = $input['title'] ?? null;
        $description = $input['description'] ?? null;
        $isPublic = $input['is_public'] ?? null;
        
        if (!$imageId) {
            throw new Exception("Image ID required");
        }
        
        // Check ownership
        $image = $imageService->getById($imageId);
        if (!$image || $image['user_id'] != $userId) {
            throw new Exception("Image not found or access denied");
        }
        
        // Update image
        $db = Database::getInstance();
        $updates = [];
        $params = [];
        
        if ($title !== null) {
            $updates[] = "title = ?";
            $params[] = Security::sanitizeInput($title);
        }
        
        if ($description !== null) {
            $updates[] = "description = ?";
            $params[] = Security::sanitizeInput($description);
        }
        
        if ($isPublic !== null) {
            $updates[] = "is_public = ?";
            $params[] = $isPublic ? 1 : 0;
        }
        
        if (!empty($updates)) {
            $params[] = $imageId;
            $sql = "UPDATE images SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }
        
        // Get updated image
        $updatedImage = $imageService->getById($imageId);
        successResponse($updatedImage, 'Image updated successfully');
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete image
        $userId = AuthMiddleware::requireAuth();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $imageId = $input['id'] ?? null;
        
        if (!$imageId) {
            throw new Exception("Image ID required");
        }
        
        $result = $imageService->delete($imageId, $userId);
        successResponse(['deleted' => true], 'Image deleted successfully');
        
    } else {
        throw new Exception("Method not allowed");
    }
    
} catch (Exception $e) {
    errorResponse($e->getMessage());
}
