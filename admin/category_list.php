<?php
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/includes/admin_category_directory_helper.php');
require('header.php');

if(!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    echo '<script>alert("Please login first!"); window.location.href="login.php";</script>';
    exit;
}

ensureAdminCategoryTable($connect);
$table = adminCategoryTableName();

$success_message = '';
$error_message = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $row_id = intval($_POST['row_id'] ?? 0);
    if($_POST['action'] == 'toggle_status' && $row_id > 0) {
        if(mysqli_query($connect, "UPDATE `$table` SET is_active = NOT is_active WHERE id=$row_id")) {
            $success_message = "Status updated successfully!";
        } else {
            $error_message = "Error updating status!";
        }
    }
    if($_POST['action'] == 'delete_row' && $row_id > 0) {
        if(mysqli_query($connect, "DELETE FROM `$table` WHERE id=$row_id")) {
            $success_message = "Row deleted successfully!";
        } else {
            $error_message = "Error deleting row!";
        }
    }
}

$result = mysqli_query($connect, "SELECT * FROM `$table` ORDER BY directory_priority ASC, business_heading ASC, business_category ASC, product_category ASC");
$rows = [];
if($result) {
    while($r = mysqli_fetch_assoc($result)) {
        $rows[] = $r;
    }
}

$csv_headers = adminCategoryCsvHeaders();
?>

<div class="main3">
    <div style="display: flex; gap: 10px; margin-bottom: 30px;">
        <a href="category_add.php" class="btn btn-primary"><i class="fa fa-plus"></i> Add New Category</a>
        <a href="category_bulk_import.php" class="btn btn-info"><i class="fa fa-upload"></i> Bulk Import (CSV)</a>
        <a href="category_list.php" class="btn btn-secondary"><i class="fa fa-list"></i> All Categories</a>
        <a href="export_categories.php" class="btn btn-warning"><i class="fa fa-download"></i> Export Data</a>
    </div>

    <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <?php if($success_message): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if($error_message): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">Category Directory (Admin)</h2>
            <div style="display: flex; gap: 10px;">
                <input type="text" id="searchInput" placeholder="Search..." style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; width: 250px;">
                <span id="resultCount" style="padding: 10px 15px; background: #f8f9fa; border-radius: 5px;">Total: <?php echo count($rows); ?></span>
            </div>
        </div>

        <?php if(count($rows) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="csv-format-table" id="categoriesTable">
                    <thead>
                        <tr>
                            <?php foreach($csv_headers as $h): ?>
                                <th><?php echo htmlspecialchars($h); ?></th>
                            <?php endforeach; ?>
                            <th style="width: 160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $r):
                            $searchable = strtolower(implode(' ', [
                                $r['business_profile_type'], $r['business_heading'], $r['business_category'],
                                $r['product_category'], $r['keywords'], $r['tags']
                            ]));
                        ?>
                        <tr class="category-row" data-search="<?php echo htmlspecialchars($searchable); ?>">
                            <td><?php echo htmlspecialchars($r['business_profile_type']); ?></td>
                            <td><?php echo htmlspecialchars($r['business_heading']); ?></td>
                            <td><?php echo htmlspecialchars($r['business_category']); ?></td>
                            <td><code><?php echo htmlspecialchars($r['business_category_slug']); ?></code></td>
                            <td><?php echo htmlspecialchars($r['product_category']); ?></td>
                            <td><code><?php echo htmlspecialchars($r['product_category_slug']); ?></code></td>
                            <td><?php echo (int) $r['directory_priority']; ?></td>
                            <td><?php echo $r['is_active'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo htmlspecialchars($r['keywords'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['tags'] ?? ''); ?></td>
                            <td style="white-space: nowrap;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="row_id" value="<?php echo (int) $r['id']; ?>">
                                    <button type="submit" class="status-btn <?php echo $r['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $r['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this row?');">
                                    <input type="hidden" name="action" value="delete_row">
                                    <input type="hidden" name="row_id" value="<?php echo (int) $r['id']; ?>">
                                    <button type="submit" class="delete-btn">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:24px;background:#e7f3ff;border-radius:5px;">
                No rows yet. <a href="category_bulk_import.php">Import CSV</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    const term = this.value.toLowerCase();
    const rows = document.querySelectorAll('tr.category-row');
    let n = 0;
    rows.forEach(row => {
        const show = !term || (row.getAttribute('data-search') || '').includes(term);
        row.style.display = show ? '' : 'none';
        if(show) n++;
    });
    document.getElementById('resultCount').textContent = term ? (n ? 'Found: ' + n : 'No results') : 'Total: ' + rows.length;
});
</script>

<style>
.main3 { padding: 20px; background: #f5f7fa; }
.csv-format-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.csv-format-table th, .csv-format-table td { padding: 10px 12px; border-bottom: 1px solid #e0e0e0; }
.csv-format-table th { background: #333; color: #fff; white-space: nowrap; }
.status-btn { border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; color: #fff; }
.status-btn.active { background: #28a745; }
.status-btn.inactive { background: #6c757d; }
.delete-btn { background: #dc3545; color: #fff; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn { padding: 8px 16px; border-radius: 4px; text-decoration: none; color: #fff; display: inline-block; font-size: 14px; }
.btn-primary { background: #007bff; } .btn-info { background: #17a2b8; } .btn-secondary { background: #6c757d; } .btn-warning { background: #ffc107; color: #333; }
</style>

<?php include('footer.php'); ?>
