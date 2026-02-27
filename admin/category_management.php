<?php
// Redirect to the main category list page
// The three separate pages are:
// 1. category_add.php - Add/Edit categories
// 2. category_bulk_import.php - Bulk import from CSV
// 3. category_list.php - View and manage all categories
header('Location: category_list.php');
exit;
?>
