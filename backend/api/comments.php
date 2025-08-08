<?php
require_once '../../backend/config.php';

header('Content-Type: application/json');

try {
    if (isGet()) {
        // Get comments for an image
        $imageId = getGet('image_id');
        
        if (!$imageId) {
            throw new Exception("Image ID required");
        }
        
        $imageService = new Image();
        $comments = $imageService->getComments($imageId);
        
        successResponse($comments);
        
    } elseif (isPost()) {
        // Add new comment
        $userId = AuthMiddleware::requireAuth();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // CSRF Protection - check in JSON input for AJAX requests
        $csrfToken = $input['csrf_token'] ?? getPost('csrf_token');
        if (!Security::verifyCSRFToken($csrfToken)) {
            throw new Exception("Invalid CSRF token");
        }
        
        $imageId = $input['image_id'] ?? null;
        $content = $input['content'] ?? null;
        
        if (!$imageId || !$content) {
            throw new Exception("Missing required parameters");
        }
        
        $imageService = new Image();
        $commentId = $imageService->addComment($imageId, $userId, $content);
        
        // Get updated image data
        $image = $imageService->getById($imageId);
        
        successResponse([
            'comment_id' => $commentId,
            'comments_count' => $image['comments_count']
        ]);
        
    } else {
        throw new Exception("Method not allowed");
    }
    
} catch (Exception $e) {
    errorResponse($e->getMessage());
}
