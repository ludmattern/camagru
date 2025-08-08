<?php
require_once '../../backend/config.php';

header('Content-Type: application/json');

try {
    $imageService = new Image();
    $filters = $imageService->getFilters();
    
    successResponse($filters);
    
} catch (Exception $e) {
    errorResponse($e->getMessage());
}
