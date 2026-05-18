<?php
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/includes/admin_category_directory_helper.php');

if(!isset($_SESSION['admin_email']) || empty($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="category_template.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
fputcsv($output, adminCategoryCsvHeaders());

$sample = [
    ['Retail', 'Retail', 'Kirana Store', 'kirana-store', 'Groceries', 'groceries', '10', '1', 'grocery kirana', 'retail grocery'],
    ['Retail', 'Retail', 'Kirana Store', 'kirana-store', 'Daily Essentials', 'daily-essentials', '20', '1', 'daily needs', 'retail essentials'],
    ['Retail', 'Retail', 'Supermarket', 'supermarket', 'New Arrivals', 'new-arrivals', '10', '1', 'new products', 'retail supermarket'],
];
foreach($sample as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
