<?php
// Check if this is an AJAX request FIRST - before any output
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Start output buffering for AJAX requests immediately to prevent any output
if($is_ajax && isset($_POST['process5'])) {
    ob_start();
    // Suppress warnings/notices for AJAX requests
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
}

// Handle card_number from URL, session, or cookie
// MUST be done before any output (before including header.php)
require_once('../../common/config.php');

// Handle AJAX image processing FIRST - before any other output
// This must be at the very top to prevent any output before JSON response
if(isset($_POST['process_gallery_image_ajax']) && !empty($_FILES['gallery_image']['tmp_name'])){
    // Suppress any output and set JSON header
    while(ob_get_level() > 0) {
        ob_end_clean();
    }
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    try {
        // Include file validation functions
        $file_validation_path = __DIR__ . '/../../includes/file_validation.php';
        if(file_exists($file_validation_path)) {
            require_once $file_validation_path;
        } elseif(file_exists('../../includes/file_validation.php')) {
            require_once '../../includes/file_validation.php';
        } elseif(file_exists('../includes/file_validation.php')) {
            require_once '../includes/file_validation.php';
        } elseif(file_exists('includes/file_validation.php')) {
            require_once 'includes/file_validation.php';
        } else {
            throw new Exception('File validation library not found');
        }
        
        if(!function_exists('processImageUploadWithAutoCrop')) {
            throw new Exception('Image processing function not available');
        }
        
        // Check file upload error
        if($_FILES['gallery_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $_FILES['gallery_image']['error']);
        }
        
        $result = processImageUploadWithAutoCrop(
            $_FILES['gallery_image'], 
            600,      // Target size: 600x600
            250000,   // Target file size: 250KB
            200000,   // Min file size: 200KB
            300000,   // Max file size: 300KB
            ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
            'jpeg',
            null
        );
        
        if($result['status']) {
            // Return processed image as base64 for immediate preview
            $base64Image = base64_encode($result['data']);
            
            echo json_encode([
                'success' => true,
                'image_data' => $base64Image,
                'dimensions' => isset($result['dimensions']) ? $result['dimensions'] : ['width' => 600, 'height' => 600],
                'file_size' => isset($result['file_size']) ? $result['file_size'] : 0,
                'message' => 'Image processed successfully'
            ]);
            
            // Clean up temp file
            if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                @unlink($result['file_path']);
            }
        } else {
            $errorMsg = isset($result['message']) ? strip_tags($result['message']) : 'Unknown error processing image';
            echo json_encode([
                'success' => false,
                'message' => $errorMsg
            ]);
        }
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    } catch(Error $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

// Include common image processing functions
$file_validation_path = __DIR__ . '/../../includes/file_validation.php';
if(file_exists($file_validation_path)) {
    require_once $file_validation_path;
} elseif(file_exists('../../includes/file_validation.php')) {
    require_once '../../includes/file_validation.php';
} elseif(file_exists('../includes/file_validation.php')) {
    require_once '../includes/file_validation.php';
} elseif(file_exists('includes/file_validation.php')) {
    require_once 'includes/file_validation.php';
}
if(isset($_GET['card_number']) && !empty($_GET['card_number'])){
    $card_number = mysqli_real_escape_string($connect, $_GET['card_number']);
    $_SESSION['card_id_inprocess'] = $card_number;
    // Store in cookie for 24 hours
    setcookie('card_id_inprocess', $card_number, time() + (86400 * 1), '/');
} elseif(isset($_COOKIE['card_id_inprocess']) && !empty($_COOKIE['card_id_inprocess'])) {
    // If card_number not in URL but exists in cookie, restore to session
    $_SESSION['card_id_inprocess'] = $_COOKIE['card_id_inprocess'];
}

// Fetch existing card data
$row = [];
$gallery_images = [];
$user_id = 0;

if(isset($_SESSION['card_id_inprocess']) && !empty($_SESSION['card_id_inprocess'])) {
    $query = mysqli_query($connect, 'SELECT * FROM digi_card WHERE id="'.$_SESSION['card_id_inprocess'].'" AND user_email="'.$_SESSION['user_email'].'"');
    if(mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_array($query);
        
        // Get user_id directly from user_details table (unified table for all users)
        $user_email_escaped = mysqli_real_escape_string($connect, $_SESSION['user_email']);
        $user_email_lower = strtolower(trim($user_email_escaped));
        $user_id = 0;
        
        $user_details_query = mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = '$user_email_lower' LIMIT 1");
        if($user_details_query && mysqli_num_rows($user_details_query) > 0) {
            $user_details_row = mysqli_fetch_array($user_details_query);
            $user_id = isset($user_details_row['id']) ? intval($user_details_row['id']) : 0;
        }
        
        // Get gallery images from new table (card_image_gallery)
        $card_id = mysqli_real_escape_string($connect, $_SESSION['card_id_inprocess']);
        if($user_id > 0) {
            $gallery_query = mysqli_query($connect, "SELECT * FROM card_image_gallery WHERE card_id='$card_id' AND user_id=$user_id ORDER BY display_order ASC, id ASC");
            while($img_row = mysqli_fetch_array($gallery_query)) {
                $gallery_images[] = $img_row;
            }
        }
    } else {
        echo '<script>alert("Card ID not found or does not belong to your account."); window.location.href="business-name.php";</script>';
        exit;
    }
} else {
    echo '<script>alert("No card selected. Please select a card first."); window.location.href="business-name.php";</script>';
    exit;
}

// Handle form submission
$error_message = '';

if(isset($_POST['process5'])){
    // Verify user_id is available
    if($user_id <= 0) {
        if($is_ajax) {
            while(ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['success' => false, 'message' => 'User not found. Please login again.']);
            exit;
        }
        $error_message = '<div class="alert alert-danger">User not found. Please login again.</div>';
    } else {
        
        // Include file validation functions
        $file_validation_path = __DIR__ . '/../../includes/file_validation.php';
        if(file_exists($file_validation_path)) {
            require_once $file_validation_path;
        } elseif(file_exists('../../includes/file_validation.php')) {
            require_once '../../includes/file_validation.php';
        } elseif(file_exists('../includes/file_validation.php')) {
            require_once '../includes/file_validation.php';
        } elseif(file_exists('includes/file_validation.php')) {
            require_once 'includes/file_validation.php';
        }
        
        $card_id = mysqli_real_escape_string($connect, $_SESSION['card_id_inprocess']);
        $images_processed = false;
        $maxFileSize = 250000; // 250KB limit
        
        // Process submitted images (can be from modal or form)
        $direct_image_id = null;
        if(isset($_POST['image_id']) && !empty($_POST['image_id'])) {
            $direct_image_id = intval($_POST['image_id']);
        }
        
        for($x = 1; $x <= 10; $x++) {
            $gallery_image = null;
            $image_id = null;
            
            // Check if we have processed image data from AJAX (base64)
            if(!empty($_POST["processed_gallery_image_data$x"])){
                $images_processed = true;
                $gallery_image = base64_decode($_POST["processed_gallery_image_data$x"]);
                
                // Check if this is an update (image_id might be in hidden field)
                if(isset($_POST["image_id$x"]) && !empty($_POST["image_id$x"])) {
                    $image_id = intval($_POST["image_id$x"]);
                } elseif($direct_image_id && $direct_image_id > 0) {
                    $image_id = $direct_image_id;
                    $direct_image_id = null; // Use it only once
                }
            } elseif(!empty($_FILES["d_gall_img$x"]['tmp_name'])) {
                $images_processed = true;
                
                // Check if this is an update (image_id might be in hidden field)
                if(isset($_POST["image_id$x"]) && !empty($_POST["image_id$x"])) {
                    $image_id = intval($_POST["image_id$x"]);
                } elseif($direct_image_id && $direct_image_id > 0) {
                    $image_id = $direct_image_id;
                    $direct_image_id = null; // Use it only once
                }
                
                // Use the new automatic crop and resize function (1:1 crop)
                if(function_exists('processImageUploadWithAutoCrop')) {
                    $result = processImageUploadWithAutoCrop(
                        $_FILES["d_gall_img$x"], 
                        600,      // Target size: 600x600
                        250000,   // Target file size: 250KB
                        200000,   // Min file size: 200KB
                        300000,   // Max file size: 300KB
                        ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'],
                        'jpeg',
                        null
                    );
                    
                    if($result['status']) {
                        $gallery_image = $result['data'];
                        // Clean up temp file
                        if(isset($result['file_path']) && $result['file_path'] && file_exists($result['file_path'])) {
                            @unlink($result['file_path']);
                        }
                    } else {
                        $error_message .= '<div class="alert alert-danger">Gallery Image '.$x.': ' . strip_tags($result['message']) . '</div>';
                        continue;
                    }
                } else {
                    // Fallback compression
                    $source = $_FILES["d_gall_img$x"]['tmp_name'];
                    $imageFileType = strtolower(pathinfo($_FILES["d_gall_img$x"]['name'], PATHINFO_EXTENSION));
                    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if(!in_array($imageFileType, $allowedTypes)) {
                        $error_message .= '<div class="alert alert-danger">Gallery Image '.$x.': Only JPG, JPEG, PNG, GIF, WEBP files are allowed.</div>';
                        continue;
                    }
                    
                    if($_FILES["d_gall_img$x"]['size'] > $maxFileSize) {
                        $error_message .= '<div class="alert alert-danger">Gallery Image '.$x.': File size exceeds 250KB limit.</div>';
                        continue;
                    }
                    
                    $gallery_image = file_get_contents($source);
                }
            }
            
            // Save image to database if we have one
            if($gallery_image !== null) {
                // Get next display_order
                $max_order_query = mysqli_query($connect, "SELECT MAX(display_order) as max_order FROM card_image_gallery WHERE card_id='$card_id' AND user_id=$user_id");
                $max_order_row = mysqli_fetch_array($max_order_query);
                $display_order = isset($max_order_row['max_order']) ? intval($max_order_row['max_order']) + 1 : $x;
                
                if($image_id && $image_id > 0) {
                    // Update existing image
                    $verify_query = mysqli_query($connect, "SELECT id FROM card_image_gallery WHERE id=$image_id AND card_id='$card_id' AND user_id=$user_id");
                    if(mysqli_num_rows($verify_query) == 0) {
                        $error_message .= '<div class="alert alert-danger">Image not found or access denied.</div>';
                        continue;
                    }
                    
                    $gallery_image_escaped = addslashes($gallery_image);
                    $update_query = "UPDATE card_image_gallery SET gallery_image='$gallery_image_escaped' WHERE id=$image_id AND card_id='$card_id' AND user_id=$user_id";
                    $update_result = mysqli_query($connect, $update_query);
                    if(!$update_result) {
                        $error_message .= '<div class="alert alert-danger">Failed to update image. Error: ' . mysqli_error($connect) . '</div>';
                    }
                } else {
                    // Insert new image
                    if($user_id <= 0) {
                        $error_message .= '<div class="alert alert-danger">Invalid user ID. Please login again.</div>';
                        continue;
                    }
                    
                    $card_id_escaped = mysqli_real_escape_string($connect, $card_id);
                    $gallery_image_escaped = addslashes($gallery_image);
                    $insert_query = "INSERT INTO card_image_gallery (card_id, user_id, gallery_image, display_order) VALUES ('$card_id_escaped', $user_id, '$gallery_image_escaped', $display_order)";
                    
                    $insert_result = mysqli_query($connect, $insert_query);
                    if(!$insert_result) {
                        $error_message .= '<div class="alert alert-danger">Failed to add image. Error: ' . mysqli_error($connect) . '</div>';
                    } else {
                        // Get the inserted image_id for AJAX response
                        $new_image_id = mysqli_insert_id($connect);
                        if($is_ajax && $new_image_id > 0) {
                            // Store image_id for response (only for single image insert via AJAX)
                            if(!isset($ajax_image_id)) {
                                $ajax_image_id = $new_image_id;
                            }
                        }
                    }
                }
            }
        }
        
        if(empty($error_message)) {
            if($is_ajax) {
                // Clear any output buffer and return JSON
                if(ob_get_level() > 0) {
                    ob_clean();
                }
                // Make sure no headers are sent
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                $response_data = ['success' => true, 'message' => 'Images uploaded successfully'];
                // Include image_id if we have it (for newly inserted images)
                if(isset($ajax_image_id) && $ajax_image_id > 0) {
                    $response_data['image_id'] = $ajax_image_id;
                }
                echo json_encode($response_data);
                if(ob_get_level() > 0) {
                    ob_end_flush();
                }
                exit;
            } else {
                // Regular form submission - redirect
                $card_query = !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : '';
                echo '<script>alert("Images Uploaded Successfully!"); window.location.href="image-gallery.php'.$card_query.'";</script>';
                exit;
            }
        } else {
            if($is_ajax) {
                if(ob_get_level() > 0) {
                    ob_clean();
                }
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode(['success' => false, 'message' => $error_message]);
                if(ob_get_level() > 0) {
                    ob_end_flush();
                }
                exit;
            }
        }
    }
}

include 'header.php';
?>

<main class="Dashboard">
    <div class="container-fluid customer_content_area">
        <div class="main-top">
        <span class="headingTop">Image Gallary</span>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">Mini Website</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                </ol>
            </nav>
        </div>

        <div class="card mb-4">
                        <div class="card-body">
                            <label class="heading">Image Gallery:</label>
                            <p>You can add upto 10 images of your choice which you want to showcase on your Mini Website page.</p>
                            <br>
                <div id="status_remove_img"></div>
                <button class="btn btn-primary add_image" onclick="openImageModal()" style="width: auto;"><i class="fa fa-plus" aria-hidden="true"></i> Add Images</button>

                            <div class="Product-ServicesTable">
                                <table class="display table">
                                    <thead class="bg-secondary">
                                        <tr>
                                            <th>Image Details</th>
                                            <th>Manage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            <?php 
                            $imageCount = count($gallery_images);
                            if($imageCount > 0):
                                foreach($gallery_images as $img): 
                                    $img_id = intval($img['id']);
                            ?>
                                <tr data-image-id="<?php echo $img_id; ?>" data-card-id="<?php echo $row['id']; ?>">
                                            <td valign="middle">
                                        <img src="data:image/*;base64,<?php echo base64_encode($img['gallery_image']); ?>" class="img-fluid" width="60px" alt="">
                                            </td>
                                            <td valign="middle">
                                        <a class="edit" href="javascript:void(0);" onclick="editImage(<?php echo $img_id; ?>)">
                                            <img src="../../customer/assets/img/edit1.png" alt="">
                                        </a>
                                        <a class="delet" href="javascript:void(0);" onclick="removeData(<?php echo $row['id']; ?>, <?php echo $img_id; ?>)">
                                            <img src="../../customer/assets/img/delet.png" alt="">
                                        </a>
                                            </td>
                                        </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted">No images added yet. Click "Add Images" to add.</td>
                                        </tr>
                            <?php endif; ?>
                                    </tbody>
                                </table>

                    <!-- Hidden form for saving all images -->
                    <form id="imageForm" action="" method="POST" enctype="multipart/form-data" style="display:none;">
                        <?php for($m = 1; $m <= 10; $m++): ?>
                            <input type="file" name="d_gall_img<?php echo $m; ?>" id="form_d_gall_img<?php echo $m; ?>">
                            <!-- Hidden field for processed image data -->
                            <input type="hidden" name="processed_gallery_image_data<?php echo $m; ?>" id="form_processed_gallery_image_data<?php echo $m; ?>" value="">
                            <!-- Hidden field for image_id (for updates) -->
                            <input type="hidden" name="image_id<?php echo $m; ?>" id="form_image_id<?php echo $m; ?>" value="">
                        <?php endfor; ?>
                    </form>

                                <div class="Product-ServicesBtn">
                        <a href="product-pricing.php<?php echo !empty($_SESSION['card_id_inprocess']) ? '?card_number=' . $_SESSION['card_id_inprocess'] : ''; ?>" class="btn btn-secondary align-left">
                            <span class="left_angle angle"><i class="fa fa-angle-left"></i></span>
                            <span>Back</span>
                        </a>
                        <button class="btn btn-primary align-center" onclick="saveImages()"><img src="../../customer/assets/img/Save.png" class="img-fluid" width="35px" alt=""> <span>Save</span></button>
                        <a href="../../team/dashboard/" class="btn btn-secondary align-right">
                            <span>Finish</span>
                            <span class="right_angle angle"><i class="fa fa-angle-right"></i></span>
                        </a>
                    </div>
                                </div>
                            </div>
                       </div>
                    </div>
</main>
 
<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" role="dialog" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Add/Edit Gallery Image</h5>
                <button type="button" class="close" onclick="closeImageModal()" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="modalImageForm">
                    <input type="hidden" id="modal_image_id" value="">
                    <input type="hidden" id="modal_image_number" value="">
                    <div class="form-group">
                        <label>Gallery Image</label>
                        <div class="image-preview-modal" style="text-align: center; margin-bottom: 15px;">
                            <img id="modal_image_preview" 
                                 src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+" 
                                 alt="Gallery Image" 
                                 onclick="document.getElementById('modal_image').click()" 
                                 style="max-width: 200px; max-height: 200px; border: 2px dashed #ddd; border-radius: 8px; cursor: pointer; padding: 10px;">
                        </div>
                        <input type="file" id="modal_image" onchange="readModalImage(this);" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modal_image').click()">Choose Image</button>
                        <small class="form-text text-muted">File Supported - .png, .jpg, .jpeg, .gif, .webp</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeImageModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addImageToForm()">Add Image</button>
            </div>
        </div>
    </div>
</div>

<script>
var imageFiles = {};
// Store processed image data for form submission
var processedGalleryImageData = null;

// Find next available image slot (for backward compatibility)
function findNextAvailableSlot() {
    // Count existing images and return next number
    var existingCount = $('tr[data-image-id]').length;
    if(existingCount >= 10) {
        alert('Maximum 10 images allowed. Please delete an image first.');
        return null;
    }
    return existingCount + 1;
}

// Open image modal
function openImageModal() {
    processedGalleryImageData = null; // Reset processed image data
    $('#modal_image_id').val('');
    $('#modal_image_number').val('');
    $('#modal_image').val('');
    $('#modal_image_preview').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+');
    $('#imageModalLabel').text('Add Gallery Image');
    $('.modal-footer button:last').text('Add Image');
    
    // Try to open modal using different methods
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        $('#imageModal').modal('show');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modalElement = document.getElementById('imageModal');
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        // Fallback: direct DOM manipulation
        document.getElementById('imageModal').style.display = 'block';
        document.getElementById('imageModal').classList.add('show');
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'modalBackdrop';
        document.body.appendChild(backdrop);
    }
}

// Edit image (imageNum is now image_id)
function editImage(imageId) {
    processedGalleryImageData = null; // Reset processed image data
    
    // Get existing image BEFORE opening modal (to avoid timing issues)
    // Try multiple ways to find the row (by image_id, or by imageNum if imageId is actually a number)
    var existingRow = null;
    var existingImgSrc = null;
    
    // First try by data-image-id
    if(imageId && imageId !== '') {
        existingRow = $('tr[data-image-id="' + imageId + '"]');
    }
    
    // If not found and imageId looks like a number, it might be imageNum
    if((!existingRow || existingRow.length === 0) && imageId && !isNaN(imageId)) {
        existingRow = $('tr[data-image-num="' + imageId + '"]');
    }
    
    if(existingRow && existingRow.length > 0) {
        // Try to find image in first column (Image Details column)
        var existingImg = existingRow.find('td:first img');
        if(existingImg.length === 0) {
            // Fallback: try any img in the row
            existingImg = existingRow.find('img');
        }
        
        if(existingImg.length > 0) {
            var imgSrc = existingImg.attr('src');
            // Check if it's a valid image (not SVG placeholder and not "No Image" text)
            if(imgSrc && imgSrc.startsWith('data:image') && !imgSrc.includes('svg+xml')) {
                existingImgSrc = imgSrc;
            } else if(imgSrc && (imgSrc.startsWith('data:image/jpeg') || imgSrc.startsWith('data:image/png') || imgSrc.startsWith('data:image/*'))) {
                existingImgSrc = imgSrc;
            }
        }
    }
    
    // Open modal first
    openImageModal();
    
    // Set values after modal is opened (use setTimeout to ensure modal is fully rendered)
    setTimeout(function() {
        $('#modal_image_id').val(imageId);
        $('#modal_image_number').val(''); // Clear slot number for edit
        
        // Set existing image if available
        if(existingImgSrc) {
            $('#modal_image_preview').attr('src', existingImgSrc);
        } else {
            $('#modal_image_preview').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+');
        }
        
        $('#imageModalLabel').text('Edit Gallery Image');
        $('.modal-footer button:last').text('Update Image');
    }, 200); // Increased timeout to ensure modal is fully rendered
}

// Read modal image
function readModalImage(input) {
    if(input.files && input.files[0]) {
        var file = input.files[0];
        var allowedTypes = ['image/jpeg','image/png','image/gif','image/webp'];
        var maxSize = 10 * 1024 * 1024; // 10MB (will be auto-optimized to 250KB)
        
        // Validate file type
        if(allowedTypes.indexOf(file.type) === -1) {
            alert('Only JPG, PNG, GIF, and WEBP images are allowed.');
            $(input).val('');
            processedGalleryImageData = null;
            return;
        }
        
        // Validate file size (10MB max - will be auto-optimized to 250KB)
        if(file.size > maxSize) {
            alert('Image size must be 10MB or less. The image will be automatically optimized to 250KB.');
            $(input).val('');
            processedGalleryImageData = null;
            return;
        }
        
        // Show loading indicator
        $('#modal_image_preview').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5Qcm9jZXNzaW5nLi4uPC90ZXh0Pjwvc3ZnPg==');
        
        // Immediately process the image via AJAX
        var formData = new FormData();
        formData.append('gallery_image', file);
        formData.append('process_gallery_image_ajax', '1');
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if(response && response.success) {
                    // Show processed image (cropped to 1:1, optimized)
                    var processedImageSrc = 'data:image/jpeg;base64,' + response.image_data;
                    $('#modal_image_preview').attr('src', processedImageSrc);
                    
                    // Store processed image data for form submission
                    processedGalleryImageData = response.image_data;
                } else {
                    alert(response.message || 'Error processing image. Please try again.');
                    // Revert preview
                    $('#modal_image_preview').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+');
                    $(input).val('');
                    processedGalleryImageData = null;
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                var errorMsg = 'Error processing image. Please try again.';
                try {
                    if(xhr.responseText) {
                        var errorResponse = JSON.parse(xhr.responseText);
                        if(errorResponse && errorResponse.message) {
                            errorMsg = errorResponse.message;
                        }
                    }
                } catch(e) {}
                alert(errorMsg);
                // Revert preview
                $('#modal_image_preview').attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBVcGxvYWQ8L3RleHQ+PC9zdmc+');
                $(input).val('');
                processedGalleryImageData = null;
            }
        });
    }
}

