<?php

require('connect.php');
require('header.php');
?>
<link rel="stylesheet" href="assets/css/common-admin.css">
<?php

// Include PHPMailer and email configuration with robust fallbacks
$hasMailer = false;
$autoloadCandidates = [
    __DIR__ . '/../panel/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../panel/login/vendor/autoload.php'
];
foreach ($autoloadCandidates as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $hasMailer = true;
        break;
    }
}
require_once __DIR__ . '/../common/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to send email using PHPMailer (fallback to mail() if unavailable)
function sendEmail($to, $subject, $message, $name = '') {
    global $hasMailer;
    if ($hasMailer) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = SMTP_AUTH;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
            $mail->setFrom(SMTP_USERNAME, 'MiniWebsite Support');
            $mail->addAddress($to, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));
            return $mail->send();
        } catch (Exception $e) {
            error_log('Email sending failed via PHPMailer: ' . $e->getMessage());
            // fall through to basic mail()
        }
    }

    // Fallback: basic PHP mail()
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: MiniWebsite Support <' . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'miniwebsite.in')) . ">\r\n";
    return @mail($to, $subject, $message, $headers);
}

?>
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- jQuery (if not already included) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>


<div class="main3">
	<a href="index.php"><h3 class="back_btn"><i class="fa fa-arrow-circle-left"></i> back </h3></a>
	<h1 class="close_form">Add Franchisee</h1>
	
	<form action="" method="POST" class="close_form" enctype="multipart/form-data" id="franchiseeForm">
		
		<h3></h3>
		<div class="input_box"><p>Login Email (Email ID) *</p><input type="email" name="f_user_email" maxlength="199" placeholder="Enter Login Email for franchisee login" required></div>
		<div class="input_box"><p>Login Mobile (10 digits) *</p><input type="tel" name="f_user_contact" maxlength="10" minlength="10" pattern="[0-9]{10}" placeholder="Enter 10 digit Mobile Number" required></div>
		<div class="input_box"><p>Login password *</p><input type="text" name="f_user_password" maxlength="199" placeholder="Enter Password" required></div>
			
		<input type="submit" class="" name="process1" value="Create ID & Password" id="block_loader">
	
	
	</form>

	<script>
	document.getElementById('franchiseeForm').addEventListener('submit', function(e) {
		var email = document.querySelector('input[name="f_user_email"]').value;
		var contact = document.querySelector('input[name="f_user_contact"]').value;
		var password = document.querySelector('input[name="f_user_password"]').value;
		
		var errors = [];
		
		// Validate email
		if(!email.trim()) {
			errors.push('Email is required');
		} else if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
			errors.push('Please enter a valid email address');
		}
		
		// Validate contact number
		if(!contact.trim()) {
			errors.push('Contact number is required');
		} else if(!/^[0-9]{10}$/.test(contact)) {
			errors.push('Contact number must be exactly 10 digits');
		}
		
		// Validate password
		if(!password.trim()) {
			errors.push('Password is required');
		}
		
		// If there are errors, prevent form submission
		if(errors.length > 0) {
			e.preventDefault();
			alert('Validation Errors:\\n• ' + errors.join('\\n• '));
			return false;
		}
	});
	
	// Real-time validation for contact number
	document.querySelector('input[name="f_user_contact"]').addEventListener('input', function(e) {
		// Remove any non-numeric characters
		this.value = this.value.replace(/[^0-9]/g, '');
		
		// Limit to 10 digits
		if(this.value.length > 10) {
			this.value = this.value.substring(0, 10);
		}
	});
	</script>
	




