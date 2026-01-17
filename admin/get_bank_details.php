<?php
require_once(__DIR__ . '/../app/config/database.php');

if(isset($_GET['referrer_email'])) {
    $referrer_email = mysqli_real_escape_string($connect, $_GET['referrer_email']);
    
    // Get referrer info
    $referrer_query = mysqli_query($connect, "SELECT * FROM user_bank_details WHERE user_email='$referrer_email'");
    
    echo '<div class="bank-details-header">';
    echo '<h4><i class="fas fa-university"></i> Bank Details for: ' . htmlspecialchars($referrer_email) . '</h4>';
    echo '<button id="editBtn" onclick="toggleEditMode(\'' . $referrer_email . '\')" class="btn-edit">';
    echo '<i class="fas fa-edit"></i> Edit';
    echo '</button>';
    echo '</div>';
    
    if(mysqli_num_rows($referrer_query) > 0) {
        echo '<form id="bankDetailsForm" method="POST" onsubmit="return false;">';
        echo '<input type="hidden" name="user_email" value="' . $referrer_email . '">';
        echo '<input type="hidden" name="update_bank_details" value="1">';
        
        echo '<div class="bank-details-grid">';
        
        while($row = mysqli_fetch_array($referrer_query)) {
            echo '<div class="bank-field">';
            echo '<label><i class="fas fa-user"></i> Account Holder Name</label>';
            echo '<span class="display-text">'.htmlspecialchars($row['account_holder_name']).'</span>';
            echo '<input type="text" name="account_holder_name" value="'.htmlspecialchars($row['account_holder_name']).'" class="edit-input" style="display: none;" required>';
            echo '</div>';
            
            echo '<div class="bank-field">';
            echo '<label><i class="fas fa-credit-card"></i> Account Number</label>';
            echo '<span class="display-text">'.htmlspecialchars($row['account_number']).'</span>';
            echo '<input type="text" name="account_number" value="'.htmlspecialchars($row['account_number']).'" class="edit-input" style="display: none;" required>';
            echo '</div>';
            
            echo '<div class="bank-field">';
            echo '<label><i class="fas fa-code"></i> IFSC Code</label>';
            echo '<span class="display-text">'.htmlspecialchars($row['ifsc_code']).'</span>';
            echo '<input type="text" name="ifsc_code" value="'.htmlspecialchars($row['ifsc_code']).'" class="edit-input" style="display: none;" required>';
            echo '</div>';
            
            echo '<div class="bank-field">';
            echo '<label><i class="fas fa-building"></i> Bank Name</label>';
            echo '<span class="display-text">'.htmlspecialchars($row['bank_name']).'</span>';
            echo '<input type="text" name="bank_name" value="'.htmlspecialchars($row['bank_name']).'" class="edit-input" style="display: none;" required>';
            echo '</div>';
            
            echo '<div class="bank-field">';
            echo '<label><i class="fas fa-mobile-alt"></i> UPI ID</label>';
            echo '<span class="display-text">'.htmlspecialchars($row['upi_id']).'</span>';
            echo '<input type="text" name="upi_id" value="'.htmlspecialchars($row['upi_id']).'" class="edit-input" style="display: none;">';
            echo '</div>';
            
            echo '<div class="bank-field">';
            echo '<label><i class="fas fa-user-tag"></i> UPI Name</label>';
            echo '<span class="display-text">'.htmlspecialchars($row['upi_name']).'</span>';
            echo '<input type="text" name="upi_name" value="'.htmlspecialchars($row['upi_name']).'" class="edit-input" style="display: none;">';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</form>';
    } else {
        echo '<div class="no-bank-details">';
        echo '<i class="fas fa-exclamation-circle"></i>';
        echo '<p>No bank details found for this user.</p>';
        echo '<p>Click "Edit" to add bank details.</p>';
        echo '</div>';
        
        // Show empty form for new bank details
        echo '<form id="bankDetailsForm" method="POST" onsubmit="return false;" style="display: none;">';
        echo '<input type="hidden" name="user_email" value="' . $referrer_email . '">';
        echo '<input type="hidden" name="update_bank_details" value="1">';
        
        echo '<div class="bank-details-grid">';
        echo '<div class="bank-field">';
        echo '<label><i class="fas fa-user"></i> Account Holder Name</label>';
        echo '<span class="display-text"></span>';
        echo '<input type="text" name="account_holder_name" value="" class="edit-input" required>';
        echo '</div>';
        
        echo '<div class="bank-field">';
        echo '<label><i class="fas fa-credit-card"></i> Account Number</label>';
        echo '<span class="display-text"></span>';
        echo '<input type="text" name="account_number" value="" class="edit-input" required>';
        echo '</div>';
        
        echo '<div class="bank-field">';
        echo '<label><i class="fas fa-code"></i> IFSC Code</label>';
        echo '<span class="display-text"></span>';
        echo '<input type="text" name="ifsc_code" value="" class="edit-input" required>';
        echo '</div>';
        
        echo '<div class="bank-field">';
        echo '<label><i class="fas fa-building"></i> Bank Name</label>';
        echo '<span class="display-text"></span>';
        echo '<input type="text" name="bank_name" value="" class="edit-input" required>';
        echo '</div>';
        
        echo '<div class="bank-field">';
        echo '<label><i class="fas fa-mobile-alt"></i> UPI ID</label>';
        echo '<span class="display-text"></span>';
        echo '<input type="text" name="upi_id" value="" class="edit-input">';
        echo '</div>';
        
        echo '<div class="bank-field">';
        echo '<label><i class="fas fa-user-tag"></i> UPI Name</label>';
        echo '<span class="display-text"></span>';
        echo '<input type="text" name="upi_name" value="" class="edit-input">';
        echo '</div>';
        
        echo '</div>';
        echo '</form>';
    }
}
?>

<style>
.bank-details-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.bank-details-header h4 {
    margin: 0;
    color: #495057;
    font-size: 18px;
}

.bank-details-header h4 i {
    color: #007bff;
    margin-right: 8px;
}

.btn-edit {
    background: #ff6b35;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-edit:hover {
    background: #e55a2b;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(255, 107, 53, 0.3);
}

.bank-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.bank-field {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.bank-field:hover {
    border-color: #007bff;
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.1);
}

.bank-field label {
    display: block;
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    font-size: 14px;
}

.bank-field label i {
    color: #007bff;
    margin-right: 6px;
    width: 16px;
}

.display-text {
    font-size: 16px;
    color: #212529;
    font-weight: 500;
    padding: 8px 0;
    display: block;
    min-height: 20px;
}

.edit-input {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #007bff;
    border-radius: 6px;
    font-size: 16px;
    background: white;
    transition: all 0.3s ease;
}

.edit-input:focus {
    outline: none;
    border-color: #0056b3;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.no-bank-details {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.no-bank-details i {
    font-size: 48px;
    color: #ffc107;
    margin-bottom: 15px;
}

.no-bank-details p {
    margin: 10px 0;
    font-size: 16px;
}

/* Responsive design */
@media (max-width: 768px) {
    .bank-details-grid {
        grid-template-columns: 1fr;
    }
    
    .bank-details-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}
</style>