// Add image to form and save immediately
function addImageToForm() {
    var imageNum = $('#modal_image_number').val();
    if(!imageNum) {
        imageNum = findNextAvailableSlot();
        if(!imageNum) return;
    }
    
    // Handle image file - use processed image data if available
    if(!processedGalleryImageData) {
        var imageFile = document.getElementById('modal_image').files[0];
        if(!imageFile) {
            alert('Please select an image.');
            return;
        }
    }
    
    // Get image_id if editing
    var imageId = $('#modal_image_id').val();
    
    // Create FormData for AJAX submission
    var formData = new FormData();
    formData.append('process5', '1');
    if(imageId) {
        formData.append('image_id', imageId);
        formData.append('image_id' + imageNum, imageId);
    }
    
    // Use processed image data if available, otherwise use file
    if(processedGalleryImageData) {
        formData.append('processed_gallery_image_data' + imageNum, processedGalleryImageData);
    } else {
        var imageFile = document.getElementById('modal_image').files[0];
        if(imageFile) {
            formData.append('d_gall_img' + imageNum, imageFile);
        }
    }
    
    // Also update hidden form for later save
    // Set image_id in hidden form field if editing
    if(imageId) {
        $('#form_image_id' + imageNum).val(imageId);
    }
    
    if(processedGalleryImageData) {
        // Create hidden input for processed image data
        var hiddenInput = document.getElementById('form_processed_gallery_image_data' + imageNum);
        if(!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'processed_gallery_image_data' + imageNum;
            hiddenInput.id = 'form_processed_gallery_image_data' + imageNum;
            document.getElementById('imageForm').appendChild(hiddenInput);
        }
        hiddenInput.value = processedGalleryImageData;
    } else {
        var imageFile = document.getElementById('modal_image').files[0];
        if(imageFile) {
            var newInput = document.createElement('input');
            newInput.type = 'file';
            newInput.name = 'd_gall_img' + imageNum;
            newInput.id = 'form_d_gall_img' + imageNum;
            newInput.style.display = 'none';
            var dataTransfer = new DataTransfer();
            dataTransfer.items.add(imageFile);
            newInput.files = dataTransfer.files;
            var oldInput = document.getElementById('form_d_gall_img' + imageNum);
            if(oldInput) oldInput.remove();
            document.getElementById('imageForm').appendChild(newInput);
            imageFiles[imageNum] = imageFile;
        }
    }
    
    // Show loading
    var loadingMsg = '<div class="alert alert-info">Uploading image...</div>';
    $('#status_remove_img').html(loadingMsg);
    
    // Get cardId for use in AJAX callback
    var cardId = $('tbody tr[data-card-id]').first().data('card-id') || '';
    
    // Get image preview first (before AJAX)
    var imagePreview = '';
    var updateTableCallback = function() {
        // Update table immediately (optimistic update)
        updateImageTable(imageNum, imagePreview, imageId);
        
        // Submit via AJAX in background
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            dataType: 'json',
            success: function(response) {
                if(response && response.success) {
                    // If we got an image_id back from server, update the row
                    if(response.image_id && !imageId) {
                        // Update the row's data-image-id attribute
                        var updatedRow = $('tr[data-image-num="' + imageNum + '"]');
                        if(updatedRow.length > 0) {
                            updatedRow.attr('data-image-id', response.image_id);
                            // Update edit and delete onclick to use the new image_id
                            updatedRow.find('.edit').attr('onclick', 'editImage(' + response.image_id + ')');
                            updatedRow.find('.delet').attr('onclick', 'removeData(' + cardId + ', ' + response.image_id + ')');
                        }
                    }
                    
                    $('#status_remove_img').html('<div class="alert alert-success">' + (response.message || 'Image uploaded successfully!') + '</div>');
                    setTimeout(function() {
                        $('#status_remove_img').html('');
                    }, 2000);
                } else {
                    $('#status_remove_img').html('<div class="alert alert-danger">' + (response.message || 'Error uploading image.') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', {
                    status: status, 
                    error: error, 
                    statusCode: xhr.status,
                    responseText: xhr.responseText.substring(0, 500)
                });
                
                var errorMsg = 'Error uploading image. ';
                
                // Check HTTP status code
                if(xhr.status === 0) {
                    errorMsg = 'Network error. Please check your connection.';
                } else if(xhr.status >= 500) {
                    errorMsg = 'Server error (HTTP ' + xhr.status + '). Please try again later.';
                } else if(xhr.status >= 400) {
                    errorMsg = 'Request error (HTTP ' + xhr.status + '). Please refresh and try again.';
                }
                
                // Try to parse JSON response
                try {
                    var response = JSON.parse(xhr.responseText);
                    if(response && !response.success) {
                        $('#status_remove_img').html('<div class="alert alert-danger">' + (response.message || 'Error uploading image.') + '</div>');
                        return;
                    }
                } catch(e) {
                    // Not JSON - might be HTML error or PHP warning
                    if(xhr.responseText) {
                        if(xhr.responseText.includes('Warning') || xhr.responseText.includes('Notice') || xhr.responseText.includes('Fatal error')) {
                            errorMsg += 'Server error detected. Check console for details.';
                        } else if(xhr.responseText.length > 0) {
                            errorMsg += 'Unexpected response from server.';
                        }
                    }
                    $('#status_remove_img').html('<div class="alert alert-danger">' + errorMsg + '</div>');
                }
            }
        });
    };
    
    // Use processed image if available, otherwise use file preview
    if(processedGalleryImageData) {
        imagePreview = '<img src="data:image/jpeg;base64,' + processedGalleryImageData + '" class="img-fluid" width="60px" alt="">';
        updateTableCallback();
    } else {
        var imageFile = document.getElementById('modal_image').files[0];
        if(imageFile) {
            var reader = new FileReader();
            reader.onload = function(e) {
                imagePreview = '<img src="' + e.target.result + '" class="img-fluid" width="60px" alt="">';
                updateTableCallback();
            };
            reader.readAsDataURL(imageFile);
        } else {
            imagePreview = '<span class="text-muted">No Image</span>';
            updateTableCallback();
        }
    }
    
    // Close modal immediately
    closeImageModal();
}

