<?php
require_once '../backend/config.php';

// Require authentication
$userId = AuthMiddleware::requireAuth();
$user = getCurrentUser();

$title = 'Profile - Camagru';
include 'header.php';

// Handle profile updates
if ($_POST && Security::validateCSRF($_POST['csrf_token'] ?? '')) {
    $errors = [];
    $success = false;
    
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        // Validation
        if (empty($username)) {
            $errors[] = "Username is required";
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $errors[] = "Username must be 3-20 characters (letters, numbers, underscore only)";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        if (!empty($firstName) && !preg_match('/^[a-zA-ZÀ-ÿ\s\-\']{2,50}$/u', $firstName)) {
            $errors[] = "Invalid first name format";
        }
        
        if (!empty($lastName) && !preg_match('/^[a-zA-ZÀ-ÿ\s\-\']{2,50}$/u', $lastName)) {
            $errors[] = "Invalid last name format";
        }
        
        if (strlen($bio) > 500) {
            $errors[] = "Bio must be less than 500 characters";
        }
        
        if (empty($errors)) {
            try {
                $userService = new User();
                
                // Check if username/email already taken by another user
                $db = Database::getInstance();
                
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $userId]);
                if ($stmt->fetch()) {
                    $errors[] = "Username already taken";
                }
                
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $userId]);
                if ($stmt->fetch()) {
                    $errors[] = "Email already registered";
                }
                
                if (empty($errors)) {
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET username = ?, email = ?, first_name = ?, last_name = ?, bio = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$username, $email, $firstName, $lastName, $bio, $userId]);
                    
                    $_SESSION['success'] = "Profile updated successfully";
                    $success = true;
                    
                    // Refresh user data
                    $user = getCurrentUser();
                }
            } catch (Exception $e) {
                $errors[] = "Failed to update profile";
                error_log("Profile update error: " . $e->getMessage());
            }
        }
    }
    
    if (isset($_POST['update_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($currentPassword)) {
            $errors[] = "Current password is required";
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
        
        if (empty($newPassword)) {
            $errors[] = "New password is required";
        } elseif (strlen($newPassword) < 8) {
            $errors[] = "New password must be at least 8 characters";
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $newPassword)) {
            $errors[] = "New password must contain at least one uppercase letter, one lowercase letter, and one number";
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = "New passwords do not match";
        }
        
        if (empty($errors)) {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $db = Database::getInstance();
                $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                
                $_SESSION['success'] = "Password updated successfully";
                $success = true;
            } catch (Exception $e) {
                $errors[] = "Failed to update password";
                error_log("Password update error: " . $e->getMessage());
            }
        }
    }
    
    if (isset($_POST['update_preferences'])) {
        $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
        $publicProfile = isset($_POST['public_profile']) ? 1 : 0;
        
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                UPDATE users 
                SET email_notifications = ?, public_profile = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$emailNotifications, $publicProfile, $userId]);
            
            $_SESSION['success'] = "Preferences updated successfully";
            $success = true;
            
            // Refresh user data
            $user = getCurrentUser();
        } catch (Exception $e) {
            $errors[] = "Failed to update preferences";
            error_log("Preferences update error: " . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
    
    if ($success) {
        header('Location: /profile.php');
        exit;
    }
}

// Get user statistics
try {
    $db = Database::getInstance();
    
    // Get image count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM images WHERE user_id = ?");
    $stmt->execute([$userId]);
    $imageCount = $stmt->fetch()['count'];
    
    // Get total likes received
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM likes l 
        JOIN images i ON l.image_id = i.id 
        WHERE i.user_id = ?
    ");
    $stmt->execute([$userId]);
    $totalLikes = $stmt->fetch()['count'];
    
    // Get total comments received
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM comments c 
        JOIN images i ON c.image_id = i.id 
        WHERE i.user_id = ?
    ");
    $stmt->execute([$userId]);
    $totalComments = $stmt->fetch()['count'];
    
    // Get recent activity
    $stmt = $db->prepare("
        SELECT 'image' as type, title, created_at, filename
        FROM images 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentActivity = $stmt->fetchAll();
    
} catch (Exception $e) {
    $imageCount = 0;
    $totalLikes = 0;
    $totalComments = 0;
    $recentActivity = [];
    error_log("Profile stats error: " . $e->getMessage());
}
?>

<div class="row">
    <div class="col-md-4">
        <!-- Profile Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-body text-center">
                <div class="profile-avatar mb-3">
                    <i class="fas fa-user-circle fa-5x text-muted"></i>
                </div>
                <h4><?php echo Security::escape($user['username']); ?></h4>
                <?php if ($user['first_name'] || $user['last_name']): ?>
                    <p class="text-muted mb-2">
                        <?php echo Security::escape(trim($user['first_name'] . ' ' . $user['last_name'])); ?>
                    </p>
                <?php endif; ?>
                <p class="text-muted small">
                    Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                </p>
                <?php if ($user['bio']): ?>
                    <p class="mt-3"><?php echo Security::escape($user['bio']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistics Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Statistics</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="stat-item">
                            <h4 class="text-primary mb-0"><?php echo $imageCount; ?></h4>
                            <small class="text-muted">Photos</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-item">
                            <h4 class="text-danger mb-0"><?php echo $totalLikes; ?></h4>
                            <small class="text-muted">Likes</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-item">
                            <h4 class="text-info mb-0"><?php echo $totalComments; ?></h4>
                            <small class="text-muted">Comments</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-clock"></i> Recent Activity</h6>
            </div>
            <div class="card-body">
                <?php if (empty($recentActivity)): ?>
                    <p class="text-muted text-center">No recent activity</p>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-camera text-primary"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="small">
                                        <strong>Posted:</strong> 
                                        <?php echo Security::escape($activity['title'] ?: 'Untitled photo'); ?>
                                    </div>
                                    <div class="text-muted smaller">
                                        <?php echo timeAgo($activity['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center">
                        <a href="/my-images.php" class="btn btn-sm btn-outline-primary">View All Photos</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Profile Settings Tabs -->
        <div class="card shadow-sm">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" 
                                data-bs-target="#profile" type="button" role="tab">
                            <i class="fas fa-user"></i> Profile
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="security-tab" data-bs-toggle="tab" 
                                data-bs-target="#security" type="button" role="tab">
                            <i class="fas fa-lock"></i> Security
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" 
                                data-bs-target="#preferences" type="button" role="tab">
                            <i class="fas fa-cog"></i> Preferences
                        </button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body">
                <div class="tab-content" id="profileTabsContent">
                    <!-- Profile Tab -->
                    <div class="tab-pane fade show active" id="profile" role="tabpanel">
                        <form method="POST" action="/profile.php">
                            <?php echo Security::generateCSRFField(); ?>
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username *</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo Security::escape($user['username']); ?>" 
                                               pattern="[a-zA-Z0-9_]{3,20}" required>
                                        <div class="form-text">3-20 characters, letters, numbers, and underscore only</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo Security::escape($user['email']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo Security::escape($user['first_name'] ?? ''); ?>" 
                                               maxlength="50">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo Security::escape($user['last_name'] ?? ''); ?>" 
                                               maxlength="50">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control" id="bio" name="bio" rows="3" 
                                          maxlength="500" placeholder="Tell us about yourself..."><?php echo Security::escape($user['bio'] ?? ''); ?></textarea>
                                <div class="form-text">Maximum 500 characters</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Security Tab -->
                    <div class="tab-pane fade" id="security" role="tabpanel">
                        <form method="POST" action="/profile.php" id="passwordForm">
                            <?php echo Security::generateCSRFField(); ?>
                            <input type="hidden" name="update_password" value="1">
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password *</label>
                                <input type="password" class="form-control" id="current_password" 
                                       name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password *</label>
                                <input type="password" class="form-control" id="new_password" 
                                       name="new_password" minlength="8" required>
                                <div class="form-text">
                                    At least 8 characters with uppercase, lowercase, and number
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                <input type="password" class="form-control" id="confirm_password" 
                                       name="confirm_password" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning">Update Password</button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <!-- Account Verification Status -->
                        <div class="alert <?php echo $user['email_verified'] ? 'alert-success' : 'alert-warning'; ?>">
                            <h6 class="alert-heading">
                                <i class="fas <?php echo $user['email_verified'] ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                                Email Verification
                            </h6>
                            <?php if ($user['email_verified']): ?>
                                Your email address is verified.
                            <?php else: ?>
                                Your email address is not verified. 
                                <a href="/resend-verification.php" class="alert-link">Resend verification email</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Preferences Tab -->
                    <div class="tab-pane fade" id="preferences" role="tabpanel">
                        <form method="POST" action="/profile.php">
                            <?php echo Security::generateCSRFField(); ?>
                            <input type="hidden" name="update_preferences" value="1">
                            
                            <div class="mb-4">
                                <h6>Notifications</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="email_notifications" 
                                           name="email_notifications" <?php echo $user['email_notifications'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">
                                        Email notifications for likes and comments
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h6>Privacy</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="public_profile" 
                                           name="public_profile" <?php echo $user['public_profile'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="public_profile">
                                        Make my profile visible to other users
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Update Preferences</button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <!-- Danger Zone -->
                        <div class="border border-danger rounded p-3">
                            <h6 class="text-danger">Danger Zone</h6>
                            <p class="text-muted mb-3">
                                These actions are irreversible. Please be certain before proceeding.
                            </p>
                            <button class="btn btn-outline-danger" onclick="confirmDeleteAccount()">
                                Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        CamagruApp.showAlert('New passwords do not match', 'danger');
        return false;
    }
    
    // Check password strength
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/;
    if (!passwordRegex.test(newPassword)) {
        e.preventDefault();
        CamagruApp.showAlert('Password must contain at least one uppercase letter, one lowercase letter, and one number', 'danger');
        return false;
    }
});

function confirmDeleteAccount() {
    const confirmation = prompt('Type "DELETE" to confirm account deletion:');
    if (confirmation === 'DELETE') {
        if (confirm('This will permanently delete your account and all your photos. Are you absolutely sure?')) {
            deleteAccount();
        }
    }
}

function deleteAccount() {
    fetch('/backend/api/user.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ action: 'delete_account' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            CamagruApp.showAlert('Account deleted successfully', 'success');
            setTimeout(() => {
                window.location.href = '/';
            }, 2000);
        } else {
            CamagruApp.showAlert(data.error || 'Failed to delete account', 'danger');
        }
    })
    .catch(error => {
        console.error('Delete account error:', error);
        CamagruApp.showAlert('Failed to delete account', 'danger');
    });
}

// Character counter for bio
document.getElementById('bio').addEventListener('input', function() {
    const maxLength = 500;
    const currentLength = this.value.length;
    const remaining = maxLength - currentLength;
    
    let counterElement = document.getElementById('bio-counter');
    if (!counterElement) {
        counterElement = document.createElement('div');
        counterElement.id = 'bio-counter';
        counterElement.className = 'form-text';
        this.parentNode.appendChild(counterElement);
    }
    
    counterElement.textContent = `${remaining} characters remaining`;
    counterElement.className = remaining < 50 ? 'form-text text-warning' : 'form-text';
});
</script>

<style>
.stat-item {
    padding: 10px 0;
}

.activity-list .d-flex {
    border-left: 2px solid #e9ecef;
    padding-left: 15px;
    margin-left: 10px;
}

.activity-list .d-flex:last-child {
    border-left: none;
}

.profile-avatar {
    position: relative;
    display: inline-block;
}

.smaller {
    font-size: 0.75rem;
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
}

.nav-tabs .nav-link.active {
    background-color: transparent;
    border-bottom: 2px solid #0d6efd;
    color: #0d6efd;
}
</style>

<?php include 'footer.php'; ?>
