<?php
require_once '../backend/config.php';

// Require authentication
$userId = AuthMiddleware::requireAuth();

$title = 'Photo Editor - Camagru';
include 'header.php';
?>

<div class="editor-container">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-camera"></i> Create Photo</h1>
            <p class="text-muted">Take a photo with your webcam or upload an image, then apply filters to create amazing content!</p>
        </div>
    </div>
    
    <div class="row">
        <!-- Camera/Upload Section -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5><i class="fas fa-video"></i> Camera / Upload</h5>
                </div>
                <div class="card-body">
                    <!-- Camera Container -->
                    <div id="camera-container" class="camera-container mb-3">
                        <video id="camera-feed" class="camera-feed" autoplay muted playsinline></video>
                        <canvas id="capture-canvas" style="display: none;"></canvas>
                        <div class="camera-overlay">
                            <canvas id="filter-preview" class="filter-preview" style="display: none;"></canvas>
                        </div>
                    </div>
                    
                    <!-- Camera Controls -->
                    <div id="camera-controls" class="text-center" style="display: none;">
                        <button id="capture-btn" class="btn btn-primary btn-lg me-2" disabled>
                            <i class="fas fa-camera"></i> Capture Photo
                        </button>
                        <button id="switch-camera-btn" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt"></i> Switch Camera
                        </button>
                    </div>
                    
                    <!-- Upload Fallback -->
                    <div id="upload-fallback" class="text-center" style="display: none;">
                        <div class="border-dashed p-4 rounded">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <h5>Camera not available</h5>
                            <p class="text-muted mb-3">Upload an image instead</p>
                            <input type="file" id="upload-input" accept="image/*" class="form-control">
                        </div>
                    </div>
                    
                    <!-- Image Preview -->
                    <div id="preview-container" class="mt-3" style="display: none;">
                        <img id="image-preview" class="img-fluid rounded" alt="Preview">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters Section -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5><i class="fas fa-palette"></i> Filters & Effects</h5>
                </div>
                <div class="card-body">
                    <div id="filters-container" class="filters-grid">
                        <!-- Filters will be loaded here -->
                        <div class="d-flex justify-content-center">
                            <div class="spinner"></div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label for="photo-title" class="form-label">Photo Title (Optional)</label>
                        <input type="text" id="photo-title" class="form-control" placeholder="Give your photo a title...">
                    </div>
                    
                    <div class="mt-3">
                        <label for="photo-description" class="form-label">Description (Optional)</label>
                        <textarea id="photo-description" class="form-control" rows="3" placeholder="Describe your photo..."></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Gallery -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-images"></i> My Photos</h5>
                    <span class="badge bg-primary" id="photos-count">0</span>
                </div>
                <div class="card-body">
                    <div id="user-gallery" class="user-gallery">
                        <!-- User photos will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Processing Modal -->
<div class="modal fade" id="processingModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="spinner mb-3"></div>
                <h5>Processing your photo...</h5>
                <p class="text-muted mb-0">Please wait while we apply the filters and save your photo.</p>
            </div>
        </div>
    </div>
</div>

<style>
.border-dashed {
    border: 2px dashed #dee2e6 !important;
}

