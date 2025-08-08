<?php
require_once '../../backend/config.php';

header('Content-Type: application/json');

// Require authentication
$userId = AuthMiddleware::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['action']) || $input['action'] !== 'delete_account') {
                throw new Exception('Invalid action');
            }
            
            // Begin transaction
            $db = Database::getInstance();
            $db->beginTransaction();
            
            try {
                // Delete user's images from filesystem
                $stmt = $db->prepare("SELECT filename FROM images WHERE user_id = ?");
                $stmt->execute([$userId]);
                $images = $stmt->fetchAll();
                
                foreach ($images as $image) {
                    $filePath = __DIR__ . '/../../public/uploads/' . $image['filename'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                
                // Delete user data (cascading deletes will handle related records)
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                
                $db->commit();
                
                // Destroy session
                session_destroy();
                
                echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'GET':
            // Get user profile data
            $user = getCurrentUser();
            
            // Remove sensitive data
            unset($user['password']);
            
            echo json_encode(['success' => true, 'data' => $user]);
            break;
            
        case 'PUT':
            // Update user profile (alternative to form submission)
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                throw new Exception('Invalid JSON data');
            }
            
            $allowedFields = ['username', 'email', 'first_name', 'last_name', 'bio', 'email_notifications', 'public_profile'];
            $updateData = [];
            $params = [];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateData[] = "$field = ?";
                    $params[] = $input[$field];
                }
            }
            
            if (empty($updateData)) {
                throw new Exception('No valid fields to update');
            }
            
            $params[] = $userId;
            $sql = "UPDATE users SET " . implode(', ', $updateData) . ", updated_at = NOW() WHERE id = ?";
            
            $db = Database::getInstance();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
