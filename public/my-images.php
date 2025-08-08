<?php
require_once '../backend/config.php';

// Require authentication
$userId = AuthMiddleware::requireAuth();
$user = getCurrentUser();

$title = 'My Photos - Camagru';
include 'header.php';

// Get user's images
$page = max(1, (int)getGet('page', 1));
$limit = 12;

try {
    $imageService = new Image();
    $images = $imageService->getUserImages($userId, $page, $limit);
    
    // Get total count for pagination
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM images WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalImages = $stmt->fetch()['count'];
    $totalPages = ceil($totalImages / $limit);
} catch (Exception $e) {
    $_SESSION['error'] = "Failed to load images";
    $images = [];
    $totalPages = 0;
    $totalImages = 0;
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><i class="fas fa-images"></i> My Photos</h1>
                <p class="text-muted mb-0">
                    Total: <?php echo $totalImages; ?> photo<?php echo $totalImages !== 1 ? 's' : ''; ?>
                </p>
            </div>
            <a href="/editor.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Photo
            </a>
        </div>
    </div>
</div>

<?php if (empty($images)): ?>
    <div class="text-center py-5">
        <i class="fas fa-camera fa-5x text-muted mb-4"></i>
        <h3 class="text-muted">No photos yet</h3>
        <p class="text-muted mb-4">Start creating amazing photos with filters and effects!</p>
        <a href="/editor.php" class="btn btn-primary btn-lg">
            <i class="fas fa-camera"></i> Create Your First Photo
        </a>
    </div>
<?php else: ?>
    <!-- Filter and Sort Options -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="search-input" placeholder="Search your photos...">
            </div>
        </div>
        <div class="col-md-6">
            <select class="form-select" id="sort-select">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="most_liked">Most Liked</option>
                <option value="most_commented">Most Commented</option>
            </select>
        </div>
    </div>
    
    <!-- Images Grid -->
    <div class="row" id="images-grid">
        <?php foreach ($images as $image): ?>
            <div class="col-sm-6 col-md-4 col-lg-3 mb-4 image-item" 
                 data-title="<?php echo strtolower(Security::escape($image['title'] ?? '')); ?>"
                 data-description="<?php echo strtolower(Security::escape($image['description'] ?? '')); ?>"
                 data-created="<?php echo $image['created_at']; ?>"
                 data-likes="<?php echo $image['likes_count']; ?>"
                 data-comments="<?php echo $image['comments_count']; ?>">
                
                <div class="card shadow-sm h-100">
                    <div class="position-relative">
                        <img src="/uploads/<?php echo Security::escape($image['filename']); ?>" 
                             class="card-img-top" 
                             alt="<?php echo Security::escape($image['title'] ?? 'My photo'); ?>"
                             style="aspect-ratio: 1; object-fit: cover; cursor: pointer;"
                             onclick="openImageModal(<?php echo $image['id']; ?>)">
                        
                        <!-- Quick Stats Overlay -->
                        <div class="position-absolute top-0 end-0 m-2">
                            <div class="d-flex flex-column gap-1">
                                <span class="badge bg-dark bg-opacity-75">
                                    <i class="fas fa-heart text-danger"></i> <?php echo $image['likes_count']; ?>
                                </span>
                                <span class="badge bg-dark bg-opacity-75">
                                    <i class="fas fa-comment text-primary"></i> <?php echo $image['comments_count']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-3">
                        <?php if ($image['title']): ?>
                            <h6 class="card-title mb-2"><?php echo Security::escape($image['title']); ?></h6>
                        <?php endif; ?>
                        
                        <?php if ($image['description']): ?>
                            <p class="card-text small text-muted mb-2">
                                <?php echo Security::escape(substr($image['description'], 0, 50)) . (strlen($image['description']) > 50 ? '...' : ''); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <?php echo timeAgo($image['created_at']); ?>
                            </small>
                            
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="editImage(<?php echo $image['id']; ?>)" 
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteImage(<?php echo $image['id']; ?>)" 
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center mb-4">
            <?php echo generatePagination($page, $totalPages, '/my-images.php'); ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Image Detail Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalTitle">Photo Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="imageModalBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editImageBtn">Edit</button>
                <button type="button" class="btn btn-danger" id="deleteImageBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Image Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editImageForm">
                <div class="modal-body">
                    <input type="hidden" id="edit-image-id">
                    
                    <div class="mb-3">
                        <label for="edit-title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="edit-title" maxlength="255">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit-description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit-description" rows="3" maxlength="1000"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit-public" checked>
                            <label class="form-check-label" for="edit-public">
                                Make this photo public
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentImageId = null;

document.addEventListener('DOMContentLoaded', function() {
    setupSearch();
    setupSort();
});

function setupSearch() {
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            filterImages();
        }, 300));
    }
}

function setupSort() {
    const sortSelect = document.getElementById('sort-select');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            sortImages(this.value);
        });
    }
}

