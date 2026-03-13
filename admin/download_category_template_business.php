<?php
require_once(__DIR__ . '/../app/config/database.php');

// Check if admin is logged in
if(!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="category_template_business_hierarchical.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header row - matches user's CSV structure
fputcsv($output, [
    'Business Heading',
    'Business Category',
    'Business Category Slug',
    'Product Category',
    'Product Category Slug',
    'Product Name',
    'Product Slug'
]);

// Write sample data matching the user's format
$sample_data = [
    ['Retail', 'Kirana Store', 'kirana-store', 'New Arrivals', 'new-arrivals', 'Latest Collection', 'latest-collection'],
    ['Retail', 'Kirana Store', 'kirana-store', 'New Arrivals', 'new-arrivals', 'Trending Products', 'trending-products'],
    ['Retail', 'Kirana Store', 'kirana-store', 'New Arrivals', 'new-arrivals', 'Seasonal Products', 'seasonal-products'],
];

foreach($sample_data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
