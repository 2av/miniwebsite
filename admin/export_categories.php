<?php
require_once(__DIR__ . '/../app/config/database.php');

// Check if admin is logged in
if(!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

// Get all categories
$query = "SELECT * FROM product_categories ORDER BY parent_id, display_order ASC";
$result = mysqli_query($connect, $query);

if(!$result) {
    header('HTTP/1.1 500 Internal Server Error');
    die('Error fetching categories: ' . mysqli_error($connect));
}

$all_categories = [];
$category_map = [];
while($row = mysqli_fetch_assoc($result)) {
    $all_categories[] = $row;
    $category_map[$row['id']] = $row;
}

// Build path from category to root
function getCategoryPath($cat_id, $category_map) {
    $path = [];
    $id = $cat_id;
    while($id && isset($category_map[$id])) {
        array_unshift($path, $category_map[$id]);
        $id = $category_map[$id]['parent_id'] ? (int)$category_map[$id]['parent_id'] : null;
    }
    return $path;
}

// Map path to Business Hierarchical Format row
function pathToCsvRow($path) {
    $row = [
        'business_heading' => '',
        'business_category' => '',
        'business_category_slug' => '',
        'product_category' => '',
        'product_category_slug' => '',
        'product_name' => '',
        'product_slug' => ''
    ];
    $bc_count = 0;
    foreach($path as $cat) {
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
        } elseif($cat['category_type'] === 'product-name' || $cat['category_type'] === 'service') {
            $row['product_name'] = $cat['category_name'];
            $row['product_slug'] = $cat['category_slug'];
        }
    }
    return $row;
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="categories_export_' . date('Y-m-d_H-i-s') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header row - Business Hierarchical Format
fputcsv($output, [
    'Business Heading',
    'Business Category',
    'Business Category Slug',
    'Product Category',
    'Product Category Slug',
    'Product Name',
    'Product Slug'
]);

// Write data rows
foreach($all_categories as $cat) {
    $path = getCategoryPath($cat['id'], $category_map);
    $csv_row = pathToCsvRow($path);
    fputcsv($output, [
        $csv_row['business_heading'],
        $csv_row['business_category'],
        $csv_row['business_category_slug'],
        $csv_row['product_category'],
        $csv_row['product_category_slug'],
        $csv_row['product_name'],
        $csv_row['product_slug']
    ]);
}

fclose($output);
exit;