// Update image table dynamically
function updateImageTable(imageNum, imagePreview, imageId) {
    var tableBody = $('.Product-ServicesTable tbody');
    var cardId = $('tbody tr[data-card-id]').first().data('card-id') || '<?php echo isset($row["id"]) ? $row["id"] : ""; ?>';
    
    // Remove "No images" message if exists
    tableBody.find('td[colspan="2"]').closest('tr').remove();
    
    // Check if row already exists (for edit) - check by image_id if provided, otherwise by imageNum
    var existingRow = null;
    if(imageId) {
        existingRow = tableBody.find('tr[data-image-id="' + imageId + '"]');
    } else {
        existingRow = tableBody.find('tr[data-image-num="' + imageNum + '"]');
    }
    
    if(existingRow.length > 0) {
        // Update existing row
        existingRow.find('td:first').html(imagePreview);
        // Update edit onclick with image_id
        var editImageId = imageId || imageNum;
        existingRow.find('.edit').attr('onclick', 'editImage(' + editImageId + ')');
        // Update delete onclick with image_id
        existingRow.find('.delet').attr('onclick', 'removeData(' + cardId + ', ' + editImageId + ')');
    } else {
        // Add new row
        var rowImageId = imageId || '';
        var newRow = '<tr data-image-id="' + rowImageId + '" data-image-num="' + imageNum + '" data-card-id="' + cardId + '">' +
            '<td valign="middle">' + imagePreview + '</td>' +
            '<td valign="middle">' +
            '<a class="edit" href="javascript:void(0);" onclick="editImage(' + (imageId || imageNum) + ')">' +
            '<img src="../../customer/assets/img/edit1.png" alt=""></a> ' +
            '<a class="delet" href="javascript:void(0);" onclick="removeData(' + cardId + ', ' + (imageId || imageNum) + ')">' +
            '<img src="../../customer/assets/img/delet.png" alt=""></a>' +
            '</td>' +
            '</tr>';
        tableBody.append(newRow);
    }
}

