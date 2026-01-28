<?php
require_once(__DIR__ . '/../app/config/database.php');
require_once(__DIR__ . '/../app/helpers/password_helper.php');

header('Content-Type: application/json; charset=utf-8');

try {
    // Admin auth - check if admin is logged in
    // Use same check as other admin pages (just admin_email)
    if (empty($_SESSION['admin_email'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in as admin']);
        exit;
    }

    $email = strtolower(trim($_POST['email'] ?? ''));
    $role  = strtoupper(trim($_POST['role'] ?? 'FRANCHISEE'));
    $new   = (string)($_POST['new_password'] ?? '');
    $conf  = (string)($_POST['confirm_password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Valid email is required.');
    }
    if (!in_array($role, ['FRANCHISEE','CUSTOMER','TEAM','ADMIN'], true)) {
        throw new Exception('Invalid role.');
    }
    if (strlen($new) < 6) {
        throw new Exception('Password must be at least 6 characters long.');
    }
    if ($new !== $conf) {
        throw new Exception('New password and confirm password do not match.');
    }

    $hash = mw_hash_password($new);

    $stmt = $connect->prepare('UPDATE user_details SET password = ?, password_hash = ?, updated_at = NOW() WHERE email = ? AND role = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Failed to prepare update: ' . $connect->error);
    }
    $stmt->bind_param('ssss', $hash, $hash, $email, $role);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected <= 0) {
        throw new Exception('User not found in user_details for this role.');
    }

    echo json_encode(['success' => true, 'message' => 'Password updated successfully for ' . $email]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

