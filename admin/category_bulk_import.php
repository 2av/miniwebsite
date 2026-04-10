<?php
require_once(__DIR__ . '/../app/config/database.php');
require('header.php');

// Check if admin is logged in
if(!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    echo '<script>alert("Please login first!"); window.location.href="login.php";</script>';
    exit;
}

// Initialize messages
$success_message = '';
$error_message = '';
$imported_count = 0;
$error_rows = [];

// Create table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT DEFAULT NULL,
    category_name VARCHAR(255) NOT NULL UNIQUE,
    category_type VARCHAR(50) DEFAULT 'product-category',
    category_slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    icon_class VARCHAR(100),
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by VARCHAR(255),
    FOREIGN KEY (parent_id) REFERENCES product_categories(id) ON DELETE SET NULL,
    INDEX idx_active (is_active),
    INDEX idx_order (display_order),
    INDEX idx_parent_id (parent_id),
    INDEX idx_type (category_type)
)";

mysqli_query($connect, $create_table_query);

// Handle CSV file upload
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'import_csv') {
    if(!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "Please select a valid CSV file!";
    } else {
        $file_extension = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['csv'];
        $max_file_size = 5 * 1024 * 1024;
        
        if(!in_array($file_extension, $allowed_extensions)) {
            $error_message = "Only CSV files are allowed!";
        } elseif($_FILES['csv_file']['size'] > $max_file_size) {
            $error_message = "File size exceeds 5MB limit!";
        } else {
            // Maintain/archive the uploaded CSV file
            $csv_upload_dir = __DIR__ . '/../assets/upload/category-imports/';
            if(!is_dir($csv_upload_dir)) {
                mkdir($csv_upload_dir, 0755, true);
            }
            $saved_csv_filename = 'category_import_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['csv_file']['name']));
            $saved_csv_path = $csv_upload_dir . $saved_csv_filename;
            $csv_saved = copy($_FILES['csv_file']['tmp_name'], $saved_csv_path);
            
            $file_handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $imported_count = 0;
            $skipped_count = 0;
            $error_rows = [];
            $row_number = 0;
            $parent_map = [];
            $skip_duplicates = !empty($_POST['skip_duplicates']);
            
            if($file_handle) {
                $header = fgetcsv($file_handle);
                $header_lower = array_map('strtolower', array_map('trim', $header));
                
                // Only allow this CSV format:
                // Business Heading, Business Category, Business Category Slug, Product Category, Product Category Slug
                $required_headers = [
                    'business heading',
                    'business category',
                    'business category slug',
                    'product category',
                    'product category slug'
                ];
                $missing_headers = array_diff($required_headers, $header_lower);
                if(!empty($missing_headers)) {
                    $error_message = "Invalid CSV format. Required columns: Business Heading, Business Category, Business Category Slug, Product Category, Product Category Slug";
                    fclose($file_handle);
                    $file_handle = null;
                } else {
                    $created_by = trim(preg_replace('/[\r\n\x00]/', '', $_SESSION['admin_email'] ?? ''));
                    if($created_by === '') $created_by = 'admin';
                    
                    // Map header names to column indices
                    $col_map = [];
                    foreach($header as $i => $col) {
                        $col_map[strtolower(trim($col))] = $i;
                    }
                    
                    $get_col = function($data, $key) use ($col_map) {
                        $idx = isset($col_map[$key]) ? $col_map[$key] : -1;
                        return ($idx >= 0 && isset($data[$idx])) ? trim($data[$idx]) : '';
                    };
                    
                    // Helper: get or create category, return id
                    $getOrCreateCategory = function($name, $slug, $type, $parent_id, $display_order = 0) use ($connect, &$parent_map, $created_by) {
                        $slug = !empty($slug) ? $slug : strtolower(str_replace(' ', '-', $name));
                        $key = $type . '::' . ($parent_id ? $parent_id : 'root') . '::' . $name;
                        if(isset($parent_map[$key])) return $parent_map[$key];
                        
                        $name_esc = mysqli_real_escape_string($connect, $name);
                        $slug_esc = mysqli_real_escape_string($connect, $slug);
                        $type_esc = mysqli_real_escape_string($connect, $type);
                        
                        // Reuse existing category if either unique key already exists.
                        $check = mysqli_query($connect, "SELECT id FROM product_categories WHERE category_slug='$slug_esc' OR category_name='$name_esc' LIMIT 1");
                        if(mysqli_num_rows($check) > 0) {
                            $row = mysqli_fetch_assoc($check);
                            $parent_map[$key] = $row['id'];
                            return $row['id'];
                        }
                        
                        $pid_sql = $parent_id ? $parent_id : 'NULL';
                        $created_by_esc = mysqli_real_escape_string($connect, $created_by);
                        $q = "INSERT INTO product_categories (parent_id, category_name, category_type, category_slug, display_order, created_by) 
                              VALUES ($pid_sql, '$name_esc', '$type_esc', '$slug_esc', $display_order, '$created_by_esc')";
                        try {
                            if(mysqli_query($connect, $q)) {
                                $id = mysqli_insert_id($connect);
                                $parent_map[$key] = $id;
                                return $id;
                            }
                        } catch(Throwable $e) {
                            // If a duplicate slips through, fetch existing record and continue import.
                            $retry = mysqli_query($connect, "SELECT id FROM product_categories WHERE category_slug='$slug_esc' OR category_name='$name_esc' LIMIT 1");
                            if($retry && mysqli_num_rows($retry) > 0) {
                                $row = mysqli_fetch_assoc($retry);
                                $parent_map[$key] = $row['id'];
                                return $row['id'];
                            }
                        }
                        return null;
                    };
                    $display_order = 0;
                    
                    while(($data = fgetcsv($file_handle)) !== false) {
                        $row_number++;
                        if(!empty($data[0]) && strpos(trim($data[0]), '#') === 0) continue;
                        
                        $business_heading = $get_col($data, 'business heading');
                        $business_category = $get_col($data, 'business category');
                        $business_category_slug = $get_col($data, 'business category slug');
                        $product_category = $get_col($data, 'product category');
                        $product_category_slug = $get_col($data, 'product category slug');
                        
                        if(empty($business_heading) && empty($business_category) && empty($product_category)) {
                            continue;
                        }
                        
                        if(empty($business_heading) || empty($business_category) || empty($product_category)) {
                            $error_rows[] = "Row $row_number: Business Heading, Business Category and Product Category are required";
                            continue;
                        }
                        
                        $parent_id = null;
                        $parent_id = $getOrCreateCategory(
                            $business_heading,
                            strtolower(str_replace(' ', '-', $business_heading)),
                            'business-category',
                            null,
                            $display_order++
                        );
                        if($parent_id === null) {
                            $error_rows[] = "Row $row_number: Failed to create Business Heading '$business_heading'";
                            continue;
                        }
                        
                        $parent_id = $getOrCreateCategory(
                            $business_category,
                            !empty($business_category_slug) ? $business_category_slug : strtolower(str_replace(' ', '-', $business_category)),
                            'business-category',
                            $parent_id,
                            $display_order++
                        );
                        if($parent_id === null) {
                            $error_rows[] = "Row $row_number: Failed to create Business Category '$business_category'";
                            continue;
                        }
                        
                        $product_category_id = $getOrCreateCategory(
                            $product_category,
                            !empty($product_category_slug) ? $product_category_slug : strtolower(str_replace(' ', '-', $product_category)),
                            'product-category',
                            $parent_id,
                            $display_order++
                        );
                        if($product_category_id === null) {
                            $error_rows[] = "Row $row_number: Failed to create Product Category '$product_category'";
                            continue;
                        }
                        
                        $imported_count++;
                    }
                }
                
                if($file_handle) {
                    fclose($file_handle);
                }
                
                if($imported_count > 0 || $skipped_count > 0) {
                    $success_message = "Import completed! Processed $imported_count rows.";
                    if($skipped_count > 0) {
                        $success_message .= " Skipped $skipped_count duplicates.";
                    }
                    if($csv_saved) {
                        $success_message .= " CSV file saved: <code>" . htmlspecialchars($saved_csv_filename) . "</code>";
                    }
                }
                
                if(count($error_rows) > 0) {
                    $error_message = "Import completed with " . count($error_rows) . " errors.";
                    if($skipped_count > 0) {
                        $error_message .= " Skipped $skipped_count duplicates.";
                    }
                    if($imported_count > 0) {
                        $error_message .= " Imported $imported_count categories.";
                    }
                    $error_message .= "<br><details><summary style='cursor:pointer; margin:10px 0;'>Show error details (" . count($error_rows) . ")</summary><ul style='margin: 10px 0; max-height: 200px; overflow-y: auto;'>";
                    foreach($error_rows as $error) {
                        $error_message .= "<li>" . htmlspecialchars($error) . "</li>";
                    }
                    $error_message .= "</ul></details>";
                    if($csv_saved) {
                        $error_message .= "<p style='margin-top: 10px;'><i class='fa fa-file-csv'></i> CSV file saved: <code>" . htmlspecialchars($saved_csv_filename) . "</code></p>";
                    }
                }
            }
        }
    }
}
?>

