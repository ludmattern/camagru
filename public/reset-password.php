<?php
require_once '../backend/config.php';

$title = 'Reset Password - Camagru';
include 'header.php';

$token = $_GET['token'] ?? null;
$tokenValid = false;

if ($token) {
    try {
        // Validate token exists and is not expired
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
        $stmt->execute([$token]);
        $tokenValid = $stmt->fetch() !== false;
    } catch (Exception $e) {
        $tokenValid = false;
    }
}

if (isPost() && $tokenValid) {
    try {
        // CSRF Protection
        $csrfToken = getPost('csrf_token');
        if (!Security::verifyCSRFToken($csrfToken)) {
            throw new Exception("Invalid CSRF token");
        }
        
        $password = getPost('password');
        $confirmPassword = getPost('confirm_password');
        
        if (empty($password) || empty($confirmPassword)) {
            throw new Exception("Please fill in all fields");
        }
        
        if ($password !== $confirmPassword) {
            throw new Exception("Passwords do not match");
        }
        
        $user = new User();
        $user->resetPassword($token, $password);
        
        $_SESSION['success'] = "Password reset successful! You can now log in with your new password.";
        redirect('/login.php');
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$csrfToken = Security::generateCSRFToken();
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <?php if (!$token): ?>
            <div class="card shadow">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <h3>Invalid Reset Link</h3>
                    <p class="text-muted">The password reset link is missing or invalid.</p>
                    <a href="/forgot-password.php" class="btn btn-primary">Request New Link</a>
                </div>
            </div>
        <?php elseif (!$tokenValid): ?>
            <div class="card shadow">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-3x text-danger mb-3"></i>
                    <h3>Link Expired</h3>
                    <p class="text-muted">This password reset link has expired or is invalid.</p>
                    <a href="/forgot-password.php" class="btn btn-primary">Request New Link</a>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow">
                <div class="card-header text-center bg-success text-white">
                    <h3><i class="fas fa-lock"></i> Set New Password</h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="resetForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$"
                                   title="Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number"
                                   required>
                            <div class="form-text">At least 8 characters with uppercase, lowercase and number</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-check"></i> Reset Password
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resetForm');
    if (form) {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        function validatePasswords() {
            if (password.value && confirmPassword.value) {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
        }
        
        password.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
        
        form.addEventListener('submit', function(e) {
            validatePasswords();
            if (!confirmPassword.checkValidity()) {
                e.preventDefault();
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>