<?php
if(isset($_POST['process1'])){
	
	// Server-side validation
	$errors = array();
	
	// Validate email
	if(empty($_POST['f_user_email'])) {
		$errors[] = "Email is required";
	} elseif(!filter_var($_POST['f_user_email'], FILTER_VALIDATE_EMAIL)) {
		$errors[] = "Please enter a valid email address";
	}
	
	// Validate contact number
	if(empty($_POST['f_user_contact'])) {
		$errors[] = "Contact number is required";
	} elseif(!preg_match('/^[0-9]{10}$/', $_POST['f_user_contact'])) {
		$errors[] = "Contact number must be exactly 10 digits";
	}
	
	// Validate password
	if(empty($_POST['f_user_password'])) {
		$errors[] = "Password is required";
	}
	
	// If there are validation errors, display them
	if(!empty($errors)) {
		echo '<div class="alert danger">';
		echo '<strong>Validation Errors:</strong><br>';
		foreach($errors as $error) {
			echo '• ' . $error . '<br>';
		}
		echo '</div>';
	} else {
		// Proceed with database operations if validation passes
		// Check in user_details table with role='FRANCHISEE'
		$f_user_email = mysqli_real_escape_string($connect, $_POST['f_user_email']);
		$f_user_contact = mysqli_real_escape_string($connect, $_POST['f_user_contact']);
		$query=mysqli_query($connect,'SELECT * FROM user_details WHERE email="'.$f_user_email.'" AND phone="'.$f_user_contact.'" AND role="FRANCHISEE"');
		if(mysqli_num_rows($query)>0){
			
			
			echo '<div class="alert info">Account already available.</div>';
			$row=mysqli_fetch_array($query);
			
					
				
				

		}else{

		
				// Insert into user_details table with role='FRANCHISEE'
				$f_user_email_esc = mysqli_real_escape_string($connect, $_POST['f_user_email']);
				$f_user_password_esc = mysqli_real_escape_string($connect, $_POST['f_user_password']);
				$f_user_contact_esc = mysqli_real_escape_string($connect, $_POST['f_user_contact']);
				// Use email as name if name is not provided
				$f_user_name_esc = mysqli_real_escape_string($connect, $_POST['f_user_email']);
				$insert=mysqli_query($connect,'INSERT INTO user_details (role, email, phone, name, password, status) VALUES ("FRANCHISEE", "'.$f_user_email_esc.'", "'.$f_user_contact_esc.'", "'.$f_user_name_esc.'", "'.$f_user_password_esc.'", "ACTIVE")');
				if($insert){
					// Send welcome email to the newly created franchisee
					$user_email = $_POST['f_user_email'];
					$user_name = $_POST['f_user_email']; // Using email as name since name is not collected in this form
					
					$subject = "Welcome to MiniWebsite.in – Your Franchisee Account is Ready!";
					$message = '
					<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
						<p style="color: #333; font-size: 16px; line-height: 1.6;">Hi <strong>' . htmlspecialchars($user_name) . '</strong>,</p>
						
						<p style="color: #333; font-size: 16px; line-height: 1.6;">Thank you for registering as a franchisee with MiniWebsite.in.</p>
						
						<p style="color: #333; font-size: 16px; line-height: 1.6;">We are excited to have you on board! Your franchisee account has been successfully created. You can now log in using your email and password at the link below:</p>
						
						<p style="color: #333; font-size: 16px; line-height: 1.6;">👉 <a href="https://' . $_SERVER['HTTP_HOST'] . '/panel/franchisee-login/login.php" style="color: #007bff; text-decoration: none;">(Franchisee Login details)</a></p>
						
						<br><br>
						
						<p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>Follow these simple steps to activate your franchise:</strong></p>
                
						<p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>1. Pay the One-Time Franchise Fee (Non-Refundable)</strong><br>
						Amount: ₹5,100 + 18% GST = ₹6,018<br>
						<a href="https://' . $_SERVER['HTTP_HOST'] . '/franchise_agreement.php?email=' . urlencode($user_email) . '" style="color: #007bff; text-decoration: none;">(Click to Pay)</a></p>
						
						<p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>2. After payment, complete your document Verification from your Dashboard.</strong></p>
						
						<p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>3. After the documents get verified, you can access your Franchise Kit and Onboarding Material from your dashboard only.</strong></p>
						
						<br>
						
						<p style="color: #333; font-size: 16px; line-height: 1.6;">That\'s it! Once these steps are completed, you are officially part of the MiniWebsite.in franchise network. You can begin building your business and start earning right away.</p>
						
						<p style="color: #333; font-size: 16px; line-height: 1.6;">If you have any questions or need assistance, feel free to reach out to our support team.</p>
						
						<br>
						
						<p style="color: #333; font-size: 16px; line-height: 1.6;">Best regards,<br>
						Team MiniWebsite.in<br>
						www.miniwebsite.in</p>
					</div>';

					$email_sent = sendEmail($user_email, $subject, $message, $user_name);
					
					if($email_sent) {
						echo '<div class="alert info">Account Created! Welcome email sent successfully.</div>';
					} else {
						echo '<div class="alert info">Account Created! (Email sending failed)</div>';
					}
					
					
				}
				
	} // End of validation else block
}

}
?>

</div>
<div class="table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-table me-2"></i>
            Franchisee Management
        </div>
    </div>
    <div class="table-responsive table-container">
        <table class="table table-striped table-hover modern-table" style="text-align: center;">
            <thead class="bg-secondary">
                <tr>
                    <th>FR ID</th>
                    <th>User Email</th>
                    <th>User Name</th>
                    <th>User Number</th>
                    <th>Joined On</th>
                    <th>Referral Source</th>
                    <th>Company Name</th>
                    <th>FR Status</th>
                    <th>View/Edit/Share</th>
                    <th>User Payment Status</th>
                    <th>Franchise Fee</th>
                    <th>Invoice</th>
                    <th>Total MW Created</th>
                    <th>Document Status</th>
                    <th>Wallet Balance</th>
                    <th>View Dashborad</th>
                    <th>Reset Password</th>
                </tr>
            </thead>
            <tbody>
            <?php
	
	

