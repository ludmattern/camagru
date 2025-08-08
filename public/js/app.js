// Camagru Application JavaScript

// Global variables
let currentUser = null;
let csrfToken = null;

// Initialize application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// App initialization
function initializeApp() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Auto-hide alerts
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.classList.contains('alert-success')) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        });
    }, 5000);
    
    // Initialize specific page functionality
    initializePageSpecific();
}

// Page-specific initialization
function initializePageSpecific() {
    const currentPage = getCurrentPage();
    
    switch(currentPage) {
        case 'gallery':
            initializeGallery();
            break;
        case 'editor':
            initializeEditor();
            break;
        case 'profile':
            initializeProfile();
            break;
    }
}

// Get current page
function getCurrentPage() {
    const path = window.location.pathname;
    if (path.includes('gallery')) return 'gallery';
    if (path.includes('editor') || path.includes('create')) return 'editor';
    if (path.includes('profile')) return 'profile';
    return 'other';
}

// Gallery functionality
function initializeGallery() {
    // Infinite scroll (bonus feature)
    if (typeof IntersectionObserver !== 'undefined') {
        setupInfiniteScroll();
    }
    
    // Image lazy loading
    setupLazyLoading();
}

// Editor functionality
function initializeEditor() {
    if (document.getElementById('camera-container')) {
        initializeCamera();
    }
    
    if (document.getElementById('filters-container')) {
        initializeFilters();
    }
}

// Profile functionality
function initializeProfile() {
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        setupProfileValidation();
    }
}

// Camera functionality
function initializeCamera() {
    const video = document.getElementById('camera-feed');
    const captureBtn = document.getElementById('capture-btn');
    const uploadInput = document.getElementById('upload-input');
    
    if (!video) return;
    
    // Try to access camera
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ 
            video: { 
                width: { ideal: 640 },
                height: { ideal: 480 },
                facingMode: 'user'
            } 
        })
        .then(function(stream) {
            video.srcObject = stream;
            video.play();
            
            // Show camera controls
            document.getElementById('camera-controls').style.display = 'block';
            document.getElementById('upload-fallback').style.display = 'none';
        })
        .catch(function(error) {
            console.log('Camera access denied or not available:', error);
            showCameraFallback();
        });
    } else {
        showCameraFallback();
    }
    
    // Capture button
    if (captureBtn) {
        captureBtn.addEventListener('click', capturePhoto);
    }
    
    // Upload input
    if (uploadInput) {
        uploadInput.addEventListener('change', handleFileUpload);
    }
}

// Show camera fallback (file upload)
function showCameraFallback() {
    document.getElementById('camera-controls').style.display = 'none';
    document.getElementById('upload-fallback').style.display = 'block';
}

// Capture photo from camera
function capturePhoto() {
    const video = document.getElementById('camera-feed');
    const canvas = document.getElementById('capture-canvas');
    const selectedFilter = document.querySelector('.filter-option.active');
    
    if (!video || !canvas) return;
    
    const context = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    // Draw video frame
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    // Apply filter if selected
    if (selectedFilter) {
        const filterImg = selectedFilter.querySelector('img');
        if (filterImg) {
            context.drawImage(filterImg, 0, 0, canvas.width, canvas.height);
        }
    }
    
    // Convert to blob and upload
    canvas.toBlob(function(blob) {
        uploadCapturedPhoto(blob);
    }, 'image/png');
}

// Handle file upload
function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validate file
    if (!file.type.startsWith('image/')) {
        showAlert('Please select an image file', 'danger');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) { // 5MB limit
        showAlert('File size must be less than 5MB', 'danger');
        return;
    }
    
    // Show preview
    const reader = new FileReader();
    reader.onload = function(e) {
        showImagePreview(e.target.result);
    };
    reader.readAsDataURL(file);
    
    // Process with selected filter
    processUploadedImage(file);
}

// Process uploaded image with filter
function processUploadedImage(file) {
    const selectedFilter = document.querySelector('.filter-option.active');
    
    const formData = new FormData();
    formData.append('image', file);
    
    if (selectedFilter) {
        formData.append('filter_id', selectedFilter.dataset.filterId);
    }
    
    showLoading('Processing image...');
    
    fetch('/api/upload.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showAlert('Image processed successfully!', 'success');
            // Redirect to gallery or show result
            setTimeout(() => {
                window.location.href = '/gallery.php';
            }, 1500);
        } else {
            showAlert(data.error || 'Failed to process image', 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Upload error:', error);
        showAlert('Failed to process image', 'danger');
    });
}

// Upload captured photo
function uploadCapturedPhoto(blob) {
    const selectedFilter = document.querySelector('.filter-option.active');
    
    const formData = new FormData();
    formData.append('image', blob, 'capture.png');
    
    if (selectedFilter) {
        formData.append('filter_id', selectedFilter.dataset.filterId);
    }
    
    showLoading('Processing photo...');
    
    fetch('/api/upload.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            showAlert('Photo captured successfully!', 'success');
            // Add to user gallery
            addToUserGallery(data.data);
        } else {
            showAlert(data.error || 'Failed to capture photo', 'danger');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Capture error:', error);
        showAlert('Failed to capture photo', 'danger');
    });
}

