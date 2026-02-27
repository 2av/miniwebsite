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
                        // Build category tree with all levels
                        $category_map = [];
                        foreach($all_categories as $cat) {
                            $category_map[$cat['id']] = $cat;
                        }
                        
                        // Function to recursively render categories
                        function renderCategoryTree($parent_id, $all_categories, $depth = 0) {
                            $html = '';
                            foreach($all_categories as $category) {
                                if($category['parent_id'] == $parent_id || ($parent_id === null && $category['parent_id'] === null)) {
                                    $has_children = false;
                                    foreach($all_categories as $check) {
                                        if($check['parent_id'] == $category['id']) {
                                            $has_children = true;
                                            break;
                                        }
                                    }
                                    
                                    $indent = $depth * 40;
                                    $margin_left = $indent + 15;
                                    
                                    // Determine colors based on depth
                                    $background_colors = [
                                        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',  // Root
                                        '#f0f4ff',  // Level 1
                                        '#f8fbff',  // Level 2+
                                    ];
                                    $bg_color = $background_colors[min($depth, 2)];
                                    
                                    $text_colors = ['white', '#333', '#555'];
                                    $text_color = $text_colors[min($depth, 2)];
                                    
                                    $icon_colors = ['white', '#667eea', '#667eea'];
                                    $icon_color = $icon_colors[min($depth, 2)];
                                    
                                    $html .= '<tr class="category-row ' . ($depth === 0 ? 'parent-row' : 'child-row') . '" data-category="' . htmlspecialchars(strtolower($category['category_name'])) . '" data-depth="' . $depth . '" style="display: table-row; background: ' . $bg_color . ';">';
                                    
                                    $html .= '<td style="padding: 15px; border-bottom: 1px solid #e0e0e0;">';
                                    $html .= '<div style="display: flex; align-items: center; gap: 15px; margin-left: ' . $margin_left . 'px;">';
                                    
                                    if($has_children) {
                                        $html .= '<button class="toggle-btn" data-category-id="' . $category['id'] . '" style="background: rgba(102, 126, 234, 0.1); border: 1px solid rgba(102, 126, 234, 0.3); color: #667eea; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-weight: bold; font-size: 14px;">▼</button>';
                                    } else {
                                        $html .= '<span style="width: 30px;"></span>';
                                    }
                                    
                                    $html .= '<div style="flex: 1;">';
                                    $html .= '<div style="color: ' . $text_color . '; font-weight: 600; font-size: 15px;">';
                                    
                                    if($depth > 0) {
                                        for($i = 0; $i < $depth; $i++) {
                                            $html .= '&nbsp;&nbsp;';
                                        }
                                        $html .= '└─ ';
                                    }
                                    
                                    $icon_classes = ['fa-folder-open', 'fa-tag', 'fa-circle'];
                                    $icon_class = isset($icon_classes[$depth]) ? $icon_classes[$depth] : 'fa-circle';
                                    
                                    $html .= '<i class="fa ' . $icon_class . '" style="margin-right: 8px; color: ' . $icon_color . ';"></i>' . htmlspecialchars($category['category_name']);
                                    $html .= '</div>';
                                    $html .= '<small style="color: ' . ($text_color === 'white' ? 'rgba(255,255,255,0.7)' : '#999') . ';">' . htmlspecialchars($category['category_type']) . '</small>';
                                    $html .= '</div>';
                                    
                                    $html .= '<div style="display: flex; gap: 8px; align-items: center;">';
                                    if(!empty($category['icon_class'])) {
                                        $html .= '<i class="fa ' . htmlspecialchars($category['icon_class']) . '" style="color: ' . $icon_color . '; font-size: 16px;"></i>';
                                    }
                                    $bg_badge = $depth === 0 ? 'rgba(255,255,255,0.2)' : '#e7f3ff';
                                    $color_badge = $depth === 0 ? 'white' : '#667eea';
                                    $html .= '<span style="background: ' . $bg_badge . '; color: ' . $color_badge . '; padding: 3px 8px; border-radius: 3px; font-size: 12px;">ID: ' . $category['id'] . '</span>';
                                    $html .= '</div>';
                                    $html .= '</div>';
                                    $html .= '</td>';
                                    
                                    $html .= '<td style="padding: 15px; border-bottom: 1px solid #e0e0e0; text-align: right;">';
                                    $html .= '<form method="POST" style="display: inline; margin-right: 10px;">';
                                    $html .= '<input type="hidden" name="action" value="toggle_status">';
                                    $html .= '<input type="hidden" name="category_id" value="' . $category['id'] . '">';
                                    $html .= '<button type="submit" class="status-btn ' . ($category['is_active'] ? 'active' : 'inactive') . '" style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 12px;">';
                                    $html .= $category['is_active'] ? '✓ Active' : '✕ Inactive';
                                    $html .= '</button>';
                                    $html .= '</form>';
                                    $html .= '<a href="category_add.php?edit_id=' . $category['id'] . '" class="edit-btn" style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 12px; background: #007bff; color: white; text-decoration: none; display: inline-block; margin-right: 5px;">✎ Edit</a>';
                                    $html .= '<form method="POST" style="display: inline;" onsubmit="return confirm(\'Delete this category?\');">';
                                    $html .= '<input type="hidden" name="action" value="delete_category">';
                                    $html .= '<input type="hidden" name="category_id" value="' . $category['id'] . '">';
                                    $html .= '<button type="submit" class="delete-btn" style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 12px; background: #dc3545; color: white;">🗑 Delete</button>';
                                    $html .= '</form>';
                                    $html .= '</td>';
                                    
                                    $html .= '</tr>';
                                    
                                    // Recursively render children
                                    $html .= renderCategoryTree($category['id'], $all_categories, $depth + 1);
                                }
                            }
                            return $html;
                        }
                        
                        echo renderCategoryTree(null, $all_categories);
                        ?>
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
// Build a map of parent-child relationships
const buildHierarchyMap = () => {
    const map = {};
    const rows = document.querySelectorAll('tr.category-row');
    
    rows.forEach(row => {
        const depth = parseInt(row.getAttribute('data-depth'));
        const index = Array.from(rows).indexOf(row);
        
        map[index] = {
            depth: depth,
            children: []
        };
    });
    
    // Find children for each row
    rows.forEach((row, index) => {
        const currentDepth = parseInt(row.getAttribute('data-depth'));
        let nextIndex = index + 1;
        
        while(nextIndex < rows.length) {
            const nextRow = rows[nextIndex];
            const nextDepth = parseInt(nextRow.getAttribute('data-depth'));
            
            if(nextDepth <= currentDepth) break;
            
            if(nextDepth === currentDepth + 1) {
                map[index].children.push(nextIndex);
            }
            
            nextIndex++;
        }
    });
    
    return map;
};

