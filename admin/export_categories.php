<?php
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/includes/admin_category_directory_helper.php');

if(!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

ensureAdminCategoryTable($connect);
$table = adminCategoryTableName();

$result = mysqli_query($connect, "SELECT * FROM `$table` ORDER BY directory_priority ASC, business_heading ASC, business_category ASC, product_category ASC");
if(!$result) {
    die('Error: ' . mysqli_error($connect));
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="categories_' . date('Y-m-d_H-i-s') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($output, adminCategoryCsvHeaders());

while($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['business_profile_type'],
        $row['business_heading'],
        $row['business_category'],
        $row['business_category_slug'],
        $row['product_category'],
        $row['product_category_slug'],
        $row['directory_priority'],
        $row['is_active'] ? '1' : '0',
        $row['keywords'] ?? '',
        $row['tags'] ?? '',
    ]);
}

fclose($output);
exit;
