<?php
// Set PHP execution limits (upload_max_filesize and post_max_size must be set in php.ini)
@ini_set('max_execution_time', '300');
@ini_set('max_input_time', '300');
@ini_set('memory_limit', '128M');

require_once('connect.php');
include_once('header.php');

// Determine active kit tab
$allowed_kits = array('sales','marketing','franchise_sales');
$current_kit = isset($_GET['kit']) && in_array(strtolower($_GET['kit']), $allowed_kits) ? strtolower($_GET['kit']) : 'sales';

$kit_upload_dir = '../assets/upload/kits/';

function kitFolderIdSql($folder_id) {
    $folder_id = intval($folder_id);
    return $folder_id > 0 ? (string)$folder_id : 'NULL';
}

function kitValidateFolderForCategory($connect, $folder_id, $category) {
    $folder_id = intval($folder_id);
    if ($folder_id <= 0) {
        return 0;
    }
    $category = mysqli_real_escape_string($connect, $category);
    $res = mysqli_query($connect, "SELECT id FROM franchisee_kit_folders WHERE id = $folder_id AND category = '$category' LIMIT 1");
    if ($res && mysqli_fetch_array($res)) {
        return $folder_id;
    }
    return 0;
}

function kitParentIdSql($parent_id) {
    $parent_id = intval($parent_id);
    return $parent_id > 0 ? (string)$parent_id : 'NULL';
}

function kitValidateParentFolder($connect, $parent_id, $category, $exclude_folder_id = 0) {
    $parent_id = intval($parent_id);
    if ($parent_id <= 0) {
        return 0;
    }
    if ($parent_id === intval($exclude_folder_id)) {
        return 0;
    }
    if (!kitValidateFolderForCategory($connect, $parent_id, $category)) {
        return 0;
    }
    if ($exclude_folder_id > 0) {
        $descendants = kitGetFolderDescendantIds($connect, $exclude_folder_id);
        if (in_array($parent_id, $descendants, true)) {
            return 0;
        }
    }
    return $parent_id;
}

function kitGetFolderDescendantIds($connect, $folder_id) {
    $folder_id = intval($folder_id);
    $ids = [];
    $res = mysqli_query($connect, "SELECT id FROM franchisee_kit_folders WHERE parent_id = $folder_id");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $child_id = (int)$row['id'];
            $ids[] = $child_id;
            $ids = array_merge($ids, kitGetFolderDescendantIds($connect, $child_id));
        }
    }
    return $ids;
}

