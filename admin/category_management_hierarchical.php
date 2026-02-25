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

// Create table if it doesn't exist (with parent_id support)
$create_table_query = "CREATE TABLE IF NOT EXISTS product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT DEFAULT NULL,
    category_name VARCHAR(255) NOT NULL UNIQUE,
    category_type VARCHAR(50) DEFAULT 'product',
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

if(!mysqli_query($connect, $create_table_query)) {
    // Table may already exist
}

// Add parent_id column if it doesn't exist
$check_column = mysqli_query($connect, "SHOW COLUMNS FROM product_categories LIKE 'parent_id'");
if(mysqli_num_rows($check_column) == 0) {
    mysqli_query($connect, "ALTER TABLE product_categories ADD COLUMN parent_id INT DEFAULT NULL");
    mysqli_query($connect, "ALTER TABLE product_categories ADD FOREIGN KEY (parent_id) REFERENCES product_categories(id) ON DELETE SET NULL");
    mysqli_query($connect, "ALTER TABLE product_categories ADD INDEX idx_parent_id (parent_id)");
}

// Add category_type column if it doesn't exist
$check_type_column = mysqli_query($connect, "SHOW COLUMNS FROM product_categories LIKE 'category_type'");
if(mysqli_num_rows($check_type_column) == 0) {
    mysqli_query($connect, "ALTER TABLE product_categories ADD COLUMN category_type VARCHAR(50) DEFAULT 'product' AFTER category_name");
    mysqli_query($connect, "ALTER TABLE product_categories ADD INDEX idx_type (category_type)");
}

// Get all categories for parent selection dropdown
function getAllCategoriesForDropdown($connect, $exclude_id = null) {
    $categories = [];
    $query = "SELECT id, parent_id, category_name, display_order FROM product_categories ORDER BY parent_id, display_order ASC";
    $result = mysqli_query($connect, $query);
    
    while($row = mysqli_fetch_assoc($result)) {
        if($exclude_id && $row['id'] == $exclude_id) {
            continue; // Skip the category itself
        }
        $categories[] = $row;
    }
    
    return buildCategoryTree($categories);
}

