<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Upload with Crop & Zoom</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Cropper.js CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="image_upload_crop.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-image"></i> Image Upload with Crop & Zoom</h3>
                        <p class="mb-0 small">Upload, crop, zoom, and optimize images to 512x512</p>
                    </div>
                    <div class="card-body">
                        <!-- Upload Section -->
                        <div id="upload-section" class="upload-section">
                            <div class="upload-area" id="upload-area">
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <p class="upload-text">Click to upload or drag and drop</p>
                                <p class="upload-hint">PNG, JPG, JPEG up to 10MB</p>
                                <input type="file" id="image-input" accept="image/*" class="d-none">
                            </div>
                            <div class="text-center mt-3">
                                <button class="btn btn-primary" onclick="document.getElementById('image-input').click()">
                                    <i class="fas fa-upload"></i> Select Image
                                </button>
                            </div>
                        </div>

                        <!-- Crop Section -->
                        <div id="crop-section" class="crop-section d-none">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="crop-container">
                                        <img id="crop-image" src="" alt="Crop Image">
                                    </div>
                                    <div class="controls mt-3">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Zoom</label>
                                                <input type="range" class="form-range" id="zoom-slider" min="0" max="3" step="0.1" value="1">
                                                <div class="d-flex justify-content-between">
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="zoomOut()">
                                                        <i class="fas fa-search-minus"></i> Zoom Out
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="zoomIn()">
                                                        <i class="fas fa-search-plus"></i> Zoom In
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Rotate</label>
                                                <div class="d-flex justify-content-between">
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="rotateLeft()">
                                                        <i class="fas fa-undo"></i> Rotate Left
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="rotateRight()">
                                                        <i class="fas fa-redo"></i> Rotate Right
                                                    </button>
                                                </div>
                                                <div class="mt-2">
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="flipHorizontal()">
                                                        <i class="fas fa-arrows-alt-h"></i> Flip H
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="flipVertical()">
                                                        <i class="fas fa-arrows-alt-v"></i> Flip V
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <button class="btn btn-success" onclick="resetCrop()">
                                                    <i class="fas fa-undo"></i> Reset
                                                </button>
                                                <button class="btn btn-warning" onclick="cancelCrop()">
                                                    <i class="fas fa-times-circle"></i> Cancel
                                                </button>
                                                <button class="btn btn-primary" onclick="cropAndUpload()">
                                                    <i class="fas fa-check-circle"></i> Crop & Upload
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="preview-section">
                                        <h5>Preview (512x512)</h5>
                                        <div class="preview-container">
                                            <div id="preview-512" class="preview-box"></div>
                                        </div>
                                        <div class="image-info mt-3">
                                            <p><strong>Original Size:</strong> <span id="original-size">-</span></p>
                                            <p><strong>Cropped Size:</strong> <span id="cropped-size" class="text-primary">-</span></p>
                                            <p><strong>Dimensions:</strong> <span id="cropped-dimensions">512 x 512 px</span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Result Section -->
                        <div id="result-section" class="result-section d-none mt-4">
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle"></i> Image Uploaded Successfully!</h5>
                                <p id="result-message"></p>
                            </div>
                            <div class="text-center mt-3">
                                <img id="result-image" src="" alt="Uploaded Image" class="result-image">
                                <div class="mt-3">
                                    <button class="btn btn-primary" onclick="uploadNew()">
                                        <i class="fas fa-plus-circle"></i> Upload Another Image
                                    </button>
                                    <a id="download-link" href="#" download class="btn btn-success">
                                        <i class="fas fa-download"></i> Download Image
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Loading Overlay -->
                        <div id="loading-overlay" class="loading-overlay d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3">Processing image...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Cropper.js JS -->
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>
    <!-- Custom JS -->
    <script src="image_upload_crop.js"></script>
</body>
</html>

