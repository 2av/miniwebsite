<?php
require_once(__DIR__ . '/../app/config/database.php');
require('header.php');

// Handle content update
if(isset($_POST['update_content'])) {
    $content_type = mysqli_real_escape_string($connect, $_POST['content_type']);
    $title = mysqli_real_escape_string($connect, $_POST['title']);
    $content = mysqli_real_escape_string($connect, $_POST['content']);
    $meta_description = mysqli_real_escape_string($connect, $_POST['meta_description']);
    $meta_keywords = mysqli_real_escape_string($connect, $_POST['meta_keywords']);
    $updated_by = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'admin';
    
    // Check if content exists
    $check_query = mysqli_query($connect, "SELECT id FROM content_management WHERE content_type='$content_type'");
    
    if(mysqli_num_rows($check_query) > 0) {
        // Update existing content
        $update_query = "UPDATE content_management SET 
                        title='$title', 
                        content='$content', 
                        meta_description='$meta_description', 
                        meta_keywords='$meta_keywords', 
                        updated_by='$updated_by',
                        last_updated=NOW()
                        WHERE content_type='$content_type'";
        
        if(mysqli_query($connect, $update_query)) {
            echo '<div class="alert alert-success">Content updated successfully!</div>';
        } else {
            echo '<div class="alert alert-danger">Error updating content: ' . mysqli_error($connect) . '</div>';
        }
    } else {
        // Insert new content
        $insert_query = "INSERT INTO content_management (content_type, title, content, meta_description, meta_keywords, updated_by) 
                        VALUES ('$content_type', '$title', '$content', '$meta_description', '$meta_keywords', '$updated_by')";
        
        if(mysqli_query($connect, $insert_query)) {
            echo '<div class="alert alert-success">Content created successfully!</div>';
        } else {
            echo '<div class="alert alert-danger">Error creating content: ' . mysqli_error($connect) . '</div>';
        }
    }
}

// Get current content
$content_types = ['terms_conditions', 'privacy_policy', 'franchisee_agreement', 'franchisee_distributer'];
$current_content = [];

