<?php
require_once '../../backend/config.php';

header('Content-Type: application/json');

try {
    $userId = AuthMiddleware::requireAuth();
    
    if (!isPost()) {
        throw new Exception("Only POST method allowed");
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // CSRF Protection - check in JSON input for AJAX requests
    $csrfToken = $input['csrf_token'] ?? getPost('csrf_token');
    if (!Security::verifyCSRFToken($csrfToken)) {
        throw new Exception("Invalid CSRF token");
    }
    $imageId = $input['image_id'] ?? null;
    $action = $input['action'] ?? null;
    
    if (!$imageId || !$action) {
        throw new Exception("Missing required parameters");
    }
    
    if (!in_array($action, ['like', 'unlike'])) {
        throw new Exception("Invalid action");
    }
    
    $imageService = new Image();
    
    if ($action === 'like') {
        $result = $imageService->addLike($imageId, $userId);
    } else {
        $result = $imageService->removeLike($imageId, $userId);
    }
    
    // Get updated image data
    $image = $imageService->getById($imageId);
    
    successResponse([
        'liked' => $action === 'like',
        'likes_count' => $image['likes_count']
    ]);
    
} catch (Exception $e) {
    errorResponse($e->getMessage());
}
