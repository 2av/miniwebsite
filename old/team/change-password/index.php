<?php
// Handle password change form submission FIRST (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    // Ensure clean output for JSON response
    ini_set('display_errors', 0);
    ob_clean();
    ob_start();
    
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Include database connection
        require_once('../../common/config.php');
        
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $user_email = $_SESSION['user_email'] ?? '';
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception('All fields are required');
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception('New password and confirm password do not match');
        }
        
        if (strlen($new_password) < 6) {
            throw new Exception('New password must be at least 6 characters long');
        }
        
        // Check database connection
        if (!$connect) {
            throw new Exception('Database connection failed');
        }
        
        // Verify current password
        $check_query = mysqli_prepare($connect, "SELECT user_password FROM customer_login WHERE user_email = ?");
        if (!$check_query) {
            throw new Exception('Database query preparation failed');
        }
        
        mysqli_stmt_bind_param($check_query, "s", $user_email);
        mysqli_stmt_execute($check_query);
        $result = mysqli_stmt_get_result($check_query);
        
        if (mysqli_num_rows($result) === 0) {
            mysqli_stmt_close($check_query);
            throw new Exception('User not found');
        }
        
        $user_data = mysqli_fetch_array($result);
        mysqli_stmt_close($check_query);
        
        // Verify current password
        if (!password_verify($current_password, $user_data['user_password'])) {
            throw new Exception('Current password is incorrect');
        }
        
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $update_query = mysqli_prepare($connect, "UPDATE customer_login SET user_password = ? WHERE user_email = ?");
        if (!$update_query) {
            throw new Exception('Database update query preparation failed');
        }
        
        mysqli_stmt_bind_param($update_query, "ss", $hashed_password, $user_email);
        
        if (mysqli_stmt_execute($update_query)) {
            mysqli_stmt_close($update_query);
            $response = [
                'success' => true,
                'message' => 'Password changed successfully!'
            ];
        } else {
            mysqli_stmt_close($update_query);
            throw new Exception('Failed to update password: ' . mysqli_stmt_error($update_query));
        }
        
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    } catch (Error $e) {
        $response = [
            'success' => false,
            'message' => 'Fatal Error: ' . $e->getMessage()
        ];
    }
    
    // Clean output and send JSON
    ob_clean();
    echo json_encode($response);
    exit;
}

// Regular page load - include header and other files
include '../header.php';
?>

<main class="Dashboard">
    <div class="container-fluid px-4">
        <div class="main-top">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard/">Team Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Change Password</li>
                </ol>
            </nav>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="change-password-container">
                            <h3 class="text-center mb-4">Change Password</h3>
                            <p class="text-center text-muted mb-4">Update your account password for better security</p>
                            
                            <form id="changePasswordForm" method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="form-group mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                                <i class="fa fa-eye" id="current_password_icon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                                <i class="fa fa-eye" id="new_password_icon"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">Password must be at least 6 characters long</small>
                                </div>
                                
                                <div class="form-group mb-4">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                <i class="fa fa-eye" id="confirm_password_icon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group text-center">
                                    <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn">
                                        <i class="fa fa-key mr-2"></i>Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.change-password-container {
    background: #fff;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin: 2rem 0;
}

.form-label {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
}

.form-control {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #007bff, #0056b3);
    border: none;
    border-radius: 8px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0056b3, #004085);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

.btn-primary:disabled {
    background: #6c757d;
    transform: none;
    box-shadow: none;
}

.input-group-append .btn {
    border: 2px solid #e9ecef;
    border-left: none;
    background: #f8f9fa;
    color: #6c757d;
    transition: all 0.3s ease;
}

.input-group-append .btn:hover {
    background: #e9ecef;
    color: #495057;
}

.alert {
    border-radius: 8px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    color: #721c24;
}

.password-strength {
    margin-top: 0.5rem;
    font-size: 0.875rem;
}

.strength-weak { color: #dc3545; }
.strength-medium { color: #ffc107; }
.strength-strong { color: #28a745; }
</style>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Password strength indicator
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthIndicator = document.getElementById('strength-indicator');
    
    if (!strengthIndicator) {
        const indicator = document.createElement('div');
        indicator.id = 'strength-indicator';
        indicator.className = 'password-strength';
        this.parentNode.parentNode.appendChild(indicator);
    }
    
    const indicator = document.getElementById('strength-indicator');
    let strength = 0;
    let strengthText = '';
    let strengthClass = '';
    
    if (password.length >= 6) strength++;
    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    
    if (strength < 2) {
        strengthText = 'Weak';
        strengthClass = 'strength-weak';
    } else if (strength < 4) {
        strengthText = 'Medium';
        strengthClass = 'strength-medium';
    } else {
        strengthText = 'Strong';
        strengthClass = 'strength-strong';
    }
    
    indicator.textContent = 'Password strength: ' + strengthText;
    indicator.className = 'password-strength ' + strengthClass;
});

// Form submission
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i>Changing Password...';
    submitBtn.disabled = true;
    
    // Get form data
    const formData = new FormData(this);
    
    // Submit via AJAX
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            // If not JSON, get text to see what we got
            return response.text().then(text => {
                console.error('Non-JSON response:', text);
                throw new Error('Server returned non-JSON response. Check console for details.');
            });
        }
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            // Show success message
            showAlert('success', data.message);
            // Reset form
            this.reset();
            // Remove strength indicator
            const indicator = document.getElementById('strength-indicator');
            if (indicator) {
                indicator.remove();
            }
        } else {
            // Show error message
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while changing password: ' + error.message);
    })
    .finally(() => {
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Show alert message
function showAlert(type, message) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    `;
    
    // Insert at the top of the form
    const form = document.getElementById('changePasswordForm');
    form.parentNode.insertBefore(alert, form);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include '../footer.php'; ?>