// Save images
function saveImages() {
    document.getElementById('imageForm').submit();
}

// Remove image (numb is now image_id)
function removeData(cardId, imageId) {
    if(confirm('Are you sure you want to remove this image?')) {
        $('#status_remove_img').css('color','blue');
        
        $.ajax({
            url: '../../panel/login/js_request.php',
            method: 'POST',
            data: {action: 'delete_gallery_image', image_id: imageId},
            dataType: 'text',
            success: function(data){
                $('#status_remove_img').html(data);
                if(data.includes('success')){
                    // Remove the row from table
                    $('tr[data-image-id="' + imageId + '"]').remove();
                    
                    // Check if table is now empty
                    var tableBody = $('.Product-ServicesTable tbody');
                    if(tableBody.find('tr[data-image-id]').length === 0) {
                        tableBody.html('<tr><td colspan="2" class="text-center text-muted">No images added yet. Click "Add Images" to add.</td></tr>');
                    }
                    
                    $('#status_remove_img').html('<div class="alert alert-success">Image removed successfully!</div>');
                    setTimeout(function(){
                        $('#status_remove_img').html('');
                    }, 2000);
                }
            },
            error: function(){
                $('#status_remove_img').html('<div class="alert alert-danger">Error deleting image. Please try again.</div>');
            }
        });
    }
}

