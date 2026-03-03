<?php
/**
 * User Helper Functions
 * 
 * Provides utilities for working with users, including soft-delete filtering
 */

/**
 * Get WHERE clause to exclude deleted users
 * Use in queries when you want to filter out deleted users
 * 
 * Example:
 *   $sql = "SELECT * FROM user_details WHERE " . userWhereClause();
 *   $sql = "SELECT * FROM user_details ud JOIN ... WHERE " . userWhereClause('ud') . " AND ...";
 * 
 * @param string $tableAlias Optional table alias (e.g., 'ud' for 'user_details ud')
 * @return string WHERE clause fragment
 */
function userWhereClause($tableAlias = 'user_details') {
    return "$tableAlias.isDeleted = 0";
}

/**
 * Check if a user is deleted
 * 
 * @param int $userId
 * @param mysqli $connection
 * @return bool
 */
function isUserDeleted($userId, $connection) {
    $userId = intval($userId);
    $result = mysqli_query($connection, "SELECT isDeleted FROM user_details WHERE id = $userId");
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['isDeleted'] == 1;
    }
    
    return false;
}

/**
 * Get active (non-deleted) user by ID
 * 
 * @param int $userId
 * @param mysqli $connection
 * @return array|null User data or null if not found/deleted
 */
function getActiveUser($userId, $connection) {
    $userId = intval($userId);
    $result = mysqli_query($connection, "SELECT * FROM user_details WHERE id = $userId AND isDeleted = 0");
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

/**
 * Get active (non-deleted) user by email
 * 
 * @param string $email
 * @param mysqli $connection
 * @return array|null User data or null if not found/deleted
 */
function getActiveUserByEmail($email, $connection) {
    $email = $connection->real_escape_string($email);
    $result = mysqli_query($connection, "SELECT * FROM user_details WHERE email = '$email' AND isDeleted = 0");
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

/**
 * Soft delete a user (mark as deleted)
 * 
 * @param int $userId
 * @param mysqli $connection
 * @return bool Success
 */
function softDeleteUser($userId, $connection) {
    $userId = intval($userId);
    return mysqli_query($connection, "UPDATE user_details SET isDeleted = 1 WHERE id = $userId");
}

/**
 * Restore a deleted user
 * 
 * @param int $userId
 * @param mysqli $connection
 * @return bool Success
 */
function restoreUser($userId, $connection) {
    $userId = intval($userId);
    return mysqli_query($connection, "UPDATE user_details SET isDeleted = 0 WHERE id = $userId");
}

/**
 * Hard delete a user and all related data
 * 
 * @param int $userId
 * @param mysqli $connection
 * @return bool Success
 */
function hardDeleteUser($userId, $connection) {
    $userId = intval($userId);
    
    mysqli_begin_transaction($connection);
    
    try {
        // Delete related records from various tables
        $relatedTables = [
            'digi_card' => 'user_id',
            'team_members' => 'franchisee_id',
            'payment_history' => 'user_id',
            'orders' => 'user_id'
        ];
        
        foreach ($relatedTables as $table => $idColumn) {
            // Check if table exists
            $checkTable = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'";
            if (mysqli_query($connection, $checkTable)) {
                @mysqli_query($connection, "DELETE FROM $table WHERE $idColumn = $userId");
            }
        }
        
        // Delete the user
        $deleteQuery = "DELETE FROM user_details WHERE id = $userId";
        if (!mysqli_query($connection, $deleteQuery)) {
            throw new Exception('Failed to delete user');
        }
        
        mysqli_commit($connection);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($connection);
        error_log('Hard delete user error: ' . $e->getMessage());
        return false;
    }
}

?>
