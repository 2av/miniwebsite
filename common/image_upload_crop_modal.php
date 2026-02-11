<?php
/**
 * Reusable image upload & crop modal (Cropper.js).
 * Include this file where the crop UI is needed (e.g. user header, company-details).
 * Use window.ImageCropUpload.open(file, options) from image_upload_crop.js to open.
 * Options: method 'upload'|'base64', uploadUrl, hiddenField, previewSelector, spanSelector, onSuccess, onError, title.
 */
if (!defined('IMAGE_CROP_MODAL_INCLUDED')) {
    define('IMAGE_CROP_MODAL_INCLUDED', true);
?>
<style>
    /* Z-index is set dynamically via JavaScript to layer above any existing modals */
    #imageCropModal { z-index: 1041 !important; }
    #imageCropModal .modal-backdrop { z-index: 1040 !important; }
    #imageCropModal .modal-dialog { max-width: 900px; width: 95%; }
    #imageCropModal .crop-container { width: 100%; height: 380px; max-height: 380px; overflow: hidden; background: #000; display: flex; align-items: center; justify-content: center; border-radius: 5px; }
    #imageCropModal .crop-container img { max-width: 100%; max-height: 100%; display: block; }
    #imageCropModal .crop-preview-box { width: 160px; height: 160px; overflow: hidden; border: 2px solid #dee2e6; border-radius: 8px; background: #fff; margin: 0 auto; }
    #imageCropModal .crop-preview-box img { width: 100%; height: 100%; object-fit: contain; }
    
    @media (max-width: 768px) {
        #imageCropModal .modal-dialog { max-width: 98%; }
        #imageCropModal .crop-container { height: 300px; max-height: 300px; }
        #imageCropModal .crop-preview-box { width: 120px; height: 120px; }
    }
</style>
<div class="modal fade" id="imageCropModal" tabindex="-1" role="dialog" aria-labelledby="imageCropModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="background-color: #f8f9fa;">
            <div class="modal-header">
                <h5 class="modal-title text-dark" id="imageCropModalTitle" style="font-weight: 600; color: black;">Adjust & Crop Image</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="crop-container mb-3">
                                <img id="imageToCrop" src="" alt="Crop">
                            </div>
                            <div class="controls">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label small">Zoom</label>
                                        <input type="range" class="form-range" id="cropZoomSlider" min="0" max="3" step="0.1" value="1">
                                        <div class="d-flex justify-content-between mb-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cropZoomOut"><i class="fa fa-search-minus"></i> Zoom Out</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cropZoomIn"><i class="fa fa-search-plus"></i> Zoom In</button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">Rotate &amp; Flip</label>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="cropRotateLeft"><i class="fa fa-rotate-left"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="cropRotateRight"><i class="fa fa-rotate-right"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cropFlipH"><i class="fa fa-arrows-alt-h"></i> H</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cropFlipV"><i class="fa fa-arrows-alt-v"></i> V</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cropResetCrop"><i class="fa fa-refresh"></i> Reset</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light">
                                <h6 class="mb-2">Preview (512&times;512)</h6>
                                <div id="cropPreviewBox" class="crop-preview-box"></div>
                                <p class="small text-muted mt-2 mb-0"><strong>Dimensions:</strong> <span id="cropCroppedDimensions">512 &times; 512 px</span></p>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small text-center mt-2 mb-0"><i class="fa fa-info-circle"></i> Drag to adjust. Image will be saved as 512&times;512.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cropCancelBtn" data-bs-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
                <button type="button" class="btn btn-primary" id="cropAndSaveBtn"><i class="fa fa-check-circle"></i> Crop & Save</button>
            </div>
        </div>
    </div>
</div>
<?php } ?>