if(isset($_GET['page_no'])){
				
			}else {$_GET['page_no']='1';}

			
			 
			 $limit=30;
			 
			  $start_from=($_GET['page_no']-1)*$limit;
			 
	// Query user_details table for franchisees
	$query=mysqli_query($connect,'SELECT * FROM user_details WHERE role="FRANCHISEE" ORDER BY id DESC LIMIT '.$start_from.','.$limit.'');
	

		if(mysqli_num_rows($query)>0){
			
            while($row=mysqli_fetch_array($query)){
                // Map user_details fields to old field names for compatibility
                $f_user_email = $row['email'] ?? '';
                $f_user_name = $row['name'] ?? '';
                $f_user_contact = $row['phone'] ?? '';
                $f_user_active = ($row['status'] ?? 'INACTIVE') === 'ACTIVE' ? 'YES' : 'NO';
                $uploaded_date = $row['created_at'] ?? '';
                
                $cards_q = mysqli_query($connect,'SELECT * FROM digi_card WHERE f_user_email="'.$f_user_email.'" ORDER BY id DESC ');
                $website_count = $cards_q ? mysqli_num_rows($cards_q) : 0;
                $first_card = ($cards_q && $website_count>0) ? mysqli_fetch_array($cards_q) : null;
                echo '<tr>';
                echo '<td>'.($row['id'] ?? '-').'</td>';
                echo '<td>'.htmlspecialchars($f_user_email).'</td>';
                echo '<td>'.htmlspecialchars($f_user_name).'</td>';
                echo '<td>'.htmlspecialchars($f_user_contact).'</td>';
                $joined = !empty($uploaded_date) && $uploaded_date!='0000-00-00 00:00:00' ? date('d-m-Y', strtotime($uploaded_date)) : '-';
                echo '<td>'.$joined.'</td>';
                // Referral Source logic - check in user_details table
                $ref_source = 'Direct';
                if (isset($row['referred_by']) && $row['referred_by'] !== '') {
                    $ref_by = trim($row['referred_by']);
                    // Check if referred_by is a user ID (numeric) or email
                    if (is_numeric($ref_by)) {
                        $ref_source = 'User - ' . $ref_by;
                    } else {
                        // If it's an email, try to get the user ID from user_details
                        $ref_user_query = mysqli_query($connect, "SELECT id, role FROM user_details WHERE email='".mysqli_real_escape_string($connect, $ref_by)."' LIMIT 1");
                        if ($ref_user_query && mysqli_num_rows($ref_user_query) > 0) {
                            $ref_user = mysqli_fetch_array($ref_user_query);
                            $ref_id = intval($ref_user['id']);
                            $ref_role = strtoupper($ref_user['role'] ?? '');
                            
                            if ($ref_role === 'FRANCHISEE') {
                                $ref_source = 'FR - ' . str_pad($ref_id, 3, '0', STR_PAD_LEFT);
                            } elseif ($ref_role === 'TEAM') {
                                $ref_source = 'Team - ' . $ref_id;
                            } elseif ($ref_role === 'ADMIN') {
                                $ref_source = 'Admin - ' . $ref_id;
                            } else {
                                $ref_source = 'User - ' . $ref_id;
                            }
                        } else {
                            $ref_source = 'User - ' . $ref_by;
                        }
                    }
                }
                echo '<td>'.htmlspecialchars($ref_source).'</td>';
                $comp_name = '-';
                if ($first_card) {
                    $billing_name = !empty($first_card['billing_name']) ? $first_card['billing_name'] : ($first_card['d_comp_name'] ?? '');
                    $comp_name = $billing_name !== '' ? $billing_name : '-';
                }
                echo '<td>'.htmlspecialchars($comp_name).'</td>';
                $status_badge = $f_user_active==='YES' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
                echo '<td>'.$status_badge.'</td>';
                echo '<td>';
                if ($first_card) {
                    $cardUrl = 'https://'.$_SERVER['HTTP_HOST'].'/'.$first_card['id'];
                    echo '<a href="'.$cardUrl.'" target="_blank" title="View" class="me-2"><i class="fa fa-eye"></i></a>';
                    echo '<a href="select_theme.php?card_number='.$first_card['id'].'&user_email='.$first_card['user_email'].'" title="Edit" class="me-2"><i class="fa fa-edit"></i></a>';
                    echo '<a href="https://wa.me/?text='.urlencode($cardUrl).'" target="_blank" title="Share"><i class="fa fa-share"></i></a>';
                } else { echo '-'; }
                echo '</td>';
                $franchise_fee_query = mysqli_query($connect, 'SELECT * FROM invoice_details WHERE user_email="'.$f_user_email.'" AND service_name="Franchisee Registration" ORDER BY id DESC LIMIT 1');
                $paymentBadge = '<span class="badge bg-secondary">Unpaid</span>';
                $invoiceId = null; $ff = null;
                if ($franchise_fee_query && mysqli_num_rows($franchise_fee_query)>0) {
                    $ff = mysqli_fetch_array($franchise_fee_query);
                    $invoiceId = $ff['id'] ?? null;
                    if (($ff['payment_status'] ?? '')==='Success') {
                        $paid_on = !empty($ff['invoice_date']) ? date('d-m-Y', strtotime($ff['invoice_date'])) : date('d-m-Y');
                        $paymentBadge = '<span class="badge bg-success">Paid on '.$paid_on.'</span>';
                    }
                }
                echo '<td>'.$paymentBadge.'</td>';
                if ($ff) { echo '<td>₹'.number_format((float)($ff['total_amount'] ?? 0),2).'</td>'; } else { echo '<td>₹0.00</td>'; }
                echo '<td>';
                if ($invoiceId) { echo '<a href="invoice_admin_access.php?invoice_id='.$invoiceId.'" target="_blank" title="Download Invoice" class="download-btn"><i class="fa fa-download"></i></a>'; } else { echo '-'; }
                echo '</td>';
                echo '<td>'.$website_count.'</td>';
                
                // Check document verification status
			$verification_query = mysqli_query($connect, 'SELECT status FROM franchisee_verification WHERE user_email="'.$f_user_email.'" ORDER BY id DESC LIMIT 1');
			$verification_status = 'Not Uploaded';
			$has_documents = false;
			
			if(mysqli_num_rows($verification_query) > 0) {
				$verification_row = mysqli_fetch_array($verification_query);
				$verification_status = $verification_row['status'];
				$has_documents = true;
			}
			
			// Display document status
                if($verification_status == 'submitted') {
                    echo '<td><span class="document-status" onclick="viewDocuments(\''.$f_user_email.'\')">Ready to Verify</span></td>';
                } elseif($verification_status == 'approved') {
                    echo '<td><span class="badge bg-success">Approved</span></td>';
                } elseif($verification_status == 'rejected') {
                    echo '<td><span class="badge bg-danger">Not Approved</span></td>';
                } else {
                    echo '<td><span class="badge bg-secondary">Not Uploaded</span></td>';
                }
                // Wallet balance
                $wb_q = mysqli_query($connect,'SELECT w_balance FROM wallet WHERE f_user_email="'.$f_user_email.'" ORDER BY id DESC LIMIT 1');
                $wallet_balance = ($wb_q && mysqli_num_rows($wb_q)>0) ? (float)mysqli_fetch_array($wb_q)['w_balance'] : 0;
                echo '<td>';
                echo '<span class="me-2">₹'.number_format($wallet_balance,2).'</span>';
                echo '<button type="button" class="btn btn-sm btn-outline-primary" onclick="openWalletTransactions(\''.addslashes($f_user_email).'\')">View</button>';
                echo '</td>';
                if ($website_count > 0) {
                    echo '<td><button type="button" class="btn btn-sm btn-outline-primary" onclick="openFranchiseeDashboard(\''.addslashes($f_user_email).'\')">View</button></td>';
                } else {
                    echo '<td>-</td>';
                }
                echo '<td><a class="btn btn-sm btn-outline-danger" href="change-password.php?email='.urlencode($f_user_email).'">Reset</a></td>';
                echo '</tr>';
            }
        }else {
            echo '<tr><td colspan="17" class="text-center py-4">No franchisees found</td></tr>';
        }
    ?>
            </tbody>
        </table>
    </div>