foreach($content_types as $type) {
    $query = mysqli_query($connect, "SELECT * FROM content_management WHERE content_type='$type'");
    if(mysqli_num_rows($query) > 0) {
        $current_content[$type] = mysqli_fetch_array($query);
    } else {
        $current_content[$type] = [
            'title' => '',
            'content' => '',
            'meta_description' => '',
            'meta_keywords' => '',
            'last_updated' => '',
            'updated_by' => ''
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - Admin Panel</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts - Baloo Bhai 2 -->
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Baloo+Bhai+2:wght@400..800&display=swap">
    <!-- CKEditor -->
    <script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>
    <style>
        .content-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 30px;
        }
        .content-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 15px 25px;
            transition: all 0.3s ease;
        }
        .content-tabs .nav-link.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background: none;
        }
        .content-tabs .nav-link:hover {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        .content-form {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ced4da;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        .content-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .last-updated {
            font-size: 0.9rem;
            color: #6c757d;
            font-style: italic;
        }
        .content-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-terms { background: #e3f2fd; color: #1976d2; }
        .badge-privacy { background: #f3e5f5; color: #7b1fa2; }
        .badge-franchisee { background: #e8f5e8; color: #388e3c; }
        
        /* CKEditor Styling */
        .ck-editor__editable {
            min-height: 400px;
            border-radius: 8px;
            background-color: #ffffff !important;
            color: #333333 !important;
        }
        
        .ck-editor__editable:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        
        .ck.ck-editor__main > .ck-editor__editable {
            border: 1px solid #ced4da;
            border-radius: 8px;
            background-color: #ffffff !important;
            color: #333333 !important;
        }
        
        .ck.ck-editor__main > .ck-editor__editable:not(.ck-focused) {
            border-color: #ced4da;
            background-color: #ffffff !important;
            color: #333333 !important;
        }
        
        .ck.ck-editor__main > .ck-editor__editable.ck-focused {
            border-color: #007bff;
            background-color: #ffffff !important;
            color: #333333 !important;
        }
        
        .ck.ck-toolbar {
            border: 1px solid #ced4da;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            background-color: #f8f9fa;
        }
        
        .ck.ck-editor__main > .ck-editor__editable {
            border-radius: 0 0 8px 8px;
        }
        
        /* Ensure text is visible in CKEditor */
        .ck-editor__editable p,
        .ck-editor__editable div,
        .ck-editor__editable span,
        .ck-editor__editable h1,
        .ck-editor__editable h2,
        .ck-editor__editable h3,
        .ck-editor__editable h4,
        .ck-editor__editable h5,
        .ck-editor__editable h6,
        .ck-editor__editable li,
        .ck-editor__editable td,
        .ck-editor__editable th {
            color: #333333 !important;
        }
        
        /* CKEditor content styling */
        .ck-editor__editable * {
            color: inherit !important;
        }
        
        /* Override any white text */
        .ck-editor__editable {
            color: #333333 !important;
        }
        
        /* Ensure proper contrast for all text elements */
        .ck-editor__editable .ck-placeholder {
            color: #6c757d !important;
        }
        
        /* Force dark text in editor */
        .ck-editor__editable {
            color: #333333 !important;
            background-color: #ffffff !important;
            font-family: "Baloo Bhai 2", sans-serif !important;
        }
        
        /* Override any inherited styles */
        .ck-editor__editable * {
            color: #333333 !important;
            font-family: "Baloo Bhai 2", sans-serif !important;
        }
        
        /* Ensure proper text visibility */
        .ck-editor__editable:not(.ck-focused) {
            color: #333333 !important;
            background-color: #ffffff !important;
            font-family: "Baloo Bhai 2", sans-serif !important;
        }
        
        .ck-editor__editable.ck-focused {
            color: #333333 !important;
            background-color: #ffffff !important;
            font-family: "Baloo Bhai 2", sans-serif !important;
        }
        
        /* Editor container styling */
        .editor-container {
            margin-bottom: 20px;
        }
        
        .editor-container label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <div class="col-12">
                <div class="main-content p-4">
                    <!-- Page Header -->
                    <div class="page-header mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="d-flex align-items-center mb-2">
                                    <a href="index.php" class="btn btn-outline-secondary me-3">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                    </a>
                                    <h2 class="mb-0">Content Management</h2>
                                </div>
                                <p class="text-muted mb-0">Manage Terms & Conditions, Privacy Policy, and Franchisee Agreement content</p>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">Last updated: <?php echo date('M d, Y H:i'); ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- Content Tabs -->
                    <ul class="nav nav-tabs content-tabs" id="contentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="terms-tab" data-bs-toggle="tab" data-bs-target="#terms" type="button" role="tab">
                                <i class="fas fa-file-contract me-2"></i>Terms & Conditions
                                <span class="content-type-badge badge-terms ms-2">Legal</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" type="button" role="tab">
                                <i class="fas fa-shield-alt me-2"></i>Privacy Policy
                                <span class="content-type-badge badge-privacy ms-2">Privacy</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="franchisee-tab" data-bs-toggle="tab" data-bs-target="#franchisee" type="button" role="tab">
                                <i class="fas fa-handshake me-2"></i>Franchisee Agreement
                                <span class="content-type-badge badge-franchisee ms-2">Partnership</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="frandist-tab" data-bs-toggle="tab" data-bs-target="#frandist" type="button" role="tab">
                                <i class="fas fa-users-cog me-2"></i>Franchisee Distributer
                                <span class="content-type-badge badge-franchisee ms-2">Program</span>
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="contentTabsContent">
                        <!-- Terms & Conditions Tab -->
                        <div class="tab-pane fade show active" id="terms" role="tabpanel">
                            <div class="content-form">
                                <form method="POST" action="">
                                    <input type="hidden" name="content_type" value="terms_conditions">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label for="terms_title" class="form-label">Title</label>
                                            <input type="text" class="form-control" id="terms_title" name="title" 
                                                   value="<?php echo htmlspecialchars($current_content['terms_conditions']['title']); ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Last Updated</label>
                                            <div class="form-control-plaintext last-updated">
                                                <?php 
                                                if(!empty($current_content['terms_conditions']['last_updated'])) {
                                                    echo date('M d, Y H:i', strtotime($current_content['terms_conditions']['last_updated']));
                                                    echo ' by ' . $current_content['terms_conditions']['updated_by'];
                                                } else {
                                                    echo 'Never updated';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="terms_meta_description" class="form-label">Meta Description</label>
                                            <textarea class="form-control" id="terms_meta_description" name="meta_description" rows="3"><?php echo htmlspecialchars($current_content['terms_conditions']['meta_description']); ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="terms_meta_keywords" class="form-label">Meta Keywords</label>
                                            <textarea class="form-control" id="terms_meta_keywords" name="meta_keywords" rows="3"><?php echo htmlspecialchars($current_content['terms_conditions']['meta_keywords']); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="mb-3 editor-container">
                                        <label for="terms_content" class="form-label">Content</label>
                                        <textarea class="form-control" id="terms_content" name="content" rows="15"><?php echo htmlspecialchars($current_content['terms_conditions']['content']); ?></textarea>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary" onclick="previewContent('terms')">
                                            <i class="fas fa-eye me-2"></i>Preview
                                        </button>
                                        <button type="submit" name="update_content" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Terms & Conditions
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Privacy Policy Tab -->
                        <div class="tab-pane fade" id="privacy" role="tabpanel">
                            <div class="content-form">
                                <form method="POST" action="">
                                    <input type="hidden" name="content_type" value="privacy_policy">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label for="privacy_title" class="form-label">Title</label>
                                            <input type="text" class="form-control" id="privacy_title" name="title" 
                                                   value="<?php echo htmlspecialchars($current_content['privacy_policy']['title']); ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Last Updated</label>
                                            <div class="form-control-plaintext last-updated">
                                                <?php 
                                                if(!empty($current_content['privacy_policy']['last_updated'])) {
                                                    echo date('M d, Y H:i', strtotime($current_content['privacy_policy']['last_updated']));
                                                    echo ' by ' . $current_content['privacy_policy']['updated_by'];
                                                } else {
                                                    echo 'Never updated';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="privacy_meta_description" class="form-label">Meta Description</label>
                                            <textarea class="form-control" id="privacy_meta_description" name="meta_description" rows="3"><?php echo htmlspecialchars($current_content['privacy_policy']['meta_description']); ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="privacy_meta_keywords" class="form-label">Meta Keywords</label>
                                            <textarea class="form-control" id="privacy_meta_keywords" name="meta_keywords" rows="3"><?php echo htmlspecialchars($current_content['privacy_policy']['meta_keywords']); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="mb-3 editor-container">
                                        <label for="privacy_content" class="form-label">Content</label>
                                        <textarea class="form-control" id="privacy_content" name="content" rows="15"><?php echo htmlspecialchars($current_content['privacy_policy']['content']); ?></textarea>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary" onclick="previewContent('privacy')">
                                            <i class="fas fa-eye me-2"></i>Preview
                                        </button>
                                        <button type="submit" name="update_content" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Privacy Policy
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Franchisee Agreement Tab -->
                        <div class="tab-pane fade" id="franchisee" role="tabpanel">
                            <div class="content-form">
                                <form method="POST" action="">
                                    <input type="hidden" name="content_type" value="franchisee_agreement">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label for="franchisee_title" class="form-label">Title</label>
                                            <input type="text" class="form-control" id="franchisee_title" name="title" 
                                                   value="<?php echo htmlspecialchars($current_content['franchisee_agreement']['title']); ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Last Updated</label>
                                            <div class="form-control-plaintext last-updated">
                                                <?php 
                                                if(!empty($current_content['franchisee_agreement']['last_updated'])) {
                                                    echo date('M d, Y H:i', strtotime($current_content['franchisee_agreement']['last_updated']));
                                                    echo ' by ' . $current_content['franchisee_agreement']['updated_by'];
                                                } else {
                                                    echo 'Never updated';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="franchisee_meta_description" class="form-label">Meta Description</label>
                                            <textarea class="form-control" id="franchisee_meta_description" name="meta_description" rows="3"><?php echo htmlspecialchars($current_content['franchisee_agreement']['meta_description']); ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="franchisee_meta_keywords" class="form-label">Meta Keywords</label>
                                            <textarea class="form-control" id="franchisee_meta_keywords" name="meta_keywords" rows="3"><?php echo htmlspecialchars($current_content['franchisee_agreement']['meta_keywords']); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="mb-3 editor-container">
                                        <label for="franchisee_content" class="form-label">Content</label>
                                        <textarea class="form-control" id="franchisee_content" name="content" rows="15"><?php echo htmlspecialchars($current_content['franchisee_agreement']['content']); ?></textarea>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary" onclick="previewContent('franchisee')">
                                            <i class="fas fa-eye me-2"></i>Preview
                                        </button>
                                        <button type="submit" name="update_content" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Franchisee Agreement
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Franchisee Distributer Tab -->
                        <div class="tab-pane fade" id="frandist" role="tabpanel">
                            <div class="content-form">
                                <form method="POST" action="">
                                    <input type="hidden" name="content_type" value="franchisee_distributer">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label for="frandist_title" class="form-label">Title</label>
                                            <input type="text" class="form-control" id="frandist_title" name="title" 
                                                   value="<?php echo htmlspecialchars($current_content['franchisee_distributer']['title']); ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Last Updated</label>
                                            <div class="form-control-plaintext last-updated">
                                                <?php 
                                                if(!empty($current_content['franchisee_distributer']['last_updated'])) {
                                                    echo date('M d, Y H:i', strtotime($current_content['franchisee_distributer']['last_updated']));
                                                    echo ' by ' . $current_content['franchisee_distributer']['updated_by'];
                                                } else {
                                                    echo 'Never updated';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="frandist_meta_description" class="form-label">Meta Description</label>
                                            <textarea class="form-control" id="frandist_meta_description" name="meta_description" rows="3"><?php echo htmlspecialchars($current_content['franchisee_distributer']['meta_description']); ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="frandist_meta_keywords" class="form-label">Meta Keywords</label>
                                            <textarea class="form-control" id="frandist_meta_keywords" name="meta_keywords" rows="3"><?php echo htmlspecialchars($current_content['franchisee_distributer']['meta_keywords']); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="mb-3 editor-container">
                                        <label for="frandist_content" class="form-label">Content</label>
                                        <textarea class="form-control" id="frandist_content" name="content" rows="15"><?php echo htmlspecialchars($current_content['franchisee_distributer']['content']); ?></textarea>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary" onclick="previewContent('frandist')">
                                            <i class="fas fa-eye me-2"></i>Preview
                                        </button>
                                        <button type="submit" name="update_content" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Franchisee Distributer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Content Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="previewContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize CKEditor for all content textareas
        let editors = {};
        
        // Initialize editors when tabs are shown
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize editors for all tabs
            initializeEditors();
        });
        
        function initializeEditors() {
            // Terms & Conditions Editor
            if (document.getElementById('terms_content')) {
                ClassicEditor
                    .create(document.querySelector('#terms_content'), {
                        toolbar: {
                            items: [
                                'heading', '|',
                                'bold', 'italic', 'underline', '|',
                                'bulletedList', 'numberedList', '|',
                                'outdent', 'indent', '|',
                                'blockQuote', 'insertTable', '|',
                                'undo', 'redo', '|',
                                'link', '|',
                                'alignment', '|',
                                'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor'
                            ]
                        },
                        language: 'en',
                        table: {
                            contentToolbar: [
                                'tableColumn',
                                'tableRow',
                                'mergeTableCells'
                            ]
                        }
                    })
                    .then(editor => {
                        editors.terms = editor;
                        console.log('Terms editor initialized');
                    })
                    .catch(error => {
                        console.error('Terms editor initialization failed:', error);
                    });
            }
            
            // Privacy Policy Editor
            if (document.getElementById('privacy_content')) {
                ClassicEditor
                    .create(document.querySelector('#privacy_content'), {
                        toolbar: {
                            items: [
                                'heading', '|',
                                'bold', 'italic', 'underline', '|',
                                'bulletedList', 'numberedList', '|',
                                'outdent', 'indent', '|',
                                'blockQuote', 'insertTable', '|',
                                'undo', 'redo', '|',
                                'link', '|',
                                'alignment', '|',
                                'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor'
                            ]
                        },
                        language: 'en',
                        table: {
                            contentToolbar: [
                                'tableColumn',
                                'tableRow',
                                'mergeTableCells'
                            ]
                        }
                    })
                    .then(editor => {
                        editors.privacy = editor;
                        console.log('Privacy editor initialized');
                    })
                    .catch(error => {
                        console.error('Privacy editor initialization failed:', error);
                    });
            }
            
            // Franchisee Agreement Editor
            if (document.getElementById('franchisee_content')) {
                ClassicEditor
                    .create(document.querySelector('#franchisee_content'), {
                        toolbar: {
                            items: [
                                'heading', '|',
                                'bold', 'italic', 'underline', '|',
                                'bulletedList', 'numberedList', '|',
                                'outdent', 'indent', '|',
                                'blockQuote', 'insertTable', '|',
                                'undo', 'redo', '|',
                                'link', '|',
                                'alignment', '|',
                                'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor'
                            ]
                        },
                        language: 'en',
                        table: {
                            contentToolbar: [
                                'tableColumn',
                                'tableRow',
                                'mergeTableCells'
                            ]
                        }
                    })
                    .then(editor => {
                        editors.franchisee = editor;
                        console.log('Franchisee editor initialized');
                    })
                    .catch(error => {
                        console.error('Franchisee editor initialization failed:', error);
                    });
            }
            
            // Franchisee Distributer Editor
            if (document.getElementById('frandist_content')) {
                ClassicEditor
                    .create(document.querySelector('#frandist_content'), {
                        toolbar: {
                            items: [
                                'heading', '|',
                                'bold', 'italic', 'underline', '|',
                                'bulletedList', 'numberedList', '|',
                                'outdent', 'indent', '|',
                                'blockQuote', 'insertTable', '|',
                                'undo', 'redo', '|',
                                'link', '|',
                                'alignment', '|',
                                'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor'
                            ]
                        },
                        language: 'en',
                        table: {
                            contentToolbar: [
                                'tableColumn',
                                'tableRow',
                                'mergeTableCells'
                            ]
                        }
                    })
                    .then(editor => {
                        editors.frandist = editor;
                        console.log('Franchisee Distributer editor initialized');
                    })
                    .catch(error => {
                        console.error('Franchisee Distributer editor initialization failed:', error);
                    });
            }
        }

        // Preview content function
        function previewContent(type) {
            let content = '';
            let title = '';
            
            switch(type) {
                case 'terms':
                    content = editors.terms ? editors.terms.getData() : document.getElementById('terms_content').value;
                    title = document.getElementById('terms_title').value;
                    break;
                case 'privacy':
                    content = editors.privacy ? editors.privacy.getData() : document.getElementById('privacy_content').value;
                    title = document.getElementById('privacy_title').value;
                    break;
                case 'franchisee':
                    content = editors.franchisee ? editors.franchisee.getData() : document.getElementById('franchisee_content').value;
                    title = document.getElementById('franchisee_title').value;
                    break;
                case 'frandist':
                    content = editors.frandist ? editors.frandist.getData() : document.getElementById('frandist_content').value;
                    title = document.getElementById('frandist_title').value;
                    break;
            }
            
            document.getElementById('previewContent').innerHTML = 
                '<h1>' + title + '</h1>' + 
                '<div style="border-top: 1px solid #dee2e6; padding-top: 20px; margin-top: 20px;">' + 
                content + 
                '</div>';
            
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }

        // Auto-save functionality (optional)
        function autoSave(type) {
            // This could be implemented to save content automatically
            console.log('Auto-saving ' + type + ' content...');
        }

        // Form validation and data sync
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const contentField = form.querySelector('textarea[name="content"]');
                    let content = '';
                    
                    // Get content from CKEditor if available, otherwise from textarea
                    if (contentField.id === 'terms_content' && editors.terms) {
                        content = editors.terms.getData();
                    } else if (contentField.id === 'privacy_content' && editors.privacy) {
                        content = editors.privacy.getData();
                    } else if (contentField.id === 'franchisee_content' && editors.franchisee) {
                        content = editors.franchisee.getData();
                    } else if (contentField.id === 'frandist_content' && editors.frandist) {
                        content = editors.frandist.getData();
                    } else {
                        content = contentField.value;
                    }
                    
                    if (content.trim() === '') {
                        e.preventDefault();
                        alert('Please enter some content before saving.');
                        return false;
                    }
                    
                    // Update the textarea with CKEditor content before form submission
                    contentField.value = content;
                });
            });
        });
    </script>
</body>
</html>