.camera-container {
    position: relative;
    background: #000;
    border-radius: 0.375rem;
    overflow: hidden;
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.camera-feed {
    width: 100%;
    height: auto;
    max-height: 400px;
    object-fit: contain;
}

.camera-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.filter-preview {
    width: 100%;
    height: 100%;
    object-fit: contain;
    opacity: 0.8;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 0.75rem;
}

.filter-option {
    aspect-ratio: 1;
    border: 2px solid #dee2e6;
    border-radius: 0.375rem;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s ease;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.filter-option:hover {
    border-color: var(--bs-primary);
    transform: scale(1.05);
}

.filter-option.active {
    border-color: var(--bs-primary);
    background-color: rgba(13, 110, 253, 0.1);
}

.filter-option img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.filter-option span {
    font-size: 0.75rem;
    font-weight: 500;
    text-align: center;
    padding: 0.25rem;
}

.user-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
}

.user-image {
    position: relative;
    aspect-ratio: 1;
    border-radius: 0.375rem;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s ease;
}

.user-image:hover {
    transform: scale(1.02);
}

.user-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.user-image:hover .user-image-overlay {
    opacity: 1;
}

@media (max-width: 768px) {
    .filters-grid {
        grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
        gap: 0.5rem;
    }
    
    .user-gallery {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 0.75rem;
    }
}
</style>

<script>
let currentStream = null;
let selectedFilter = null;
let facingMode = 'user'; // 'user' for front camera, 'environment' for back camera

document.addEventListener('DOMContentLoaded', function() {
    initializeEditor();
});

function initializeEditor() {
    loadFilters();
    loadUserGallery();
    initializeCamera();
    setupEventListeners();
}

function setupEventListeners() {
    const captureBtn = document.getElementById('capture-btn');
    const uploadInput = document.getElementById('upload-input');
    const switchCameraBtn = document.getElementById('switch-camera-btn');
    
    if (captureBtn) {
        captureBtn.addEventListener('click', capturePhoto);
    }
    
    if (uploadInput) {
        uploadInput.addEventListener('change', handleFileUpload);
    }
    
    if (switchCameraBtn) {
        switchCameraBtn.addEventListener('click', switchCamera);
    }
}

function initializeCamera() {
    const video = document.getElementById('camera-feed');
    
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showCameraFallback();
        return;
    }
    
    startCamera();
}

function startCamera() {
    const video = document.getElementById('camera-feed');
    const constraints = {
        video: {
            width: { ideal: 640 },
            height: { ideal: 480 },
            facingMode: facingMode
        }
    };
    
    navigator.mediaDevices.getUserMedia(constraints)
        .then(function(stream) {
            currentStream = stream;
            video.srcObject = stream;
            video.play();
            
            // Show camera controls
            document.getElementById('camera-controls').style.display = 'block';
            document.getElementById('upload-fallback').style.display = 'none';
            
            // Enable capture button when a filter is selected
            updateCaptureButton();
        })
        .catch(function(error) {
            console.log('Camera access denied or not available:', error);
            showCameraFallback();
        });
}

function switchCamera() {
    // Stop current stream
    if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
    }
    
    // Switch facing mode
    facingMode = facingMode === 'user' ? 'environment' : 'user';
    
    // Restart camera
    startCamera();
}

function showCameraFallback() {
    document.getElementById('camera-controls').style.display = 'none';
    document.getElementById('upload-fallback').style.display = 'block';
}

function loadFilters() {
    fetch('/backend/api/filters.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayFilters(data.data);
            } else {
                console.error('Failed to load filters:', data.error);
            }
        })
        .catch(error => {
            console.error('Error loading filters:', error);
        });
}