// Initialize filters
function initializeFilters() {
    const filterOptions = document.querySelectorAll('.filter-option');
    
    filterOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove active class from all options
            filterOptions.forEach(opt => opt.classList.remove('active'));
            
            // Add active class to clicked option
            this.classList.add('active');
            
            // Update preview
            updateFilterPreview();
        });
    });
    
    // Load filters from server
    loadFilters();
}

// Load available filters
function loadFilters() {
    fetch('/api/filters.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayFilters(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading filters:', error);
        });
}

// Display filters
function displayFilters(filters) {
    const container = document.getElementById('filters-container');
    if (!container) return;
    
    let html = '<div class="filter-option" data-filter-id="0"><span>No Filter</span></div>';
    
    filters.forEach(filter => {
        html += `
            <div class="filter-option" data-filter-id="${filter.id}">
                <img src="/filters/${filter.filename}" alt="${filter.name}" loading="lazy">
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Re-initialize filter event listeners
    initializeFilters();
}

// Update filter preview
function updateFilterPreview() {
    const selectedFilter = document.querySelector('.filter-option.active');
    const preview = document.getElementById('filter-preview');
    
    if (!preview) return;
    
    if (selectedFilter && selectedFilter.dataset.filterId !== '0') {
        const img = selectedFilter.querySelector('img');
        if (img) {
            preview.src = img.src;
            preview.style.display = 'block';
        }
    } else {
        preview.style.display = 'none';
    }
}

// Show image preview
function showImagePreview(src) {
    const preview = document.getElementById('image-preview');
    if (preview) {
        preview.src = src;
        preview.style.display = 'block';
    }
}

// Add to user gallery
function addToUserGallery(imageData) {
    const gallery = document.getElementById('user-gallery');
    if (!gallery) return;
    
    const imageHtml = `
        <div class="user-image" data-image-id="${imageData.id}">
            <img src="/uploads/${imageData.filename}" alt="User photo" loading="lazy">
            <div class="user-image-overlay">
                <div class="user-image-actions">
                    <button class="btn btn-sm btn-danger" onclick="deleteImage(${imageData.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    gallery.insertAdjacentHTML('afterbegin', imageHtml);
}

// Delete image
function deleteImage(imageId) {
    if (!confirm('Are you sure you want to delete this image?')) {
        return;
    }
    
    fetch('/api/images.php', {
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
            // Remove from DOM
            const imageElement = document.querySelector(`[data-image-id="${imageId}"]`);
            if (imageElement) {
                imageElement.remove();
            }
            showAlert('Image deleted successfully', 'success');
        } else {
            showAlert(data.error || 'Failed to delete image', 'danger');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showAlert('Failed to delete image', 'danger');
    });
}

// Infinite scroll setup
function setupInfiniteScroll() {
    const sentinel = document.createElement('div');
    sentinel.id = 'scroll-sentinel';
    document.querySelector('main').appendChild(sentinel);
    
    const observer = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting) {
            loadMoreImages();
        }
    });
    
    observer.observe(sentinel);
}

// Load more images (infinite scroll)
function loadMoreImages() {
    // Implementation for infinite scroll loading
    console.log('Loading more images...');
}

// Lazy loading setup
function setupLazyLoading() {
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
}

// Profile validation
function setupProfileValidation() {
    const form = document.getElementById('profileForm');
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirm_password');
    
    if (passwordField && confirmPasswordField) {
        function validatePasswords() {
            if (passwordField.value && confirmPasswordField.value) {
                if (passwordField.value !== confirmPasswordField.value) {
                    confirmPasswordField.setCustomValidity('Passwords do not match');
                } else {
                    confirmPasswordField.setCustomValidity('');
                }
            }
        }
        
        passwordField.addEventListener('input', validatePasswords);
        confirmPasswordField.addEventListener('input', validatePasswords);
    }
}

// Utility functions
function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('main .container');
    if (container) {
        container.insertAdjacentHTML('afterbegin', alertHtml);
        
        // Auto-hide success alerts
        if (type === 'success') {
            setTimeout(() => {
                const alert = container.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 3000);
        }
    }
}

function showLoading(message = 'Loading...') {
    const loadingHtml = `
        <div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-sm modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body text-center">
                        <div class="spinner mb-3"></div>
                        <p class="mb-0">${escapeHtml(message)}</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', loadingHtml);
    const modal = new bootstrap.Modal(document.getElementById('loadingModal'));
    modal.show();
}

function hideLoading() {
    const modal = document.getElementById('loadingModal');
    if (modal) {
        const bsModal = bootstrap.Modal.getInstance(modal);
        bsModal.hide();
        
        // Remove modal from DOM after animation
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function debounce(func, wait, immediate) {
    let timeout;
    return function executedFunction() {
        const context = this;
        const args = arguments;
        
        const later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        
        const callNow = immediate && !timeout;
        
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        
        if (callNow) func.apply(context, args);
    };
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + Enter to submit forms
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        const form = document.activeElement.closest('form');
        if (form) {
            form.submit();
        }
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        });
    }
});

// Service Worker registration (for PWA features)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function(error) {
                console.log('ServiceWorker registration failed');
            });
    });
}

// Export functions for global use
window.CamagruApp = {
    showAlert,
    showLoading,
    hideLoading,
    deleteImage,
    escapeHtml,
    formatBytes
};