const hierarchyMap = buildHierarchyMap();

// Toggle expand/collapse for categories
document.querySelectorAll('.toggle-btn').forEach((btn, index) => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const rows = document.querySelectorAll('tr.category-row');
        
        // Find the index of the parent row
        let parentIndex = -1;
        rows.forEach((row, i) => {
            const toggleBtnInRow = row.querySelector('.toggle-btn');
            if(toggleBtnInRow === btn) {
                parentIndex = i;
            }
        });
        
        if(parentIndex === -1) return;
        
        const childIndices = getDescendants(parentIndex, rows);
        const firstChildRow = rows[childIndices[0]];
        const isVisible = firstChildRow && firstChildRow.style.display === 'table-row';
        
        childIndices.forEach(childIndex => {
            rows[childIndex].style.display = isVisible ? 'none' : 'table-row';
        });
        
        this.textContent = isVisible ? '▶' : '▼';
    });
});

// Get all descendants (not just direct children)
const getDescendants = (parentIndex, rows) => {
    const parentRow = rows[parentIndex];
    const parentDepth = parseInt(parentRow.getAttribute('data-depth'));
    const descendants = [];
    
    for(let i = parentIndex + 1; i < rows.length; i++) {
        const row = rows[i];
        const depth = parseInt(row.getAttribute('data-depth'));
        
        if(depth <= parentDepth) break;
        
        descendants.push(i);
    }
    
    return descendants;
};

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('tr.category-row');
    let visibleCount = 0;
    
    if(!searchTerm) {
        // Reset to default view
        rows.forEach(row => {
            row.style.display = 'table-row';
        });
        visibleCount = rows.length;
    } else {
        // Find matching categories and show them with ancestors
        rows.forEach((row, index) => {
            const categoryName = row.getAttribute('data-category');
            
            if(categoryName && categoryName.includes(searchTerm)) {
                row.style.display = 'table-row';
                visibleCount++;
                
                // Show all ancestors
                let currentDepth = parseInt(row.getAttribute('data-depth'));
                for(let i = index - 1; i >= 0; i--) {
                    const potentialParent = rows[i];
                    const parentDepth = parseInt(potentialParent.getAttribute('data-depth'));
                    
                    if(parentDepth < currentDepth) {
                        potentialParent.style.display = 'table-row';
                        currentDepth = parentDepth;
                    }
                    
                    if(currentDepth === 0) break;
                }
                
                // Expand parent toggle buttons
                const parentDepth = parseInt(row.getAttribute('data-depth'));
                if(parentDepth > 0) {
                    for(let i = index - 1; i >= 0; i--) {
                        const potentialParent = rows[i];
                        const parentDepth = parseInt(potentialParent.getAttribute('data-depth'));
                        
                        if(parentDepth < parseInt(row.getAttribute('data-depth'))) {
                            const toggleBtn = potentialParent.querySelector('.toggle-btn');
                            if(toggleBtn) toggleBtn.textContent = '▼';
                            break;
                        }
                    }
                }
            } else {
                row.style.display = 'none';
            }
        });
        
        // Count visible rows
        visibleCount = Array.from(rows).filter(r => r.style.display === 'table-row').length;
    }
    
    document.getElementById('resultCount').textContent = visibleCount > 0 ? 'Found: ' + visibleCount : 'No results found';
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