function displayFilters(filters) {
    const container = document.getElementById('filters-container');
    let html = '<div class="filter-option active" data-filter-id="0"><span>No Filter</span></div>';
    
    filters.forEach(filter => {
        html += `
            <div class="filter-option" data-filter-id="${filter.id}" title="${filter.name}">
                <img src="/filters/${filter.filename}" alt="${filter.name}" loading="lazy">
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Add event listeners
    container.querySelectorAll('.filter-option').forEach(option => {
        option.addEventListener('click', function() {
            selectFilter(this);
        });
    });
}

function selectFilter(element) {
    // Remove active class from all filters
    document.querySelectorAll('.filter-option').forEach(opt => {
        opt.classList.remove('active');
    });
    
    // Add active class to selected filter
    element.classList.add('active');
    selectedFilter = element.dataset.filterId;
    
    // Update capture button
    updateCaptureButton();
    
    // Update preview
    updateFilterPreview();
}

function updateCaptureButton() {
    const captureBtn = document.getElementById('capture-btn');
    const cameraWorking = document.getElementById('camera-controls').style.display !== 'none';
    
    if (captureBtn && cameraWorking) {
        captureBtn.disabled = selectedFilter === null;
    }
}

function updateFilterPreview() {
    // This would implement live preview of the filter on the camera feed
    // For now, we'll keep it simple and apply filters during capture
}

function capturePhoto() {
    const video = document.getElementById('camera-feed');
    const canvas = document.getElementById('capture-canvas');
    
    if (!video || !canvas) return;
    
    const context = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    // Draw video frame
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    // Convert to blob and upload
    canvas.toBlob(function(blob) {
        uploadPhoto(blob, true);
    }, 'image/png');
}

function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validate file
    if (!file.type.startsWith('image/')) {
        CamagruApp.showAlert('Please select an image file', 'danger');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) { // 5MB limit
        CamagruApp.showAlert('File size must be less than 5MB', 'danger');
        return;
    }
    
    // Show preview
    const reader = new FileReader();
    reader.onload = function(e) {
        showImagePreview(e.target.result);
    };
    reader.readAsDataURL(file);
    
    // Upload file
    uploadPhoto(file, false);
}

function showImagePreview(src) {
    const preview = document.getElementById('image-preview');
    const container = document.getElementById('preview-container');
    
    if (preview && container) {
        preview.src = src;
        container.style.display = 'block';
    }
}

function uploadPhoto(file, isCapture = false) {
    const formData = new FormData();
    formData.append('image', file);
    
    if (selectedFilter && selectedFilter !== '0') {
        formData.append('filter_id', selectedFilter);
    }
    
    const title = document.getElementById('photo-title').value;
    const description = document.getElementById('photo-description').value;
    
    if (title) formData.append('title', title);
    if (description) formData.append('description', description);
    
    // Show processing modal
    const modal = new bootstrap.Modal(document.getElementById('processingModal'));
    modal.show();
    
    fetch('/backend/api/upload.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        modal.hide();
        
        if (data.success) {
            CamagruApp.showAlert(isCapture ? 'Photo captured successfully!' : 'Photo uploaded successfully!', 'success');
            
            // Clear form
            document.getElementById('photo-title').value = '';
            document.getElementById('photo-description').value = '';
            document.getElementById('upload-input').value = '';
            
            // Hide preview
            document.getElementById('preview-container').style.display = 'none';
            
            // Reload user gallery
            loadUserGallery();
            
        } else {
            CamagruApp.showAlert(data.error || 'Failed to process photo', 'danger');
        }
    })
    .catch(error => {
        modal.hide();
        console.error('Upload error:', error);
        CamagruApp.showAlert('Failed to process photo', 'danger');
    });
}

function loadUserGallery() {
    fetch('/backend/api/images.php?user_images=1')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayUserGallery(data.data);
                updatePhotosCount(data.data.length);
            }
        })
        .catch(error => {
            console.error('Error loading user gallery:', error);
        });
}

function displayUserGallery(images) {
    const gallery = document.getElementById('user-gallery');
    
    if (images.length === 0) {
        gallery.innerHTML = `
            <div class="col-12 text-center p-4">
                <i class="fas fa-images fa-3x text-muted mb-3"></i>
                <p class="text-muted">No photos yet. Capture your first photo!</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    images.forEach(image => {
        html += `
            <div class="user-image" data-image-id="${image.id}">
                <img src="/uploads/${image.filename}" alt="My photo" loading="lazy">
                <div class="user-image-overlay">
                    <div class="user-image-actions">
                        <button class="btn btn-sm btn-danger" onclick="deleteUserImage(${image.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    gallery.innerHTML = html;
}

function updatePhotosCount(count) {
    const badge = document.getElementById('photos-count');
    if (badge) {
        badge.textContent = count;
    }
}

function deleteUserImage(imageId) {
    if (!confirm('Are you sure you want to delete this photo?')) {
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
            loadUserGallery();
        } else {
            CamagruApp.showAlert(data.error || 'Failed to delete photo', 'danger');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        CamagruApp.showAlert('Failed to delete photo', 'danger');
    });
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
    }
});
</script>

<?php include 'footer.php'; ?>
