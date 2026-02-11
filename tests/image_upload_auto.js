// Automatic Image Upload with 1:1 Crop and Compression

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    const imageInput = document.getElementById('image-input');
    const uploadArea = document.getElementById('upload-area');
    
    // Check library status
    checkLibraryStatus();
    
    // Click to upload
    uploadArea.addEventListener('click', () => {
        imageInput.click();
    });
    
    // File input change
    imageInput.addEventListener('change', function(e) {
        if (e.target.files && e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
    });
    
    // Drag and drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            handleFileSelect(e.dataTransfer.files[0]);
        }
    });
});

// Check library status
function checkLibraryStatus() {
    fetch('image_upload_auto_handler.php?action=check_libraries')
        .then(response => response.json())
        .then(data => {
            const statusDiv = document.getElementById('library-status');
            let html = '';
            
            if (data.intervention_image) {
                html += '<span class="badge bg-success me-2"><i class="fas fa-check"></i> Intervention Image</span>';
            } else {
                html += '<span class="badge bg-warning me-2"><i class="fas fa-exclamation-triangle"></i> Intervention Image (Not Installed)</span>';
            }
            
            if (data.gd_library) {
                html += '<span class="badge bg-success me-2"><i class="fas fa-check"></i> GD Library</span>';
            } else {
                html += '<span class="badge bg-danger me-2"><i class="fas fa-times"></i> GD Library (Not Available)</span>';
            }
            
            if (data.imagick) {
                html += '<span class="badge bg-info me-2"><i class="fas fa-check"></i> ImageMagick</span>';
            }
            
            if (data.webp_support) {
                html += '<span class="badge bg-info me-2"><i class="fas fa-check"></i> WebP Support</span>';
            }
            
            statusDiv.innerHTML = html;
        })
        .catch(error => {
            document.getElementById('library-status').innerHTML = 
                '<span class="text-danger">Error checking library status</span>';
        });
}

// Handle file selection
function handleFileSelect(file) {
    // Validate file type
    if (!file.type.match('image.*')) {
        showError('Please select an image file.');
        return;
    }
    
    // Validate file size (10MB max)
    if (file.size > 10 * 1024 * 1024) {
        showError('File size exceeds 10MB limit.');
        return;
    }
    
    // Show original image preview
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('original-image').src = e.target.result;
        document.getElementById('original-size').textContent = formatFileSize(file.size);
        document.getElementById('original-format').textContent = file.type.split('/')[1].toUpperCase();
        
        // Get image dimensions
        const img = new Image();
        img.onload = function() {
            document.getElementById('original-dimensions').textContent = 
                this.width + ' x ' + this.height + ' px';
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
    
    // Upload and process
    uploadAndProcess(file);
}

// Upload and process image
function uploadAndProcess(file) {
    // Hide upload section, show processing
    document.getElementById('upload-section').classList.add('d-none');
    document.getElementById('processing-section').classList.remove('d-none');
    document.getElementById('result-section').classList.add('d-none');
    document.getElementById('error-section').classList.add('d-none');
    
    // Create form data
    const formData = new FormData();
    formData.append('image', file);
    formData.append('action', 'upload');
    
    // Upload via AJAX
    fetch('image_upload_auto_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('processing-section').classList.add('d-none');
        
        if (data.success) {
            showResult(data);
        } else {
            showError(data.message || 'Error processing image');
        }
    })
    .catch(error => {
        document.getElementById('processing-section').classList.add('d-none');
        console.error('Error:', error);
        showError('Error uploading image: ' + error.message);
    });
}

// Show result
function showResult(data) {
    // Show result section
    const resultSection = document.getElementById('result-section');
    resultSection.classList.remove('d-none');
    
    // Set optimized image
    document.getElementById('optimized-image').src = data.image_url;
    
    // Set download link
    document.getElementById('download-link').href = data.image_url;
    
    // Set dimensions
    document.getElementById('optimized-dimensions').textContent = 
        data.dimensions.width + ' x ' + data.dimensions.height + ' px';
    
    // Set file size - use the accurate size from server
    if (data.file_size) {
        // Use file_size_kb or file_size_mb if available for better accuracy
        let fileSizeBytes = data.file_size;
        
        // If server provided KB or MB, use those for display
        if (data.file_size_kb) {
            const optimizedSizeText = data.file_size_kb + ' KB';
            if (data.file_size_mb) {
                document.getElementById('optimized-size').textContent = 
                    data.file_size_mb + ' MB (' + optimizedSizeText + ')';
            } else {
                document.getElementById('optimized-size').textContent = optimizedSizeText;
            }
        } else {
            // Fallback to formatting from bytes
            const optimizedSizeText = formatFileSize(fileSizeBytes);
            document.getElementById('optimized-size').textContent = optimizedSizeText;
        }
        
        // Calculate size reduction
        if (data.original_size && data.file_size) {
            const reduction = ((data.original_size - data.file_size) / data.original_size * 100).toFixed(1);
            if (reduction > 0) {
                document.getElementById('size-reduction').textContent = reduction + '% smaller';
            } else {
                document.getElementById('size-reduction').textContent = 'Size maintained';
            }
        }
    }
    
    // Set format
    document.getElementById('optimized-format').textContent = 
        (data.format || 'JPEG').toUpperCase();
    
    // Set message
    let message = 'Your image has been automatically cropped to 1:1 ratio, ';
    message += 'resized to ' + data.dimensions.width + 'x' + data.dimensions.height + ' pixels, ';
    message += 'and optimized for fast loading.';
    document.getElementById('result-message').textContent = message;
}

// Show error
function showError(message) {
    document.getElementById('error-message').textContent = message;
    document.getElementById('error-section').classList.remove('d-none');
    document.getElementById('upload-section').classList.add('d-none');
    document.getElementById('processing-section').classList.add('d-none');
    document.getElementById('result-section').classList.add('d-none');
}

// Upload new image
function uploadNew() {
    document.getElementById('upload-section').classList.remove('d-none');
    document.getElementById('result-section').classList.add('d-none');
    document.getElementById('error-section').classList.add('d-none');
    document.getElementById('image-input').value = '';
    
    // Reset images
    document.getElementById('original-image').src = '';
    document.getElementById('optimized-image').src = '';
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

