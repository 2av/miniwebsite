<?php
/**
 * Reusable image upload & crop modal (Cropper.js) — uses common/mw_modal.php shell.
 * Included from user/includes/header.php. Open via ImageCropUpload.open() in image_upload_crop.js.
 */
if (!defined('IMAGE_CROP_MODAL_INCLUDED')) {
    define('IMAGE_CROP_MODAL_INCLUDED', true);
?>
<style>
    #imageCropModal.mw-modal.is-open { z-index: 1065; }
    #imageCropModal .mw-modal-panel.mw-image-crop-panel {
        max-width: min(56rem, calc(100vw - 2rem));
    }
    #imageCropModal .mw-modal-body { padding: 1rem 1.25rem; }
    #imageCropModal .crop-container {
        width: 100%;
        height: 380px;
        max-height: 50vh;
        overflow: hidden;
        background: #000;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
    }
    #imageCropModal .crop-container img {
        max-width: 100%;
        max-height: 100%;
        display: block;
    }
    #imageCropModal .crop-preview-box {
        width: 160px;
        height: 160px;
        overflow: hidden;
        border: 2px solid var(--mw-modal-border, #e2e8f0);
        border-radius: 8px;
        background: #fff;
        margin: 0 auto;
    }
    #imageCropModal .crop-preview-box img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    @media (max-width: 767.98px) {
        #imageCropModal .crop-container { height: 280px; }
        #imageCropModal .crop-preview-box { width: 120px; height: 120px; }
        #imageCropModal .mw-modal-body .row > [class*="col-"] { margin-bottom: 1rem; }
    }
</style>
<div class="mw-modal" id="imageCropModal" role="dialog" aria-modal="true" aria-labelledby="imageCropModalTitle" hidden>
    <div class="mw-modal-backdrop" data-mw-modal-close aria-hidden="true"></div>
    <div class="mw-modal-panel mw-modal-lg mw-image-crop-panel">
        <div class="mw-modal-header">
            <div class="mw-modal-header-main">
                <span class="mw-modal-header-icon" aria-hidden="true"><i class="fa fa-image"></i></span>
                <div class="mw-modal-header-text-wrap">
                    <h2 class="mw-modal-title" id="imageCropModalTitle">Adjust &amp; Crop Image</h2>
                    <p class="mw-modal-subtitle">Drag to adjust. Crop dimensions depend on image type.</p>
                </div>
            </div>
            <button type="button" class="mw-modal-close" data-mw-modal-close aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="mw-modal-body">
            <div class="container-fluid px-0">
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
                                    <div class="d-flex justify-content-between mb-2 flex-wrap gap-1">
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
                            <h6 class="mb-2" id="cropPreviewTitle">Preview</h6>
                            <div id="cropPreviewBox" class="crop-preview-box"></div>
                            <p class="small text-muted mt-2 mb-0"><strong>Dimensions:</strong> <span id="cropCroppedDimensions">—</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mw-modal-footer">
            <button type="button" class="mw-btn mw-btn-cancel" id="cropCancelBtn" data-mw-modal-close><i class="fa fa-times"></i> Cancel</button>
            <button type="button" class="mw-btn mw-btn-save" id="cropAndSaveBtn"><i class="fa fa-check-circle"></i> Crop &amp; Save</button>
        </div>
    </div>
</div>
<?php } ?>