<div class="main3">
    <div style="display: flex; gap: 10px; margin-bottom: 30px;">
        <a href="category_add.php" class="btn btn-primary">
            <i class="fa fa-plus"></i> Add New Category
        </a>
        <a href="category_bulk_import.php" class="btn btn-info">
            <i class="fa fa-upload"></i> Bulk Import (CSV)
        </a>
        <a href="category_list.php" class="btn btn-secondary">
            <i class="fa fa-list"></i> All Categories
        </a>
        <a href="export_categories.php" class="btn btn-warning">
            <i class="fa fa-download"></i> Export Data
        </a>
    </div>
    
    <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <?php if($success_message): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #28a745;">
                <i class="fa fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error_message): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #dc3545;">
                <i class="fa fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <h2>Bulk Import Categories from CSV</h2>
        
        <div style="margin-bottom: 20px;">
            <a href="download_category_template_business.php" class="btn btn-info" download style="margin-right: 10px;">
                <i class="fa fa-download"></i> Business Format Template
            </a>
            <a href="download_category_template_hierarchical.php" class="btn btn-secondary" download style="margin-right: 10px;">
                <i class="fa fa-download"></i> Legacy Template
            </a>
            <span style="color: #666; font-size: 14px;">Download a sample CSV file to see the correct format</span>
        </div>
        
        <form method="POST" enctype="multipart/form-data" style="max-width: 600px;">
            <input type="hidden" name="action" value="import_csv">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="skip_duplicates" value="1" checked>
                    <span><strong>Skip duplicates</strong> — duplicate category slugs will be reused automatically during import</span>
                </label>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 10px; font-weight: bold;">Select CSV File</label>
                <input type="file" name="csv_file" accept=".csv" style="width: 100%; padding: 10px; border: 2px dashed #ddd; border-radius: 4px;" required>
                <small style="color: #666; display: block; margin-top: 10px;">
                    <i class="fa fa-info-circle"></i> Maximum file size: 5MB. Uploaded files are maintained in <code>assets/upload/category-imports/</code> for reference.
                </small>
            </div>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #007bff;">
                <h4 style="margin-top: 0;">Supported CSV Format</h4>
                
                <p style="margin: 10px 0;"><strong>Required Columns:</strong></p>
                <ul style="margin: 5px 0 15px 20px;">
                    <li><strong>Business Heading</strong> - Top-level (e.g., Retail)</li>
                    <li><strong>Business Category</strong> - (e.g., Kirana Store)</li>
                    <li><strong>Business Category Slug</strong> - URL slug (e.g., kirana-store)</li>
                    <li><strong>Product Category</strong> - (e.g., New Arrivals)</li>
                    <li><strong>Product Category Slug</strong> - (e.g., new-arrivals)</li>
                </ul>
                <code style="background: white; padding: 10px; border-radius: 3px; display: block; overflow-x: auto; margin-bottom: 15px; font-size: 11px;">
Business Heading,Business Category,Business Category Slug,Product Category,Product Category Slug<br>
Retail,Kirana Store,kirana-store,New Arrivals,new-arrivals<br>
Retail,Kirana Store,kirana-store,Trending,trending
                </code>
            </div>
            
            <button type="submit" class="btn btn-success" style="padding: 10px 20px; font-size: 16px;">
                <i class="fa fa-upload"></i> Import CSV
            </button>
        </form>
    </div>
</div>

<style>
.main3 {
    padding: 20px;
    background: #f5f7fa;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
    transition: all 0.3s ease;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn-info:hover {
    background-color: #138496;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

.btn-warning {
    background-color: #ffc107;
    color: #333;
}

.btn-warning:hover {
    background-color: #e0a800;
}

.btn-success {
    background-color: #28a745;
    color: white;
}

.btn-success:hover {
    background-color: #218838;
}

.form-group input[type="file"] {
    cursor: pointer;
}

code {
    font-family: 'Courier New', monospace;
    font-size: 12px;
}
</style>

<?php include('footer.php'); ?>
