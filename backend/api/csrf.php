<?php
require_once '../../backend/config.php';

header('Content-Type: application/json');

try {
    // Generate and return CSRF token
    $token = Security::generateCSRFToken();
    
    echo json_encode([
        'success' => true,
        'csrf_token' => $token
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate CSRF token'
    ]);
}
?>