function filterImages() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase();
    const imageItems = document.querySelectorAll('.image-item');
    
    imageItems.forEach(item => {
        const title = item.dataset.title || '';
        const description = item.dataset.description || '';
        
        if (title.includes(searchTerm) || description.includes(searchTerm)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

function sortImages(sortBy) {
    const grid = document.getElementById('images-grid');
    const items = Array.from(document.querySelectorAll('.image-item'));
    
    items.sort((a, b) => {
        switch (sortBy) {
            case 'newest':
                return new Date(b.dataset.created) - new Date(a.dataset.created);
            case 'oldest':
                return new Date(a.dataset.created) - new Date(b.dataset.created);
            case 'most_liked':
                return parseInt(b.dataset.likes) - parseInt(a.dataset.likes);
            case 'most_commented':
                return parseInt(b.dataset.comments) - parseInt(a.dataset.comments);
            default:
                return 0;
        }
    });
    
    // Reorder in DOM
    items.forEach(item => grid.appendChild(item));
}

function openImageModal(imageId) {
    currentImageId = imageId;
    
    fetch(`/backend/api/images.php?id=${imageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayImageDetails(data.data);
                
                const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                modal.show();
            } else {
                CamagruApp.showAlert('Failed to load image details', 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading image:', error);
            CamagruApp.showAlert('Failed to load image details', 'danger');
        });
}

function displayImageDetails(image) {
    const modalBody = document.getElementById('imageModalBody');
    const modalTitle = document.getElementById('imageModalTitle');
    
    modalTitle.textContent = image.title || 'Photo';
    
    modalBody.innerHTML = `
        <div class="text-center mb-3">
            <img src="/uploads/${image.filename}" class="img-fluid rounded" alt="${image.title || 'Photo'}" style="max-height: 400px;">
        </div>
        <div class="row">
            <div class="col-md-6">
                <h6>Details</h6>
                <ul class="list-unstyled">
                    <li><strong>Created:</strong> ${new Date(image.created_at).toLocaleDateString()}</li>
                    <li><strong>Size:</strong> ${image.width}x${image.height}px</li>
                    <li><strong>Filter:</strong> ${image.filter_used || 'None'}</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Engagement</h6>
                <ul class="list-unstyled">
                    <li><strong>Likes:</strong> ${image.likes_count}</li>
                    <li><strong>Comments:</strong> ${image.comments_count}</li>
                    <li><strong>Visibility:</strong> ${image.is_public ? 'Public' : 'Private'}</li>
                </ul>
            </div>
        </div>
        ${image.description ? `<div class="mt-3"><h6>Description</h6><p>${image.description}</p></div>` : ''}
    `;
    
    // Setup modal buttons
    document.getElementById('editImageBtn').onclick = () => editImage(image.id);
    document.getElementById('deleteImageBtn').onclick = () => deleteImage(image.id);
}

function editImage(imageId) {
    // Close image modal
    const imageModal = bootstrap.Modal.getInstance(document.getElementById('imageModal'));
    if (imageModal) imageModal.hide();
    
    // Load image data for editing
    fetch(`/backend/api/images.php?id=${imageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const image = data.data;
                
                document.getElementById('edit-image-id').value = image.id;
                document.getElementById('edit-title').value = image.title || '';
                document.getElementById('edit-description').value = image.description || '';
                document.getElementById('edit-public').checked = image.is_public == 1;
                
                const modal = new bootstrap.Modal(document.getElementById('editModal'));
                modal.show();
            }
        })
        .catch(error => {
            console.error('Error loading image for edit:', error);
            CamagruApp.showAlert('Failed to load image data', 'danger');
        });
}

function deleteImage(imageId) {
    if (!confirm('Are you sure you want to delete this photo? This action cannot be undone.')) {
        return;
    }
    
    fetch('/backend/api/images.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: imageId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            CamagruApp.showAlert('Photo deleted successfully', 'success');
            
            // Close modal if open
            const modal = bootstrap.Modal.getInstance(document.getElementById('imageModal'));
            if (modal) modal.hide();
            
            // Remove image from grid
            const imageItem = document.querySelector(`[onclick="openImageModal(${imageId})"]`).closest('.image-item');
            if (imageItem) {
                imageItem.remove();
            }
            
            // Reload page if no images left
            if (document.querySelectorAll('.image-item').length === 0) {
                location.reload();
            }
        } else {
            CamagruApp.showAlert(data.error || 'Failed to delete photo', 'danger');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        CamagruApp.showAlert('Failed to delete photo', 'danger');
    });
}

// Handle edit form submission
document.getElementById('editImageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const imageId = document.getElementById('edit-image-id').value;
    const title = document.getElementById('edit-title').value;
    const description = document.getElementById('edit-description').value;
    const isPublic = document.getElementById('edit-public').checked;
    
    fetch('/backend/api/images.php', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            id: imageId,
            title: title,
            description: description,
            is_public: isPublic
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            CamagruApp.showAlert('Photo updated successfully', 'success');
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
            modal.hide();
            
            // Refresh the page to show updated data
            setTimeout(() => location.reload(), 1000);
        } else {
            CamagruApp.showAlert(data.error || 'Failed to update photo', 'danger');
        }
    })
    .catch(error => {
        console.error('Update error:', error);
        CamagruApp.showAlert('Failed to update photo', 'danger');
    });
});

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script>

<?php include 'footer.php'; ?>
