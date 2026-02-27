<?php
/**
 * Custom Categories Helper Functions
 * Provides utilities for displaying user custom categories
 */

/**
 * Get user ID from email
 * @param mysqli $connect Database connection
 * @param string $user_email User's email
 * @return int User ID or 0 if not found
 */
function getUserIdByEmail($connect, $user_email) {
    $user_email_escaped = mysqli_real_escape_string($connect, $user_email);
    $query = mysqli_query($connect, "SELECT id FROM user_details WHERE LOWER(TRIM(email)) = LOWER(TRIM('$user_email_escaped')) LIMIT 1");
    
    if ($query && mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);
        return intval($row['id']);
    }
    return 0;
}

/**
 * Get user's custom categories of a specific type
 * @param mysqli $connect Database connection
 * @param int $user_id User ID
 * @param string $category_type Type of category (business-category, product-category, product-name)
 * @return array Array of custom categories
 */
function getUserCustomCategories($connect, $user_id, $category_type) {
    $category_type_escaped = mysqli_real_escape_string($connect, $category_type);
    $query = mysqli_query($connect, "
        SELECT id, category_name 
        FROM user_custom_categories 
        WHERE user_id = $user_id 
        AND category_type = '$category_type_escaped'
        AND is_active = 1
        ORDER BY created_at DESC
    ");
    
    $categories = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $categories[] = $row;
    }
    return $categories;
}

/**
 * Display custom categories as option elements
 * @param mysqli $connect Database connection
 * @param int $user_id User ID
 * @param string $category_type Type of category
 * @param bool $mark_as_custom Add a prefix to indicate custom category
 */
function displayCustomCategoryOptions($connect, $user_id, $category_type, $mark_as_custom = true) {
    $categories = getUserCustomCategories($connect, $user_id, $category_type);
    
    if (count($categories) > 0) {
        if ($mark_as_custom) {
            echo '<optgroup label="My Custom ' . ucfirst(str_replace('-', ' ', $category_type)) . '">';
        }
        
        foreach ($categories as $cat) {
            echo '<option value="' . intval($cat['id']) . '" data-custom="true">[Custom] ' . htmlspecialchars($cat['category_name']) . '</option>';
        }
        
        if ($mark_as_custom) {
            echo '</optgroup>';
        }
    }
}

/**
 * Check if a category exists and belongs to user
 * @param mysqli $connect Database connection
 * @param int $user_id User ID
 * @param int $category_id Category ID
 * @return bool True if exists and belongs to user
 */
function userOwnsCustomCategory($connect, $user_id, $category_id) {
    $query = mysqli_query($connect, "
        SELECT id FROM user_custom_categories 
        WHERE id = $category_id 
        AND user_id = $user_id
        LIMIT 1
    ");
    
    return mysqli_num_rows($query) > 0;
}

/**
 * Get category details
 * @param mysqli $connect Database connection
 * @param int $category_id Category ID
 * @return array Category details or empty array
 */
function getCustomCategoryDetails($connect, $category_id) {
    $query = mysqli_query($connect, "
        SELECT * FROM user_custom_categories 
        WHERE id = $category_id
        LIMIT 1
    ");
    
    if ($query && mysqli_num_rows($query) > 0) {
        return mysqli_fetch_assoc($query);
    }
    return [];
}

/**
 * Create a custom category
 * @param mysqli $connect Database connection
 * @param int $user_id User ID
 * @param string $user_email User email
 * @param string $category_name Category name
 * @param string $category_type Category type
 * @param string $description Optional description
 * @return array Result with 'success' and optional 'id' or 'error'
 */
function createCustomCategory($connect, $user_id, $user_email, $category_name, $category_type, $description = '') {
    // Validate inputs
    if (empty($category_name)) {
        return ['success' => false, 'error' => 'Category name is required'];
    }
    
    if (strlen($category_name) > 255) {
        return ['success' => false, 'error' => 'Category name must not exceed 255 characters'];
    }
    
    $allowed_types = ['business-category', 'product-category', 'product-name'];
    if (!in_array($category_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid category type'];
    }
    
    // Escape strings
    $category_name_escaped = mysqli_real_escape_string($connect, $category_name);
    $category_type_escaped = mysqli_real_escape_string($connect, $category_type);
    $description_escaped = mysqli_real_escape_string($connect, $description);
    $user_email_escaped = mysqli_real_escape_string($connect, $user_email);
    
    // Check for duplicates
    $check_query = mysqli_query($connect, "
        SELECT id FROM user_custom_categories 
        WHERE user_id = $user_id 
        AND LOWER(TRIM(category_name)) = LOWER(TRIM('$category_name_escaped'))
        AND category_type = '$category_type_escaped'
        LIMIT 1
    ");
    
    if (mysqli_num_rows($check_query) > 0) {
        return ['success' => false, 'error' => 'This category already exists'];
    }
    
    // Insert
    $insert_query = "
        INSERT INTO user_custom_categories 
        (user_id, user_email, category_name, category_type, description)
        VALUES 
        ($user_id, '$user_email_escaped', '$category_name_escaped', '$category_type_escaped', '$description_escaped')
    ";
    
    if (mysqli_query($connect, $insert_query)) {
        return ['success' => true, 'id' => mysqli_insert_id($connect)];
    } else {
        return ['success' => false, 'error' => mysqli_error($connect)];
    }
}

?>