// Build hierarchical tree for display
function buildCategoryTree($categories, $parent_id = null, $depth = 0) {
    $tree = [];
    foreach($categories as $cat) {
        if($cat['parent_id'] === $parent_id || ($parent_id === null && $cat['parent_id'] === null)) {
            // Use actual spaces and dashes for better display
            $indent = str_repeat('— ', $depth);
            $cat['indent'] = $indent;
            $cat['indent_html'] = str_repeat('&nbsp;&nbsp;', $depth);
            $cat['depth'] = $depth;
            $tree[] = $cat;
            
            // Add children
            $children = buildCategoryTree($categories, $cat['id'], $depth + 1);
            $tree = array_merge($tree, $children);
        }
    }
    return $tree;
}

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Add new category
        if($action == 'add_category') {
            $category_name = mysqli_real_escape_string($connect, $_POST['category_name']);
            $category_type = mysqli_real_escape_string($connect, $_POST['category_type'] ?? 'product');
            $parent_id = (!empty($_POST['parent_id']) && $_POST['parent_id'] != '0') ? intval($_POST['parent_id']) : null;
            $category_slug = mysqli_real_escape_string($connect, strtolower(str_replace(' ', '-', $_POST['category_name'])));
            $description = mysqli_real_escape_string($connect, $_POST['description'] ?? '');
            $icon_class = mysqli_real_escape_string($connect, $_POST['icon_class'] ?? '');
            $display_order = intval($_POST['display_order'] ?? 0);
            $created_by = $_SESSION['admin_email'];
            
            // Check if category already exists
            $check_query = mysqli_query($connect, "SELECT id FROM product_categories WHERE category_name='$category_name' OR category_slug='$category_slug'");
            
            if(mysqli_num_rows($check_query) > 0) {
                $error_message = "Category already exists!";
            } else {
                // Prevent circular reference
                $valid_parent = true;
                if($parent_id) {
                    $parent_check = mysqli_query($connect, "SELECT id FROM product_categories WHERE id=$parent_id");
                    if(mysqli_num_rows($parent_check) == 0) {
                        $valid_parent = false;
                    }
                }
                
                if(!$valid_parent) {
                    $error_message = "Invalid parent category selected!";
                } else {
                    $parent_id_sql = $parent_id ? $parent_id : 'NULL';
                    $insert_query = "INSERT INTO product_categories (parent_id, category_name, category_type, category_slug, description, icon_class, display_order, created_by) 
                                    VALUES ($parent_id_sql, '$category_name', '$category_type', '$category_slug', '$description', '$icon_class', $display_order, '$created_by')";
                    
                    if(mysqli_query($connect, $insert_query)) {
                        $success_message = "Category added successfully!";
                    } else {
                        $error_message = "Error adding category: " . mysqli_error($connect);
                    }
                }
            }
        }
        
        // Update category
        if($action == 'update_category') {
            $category_id = intval($_POST['category_id']);
            $category_name = mysqli_real_escape_string($connect, $_POST['category_name']);
            $category_type = mysqli_real_escape_string($connect, $_POST['category_type'] ?? 'product');
            $parent_id = (!empty($_POST['parent_id']) && $_POST['parent_id'] != '0') ? intval($_POST['parent_id']) : null;
            $category_slug = mysqli_real_escape_string($connect, strtolower(str_replace(' ', '-', $_POST['category_name'])));
            $description = mysqli_real_escape_string($connect, $_POST['description'] ?? '');
            $icon_class = mysqli_real_escape_string($connect, $_POST['icon_class'] ?? '');
            $display_order = intval($_POST['display_order'] ?? 0);
            
            // Check if category name/slug already exists for other records
            $check_query = mysqli_query($connect, "SELECT id FROM product_categories WHERE (category_name='$category_name' OR category_slug='$category_slug') AND id!=$category_id");
            
            if(mysqli_num_rows($check_query) > 0) {
                $error_message = "Category name already exists!";
            } else {
                // Prevent circular reference - parent cannot be self or child
                $valid_parent = true;
                if($parent_id) {
                    if($parent_id == $category_id) {
                        $valid_parent = false;
                        $error_message = "Category cannot be its own parent!";
                    } else {
                        $parent_check = mysqli_query($connect, "SELECT id FROM product_categories WHERE id=$parent_id");
                        if(mysqli_num_rows($parent_check) == 0) {
                            $valid_parent = false;
                            $error_message = "Invalid parent category!";
                        }
                    }
                }
                
                if($valid_parent) {
                    $parent_id_sql = $parent_id ? $parent_id : 'NULL';
                    $update_query = "UPDATE product_categories SET parent_id=$parent_id_sql, category_name='$category_name', category_type='$category_type', category_slug='$category_slug', description='$description', icon_class='$icon_class', display_order=$display_order WHERE id=$category_id";
                    
                    if(mysqli_query($connect, $update_query)) {
                        $success_message = "Category updated successfully!";
                    } else {
                        $error_message = "Error updating category: " . mysqli_error($connect);
                    }
                }
            }
        }
        
        // Delete category
        if($action == 'delete_category') {
            $category_id = intval($_POST['category_id']);
            
            // Check if category has children
            $children_check = mysqli_query($connect, "SELECT COUNT(*) as count FROM product_categories WHERE parent_id=$category_id");
            $children_result = mysqli_fetch_assoc($children_check);
            
            if($children_result['count'] > 0) {
                $error_message = "Cannot delete category with subcategories! Delete subcategories first.";
            } else {
                $delete_query = "DELETE FROM product_categories WHERE id=$category_id";
                
                if(mysqli_query($connect, $delete_query)) {
                    $success_message = "Category deleted successfully!";
                } else {
                    $error_message = "Error deleting category: " . mysqli_error($connect);
                }
            }
        }
        
        // Toggle active status
        if($action == 'toggle_status') {
            $category_id = intval($_POST['category_id']);
            $toggle_query = "UPDATE product_categories SET is_active = NOT is_active WHERE id=$category_id";
            
            if(mysqli_query($connect, $toggle_query)) {
                $success_message = "Category status updated!";
            } else {
                $error_message = "Error updating status: " . mysqli_error($connect);
            }
        }
    }
}