</div>

<!-------------------Pagination-------------------->
		<div class="pagination">
			<?php 



				

				// Query user_details table for franchisees
				$query2=mysqli_query($connect,'SELECT * FROM user_details WHERE role="FRANCHISEE" ORDER BY id DESC ');
			
			 $pages=ceil(mysqli_num_rows($query2)/30);

			for($i=1;$i<=$pages;$i++){
				if($_GET['page_no']==$i){
					echo '<a href="?page_no='.$i.'"><div class="page_btn active">'. $i.'</div></a>';
				}else {
					echo '<a href="?page_no='.$i.'"><div class="page_btn">'. $i.'</div></a>';
				}
				
			}


			?>
	</div>

<!-------------------Pagination-------------------->

<script>
							
							// if approved
								function activateUser(id){
										
										$('.idact'+id).css('color','blue').html('Wait...');
									
										$.ajax({
											url:'js_request.php',
											method:'POST',
											data:{fr_id:id},
											dataType:'text',
											success:function(data){
												$('.idact'+id).html(data);
											}
									
										});
										
									}

							// Open franchisee dashboard in new tab (similar to Referral Details view)
							function openFranchiseeDashboard(email){
								// Open modal and load embedded dashboard preview
								var modal = document.getElementById('frPreviewModal');
								var frame = document.getElementById('frPreviewFrame');
								if(modal && frame){
									frame.src = 'franchisee_dashboard_preview.php?email=' + encodeURIComponent(email);
									modal.style.display = 'block';
								}
							}

							// Wallet Transactions popup loader
							function openWalletTransactions(email){
								var modal = document.getElementById('walletTxnModal');
								var body = document.getElementById('walletTxnBody');
								if(modal && body){
									body.innerHTML = '<div class="p-4 text-center">Loading...</div>';
									modal.style.display = 'block';
									fetch('get_wallet_transactions.php?email=' + encodeURIComponent(email))
										.then(r => r.text())
										.then(html => { body.innerHTML = html; })
										.catch(() => { body.innerHTML = '<div class="p-4 text-center text-danger">Failed to load transactions.</div>'; });
								}
							}
									
							</script>

<!-- Franchisee Dashboard Preview Modal -->
<div id="frPreviewModal" class="modal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999;">
    <div class="modal-content" style="width: 95%; max-width: 1200px; margin: 2% auto; background: #fff; border-radius: 10px; overflow: hidden;">
        <div class="modal-header" style="display:flex; justify-content: space-between; align-items:center; padding: 10px 15px; background: #1a2b4c; color: #fff;">
            <h5 style="margin:0;">Dashboard</h5>
            <button type="button" onclick="(function(){ document.getElementById('frPreviewModal').style.display='none'; document.getElementById('frPreviewFrame').src='about:blank'; })()" class="btn btn-sm btn-light">Close</button>
        </div>
        <div class="modal-body" style="height: 80vh; padding:0;">
            <iframe id="frPreviewFrame" src="about:blank" style="width:100%; height:100%; border:0;"></iframe>
        </div>
    </div>
</div>

