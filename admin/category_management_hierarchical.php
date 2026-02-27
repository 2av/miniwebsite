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

// Determine which tab to show (default: add_form)
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'add_form';
$allowed_tabs = ['add_form', 'csv_upload', 'all_categories'];
if(!in_array($active_tab, $allowed_tabs)) {
    $active_tab = 'add_form';
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
            $category_type = mysqli_real_escape_string($connect, $_POST['category_type'] ?? 'product-category');
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
            $category_type = mysqli_real_escape_string($connect, $_POST['category_type'] ?? 'product-category');
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
        <a href="?tab=add_form" class="btn btn-primary" style="margin-right: 10px;">
            <i class="fa fa-plus"></i> Add New Category
        </a>
        <a href="?tab=csv_upload" class="btn btn-info" style="margin-right: 10px;">
            <i class="fa fa-upload"></i> Bulk Import (CSV)
        </a>
        <a href="?tab=all_categories" class="btn btn-secondary" style="margin-right: 10px;">
            <i class="fa fa-list"></i> All Categories (<?php echo count($categories); ?>)
        </a>
        <a href="export_categories.php" class="btn btn-warning" style="margin-right: 10px;">
            <i class="fa fa-download"></i> Export Data
        </a>
    </div>
    
    <!-- Add/Edit Category Form -->
    <div id="add_form" class="tab-content" style="display: <?php echo $active_tab === 'add_form' ? 'block' : 'none'; ?>; background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
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
                    <option value="business-category" <?php echo ($edit_category && $edit_category['category_type'] == 'business-category') ? 'selected' : ''; ?>>Business Category</option>
                    <option value="product-category" <?php echo (!$edit_category || $edit_category['category_type'] == 'product-category') ? 'selected' : ''; ?>>Product Category</option>
                    <option value="product-name" <?php echo ($edit_category && $edit_category['category_type'] == 'product-name') ? 'selected' : ''; ?>>Product Name</option>
                </select>
                <small style="color: #666;">Select the type of category</small>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Category Name *</label>
                <input type="text" name="category_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" 
                       value="<?php echo $edit_category ? htmlspecialchars($edit_category['category_name']) : ''; ?>" 
                       placeholder="e.g., Electronics, Laptops, etc.">
            </div>
            
            <div class="form-group" style="margin-bottom: 15px; display: none;">
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
    <div id="csv_upload" class="tab-content" style="display: <?php echo $active_tab === 'csv_upload' ? 'block' : 'none'; ?>; background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 30px;">
        <h2>Bulk Import Categories from CSV</h2>
        
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
    </div>
    
    <!-- All Categories List -->
    <div id="all_categories" class="tab-content" style="display: <?php echo $active_tab === 'all_categories' ? 'block' : 'none'; ?>;">
        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; font-size: 24px; color: #333;">Categories Hierarchy</h2>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="searchInput" placeholder="🔍 Search categories..." style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; width: 250px;">
                    <span id="resultCount" style="padding: 10px 15px; background: #f8f9fa; border-radius: 5px; font-weight: 500; color: #666;">Total: <?php echo count($categories); ?></span>
                </div>
            </div>
            
            <?php if(count($categories) > 0): ?>
                <div style="overflow-x: auto;">
                    <table id="categoriesTable" style="width: 100%; border-collapse: collapse;">
                        <tbody id="categoriesBody">
                            <?php 
                            $root_categories = [];
                            $child_categories = [];
                            
                            foreach($categories as $category) {
                                if(!$category['parent_id']) {
                                    $root_categories[] = $category;
                                } else {
                                    if(!isset($child_categories[$category['parent_id']])) {
                                        $child_categories[$category['parent_id']] = [];
                                    }
                                    $child_categories[$category['parent_id']][] = $category;
                                }
                            }
                            
                            foreach($root_categories as $root): 
                            ?>
                                <tr class="category-row parent-row" data-category="<?php echo htmlspecialchars(strtolower($root['category_name'])); ?>">
                                    <td style="padding: 15px; border-bottom: 1px solid #f0f0f0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <div style="display: flex; align-items: center; gap: 15px;">
                                            <button class="toggle-btn" data-parent-id="<?php echo $root['id']; ?>" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-weight: bold;">
                                                <?php echo isset($child_categories[$root['id']]) ? '▼' : ''; ?>
                                            </button>
                                            <div style="flex: 1;">
                                                <div style="color: white; font-weight: 600; font-size: 15px;">
                                                    <i class="fa fa-folder-open" style="margin-right: 8px;"></i><?php echo htmlspecialchars($root['category_name']); ?>
                                                </div>
                                                <small style="color: rgba(255,255,255,0.7);"><?php echo htmlspecialchars($root['category_type']); ?></small>
                                            </div>
                                            <div style="display: flex; gap: 8px; align-items: center;">
                                                <?php if(!empty($root['icon_class'])): ?>
                                                    <i class="fa <?php echo htmlspecialchars($root['icon_class']); ?>" style="color: white; font-size: 18px;"></i>
                                                <?php endif; ?>
                                                <span style="background: rgba(255,255,255,0.2); color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px;">ID: <?php echo $root['id']; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 15px; border-bottom: 1px solid #f0f0f0; text-align: right;">
                                        <form method="POST" style="display: inline; margin-right: 10px;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="category_id" value="<?php echo $root['id']; ?>">
                                            <button type="submit" class="status-btn <?php echo $root['is_active'] ? 'active' : 'inactive'; ?>" style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 12px;">
                                                <?php echo $root['is_active'] ? '✓ Active' : '✕ Inactive'; ?>
                                            </button>
                                        </form>
                                        <a href="?tab=all_categories&edit_id=<?php echo $root['id']; ?>" class="edit-btn" style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 12px; background: #007bff; color: white; text-decoration: none; display: inline-block; margin-right: 5px;">
                                            ✎ Edit
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this category?');">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?php echo $root['id']; ?>">
                                            <button type="submit" class="delete-btn" style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 12px; background: #dc3545; color: white;">
                                                🗑 Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                
                                <!-- Children of this parent -->
                                <?php if(isset($child_categories[$root['id']])): ?>
                                    <?php foreach($child_categories[$root['id']] as $child): ?>
                                        <tr class="category-row child-row" data-parent-id="<?php echo $root['id']; ?>" data-category="<?php echo htmlspecialchars(strtolower($child['category_name'])); ?>" style="display: table-row; background: #f8f9fa;">
                                            <td style="padding: 15px; border-bottom: 1px solid #e0e0e0;">
                                                <div style="display: flex; align-items: center; gap: 15px; margin-left: 40px;">
                                                    <span style="color: #667eea; font-size: 12px; font-weight: bold;">└─</span>
                                                    <div style="flex: 1;">
                                                        <div style="color: #333; font-weight: 500; font-size: 14px;">
                                                            <i class="fa fa-tag" style="margin-right: 8px; color: #667eea;"></i><?php echo htmlspecialchars($child['category_name']); ?>
                                                        </div>
                                                        <small style="color: #999;"><?php echo htmlspecialchars($child['category_type']); ?></small>
                                                    </div>
                                                    <div style="display: flex; gap: 8px; align-items: center;">
                                                        <?php if(!empty($child['icon_class'])): ?>
                                                            <i class="fa <?php echo htmlspecialchars($child['icon_class']); ?>" style="color: #667eea; font-size: 16px;"></i>
                                                        <?php endif; ?>
                                                        <span style="background: #e7f3ff; color: #667eea; padding: 3px 8px; border-radius: 3px; font-size: 12px;">ID: <?php echo $child['id']; ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="padding: 15px; border-bottom: 1px solid #e0e0e0; text-align: right;">
                                                <form method="POST" style="display: inline; margin-right: 10px;">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="category_id" value="<?php echo $child['id']; ?>">
                                                    <button type="submit" class="status-btn <?php echo $child['is_active'] ? 'active' : 'inactive'; ?>" style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 12px;">
                                                        <?php echo $child['is_active'] ? '✓ Active' : '✕ Inactive'; ?>
                                                    </button>
                                                </form>
                                                <a href="?tab=all_categories&edit_id=<?php echo $child['id']; ?>" class="edit-btn" style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 12px; background: #007bff; color: white; text-decoration: none; display: inline-block; margin-right: 5px;">
                                                    ✎ Edit
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this category?');">
                                                    <input type="hidden" name="action" value="delete_category">
                                                    <input type="hidden" name="category_id" value="<?php echo $child['id']; ?>">
                                                    <button type="submit" class="delete-btn" style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 12px; background: #dc3545; color: white;">
                                                        🗑 Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="background: #e7f3ff; color: #0066cc; padding: 20px; border-radius: 5px; text-align: center; border-left: 4px solid #0066cc;">
                    <i class="fa fa-info-circle" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                    No categories found. <a href="?tab=add_form" style="color: #0066cc; font-weight: bold;">Add one now!</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Toggle expand/collapse for parent categories
document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const parentId = this.getAttribute('data-parent-id');
        const childRows = document.querySelectorAll(`tr.child-row[data-parent-id="${parentId}"]`);
        const isVisible = childRows.length > 0 && childRows[0].style.display === 'table-row';
        
        childRows.forEach(row => {
            row.style.display = isVisible ? 'none' : 'table-row';
        });
        
        this.textContent = isVisible ? '▶' : '▼';
    });
});

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('tr.category-row');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const categoryName = row.getAttribute('data-category');
        const parentId = row.getAttribute('data-parent-id');
        
        if(categoryName && categoryName.includes(searchTerm)) {
            row.style.display = 'table-row';
            visibleCount++;
            
            // Show parent if child is visible
            if(parentId) {
                const parentRow = document.querySelector(`tr.parent-row[data-category*="${parentId}"]`);
                if(parentRow) parentRow.style.display = 'table-row';
                
                // Expand parent if child matches search
                const toggleBtn = document.querySelector(`.toggle-btn[data-parent-id="${parentId}"]`);
                if(toggleBtn) toggleBtn.textContent = '▼';
            }
        } else if(!searchTerm) {
            // Reset to original state when search is cleared
            row.style.display = 'table-row';
        } else if(row.classList.contains('parent-row')) {
            // Hide parent if no children match
            const parentId = row.querySelector('.toggle-btn').getAttribute('data-parent-id');
            const childRows = document.querySelectorAll(`tr.child-row[data-parent-id="${parentId}"]`);
            const hasVisibleChild = Array.from(childRows).some(r => r.style.display !== 'none');
            
            if(hasVisibleChild) {
                row.style.display = 'table-row';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        } else {
            row.style.display = 'none';
        }
    });
    
    document.getElementById('resultCount').textContent = 'Found: ' + visibleCount;
});
</script>

<style>
.main3 {
    padding: 20px;
    background: #f5f7fa;
}

.status-btn.active {
    background-color: #28a745 !important;
    color: white !important;
}

.status-btn.inactive {
    background-color: #6c757d !important;
    color: white !important;
}

.status-btn:hover {
    opacity: 0.9;
}

.edit-btn:hover {
    background-color: #0056b3 !important;
}

.delete-btn:hover {
    background-color: #c82333 !important;
}

.toggle-btn:hover {
    opacity: 0.8;
}

#categoriesTable {
    border-spacing: 0;
    border-collapse: separate;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

#categoriesTable tr:first-child td {
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}

#categoriesTable tbody tr:last-child td {
    border-bottom-left-radius: 8px;
    border-bottom-right-radius: 8px;
}

.category-row:hover {
    background-color: rgba(102, 126, 234, 0.05);
    transition: background-color 0.2s ease;
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