// Handle CSV Upload
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $csv_upload_dir = '../assets/upload/categories/';
    
    if(!file_exists($csv_upload_dir)) {
        @mkdir($csv_upload_dir, 0755, true);
    }
    
    if($_FILES['csv_file']['error'] == 0) {
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
            $parent_map = []; // Map of category names to IDs for parent references
            
            if($file_handle) {
                // Skip header row
                $header = fgetcsv($file_handle);
                
                while(($data = fgetcsv($file_handle)) !== false) {
                    $row_number++;
                    
                    // Skip comment rows (starting with #)
                    if(!empty($data[0]) && strpos($data[0], '#') === 0) {
                        continue;
                    }
                    
                    // Skip empty rows
                    if(empty($data[0])) {
                        continue;
                    }
                    
                    if(count($data) < 2) {
                        $error_rows[] = "Row $row_number: Incomplete data";
                        continue;
                    }
                    
                    // Check if this is an update (has ID) or new category (no ID)
                    $category_id = !empty($data[0]) && is_numeric($data[0]) ? intval($data[0]) : null;
                    $category_name = trim($data[0]);
                    $category_type = isset($data[1]) ? trim($data[1]) : 'product';
                    $parent_name = isset($data[2]) ? trim($data[2]) : '';
                    $description = isset($data[3]) ? trim($data[3]) : '';
                    $icon_class = isset($data[4]) ? trim($data[4]) : '';
                    $display_order = isset($data[5]) && is_numeric($data[5]) ? intval($data[5]) : 0;
                    
                    // If first column is numeric, treat as ID for update
                    if(!empty($data[0]) && is_numeric($data[0])) {
                        $category_id = intval($data[0]);
                        $category_name = isset($data[1]) ? trim($data[1]) : '';
                        $category_type = isset($data[2]) ? trim($data[2]) : 'product';
                        $parent_name = isset($data[3]) ? trim($data[3]) : '';
                        $description = isset($data[4]) ? trim($data[4]) : '';
                        $icon_class = isset($data[5]) ? trim($data[5]) : '';
                        $display_order = isset($data[6]) && is_numeric($data[6]) ? intval($data[6]) : 0;
                    }
                    
                    if(empty($category_name)) {
                        $error_rows[] = "Row $row_number: Category name is required";
                        continue;
                    }
                    
                    $category_name = mysqli_real_escape_string($connect, $category_name);
                    $category_slug = strtolower(str_replace(' ', '-', $category_name));
                    $description = mysqli_real_escape_string($connect, $description);
                    $icon_class = mysqli_real_escape_string($connect, $icon_class);
                    $created_by = $_SESSION['admin_email'];
                    
                    // Handle parent category
                    $parent_id = null;
                    if(!empty($parent_name)) {
                        $parent_name = mysqli_real_escape_string($connect, $parent_name);
                        
                        // Check if parent was in current import
                        if(isset($parent_map[$parent_name])) {
                            $parent_id = $parent_map[$parent_name];
                        } else {
                            // Check database
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
                    
                    // If category_id is provided, UPDATE the category
                    if($category_id) {
                        // Verify ID exists
                        $verify_query = mysqli_query($connect, "SELECT id FROM product_categories WHERE id=$category_id");
                        if(mysqli_num_rows($verify_query) == 0) {
                            $error_rows[] = "Row $row_number: Category ID $category_id not found";
                            continue;
                        }
                        
                        // Check if new name conflicts with other categories
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
                                        description='$description', 
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
                        // NEW category - check if already exists
                        $check_query = mysqli_query($connect, "SELECT id FROM product_categories WHERE category_slug='$category_slug'");
                        
                        if(mysqli_num_rows($check_query) > 0) {
                            $error_rows[] = "Row $row_number: Category '$category_name' already exists";
                            continue;
                        }
                        
                        $parent_id_sql = $parent_id ? $parent_id : 'NULL';
                        $category_type = mysqli_real_escape_string($connect, $category_type);
                        $insert_query = "INSERT INTO product_categories (parent_id, category_name, category_type, category_slug, description, icon_class, display_order, created_by) 
                                        VALUES ($parent_id_sql, '$category_name', '$category_type', '$category_slug', '$description', '$icon_class', $display_order, '$created_by')";
                        
                        if(mysqli_query($connect, $insert_query)) {
                            $imported_count++;
                            // Store in map for later rows to reference
                            $parent_map[$category_name] = mysqli_insert_id($connect);
                        } else {
                            $error_rows[] = "Row $row_number: Error creating category - " . mysqli_error($connect);
                        }
                    }
                }
                
                fclose($file_handle);
                
                if($imported_count > 0) {
                    $success_message = "Successfully imported $imported_count categories!";
                }
                
                if(!empty($error_rows)) {
                    $error_message = "Import completed with " . count($error_rows) . " errors:<br>" . implode("<br>", array_slice($error_rows, 0, 10));
                    if(count($error_rows) > 10) {
                        $error_message .= "<br>... and " . (count($error_rows) - 10) . " more errors";
                    }
                }
            }
        }
    } else {
        $error_message = "Error uploading file: " . $_FILES['csv_file']['error'];
    }
}

