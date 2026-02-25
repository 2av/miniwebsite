<?php
require_once(__DIR__ . '/../app/config/database.php');

// Check if admin is logged in
if(!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    echo '<script>alert("Please login first!"); window.location.href="login.php";</script>';
    exit;
}

// Get all categories with parent information
$query = "SELECT 
    c.id,
    c.category_name,
    c.category_type,
    c.description,
    c.icon_class,
    c.display_order,
    COALESCE(p.category_name, '') as parent_name
FROM product_categories c
LEFT JOIN product_categories p ON c.parent_id = p.id
ORDER BY c.parent_id, c.display_order ASC";

$result = mysqli_query($connect, $query);

if(!$result) {
    header('HTTP/1.1 500 Internal Server Error');
    die('Error fetching categories: ' . mysqli_error($connect));
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="categories_export_' . date('Y-m-d_H-i-s') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write instructions as comment rows
fputcsv($output, ['# IMPORTANT: DO NOT CHANGE THE ID COLUMN']);
fputcsv($output, ['# If you keep the ID, the category will be UPDATED']);
fputcsv($output, ['# If you remove the ID row, a NEW category will be created']);
fputcsv($output, ['# Parent Category names must match existing categories (case-sensitive)']);
fputcsv($output, ['# Type options: category, product, service, business']);
fputcsv($output, ['']);

// Write header row
fputcsv($output, [
    'ID',
    'Category Name',
    'Type',
    'Parent Category',
    'Description',
    'Icon Class',
    'Display Order'
]);

// Write data rows
while($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['id'],
        $row['category_name'],
        $row['category_type'],
        $row['parent_name'],
        $row['description'],
        $row['icon_class'],
        $row['display_order']
    ]);
}

fclose($output);
exit;
?>
