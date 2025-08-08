<?php
require_once '../backend/config.php';

// Require authentication
$userId = AuthMiddleware::requireAuth();
$user = getCurrentUser();

$title = 'Resend Verification - Camagru';
include 'header.php';

// If already verified, redirect
if ($user['email_verified']) {
    $_SESSION['info'] = "Your email is already verified";
    header('Location: /profile.php');
    exit;
}

$canResend = true;
$waitTime = 0;

// Check if we can resend (rate limiting)
if (isset($_SESSION['last_verification_sent'])) {
    $timeSinceLastSent = time() - $_SESSION['last_verification_sent'];
    $minWaitTime = 60; // 1 minute between requests
    
    if ($timeSinceLastSent < $minWaitTime) {
        $canResend = false;
        $waitTime = $minWaitTime - $timeSinceLastSent;
    }
}

// Handle resend request
if ($_POST && Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    if (!$canResend) {
        $_SESSION['error'] = "Please wait before requesting another verification email";
    } else {
        try {
            $emailService = new EmailService();
            
            // Generate new verification token
            $verificationToken = bin2hex(random_bytes(32));
            
            // Update user with new token
            $db = Database::getInstance();
            $stmt = $db->prepare("
                UPDATE users 
                SET email_verification_token = ?, email_verification_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR)
                WHERE id = ?
            ");
            $stmt->execute([$verificationToken, $userId]);
            
            // Send verification email
            $verificationLink = getBaseUrl() . "/verify.php?token=" . $verificationToken;
            
            $emailSent = $emailService->sendVerificationEmail(
                $user['email'],
                $user['username'],
                $verificationLink
            );
            
            if ($emailSent) {
                $_SESSION['success'] = "Verification email sent successfully! Please check your inbox.";
                $_SESSION['last_verification_sent'] = time();
                
                // Redirect to prevent resubmission
                header('Location: /resend-verification.php');
                exit;
            } else {
                $_SESSION['error'] = "Failed to send verification email. Please try again later.";
            }
            
        } catch (Exception $e) {
            $_SESSION['error'] = "An error occurred while sending the verification email";
            error_log("Resend verification error: " . $e->getMessage());
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-center mb-4">
                    <i class="fas fa-envelope-open fa-3x text-warning mb-3"></i>
                    <h2>Email Verification</h2>
                    <p class="text-muted">
                        Your email address <strong><?php echo Security::escape($user['email']); ?></strong> 
                        is not verified yet.
                    </p>
                </div>
                
                <div class="alert alert-info">
                    <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Why verify your email?</h6>
                    <ul class="mb-0">
                        <li>Receive notifications about likes and comments</li>
                        <li>Reset your password if you forget it</li>
                        <li>Keep your account secure</li>
                        <li>Get important updates about your account</li>
                    </ul>
                </div>
                
                <?php if ($canResend): ?>
                    <form method="POST" action="/resend-verification.php">
                        <?php echo Security::generateCSRFField(); ?>
                        
                        <div class="mb-3">
                            <p>Click the button below to receive a new verification email:</p>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane"></i> Send Verification Email
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center">
                        <div class="alert alert-warning">
                            <i class="fas fa-clock"></i> 
                            Please wait <strong id="countdown"><?php echo $waitTime; ?></strong> seconds 
                            before requesting another verification email.
                        </div>
                        
                        <button class="btn btn-primary btn-lg" id="resend-btn" disabled>
                            <i class="fas fa-paper-plane"></i> Send Verification Email
                        </button>
                    </div>
                    
                    <script>
                    let countdown = <?php echo $waitTime; ?>;
                    const countdownElement = document.getElementById('countdown');
                    const resendBtn = document.getElementById('resend-btn');
                    
                    const timer = setInterval(() => {
                        countdown--;
                        countdownElement.textContent = countdown;
                        
                        if (countdown <= 0) {
                            clearInterval(timer);
                            location.reload(); // Refresh to show the form
                        }
                    }, 1000);
                    </script>
                <?php endif; ?>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <h6>Having trouble?</h6>
                    <ul class="list-unstyled text-muted small">
                        <li><i class="fas fa-check"></i> Check your spam/junk folder</li>
                        <li><i class="fas fa-check"></i> Make sure your email address is correct</li>
                        <li><i class="fas fa-check"></i> Add noreply@camagru.com to your contacts</li>
                    </ul>
                    
                    <div class="mt-3">
                        <a href="/profile.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Profile
                        </a>
                    </div>
                </div>
                
                <!-- Change Email Option -->
                <div class="mt-4 p-3 bg-light rounded">
                    <h6>Need to change your email address?</h6>
                    <p class="text-muted mb-2">
                        You can update your email address in your profile settings.
                    </p>
                    <a href="/profile.php" class="btn btn-sm btn-outline-primary">
                        Update Email Address
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Help Card -->
        <div class="card shadow-sm mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-question-circle"></i> Frequently Asked Questions</h6>
            </div>
            <div class="card-body">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq1">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#faqCollapse1">
                                I haven't received the verification email
                            </button>
                        </h2>
                        <div id="faqCollapse1" class="accordion-collapse collapse" 
                             data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted">
                                <ul>
                                    <li>Check your spam/junk folder</li>
                                    <li>Make sure your email address is correct in your profile</li>
                                    <li>Try requesting a new verification email</li>
                                    <li>Contact support if the problem persists</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq2">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#faqCollapse2">
                                How long is the verification link valid?
                            </button>
                        </h2>
                        <div id="faqCollapse2" class="accordion-collapse collapse" 
                             data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted">
                                Verification links are valid for 24 hours. After that, you'll need to 
                                request a new verification email.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq3">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#faqCollapse3">
                                Can I use Camagru without verifying my email?
                            </button>
                        </h2>
                        <div id="faqCollapse3" class="accordion-collapse collapse" 
                             data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted">
                                You can use most features, but email verification is required for 
                                password reset and email notifications. We highly recommend verifying 
                                your email for account security.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