// Get edit category if requested
$edit_category = null;
if(isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $result = mysqli_query($connect, "SELECT * FROM product_categories WHERE id=$edit_id");
    if(mysqli_num_rows($result) > 0) {
        $edit_category = mysqli_fetch_assoc($result);
    }
}

// Get all categories with hierarchy
$all_categories_flat = [];
$result = mysqli_query($connect, "SELECT * FROM product_categories ORDER BY parent_id, display_order ASC");
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $all_categories_flat[] = $row;
    }
}
$categories = buildCategoryTree($all_categories_flat);
$parent_categories = getAllCategoriesForDropdown($connect, $edit_category ? $edit_category['id'] : null);
?>

<div class="main3">
    <a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back</h3></a>
    <h1>Product Category Management (Hierarchical)</h1>
    
    <!-- Alert Messages -->
    <?php if(!empty($success_message)): ?>
        <div class="alert alert-success" style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <i class="fa fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if(!empty($error_message)): ?>
        <div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <i class="fa fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Tabs Navigation -->
    <div style="margin-bottom: 30px;">
        <button class="btn btn-primary" onclick="switchTab('add_form')" style="margin-right: 10px;">
            <i class="fa fa-plus"></i> Add New Category
        </button>
        <button class="btn btn-info" onclick="switchTab('csv_upload')" style="margin-right: 10px;">
            <i class="fa fa-upload"></i> Bulk Import (CSV)
        </button>
        <button class="btn btn-secondary" onclick="switchTab('all_categories')" style="margin-right: 10px;">
            <i class="fa fa-list"></i> All Categories (<?php echo count($categories); ?>)
        </button>
        <a href="export_categories.php" class="btn btn-warning" style="margin-right: 10px;">
            <i class="fa fa-download"></i> Export Data
        </a>
    </div>
    
    <!-- Add/Edit Category Form -->
    <div id="add_form" class="tab-content" style="display: block; background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
        <h2><?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?></h2>
        <form method="POST" style="max-width: 600px;">
            <input type="hidden" name="action" value="<?php echo $edit_category ? 'update_category' : 'add_category'; ?>">
            <?php if($edit_category): ?>
                <input type="hidden" name="category_id" value="<?php echo $edit_category['id']; ?>">
            <?php endif; ?>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Parent Category (Optional)</label>
                <select name="parent_id" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="0">-- None (Root Category) --</option>
                    <?php foreach($parent_categories as $parent): ?>
                        <option value="<?php echo $parent['id']; ?>" <?php echo ($edit_category && $edit_category['parent_id'] == $parent['id']) ? 'selected' : ''; ?>>
                            <?php echo $parent['indent'] . htmlspecialchars($parent['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666;">Select a parent to create a subcategory</small>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Category Type *</label>
                <select name="category_type" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">-- Select Type --</option>
                    <option value="product" <?php echo (!$edit_category || $edit_category['category_type'] == 'product') ? 'selected' : ''; ?>>Product</option>
                    <option value="business" <?php echo ($edit_category && $edit_category['category_type'] == 'business') ? 'selected' : ''; ?>>Business</option>
                </select>
                <small style="color: #666;">Select the type of category (default: Product)</small>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Category Name *</label>
                <input type="text" name="category_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" 
                       value="<?php echo $edit_category ? htmlspecialchars($edit_category['category_name']) : ''; ?>" 
                       placeholder="e.g., Electronics, Laptops, etc.">
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Description</label>
                <textarea name="description" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-height: 100px;" 
                          placeholder="Category description..."><?php echo $edit_category ? htmlspecialchars($edit_category['description']) : ''; ?></textarea>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Icon Class (Font Awesome)</label>
                <input type="text" name="icon_class" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" 
                       value="<?php echo $edit_category ? htmlspecialchars($edit_category['icon_class']) : ''; ?>" 
                       placeholder="e.g., fa-shopping-bag, fa-laptop, fa-tshirt">
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Display Order</label>
                <input type="number" name="display_order" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" 
                       value="<?php echo $edit_category ? $edit_category['display_order'] : '0'; ?>" 
                       placeholder="0">
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-success" style="padding: 10px 20px;">
                    <i class="fa fa-save"></i> <?php echo $edit_category ? 'Update Category' : 'Add Category'; ?>
                </button>
                <?php if($edit_category): ?>
                    <a href="category_management.php" class="btn btn-secondary" style="padding: 10px 20px;">
                        <i class="fa fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- CSV Upload Section -->
    <div id="csv_upload" class="tab-content" style="display: none; background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
        <h2>Bulk Import Categories from CSV</h2>
        
        <div style="margin-bottom: 20px; background: #e7f3ff; padding: 15px; border-radius: 4px; border-left: 4px solid #2196F3;">
            <h4><i class="fa fa-lightbulb"></i> Pro Tip:</h4>
            <p style="margin: 0;">Click the <strong>"Export Data"</strong> button above to download your existing categories as CSV. You can then modify them and re-upload with changes!</p>
        </div>
        
        <div style="margin-bottom: 20px; background: white; padding: 15px; border-radius: 4px; border-left: 4px solid #007bff;">
            <h4><i class="fa fa-info-circle"></i> Instructions:</h4>
            <ol style="margin: 10px 0; padding-left: 20px;">
                <li>Click "Download CSV Template" to get a blank template</li>
                <li>OR click "Export Data" button above to export existing categories</li>
                <li>Fill in your category data (including parent categories)</li>
                <li>Upload the file using the form below</li>
                <li>The system will validate and import all categories with their hierarchy</li>
            </ol>
        </div>
        
        <div style="margin-bottom: 20px;">
            <a href="download_category_template_hierarchical.php" class="btn btn-info" download style="margin-right: 10px;">
                <i class="fa fa-download"></i> Download Blank Template
            </a>
            <a href="export_categories.php" class="btn btn-warning">
                <i class="fa fa-download"></i> Export Existing Data
            </a>
        </div>
        
        <form method="POST" enctype="multipart/form-data" style="max-width: 600px;">
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Select CSV File *</label>
                <input type="file" name="csv_file" accept=".csv" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <small style="color: #666;">Maximum file size: 5MB</small>
            </div>
            
            <button type="submit" class="btn btn-success" style="padding: 10px 20px;">
                <i class="fa fa-upload"></i> Import Categories
            </button>
        </form>
        
        <div style="margin-top: 30px; background: white; padding: 15px; border-radius: 4px;">
            <h4><i class="fa fa-file-csv"></i> CSV Format (with ID for updates):</h4>
            <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">
Category Name,Type,Parent Category,Description,Icon Class,Display Order
Electronics,category,,Electronic gadgets and devices,fa-laptop,1
Laptops,product,Electronics,Laptop computers,fa-laptop,1
Phones,product,Electronics,Mobile phones,fa-mobile,2
1,Clothing,category,,Apparel items (UPDATED),fa-tshirt,2
2,Men,product,Clothing,Men clothing (UPDATED),fa-male,1</pre>
            <p style="margin-top: 10px; color: #666; font-size: 13px;">
                <strong>How it works:</strong><br>
                • Leave first column empty = CREATE new category<br>
                • Add ID number in first column = UPDATE that specific category<br>
                • Type options: category, product, service, business (default: product)<br>
                • Parent Category names must match existing categories (case-sensitive)<br>
                • DO NOT CHANGE the ID column value
            </p>
        </div>
    </div>
    
    <!-- All Categories List -->
    <div id="all_categories" class="tab-content" style="display: none;">
        <h2>All Categories (Hierarchical)</h2>
        
        <?php if(count($categories) > 0): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 5px; overflow: hidden;">
                    <thead style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <tr>
                            <th style="padding: 15px; text-align: left; font-weight: bold;">Category Name</th>
                            <th style="padding: 15px; text-align: left; font-weight: bold;">Type</th>
                            <th style="padding: 15px; text-align: left; font-weight: bold;">Parent</th>
                            <th style="padding: 15px; text-align: left; font-weight: bold;">Icon</th>
                            <th style="padding: 15px; text-align: left; font-weight: bold;">Description</th>
                            <th style="padding: 15px; text-align: center; font-weight: bold;">Order</th>
                            <th style="padding: 15px; text-align: center; font-weight: bold;">Status</th>
                            <th style="padding: 15px; text-align: center; font-weight: bold;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($categories as $category): ?>
                            <tr style="border-bottom: 1px solid #dee2e6;">
                                <td style="padding: 15px;">
                                    <span style="font-family: monospace;"><?php echo $category['indent']; ?></span>
                                    <strong><?php echo htmlspecialchars($category['category_name']); ?></strong>
                                    <br><small style="color: #666;">Slug: <?php echo htmlspecialchars($category['category_slug']); ?></small>
                                </td>
                                <td style="padding: 15px;">
                                    <span style="background-color: #e7f3ff; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 500;">
                                        <?php echo htmlspecialchars($category['category_type'] ?? 'product'); ?>
                                    </span>
                                </td>
                                <td style="padding: 15px;">
                                    <?php 
                                    if($category['parent_id']) {
                                        $parent_result = mysqli_query($connect, "SELECT category_name FROM product_categories WHERE id=" . $category['parent_id']);
                                        if($parent_result && mysqli_num_rows($parent_result) > 0) {
                                            $parent_row = mysqli_fetch_assoc($parent_result);
                                            echo htmlspecialchars($parent_row['category_name']);
                                        }
                                    } else {
                                        echo '<span style="color: #999;">-</span>';
                                    }
                                    ?>
                                </td>
                                <td style="padding: 15px;">
                                    <?php if(!empty($category['icon_class'])): ?>
                                        <i class="fa <?php echo htmlspecialchars($category['icon_class']); ?>" style="font-size: 20px;"></i>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 15px;">
                                    <?php echo htmlspecialchars(substr($category['description'] ?? '', 0, 50)); ?>
                                    <?php echo strlen($category['description'] ?? '') > 50 ? '...' : ''; ?>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <?php echo $category['display_order']; ?>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                        <button type="submit" class="btn <?php echo $category['is_active'] ? 'btn-success' : 'btn-secondary'; ?>" style="padding: 5px 10px; font-size: 12px;">
                                            <?php echo $category['is_active'] ? '<i class="fa fa-check"></i> Active' : '<i class="fa fa-times"></i> Inactive'; ?>
                                        </button>
                                    </form>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <a href="category_management.php?edit_id=<?php echo $category['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px; margin-right: 5px;">
                                        <i class="fa fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure? This cannot be undone if the category has no subcategories.');">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">
                                            <i class="fa fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; text-align: center;">
                <i class="fa fa-info-circle"></i> No categories found. <a href="#" onclick="switchTab('add_form'); return false;">Add one now!</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    document.getElementById(tabName).style.display = 'block';
    window.scrollTo(0, 0);
}
</script>

<style>
.main3 {
    padding: 20px;
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
    background-color: #117a8b;
}

.btn-success {
    background-color: #28a745;
    color: white;
}

.btn-success:hover {
    background-color: #1e7e34;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-danger:hover {
    background-color: #c82333;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #545b62;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
}

table {
    width: 100%;
    border-collapse: collapse;
}

table th {
    background-color: #f8f9fa;
    padding: 15px;
    text-align: left;
    font-weight: bold;
    border-bottom: 2px solid #dee2e6;
}

table td {
    padding: 15px;
    border-bottom: 1px solid #dee2e6;
}

table tbody tr:hover {
    background-color: #f8f9fa;
}

.alert {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.back_btn {
    display: inline-block;
    margin-bottom: 20px;
    color: #007bff;
    cursor: pointer;
    transition: all 0.3s ease;
}

.back_btn:hover {
    color: #0056b3;
}
</style>
