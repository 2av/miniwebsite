<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automatic Image Upload - 1:1 Crop & Compression</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="image_upload_auto.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0"><i class="fas fa-magic"></i> Automatic Image Upload</h3>
                        <p class="mb-0 small">Upload any image - we'll automatically crop to 1:1, resize, and optimize it!</p>
                    </div>
                    <div class="card-body">
                        <!-- Info Banner -->
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> How It Works</h6>
                            <ul class="mb-0">
                                <li>Upload any rectangular image from your phone or computer (PNG, JPG, JPEG, GIF, or WEBP)</li>
                                <li>System automatically crops from center to perfect 1:1 square</li>
                                <li>Resizes to optimized dimensions (600x600 or 800x800)</li>
                                <li>Ensures minimum file size of 250KB for quality</li>
                                <li>Automatically adjusts quality and size to meet requirements</li>
                                <li>No manual cropping needed - it's all automatic!</li>
                            </ul>
                        </div>

                        <!-- Upload Section -->
                        <div id="upload-section" class="upload-section">
                            <div class="upload-area" id="upload-area">
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <p class="upload-text">Click to upload or drag and drop</p>
                                <p class="upload-hint">PNG, JPG, JPEG, GIF, WEBP up to 10MB</p>
                                <input type="file" id="image-input" accept="image/*" class="d-none">
                            </div>
                            <div class="text-center mt-3">
                                <button class="btn btn-success btn-lg" onclick="document.getElementById('image-input').click()">
                                    <i class="fas fa-upload"></i> Upload Image
                                </button>
                            </div>
                        </div>

                        <!-- Processing Section -->
                        <div id="processing-section" class="processing-section d-none">
                            <div class="text-center">
                                <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;">
                                    <span class="visually-hidden">Processing...</span>
                                </div>
                                <p class="mt-3"><strong>Processing your image...</strong></p>
                                <p class="text-muted">Cropping, resizing, and optimizing...</p>
                            </div>
                        </div>

                        <!-- Result Section -->
                        <div id="result-section" class="result-section d-none mt-4">
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle"></i> Image Processed Successfully!</h5>
                                <p id="result-message"></p>
                            </div>

                            <!-- Before/After Comparison -->
                            <div class="comparison-section mt-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="comparison-card">
                                            <h6 class="comparison-title">
                                                <i class="fas fa-image"></i> Original Image
                                            </h6>
                                            <div class="image-container">
                                                <img id="original-image" src="" alt="Original" class="comparison-image">
                                            </div>
                                            <div class="image-stats">
                                                <p><strong>Dimensions:</strong> <span id="original-dimensions">-</span></p>
                                                <p><strong>File Size:</strong> <span id="original-size">-</span></p>
                                                <p><strong>Format:</strong> <span id="original-format">-</span></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="comparison-card optimized">
                                            <h6 class="comparison-title">
                                                <i class="fas fa-check-circle"></i> Optimized Image (1:1)
                                            </h6>
                                            <div class="image-container">
                                                <img id="optimized-image" src="" alt="Optimized" class="comparison-image">
                                            </div>
                                            <div class="image-stats">
                                                <p><strong>Dimensions:</strong> <span id="optimized-dimensions">-</span></p>
                                                <p><strong>File Size:</strong> <span id="optimized-size">-</span></p>
                                                <p><strong>Format:</strong> <span id="optimized-format">-</span></p>
                                                <p><strong>Size Reduction:</strong> <span id="size-reduction" class="text-success">-</span></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="text-center mt-4">
                                <a id="download-link" href="#" download class="btn btn-success btn-lg">
                                    <i class="fas fa-download"></i> Download Optimized Image
                                </a>
                                <button class="btn btn-primary btn-lg" onclick="uploadNew()">
                                    <i class="fas fa-plus-circle"></i> Upload Another Image
                                </button>
                            </div>
                        </div>

                        <!-- Error Section -->
                        <div id="error-section" class="error-section d-none mt-4">
                            <div class="alert alert-danger">
                                <h5><i class="fas fa-exclamation-triangle"></i> Error</h5>
                                <p id="error-message"></p>
                            </div>
                            <div class="text-center">
                                <button class="btn btn-primary" onclick="uploadNew()">
                                    <i class="fas fa-redo"></i> Try Again
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Library Status -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6><i class="fas fa-info-circle"></i> Library Status</h6>
                        <div id="library-status" class="text-muted">
                            <i class="fas fa-spinner fa-spin"></i> Checking libraries...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="image_upload_auto.js"></script>
</body>
</html>