function kitDeleteFolderRecursive($connect, $folder_id, $upload_dir) {
    $folder_id = intval($folder_id);
    $sub_res = mysqli_query($connect, "SELECT id FROM franchisee_kit_folders WHERE parent_id = $folder_id");
    if ($sub_res) {
        while ($sub = mysqli_fetch_assoc($sub_res)) {
            kitDeleteFolderRecursive($connect, (int)$sub['id'], $upload_dir);
        }
    }

    $items_res = mysqli_query($connect, "SELECT id, file_path FROM franchisee_kit WHERE folder_id = $folder_id");
    if ($items_res) {
        while ($item = mysqli_fetch_assoc($items_res)) {
            if (!empty($item['file_path'])) {
                $file_path = $upload_dir . $item['file_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            mysqli_query($connect, "DELETE FROM franchisee_kit WHERE id = " . intval($item['id']));
        }
    }

    mysqli_query($connect, "DELETE FROM franchisee_kit_folders WHERE id = $folder_id");
}

function kitBuildFolderChildrenMap($folders) {
    $children = [];
    foreach ($folders as $folder) {
        $parent_id = !empty($folder['parent_id']) ? (int)$folder['parent_id'] : 0;
        if (!isset($children[$parent_id])) {
            $children[$parent_id] = [];
        }
        $children[$parent_id][] = $folder;
    }
    return $children;
}

function kitRenderFolderOptions($children_map, $parent_id = 0, $depth = 0, $selected_id = 0, $exclude_id = 0) {
    if (!isset($children_map[$parent_id])) {
        return;
    }
    foreach ($children_map[$parent_id] as $folder) {
        $folder_id = (int)$folder['id'];
        if ($folder_id === (int)$exclude_id) {
            continue;
        }
        $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
        $selected = $folder_id === (int)$selected_id ? ' selected' : '';
        echo '<option value="' . $folder_id . '"' . $selected . '>' . htmlspecialchars($prefix . $folder['title']) . '</option>';
        kitRenderFolderOptions($children_map, $folder_id, $depth + 1, $selected_id, $exclude_id);
    }
}

function kitCountSubfolders($children_map, $folder_id) {
    $folder_id = (int)$folder_id;
    if (!isset($children_map[$folder_id])) {
        return 0;
    }
    $count = count($children_map[$folder_id]);
    foreach ($children_map[$folder_id] as $child) {
        $count += kitCountSubfolders($children_map, (int)$child['id']);
    }
    return $count;
}

function kitLabel($key) {
    if ($key === 'sales') return 'MW Sales Kit';
    if ($key === 'marketing') return 'Creator Kit';
    if ($key === 'franchise_sales') return 'Franchise Sales Kit';
    return '';
}

// Counts per kit for badges
$kit_counts = array('sales' => 0, 'marketing' => 0, 'franchise_sales' => 0);
$count_res = mysqli_query($connect, "SELECT category, COUNT(*) AS c FROM franchisee_kit GROUP BY category");
if ($count_res) {
    while ($cr = mysqli_fetch_array($count_res)) {
        $cat = strtolower($cr['category']);
        if (isset($kit_counts[$cat])) { $kit_counts[$cat] = (int)$cr['c']; }
    }
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Function to convert PHP ini size string to bytes
function convertToBytes($val) {
    if (empty($val)) return 0;
    $val = trim($val);
    $len = strlen($val);
    if ($len == 0) return 0;
    $last = strtolower($val[$len - 1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'create_folder') {
            $folder_title = trim(isset($_POST['folder_title']) ? $_POST['folder_title'] : '');
            $display_order = intval(isset($_POST['folder_order']) ? $_POST['folder_order'] : 0);
            $kit_category = mysqli_real_escape_string($connect, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
            $parent_id = kitValidateParentFolder($connect, isset($_POST['parent_folder_id']) ? $_POST['parent_folder_id'] : 0, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
            $parent_sql = kitParentIdSql($parent_id);

            if ($folder_title === '') {
                $error_message = "Please enter a folder name.";
            } else {
                $folder_title = mysqli_real_escape_string($connect, $folder_title);
                $insert_query = "INSERT INTO franchisee_kit_folders (title, category, parent_id, display_order, status)
                                 VALUES ('$folder_title', '$kit_category', $parent_sql, $display_order, 'active')";
                if (mysqli_query($connect, $insert_query)) {
                    $success_message = $parent_id > 0 ? "Subfolder created successfully!" : "Folder created successfully!";
                } else {
                    $error_message = "Error creating folder: " . mysqli_error($connect);
                }
            }
        }

        elseif ($action == 'edit_folder') {
            $folder_id = intval($_POST['folder_id']);
            $folder_title = trim(isset($_POST['edit_folder_title']) ? $_POST['edit_folder_title'] : '');
            $display_order = intval(isset($_POST['edit_folder_order']) ? $_POST['edit_folder_order'] : 0);
            $status = mysqli_real_escape_string($connect, isset($_POST['edit_folder_status']) ? $_POST['edit_folder_status'] : 'active');
            $kit_category = mysqli_real_escape_string($connect, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
            $parent_id = kitValidateParentFolder($connect, isset($_POST['edit_parent_folder_id']) ? $_POST['edit_parent_folder_id'] : 0, $kit_category, $folder_id);
            $parent_sql = kitParentIdSql($parent_id);

            if ($folder_title === '') {
                $error_message = "Please enter a folder name.";
            } else {
                $folder_title = mysqli_real_escape_string($connect, $folder_title);
                $update_query = "UPDATE franchisee_kit_folders SET title = '$folder_title', parent_id = $parent_sql, display_order = $display_order, status = '$status' WHERE id = $folder_id";
                if (mysqli_query($connect, $update_query)) {
                    $success_message = "Folder updated successfully!";
                } else {
                    $error_message = "Error updating folder: " . mysqli_error($connect);
                }
            }
        }

        elseif ($action == 'delete_folder') {
            $folder_id = intval($_POST['folder_id']);
            global $kit_upload_dir;
            kitDeleteFolderRecursive($connect, $folder_id, $kit_upload_dir);
            $success_message = "Folder and all subfolders/contents deleted successfully!";
        }

        elseif ($action == 'add_image') {
            // Handle image upload
            if (isset($_FILES['kit_image']) && $_FILES['kit_image']['error'] == 0) {
                $upload_dir = $kit_upload_dir;
                $folder_id = kitValidateFolderForCategory($connect, isset($_POST['folder_id']) ? $_POST['folder_id'] : 0, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
                $folder_sql = kitFolderIdSql($folder_id);
                
                // Check if directory exists, create if not
                if (!file_exists($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $error_message = "Failed to create upload directory. Please check server permissions.";
                    }
                }
                
                // Check if directory is writable
                if (file_exists($upload_dir) && !is_writable($upload_dir)) {
                    $error_message = "Upload directory is not writable. Please set permissions to 755 or 777 on: " . $upload_dir;
                }
                
                if (!isset($error_message)) {
                    $file_extension = strtolower(pathinfo($_FILES['kit_image']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    $max_file_size = 10 * 1024 * 1024; // 10MB in bytes
                    
                    // Check file size
                    if ($_FILES['kit_image']['size'] > $max_file_size) {
                        $error_message = "Image size too large. Maximum allowed size is 10MB. Your file size: " . formatFileSize($_FILES['kit_image']['size']);
                    } elseif (in_array($file_extension, $allowed_extensions)) {
                        $new_filename = 'kit_image_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['kit_image']['tmp_name'], $upload_path)) {
                            $title = mysqli_real_escape_string($connect, isset($_POST['image_title']) ? $_POST['image_title'] : '');
                            $display_order = intval(isset($_POST['image_order']) ? $_POST['image_order'] : 0);
                            
                            // Category from hidden field or current tab
                            $kit_category = mysqli_real_escape_string($connect, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
                            $insert_query = "INSERT INTO franchisee_kit (type, title, file_path, display_order, status, category, folder_id) 
                                           VALUES ('image', '$title', '$new_filename', $display_order, 'active', '$kit_category', $folder_sql)";
                            
                            if (mysqli_query($connect, $insert_query)) {
                                $success_message = "Image uploaded successfully!";
                            } else {
                                $error_message = "Error saving image details: " . mysqli_error($connect);
                            }
                        } else {
                            $error_message = "Error uploading image file. Upload directory: " . $upload_dir . " (writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . ")";
                        }
                    } else {
                        $error_message = "Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.";
                    }
                }
            } else {
                $error_message = "Please select an image file.";
            }
        }
        
        elseif ($action == 'add_video') {
            // Handle video URL addition
            $video_url = mysqli_real_escape_string($connect, isset($_POST['video_url']) ? $_POST['video_url'] : '');
            $video_title = mysqli_real_escape_string($connect, isset($_POST['video_title']) ? $_POST['video_title'] : '');
            $display_order = intval(isset($_POST['video_order']) ? $_POST['video_order'] : 0);
            $kit_category = mysqli_real_escape_string($connect, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
            $folder_id = kitValidateFolderForCategory($connect, isset($_POST['folder_id']) ? $_POST['folder_id'] : 0, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
            $folder_sql = kitFolderIdSql($folder_id);
            
            if (!empty($video_url)) {
                $insert_query = "INSERT INTO franchisee_kit (type, title, video_url, display_order, status, category, folder_id) 
                               VALUES ('video', '$video_title', '$video_url', $display_order, 'active', '$kit_category', $folder_sql)";
                
                if (mysqli_query($connect, $insert_query)) {
                    $success_message = "Video link added successfully!";
                } else {
                    $error_message = "Error saving video details: " . mysqli_error($connect);
                }
            } else {
                $error_message = "Please enter a video URL.";
            }
        }

        elseif ($action == 'add_video_file') {
            if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] == 0) {
                $upload_dir = $kit_upload_dir;
                $folder_id = kitValidateFolderForCategory($connect, isset($_POST['folder_id']) ? $_POST['folder_id'] : 0, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
                $folder_sql = kitFolderIdSql($folder_id);

                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                if (file_exists($upload_dir) && !is_writable($upload_dir)) {
                    $error_message = "Upload directory is not writable.";
                } else {
                    $file_extension = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['mp4', 'webm', 'mov', 'avi'];
                    $max_file_size = 50 * 1024 * 1024;

                    if ($_FILES['video_file']['size'] > $max_file_size) {
                        $error_message = "Video size too large. Maximum allowed size is 50MB.";
                    } elseif (in_array($file_extension, $allowed_extensions)) {
                        $new_filename = 'kit_video_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;

                        if (move_uploaded_file($_FILES['video_file']['tmp_name'], $upload_path)) {
                            $title = mysqli_real_escape_string($connect, isset($_POST['video_file_title']) ? $_POST['video_file_title'] : '');
                            $display_order = intval(isset($_POST['video_file_order']) ? $_POST['video_file_order'] : 0);
                            $kit_category = mysqli_real_escape_string($connect, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
                            $insert_query = "INSERT INTO franchisee_kit (type, title, file_path, display_order, status, category, folder_id)
                                           VALUES ('video', '$title', '$new_filename', $display_order, 'active', '$kit_category', $folder_sql)";

                            if (mysqli_query($connect, $insert_query)) {
                                $success_message = "Video uploaded successfully!";
                            } else {
                                $error_message = "Error saving video details: " . mysqli_error($connect);
                            }
                        } else {
                            $error_message = "Error uploading video file.";
                        }
                    } else {
                        $error_message = "Invalid file type. Only MP4, WEBM, MOV, and AVI files are allowed.";
                    }
                }
            } else {
                $error_message = "Please select a video file.";
            }
        }
        
        elseif ($action == 'add_file') {
            // Handle file upload
            if (isset($_FILES['file_upload'])) {
                $file_error = $_FILES['file_upload']['error'];
                
                // Check for upload errors
                if ($file_error == 0) {
                    $upload_dir = $kit_upload_dir;
                    $folder_id = kitValidateFolderForCategory($connect, isset($_POST['folder_id']) ? $_POST['folder_id'] : 0, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
                    $folder_sql = kitFolderIdSql($folder_id);
                    
                    // Check if directory exists, create if not
                    if (!file_exists($upload_dir)) {
                        if (!mkdir($upload_dir, 0755, true)) {
                            $error_message = "Failed to create upload directory. Please check server permissions.";
                        }
                    }
                    
                    // Check if directory is writable
                    if (file_exists($upload_dir) && !is_writable($upload_dir)) {
                        $error_message = "Upload directory is not writable. Please set permissions to 755 or 777 on: " . $upload_dir;
                    }
                    
                    if (!isset($error_message)) {
                        $file_extension = strtolower(pathinfo($_FILES['file_upload']['name'], PATHINFO_EXTENSION));
                        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'mp4', 'avi', 'mov', 'mp3', 'wav'];
                        $max_file_size = 10 * 1024 * 1024; // 10MB in bytes
                        
                        // Check file size
                        if ($_FILES['file_upload']['size'] > $max_file_size) {
                            $error_message = "File size too large. Maximum allowed size is 10MB. Your file size: " . formatFileSize($_FILES['file_upload']['size']);
                        } elseif (in_array($file_extension, $allowed_extensions)) {
                            $new_filename = 'kit_file_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                            $upload_path = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $upload_path)) {
                            $title = mysqli_real_escape_string($connect, isset($_POST['file_title']) ? $_POST['file_title'] : '');
                            $display_order = intval(isset($_POST['file_order']) ? $_POST['file_order'] : 0);
                            
                            $kit_category = mysqli_real_escape_string($connect, isset($_POST['kit_category']) ? $_POST['kit_category'] : $current_kit);
                            $insert_query = "INSERT INTO franchisee_kit (type, title, file_path, display_order, status, category, folder_id) 
                                           VALUES ('file', '$title', '$new_filename', $display_order, 'active', '$kit_category', $folder_sql)";
                            
                            if (mysqli_query($connect, $insert_query)) {
                                $success_message = "File uploaded successfully!";
                            } else {
                                $error_message = "Error saving file details: " . mysqli_error($connect);
                            }
                        } else {
                            $error_message = "Error uploading file. Please check file permissions. Upload directory: " . $upload_dir . " (writable: " . (is_writable($upload_dir) ? 'Yes' : 'No') . ")";
                        }
                    } else {
                        $error_message = "Invalid file type. Only PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, MP4, AVI, MOV, MP3, WAV files are allowed. Your file: ." . $file_extension;
                    }
                }
                } else {
                    // Handle specific upload errors
                    switch ($file_error) {
                        case 1:
                            $error_message = "File too large. Please select a smaller file.";
                            break;
                        case 2:
                            $error_message = "File too large. Please select a smaller file.";
                            break;
                        case 3:
                            $error_message = "File upload was interrupted. Please try again.";
                            break;
                        case 4:
                            $error_message = "Please select a file.";
                            break;
                        case 6:
                            $error_message = "Server error. Please try again later.";
                            break;
                        case 7:
                            $error_message = "Server error. Please try again later.";
                            break;
                        case 8:
                            $error_message = "File upload stopped. Please try again.";
                            break;
                        default:
                            $error_message = "Upload error. Please try again.";
                    }
                }
            } else {
                $error_message = "Please select a file.";
            }
        }
        
        elseif ($action == 'delete_item') {
            $item_id = intval($_POST['item_id']);
            $item_type = mysqli_real_escape_string($connect, $_POST['item_type']);
            
            // Get file path if it's an image, file, or uploaded video
            if ($item_type == 'image' || $item_type == 'file' || $item_type == 'video') {
                $get_file_query = "SELECT file_path FROM franchisee_kit WHERE id = $item_id";
                $file_result = mysqli_query($connect, $get_file_query);
                if ($file_result && $file_row = mysqli_fetch_array($file_result)) {
                    if (!empty($file_row['file_path'])) {
                        $file_path = $kit_upload_dir . $file_row['file_path'];
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                }
            }
            
            $delete_query = "DELETE FROM franchisee_kit WHERE id = $item_id";
            if (mysqli_query($connect, $delete_query)) {
                $success_message = "Item deleted successfully!";
            } else {
                $error_message = "Error deleting item: " . mysqli_error($connect);
            }
        }
        
        elseif ($action == 'update_status') {
            $item_id = intval($_POST['item_id']);
            $new_status = mysqli_real_escape_string($connect, $_POST['new_status']);
            
            $update_query = "UPDATE franchisee_kit SET status = '$new_status' WHERE id = $item_id";
            if (mysqli_query($connect, $update_query)) {
                $success_message = "Status updated successfully!";
            } else {
                $error_message = "Error updating status: " . mysqli_error($connect);
            }
        }
        
        elseif ($action == 'move_item') {
            $item_id = intval($_POST['item_id']);
            $folder_id = kitValidateFolderForCategory($connect, isset($_POST['move_folder_id']) ? $_POST['move_folder_id'] : 0, $current_kit);
            $folder_sql = kitFolderIdSql($folder_id);

            $update_query = "UPDATE franchisee_kit SET folder_id = $folder_sql WHERE id = $item_id";
            if (mysqli_query($connect, $update_query)) {
                $success_message = $folder_id > 0 ? "Item moved to folder successfully!" : "Item removed from folder (uncategorized).";
            } else {
                $error_message = "Error moving item: " . mysqli_error($connect);
            }
        }

        elseif ($action == 'edit_image') {
            $item_id = intval($_POST['item_id']);
            $title = mysqli_real_escape_string($connect, isset($_POST['edit_image_title']) ? $_POST['edit_image_title'] : '');
            $display_order = intval(isset($_POST['edit_image_order']) ? $_POST['edit_image_order'] : 0);
            $status = mysqli_real_escape_string($connect, isset($_POST['edit_image_status']) ? $_POST['edit_image_status'] : 'active');
            $folder_id = kitValidateFolderForCategory($connect, isset($_POST['edit_image_folder_id']) ? $_POST['edit_image_folder_id'] : 0, $current_kit);
            $folder_sql = kitFolderIdSql($folder_id);
            
            // Handle new image upload if provided
            $image_update = '';
            if (isset($_FILES['edit_kit_image']) && $_FILES['edit_kit_image']['error'] == 0) {
                $upload_dir = $kit_upload_dir;
                $file_extension = strtolower(pathinfo($_FILES['edit_kit_image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    $max_file_size = 10 * 1024 * 1024; // 10MB in bytes
                
                // Check file size
                if ($_FILES['edit_kit_image']['size'] > $max_file_size) {
                    $error_message = "Image size too large. Maximum allowed size is 10MB. Your file size: " . formatFileSize($_FILES['edit_kit_image']['size']);
                    goto edit_end;
                } elseif (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = 'kit_image_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['edit_kit_image']['tmp_name'], $upload_path)) {
                        // Delete old image file
                        $old_file_query = "SELECT file_path FROM franchisee_kit WHERE id = $item_id";
                        $old_file_result = mysqli_query($connect, $old_file_query);
                        if ($old_file_result && $old_file_row = mysqli_fetch_array($old_file_result)) {
                            $old_file_path = $upload_dir . $old_file_row['file_path'];
                            if (file_exists($old_file_path)) {
                                unlink($old_file_path);
                            }
                        }
                        $image_update = ", file_path = '$new_filename'";
                    } else {
                        $error_message = "Error uploading new image file.";
                        goto edit_end;
                    }
                } else {
                    $error_message = "Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.";
                    goto edit_end;
                }
            }
            
            $update_query = "UPDATE franchisee_kit SET title = '$title', display_order = $display_order, status = '$status', folder_id = $folder_sql$image_update WHERE id = $item_id";
            if (mysqli_query($connect, $update_query)) {
                $success_message = "Image updated successfully!";
            } else {
                $error_message = "Error updating image: " . mysqli_error($connect);
            }
            edit_end:
        }
        
        elseif ($action == 'edit_video') {
            $item_id = intval($_POST['item_id']);
            $title = mysqli_real_escape_string($connect, isset($_POST['edit_video_title']) ? $_POST['edit_video_title'] : '');
            $video_url = mysqli_real_escape_string($connect, isset($_POST['edit_video_url']) ? $_POST['edit_video_url'] : '');
            $display_order = intval(isset($_POST['edit_video_order']) ? $_POST['edit_video_order'] : 0);
            $status = mysqli_real_escape_string($connect, isset($_POST['edit_video_status']) ? $_POST['edit_video_status'] : 'active');
            $folder_id = kitValidateFolderForCategory($connect, isset($_POST['edit_video_folder_id']) ? $_POST['edit_video_folder_id'] : 0, $current_kit);
            $folder_sql = kitFolderIdSql($folder_id);

            $existing_res = mysqli_query($connect, "SELECT file_path FROM franchisee_kit WHERE id = $item_id LIMIT 1");
            $has_uploaded_file = false;
            if ($existing_res && $existing_row = mysqli_fetch_assoc($existing_res)) {
                $has_uploaded_file = !empty($existing_row['file_path']);
            }

            if (!empty($video_url) || $has_uploaded_file) {
                $url_update = !empty($video_url) ? ", video_url = '$video_url'" : '';
                $update_query = "UPDATE franchisee_kit SET title = '$title', display_order = $display_order, status = '$status', folder_id = $folder_sql$url_update WHERE id = $item_id";
                if (mysqli_query($connect, $update_query)) {
                    $success_message = "Video updated successfully!";
                } else {
                    $error_message = "Error updating video: " . mysqli_error($connect);
                }
            } else {
                $error_message = "Please enter a video URL or keep the uploaded video file.";
            }
        }
        
        elseif ($action == 'edit_file') {
            $item_id = intval($_POST['item_id']);
            $title = mysqli_real_escape_string($connect, isset($_POST['edit_file_title']) ? $_POST['edit_file_title'] : '');
            $display_order = intval(isset($_POST['edit_file_order']) ? $_POST['edit_file_order'] : 0);
            $status = mysqli_real_escape_string($connect, isset($_POST['edit_file_status']) ? $_POST['edit_file_status'] : 'active');
            $folder_id = kitValidateFolderForCategory($connect, isset($_POST['edit_file_folder_id']) ? $_POST['edit_file_folder_id'] : 0, $current_kit);
            $folder_sql = kitFolderIdSql($folder_id);
            
            // Handle new file upload if provided
            $file_update = '';
            if (isset($_FILES['edit_file_upload']) && $_FILES['edit_file_upload']['error'] == 0) {
                $upload_dir = $kit_upload_dir;
                $file_extension = strtolower(pathinfo($_FILES['edit_file_upload']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'mp4', 'avi', 'mov', 'mp3', 'wav'];
                
                    $max_file_size = 10 * 1024 * 1024; // 10MB in bytes
                
                // Check file size
                if ($_FILES['edit_file_upload']['size'] > $max_file_size) {
                    $error_message = "File size too large. Maximum allowed size is 10MB. Your file size: " . formatFileSize($_FILES['edit_file_upload']['size']);
                    goto edit_file_end;
                } elseif (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = 'kit_file_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['edit_file_upload']['tmp_name'], $upload_path)) {
                        // Delete old file
                        $old_file_query = "SELECT file_path FROM franchisee_kit WHERE id = $item_id";
                        $old_file_result = mysqli_query($connect, $old_file_query);
                        if ($old_file_result && $old_file_row = mysqli_fetch_array($old_file_result)) {
                            $old_file_path = $upload_dir . $old_file_row['file_path'];
                            if (file_exists($old_file_path)) {
                                unlink($old_file_path);
                            }
                        }
                        $file_update = ", file_path = '$new_filename'";
                    } else {
                        $error_message = "Error uploading new file.";
                        goto edit_file_end;
                    }
                } else {
                    $error_message = "Invalid file type. Only PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, MP4, AVI, MOV, MP3, WAV files are allowed.";
                    goto edit_file_end;
                }
            }
            
            $update_query = "UPDATE franchisee_kit SET title = '$title', display_order = $display_order, status = '$status', folder_id = $folder_sql$file_update WHERE id = $item_id";
            if (mysqli_query($connect, $update_query)) {
                $success_message = "File updated successfully!";
            } else {
                $error_message = "Error updating file: " . mysqli_error($connect);
            }
            edit_file_end:
        }
    }
}

// Get all kit items for current category
$kit_items_query = "SELECT * FROM franchisee_kit WHERE category='" . mysqli_real_escape_string($connect, $current_kit) . "' ORDER BY display_order ASC, created_at DESC";
$kit_items_result = mysqli_query($connect, $kit_items_query);
$kit_items = [];
if ($kit_items_result) {
    while ($row = mysqli_fetch_assoc($kit_items_result)) {
        $kit_items[] = $row;
    }
}

// Get folders for current category
$kit_folders = [];
$folders_query = "SELECT f.*, (SELECT COUNT(*) FROM franchisee_kit k WHERE k.folder_id = f.id) AS item_count
                  FROM franchisee_kit_folders f
                  WHERE f.category='" . mysqli_real_escape_string($connect, $current_kit) . "'
                  ORDER BY f.display_order ASC, f.title ASC";
$folders_result = mysqli_query($connect, $folders_query);
if ($folders_result) {
    while ($row = mysqli_fetch_assoc($folders_result)) {
        $kit_folders[] = $row;
    }
}

$items_by_folder = [];
$uncategorized_items = [];
foreach ($kit_items as $item) {
    if (!empty($item['folder_id'])) {
        $fid = (int)$item['folder_id'];
        if (!isset($items_by_folder[$fid])) {
            $items_by_folder[$fid] = [];
        }
        $items_by_folder[$fid][] = $item;
    } else {
        $uncategorized_items[] = $item;
    }
}

$folder_children_map = kitBuildFolderChildrenMap($kit_folders);

// Explorer navigation state (Windows-style folder browsing)
$kit_folders_by_id = kitAdminFoldersById($kit_folders);
$current_folder_id = (int)($_GET['folder'] ?? 0);
if ($current_folder_id > 0 && !isset($kit_folders_by_id[$current_folder_id])) {
    $current_folder_id = 0; // invalid id or belongs to another category
}
$kit_breadcrumb = kitAdminBreadcrumb($current_folder_id, $kit_folders_by_id);
$current_subfolders = isset($folder_children_map[$current_folder_id]) ? $folder_children_map[$current_folder_id] : [];
$current_folder_items = ($current_folder_id === 0)
    ? $uncategorized_items
    : (isset($items_by_folder[$current_folder_id]) ? $items_by_folder[$current_folder_id] : []);

function renderKitFolderCard($folder, $children_map, $items_by_folder, $kit_upload_dir, $depth = 0) {
    $folder_id = (int)$folder['id'];
    $folder_items = isset($items_by_folder[$folder_id]) ? $items_by_folder[$folder_id] : [];
    $folder_images = array_filter($folder_items, function($item) { return $item['type'] === 'image'; });
    $folder_videos = array_filter($folder_items, function($item) { return $item['type'] === 'video'; });
    $subfolder_count = isset($children_map[$folder_id]) ? count($children_map[$folder_id]) : 0;
    $margin_left = $depth > 0 ? ($depth * 24) : 0;
    ?>
    <div class="folder-card" style="<?php echo $margin_left > 0 ? 'margin-left:' . $margin_left . 'px;' : ''; ?>">
        <div class="folder-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-1" style="font-size: <?php echo $depth > 0 ? '1rem' : '1.25rem'; ?>;">
                    <i class="fas fa-<?php echo $depth > 0 ? 'folder' : 'folder-open'; ?> text-warning me-2"></i>
                    <?php echo htmlspecialchars($folder['title']); ?>
                    <?php if ($depth > 0): ?><span class="badge bg-secondary ms-1">Subfolder</span><?php endif; ?>
                </h5>
                <small class="text-muted">
                    <?php echo count($folder_images); ?> images · <?php echo count($folder_videos); ?> videos · <?php echo $subfolder_count; ?> subfolders · Order: <?php echo (int)$folder['display_order']; ?>
                </small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#createFolderModal" onclick="openCreateSubfolder(<?php echo $folder_id; ?>)">
                    <i class="fas fa-folder-plus me-1"></i>Subfolder
                </button>
                <button type="button" class="btn btn-sm btn-custom-warning" data-bs-toggle="modal" data-bs-target="#addImageModal" onclick="setModalFolder('image_folder_id', '<?php echo $folder_id; ?>')">
                    <i class="fas fa-image me-1"></i>Add Image
                </button>
                <button type="button" class="btn btn-sm btn-custom-success" data-bs-toggle="modal" data-bs-target="#addVideoModal" onclick="setModalFolder('video_folder_id', '<?php echo $folder_id; ?>')">
                    <i class="fas fa-link me-1"></i>Video Link
                </button>
                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addVideoFileModal" onclick="setModalFolder('video_file_folder_id', '<?php echo $folder_id; ?>')">
                    <i class="fas fa-upload me-1"></i>Upload Video
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editFolder(<?php echo $folder_id; ?>)">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteFolder(<?php echo $folder_id; ?>, '<?php echo htmlspecialchars(addslashes($folder['title'])); ?>')">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <div class="folder-card-body">
            <?php if (!empty($folder_items)): ?>
                <div class="row g-4">
                    <?php foreach ($folder_items as $item) { renderKitFolderItem($item, $kit_upload_dir); } ?>
                </div>
            <?php elseif ($subfolder_count === 0): ?>
                <div class="folder-empty">
                    <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                    <p class="mb-0">This folder is empty. Add images, videos, or create a subfolder.</p>
                </div>
            <?php endif; ?>

            <?php if ($subfolder_count > 0 && isset($children_map[$folder_id])): ?>
                <div class="mt-3">
                    <?php foreach ($children_map[$folder_id] as $child_folder) {
                        renderKitFolderCard($child_folder, $children_map, $items_by_folder, $kit_upload_dir, $depth + 1);
                    } ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function renderKitFolderItem($item, $kit_upload_dir) {
    $type = $item['type'];
    if ($type === 'image') {
        ?>
        <div class="col-md-4 col-lg-3">
            <div class="kit-item-card">
                <img src="<?php echo htmlspecialchars($kit_upload_dir . $item['file_path']); ?>"
                     class="kit-image"
                     alt="<?php echo htmlspecialchars($item['title'] ?: 'Untitled'); ?>">
                <div class="card-body p-3">
                    <h6 class="card-title mb-2"><?php echo htmlspecialchars($item['title'] ?: 'Untitled Image'); ?></h6>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <small class="text-muted">Order: <?php echo (int)$item['display_order']; ?></small>
                        <span class="status-badge <?php echo $item['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo ucfirst($item['status']); ?>
                        </span>
                    </div>
                    <div class="action-buttons">
                        <button type="button" class="btn btn-outline-secondary btn-action" onclick="openMoveItem(<?php echo (int)$item['id']; ?>, '<?php echo $type; ?>', <?php echo (int)(isset($item['folder_id']) ? $item['folder_id'] : 0); ?>)" title="Move to Folder"><i class="fas fa-folder"></i></button>
                        <button type="button" class="btn btn-outline-primary btn-action" onclick="editItem(<?php echo (int)$item['id']; ?>, '<?php echo $type; ?>')" title="Edit"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-outline-<?php echo $item['status'] == 'active' ? 'warning' : 'success'; ?> btn-action" onclick="toggleStatus(<?php echo (int)$item['id']; ?>, '<?php echo $item['status']; ?>')" title="Toggle"><i class="fas fa-<?php echo $item['status'] == 'active' ? 'eye-slash' : 'eye'; ?>"></i></button>
                        <button type="button" class="btn btn-outline-danger btn-action" onclick="deleteItem(<?php echo (int)$item['id']; ?>, '<?php echo $type; ?>')" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } elseif ($type === 'video') {
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="kit-video-card">
                <div class="mb-3">
                    <?php if (!empty($item['file_path'])): ?>
                        <video controls style="width:100%;max-height:180px;border-radius:8px;">
                            <source src="<?php echo htmlspecialchars($kit_upload_dir . $item['file_path']); ?>">
                        </video>
                    <?php else: ?>
                        <i class="fas fa-play-circle" style="font-size: 3rem; color: #667eea;"></i>
                    <?php endif; ?>
                </div>
                <h6 class="card-title mb-2"><?php echo htmlspecialchars($item['title'] ?: 'Untitled Video'); ?></h6>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <small class="text-muted">Order: <?php echo (int)$item['display_order']; ?></small>
                    <span class="status-badge <?php echo $item['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo ucfirst($item['status']); ?>
                    </span>
                </div>
                <?php if (!empty($item['video_url'])): ?>
                    <p class="card-text mb-3"><small class="text-break"><?php echo htmlspecialchars($item['video_url']); ?></small></p>
                <?php endif; ?>
                <div class="action-buttons">
                    <button type="button" class="btn btn-outline-secondary btn-action" onclick="openMoveItem(<?php echo (int)$item['id']; ?>, 'video', <?php echo (int)(isset($item['folder_id']) ? $item['folder_id'] : 0); ?>)" title="Move to Folder"><i class="fas fa-folder"></i></button>
                    <button type="button" class="btn btn-outline-primary btn-action" onclick="editItem(<?php echo (int)$item['id']; ?>, 'video')" title="Edit"><i class="fas fa-edit"></i></button>
                    <button type="button" class="btn btn-outline-<?php echo $item['status'] == 'active' ? 'warning' : 'success'; ?> btn-action" onclick="toggleStatus(<?php echo (int)$item['id']; ?>, '<?php echo $item['status']; ?>')" title="Toggle"><i class="fas fa-<?php echo $item['status'] == 'active' ? 'eye-slash' : 'eye'; ?>"></i></button>
                    <button type="button" class="btn btn-outline-danger btn-action" onclick="deleteItem(<?php echo (int)$item['id']; ?>, 'video')" title="Delete"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        </div>
        <?php
    } elseif ($type === 'file') {
        $file_extension = strtoupper(pathinfo($item['file_path'], PATHINFO_EXTENSION));
        $folder_of = (int)(isset($item['folder_id']) ? $item['folder_id'] : 0);
        ?>
        <div class="col-md-4 col-lg-3">
            <div class="kit-item-card h-100">
                <div class="card-body p-3 text-center">
                    <div class="mb-2"><i class="fas fa-file-alt fa-3x text-primary"></i></div>
                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($item['title'] ?: 'Untitled File'); ?></h6>
                    <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars($file_extension); ?> File</p>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <small class="text-muted">Order: <?php echo (int)$item['display_order']; ?></small>
                        <span class="status-badge <?php echo $item['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>"><?php echo ucfirst($item['status']); ?></span>
                    </div>
                    <div class="action-buttons">
                        <a href="../assets/upload/kits/<?php echo htmlspecialchars($item['file_path']); ?>" target="_blank" class="btn btn-outline-info btn-action" title="Download"><i class="fas fa-download"></i></a>
                        <button type="button" class="btn btn-outline-secondary btn-action" onclick="openMoveItem(<?php echo (int)$item['id']; ?>, 'file', <?php echo $folder_of; ?>)" title="Move to Folder"><i class="fas fa-folder"></i></button>
                        <button type="button" class="btn btn-outline-primary btn-action" onclick="editItem(<?php echo (int)$item['id']; ?>, 'file')" title="Edit"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-outline-<?php echo $item['status'] == 'active' ? 'warning' : 'success'; ?> btn-action" onclick="toggleStatus(<?php echo (int)$item['id']; ?>, '<?php echo $item['status']; ?>')" title="Toggle"><i class="fas fa-<?php echo $item['status'] == 'active' ? 'eye-slash' : 'eye'; ?>"></i></button>
                        <button type="button" class="btn btn-outline-danger btn-action" onclick="deleteItem(<?php echo (int)$item['id']; ?>, 'file')" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

// ---- Windows-style explorer helpers (admin) ----
function kitAdminExplorerUrl($current_kit, $folder_id) {
    $params = ['kit' => $current_kit];
    if ((int)$folder_id > 0) {
        $params['folder'] = (int)$folder_id;
    }
    return '?' . http_build_query($params);
}

function kitAdminFoldersById($folders) {
    $map = [];
    foreach ($folders as $f) {
        $map[(int)$f['id']] = $f;
    }
    return $map;
}

function kitAdminBreadcrumb($folder_id, $folders_by_id) {
    $crumbs = [];
    $current = (int)$folder_id;
    $guard = 0;
    while ($current > 0 && isset($folders_by_id[$current]) && $guard < 50) {
        $crumbs[] = $folders_by_id[$current];
        $current = !empty($folders_by_id[$current]['parent_id']) ? (int)$folders_by_id[$current]['parent_id'] : 0;
        $guard++;
    }
    return array_reverse($crumbs);
}

function kitAdminWinFolderSvg() {
    return '<svg class="win-folder-svg" viewBox="0 0 48 40" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<path d="M3 6a3 3 0 0 1 3-3h11.2a3 3 0 0 1 2.1.9L23 7h19a3 3 0 0 1 3 3v3H3z" fill="#E8A33D"/>'
        . '<path d="M3 11a3 3 0 0 1 3-3h36a3 3 0 0 1 3 3v23a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3z" fill="#FBC55B"/>'
        . '<path d="M3 12h42v2H3z" fill="#ffffff" opacity="0.25"/>'
        . '</svg>';
}

function renderKitAdminFolderTile($folder, $children_map, $items_by_folder, $current_kit) {
    $fid = (int)$folder['id'];
    $direct_items = isset($items_by_folder[$fid]) ? count($items_by_folder[$fid]) : 0;
    $subfolders = isset($children_map[$fid]) ? count($children_map[$fid]) : 0;
    $url = kitAdminExplorerUrl($current_kit, $fid);
    ?>
    <div class="col-6 col-sm-4 col-md-3 col-lg-2">
        <div class="kit-folder-tile" title="<?php echo htmlspecialchars($folder['title']); ?>">
            <a class="kit-folder-link" href="<?php echo htmlspecialchars($url); ?>">
                <span class="kit-folder-icon" aria-hidden="true"><?php echo kitAdminWinFolderSvg(); ?></span>
                <span class="kit-folder-name"><?php echo htmlspecialchars($folder['title']); ?></span>
                <span class="kit-folder-count"><?php echo $subfolders; ?> sub · <?php echo $direct_items; ?> items</span>
            </a>
            <div class="kit-folder-actions">
                <button type="button" class="btn btn-sm btn-light" onclick="editFolder(<?php echo $fid; ?>)" title="Rename"><i class="fas fa-edit"></i></button>
                <button type="button" class="btn btn-sm btn-light text-danger" onclick="deleteFolder(<?php echo $fid; ?>, '<?php echo htmlspecialchars(addslashes($folder['title'])); ?>')" title="Delete"><i class="fas fa-trash"></i></button>
            </div>
        </div>
    </div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Kit Management - Admin Panel</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 20px;
        }
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            overflow: hidden;
        }
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            border: none;
        }
        .btn-custom-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-custom-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-custom-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-custom-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
        .btn-custom-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-custom-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
            color: white;
        }
        /* Windows-style folder tiles */
        .kit-admin-breadcrumb { font-size: 0.95rem; }
        .kit-admin-breadcrumb a { color: #fff; text-decoration: none; opacity: 0.95; }
        .kit-admin-breadcrumb a:hover { text-decoration: underline; }
        .kit-admin-breadcrumb .crumb-sep { margin: 0 0.4rem; opacity: 0.75; }
        .kit-admin-breadcrumb .crumb-current { font-weight: 600; }
        .kit-folder-tile {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 1.25rem 0.75rem 1rem;
            border: 1px solid #eef0f4;
            border-radius: 12px;
            background: #fff;
            transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, transform 0.12s ease;
            min-height: 180px;
            height: 100%;
        }
        .kit-folder-tile:hover {
            background: #fff8eb;
            border-color: #fcd34d;
            box-shadow: 0 6px 16px rgba(234, 88, 12, 0.15);
            transform: translateY(-2px);
        }
        .kit-folder-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none !important;
            color: inherit;
            width: 100%;
        }
        .kit-folder-icon {
            font-size: 5.5rem;
            line-height: 1;
            margin-bottom: 0.5rem;
            filter: drop-shadow(0 3px 4px rgba(0,0,0,0.12));
        }
        .kit-folder-icon .win-folder-svg { width: 1em; height: auto; display: block; }
        .kit-folder-name {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.3;
            word-break: break-word;
            max-width: 100%;
        }
        .kit-folder-count { font-size: 0.78rem; color: #64748b; margin-top: 0.3rem; }
        .kit-folder-actions {
            position: absolute;
            top: 6px;
            right: 6px;
            display: flex;
            gap: 4px;
            opacity: 0;
            transition: opacity 0.15s ease;
        }
        .kit-folder-tile:hover .kit-folder-actions { opacity: 1; }
        .kit-folder-actions .btn { padding: 2px 7px; border: 1px solid #e2e8f0; }
        @media (max-width: 575.98px) {
            .kit-folder-icon { font-size: 4rem; }
            .kit-folder-tile { min-height: 140px; }
            .kit-folder-actions { opacity: 1; }
        }
        .kit-item-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        .kit-item-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .kit-image {
            height: 200px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
            width: 100%;
        }
        .kit-video-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .kit-video-card:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f2ff 0%, #e8ecff 100%);
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .status-inactive {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .btn-action {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .btn-action:hover {
            transform: translateY(-1px);
        }
        .modal-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .modal-header-custom .btn-close {
            filter: invert(1);
        }
        .form-control-custom {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control-custom:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .stats-overview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 18px 20px;
            margin-bottom: 30px;
        }
        .stats-overview .stats-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
        }
        .stats-overview .stat-col {
            flex: 1 1 0;
            min-width: 0;
        }
        .stat-item {
            text-align: center;
            padding: 8px 6px;
        }
        .stat-number {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 3px;
        }
        .stat-label {
            font-size: 0.75rem;
            opacity: 0.9;
            white-space: nowrap;
        }
        @media (max-width: 575.98px) {
            .stats-overview .stats-row { flex-wrap: wrap; }
            .stats-overview .stat-col { flex: 1 1 33.333%; }
            .stat-number { font-size: 1.3rem; }
            .stat-label { font-size: 0.7rem; }
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        .folder-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 24px;
            overflow: hidden;
            background: #fff;
        }
        .folder-card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #eef1f7 100%);
            padding: 18px 22px;
            border-bottom: 1px solid #e9ecef;
        }
        .folder-card-body {
            padding: 20px;
        }
        .folder-empty {
            text-align: center;
            color: #6c757d;
            padding: 30px 15px;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
        }
        .folder-card .folder-card {
            margin-top: 16px;
            border-color: #d0d7de;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="d-flex align-items-center mb-2">
                        <a href="index.php" class="btn btn-outline-secondary me-3">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                        <h2 class="mb-0"><i class="fas fa-toolbox me-2"></i>Kit Management</h2>
                    </div>
                    <p class="text-muted mb-0">Manage promotional materials and resources for franchisees</p>
                </div>
            </div>
        </div>

        <!-- Kit Tabs -->
        <ul class="nav nav-tabs content-tabs" role="tablist" style="margin-top:-10px;gap:8px;border-bottom:none;">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $current_kit=='sales' ? 'active' : ''; ?>" href="?kit=sales" style="border:1px solid #e9ecef;border-bottom-width:3px;border-radius:10px;padding:10px 16px;">
                    <i class="fas fa-briefcase me-2"></i><?php echo kitLabel('sales'); ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo $kit_counts['sales']; ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $current_kit=='marketing' ? 'active' : ''; ?>" href="?kit=marketing" style="border:1px solid #e9ecef;border-bottom-width:3px;border-radius:10px;padding:10px 16px;">
                    <i class="fas fa-bullhorn me-2"></i><?php echo kitLabel('marketing'); ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo $kit_counts['marketing']; ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $current_kit=='franchise_sales' ? 'active' : ''; ?>" href="?kit=franchise_sales" style="border:1px solid #e9ecef;border-bottom-width:3px;border-radius:10px;padding:10px 16px;">
                    <i class="fas fa-handshake me-2"></i><?php echo kitLabel('franchise_sales'); ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo $kit_counts['franchise_sales']; ?></span>
                </a>
            </li>
        </ul>
        <style>
            .content-tabs .nav-link.active{
                background: #ffffff;
                border-color: #667eea !important;
                color: #667eea !important;
                box-shadow: 0 3px 10px rgba(102,126,234,0.15);
            }
            .content-tabs .nav-link{
                color: #495057;
                font-weight: 600;
                background:#fff;
            }
        </style>

        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stats-row">
                <div class="stat-col">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($kit_folders); ?></div>
                        <div class="stat-label">Folders</div>
                    </div>
                </div>
                <div class="stat-col">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($kit_items, function($item) { return $item['type'] == 'image'; })); ?></div>
                        <div class="stat-label">Images</div>
                    </div>
                </div>
                <div class="stat-col">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($kit_items, function($item) { return $item['type'] == 'video'; })); ?></div>
                        <div class="stat-label">Videos</div>
                    </div>
                </div>
                <div class="stat-col">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($kit_items, function($item) { return $item['type'] == 'file'; })); ?></div>
                        <div class="stat-label">Files</div>
                    </div>
                </div>
                <div class="stat-col">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count(array_filter($kit_items, function($item) { return $item['status'] == 'active'; })); ?></div>
                        <div class="stat-label">Active Items</div>
                    </div>
                </div>
                <div class="stat-col">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($kit_items); ?></div>
                        <div class="stat-label">Total Items</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Server Configuration Check -->
        <?php if (isset($error_message) && (strpos($error_message, 'too large') !== false || strpos($error_message, 'Upload error') !== false || strpos($error_message, 'permissions') !== false || strpos($error_message, 'writable') !== false)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong><i class="fas fa-info-circle me-2"></i>Server Configuration Diagnostics:</strong><br>
            <div class="row mt-2">
                <div class="col-md-6">
                    <strong>PHP Upload Limits:</strong><br>
                    Upload max size: <strong><?php echo ini_get('upload_max_filesize'); ?></strong><br>
                    Post max size: <strong><?php echo ini_get('post_max_size'); ?></strong><br>
                    Memory limit: <strong><?php echo ini_get('memory_limit'); ?></strong><br>
                </div>
                <div class="col-md-6">
                    <strong>Upload Directory Status:</strong><br>
                    Directory: <code><?php echo realpath('../assets/upload/kits/') ?: '../assets/upload/kits/'; ?></code><br>
                    Exists: <strong><?php echo file_exists('../assets/upload/kits/') ? 'Yes' : 'No'; ?></strong><br>
                    Writable: <strong><?php echo is_writable('../assets/upload/kits/') ? 'Yes' : 'No'; ?></strong><br>
                    <?php if (file_exists('../assets/upload/kits/')): ?>
                    Permissions: <strong><?php echo substr(sprintf('%o', fileperms('../assets/upload/kits/')), -4); ?></strong><br>
                    <?php endif; ?>
                </div>
            </div>
            <small class="mt-2 d-block">
                <?php 
                $upload_max = convertToBytes(ini_get('upload_max_filesize'));
                $post_max = convertToBytes(ini_get('post_max_size'));
                $required_upload = 10 * 1024 * 1024; // 10MB
                $required_post = 12 * 1024 * 1024; // 12MB
                $upload_dir = $kit_upload_dir;
                $has_php_issue = ($upload_max < $required_upload || $post_max < $required_post);
                $has_permission_issue = (file_exists($upload_dir) && !is_writable($upload_dir));
                
                if ($has_php_issue || $has_permission_issue): ?>
                    <strong>Action Required for Live Server:</strong><br>
                    <?php if ($has_php_issue): ?>
                    <strong>1. PHP Configuration:</strong> Contact your hosting provider or edit <code>php.ini</code> to set:<br>
                    <code>upload_max_filesize = 10M</code><br>
                    <code>post_max_size = 12M</code><br>
                    <em>Note: If using cPanel, you can also create/update <code>.user.ini</code> file in your root directory with these values.</em><br><br>
                    <?php endif; ?>
                    <?php if ($has_permission_issue): ?>
                    <strong>2. File Permissions:</strong> Set permissions on upload directory via FTP/cPanel File Manager:<br>
                    <code>chmod 755</code> or <code>chmod 777</code> on <code>assets/upload/kits/</code><br>
                    <em>In cPanel: Right-click folder → Change Permissions → Set to 755 (or 777 if 755 doesn't work)</em><br><br>
                    <?php endif; ?>
                    <?php if (!$has_php_issue && !$has_permission_issue): ?>
                    Your server configuration should allow 10MB uploads. If errors persist, check Apache error logs or contact your hosting provider.
                    <?php endif; ?>
                <?php else: ?>
                    Your server configuration appears correct. If you're still getting errors, check Apache/PHP error logs.
                <?php endif; ?>
            </small>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Kit Explorer (Windows-style folder browsing) -->
        <div class="content-card">
            <div class="card-header-custom">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <nav aria-label="Folder navigation" class="kit-admin-breadcrumb">
                        <a href="<?php echo htmlspecialchars(kitAdminExplorerUrl($current_kit, 0)); ?>"><i class="fas fa-home me-1"></i><?php echo htmlspecialchars(kitLabel($current_kit)); ?></a>
                        <?php foreach ($kit_breadcrumb as $crumb): ?>
                            <span class="crumb-sep">›</span>
                            <?php if ((int)$crumb['id'] === $current_folder_id): ?>
                                <span class="crumb-current"><?php echo htmlspecialchars($crumb['title']); ?></span>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars(kitAdminExplorerUrl($current_kit, (int)$crumb['id'])); ?>"><?php echo htmlspecialchars($crumb['title']); ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>
                    <span class="badge bg-light text-dark"><?php echo count($current_subfolders); ?> folders · <?php echo count($current_folder_items); ?> items</span>
                </div>
            </div>
            <div class="card-body p-4">
                <!-- Action toolbar (scoped to current folder) -->
                <div class="d-flex gap-2 flex-wrap mb-4">
                    <button type="button" class="btn btn-custom-primary" data-bs-toggle="modal" data-bs-target="#createFolderModal" onclick="openCreateSubfolder('<?php echo $current_folder_id; ?>')">
                        <i class="fas fa-folder-plus me-2"></i>Add Folder
                    </button>
                    <button type="button" class="btn btn-custom-warning" data-bs-toggle="modal" data-bs-target="#addImageModal" onclick="setModalFolder('image_folder_id', '<?php echo $current_folder_id; ?>')">
                        <i class="fas fa-image me-2"></i>Add Images
                    </button>
                    <button type="button" class="btn btn-custom-success" data-bs-toggle="modal" data-bs-target="#addVideoModal" onclick="setModalFolder('video_folder_id', '<?php echo $current_folder_id; ?>')">
                        <i class="fas fa-video me-2"></i>Add Video Link
                    </button>
                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addVideoFileModal" onclick="setModalFolder('video_file_folder_id', '<?php echo $current_folder_id; ?>')">
                        <i class="fas fa-upload me-2"></i>Upload Video
                    </button>
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addfileModal" onclick="setModalFolder('file_folder_id', '<?php echo $current_folder_id; ?>')">
                        <i class="fas fa-plus me-2"></i>Add File
                    </button>
                </div>

                <!-- Subfolders -->
                <?php if (!empty($current_subfolders)): ?>
                    <div class="kit-explorer-folders row g-3 mb-4">
                        <?php foreach ($current_subfolders as $folder) {
                            renderKitAdminFolderTile($folder, $folder_children_map, $items_by_folder, $current_kit);
                        } ?>
                    </div>
                <?php endif; ?>

                <!-- Items in the current folder -->
                <?php if (!empty($current_folder_items)): ?>
                    <div class="row g-4">
                        <?php foreach ($current_folder_items as $item) { renderKitFolderItem($item, $kit_upload_dir); } ?>
                    </div>
                <?php endif; ?>

                <!-- Empty state -->
                <?php if (empty($current_subfolders) && empty($current_folder_items)): ?>
                    <div class="empty-state py-4">
                        <i class="fas fa-folder-open"></i>
                        <h5><?php echo $current_folder_id === 0 ? 'No Folders or Items Yet' : 'This Folder is Empty'; ?></h5>
                        <p>Use <strong>Add Folder</strong> to create <?php echo $current_folder_id === 0 ? 'a folder' : 'a subfolder'; ?>, or add images, videos and files here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Create Folder Modal -->
    <div class="modal fade" id="createFolderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-folder-plus me-2"></i>Create Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="create_folder">
                        <input type="hidden" name="kit_category" value="<?php echo htmlspecialchars($current_kit); ?>">
                        <input type="hidden" name="parent_folder_id" id="parent_folder_id" value="">
                        <div class="mb-3">
                            <label for="parent_folder_select" class="form-label">Parent Folder (Optional)</label>
                            <select class="form-control form-control-custom" id="parent_folder_select" onchange="document.getElementById('parent_folder_id').value = this.value">
                                <option value="">None (main folder)</option>
                                <?php kitRenderFolderOptions($folder_children_map); ?>
                            </select>
                            <div class="form-text">Select a parent to create a subfolder inside it.</div>
                        </div>
                        <div class="mb-3">
                            <label for="folder_title" class="form-label">Folder Name</label>
                            <input type="text" class="form-control form-control-custom" id="folder_title" name="folder_title" placeholder="e.g. Product Photos, Training Videos" required>
                        </div>
                        <div class="mb-3">
                            <label for="folder_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control form-control-custom" id="folder_order" name="folder_order" value="0" min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-primary"><i class="fas fa-folder-plus me-2"></i>Create Folder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Folder Modal -->
    <div class="modal fade" id="editFolderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit_folder">
                        <input type="hidden" name="folder_id" id="edit_folder_id">
                        <input type="hidden" name="kit_category" value="<?php echo htmlspecialchars($current_kit); ?>">
                        <div class="mb-3">
                            <label for="edit_parent_folder_select" class="form-label">Parent Folder (Optional)</label>
                            <select class="form-control form-control-custom" id="edit_parent_folder_select" name="edit_parent_folder_id">
                                <option value="">None (main folder)</option>
                                <?php /* options filled by JS to exclude self/descendants */ ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_folder_title" class="form-label">Folder Name</label>
                            <input type="text" class="form-control form-control-custom" id="edit_folder_title" name="edit_folder_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_folder_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control form-control-custom" id="edit_folder_order" name="edit_folder_order" min="0">
                        </div>
                        <div class="mb-3">
                            <label for="edit_folder_status" class="form-label">Status</label>
                            <select class="form-control form-control-custom" id="edit_folder_status" name="edit_folder_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-primary"><i class="fas fa-save me-2"></i>Update Folder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload Video File Modal -->
    <div class="modal fade" id="addVideoFileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Upload Video File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add_video_file">
                        <input type="hidden" name="kit_category" value="<?php echo htmlspecialchars($current_kit); ?>">
                        <input type="hidden" name="folder_id" id="video_file_folder_id" value="">
                        <div class="mb-3">
                            <label for="video_file_folder_select" class="form-label">Folder (Optional)</label>
                            <select class="form-control form-control-custom" id="video_file_folder_select" onchange="setModalFolder('video_file_folder_id', this.value)">
                                <option value="">No folder (uncategorized)</option>
                                <?php kitRenderFolderOptions($folder_children_map); ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="video_file_title" class="form-label">Video Title</label>
                            <input type="text" class="form-control form-control-custom" id="video_file_title" name="video_file_title" placeholder="Enter a title for the video">
                        </div>
                        <div class="mb-3">
                            <label for="video_file" class="form-label">Select Video File</label>
                            <input type="file" class="form-control form-control-custom" id="video_file" name="video_file" accept="video/mp4,video/webm,video/quicktime,video/x-msvideo,.mp4,.webm,.mov,.avi" required>
                            <div class="form-text">Supported: MP4, WEBM, MOV, AVI (Max 50MB)</div>
                        </div>
                        <div class="mb-3">
                            <label for="video_file_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control form-control-custom" id="video_file_order" name="video_file_order" value="0" min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-success"><i class="fas fa-upload me-2"></i>Upload Video</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Folder Modal -->
    <div class="modal fade" id="deleteFolderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4 text-center">
                        <input type="hidden" name="action" value="delete_folder">
                        <input type="hidden" name="folder_id" id="delete_folder_id">
                        <i class="fas fa-folder-minus text-danger" style="font-size: 3rem; margin-bottom: 20px;"></i>
                        <h5>Delete "<span id="delete_folder_name"></span>"?</h5>
                        <p class="text-muted">All subfolders, images, and videos inside will also be permanently deleted.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Delete Folder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Image Modal -->
    <div class="modal fade" id="addImageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-image me-2"></i>Add New Promotional Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add_image">
                        <input type="hidden" name="kit_category" value="<?php echo htmlspecialchars($current_kit); ?>">
                        <input type="hidden" name="folder_id" id="image_folder_id" value="">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="image_folder_select" class="form-label">Folder (Optional)</label>
                                    <select class="form-control form-control-custom" id="image_folder_select" onchange="setModalFolder('image_folder_id', this.value)">
                                        <option value="">No folder (uncategorized)</option>
                                        <?php kitRenderFolderOptions($folder_children_map); ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="image_title" class="form-label">Image Title</label>
                                    <input type="text" class="form-control form-control-custom" id="image_title" name="image_title" 
                                           placeholder="Enter a descriptive title for the image">
                                </div>
                                <div class="mb-3">
                                    <label for="kit_image" class="form-label">Select Image File</label>
                                    <input type="file" class="form-control form-control-custom" id="kit_image" name="kit_image" 
                                           accept="image/*" required>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Supported formats: JPG, JPEG, PNG, GIF (Max size: 10MB)
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="image_order" class="form-label">Display Order</label>
                                    <input type="number" class="form-control form-control-custom" id="image_order" name="image_order" 
                                           value="0" min="0" placeholder="0">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Lower numbers appear first
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div id="imagePreview" class="mt-3" style="display: none;">
                                        <img id="previewImg" src="" alt="Preview" class="img-fluid rounded" style="max-height: 200px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-warning">
                            <i class="fas fa-upload me-2"></i>Upload Image
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Video Modal -->
    <div class="modal fade" id="addVideoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-video me-2"></i>Add New Video Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add_video">
                        <input type="hidden" name="kit_category" value="<?php echo htmlspecialchars($current_kit); ?>">
                        <input type="hidden" name="folder_id" id="video_folder_id" value="">
                        <div class="mb-3">
                            <label for="video_folder_select" class="form-label">Folder (Optional)</label>
                            <select class="form-control form-control-custom" id="video_folder_select" onchange="setModalFolder('video_folder_id', this.value)">
                                <option value="">No folder (uncategorized)</option>
                                <?php kitRenderFolderOptions($folder_children_map); ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="video_title" class="form-label">Video Title</label>
                            <input type="text" class="form-control form-control-custom" id="video_title" name="video_title" 
                                   placeholder="Enter a descriptive title for the video">
                        </div>
                        <div class="mb-3">
                            <label for="video_url" class="form-label">YouTube Video URL</label>
                            <input type="url" class="form-control form-control-custom" id="video_url" name="video_url" 
                                   placeholder="https://www.youtube.com/watch?v=..." required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Paste the full YouTube video URL
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="video_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control form-control-custom" id="video_order" name="video_order" 
                                   value="0" min="0" placeholder="0">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Lower numbers appear first
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-success">
                            <i class="fas fa-plus me-2"></i>Add Video
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add File Modal -->
    <div class="modal fade" id="addfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-file me-2"></i>Add New File Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add_file">
                        <input type="hidden" name="kit_category" value="<?php echo htmlspecialchars($current_kit); ?>">
                        <input type="hidden" name="folder_id" id="file_folder_id" value="">
                        <div class="mb-3">
                            <label for="file_folder_select" class="form-label">Folder (Optional)</label>
                            <select class="form-control form-control-custom" id="file_folder_select" onchange="setModalFolder('file_folder_id', this.value)">
                                <option value="">No folder (uncategorized)</option>
                                <?php kitRenderFolderOptions($folder_children_map); ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="file_title" class="form-label">File Title</label>
                            <input type="text" class="form-control form-control-custom" id="file_title" name="file_title" 
                                   placeholder="Enter a descriptive title for the file" required>
                        </div>
                        <div class="mb-3">
                            <label for="file_upload" class="form-label">Select File</label>
                            <input type="file" class="form-control form-control-custom" id="file_upload" name="file_upload" 
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.mp4,.avi,.mov,.mp3,.wav" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Supported formats: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, MP4, AVI, MOV, MP3, WAV (Max size: 10MB)
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="file_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control form-control-custom" id="file_order" name="file_order" 
                                   value="0" min="0" placeholder="0">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Lower numbers appear first
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-plus me-2"></i>Add File
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Image Modal -->
    <div class="modal fade" id="editImageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit_image">
                        <input type="hidden" name="item_id" id="edit_image_id">
                        <input type="hidden" name="kit_category" value="<?php echo htmlspecialchars($current_kit); ?>">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="edit_image_folder_id" class="form-label">Folder</label>
                                    <select class="form-control form-control-custom" id="edit_image_folder_id" name="edit_image_folder_id">
                                        <option value="">No folder (uncategorized)</option>
                                        <?php kitRenderFolderOptions($folder_children_map); ?>
                                    </select>
                                    <div class="form-text">Select a folder to move this image.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_image_title" class="form-label">Image Title</label>
                                    <input type="text" class="form-control form-control-custom" id="edit_image_title" name="edit_image_title" 
                                           placeholder="Enter a descriptive title for the image" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_kit_image" class="form-label">Update Image (Optional)</label>
                                    <input type="file" class="form-control form-control-custom" id="edit_kit_image" name="edit_kit_image" 
                                           accept="image/*">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Leave empty to keep the current image. Supported formats: JPG, JPEG, PNG, GIF (Max size: 10MB)
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_image_order" class="form-label">Display Order</label>
                                    <input type="number" class="form-control form-control-custom" id="edit_image_order" name="edit_image_order" 
                                           min="0" placeholder="0" required>
                                </div>
                                <div class="mb-3">
                                    <label for="edit_image_status" class="form-label">Status</label>
                                    <select class="form-control form-control-custom" id="edit_image_status" name="edit_image_status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="text-center">
                                    <div id="editImagePreview" class="mt-3">
                                        <img id="editPreviewImg" src="" alt="Current Image" class="img-fluid rounded" style="max-height: 200px;">
                                        <p class="text-muted mt-2">Current Image</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-save me-2"></i>Update Image
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Video Modal -->
    <div class="modal fade" id="editVideoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit_video">
                        <input type="hidden" name="item_id" id="edit_video_id">
                        <input type="hidden" name="kit_category" value="<?php echo htmlspecialchars($current_kit); ?>">
                        <div class="mb-3">
                            <label for="edit_video_folder_id" class="form-label">Folder</label>
                            <select class="form-control form-control-custom" id="edit_video_folder_id" name="edit_video_folder_id">
                                <option value="">No folder (uncategorized)</option>
                                <?php kitRenderFolderOptions($folder_children_map); ?>
                            </select>
                            <div class="form-text">Select a folder to move this video.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_video_title" class="form-label">Video Title</label>
                            <input type="text" class="form-control form-control-custom" id="edit_video_title" name="edit_video_title" 
                                   placeholder="Enter a descriptive title for the video" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_video_url" class="form-label">YouTube Video URL</label>
                            <input type="url" class="form-control form-control-custom" id="edit_video_url" name="edit_video_url" 
                                   placeholder="https://www.youtube.com/watch?v=...">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Leave empty if this is an uploaded video file
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_video_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control form-control-custom" id="edit_video_order" name="edit_video_order" 
                                   min="0" placeholder="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_video_status" class="form-label">Status</label>
                            <select class="form-control form-control-custom" id="edit_video_status" name="edit_video_status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-save me-2"></i>Update Video
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit File Modal -->
    <div class="modal fade" id="editFileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit_file">
                        <input type="hidden" name="item_id" id="edit_file_id">
                        <input type="hidden" name="kit_category" value="<?php echo htmlspecialchars($current_kit); ?>">
                        <div class="mb-3">
                            <label for="edit_file_folder_id" class="form-label">Folder</label>
                            <select class="form-control form-control-custom" id="edit_file_folder_id" name="edit_file_folder_id">
                                <option value="">No folder (uncategorized)</option>
                                <?php kitRenderFolderOptions($folder_children_map); ?>
                            </select>
                            <div class="form-text">Select a folder to move this file.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_file_title" class="form-label">File Title</label>
                            <input type="text" class="form-control form-control-custom" id="edit_file_title" name="edit_file_title" 
                                   placeholder="Enter a descriptive title for the file" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_file_upload" class="form-label">Update File (Optional)</label>
                            <input type="file" class="form-control form-control-custom" id="edit_file_upload" name="edit_file_upload" 
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.mp4,.avi,.mov,.mp3,.wav">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Leave empty to keep the current file. Supported formats: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, MP4, AVI, MOV, MP3, WAV (Max size: 10MB)
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_file_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control form-control-custom" id="edit_file_order" name="edit_file_order" 
                                   min="0" placeholder="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_file_status" class="form-label">Status</label>
                            <select class="form-control form-control-custom" id="edit_file_status" name="edit_file_status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-save me-2"></i>Update File
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Move Item Modal -->
    <div class="modal fade" id="moveItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title"><i class="fas fa-folder me-2"></i>Move to Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="move_item">
                        <input type="hidden" name="item_id" id="move_item_id">
                        <input type="hidden" name="kit_category" value="<?php echo htmlspecialchars($current_kit); ?>">
                        <p class="text-muted mb-3">Move <strong id="move_item_label">this item</strong> to a folder. Choose "No folder" to keep it uncategorized.</p>
                        <div class="mb-3">
                            <label for="move_folder_id" class="form-label">Select Folder</label>
                            <select class="form-control form-control-custom" id="move_folder_id" name="move_folder_id">
                                <option value="">No folder (uncategorized)</option>
                                <?php kitRenderFolderOptions($folder_children_map); ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom-primary">
                            <i class="fas fa-folder me-2"></i>Move Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="item_id" id="delete_item_id">
                        <input type="hidden" name="item_type" id="delete_item_type">
                        <div class="text-center">
                            <i class="fas fa-trash-alt text-danger" style="font-size: 3rem; margin-bottom: 20px;"></i>
                            <h5>Are you sure?</h5>
                            <p class="text-muted">This action cannot be undone. The item will be permanently deleted.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const kitFoldersData = <?php echo json_encode($kit_folders, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function buildFolderChildrenMap(folders) {
            const children = {};
            folders.forEach(function(f) {
                const pid = f.parent_id ? parseInt(f.parent_id) : 0;
                if (!children[pid]) children[pid] = [];
                children[pid].push(f);
            });
            return children;
        }

        const folderChildrenMapJs = buildFolderChildrenMap(kitFoldersData);

        function getFolderDescendantIds(folderId) {
            let ids = [];
            const children = folderChildrenMapJs[parseInt(folderId)] || [];
            children.forEach(function(child) {
                ids.push(parseInt(child.id));
                ids = ids.concat(getFolderDescendantIds(child.id));
            });
            return ids;
        }

        function buildFolderOptionsHtml(parentId, depth, excludeIds, selectedId) {
            const children = folderChildrenMapJs[parentId] || [];
            let html = '';
            children.forEach(function(folder) {
                const id = parseInt(folder.id);
                if (excludeIds.indexOf(id) !== -1) return;
                const prefix = depth > 0 ? Array(depth + 1).join('— ') : '';
                const selected = id === parseInt(selectedId) ? ' selected' : '';
                html += '<option value="' + id + '"' + selected + '>' + prefix + folder.title + '</option>';
                html += buildFolderOptionsHtml(id, depth + 1, excludeIds, selectedId);
            });
            return html;
        }

        function populateEditParentSelect(folderId, selectedParentId) {
            const excludeIds = [parseInt(folderId)].concat(getFolderDescendantIds(folderId));
            const select = document.getElementById('edit_parent_folder_select');
            select.innerHTML = '<option value="">None (main folder)</option>' + buildFolderOptionsHtml(0, 0, excludeIds, selectedParentId || 0);
        }

        function openCreateSubfolder(parentId) {
            document.getElementById('parent_folder_id').value = parentId || '';
            document.getElementById('parent_folder_select').value = parentId || '';
        }

        function setModalFolder(hiddenId, folderId) {
            const hidden = document.getElementById(hiddenId);
            if (hidden) {
                hidden.value = folderId || '';
            }
            const selectMap = {
                image_folder_id: 'image_folder_select',
                video_folder_id: 'video_folder_select',
                video_file_folder_id: 'video_file_folder_select',
                file_folder_id: 'file_folder_select'
            };
            const selectId = selectMap[hiddenId];
            if (selectId) {
                const select = document.getElementById(selectId);
                if (select) {
                    select.value = folderId || '';
                }
            }
        }

        function editFolder(folderId) {
            const folder = kitFoldersData.find(f => parseInt(f.id) === parseInt(folderId));
            if (!folder) return;
            document.getElementById('edit_folder_id').value = folder.id;
            document.getElementById('edit_folder_title').value = folder.title || '';
            document.getElementById('edit_folder_order').value = folder.display_order || 0;
            document.getElementById('edit_folder_status').value = folder.status || 'active';
            populateEditParentSelect(folder.id, folder.parent_id || '');
            new bootstrap.Modal(document.getElementById('editFolderModal')).show();
        }

        function openMoveItem(itemId, itemType, currentFolderId) {
            document.getElementById('move_item_id').value = itemId;
            document.getElementById('move_folder_id').value = currentFolderId || '';
            const typeLabels = { image: 'image', video: 'video', file: 'file' };
            document.getElementById('move_item_label').textContent = 'this ' + (typeLabels[itemType] || 'item');
            new bootstrap.Modal(document.getElementById('moveItemModal')).show();
        }

        function deleteFolder(folderId, folderName) {
            document.getElementById('delete_folder_id').value = folderId;
            document.getElementById('delete_folder_name').textContent = folderName;
            new bootstrap.Modal(document.getElementById('deleteFolderModal')).show();
        }

        // Image preview functionality
        document.getElementById('kit_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });

        // Delete item functionality
        function deleteItem(itemId, itemType) {
            document.getElementById('delete_item_id').value = itemId;
            document.getElementById('delete_item_type').value = itemType;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Edit item functionality
        function editItem(itemId, itemType) {
            itemId = parseInt(itemId); // Ensure itemId is an integer
            
            if (itemType === 'image') {
                // Fetch image data and populate edit modal
                if (fetchImageData(itemId)) {
                    document.getElementById('edit_image_id').value = itemId;
                    new bootstrap.Modal(document.getElementById('editImageModal')).show();
                } else {
                    alert('Error: Could not find image data. Please refresh the page and try again.');
                }
            } else if (itemType === 'video') {
                // Fetch video data and populate edit modal
                if (fetchVideoData(itemId)) {
                    document.getElementById('edit_video_id').value = itemId;
                    new bootstrap.Modal(document.getElementById('editVideoModal')).show();
                } else {
                    alert('Error: Could not find video data. Please refresh the page and try again.');
                }
            } else if (itemType === 'file') {
                // Fetch file data and populate edit modal
                if (fetchFileData(itemId)) {
                    document.getElementById('edit_file_id').value = itemId;
                    new bootstrap.Modal(document.getElementById('editFileModal')).show();
                } else {
                    alert('Error: Could not find file data. Please refresh the page and try again.');
                }
            }
        }

        // Store kit items data globally
        const kitItemsData = {
            images: <?php 
                $image_items_all = array_values(array_filter($kit_items, function($item) { return $item['type'] == 'image'; }));
                echo json_encode($image_items_all, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>,
            videos: <?php 
                $video_items_all = array_values(array_filter($kit_items, function($item) { return $item['type'] == 'video'; }));
                echo json_encode($video_items_all, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>,
            files: <?php 
                $file_items_all = array_values(array_filter($kit_items, function($item) { return $item['type'] == 'file'; }));
                echo json_encode($file_items_all, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>
        };

        // Fetch image data for editing
        function fetchImageData(itemId) {
            try {
                itemId = parseInt(itemId);
                const item = kitItemsData.images.find(img => parseInt(img.id) === itemId);
                
                if (item) {
                    const titleEl = document.getElementById('edit_image_title');
                    const orderEl = document.getElementById('edit_image_order');
                    const statusEl = document.getElementById('edit_image_status');
                    const previewImg = document.getElementById('editPreviewImg');
                    
                    if (titleEl) titleEl.value = item.title || '';
                    if (orderEl) orderEl.value = item.display_order || 0;
                    if (statusEl) statusEl.value = item.status || 'active';
                    const folderEl = document.getElementById('edit_image_folder_id');
                    if (folderEl) folderEl.value = item.folder_id || '';
                    
                    // Set current image preview
                    if (previewImg && item.file_path) {
                        previewImg.src = '../assets/upload/kits/' + item.file_path;
                        previewImg.style.display = 'block';
                    }
                    return true;
                }
                console.error('Image item not found. ItemId:', itemId, 'Available items:', kitItemsData.images);
                return false;
            } catch (error) {
                console.error('Error fetching image data:', error);
                return false;
            }
        }

        // Fetch video data for editing
        function fetchVideoData(itemId) {
            try {
                itemId = parseInt(itemId);
                const item = kitItemsData.videos.find(vid => parseInt(vid.id) === itemId);
                
                if (item) {
                    const titleEl = document.getElementById('edit_video_title');
                    const urlEl = document.getElementById('edit_video_url');
                    const orderEl = document.getElementById('edit_video_order');
                    const statusEl = document.getElementById('edit_video_status');
                    
                    if (titleEl) titleEl.value = item.title || '';
                    if (urlEl) urlEl.value = item.video_url || '';
                    if (orderEl) orderEl.value = item.display_order || 0;
                    if (statusEl) statusEl.value = item.status || 'active';
                    const folderEl = document.getElementById('edit_video_folder_id');
                    if (folderEl) folderEl.value = item.folder_id || '';
                    return true;
                }
                console.error('Video item not found. ItemId:', itemId, 'Available items:', kitItemsData.videos);
                return false;
            } catch (error) {
                console.error('Error fetching video data:', error);
                return false;
            }
        }

        // Fetch file data for editing
        function fetchFileData(itemId) {
            try {
                itemId = parseInt(itemId);
                const item = kitItemsData.files.find(file => parseInt(file.id) === itemId);
                
                if (item) {
                    const titleEl = document.getElementById('edit_file_title');
                    const orderEl = document.getElementById('edit_file_order');
                    const statusEl = document.getElementById('edit_file_status');
                    
                    if (titleEl) titleEl.value = item.title || '';
                    if (orderEl) orderEl.value = item.display_order || 0;
                    if (statusEl) statusEl.value = item.status || 'active';
                    const folderEl = document.getElementById('edit_file_folder_id');
                    if (folderEl) folderEl.value = item.folder_id || '';
                    return true;
                }
                console.error('File item not found. ItemId:', itemId, 'Available items:', kitItemsData.files);
                return false;
            } catch (error) {
                console.error('Error fetching file data:', error);
                return false;
            }
        }

        // Toggle item status
        function toggleStatus(itemId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = currentStatus === 'active' ? 'deactivate' : 'activate';
            
            if (confirm(`Are you sure you want to ${action} this item?`)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="item_id" value="${itemId}">
                    <input type="hidden" name="new_status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>

</body>
</html>



