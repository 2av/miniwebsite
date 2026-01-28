
$(document).ready(function () {
    $(".upload-profile span").click(function (e) {
        e.preventDefault(); // Prevent default action if inside an <a> tag
        $(this).closest(".nav-item").find(".dropdown-menu").slideToggle(); // Toggle dropdown
    });

    // Optional: Close dropdown when clicking outside
    $(document).click(function (event) {
        if (!$(event.target).closest(".nav-item").length) {
            $(".dropdown-menu").slideUp();
        }
    });
});





// Remove automatic preview update - only update on successful upload
// $(".file-upload").on('change', function () {
//     readURL(this);
// });

$(".upload-button").on('click', function () {
    $("#profile_image").click();
});

window.addEventListener('DOMContentLoaded', event => {
    const sidebarToggle = document.body.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', event => {
            event.preventDefault();
            
            document.body.classList.toggle('sb-sidenav-toggled');
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
        });
    }

});

// Profile image upload with cropping modal
var cropper = null;
var currentFile = null;

$("#profile_image").on('change', function() {
    if(this.files && this.files[0]) {
        var file = this.files[0];
        var maxSize = 10 * 1024 * 1024; // 10MB
        var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        
        // Validate file size
        if(file.size > maxSize) {
            alert('Image size must be 10MB or less.');
            this.value = '';
            return false;
        }
        
        // Validate file type
        if(!allowedTypes.includes(file.type)) {
            alert('Only PNG, JPG, JPEG, GIF, or WEBP files are allowed.');
            this.value = '';
            return false;
        }
        
        // Store file for later use
        currentFile = file;
        
        // Read file and show in modal
        var reader = new FileReader();
        reader.onload = function(e) {
            // Destroy previous cropper instance if exists
            if(cropper) {
                cropper.destroy();
                cropper = null;
            }
            
            // Set image source and show modal
            $('#imageToCrop').attr('src', e.target.result);
            $('#profileImageCropModal').modal('show');
        };
        reader.readAsDataURL(file);
    }
});

// Initialize Cropper.js when modal is shown
$('#profileImageCropModal').on('shown.bs.modal', function() {
    // Check if Cropper is available
    if (typeof Cropper === 'undefined') {
        console.error('Cropper.js library is not loaded. Please refresh the page.');
        alert('Image cropping library failed to load. Please refresh the page and try again.');
        $('#profileImageCropModal').modal('hide');
        return;
    }
    
    var image = document.getElementById('imageToCrop');
    if (!image || !image.src) {
        console.error('Image element not found or has no source');
        return;
    }
    
    if(cropper) {
        cropper.destroy();
        cropper = null;
    }
    
    // Wait a tiny bit to ensure image is rendered
    setTimeout(function() {
        try {
            cropper = new Cropper(image, {
                aspectRatio: 1, // 1:1 ratio for profile picture
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.8,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
                responsive: true,
                ready: function() {
                    // Image is ready for cropping
                    console.log('Cropper initialized successfully');
                }
            });
        } catch (error) {
            console.error('Error initializing Cropper:', error);
            alert('Failed to initialize image cropper. Please try again.');
        }
    }, 100);
    
    // Attach button handlers when modal is shown
    // Rotate Left
    $('#rotateLeft').off('click').on('click', function(e) {
        e.preventDefault();
        if(cropper) {
            cropper.rotate(-90);
        }
    });

    // Rotate Right
    $('#rotateRight').off('click').on('click', function(e) {
        e.preventDefault();
        if(cropper) {
            cropper.rotate(90);
        }
    });

    // Zoom In
    $('#zoomIn').off('click').on('click', function(e) {
        e.preventDefault();
        if(cropper) {
            cropper.zoom(0.1);
        }
    });

    // Zoom Out
    $('#zoomOut').off('click').on('click', function(e) {
        e.preventDefault();
        if(cropper) {
            cropper.zoom(-0.1);
        }
    });

    // Reset
    $('#resetCrop').off('click').on('click', function(e) {
        e.preventDefault();
        if(cropper) {
            cropper.reset();
        }
    });
});

// Destroy cropper when modal is hidden
$('#profileImageCropModal').on('hidden.bs.modal', function() {
    if(cropper) {
        cropper.destroy();
        cropper = null;
    }
    $('#imageToCrop').attr('src', '');
    $('#profile_image').val('');
    currentFile = null;
});

