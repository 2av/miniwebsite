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

// Handle status toggle
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if($_POST['action'] == 'toggle_status') {
        $category_id = intval($_POST['category_id']);
        $toggle_query = "UPDATE product_categories SET is_active = NOT is_active WHERE id=$category_id";
        
        if(mysqli_query($connect, $toggle_query)) {
            $success_message = "Category status updated successfully!";
        } else {
            $error_message = "Error updating category status!";
        }
    }
    
    if($_POST['action'] == 'delete_category') {
        $category_id = intval($_POST['category_id']);
        $delete_query = "DELETE FROM product_categories WHERE id=$category_id";
        
        if(mysqli_query($connect, $delete_query)) {
            $success_message = "Category deleted successfully!";
        } else {
            $error_message = "Error deleting category!";
        }
    }
}

// Get all categories
$query = "SELECT * FROM product_categories ORDER BY parent_id, display_order ASC";
$result = mysqli_query($connect, $query);
$all_categories = [];

while($row = mysqli_fetch_assoc($result)) {
    $all_categories[] = $row;
}

$categories = buildCategoryTree($all_categories);
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
                                    <a href="category_add.php?edit_id=<?php echo $root['id']; ?>" class="edit-btn" style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 12px; background: #007bff; color: white; text-decoration: none; display: inline-block; margin-right: 5px;">
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
                                            <a href="category_add.php?edit_id=<?php echo $child['id']; ?>" class="edit-btn" style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 12px; background: #007bff; color: white; text-decoration: none; display: inline-block; margin-right: 5px;">
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
                No categories found. <a href="category_add.php" style="color: #0066cc; font-weight: bold;">Add one now!</a>
            </div>
        <?php endif; ?>
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
            
            if(parentId) {
                const parentRow = document.querySelector(`tr.parent-row[data-category*="${parentId}"]`);
                if(parentRow) parentRow.style.display = 'table-row';
                
                const toggleBtn = document.querySelector(`.toggle-btn[data-parent-id="${parentId}"]`);
                if(toggleBtn) toggleBtn.textContent = '▼';
            }
        } else if(!searchTerm) {
            row.style.display = 'table-row';
        } else if(row.classList.contains('parent-row')) {
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

.status-btn:hover,
.edit-btn:hover,
.delete-btn:hover,
.toggle-btn:hover {
    opacity: 0.9;
}

.edit-btn:hover {
    background-color: #0056b3 !important;
}

.delete-btn:hover {
    background-color: #c82333 !important;
}

#categoriesTable {
    border-spacing: 0;
    border-collapse: separate;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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

.btn-primary { background-color: #007bff; color: white; }
.btn-primary:hover { background-color: #0056b3; }
.btn-info { background-color: #17a2b8; color: white; }
.btn-info:hover { background-color: #138496; }
.btn-secondary { background-color: #6c757d; color: white; }
.btn-secondary:hover { background-color: #5a6268; }
.btn-warning { background-color: #ffc107; color: #333; }
.btn-warning:hover { background-color: #e0a800; }
</style>

<?php include('footer.php'); ?>
