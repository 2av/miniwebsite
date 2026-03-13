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
$category_map = [];

while($row = mysqli_fetch_assoc($result)) {
    $all_categories[] = $row;
    $category_map[$row['id']] = $row;
}

$categories = buildCategoryTree($all_categories);

// Build CSV-format rows: each category gets its full path mapped to Business Heading | Business Category | Product Category | Product Name
function getCategoryPath($cat_id, $category_map) {
    $path = [];
    $id = $cat_id;
    while($id && isset($category_map[$id])) {
        array_unshift($path, $category_map[$id]);
        $id = $category_map[$id]['parent_id'] ? (int)$category_map[$id]['parent_id'] : null;
    }
    return $path;
}

function pathToCsvRow($path) {
    $row = [
        'business_heading' => '',
        'business_category' => '',
        'business_category_slug' => '',
        'product_category' => '',
        'product_category_slug' => '',
        'product_name' => '',
        'product_slug' => '',
        'category_id' => null,
        'category_type' => '',
        'is_active' => 1,
        'depth' => 0
    ];
    $bc_count = 0;
    foreach($path as $i => $cat) {
        $row['category_id'] = $cat['id'];
        $row['category_type'] = $cat['category_type'];
        $row['is_active'] = $cat['is_active'];
        $row['depth'] = $i;
        if($cat['category_type'] === 'business-category') {
            if($bc_count === 0) {
                $row['business_heading'] = $cat['category_name'];
                $bc_count++;
            } else {
                $row['business_category'] = $cat['category_name'];
                $row['business_category_slug'] = $cat['category_slug'];
            }
        } elseif($cat['category_type'] === 'product-category') {
            $row['product_category'] = $cat['category_name'];
            $row['product_category_slug'] = $cat['category_slug'];
        } elseif($cat['category_type'] === 'product-name') {
            $row['product_name'] = $cat['category_name'];
            $row['product_slug'] = $cat['category_slug'];
        }
    }
    return $row;
}

$csv_format_rows = [];
foreach($all_categories as $cat) {
    $path = getCategoryPath($cat['id'], $category_map);
    $csv_format_rows[] = pathToCsvRow($path);
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
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 24px; color: #333;">Categories (CSV Format)</h2>
            <div style="display: flex; gap: 10px;">
                <input type="text" id="searchInput" placeholder="🔍 Search categories..." style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; width: 250px;">
                <span id="resultCount" style="padding: 10px 15px; background: #f8f9fa; border-radius: 5px; font-weight: 500; color: #666;">Total: <?php echo count($csv_format_rows); ?></span>
            </div>
        </div>
        
        <?php if(count($csv_format_rows) > 0): ?>
            <div style="overflow-x: auto;">
                <table id="categoriesTable" class="csv-format-table">
                    <thead>
                        <tr>
                            <th>Business Heading</th>
                            <th>Business Category</th>
                            <th>Business Category Slug</th>
                            <th>Product Category</th>
                            <th>Product Category Slug</th>
                            <th>Product Name</th>
                            <th>Product Slug</th>
                            <th style="width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="categoriesBody">
                        <?php foreach($csv_format_rows as $r): 
                            $searchable = strtolower(implode(' ', [$r['business_heading'], $r['business_category'], $r['product_category'], $r['product_name']]));
                            $row_bg = $r['depth'] === 0 ? 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;' : ($r['depth'] === 1 ? 'background: #f0f4ff;' : 'background: #f8fbff;');
                            $text_color = $r['depth'] === 0 ? 'white' : '#333';
                        ?>
                        <tr class="category-row" data-search="<?php echo htmlspecialchars($searchable); ?>" data-depth="<?php echo $r['depth']; ?>" style="<?php echo $row_bg; ?>">
                            <td><?php echo htmlspecialchars($r['business_heading']); ?></td>
                            <td><?php echo htmlspecialchars($r['business_category']); ?></td>
                            <td><code style="font-size: 11px;"><?php echo htmlspecialchars($r['business_category_slug']); ?></code></td>
                            <td><?php echo htmlspecialchars($r['product_category']); ?></td>
                            <td><code style="font-size: 11px;"><?php echo htmlspecialchars($r['product_category_slug']); ?></code></td>
                            <td><?php echo htmlspecialchars($r['product_name']); ?></td>
                            <td><code style="font-size: 11px;"><?php echo htmlspecialchars($r['product_slug']); ?></code></td>
                            <td style="white-space: nowrap;">
                                <form method="POST" style="display: inline; margin-right: 5px;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="category_id" value="<?php echo $r['category_id']; ?>">
                                    <button type="submit" class="status-btn <?php echo $r['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $r['is_active'] ? '✓ Active' : '✕ Inactive'; ?></button>
                                </form>
                                <a href="category_add.php?edit_id=<?php echo $r['category_id']; ?>" class="edit-btn">✎ Edit</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this category?');">
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="category_id" value="<?php echo $r['category_id']; ?>">
                                    <button type="submit" class="delete-btn">🗑 Delete</button>
                                </form>
                            </td>
                        </tr>
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
// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('tr.category-row');
    
    if(!searchTerm) {
        rows.forEach(row => row.style.display = '');
        document.getElementById('resultCount').textContent = 'Total: ' + rows.length;
    } else {
        let visibleCount = 0;
        rows.forEach(row => {
            const text = row.getAttribute('data-search') || '';
            const show = text.includes(searchTerm);
            row.style.display = show ? '' : 'none';
            if(show) visibleCount++;
        });
        document.getElementById('resultCount').textContent = visibleCount > 0 ? 'Found: ' + visibleCount : 'No results found';
    }
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

.csv-format-table {
    border-spacing: 0;
    border-collapse: collapse;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.csv-format-table th,
.csv-format-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #e0e0e0;
    text-align: left;
}

.csv-format-table th {
    background: #333;
    color: white;
    font-weight: 600;
    font-size: 13px;
}

.csv-format-table .category-row:hover {
    background-color: rgba(102, 126, 234, 0.08) !important;
    transition: background-color 0.2s ease;
}

.csv-format-table .status-btn,
.csv-format-table .edit-btn,
.csv-format-table .delete-btn {
    padding: 5px 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    text-decoration: none;
    display: inline-block;
    margin-right: 4px;
}

.csv-format-table .edit-btn {
    background: #007bff;
    color: white;
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
