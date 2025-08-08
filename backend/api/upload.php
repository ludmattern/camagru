<?php
require_once '../../backend/config.php';

header('Content-Type: application/json');

try {
    $userId = AuthMiddleware::requireAuth();
    
    if (!isPost()) {
        throw new Exception("Only POST method allowed");
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("No image uploaded or upload error");
    }
    
    // Validate file
    Security::validateFileUpload($_FILES['image']);
    
    // Rate limiting
    $clientIP = Security::getClientIP();
    Security::checkRateLimit('upload', $clientIP, 10, 300); // 10 uploads per 5 minutes
    
    $filterId = $_POST['filter_id'] ?? null;
    $title = $_POST['title'] ?? null;
    $description = $_POST['description'] ?? null;
    
    // Sanitize inputs
    if ($title) $title = Security::sanitizeInput($title);
    if ($description) $description = Security::sanitizeInput($description);
    
    // Generate secure filename
    $originalFilename = $_FILES['image']['name'];
    $filename = Security::generateSecureFilename($originalFilename);
    $uploadPath = __DIR__ . '/../../uploads/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
        throw new Exception("Failed to save uploaded file");
    }
    
    // Apply filter if selected
    $finalFilename = $filename;
    if ($filterId && $filterId !== '0') {
        try {
            $imageService = new Image();
            $processedFilename = $imageService->processWithFilter($uploadPath, $filterId);
            
            // Remove original file and use processed one
            unlink($uploadPath);
            $finalFilename = $processedFilename;
            
        } catch (Exception $e) {
            // If filter processing fails, keep original image
            error_log("Filter processing failed: " . $e->getMessage());
        }
    }
    
    // Save image record to database
    $imageService = new Image();
    $imageId = $imageService->create(
        $userId,
        $finalFilename,
        $originalFilename,
        $filterId,
        $title,
        $description
    );
    
    // Get created image data
    $imageData = $imageService->getById($imageId);
    
    successResponse([
        'id' => $imageId,
        'filename' => $finalFilename,
        'title' => $title,
        'description' => $description,
        'filter_used' => $filterId,
        'created_at' => $imageData['created_at']
    ], 'Image uploaded successfully');
    
} catch (Exception $e) {
    // Clean up uploaded file on error
    if (isset($uploadPath) && file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    
    errorResponse($e->getMessage());
}
