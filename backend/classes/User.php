<?php

require_once 'Database.php';

/**
 * User management class
 */
class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new user
     */
    public function create($username, $email, $password) {
        try {
            // Validate input
            if (!$this->validateUsername($username)) {
                throw new Exception("Invalid username format");
            }
            
            if (!$this->validateEmail($email)) {
                throw new Exception("Invalid email format");
            }
            
            if (!$this->validatePassword($password)) {
                throw new Exception("Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number");
            }
            
            // Check if user already exists
            if ($this->emailExists($email)) {
                throw new Exception("Email already exists");
            }
            
            if ($this->usernameExists($username)) {
                throw new Exception("Username already exists");
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate verification token
            $verificationToken = bin2hex(random_bytes(32));
            
            // Insert user
            $stmt = $this->db->prepare("INSERT INTO users (username, email, password, verification_token) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashedPassword, $verificationToken]);
            
            $userId = $this->db->lastInsertId();
            
            // Send verification email
            $this->sendVerificationEmail($email, $verificationToken);
            
            return $userId;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Authenticate user
     */
    public function authenticate($login, $password) {
        try {
            // Check if login is email or username
            $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
            
            $stmt = $this->db->prepare("SELECT * FROM users WHERE $field = ? AND is_verified = 1");
            $stmt->execute([$login]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password'])) {
                throw new Exception("Invalid credentials");
            }
            
            return $user;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Verify email with token
     */
    public function verifyEmail($token) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE verification_token = ?");
            $stmt->execute([$token]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Invalid verification token");
            }
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Generate password reset token
     */
    public function generateResetToken($email) {
        try {
            if (!$this->emailExists($email)) {
                throw new Exception("Email not found");
            }
            
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $this->db->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
            $stmt->execute([$token, $expires, $email]);
            
            // Send reset email
            $this->sendResetEmail($email, $token);
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Reset password with token
     */
    public function resetPassword($token, $newPassword) {
        try {
            if (!$this->validatePassword($newPassword)) {
                throw new Exception("Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number");
            }
            
            $stmt = $this->db->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception("Invalid or expired reset token");
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            $stmt->execute([$hashedPassword, $user['id']]);
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($userId, $username = null, $email = null, $password = null, $emailNotifications = null) {
        try {
            $updates = [];
            $params = [];
            
            if ($username !== null) {
                if (!$this->validateUsername($username)) {
                    throw new Exception("Invalid username format");
                }
                if ($this->usernameExists($username, $userId)) {
                    throw new Exception("Username already exists");
                }
                $updates[] = "username = ?";
                $params[] = $username;
            }
            
            if ($email !== null) {
                if (!$this->validateEmail($email)) {
                    throw new Exception("Invalid email format");
                }
                if ($this->emailExists($email, $userId)) {
                    throw new Exception("Email already exists");
                }
                $updates[] = "email = ?";
                $params[] = $email;
            }
            
            if ($password !== null) {
                if (!$this->validatePassword($password)) {
                    throw new Exception("Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number");
                }
                $updates[] = "password = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            if ($emailNotifications !== null) {
                $updates[] = "email_notifications = ?";
                $params[] = $emailNotifications ? 1 : 0;
            }
            
            if (empty($updates)) {
                return true;
            }
            
            $params[] = $userId;
            $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get user by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT id, username, email, email_notifications, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Validate username
     */
    private function validateUsername($username) {
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
    }
    
    /**
     * Validate email
     */
    private function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate password
     */
    private function validatePassword($password) {
        return strlen($password) >= 8 && 
               preg_match('/[A-Z]/', $password) && 
               preg_match('/[a-z]/', $password) && 
               preg_match('/[0-9]/', $password);
    }
    
    /**
     * Check if email exists
     */
    private function emailExists($email, $excludeUserId = null) {
        $sql = "SELECT id FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeUserId) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Check if username exists
     */
    private function usernameExists($username, $excludeUserId = null) {
        $sql = "SELECT id FROM users WHERE username = ?";
        $params = [$username];
        
        if ($excludeUserId) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Send verification email
     */
    private function sendVerificationEmail($email, $token) {
        require_once __DIR__ . '/../utils/EmailService.php';
        $emailService = new EmailService();
        
        $subject = "Verify your Camagru account";
        $verifyUrl = $_ENV['APP_URL'] . "/verify.php?token=" . $token;
        
        $body = "
        <h2>Welcome to Camagru!</h2>
        <p>Please click the link below to verify your email address:</p>
        <p><a href='$verifyUrl'>Verify Email</a></p>
        <p>If you didn't create an account, you can safely ignore this email.</p>
        ";
        
        $emailService->send($email, $subject, $body);
    }
    
    /**
     * Send password reset email
     */
    private function sendResetEmail($email, $token) {
        require_once __DIR__ . '/../utils/EmailService.php';
        $emailService = new EmailService();
        
        $subject = "Reset your Camagru password";
        $resetUrl = $_ENV['APP_URL'] . "/reset-password.php?token=" . $token;
        
        $body = "
        <h2>Password Reset Request</h2>
        <p>Click the link below to reset your password:</p>
        <p><a href='$resetUrl'>Reset Password</a></p>
        <p>This link will expire in 1 hour.</p>
        <p>If you didn't request a password reset, you can safely ignore this email.</p>
        ";
        
        $emailService->send($email, $subject, $body);
    }
}
