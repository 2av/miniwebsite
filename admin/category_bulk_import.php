<?php
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/includes/admin_category_directory_helper.php');

if(!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

ensureAdminCategoryTable($connect);
$table = adminCategoryTableName();

/**
 * Insert rows in batches (fast for large CSV files).
 */
function adminBulkUpsertCategories($connect, $table, array $rows, $skip_duplicates) {
    if (empty($rows)) {
        return 0;
    }

    $columns = 'business_profile_type, business_heading, business_category, business_category_slug,
        product_category, product_category_slug, directory_priority, is_active, keywords, tags, created_by';

    $processed = 0;
    $chunks = array_chunk($rows, 200);

    foreach ($chunks as $chunk) {
        $value_parts = [];
        foreach ($chunk as $r) {
            $value_parts[] = "(
                '{$r['business_profile_type']}',
                '{$r['business_heading']}',
                '{$r['business_category']}',
                '{$r['business_category_slug']}',
                '{$r['product_category']}',
                '{$r['product_category_slug']}',
                {$r['directory_priority']},
                {$r['is_active']},
                '{$r['keywords']}',
                '{$r['tags']}',
                '{$r['created_by']}'
            )";
        }

        $sql = "INSERT INTO `$table` ($columns) VALUES " . implode(",\n", $value_parts);

        if (!$skip_duplicates) {
            $sql .= "
                ON DUPLICATE KEY UPDATE
                business_profile_type = VALUES(business_profile_type),
                business_heading = VALUES(business_heading),
                business_category = VALUES(business_category),
                product_category = VALUES(product_category),
                directory_priority = VALUES(directory_priority),
                is_active = VALUES(is_active),
                keywords = VALUES(keywords),
                tags = VALUES(tags),
                updated_at = NOW()";
        } else {
            $sql .= ' ON DUPLICATE KEY UPDATE id = id';
        }

        if (mysqli_query($connect, $sql)) {
            $processed += count($chunk);
        }
    }

    return $processed;
}

// Process upload before any HTML output (avoids timeout from header/sidebar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_csv') {
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');

    $result = [
        'success' => '',
        'error' => '',
        'imported' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'Please select a valid CSV file!';
    } else {
        $file_extension = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        $max_file_size = 20 * 1024 * 1024;

        if ($file_extension !== 'csv') {
            $result['error'] = 'Only CSV files are allowed!';
        } elseif ($_FILES['csv_file']['size'] > $max_file_size) {
            $result['error'] = 'File size exceeds 20MB limit!';
        } else {
            $csv_upload_dir = __DIR__ . '/../assets/upload/category-imports/';
            if (!is_dir($csv_upload_dir)) {
                mkdir($csv_upload_dir, 0755, true);
            }
            $saved_csv_filename = 'category_import_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['csv_file']['name']));
            copy($_FILES['csv_file']['tmp_name'], $csv_upload_dir . $saved_csv_filename);

            $replace_all = !empty($_POST['replace_all']);
            $skip_duplicates = !empty($_POST['skip_duplicates']);

            if ($replace_all) {
                mysqli_query($connect, "TRUNCATE TABLE `$table`");
            }

            $file_handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($file_handle) {
                $header = fgetcsv($file_handle);
                $header_lower = array_map('strtolower', array_map('trim', $header ?: []));
                $missing_headers = array_diff(adminCategoryRequiredHeaderKeys(), $header_lower);

                if (!empty($missing_headers)) {
                    $result['error'] = 'Invalid CSV format. Required columns: ' . implode(', ', adminCategoryCsvHeaders());
                } else {
                    $created_by = trim(preg_replace('/[\r\n\x00]/', '', $_SESSION['admin_email'] ?? ''));
                    if ($created_by === '') {
                        $created_by = 'admin';
                    }
                    $created_by_esc = mysqli_real_escape_string($connect, $created_by);

                    $col_map = [];
                    foreach ($header as $i => $col) {
                        $col_map[strtolower(trim($col))] = $i;
                    }

                    $pending_rows = [];
                    $row_number = 0;
                    $max_errors = 50;

                    while (($data = fgetcsv($file_handle)) !== false) {
                        $row_number++;
                        if (!empty($data[0]) && strpos(trim($data[0]), '#') === 0) {
                            continue;
                        }

                        $get_col = function ($key) use ($data, $col_map) {
                            $idx = $col_map[$key] ?? -1;
                            return ($idx >= 0 && isset($data[$idx])) ? trim($data[$idx]) : '';
                        };

                        $business_profile_type = $get_col('business profile type');
                        $business_heading = $get_col('business heading');
                        $business_category = $get_col('business category');
                        $business_category_slug = $get_col('business category slug');
                        $product_category = $get_col('product category');
                        $product_category_slug = $get_col('product category slug');
                        $directory_priority = $get_col('directory priority');
                        $is_active_raw = $get_col('is active');
                        $keywords = $get_col('keywords');
                        $tags = $get_col('tags');

                        if ($business_heading === '' && $business_category === '' && $product_category === '') {
                            continue;
                        }

                        $missing = [];
                        if ($business_profile_type === '') $missing[] = 'Business Profile Type';
                        if ($business_heading === '') $missing[] = 'Business Heading';
                        if ($business_category === '') $missing[] = 'Business Category';
                        if ($business_category_slug === '') $missing[] = 'Business Category Slug';
                        if ($product_category === '') $missing[] = 'Product Category';
                        if ($product_category_slug === '') $missing[] = 'Product Category Slug';
                        if ($directory_priority === '') $missing[] = 'Directory Priority';

                        if (!empty($missing)) {
                            if (count($result['errors']) < $max_errors) {
                                $result['errors'][] = "Row $row_number: Missing: " . implode(', ', $missing);
                            }
                            continue;
                        }

                        if ($business_category_slug === '') {
                            $business_category_slug = adminSlugify($business_category);
                        }
                        if ($product_category_slug === '') {
                            $product_category_slug = adminSlugify($product_category);
                        }

                        $pending_rows[] = [
                            'business_profile_type' => mysqli_real_escape_string($connect, $business_profile_type),
                            'business_heading' => mysqli_real_escape_string($connect, $business_heading),
                            'business_category' => mysqli_real_escape_string($connect, $business_category),
                            'business_category_slug' => mysqli_real_escape_string($connect, $business_category_slug),
                            'product_category' => mysqli_real_escape_string($connect, $product_category),
                            'product_category_slug' => mysqli_real_escape_string($connect, $product_category_slug),
                            'directory_priority' => is_numeric($directory_priority) ? (int) $directory_priority : 0,
                            'is_active' => adminParseIsActive($is_active_raw),
                            'keywords' => mysqli_real_escape_string($connect, $keywords),
                            'tags' => mysqli_real_escape_string($connect, $tags),
                            'created_by' => $created_by_esc,
                        ];
                    }
                    fclose($file_handle);

                    mysqli_query($connect, 'SET autocommit=0');
                    $result['imported'] = adminBulkUpsertCategories($connect, $table, $pending_rows, $skip_duplicates);
                    mysqli_query($connect, 'COMMIT');
                    mysqli_query($connect, 'SET autocommit=1');

                    $result['success'] = 'Import completed! Processed ' . $result['imported'] . ' rows.';
                    $result['success'] .= ' File saved: ' . $saved_csv_filename;

                    if (!empty($result['errors'])) {
                        $more = $row_number > $max_errors ? ' (showing first ' . $max_errors . ')' : '';
                        $result['error'] = count($result['errors']) . ' row(s) had validation errors' . $more . '.';
                    }
                }
            } else {
                $result['error'] = 'Could not read CSV file.';
            }
        }
    }

    $_SESSION['category_import_result'] = $result;
    header('Location: category_bulk_import.php?imported=1');
    exit;
}

