/**
 * Common image upload with crop (Cropper.js).
 * Requires: jQuery, Bootstrap (modal), Cropper.js. Modal HTML from common/image_upload_crop_modal.php.
 *
 * Usage:
 *   ImageCropUpload.open(file, {
 *     method: 'upload',           // or 'base64'
 *     uploadUrl: '/common/upload_profile.php',
 *     uploadFieldName: 'profile_image',
 *     onSuccess: function(response) { ... },
 *     onError: function(msg) { ... },
 *     title: 'Adjust & Crop Profile Image'
 *   });
 *   // or for base64 (e.g. logo in form):
 *   ImageCropUpload.open(file, {
 *     method: 'base64',
 *     hiddenField: '#processed_logo_data',
 *     previewSelector: '#showPreviewLogo',
 *     spanSelector: '#logoPreview span',
 *     onSuccess: function() { ... },
 *     onError: function(msg) { ... },
 *     title: 'Adjust & Crop Logo'
 *   });
 */
(function() {
    'use strict';

    var cropperInstance = null;
    var currentOptions = null;
    var currentFile = null;

    function updatePreview() {
        if (!cropperInstance) return;
        var canvas = cropperInstance.getCroppedCanvas({
            width: 512,
            height: 512,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });
        if (canvas) {
            var box = document.getElementById('cropPreviewBox');
            var dim = document.getElementById('cropCroppedDimensions');
            if (box) {
                box.innerHTML = '';
                var img = document.createElement('img');
                img.src = canvas.toDataURL('image/png');
                img.style.width = img.style.height = '100%';
                img.style.objectFit = 'contain';
                box.appendChild(img);
            }
            if (dim) dim.textContent = '512 × 512 px';
        }
    }

    function updateZoomSlider() {
        if (!cropperInstance) return;
        var data = cropperInstance.getData();
        var zoom = (data && data.zoom != null) ? data.zoom : 1;
        var s = document.getElementById('cropZoomSlider');
        if (s) s.value = Math.min(Math.max(zoom, 0), 3);
    }

    function bindModalHandlers() {
        var $modal = $('#imageCropModal');
        if ($modal.data('imageCropBound')) return;
        $modal.data('imageCropBound', true);

        $modal.on('shown.bs.modal', function() {
            if (typeof Cropper === 'undefined') {
                alert('Image cropping library failed to load. Please refresh the page.');
                $modal.modal('hide');
                return;
            }
            var img = document.getElementById('imageToCrop');
            if (!img || !img.src) return;
            if (cropperInstance) {
                cropperInstance.destroy();
                cropperInstance = null;
            }
            setTimeout(function() {
                try {
                    cropperInstance = new Cropper(img, {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.8,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        zoomable: true,
                        scalable: true,
                        rotatable: true,
                        minCropBoxWidth: 100,
                        minCropBoxHeight: 100,
                        ready: function() { updatePreview(); updateZoomSlider(); },
                        crop: function() { updatePreview(); }
                    });
                    if (cropperInstance.cropper && cropperInstance.cropper.addEventListener) {
                        cropperInstance.cropper.addEventListener('zoom', function() {
                            updateZoomSlider();
                            setTimeout(updatePreview, 100);
                        });
                    }
                } catch (err) {
                    console.error(err);
                    alert('Failed to initialize cropper.');
                }
            }, 100);

            $('#cropZoomSlider').off('input').on('input', function() {
                if (cropperInstance) cropperInstance.zoomTo(parseFloat($(this).val()));
            });
            $('#cropRotateLeft').off('click').on('click', function(e) {
                e.preventDefault();
                if (cropperInstance) { cropperInstance.rotate(-90); setTimeout(updatePreview, 100); }
            });
            $('#cropRotateRight').off('click').on('click', function(e) {
                e.preventDefault();
                if (cropperInstance) { cropperInstance.rotate(90); setTimeout(updatePreview, 100); }
            });
            $('#cropZoomIn').off('click').on('click', function(e) {
                e.preventDefault();
                if (cropperInstance) { cropperInstance.zoom(0.1); updateZoomSlider(); setTimeout(updatePreview, 100); }
            });
            $('#cropZoomOut').off('click').on('click', function(e) {
                e.preventDefault();
                if (cropperInstance) { cropperInstance.zoom(-0.1); updateZoomSlider(); setTimeout(updatePreview, 100); }
            });
            $('#cropFlipH').off('click').on('click', function(e) {
                e.preventDefault();
                if (cropperInstance) {
                    var d = cropperInstance.getImageData();
                    cropperInstance.scaleX(-(d.scaleX || -1));
                    setTimeout(updatePreview, 100);
                }
            });
            $('#cropFlipV').off('click').on('click', function(e) {
                e.preventDefault();
                if (cropperInstance) {
                    var d = cropperInstance.getImageData();
                    cropperInstance.scaleY(-(d.scaleY || -1));
                    setTimeout(updatePreview, 100);
                }
            });
            $('#cropResetCrop').off('click').on('click', function(e) {
                e.preventDefault();
                if (cropperInstance) {
                    cropperInstance.reset();
                    var s = document.getElementById('cropZoomSlider');
                    if (s) s.value = 1;
                    setTimeout(updatePreview, 100);
                }
            });
        });

        $modal.on('hidden.bs.modal', function() {
            if (cropperInstance) {
                cropperInstance.destroy();
                cropperInstance = null;
            }
            $('#imageToCrop').attr('src', '');
            currentOptions = null;
            currentFile = null;
            var box = document.getElementById('cropPreviewBox');
            if (box) box.innerHTML = '';
            var s = document.getElementById('cropZoomSlider');
            if (s) s.value = 1;
            
            // Clean up backdrop to prevent blocking interactions
            setTimeout(function() {
                // Remove any orphaned modal backdrops
                var backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(function(backdrop) {
                    if(!backdrop.classList.contains('show')) {
                        backdrop.remove();
                    }
                });
            }, 100);
        });

        // Ensure Cancel and Close (X) close the modal (Bootstrap 5 uses data-bs-dismiss; fallback click)
        $('#cropCancelBtn').off('click.cropClose').on('click.cropClose', function() { $modal.modal('hide'); });
        $modal.find('[data-bs-dismiss="modal"], [data-dismiss="modal"]').off('click.cropClose').on('click.cropClose', function() { $modal.modal('hide'); });

        $('#cropAndSaveBtn').off('click').on('click', function() {
            if (!cropperInstance || !currentOptions) return;
            var canvas = cropperInstance.getCroppedCanvas({
                width: 512,
                height: 512,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });
            if (!canvas) {
                if (currentOptions.onError) currentOptions.onError('Could not crop image.');
                return;
            }

            if (currentOptions.method === 'base64') {
                var dataUrl = canvas.toDataURL('image/jpeg', 0.95);
                var base64 = dataUrl.replace(/^data:image\/\w+;base64,/, '');
                if (currentOptions.hiddenField) $(currentOptions.hiddenField).val(base64);
                if (currentOptions.previewSelector) $(currentOptions.previewSelector).attr('src', dataUrl).show();
                if (currentOptions.spanSelector) $(currentOptions.spanSelector).hide();
                $modal.modal('hide');
                if (currentOptions.onSuccess) currentOptions.onSuccess(base64);
                return;
            }

            if (currentOptions.method === 'upload' && currentOptions.uploadUrl) {
                canvas.toBlob(function(blob) {
                    if (!blob) {
                        if (currentOptions.onError) currentOptions.onError('Failed to process image.');
                        return;
                    }
                    var formData = new FormData();
                    var fieldName = currentOptions.uploadFieldName || 'image';
                    var fileName = (currentFile && currentFile.name) ? currentFile.name : 'cropped.png';
                    formData.append(fieldName, blob, fileName);

                    if (currentOptions.showLoading) {
                        var loading = $('<div class="alert alert-info" style="position:fixed;top:20px;right:20px;z-index:9999;">Uploading...</div>');
                        $('body').append(loading);
                    }

                    $.ajax({
                        url: currentOptions.uploadUrl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        timeout: currentOptions.timeout || 30000,
                        success: function(response) {
                            if (currentOptions.showLoading) loading.remove();
                            $modal.modal('hide');
                            if (currentOptions.onSuccess) currentOptions.onSuccess(response);
                        },
                        error: function(xhr, status, error) {
                            if (currentOptions.showLoading) loading.remove();
                            var msg = 'Upload failed. Please try again.';
                            try {
                                if (xhr.responseText) {
                                    var r = JSON.parse(xhr.responseText);
                                    if (r && r.message) msg = r.message;
                                }
                            } catch (e) {}
                            if (currentOptions.onError) currentOptions.onError(msg);
                            else alert(msg);
                        }
                    });
                }, 'image/png', 0.95);
            }
        });
    }

    window.ImageCropUpload = {
        open: function(file, options) {
            if (!file || !file.type || !file.type.match(/^image\//)) {
                if (options.onError) options.onError('Please select an image file.');
                else alert('Please select an image file.');
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                if (options.onError) options.onError('Image must be 10MB or less.');
                else alert('Image must be 10MB or less.');
                return;
            }
            currentFile = file;
            currentOptions = options || {};
            currentOptions.method = currentOptions.method || 'base64';

            var titleEl = document.getElementById('imageCropModalTitle');
            if (titleEl && currentOptions.title) titleEl.innerHTML = '<i class="fa fa-image me-2"></i>' + currentOptions.title;

            bindModalHandlers();

            var reader = new FileReader();
            reader.onload = function(e) {
                $('#imageToCrop').attr('src', e.target.result);
                
                    // Calculate dynamic z-index based on currently open modals
                    function applyDynamicZIndex() {
                        var maxZIndex = 1000;
                        $('.modal.show').not('#imageCropModal').each(function() {
                            var zIndex = parseInt($(this).css('z-index'), 10);
                            if (!isNaN(zIndex) && zIndex > maxZIndex) {
                                maxZIndex = zIndex;
                            }
                        });
                        
                        // Get backdrop z-index and use higher value
                        var backdropZIndex = parseInt($('.modal-backdrop.show').css('z-index'), 10);
                        if (!isNaN(backdropZIndex)) {
                            maxZIndex = Math.max(maxZIndex, backdropZIndex);
                        }
                        
                        // Set crop modal z-index to 1041 (just above 1040 limit)
                        // Set backdrop z-index to 1040 (as defined in CSS)
                        // This ensures crop modal is visible but backdrop doesn't exceed 1040
                        $('#imageCropModal').css('z-index', 1041);
                        $('body > .modal-backdrop.show').last().css('z-index', 1040);
                    }
                
                var cropModal = $('#imageCropModal');
                
                // Remove any previous event handlers to avoid duplicates
                cropModal.off('show.bs.modal');
                
                // Apply z-index when modal is about to show
                cropModal.on('show.bs.modal', function() {
                    applyDynamicZIndex();
                });
                
                // Apply z-index after modal is fully shown
                cropModal.on('shown.bs.modal', function() {
                    setTimeout(applyDynamicZIndex, 50);
                });
                
                cropModal.modal('show');
            };
            reader.readAsDataURL(file);
        }
    };
})();
