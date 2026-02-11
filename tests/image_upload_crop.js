// Image Upload with Crop and Zoom functionality

let cropper = null;
let originalFile = null;

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    const imageInput = document.getElementById('image-input');
    const uploadArea = document.getElementById('upload-area');
    
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
    
    // Zoom slider
    const zoomSlider = document.getElementById('zoom-slider');
    zoomSlider.addEventListener('input', function(e) {
        if (cropper) {
            cropper.zoomTo(parseFloat(e.target.value));
        }
    });
});

// Handle file selection
function handleFileSelect(file) {
    // Validate file type
    if (!file.type.match('image.*')) {
        alert('Please select an image file.');
        return;
    }
    
    // Validate file size (10MB max)
    if (file.size > 10 * 1024 * 1024) {
        alert('File size exceeds 10MB limit.');
        return;
    }
    
    originalFile = file;
    
    // Show original file size
    const originalSizeText = formatFileSize(file.size);
    document.getElementById('original-size').textContent = originalSizeText;
    
    // Read file and show in cropper
    const reader = new FileReader();
    reader.onload = function(e) {
        const imageUrl = e.target.result;
        showCropSection(imageUrl);
    };
    reader.readAsDataURL(file);
}

// Show crop section
function showCropSection(imageUrl) {
    // Hide upload section
    document.getElementById('upload-section').classList.add('d-none');
    
    // Show crop section
    const cropSection = document.getElementById('crop-section');
    cropSection.classList.remove('d-none');
    
    // Set image source
    const cropImage = document.getElementById('crop-image');
    cropImage.src = imageUrl;
    
    // Initialize cropper
    if (cropper) {
        cropper.destroy();
    }
    
    cropper = new Cropper(cropImage, {
        aspectRatio: 1, // Square aspect ratio for 512x512
        viewMode: 1, // Restrict crop box within canvas
        dragMode: 'move',
        autoCropArea: 0.8,
        restore: false,
        guides: true,
        center: true,
        highlight: false,
        cropBoxMovable: true,
        cropBoxResizable: true,
        toggleDragModeOnDblclick: false,
        zoomable: true,
        scalable: true,
        rotatable: true,
        minCropBoxWidth: 100,
        minCropBoxHeight: 100,
        ready: function() {
            // Update preview when crop box changes
            updatePreview();
        },
        crop: function() {
            updatePreview();
        }
    });
    
    // Update zoom slider when cropper zoom changes
    cropper.cropper.addEventListener('zoom', function() {
        const zoom = cropper.getData().zoom || 1;
        document.getElementById('zoom-slider').value = Math.min(Math.max(zoom, 0), 3);
        // Update preview and size when zooming
        setTimeout(updatePreview, 100);
    });
}

// Update preview
function updatePreview() {
    if (!cropper) return;
    
    const canvas = cropper.getCroppedCanvas({
        width: 512,
        height: 512,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high'
    });
    
    if (canvas) {
        const previewBox = document.getElementById('preview-512');
        previewBox.innerHTML = '';
        const img = document.createElement('img');
        
        // Convert canvas to blob to get file size
        canvas.toBlob(function(blob) {
            if (blob) {
                const croppedSize = formatFileSize(blob.size);
                document.getElementById('cropped-size').textContent = croppedSize;
                
                // Update dimensions - always 512x512 for output
                document.getElementById('cropped-dimensions').textContent = '512 x 512 px';
            }
        }, 'image/png', 0.95);
        
        img.src = canvas.toDataURL('image/png');
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'contain';
        previewBox.appendChild(img);
    }
}

// Zoom functions
function zoomIn() {
    if (cropper) {
        cropper.zoom(0.1);
        updateZoomSlider();
        setTimeout(updatePreview, 100);
    }
}

function zoomOut() {
    if (cropper) {
        cropper.zoom(-0.1);
        updateZoomSlider();
        setTimeout(updatePreview, 100);
    }
}

function updateZoomSlider() {
    if (cropper) {
        const zoom = cropper.getData().zoom || 1;
        document.getElementById('zoom-slider').value = Math.min(Math.max(zoom, 0), 3);
    }
}

// Rotate functions
function rotateLeft() {
    if (cropper) {
        cropper.rotate(-90);
        setTimeout(updatePreview, 100);
    }
}

function rotateRight() {
    if (cropper) {
        cropper.rotate(90);
        setTimeout(updatePreview, 100);
    }
}

// Flip functions
function flipHorizontal() {
    if (cropper) {
        const imageData = cropper.getImageData();
        cropper.scaleX(-imageData.scaleX || -1);
        setTimeout(updatePreview, 100);
    }
}

function flipVertical() {
    if (cropper) {
        const imageData = cropper.getImageData();
        cropper.scaleY(-imageData.scaleY || -1);
        setTimeout(updatePreview, 100);
    }
}

// Reset crop
function resetCrop() {
    if (cropper) {
        cropper.reset();
        document.getElementById('zoom-slider').value = 1;
    }
}

// Cancel crop
function cancelCrop() {
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
    
    document.getElementById('upload-section').classList.remove('d-none');
    document.getElementById('crop-section').classList.add('d-none');
    document.getElementById('result-section').classList.add('d-none');
    document.getElementById('image-input').value = '';
    originalFile = null;
}

// Crop and upload
function cropAndUpload() {
    if (!cropper) {
        alert('Please select an image first.');
        return;
    }
    
    showLoading();
    
    // Get cropped canvas at 512x512
    const canvas = cropper.getCroppedCanvas({
        width: 512,
        height: 512,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high'
    });
    
    if (!canvas) {
        hideLoading();
        alert('Error creating cropped image.');
        return;
    }
    
    // Convert canvas to blob
    canvas.toBlob(function(blob) {
        if (!blob) {
            hideLoading();
            alert('Error processing image.');
            return;
        }
        
        // Create form data
        const formData = new FormData();
        formData.append('image', blob, 'cropped_image.png');
        formData.append('action', 'upload');
        
        // Upload via AJAX
        fetch('image_upload_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                showResult(data);
            } else {
                alert('Error: ' + (data.message || 'Upload failed'));
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            alert('Error uploading image: ' + error.message);
        });
    }, 'image/png', 0.95);
}

// Show result
function showResult(data) {
    // Hide crop section
    document.getElementById('crop-section').classList.add('d-none');
    
    // Show result section
    const resultSection = document.getElementById('result-section');
    resultSection.classList.remove('d-none');
    
    // Set result image
    document.getElementById('result-image').src = data.image_url;
    
    // Set download link
    document.getElementById('download-link').href = data.image_url;
    
    // Set message
    const message = `Image optimized to 512x512 pixels. `;
    document.getElementById('result-message').textContent = message;
    
    // Show optimized size in result section
    if (data.file_size) {
        const optimizedSizeText = formatFileSize(data.file_size);
        const resultMessage = document.getElementById('result-message');
        resultMessage.innerHTML = `Image optimized to 512x512 pixels.<br><strong>File Size:</strong> ${optimizedSizeText}`;
    }
}

// Upload new image
function uploadNew() {
    document.getElementById('result-section').classList.add('d-none');
    document.getElementById('upload-section').classList.remove('d-none');
    document.getElementById('image-input').value = '';
    originalFile = null;
    
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
}

// Show loading
function showLoading() {
    document.getElementById('loading-overlay').classList.remove('d-none');
}

// Hide loading
function hideLoading() {
    document.getElementById('loading-overlay').classList.add('d-none');
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