require('header.php');

$success_message = '';
$error_message = '';
$error_rows = [];

if (!empty($_GET['imported']) && !empty($_SESSION['category_import_result'])) {
    $import_result = $_SESSION['category_import_result'];
    unset($_SESSION['category_import_result']);

    if (!empty($import_result['success'])) {
        $success_message = $import_result['success'];
    }
    if (!empty($import_result['error'])) {
        $error_message = $import_result['error'];
        if (!empty($import_result['errors'])) {
            $error_rows = $import_result['errors'];
        }
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
            <div class="alert ok"><i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if($error_message): ?>
            <div class="alert err"><i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
            <?php if(!empty($error_rows)): ?>
                <ul style="margin:0 0 15px 20px; max-height:200px; overflow-y:auto;">
                    <?php foreach($error_rows as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>

        <h2>Bulk Import Categories from CSV</h2>
        <p style="color:#666; font-size:14px;">Large files are imported in batches (200 rows per query) for faster processing.</p>

        <div style="margin: 20px 0;">
            <a href="download_category_template_business.php" class="btn btn-info" download><i class="fa fa-download"></i> Download CSV Template</a>
        </div>

        <form method="POST" enctype="multipart/form-data" style="max-width: 700px;">
            <input type="hidden" name="action" value="import_csv">

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="replace_all" value="1" checked>
                    <span><strong>Replace all</strong> — clear table before import (recommended for full CSV)</span>
                </label>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="skip_duplicates" value="1" checked>
                    <span><strong>Skip duplicates</strong> — same business + product slug</span>
                </label>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight:bold;">Select CSV File</label>
                <input type="file" name="csv_file" accept=".csv" required style="width:100%;padding:10px;border:2px dashed #ddd;border-radius:4px;">
            </div>

            <div style="background:#f8f9fa;padding:15px;border-radius:5px;margin-bottom:20px;border-left:4px solid #007bff;">
                <h4 style="margin-top:0;">Required columns (all <?php echo count($csv_headers); ?>)</h4>
                <ul style="margin:0 0 12px 20px;">
                    <?php foreach($csv_headers as $h): ?>
                        <li><strong><?php echo htmlspecialchars($h); ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <button type="submit" class="btn btn-success"><i class="fa fa-upload"></i> Import CSV</button>
        </form>
    </div>
</div>

<style>
.main3{padding:20px;background:#f5f7fa;}
.btn{padding:8px 16px;border:none;border-radius:4px;text-decoration:none;display:inline-block;font-size:14px;color:#fff;margin-right:8px;}
.btn-primary{background:#007bff}.btn-info{background:#17a2b8}.btn-secondary{background:#6c757d}.btn-warning{background:#ffc107;color:#333}.btn-success{background:#28a745}
.alert.ok{background:#d4edda;padding:12px;margin-bottom:15px;border-radius:4px;color:#155724}
.alert.err{background:#f8d7da;padding:12px;margin-bottom:15px;border-radius:4px;color:#721c24}
</style>

<?php include('footer.php'); ?>
