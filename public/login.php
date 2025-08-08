<?php
require_once '../backend/config.php';

// Redirect if already logged in
AuthMiddleware::requireGuest();

$title = 'Login - Camagru';
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
        Security::checkRateLimit('login', $clientIP, 5, 300);
        
        $login = Security::sanitizeInput(getPost('login'));
        $password = getPost('password');
        $rememberMe = getPost('remember_me') === '1';
        
        if (empty($login) || empty($password)) {
            throw new Exception("Please fill in all fields");
        }
        
        $user = new User();
        $userData = $user->authenticate($login, $password);
        
        // Login successful
        AuthMiddleware::login($userData['id'], $userData);
        
        // Set remember me if requested
        if ($rememberMe) {
            $token = Security::generateToken();
            AuthMiddleware::setRememberMe($userData['id'], $token);
        }
        
        $_SESSION['success'] = "Welcome back, " . $userData['username'] . "!";
        redirect('/gallery.php');
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$csrfToken = Security::generateCSRFToken();
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-header text-center bg-primary text-white">
                <h3><i class="fas fa-sign-in-alt"></i> Login</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="mb-3">
                        <label for="login" class="form-label">Username or Email</label>
                        <input type="text" class="form-control" id="login" name="login" 
                               value="<?php echo Security::escape(getPost('login', '')); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me" value="1">
                        <label class="form-check-label" for="remember_me">
                            Remember me
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div class="text-center">
                    <p class="mb-2">
                        <a href="/forgot-password.php" class="text-decoration-none">Forgot your password?</a>
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
