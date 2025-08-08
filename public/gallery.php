<?php
require_once '../backend/config.php';

$title = 'Camagru - Photo Gallery';
include 'header.php';

// Pagination
$page = max(1, (int)getGet('page', 1));
$limit = 5;

try {
    $imageService = new Image();
    $images = $imageService->getPublicImages($page, $limit);
    $totalImages = $imageService->getPublicImagesCount();
    $totalPages = ceil($totalImages / $limit);
} catch (Exception $e) {
    $_SESSION['error'] = "Failed to load gallery";
    $images = [];
    $totalPages = 0;
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-images"></i> Photo Gallery</h1>
            <?php if (isLoggedIn()): ?>
                <a href="/editor.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Photo
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (empty($images)): ?>
    <div class="text-center py-5">
        <i class="fas fa-images fa-5x text-muted mb-3"></i>
        <h3 class="text-muted">No photos yet</h3>
        <p class="text-muted">Be the first to share a photo!</p>
        <?php if (isLoggedIn()): ?>
            <a href="/editor.php" class="btn btn-primary">Create First Photo</a>
        <?php else: ?>
            <a href="/register.php" class="btn btn-primary">Join Camagru</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php foreach ($images as $image): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="fas fa-user-circle fa-2x text-primary me-2"></i>
                    <div>
                        <h6 class="mb-0"><?php echo Security::escape($image['username']); ?></h6>
                        <small class="text-muted"><?php echo timeAgo($image['created_at']); ?></small>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary me-2">
                        <i class="fas fa-heart"></i> <?php echo $image['likes_count']; ?>
                    </span>
                    <span class="badge bg-secondary">
                        <i class="fas fa-comment"></i> <?php echo $image['comments_count']; ?>
                    </span>
                </div>
            </div>
            
            <div class="position-relative">
                <img src="/uploads/<?php echo Security::escape($image['filename']); ?>" 
                     class="card-img-top" 
                     alt="<?php echo Security::escape($image['title'] ?? 'Photo'); ?>"
                     style="max-height: 500px; object-fit: contain; width: 100%;">
                
                <?php if (isLoggedIn()): ?>
                    <div class="position-absolute top-0 end-0 m-2">
                        <button class="btn btn-light btn-sm like-btn" 
                                data-image-id="<?php echo $image['id']; ?>"
                                data-liked="<?php echo $imageService->hasUserLiked($image['id'], AuthMiddleware::getUserId()) ? '1' : '0'; ?>">
                            <i class="fas fa-heart <?php echo $imageService->hasUserLiked($image['id'], AuthMiddleware::getUserId()) ? 'text-danger' : 'text-muted'; ?>"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($image['title'] || $image['description']): ?>
                <div class="card-body">
                    <?php if ($image['title']): ?>
                        <h5 class="card-title"><?php echo Security::escape($image['title']); ?></h5>
                    <?php endif; ?>
                    <?php if ($image['description']): ?>
                        <p class="card-text"><?php echo Security::escape($image['description']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Comments Section -->
            <div class="card-footer">
                <div class="comments-container" data-image-id="<?php echo $image['id']; ?>">
                    <!-- Comments will be loaded here -->
                </div>
                
                <?php if (isLoggedIn()): ?>
                    <form class="comment-form mt-3" data-image-id="<?php echo $image['id']; ?>">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Add a comment..." name="content" required>
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="text-muted mt-3 mb-0">
                        <a href="/login.php">Login</a> to like and comment on photos.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center mb-4">
            <?php echo generatePagination($page, $totalPages, '/gallery.php'); ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load comments for all images
    document.querySelectorAll('.comments-container').forEach(container => {
        loadComments(container.dataset.imageId);
    });
    
    // Like button functionality
    document.querySelectorAll('.like-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            toggleLike(this.dataset.imageId, this);
        });
    });
    
    // Comment form submission
    document.querySelectorAll('.comment-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitComment(this);
        });
    });
});

function loadComments(imageId) {
    fetch(`/api/comments.php?image_id=${imageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayComments(imageId, data.data);
            }
        })
        .catch(error => console.error('Error loading comments:', error));
}

function displayComments(imageId, comments) {
    const container = document.querySelector(`.comments-container[data-image-id="${imageId}"]`);
    
    if (comments.length === 0) {
        container.innerHTML = '<p class="text-muted small">No comments yet.</p>';
        return;
    }
    
    let html = '';
    comments.forEach(comment => {
        html += `
            <div class="comment mb-2 p-2 bg-light rounded">
                <div class="d-flex justify-content-between">
                    <strong class="small">${escapeHtml(comment.username)}</strong>
                    <small class="text-muted">${comment.created_at}</small>
                </div>
                <div class="small">${escapeHtml(comment.content)}</div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function toggleLike(imageId, button) {
    const isLiked = button.dataset.liked === '1';
    const action = isLiked ? 'unlike' : 'like';
    
    // Get CSRF token first
    fetch('/backend/api/csrf.php')
    .then(response => response.json())
    .then(tokenData => {
        if (!tokenData.success) {
            throw new Error('Failed to get CSRF token');
        }
        
        return fetch('/backend/api/likes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                image_id: imageId,
                action: action,
                csrf_token: tokenData.csrf_token
            })
        });
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const icon = button.querySelector('i');
            const card = button.closest('.card');
            const likeBadge = card.querySelector('.badge.bg-primary');
            
            if (action === 'like') {
                icon.classList.remove('text-muted');
                icon.classList.add('text-danger');
                button.dataset.liked = '1';
            } else {
                icon.classList.remove('text-danger');
                icon.classList.add('text-muted');
                button.dataset.liked = '0';
            }
            
            // Update like count
            likeBadge.innerHTML = `<i class="fas fa-heart"></i> ${data.data.likes_count}`;
        } else {
            alert(data.error || 'Failed to update like');
        }
    })
    .catch(error => {
        console.error('Error toggling like:', error);
        alert('Failed to update like');
    });
}

function submitComment(form) {
    const imageId = form.dataset.imageId;
    const content = form.querySelector('input[name="content"]').value;
    
    if (!content.trim()) return;
    
    // Get CSRF token first
    fetch('/backend/api/csrf.php')
    .then(response => response.json())
    .then(tokenData => {
        if (!tokenData.success) {
            throw new Error('Failed to get CSRF token');
        }
        
        return fetch('/backend/api/comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                image_id: imageId,
                content: content,
                csrf_token: tokenData.csrf_token
            })
        });
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            form.querySelector('input[name="content"]').value = '';
            loadComments(imageId);
            
            // Update comment count
            const card = form.closest('.card');
            const commentBadge = card.querySelector('.badge.bg-secondary');
            commentBadge.innerHTML = `<i class="fas fa-comment"></i> ${data.data.comments_count}`;
        } else {
            alert(data.error || 'Failed to add comment');
        }
    })
    .catch(error => {
        console.error('Error submitting comment:', error);
        alert('Failed to add comment');
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include 'footer.php'; ?>