// Close image modal
function closeImageModal() {
    if(typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        $('#imageModal').modal('hide');
    } else if(typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var modalElement = document.getElementById('imageModal');
        var modal = bootstrap.Modal.getInstance(modalElement);
        if(modal) {
            modal.hide();
        } else {
            var newModal = new bootstrap.Modal(modalElement);
            newModal.hide();
        }
    } else {
        document.getElementById('imageModal').style.display = 'none';
        document.getElementById('imageModal').classList.remove('show');
        document.body.classList.remove('modal-open');
        var backdrop = document.getElementById('modalBackdrop');
        if(backdrop) backdrop.remove();
    }
}
</script>

<style>
     footer{
        margin-bottom:54px;
    }
    .savebutton span{
        font-size:26px;
    }
    .savebutton{
        display: flex !important;
    
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    }
    .headingTop{
        font-size:28px;
        font-weight:500px;
        line-height:67px;
        padding-left:5px;
    }
    .heading{
        position: relative;
    }
    .heading:after
    {
        content: '';
        width: 150px;
        height: 1px;
        background: #ffb300;
        position: absolute;
        left: 0px;
        bottom: 2px;
    }
    .card-body{
        padding:50px !important;
    }
    .Dashboard .heading{
        font-size:28px !important;
    }
    .add_product{
        border-radius: 4px;
        display: flex !important;
        justify-content: center;
        align-items: center;
        gap: 10px;
        padding:10px;
    }
    .add_product i,.add_product span{
        font-size:19px !important;
    }
    .Product-ServicesTable {
    margin: 20px auto;
}
.Product-ServicesTable table th,
.Product-ServicesTable table td{
    text-align:left !important;
    
}
.Product-ServicesTable table th:first-child,
.Product-ServicesTable table td:first-child{
    padding-left:40px;
    padding-right: 155px;
}
.Product-ServicesTable table th,
.Product-ServicesTable table td{
    width: 33%;
}
.Product-ServicesTable table th:nth-child(2),
.Product-ServicesTable table th:nth-child(3),
.Product-ServicesTable table td:nth-child(2),
.Product-ServicesTable table td:nth-child(3){
    text-align:center !important;
}
.Product-ServicesBtn button{
    display: flex !important;
    color: #fff !important;
    justify-content: center;
    align-items: center;
    gap: 10px;
}
.Product-ServicesBtn{
    padding: 0px 40px;
}
.Product-ServicesBtn button .angle{
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff !important;
    color:#000;
    font-weight:bold;
    display: flex;
    justify-content: center;
    align-items: center;
}
.Product-ServicesBtn button span:not(.angle){
    font-weight:500;
    font-size:22px;
}
.Product-ServicesBtn button span .fa-angle-left,
.Product-ServicesBtn button span .fa-angle-right{
    font-weight: bold;
    font-size: 16px !important;
}
.Product-ServicesBtn .align-center{
    padding: 4px 10px;
}
.Product-ServicesBtn .align-center img{
    width: 23px;
}
.Product-ServicesBtn .align-center span{
    color:#000;
}
.Product-ServicesBtn{
    margin-top:30px;
}
.Product-ServicesTable table th,
 .Product-ServicesTable table td {
    
    font-weight:500 !important;
}