<!-- Wallet Transactions Modal -->
<div id="walletTxnModal" class="modal" style="display:none; position: fixed; inset:0; background: rgba(0,0,0,0.6); z-index:9999;">
    <div class="modal-content" style="width:95%; max-width:1000px; margin:3% auto; background:#fff; border-radius:10px; overflow:hidden;">
        <div class="modal-header" style="display:flex; justify-content: space-between; align-items:center; padding:10px 15px; background:#1a2b4c; color:#fff;">
            <h5 style="margin:0;">Wallet Transactions</h5>
            <button type="button" onclick="(function(){ document.getElementById('walletTxnModal').style.display='none'; document.getElementById('walletTxnBody').innerHTML=''; })()" class="btn btn-sm btn-light">Close</button>
        </div>
        <div id="walletTxnBody" class="modal-body" style="max-height:75vh; overflow:auto; padding:0;"></div>
    </div>
    
</div>
							
							<script>
							// Document verification functions
							function viewDocuments(userEmail) {
								
								console.log('Opening documents for:', userEmail);
								
								// Show loading state
								$('#userEmail').text(userEmail);
								$('#panCardImage').hide();
								$('#aadhaarFrontImage').hide();
								$('#aadhaarBackImage').hide();
								$('#panCardPlaceholder').show();
								$('#aadhaarFrontPlaceholder').show();
								$('#aadhaarBackPlaceholder').show();
								
								// Ensure modal is properly initialized
								var modalElement = document.getElementById('documentModal');
								if (modalElement) {
									var modal = new bootstrap.Modal(modalElement, {
										backdrop: 'static',
										keyboard: false
									});
									modal.show();
								} else {
									console.error('Modal element not found!');
									alert('Modal not found. Please refresh the page.');
								}
								
								// Fetch document data via AJAX
								$.ajax({
									url: 'get_franchisee_documents.php',
									method: 'POST',
									data: {user_email: userEmail},
									dataType: 'json',
									success: function(data) {
										console.log('Document data received:', data);
										console.log('Data type:', typeof data);
										console.log('Raw response:', JSON.stringify(data));
										
										if(data && data.success) {
											console.log('Successfully loaded documents for:', userEmail);
											// Set document images
											if(data.pan_card_document) {
												var panCardSrc = '../franchisee/verification/uploads/' + data.pan_card_document;
												console.log('Setting PAN Card image src:', panCardSrc);
												$('#panCardImage').attr('src', panCardSrc);
												$('#panCardImage').show();
												$('#panCardPlaceholder').hide();
												
												// Check if image loads successfully
												$('#panCardImage').on('load', function() {
													console.log('PAN Card image loaded successfully');
												}).on('error', function() {
													console.error('PAN Card image failed to load:', panCardSrc);
													$('#panCardImage').hide();
													$('#panCardPlaceholder').show().text('Image not found: ' + data.pan_card_document);
												});
											} else {
												$('#panCardImage').hide();
												$('#panCardPlaceholder').show();
											}
											
											if(data.aadhaar_front_document) {
												var aadhaarFrontSrc = '../franchisee/verification/uploads/' + data.aadhaar_front_document;
												console.log('Setting Aadhaar Front image src:', aadhaarFrontSrc);
												$('#aadhaarFrontImage').attr('src', aadhaarFrontSrc);
												$('#aadhaarFrontImage').show();
												$('#aadhaarFrontPlaceholder').hide();
												
												// Check if image loads successfully
												$('#aadhaarFrontImage').on('load', function() {
													console.log('Aadhaar Front image loaded successfully');
												}).on('error', function() {
													console.error('Aadhaar Front image failed to load:', aadhaarFrontSrc);
													$('#aadhaarFrontImage').hide();
													$('#aadhaarFrontPlaceholder').show().text('Image not found: ' + data.aadhaar_front_document);
												});
											} else {
												$('#aadhaarFrontImage').hide();
												$('#aadhaarFrontPlaceholder').show();
											}
											
											if(data.aadhaar_back_document) {
												var aadhaarBackSrc = '../franchisee/verification/uploads/' + data.aadhaar_back_document;
												console.log('Setting Aadhaar Back image src:', aadhaarBackSrc);
												$('#aadhaarBackImage').attr('src', aadhaarBackSrc);
												$('#aadhaarBackImage').show();
												$('#aadhaarBackPlaceholder').hide();
												
												// Check if image loads successfully
												$('#aadhaarBackImage').on('load', function() {
													console.log('Aadhaar Back image loaded successfully');
												}).on('error', function() {
													console.error('Aadhaar Back image failed to load:', aadhaarBackSrc);
													$('#aadhaarBackImage').hide();
													$('#aadhaarBackPlaceholder').show().text('Image not found: ' + data.aadhaar_back_document);
												});
											} else {
												$('#aadhaarBackImage').hide();
												$('#aadhaarBackPlaceholder').show();
											}
											
											// Store user email for form submission
											$('#documentUserEmail').val(userEmail);
											console.log('Stored user email in hidden field:', userEmail);
										} else {
											console.error('Failed to load documents:', data);
											var errorMsg = data && data.message ? data.message : 'Unknown error occurred';
											alert('Error loading documents: ' + errorMsg);
											
											// Show placeholders with error message
											$('#panCardPlaceholder').show().text('Error: ' + errorMsg);
											$('#aadhaarFrontPlaceholder').show().text('Error: ' + errorMsg);
											$('#aadhaarBackPlaceholder').show().text('Error: ' + errorMsg);
										}
									},
									error: function(xhr, status, error) {
										console.error('=== AJAX ERROR DETAILS ===');
										console.error('Status:', status);
										console.error('Error:', error);
										console.error('Status Code:', xhr.status);
										console.error('Response Headers:', xhr.getAllResponseHeaders());
										console.error('Response Text Length:', xhr.responseText.length);
										console.error('Response Text (first 500 chars):', xhr.responseText.substring(0, 500));
										console.error('Response Text (full):', xhr.responseText);
										
										// Try to parse response for better error message
										try {
											var response = JSON.parse(xhr.responseText);
											console.log('Parsed JSON response:', response);
											alert('Error loading documents: ' + (response.message || 'Unknown error'));
										} catch(e) {
											console.error('JSON Parse Error:', e);
											console.error('JSON Parse Error Message:', e.message);
											console.error('Raw Response Type:', typeof xhr.responseText);
											alert('Error loading documents. Status: ' + xhr.status + '. Please check console for details.');
										}
									}
								});
							}
							
							// Global variables to store document status
							var documentStatus = {
								pan: null,
								aadhaar: null
							};
							
							function setDocumentStatus(documentType, status) {
								documentStatus[documentType] = status;
								console.log('Document status updated:', documentType, status);
								
								// Update button styles
								var buttons = $('button[onclick*="' + documentType + '"]');
								buttons.removeClass('btn-success btn-danger').addClass('btn-warning');
								
								// Highlight selected button
								$('button[onclick="setDocumentStatus(\'' + documentType + '\', \'' + status + '\')"]')
									.removeClass('btn-warning')
									.addClass(status === 'approve' ? 'btn-success' : 'btn-danger');
							}
							
							function setAllDocumentsStatus(status) {
								// Set status for both documents
								documentStatus.pan = status;
								documentStatus.aadhaar = status;
								console.log('All documents status updated:', status);
								
								// Update button styles for both approval buttons
								$('button[onclick="setAllDocumentsStatus(\'approve\')"]')
									.removeClass('btn-success btn-danger btn-warning')
									.addClass(status === 'approve' ? 'btn-success' : 'btn-outline-success');
								
								$('button[onclick="setAllDocumentsStatus(\'reject\')"]')
									.removeClass('btn-success btn-danger btn-warning')
									.addClass(status === 'reject' ? 'btn-danger' : 'btn-outline-danger');
								
								// Show/hide comment box based on status
								if(status === 'reject') {
									$('#commentSection').show();
									$('#verificationRemarks').focus();
								} else {
									$('#commentSection').hide();
									$('#verificationRemarks').val('');
								}
							}
							
							function submitDocumentVerification() {
								var userEmail = $('#documentUserEmail').val();
								var remarks = $('#verificationRemarks').val();
								
								console.log('Submitting verification:', {userEmail: userEmail, remarks: remarks});
								
								// Validate user email
								if(!userEmail) {
									console.error('User email is empty or undefined');
									alert('User email not found. Please refresh and try again.');
									return;
								}
								
								console.log('User email for verification:', userEmail);
								
								// Validate remarks for rejection
								if(!remarks.trim()) {
									alert('Please provide a reason for rejection.');
									return;
								}
								
								// Show loading state
								$('.btn-warning').prop('disabled', true).text('Processing...');
								
								$.ajax({
									url: 'update_document_verification.php',
									method: 'POST',
									data: {
										user_email: userEmail,
										action: 'reject',
										remarks: remarks
									},
									dataType: 'json',
									success: function(data) {
										console.log('Verification response:', data);
										
										if(data.success) {
											// Show status messages
											$('#statusMessages').show();
											$('#rejectionMessage').show();
											$('#approvalMessage').hide();
											
											// Hide submit button and show success
											$('.btn-warning').hide();
											
											// Auto close modal after 3 seconds
											setTimeout(function() {
												var modalElement = document.getElementById('documentModal');
												if (modalElement) {
													var modal = bootstrap.Modal.getInstance(modalElement);
													if (modal) {
														modal.hide();
													}
												}
												location.reload(); // Refresh page to update status
											}, 3000);
										} else {
											alert('Error: ' + data.message);
											$('.btn-warning').prop('disabled', false).text('SUBMIT');
										}
									},
									error: function(xhr, status, error) {
										console.error('=== VERIFICATION ERROR DETAILS ===');
										console.error('Status:', status);
										console.error('Error:', error);
										console.error('Status Code:', xhr.status);
										console.error('Response Headers:', xhr.getAllResponseHeaders());
										console.error('Response Text Length:', xhr.responseText.length);
										console.error('Response Text (first 500 chars):', xhr.responseText.substring(0, 500));
										console.error('Response Text (full):', xhr.responseText);
										
										// Try to parse response for better error message
										try {
											var response = JSON.parse(xhr.responseText);
											console.log('Parsed JSON response:', response);
											alert('Error updating verification: ' + (response.message || 'Unknown error'));
										} catch(e) {
											console.error('JSON Parse Error:', e);
											console.error('JSON Parse Error Message:', e.message);
											console.error('Raw Response Type:', typeof xhr.responseText);
											alert('Error updating verification. Status: ' + xhr.status + '. Please check console for details.');
										}
										
										$('.btn-warning').prop('disabled', false).text('SUBMIT');
									}
								});
							}
							
							// New function for immediate approval
							function approveDocuments() {
								var userEmail = $('#documentUserEmail').val();
								
								if(!userEmail) {
									console.error('User email is empty or undefined for approval');
									alert('User email not found. Please refresh and try again.');
									return;
								}
								
								console.log('User email for approval:', userEmail);
								
								// Show loading state
								$('.btn-success').prop('disabled', true).text('Processing...');
								$('.btn-danger').prop('disabled', true);
								
								$.ajax({
									url: 'update_document_verification.php',
									method: 'POST',
									data: {
										user_email: userEmail,
										action: 'approve',
										remarks: ''
									},
									dataType: 'json',
									success: function(data) {
										console.log('Approval response:', data);
										
										if(data.success) {
											// Show success message
											$('#statusMessages').show();
											$('#approvalMessage').show();
											$('#rejectionMessage').hide();
											
											// Hide buttons
											$('.btn-success, .btn-danger').hide();
											
											// Auto close modal after 2 seconds
											setTimeout(function() {
												var modalElement = document.getElementById('documentModal');
												if (modalElement) {
													var modal = bootstrap.Modal.getInstance(modalElement);
													if (modal) {
														modal.hide();
													}
												}
												location.reload(); // Refresh page to update status
											}, 2000);
										} else {
											alert('Error: ' + data.message);
											$('.btn-success').prop('disabled', false).text('APPROVED');
											$('.btn-danger').prop('disabled', false);
										}
									},
									error: function(xhr, status, error) {
										console.error('Approval error:', error);
										alert('Error approving documents. Please try again.');
										$('.btn-success').prop('disabled', false).text('APPROVED');
										$('.btn-danger').prop('disabled', false);
									}
								});
							}
							
							 
						 
							
						 
							$(document).ready(function() {
							 
								
								// Ensure Bootstrap modal is available
								if (typeof bootstrap === 'undefined' || typeof bootstrap.Modal === 'undefined') {
									console.error('Bootstrap modal plugin not loaded!');
									alert('Bootstrap modal plugin not loaded. Please check your Bootstrap installation.');
								} else {
									console.log('Bootstrap modal plugin loaded successfully!');
								}
								
								// Initialize modal events
								var modalElement = document.getElementById('documentModal');
								if (modalElement) {
									modalElement.addEventListener('shown.bs.modal', function () {
										console.log('Modal shown successfully');
										// Reset document status
										documentStatus = {pan: null, aadhaar: null};
										// Reset approval button styles
										$('button[onclick="setAllDocumentsStatus(\'approve\')"]')
											.removeClass('btn-success btn-outline-success')
											.addClass('btn-success');
										$('button[onclick="setAllDocumentsStatus(\'reject\')"]')
											.removeClass('btn-danger btn-outline-danger')
											.addClass('btn-danger');
										// Reset status messages
										$('#statusMessages').hide();
										$('#approvalMessage').hide();
										$('#rejectionMessage').hide();
										// Show submit button
										$('.btn-warning').show().prop('disabled', false).text('SUBMIT');
										// Hide comment section initially
										$('#commentSection').hide();
									});
									
									modalElement.addEventListener('hidden.bs.modal', function () {
										console.log('Modal hidden');
										// Reset form
										$('#verificationRemarks').val('');
										$('.btn-warning').prop('disabled', false).text('SUBMIT');
										$('#commentSection').hide();
									});
								}
							});
							</script>
							
							<!-- Document Verification Modal -->
							<div class="modal fade" id="documentModal" tabindex="-1" role="dialog" aria-labelledby="documentModalLabel" aria-hidden="true">
								<div class="modal-dialog modal-lg" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h5 class="modal-title" id="documentModalLabel">User Documents - <span id="userEmail"></span></h5>
											<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
										</div>
										<div class="modal-body">
											<div class="row">
												<div class="col-md-4">
													<div class="document-container" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 15px; min-height: 200px; text-align: center; border-style: dashed; border-color: #ddd;border-color: #040973;">
														<img id="panCardImage" src="" alt="PAN Card" style="max-width: 100%; max-height: 200px; display: none;">
														<div id="panCardPlaceholder" style="color: #999; padding: 50px 0;">No PAN Card uploaded</div>
													</div>
													<center><h6>PAN CARD</h6></center>
												</div>
												<div class="col-md-4">
													<div class="document-container" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 15px; min-height: 200px; text-align: center; border-style: dashed; border-color: #ddd;border-color: #040973;">
														<img id="aadhaarFrontImage" src="" alt="Aadhaar Front" style="max-width: 100%; max-height: 200px; display: none;">
														<div id="aadhaarFrontPlaceholder" style="color: #999; padding: 50px 0;">No Aadhaar Front uploaded</div>
													</div>
													<center><h6>AADHAAR FRONT</h6></center>
												</div>
												<div class="col-md-4">
													<div class="document-container" style="border: 1px solid #ddd; padding: 10px; margin-bottom: 15px; min-height: 200px; text-align: center; border-style: dashed; border-color: #ddd;border-color: #040973;">
														<img id="aadhaarBackImage" src="" alt="Aadhaar Back" style="max-width: 100%; max-height: 200px; display: none;">
														<div id="aadhaarBackPlaceholder" style="color: #999; padding: 50px 0;">No Aadhaar Back uploaded</div>
													</div>
													<center><h6>AADHAAR BACK</h6></center>
												</div>
											</div>
											

											<div class="row mt-3">
												<div class="col-12">
													<div class="row mt-3">
														<div class="col-12 text-center">
															<button type="button" style="background-color: #28a745; border-color: #28a745; color: white;" class="btn btn-success me-2" onclick="approveDocuments()">APPROVED</button>
															<button type="button" style="background-color: #dc3545; border-color: #dc3545; color: white;" class="btn btn-danger" onclick="setAllDocumentsStatus('reject')">NOT APPROVED</button>
														</div>
													</div>
													
													<!-- Comment Section - Hidden by default -->
													<div id="commentSection" style="display: none;" class="mt-3">
														<div class="row">
															<div class="col-12">
																<label for="verificationRemarks" class="form-label">Enter the reason for rejection:</label>
																<div class="input-group">
																	<input type="text" class="form-control" id="verificationRemarks" placeholder="Enter remarks...">
																	<div class="input-group-append">
																		<button class="btn btn-outline-secondary" type="button" onclick="$('#verificationRemarks').val('')">×</button>
																	</div>
																</div>
															</div>
														</div>
														
														<div class="row mt-3">
															<div class="col-12 text-center">
																<button type="button" class="btn btn-warning" onclick="submitDocumentVerification()">SUBMIT</button>
														 	</div>
														</div>
													</div>

													<div class="row w-100">
														<div class="col-12 text-center mb-2">
															<div id="statusMessages" style="display: none;">
																<div id="approvalMessage" style="display: none;">
																	<p class="text-success mb-1"><i class="fas fa-check-circle me-2"></i>Thanks. The user is verified now.</p>
																</div>
																<div id="rejectionMessage" style="display: none;">
																<p class="text-info mb-0"><i class="fas fa-info-circle me-2"></i>Thanks. This Reason sent to the Franchisee User</p>
																</div>
															</div>
														</div>
													</div>
												</div>
											</div>
										</div>
										 
									</div>
								</div>
							</div>
							
							<input type="hidden" id="documentUserEmail" value="">
							

							
							<style>
							.document-status {
								color: #007bff !important;
								cursor: pointer;
								text-decoration: underline;
								font-weight: bold;
							}
							
							.document-status:hover {
								color: #0056b3 !important;
							}
							
							/* Modal positioning fixes */
							.modal {
								position: fixed !important;
								top: 0 !important;
								left: 0 !important;
								width: 100% !important;
								height: 100% !important;
								z-index: 1050 !important;
								background-color: rgba(0, 0, 0, 0.5) !important;
							}
							
							.modal-dialog {
								position: relative !important;
								margin: 1.75rem auto !important;
								max-width: 800px !important;
								transform: none !important;
							}
							
							.modal-content {
								position: relative !important;
								background-color: rgb(0,5,93) !important;
								border-radius: 0.3rem !important;
								box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
								color: white !important;
							}
							
							#documentModal .document-container {
								border: 2px dashed #ddd;
								padding: 15px;
								margin-bottom: 15px;
								min-height: 200px;
								text-align: center;
								background-color: #f8f9fa;
								border-radius: 8px;
								border-style: dashed;
								border-color: #ddd;

							}
							
							#documentModal .document-container img {
								max-width: 100%;
								max-height: 200px;
								border-radius: 4px;
								box-shadow: 0 2px 4px rgba(0,0,0,0.1);
							}
							
							#documentModal .btn-warning {
								background-color: #ffc107;
								border-color: #ffc107;
								color: #212529;
								font-weight: bold;
								margin: 0 5px;
							}
							
							#documentModal .btn-warning:hover {
								background-color: #e0a800;
								border-color: #d39e00;
								color: #212529;
							}
							
							#documentModal .btn-warning:disabled {
								background-color: #6c757d;
								border-color: #6c757d;
								color: #fff;
							}
							
							#documentModal .btn-success {
								background-color: #28a745;
								border-color: #28a745;
								color: white;
								font-weight: bold;
								margin: 0 5px;
							}
							
							#documentModal .btn-success:hover {
								background-color: #218838;
								border-color: #1e7e34;
								color: white;
							}
							
							#documentModal .btn-danger {
								background-color: #dc3545;
								border-color: #dc3545;
								color: white;
								font-weight: bold;
								margin: 0 5px;
							}
							
							#documentModal .btn-danger:hover {
								background-color: #c82333;
								border-color: #bd2130;
								color: white;
							}
							
							#commentSection {
								background-color: #f8f9fa;
								padding: 15px;
								border-radius: 8px;
								border: 1px solid #dee2e6;
								margin-top: 15px;
							}
							
							/* Ensure modal backdrop */
							.modal-backdrop {
								position: fixed !important;
								top: 0 !important;
								left: 0 !important;
								width: 100vw !important;
								height: 100vh !important;
								background-color: rgba(0, 0, 0, 0.5) !important;
								z-index: 1040 !important;
							}
							</style>
</div>
   <br /><br /><br /><br />

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

 <footer class="footer-area">
  
     <center>
           <br />
                    <a href="index.html" class="footer-logo">
                        						<img src="../panel/images/f_logo.png" alt="Vcard" width="auto" height="50px">
						                    </a>
                    <p>&copy; Copyright 2025 - All Rights Reserved. Crafted With <?php echo $_SERVER['HTTP_HOST']; ?> for Someone Special ! </p> 
					<p><a target="_blank" href="https://support.ajooba.io">Support Forum</a> | <a target="_blank" href="https://support.ajooba.io/faq">Faq's</a> | <a target="_blank" href="https://support.ajooba.io/articles/category/digital-vcard">Knowlege Base</a> </p>
			
        </center></footer>