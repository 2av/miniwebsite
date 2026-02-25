<?php
require_once(__DIR__ . '/../app/config/database.php');

// Check if admin is logged in
if(!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    echo '<script>alert("Please login first!"); window.location.href="login.php";</script>';
    exit;
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="category_template_hierarchical.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

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

// Write sample hierarchical data rows (with empty IDs for new categories)
$sample_data = [
    ['', 'Electronics', 'category', '', 'Electronic gadgets and devices', 'fa-laptop', '1'],
    ['', 'Laptops', 'product', 'Electronics', 'Laptop computers', 'fa-laptop', '1'],
    ['', 'Phones', 'product', 'Electronics', 'Mobile phones', 'fa-mobile', '2'],
    ['', 'Tablets', 'product', 'Electronics', 'Tablet devices', 'fa-tablet-alt', '3'],
    ['', 'Clothing', 'category', '', 'Apparel and fashion items', 'fa-tshirt', '2'],
    ['', 'Men', 'product', 'Clothing', 'Men clothing', 'fa-male', '1'],
    ['', 'Women', 'product', 'Clothing', 'Women clothing', 'fa-female', '2'],
    ['', 'Kids', 'product', 'Clothing', 'Children clothing', 'fa-child', '3'],
    ['', 'Books', 'category', '', 'Books and educational materials', 'fa-book', '3'],
    ['', 'Fiction', 'product', 'Books', 'Fiction novels', 'fa-book', '1'],
    ['', 'Non-Fiction', 'product', 'Books', 'Non-fiction books', 'fa-book-open', '2'],
    ['', 'Educational', 'product', 'Books', 'Educational materials', 'fa-graduation-cap', '3'],
];

foreach($sample_data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>
