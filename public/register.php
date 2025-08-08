<?php
require_once '../backend/config.php';

// Redirect if already logged in
AuthMiddleware::requireGuest();

$title = 'Register - Camagru';
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
        Security::checkRateLimit('register', $clientIP, 3, 300);
        
        $username = Security::sanitizeInput(getPost('username'));
        $email = Security::sanitizeInput(getPost('email'));
        $password = getPost('password');
        $confirmPassword = getPost('confirm_password');
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
            throw new Exception("Please fill in all fields");
        }
        
        if ($password !== $confirmPassword) {
            throw new Exception("Passwords do not match");
        }
        
        $user = new User();
        $userId = $user->create($username, $email, $password);
        
        $_SESSION['success'] = "Registration successful! Please check your email to verify your account.";
        redirect('/login.php');
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$csrfToken = Security::generateCSRFToken();
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-header text-center bg-primary text-white">
                <h3><i class="fas fa-user-plus"></i> Create Account</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="registerForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo Security::escape(getPost('username', '')); ?>" 
                               pattern="[a-zA-Z0-9_]{3,20}" 
                               title="Username must be 3-20 characters long and contain only letters, numbers, and underscores"
                               required>
                        <div class="form-text">3-20 characters, letters, numbers, and underscores only</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo Security::escape(getPost('email', '')); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$"
                               title="Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number"
                               required>
                        <div class="form-text">At least 8 characters with uppercase, lowercase, and number</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div id="password-match-feedback" class="form-text"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                
                <div class="text-center">
                    <p class="mb-0">
                        Already have an account? <a href="/login.php" class="text-decoration-none">Login here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const feedback = document.getElementById('password-match-feedback');
    const submitBtn = document.getElementById('submitBtn');
    
    function checkPasswordMatch() {
        if (confirmPassword.value === '') {
            feedback.textContent = '';
            feedback.className = 'form-text';
            return true;
        }
        
        if (password.value === confirmPassword.value) {
            feedback.textContent = 'Passwords match ✓';
            feedback.className = 'form-text text-success';
            return true;
        } else {
            feedback.textContent = 'Passwords do not match ✗';
            feedback.className = 'form-text text-danger';
            return false;
        }
    }
    
    function validateForm() {
        const isPasswordMatch = checkPasswordMatch();
        const isPasswordValid = password.checkValidity();
        const isFormValid = form.checkValidity();
        
        submitBtn.disabled = !(isPasswordMatch && isPasswordValid && isFormValid);
    }
    
    password.addEventListener('input', validateForm);
    confirmPassword.addEventListener('input', validateForm);
    
    form.addEventListener('input', validateForm);
    
    // Initial validation
    validateForm();
});
</script>

<?php include 'footer.php'; ?>
