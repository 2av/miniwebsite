<?php
session_start();
header('Content-Type: application/json');

require_once(__DIR__ . '/../../app/config/database.php');

// Check database connection
if (!$connect) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error.']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

$user_email = mysqli_real_escape_string($connect, $_SESSION['user_email']);
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Get user ID from user_details table
$user_query = mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = LOWER(TRIM('$user_email')) LIMIT 1");
if (!$user_query) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query error: ' . mysqli_error($connect)]);
    exit;
}

if (mysqli_num_rows($user_query) == 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User email not found in system. Please contact support.']);
    exit;
}
$user_row = mysqli_fetch_assoc($user_query);
$user_id = intval($user_row['id']);

if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit;
}

// Handle different actions
if ($action === 'create') {
    // Create a new custom category
    $category_name = isset($_POST['category_name']) ? trim($_POST['category_name']) : '';
    $category_type = isset($_POST['category_type']) ? $_POST['category_type'] : 'product-name';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // Validation
    if (empty($category_name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required.']);
        exit;
    }
    
    if (strlen($category_name) > 255) {
        echo json_encode(['success' => false, 'message' => 'Category name must not exceed 255 characters.']);
        exit;
    }
    
    // Allowed category types
    $allowed_types = ['business-category', 'product-category', 'product-name'];
    if (!in_array($category_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid category type: ' . $category_type]);
        exit;
    }
    
    // Escape strings for database
    $category_name_escaped = mysqli_real_escape_string($connect, $category_name);
    $category_type_escaped = mysqli_real_escape_string($connect, $category_type);
    $description_escaped = mysqli_real_escape_string($connect, $description);
    
    // Check for duplicate
    $check_query = mysqli_query($connect, "
        SELECT id FROM user_custom_categories 
        WHERE user_id = $user_id 
        AND LOWER(TRIM(category_name)) = LOWER(TRIM('$category_name_escaped')) 
        AND category_type = '$category_type_escaped'
        LIMIT 1
    ");
    
    if (!$check_query) {
        echo json_encode(['success' => false, 'message' => 'Database error checking duplicate: ' . mysqli_error($connect)]);
        exit;
    }
    
    if (mysqli_num_rows($check_query) > 0) {
        echo json_encode(['success' => false, 'message' => 'This category already exists for you.']);
        exit;
    }
    
    // Insert new custom category
    $insert_query = "
        INSERT INTO user_custom_categories (user_id, user_email, category_name, category_type, description)
        VALUES ($user_id, '$user_email', '$category_name_escaped', '$category_type_escaped', '$description_escaped')
    ";
    
    if (mysqli_query($connect, $insert_query)) {
        $category_id = mysqli_insert_id($connect);
        echo json_encode([
            'success' => true,
            'message' => 'Category created successfully!',
            'category_id' => $category_id,
            'category_name' => $category_name
        ]);
    } else {
        // Log the error but provide helpful message
        $error_msg = mysqli_error($connect);
        error_log("Custom Category Insert Error: " . $error_msg . " | Query: " . $insert_query);
        echo json_encode([
            'success' => false, 
            'message' => 'Error creating category. Please try again or contact support.',
            'debug' => $error_msg
        ]);
    }
    exit;
}

if ($action === 'get_custom') {
    // Get custom categories for the user of specific type
    $category_type = isset($_GET['type']) ? $_GET['type'] : 'product-name';
    
    $allowed_types = ['business-category', 'product-category', 'product-name'];
    if (!in_array($category_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid category type.']);
        exit;
    }
    
    $query = "
        SELECT id, category_name 
        FROM user_custom_categories 
        WHERE user_id = $user_id 
        AND category_type = '$category_type'
        AND is_active = 1
        ORDER BY created_at DESC
    ";
    
    $result = mysqli_query($connect, $query);
    $categories = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = [
            'id' => intval($row['id']),
            'name' => htmlspecialchars($row['category_name']),
            'is_custom' => true
        ];
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
    exit;
}

if ($action === 'delete') {
    // Delete a custom category
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    
    if ($category_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid category ID.']);
        exit;
    }
    
    // Verify ownership
    $verify_query = mysqli_query($connect, "
        SELECT id FROM user_custom_categories 
        WHERE id = $category_id AND user_id = $user_id
        LIMIT 1
    ");
    
    if (mysqli_num_rows($verify_query) == 0) {
        echo json_encode(['success' => false, 'message' => 'Category not found or access denied.']);
        exit;
    }
    
    // Soft delete (set is_active to 0)
    $delete_query = "UPDATE user_custom_categories SET is_active = 0 WHERE id = $category_id AND user_id = $user_id";
    
    if (mysqli_query($connect, $delete_query)) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting category.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
?>
