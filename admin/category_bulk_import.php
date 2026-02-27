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
            $file_handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $imported_count = 0;
            $error_rows = [];
            $row_number = 0;
            $parent_map = [];
            
            if($file_handle) {
                $header = fgetcsv($file_handle);
                
                while(($data = fgetcsv($file_handle)) !== false) {
                    $row_number++;
                    
                    if(!empty($data[0]) && strpos($data[0], '#') === 0) {
                        continue;
                    }
                    
                    if(empty($data[0])) {
                        continue;
                    }
                    
                    if(count($data) < 2) {
                        $error_rows[] = "Row $row_number: Incomplete data";
                        continue;
                    }
                    
                    $category_id = !empty($data[0]) && is_numeric($data[0]) ? intval($data[0]) : null;
                    $category_name = isset($data[1]) ? trim($data[1]) : '';
                    $category_type = isset($data[2]) ? trim($data[2]) : 'product-category';
                    $parent_name = isset($data[3]) ? trim($data[3]) : '';
                    $icon_class = isset($data[4]) ? trim($data[4]) : '';
                    $display_order = isset($data[5]) && is_numeric($data[5]) ? intval($data[5]) : 0;
                    
                    if(!empty($data[0]) && is_numeric($data[0])) {
                        $category_id = intval($data[0]);
                        $category_name = isset($data[1]) ? trim($data[1]) : '';
                        $category_type = isset($data[2]) ? trim($data[2]) : 'product-category';
                        $parent_name = isset($data[3]) ? trim($data[3]) : '';
                        $icon_class = isset($data[4]) ? trim($data[4]) : '';
                        $display_order = isset($data[5]) && is_numeric($data[5]) ? intval($data[5]) : 0;
                    }
                    
                    if(empty($category_name)) {
                        $error_rows[] = "Row $row_number: Category name is required";
                        continue;
                    }
                    
                    $category_name = mysqli_real_escape_string($connect, $category_name);
                    $category_slug = strtolower(str_replace(' ', '-', $category_name));
                    $icon_class = mysqli_real_escape_string($connect, $icon_class);
                    $created_by = $_SESSION['admin_email'];
                    
                    $parent_id = null;
                    if(!empty($parent_name)) {
                        $parent_name = mysqli_real_escape_string($connect, $parent_name);
                        
                        if(isset($parent_map[$parent_name])) {
                            $parent_id = $parent_map[$parent_name];
                        } else {
                            $parent_query = mysqli_query($connect, "SELECT id FROM product_categories WHERE category_name='$parent_name'");
                            if(mysqli_num_rows($parent_query) > 0) {
                                $parent_row = mysqli_fetch_assoc($parent_query);
                                $parent_id = $parent_row['id'];
                                $parent_map[$parent_name] = $parent_id;
                            } else {
                                $error_rows[] = "Row $row_number: Parent category '$parent_name' not found";
                                continue;
                            }
                        }
                    }
                    
                    if($category_id) {
                        $verify_query = mysqli_query($connect, "SELECT id FROM product_categories WHERE id=$category_id");
                        if(mysqli_num_rows($verify_query) == 0) {
                            $error_rows[] = "Row $row_number: Category ID $category_id not found";
                            continue;
                        }
                        
                        $check_query = mysqli_query($connect, "SELECT id FROM product_categories WHERE (category_name='$category_name' OR category_slug='$category_slug') AND id!=$category_id");
                        if(mysqli_num_rows($check_query) > 0) {
                            $error_rows[] = "Row $row_number: Category name '$category_name' already exists";
                            continue;
                        }
                        
                        $parent_id_sql = $parent_id ? $parent_id : 'NULL';
                        $category_type = mysqli_real_escape_string($connect, $category_type);
                        $update_query = "UPDATE product_categories SET 
                                        category_name='$category_name', 
                                        category_type='$category_type',
                                        category_slug='$category_slug', 
                                        icon_class='$icon_class', 
                                        display_order=$display_order,
                                        parent_id=$parent_id_sql
                                        WHERE id=$category_id";
                        
                        if(mysqli_query($connect, $update_query)) {
                            $imported_count++;
                        } else {
                            $error_rows[] = "Row $row_number: Error updating category - " . mysqli_error($connect);
                        }
                    } else {
                        $check_query = mysqli_query($connect, "SELECT id FROM product_categories WHERE category_slug='$category_slug'");
                        
                        if(mysqli_num_rows($check_query) > 0) {
                            $error_rows[] = "Row $row_number: Category '$category_name' already exists";
                            continue;
                        }
                        
                        $parent_id_sql = $parent_id ? $parent_id : 'NULL';
                        $category_type = mysqli_real_escape_string($connect, $category_type);
                        $insert_query = "INSERT INTO product_categories (parent_id, category_name, category_type, category_slug, icon_class, display_order, created_by) 
                                        VALUES ($parent_id_sql, '$category_name', '$category_type', '$category_slug', '$icon_class', $display_order, '$created_by')";
                        
                        if(mysqli_query($connect, $insert_query)) {
                            $imported_count++;
                            $parent_map[$category_name] = mysqli_insert_id($connect);
                        } else {
                            $error_rows[] = "Row $row_number: Error creating category - " . mysqli_error($connect);
                        }
                    }
                }
                
                fclose($file_handle);
                
                if($imported_count > 0) {
                    $success_message = "Import completed successfully! Imported $imported_count categories.";
                }
                
                if(count($error_rows) > 0) {
                    $error_message = "Import completed with " . count($error_rows) . " errors:<br><ul style='margin: 10px 0;'>";
                    foreach($error_rows as $error) {
                        $error_message .= "<li>" . htmlspecialchars($error) . "</li>";
                    }
                    $error_message .= "</ul>";
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
            <a href="download_category_template_hierarchical.php" class="btn btn-info" download style="margin-right: 10px;">
                <i class="fa fa-download"></i> Download Template
            </a>
            <span style="color: #666; font-size: 14px;">Download a sample CSV file to see the correct format</span>
        </div>
        
        <form method="POST" enctype="multipart/form-data" style="max-width: 600px;">
            <input type="hidden" name="action" value="import_csv">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 10px; font-weight: bold;">Select CSV File</label>
                <input type="file" name="csv_file" accept=".csv" style="width: 100%; padding: 10px; border: 2px dashed #ddd; border-radius: 4px;" required>
                <small style="color: #666; display: block; margin-top: 10px;">
                    <i class="fa fa-info-circle"></i> Maximum file size: 5MB
                </small>
            </div>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #007bff;">
                <h4 style="margin-top: 0;">CSV Format</h4>
                <p style="margin: 5px 0;"><strong>Columns (in order):</strong></p>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>ID</strong> - Category ID (leave blank for new, add number to update)</li>
                    <li><strong>Category Name</strong> - Name of the category</li>
                    <li><strong>Type</strong> - business-category, product-category, product-name</li>
                    <li><strong>Parent Category</strong> - Name of parent (must exist)</li>
                    <li><strong>Icon Class</strong> - Font Awesome class (e.g., fa-shopping-bag)</li>
                    <li><strong>Display Order</strong> - Number for sorting</li>
                </ul>
                <p style="margin: 10px 0; color: #666;"><strong>Example:</strong></p>
                <code style="background: white; padding: 10px; border-radius: 3px; display: block; overflow-x: auto;">
ID,Category Name,Type,Parent Category,Icon Class,Display Order<br>
,Electronics,product-category,Retail,fa-laptop,0<br>
,Laptop,product-name,Electronics,fa-laptop,0
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
