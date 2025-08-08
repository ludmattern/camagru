<?php

/**
 * Authentication middleware
 */
class AuthMiddleware {
    
    /**
     * Check if user is authenticated
     */
    public static function requireAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            if (self::isAjaxRequest()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            } else {
                header('Location: /login.php');
                exit;
            }
        }
        
        return $_SESSION['user_id'];
    }
    
    /**
     * Check if user is guest (not authenticated)
     */
    public static function requireGuest() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id'])) {
            header('Location: /gallery.php');
            exit;
        }
    }
    
    /**
     * Get current user ID if authenticated
     */
    public static function getUserId() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user data
     */
    public static function getUser() {
        $userId = self::getUserId();
        if (!$userId) {
            return null;
        }
        
        require_once __DIR__ . '/../classes/User.php';
        $user = new User();
        return $user->getById($userId);
    }
    
    /**
     * Login user
     */
    public static function login($userId, $userData = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['login_time'] = time();
        
        if ($userData) {
            $_SESSION['username'] = $userData['username'];
            $_SESSION['email'] = $userData['email'];
        }
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clear all session data
        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Check if current user owns a resource
     */
    public static function checkOwnership($resourceUserId) {
        $currentUserId = self::getUserId();
        
        if (!$currentUserId || $currentUserId != $resourceUserId) {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            } else {
                http_response_code(403);
                include __DIR__ . '/../../public/403.php';
                exit;
            }
        }
        
        return true;
    }
    
    /**
     * Check session validity
     */
    public static function validateSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionLifetime = $_ENV['SESSION_LIFETIME'] ?? 86400; // 24 hours default
        
        if (isset($_SESSION['login_time'])) {
            if (time() - $_SESSION['login_time'] > $sessionLifetime) {
                self::logout();
                return false;
            }
            
            // Update login time for active sessions
            $_SESSION['login_time'] = time();
        }
        
        return true;
    }
    
    /**
     * Check if request is AJAX
     */
    private static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Set remember me cookie
     */
    public static function setRememberMe($userId, $token) {
        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
        setcookie('remember_token', $token, $expiry, '/', '', true, true);
        
        // Store token in database
        require_once __DIR__ . '/../classes/Database.php';
        $db = Database::getInstance();
        
        $hashedToken = hash('sha256', $token);
        $stmt = $db->prepare("UPDATE users SET remember_token = ?, remember_token_expires = ? WHERE id = ?");
        $stmt->execute([$hashedToken, date('Y-m-d H:i:s', $expiry), $userId]);
    }
    
    /**
     * Check remember me cookie
     */
    public static function checkRememberMe() {
        if (!isset($_COOKIE['remember_token'])) {
            return false;
        }
        
        $token = $_COOKIE['remember_token'];
        $hashedToken = hash('sha256', $token);
        
        require_once __DIR__ . '/../classes/Database.php';
        $db = Database::getInstance();
        
        $stmt = $db->prepare("
            SELECT id, username, email 
            FROM users 
            WHERE remember_token = ? AND remember_token_expires > NOW() AND is_verified = 1
        ");
        $stmt->execute([$hashedToken]);
        $user = $stmt->fetch();
        
        if ($user) {
            self::login($user['id'], $user);
            return true;
        } else {
            // Invalid or expired token, remove cookie
            setcookie('remember_token', '', time() - 3600, '/');
            return false;
        }
    }
    
    /**
     * Clear remember me token
     */
    public static function clearRememberMe($userId = null) {
        setcookie('remember_token', '', time() - 3600, '/');
        
        if ($userId) {
            require_once __DIR__ . '/../classes/Database.php';
            $db = Database::getInstance();
            
            $stmt = $db->prepare("UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?");
            $stmt->execute([$userId]);
        }
    }
}