@media screen and (max-width: 768px) {
        .card-body form {
    padding: 0px 15px;
}
.card-body {
    padding: 25px !important;
    padding-bottom: 100px !important;
}

.submitBtnSection{
    margin-top:20px;
}
.Dashboard .heading {
    font-size: 25px !important;
    margin-bottom: 20px;
}
.heading:after {
    content: '';
    width: 164px;
    height: 2px;
    background: #ffb300;
    position: absolute;
    left: 8px;
    bottom: 0px;
}
.card-body p{
    font-size: 15px;
    line-height: 20px;
}
.add_product i, .add_product span {
    font-size: 16px !important;
}
.Product-ServicesTable{
width: 100%;
overflow-x:auto;
}
.Product-ServicesTable table{
    width: 700px;
    white-space:nowrap;
}
.Product-ServicesTable table th:first-child, .Product-ServicesTable table td:first-child {
    padding-left: 10px;
    padding-top:10px;
    padding-bottom:10px;
    padding-right: 20px;
    font-weight:500 !important;
}
.Product-ServicesTable table th,
 .Product-ServicesTable table td {
    padding-left: 10px;
    padding-top:10px;
    padding-bottom:10px;
    padding-right: 20px;
    font-weight:500 !important;
}

.Product-ServicesBtn{
padding:0px;
}
.Product-ServicesBtn button span:not(.angle) {
    font-weight: 500;
    font-size: 15px;
}
    
    /* Mobile styles for edit and delete buttons */
    .Product-ServicesTable table td:last-child {
        min-width: 100px !important;
        text-align: center !important;
    }
    .Product-ServicesTable table td:last-child .edit,
    .Product-ServicesTable table td:last-child .delet {
        display: inline-block !important;
        margin: 0 5px !important;
        padding: 8px !important;
        min-width: 40px !important;
        min-height: 40px !important;
        vertical-align: middle !important;
    }
    .Product-ServicesTable table td:last-child .edit img,
    .Product-ServicesTable table td:last-child .delet img {
        width: 24px !important;
        height: 24px !important;
        display: block !important;
    }
    }

    