// Upload cropped image
$('#uploadCroppedImage').on('click', function() {
    if(!cropper || !currentFile) {
        alert('Please select an image first.');
        return;
    }
    
    // Get cropped canvas
    var canvas = cropper.getCroppedCanvas({
        width: 600,
        height: 600,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high'
    });
    
    if(!canvas) {
        alert('Could not get cropped image. Please try again.');
        return;
    }
    
    // Convert canvas to blob
    canvas.toBlob(function(blob) {
        if(!blob) {
            alert('Failed to process image. Please try again.');
            return;
        }
        
        // Show loading
        var loadingMessage = $('<div class="alert alert-info" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">Processing and uploading image...</div>');
        $('body').append(loadingMessage);
        
        // Create FormData with cropped image
        var formData = new FormData();
        formData.append('profile_image', blob, currentFile.name);
        
        // Detect upload URL - use absolute URL based on current location
        var currentOrigin = window.location.origin;
        var uploadUrl;
        var basePath = '';
        
        // Use global variable from header.php if available (most reliable)
        if (typeof UPLOAD_PROFILE_URL !== 'undefined' && UPLOAD_PROFILE_URL) {
            // If it's already a full URL, use it directly
            if (UPLOAD_PROFILE_URL.indexOf('http') === 0) {
                uploadUrl = UPLOAD_PROFILE_URL;
            } else {
                // Otherwise, build absolute URL
                uploadUrl = currentOrigin + UPLOAD_PROFILE_URL;
            }
            basePath = (typeof APP_BASE_PATH !== 'undefined') ? APP_BASE_PATH : '';
        } else {
            // Fallback: detect base path from current location
            var currentPath = window.location.pathname;
            // Extract the base path (everything before /user/) - similar to PHP get_assets_base_path()
            // Remove /user and everything after it to get base path
            basePath = currentPath.replace(/\/user(\/.*)?$/, '');
            
            // Normalize: if it's just '/', use empty string; otherwise use as is
            if (basePath === '/' || basePath === '') {
                basePath = '';
            }
            
            // Build absolute URL
            uploadUrl = currentOrigin + basePath + '/common/upload_profile.php';
        }
        
        // Debug: Log the upload URL
        console.log('Upload URL:', uploadUrl);
        console.log('Base path:', basePath);
        console.log('Current origin:', currentOrigin);
        
        // Upload
        $.ajax({
            url: uploadUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                loadingMessage.remove();
                console.log('Upload response:', response);
                if(response && response.status === 'success') {
                    alert('Profile image updated successfully!');
                    // Update profile picture in UI
                    // Image is stored in assets/upload/profile_image/
                    // Build absolute path to assets/upload/profile_image/
                    var newImageSrc = currentOrigin + basePath + '/' + response.image_path + '?t=' + new Date().getTime();
                    $('.profile-pic').attr('src', newImageSrc);
                    // Close modal
                    $('#profileImageCropModal').modal('hide');
                } else {
                    var errorMsg = response && response.message ? response.message : 'Failed to upload image';
                    // Strip HTML tags from error message for alert
                    var textMsg = $('<div>').html(errorMsg).text();
                    console.error('Upload failed:', errorMsg);
                    alert('Error: ' + textMsg);
                }
            },
            error: function(xhr, status, error) {
                loadingMessage.remove();
                console.error('Upload error:', {
                    status: status,
                    error: error,
                    statusCode: xhr.status,
                    responseText: xhr.responseText
                });
                
                var errorMsg = 'Upload failed. Please try again.';
                try {
                    if(xhr.responseText) {
                        var response = JSON.parse(xhr.responseText);
                        if(response && response.message) {
                            errorMsg = response.message;
                        } else if(xhr.status === 404) {
                            errorMsg = 'Upload endpoint not found. Please check the URL: ' + uploadUrl;
                        } else if(xhr.status === 500) {
                            errorMsg = 'Server error. Please try again later.';
                        } else if(xhr.status === 0) {
                            errorMsg = 'Network error. Please check your connection.';
                        }
                    }
                } catch(e) {
                    console.error('Error parsing response:', e);
                    if(xhr.status === 404) {
                        errorMsg = 'Upload endpoint not found. Please check the URL: ' + uploadUrl;
                    }
                }
                // Strip HTML tags from error message for alert
                var textMsg = $('<div>').html(errorMsg).text();
                alert(textMsg);
            }
        });
    }, 'image/jpeg', 0.9); // JPEG quality 0.9
});










