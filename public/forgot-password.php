<?php
require_once '../backend/config.php';

$title = 'Forgot Password - Camagru';
include 'header.php';

if (isPost()) {
    try {
        // CSRF Protection
        $csrfToken = getPost('csrf_token');
        if (!Security::verifyCSRFToken($csrfToken)) {
            throw new Exception("Invalid CSRF token");
        }
        
        // Rate limiting
        $clientIP = Security::getClientIP();
        Security::checkRateLimit('forgot_password', $clientIP, 3, 300);
        
        $email = Security::sanitizeInput(getPost('email'));
        
        if (empty($email)) {
            throw new Exception("Please enter your email address");
        }
        
        $user = new User();
        $user->generateResetToken($email);
        
        $_SESSION['success'] = "If an account with that email exists, a password reset link has been sent.";
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$csrfToken = Security::generateCSRFToken();
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-header text-center bg-warning text-dark">
                <h3><i class="fas fa-key"></i> Reset Password</h3>
            </div>
            <div class="card-body">
                <p class="text-muted text-center mb-4">
                    Enter your email address and we'll send you a link to reset your password.
                </p>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo Security::escape(getPost('email', '')); ?>" 
                               placeholder="Enter your email" required>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100 mb-3">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </form>
                
                <div class="text-center">
                    <p class="mb-2">
                        <a href="/login.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </p>
                    <p class="mb-0">
                        Don't have an account? <a href="/register.php" class="text-decoration-none">Register here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