.Product-ServicesBtn{
        
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
    }
    .Product-ServicesBtn button,
    .Product-ServicesBtn a{
        display: flex !important;
        color: #fff !important;
        justify-content: center;
        align-items: center;
        gap: 10px;
        text-decoration: none;
    }
    .Product-ServicesBtn button .angle,
    .Product-ServicesBtn a .angle{
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #fff !important;
        color:#000;
        font-weight:bold;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .Product-ServicesBtn button span:not(.angle),
    .Product-ServicesBtn a span:not(.angle){
        font-weight:500;
        font-size:16px;
    }
    .Product-ServicesBtn .align-center{
        padding: 4px 10px;
    }
    .Product-ServicesBtn .align-center img{
        width: 23px;
    }
    .Product-ServicesBtn .align-center span{
        color:#000;
    }

    .Product-ServicesBtn  .btn{
        line-height:24px !important;
    }
    .Product-ServicesBtn button {
        padding: 7px !important;
        margin-top: 22px !important;
    }
    .add_image{
        padding:10px;
    }
    .add_image:hover{
        opacity: 0.8;
    }
    .Product-ServicesTable table td:last-child img{
        width: 20px;
    }
    
    @media screen and (max-width: 768px) {
        .Product-ServicesBtn {
            padding: 0px 10px !important;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px !important;
            width: 100% !important;
        }
        .Product-ServicesBtn button,
        .Product-ServicesBtn a {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 12px 20px !important;
            font-size: 16px !important;
            justify-content: center;
        }
        .Product-ServicesBtn .align-left,
        .Product-ServicesBtn .align-center,
        .Product-ServicesBtn .align-right {
            width: 100% !important;
        }
        .Product-ServicesBtn button span:not(.angle),
        .Product-ServicesBtn a span:not(.angle) {
            font-size: 16px !important;
        }
        .Product-ServicesBtn .align-center img {
            width: 20px;
        }
        
        /* Mobile styles for edit and delete buttons */
        .Product-ServicesTable table td:last-child {
            min-width: 120px !important;
            width: auto !important;
            text-align: center !important;
            padding: 10px 5px !important;
        }
        .Product-ServicesTable table th:last-child {
            min-width: 120px !important;
            width: auto !important;
            text-align: center !important;
        }
        .Product-ServicesTable table td:last-child .edit,
        .Product-ServicesTable table td:last-child .delet {
            display: inline-block !important;
            margin: 0 8px !important;
            padding: 10px !important;
            min-width: 44px !important;
            min-height: 44px !important;
            vertical-align: middle !important;
            line-height: 1 !important;
        }
        .Product-ServicesTable table td:last-child .edit img,
        .Product-ServicesTable table td:last-child .delet img {
            width: 28px !important;
            height: 28px !important;
            display: block !important;
            margin: 0 auto !important;
        }
        .Product-ServicesTable table td:first-child {
            min-width: 80px !important;
        }
        .Product-ServicesTable table td:first-child img {
            width: 50px !important;
            height: auto !important;
        }
    }
</style>

<?php include '../footer.php'; ?>
