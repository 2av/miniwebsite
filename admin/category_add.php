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

// Get all categories for parent selection dropdown
function getAllCategoriesForDropdown($connect, $exclude_id = null) {
    $categories = [];
    $query = "SELECT id, parent_id, category_name, display_order FROM product_categories ORDER BY parent_id, display_order ASC";
    $result = mysqli_query($connect, $query);
    
    while($row = mysqli_fetch_assoc($result)) {
        if($exclude_id && $row['id'] == $exclude_id) {
            continue;
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
            $indent = str_repeat('— ', $depth);
            $cat['indent'] = $indent;
            $cat['indent_html'] = str_repeat('&nbsp;&nbsp;', $depth);
            $cat['depth'] = $depth;
            $tree[] = $cat;
            
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
            
            $check_query = mysqli_query($connect, "SELECT id FROM product_categories WHERE category_name='$category_name' OR category_slug='$category_slug'");
            
            if(mysqli_num_rows($check_query) > 0) {
                $error_message = "Category already exists!";
            } else {
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
    }
}

$edit_category = null;
if(isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $query = "SELECT * FROM product_categories WHERE id=$edit_id";
    $result = mysqli_query($connect, $query);
    if($result && mysqli_num_rows($result) > 0) {
        $edit_category = mysqli_fetch_assoc($result);
    }
}

$categories = getAllCategoriesForDropdown($connect);
$parent_categories = buildCategoryTree(getAllCategoriesForDropdown($connect));
?>

<div class="main3">
    <div style="display: flex; gap: 10px; margin-bottom: 30px;">
        <a href="category_add.php" class="btn btn-primary" style="margin-right: 10px;">
            <i class="fa fa-plus"></i> Add New Category
        </a>
        <a href="category_bulk_import.php" class="btn btn-info" style="margin-right: 10px;">
            <i class="fa fa-upload"></i> Bulk Import (CSV)
        </a>
        <a href="category_list.php" class="btn btn-secondary" style="margin-right: 10px;">
            <i class="fa fa-list"></i> All Categories
        </a>
        <a href="export_categories.php" class="btn btn-warning" style="margin-right: 10px;">
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
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Category Name <span style="color: red;">*</span></label>
                <input type="text" name="category_name" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" 
                       value="<?php echo $edit_category ? htmlspecialchars($edit_category['category_name']) : ''; ?>" 
                       placeholder="e.g., Electronics, Laptops, etc." required>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Category Type <span style="color: red;">*</span></label>
                <select name="category_type" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" required>
                    <option value="business-category" <?php echo ($edit_category && $edit_category['category_type'] === 'business-category') ? 'selected' : ''; ?>>Business Category</option>
                    <option value="product-category" <?php echo (!$edit_category || $edit_category['category_type'] === 'product-category') ? 'selected' : ''; ?>>Product Category</option>
                    <option value="product-name" <?php echo ($edit_category && $edit_category['category_type'] === 'product-name') ? 'selected' : ''; ?>>Product Name</option>
                    <option value="service" <?php echo ($edit_category && $edit_category['category_type'] === 'service') ? 'selected' : ''; ?>>Service</option>
                </select>
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
                       value="<?php echo $edit_category ? $edit_category['display_order'] : '0'; ?>">
            </div>
            
            <button type="submit" class="btn btn-success" style="padding: 10px 20px; font-size: 16px;">
                <i class="fa fa-save"></i> <?php echo $edit_category ? 'Update Category' : 'Add Category'; ?>
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

.form-group input,
.form-group select,
.form-group textarea {
    font-family: inherit;
}
</style>

<?php include('footer.php'); ?>
