<?php
require_once(__DIR__ . '/../../app/config/database.php');
require_once(__DIR__ . '/../../app/includes/product_categories_helper.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_email']) || $_SESSION['user_email'] === '') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if ($action === 'business_categories') {
    $profile_type = isset($_GET['profile_type']) ? trim($_GET['profile_type']) : '';
    if ($profile_type === '') {
        echo json_encode(['success' => true, 'categories' => []]);
        exit;
    }
    echo json_encode([
        'success' => true,
        'categories' => getBusinessCategoriesByProfileType($connect, $profile_type),
    ]);
    exit;
}

if ($action === 'product_categories') {
    $primary_id = isset($_GET['primary_id']) ? (int) $_GET['primary_id'] : 0;
    $secondary_id = isset($_GET['secondary_id']) ? (int) $_GET['secondary_id'] : 0;
    $ids = array_values(array_filter([$primary_id, $secondary_id]));
    echo json_encode([
        'success' => true,
        'categories' => getProductCategoriesForBusinessIds($connect, $ids),
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
