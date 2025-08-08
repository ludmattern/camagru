<?php
require_once '../backend/config.php';

$title = 'Verify Email - Camagru';
include 'header.php';

$success = false;
$error = null;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        $user = new User();
        $user->verifyEmail($token);
        $success = true;
        $_SESSION['success'] = "Email verified successfully! You can now log in.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-body text-center">
                <?php if ($success): ?>
                    <div class="mb-4">
                        <i class="fas fa-check-circle fa-5x text-success"></i>
                    </div>
                    <h3 class="text-success">Email Verified!</h3>
                    <p class="text-muted">Your email has been successfully verified. You can now log in to your account.</p>
                    <a href="/login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login Now
                    </a>
                <?php elseif ($error): ?>
                    <div class="mb-4">
                        <i class="fas fa-times-circle fa-5x text-danger"></i>
                    </div>
                    <h3 class="text-danger">Verification Failed</h3>
                    <p class="text-muted"><?php echo Security::escape($error); ?></p>
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="/login.php" class="btn btn-outline-primary">Login</a>
                        <a href="/register.php" class="btn btn-primary">Register</a>
                    </div>
                <?php else: ?>
                    <div class="mb-4">
                        <i class="fas fa-question-circle fa-5x text-warning"></i>
                    </div>
                    <h3 class="text-warning">Invalid Link</h3>
                    <p class="text-muted">The verification link is invalid or expired.</p>
                    <a href="/register.php" class="btn btn-primary">Register New Account</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
