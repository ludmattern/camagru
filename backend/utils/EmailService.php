<?php

/**
 * Email service class
 */
class EmailService {
    private $host;
    private $port;
    private $username;
    private $password;
    private $from;
    
    public function __construct() {
        $this->host = $_ENV['MAIL_HOST'] ?? 'localhost';
        $this->port = $_ENV['MAIL_PORT'] ?? 587;
        $this->username = $_ENV['MAIL_USERNAME'] ?? '';
        $this->password = $_ENV['MAIL_PASSWORD'] ?? '';
        $this->from = $_ENV['MAIL_FROM'] ?? 'noreply@camagru.com';
    }
    
    /**
     * Send email
     */
    public function send($to, $subject, $body, $isHTML = true) {
        try {
            // For development, we'll use PHP's mail() function
            // In production, you should use a proper SMTP library like PHPMailer or SwiftMailer
            
            $headers = [
                'From: ' . $this->from,
                'Reply-To: ' . $this->from,
                'X-Mailer: PHP/' . phpversion()
            ];
            
            if ($isHTML) {
                $headers[] = 'MIME-Version: 1.0';
                $headers[] = 'Content-type: text/html; charset=UTF-8';
            }
            
            $headerString = implode("\r\n", $headers);
            
            // In development mode, log emails instead of sending them
            if ($_ENV['APP_ENV'] === 'development' || !function_exists('mail')) {
                $this->logEmail($to, $subject, $body);
                return true;
            }
            
            return mail($to, $subject, $body, $headerString);
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Queue email for later sending
     */
    public function queue($to, $subject, $body, $template = null, $templateData = null) {
        try {
            require_once __DIR__ . '/../classes/Database.php';
            $db = Database::getInstance();
            
            $stmt = $db->prepare("
                INSERT INTO email_queue (to_email, subject, body, template, template_data) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $templateDataJson = $templateData ? json_encode($templateData) : null;
            
            $stmt->execute([$to, $subject, $body, $template, $templateDataJson]);
            
            return true;
        } catch (Exception $e) {
            error_log("Email queueing failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process email queue
     */
    public function processQueue($limit = 10) {
        try {
            require_once __DIR__ . '/../classes/Database.php';
            $db = Database::getInstance();
            
            $stmt = $db->prepare("
                SELECT * FROM email_queue 
                WHERE status = 'pending' AND scheduled_at <= NOW() AND attempts < max_attempts 
                ORDER BY scheduled_at ASC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $emails = $stmt->fetchAll();
            
            foreach ($emails as $email) {
                $this->processQueuedEmail($email);
            }
            
            return count($emails);
        } catch (Exception $e) {
            error_log("Email queue processing failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Process individual queued email
     */
    private function processQueuedEmail($email) {
        try {
            require_once __DIR__ . '/../classes/Database.php';
            $db = Database::getInstance();
            
            // Update attempts
            $stmt = $db->prepare("UPDATE email_queue SET attempts = attempts + 1 WHERE id = ?");
            $stmt->execute([$email['id']]);
            
            // Try to send email
            $success = $this->send($email['to_email'], $email['subject'], $email['body']);
            
            if ($success) {
                // Mark as sent
                $stmt = $db->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
                $stmt->execute([$email['id']]);
            } else {
                // Mark as failed if max attempts reached
                if ($email['attempts'] + 1 >= $email['max_attempts']) {
                    $stmt = $db->prepare("UPDATE email_queue SET status = 'failed', error_message = ? WHERE id = ?");
                    $stmt->execute(["Max attempts reached", $email['id']]);
                }
            }
        } catch (Exception $e) {
            // Mark as failed
            require_once __DIR__ . '/../classes/Database.php';
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE email_queue SET status = 'failed', error_message = ? WHERE id = ?");
            $stmt->execute([$e->getMessage(), $email['id']]);
        }
    }
    
    /**
     * Log email for development
     */
    private function logEmail($to, $subject, $body) {
        $logDir = '/var/www/html/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/emails.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $logEntry = "[$timestamp] EMAIL TO: $to\n";
        $logEntry .= "SUBJECT: $subject\n";
        $logEntry .= "BODY:\n$body\n";
        $logEntry .= str_repeat('-', 50) . "\n\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Send welcome email
     */
    public function sendWelcomeEmail($to, $username, $verificationToken) {
        $subject = "Welcome to Camagru!";
        $verifyUrl = $_ENV['APP_URL'] . "/verify.php?token=" . $verificationToken;
        
        $body = $this->getEmailTemplate('welcome', [
            'username' => $username,
            'verify_url' => $verifyUrl
        ]);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($to, $resetToken) {
        $subject = "Reset your Camagru password";
        $resetUrl = $_ENV['APP_URL'] . "/reset-password.php?token=" . $resetToken;
        
        $body = $this->getEmailTemplate('password_reset', [
            'reset_url' => $resetUrl
        ]);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Send comment notification email
     */
    public function sendCommentNotificationEmail($to, $commenterName, $imageUrl, $comment) {
        $subject = "New comment on your photo";
        
        $body = $this->getEmailTemplate('comment_notification', [
            'commenter_name' => $commenterName,
            'image_url' => $imageUrl,
            'comment' => $comment
        ]);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Get email template
     */
    private function getEmailTemplate($template, $variables = []) {
        $templatePath = __DIR__ . '/../templates/email/' . $template . '.html';
        
        if (file_exists($templatePath)) {
            $content = file_get_contents($templatePath);
            
            // Replace variables
            foreach ($variables as $key => $value) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
            }
            
            return $content;
        }
        
        // Fallback to simple templates
        return $this->getSimpleEmailTemplate($template, $variables);
    }
    
    /**
     * Get simple email template
     */
    private function getSimpleEmailTemplate($template, $variables = []) {
        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost:8080';
        
        $header = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Camagru</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .button { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Camagru</h1>
                </div>
                <div class='content'>
        ";
        
        $footer = "
                </div>
                <div class='footer'>
                    <p>This email was sent by Camagru. If you didn't request this email, you can safely ignore it.</p>
                    <p><a href='$appUrl'>Visit Camagru</a></p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        switch ($template) {
            case 'welcome':
                return $header . "
                    <h2>Welcome, {$variables['username']}!</h2>
                    <p>Thank you for joining Camagru. Please verify your email address to activate your account.</p>
                    <p><a href='{$variables['verify_url']}' class='button'>Verify Email</a></p>
                " . $footer;
                
            case 'password_reset':
                return $header . "
                    <h2>Password Reset Request</h2>
                    <p>You requested a password reset for your Camagru account.</p>
                    <p><a href='{$variables['reset_url']}' class='button'>Reset Password</a></p>
                    <p>This link will expire in 1 hour.</p>
                " . $footer;
                
            case 'comment_notification':
                return $header . "
                    <h2>New Comment on Your Photo</h2>
                    <p><strong>{$variables['commenter_name']}</strong> commented on your photo:</p>
                    <blockquote style='border-left: 3px solid #007bff; padding-left: 10px; margin: 10px 0;'>{$variables['comment']}</blockquote>
                    <p><a href='{$variables['image_url']}' class='button'>View Photo</a></p>
                " . $footer;
                
            default:
                return $header . "<p>Email content</p>" . $footer;
        }
    }
}
