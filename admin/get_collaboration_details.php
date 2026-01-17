<?php
require_once('connect.php');

header('Content-Type: text/html; charset=UTF-8');

$referrer_email = isset($_GET['referrer_email']) ? mysqli_real_escape_string($connect, $_GET['referrer_email']) : '';
if ($referrer_email === '') {
    echo '<div class="alert alert-danger">Missing referrer email</div>';
    exit;
}

// Collab and saleskit flags (optional) - query from user_details
$flags_q = mysqli_query($connect, "SELECT collaboration_enabled, saleskit_enabled, name FROM user_details WHERE email='".$referrer_email."' AND role='CUSTOMER' LIMIT 1");
$flags = $flags_q ? mysqli_fetch_array($flags_q) : array();
$user_name = $flags['name'] ?? $referrer_email;

// Summary: Pending and Total referral amount (paid users only)
$earnings_q = mysqli_query($connect, "SELECT 
    COALESCE(SUM(re.amount),0) as total_referral_amount,
    (
        SELECT COALESCE(SUM(rph.amount),0) FROM referral_payment_history rph 
        WHERE rph.referral_id IN (
            SELECT id FROM referral_earnings WHERE referrer_email = '".$referrer_email."'
        )
    ) as total_paid_amount
    FROM referral_earnings re 
    LEFT JOIN digi_card dc ON BINARY re.referred_email = BINARY dc.user_email
    WHERE re.referrer_email = '".$referrer_email."' 
      AND dc.d_payment_status = 'Success'");
$earnings = $earnings_q ? mysqli_fetch_array($earnings_q) : array('total_referral_amount'=>0,'total_paid_amount'=>0);
$total_referral_amount = (float)($earnings['total_referral_amount'] ?? 0);
$total_paid_amount = (float)($earnings['total_paid_amount'] ?? 0);
$pending_amount = $total_referral_amount - $total_paid_amount;

// Counts
$regular_q = mysqli_query($connect, "SELECT COUNT(*) as c FROM referral_earnings re 
    LEFT JOIN digi_card dc ON BINARY re.referred_email = BINARY dc.user_email
    WHERE re.referrer_email = '".$referrer_email."' 
      AND (re.is_collaboration IS NULL OR re.is_collaboration = 'NO')");
$regular_referrals = ($regular_q && ($r = mysqli_fetch_array($regular_q))) ? (int)$r['c'] : 0;

$collab_q = mysqli_query($connect, "SELECT COUNT(*) as c FROM referral_earnings re 
    LEFT JOIN digi_card dc ON BINARY re.referred_email = BINARY dc.user_email
    WHERE re.referrer_email = '".$referrer_email."' 
      AND re.is_collaboration = 'YES'");
$collaboration_referrals = ($collab_q && ($r2 = mysqli_fetch_array($collab_q))) ? (int)$r2['c'] : 0;

// Bank details
$bank_q = mysqli_query($connect, "SELECT account_holder_name, account_number, ifsc_code, bank_name, upi_id, upi_name 
    FROM user_bank_details WHERE user_email='".$referrer_email."' LIMIT 1");
$bank = $bank_q ? mysqli_fetch_array($bank_q) : array();

// Referred users list
$details_q = mysqli_query($connect, "SELECT 
    re.id as referral_id,
    re.referred_email,
    re.referral_date,
    re.amount,
    re.is_collaboration,
    cl.id as customer_id,
    fl.id as franchisee_id,
    COALESCE(cl.name, fl.name) as user_name,
    COALESCE(cl.phone, fl.phone) as user_contact,
    dc.id as card_id,
    dc.uploaded_date as card_uploaded_date,
    dc.validity_date as card_validity_date,
    dc.complimentary_enabled,
    dc.d_payment_status,
    dc.d_payment_date
    FROM referral_earnings re 
    LEFT JOIN user_details cl ON BINARY re.referred_email = BINARY cl.email AND cl.role='CUSTOMER' AND (re.is_collaboration IS NULL OR re.is_collaboration = 'NO')
    LEFT JOIN user_details fl ON BINARY re.referred_email = BINARY fl.email AND fl.role='FRANCHISEE' AND re.is_collaboration = 'YES'
    LEFT JOIN digi_card dc ON BINARY re.referred_email = BINARY dc.user_email
    WHERE re.referrer_email = '".$referrer_email."' 
    ORDER BY re.referral_date DESC");

?>
<div style="padding:16px; background:#fff;">
    <div class="container-fluid px-2">
        <div class="mb-3">
            <h5 style="margin:0;">Referral Overview - <?php echo htmlspecialchars($user_name); ?></h5>
            <small class="text-muted"><?php echo htmlspecialchars($referrer_email); ?></small>
        </div>

        <div class="row" style="margin-bottom:16px;">
            <div class="col-sm-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="img"><img src="../customer/../assets/img/PendingAmt.png" alt="" style="height:40px;"></div>
                        <p class="mb-1">Pending Amount</p>
                        <h4><i class="fa fa-inr"></i> <?php echo number_format($pending_amount, 0); ?>/-</h4>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="img"><img src="../customer/../assets/img/TotalEarning.png" alt="" style="height:40px;"></div>
                        <p class="mb-1">Total Referral Earning</p>
                        <h4><i class="fa fa-inr"></i> <?php echo number_format($total_referral_amount, 0); ?>/-</h4>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="img"><img src="../customer/../assets/img/ReferredUsers.png" alt="" style="height:40px;"></div>
                        <p class="mb-1">Referred MW</p>
                        <h4><?php echo (int)$regular_referrals; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="img"><img src="../customer/../assets/img/ReferredUsers.png" alt="" style="height:40px;"></div>
                        <p class="mb-1">Referred Franchise</p>
                        <h4><?php echo (int)$collaboration_referrals; ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px;">
            <div class="card-body">
                <h6 class="mb-2">Bank Account Details</h6>
                <form id="adminBankDetailsForm">
                    <input type="hidden" name="user_email" value="<?php echo htmlspecialchars($referrer_email); ?>">
                    <div class="row">
                        <div class="col-sm-6">
                            <div><strong>Bank Name:</strong>
                                <span class="display-text"> <?php echo htmlspecialchars($bank['bank_name'] ?? '-'); ?></span>
                                <input class="form-control form-control-sm edit-input" name="bank_name" style="display:none;" value="<?php echo htmlspecialchars($bank['bank_name'] ?? ''); ?>">
                            </div>
                            <div class="mt-2"><strong>Account Holder:</strong>
                                <span class="display-text"> <?php echo htmlspecialchars($bank['account_holder_name'] ?? '-'); ?></span>
                                <input class="form-control form-control-sm edit-input" name="account_holder_name" style="display:none;" value="<?php echo htmlspecialchars($bank['account_holder_name'] ?? ''); ?>">
                            </div>
                            <div class="mt-2"><strong>Account No.:</strong>
                                <span class="display-text"> <?php echo htmlspecialchars($bank['account_number'] ?? '-'); ?></span>
                                <input class="form-control form-control-sm edit-input" name="account_number" style="display:none;" value="<?php echo htmlspecialchars($bank['account_number'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div><strong>IFSC Code:</strong>
                                <span class="display-text"> <?php echo htmlspecialchars($bank['ifsc_code'] ?? '-'); ?></span>
                                <input class="form-control form-control-sm edit-input" name="ifsc_code" style="display:none;" value="<?php echo htmlspecialchars($bank['ifsc_code'] ?? ''); ?>">
                            </div>
                            <div class="mt-2"><strong>UPI ID:</strong>
                                <span class="display-text"> <?php echo htmlspecialchars($bank['upi_id'] ?? '-'); ?></span>
                                <input class="form-control form-control-sm edit-input" name="upi_id" style="display:none;" value="<?php echo htmlspecialchars($bank['upi_id'] ?? ''); ?>">
                            </div>
                            <div class="mt-2"><strong>UPI Name:</strong>
                                <span class="display-text"> <?php echo htmlspecialchars($bank['upi_name'] ?? '-'); ?></span>
                                <input class="form-control form-control-sm edit-input" name="upi_name" style="display:none;" value="<?php echo htmlspecialchars($bank['upi_name'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" id="editBankBtn" class="btn btn-sm btn-warning">Edit</button>
                        <button type="button" id="saveBankBtn" class="btn btn-sm btn-success" style="display:none;">Save</button>
                    </div>
                </form>
                <script>
                    (function(){
                        const editBtn = document.getElementById('editBankBtn');
                        const saveBtn = document.getElementById('saveBankBtn');
                        const form = document.getElementById('adminBankDetailsForm');
                        const displayTexts = form.querySelectorAll('.display-text');
                        const editInputs = form.querySelectorAll('.edit-input');
                        function setMode(isEdit){
                            displayTexts.forEach(el => el.style.display = isEdit ? 'none' : 'inline');
                            editInputs.forEach(el => el.style.display = isEdit ? 'block' : 'none');
                            editBtn.style.display = isEdit ? 'none' : 'inline-block';
                            saveBtn.style.display = isEdit ? 'inline-block' : 'none';
                        }
                        editBtn.addEventListener('click', function(){ setMode(true); });
                        saveBtn.addEventListener('click', function(){
                            if(!confirm('Save updated bank details?')) return;
                            const formData = new FormData(form);
                            // Required flag for the endpoint to process the update
                            formData.append('update_bank_details', 'YES');
                            // Endpoint already used in admin/manage_referrals.php
                            fetch('update_bank_details_inline.php', { method:'POST', body: formData })
                                .then(r=>r.text())
                                .then(t=>{
                                    if(String(t).toLowerCase().includes('success')){
                                        // Update display text
                                        const inputs = Array.from(editInputs);
                                        const texts = Array.from(displayTexts);
                                        texts.forEach((span, idx) => { if(inputs[idx]) span.textContent = ' ' + inputs[idx].value; });
                                        setMode(false);
                                    } else {
                                        alert('Failed to update bank details');
                                    }
                                })
                                .catch(()=> alert('Failed to update bank details'));
                        });
                    })();
                </script>
            </div>
        </div>

        <div class="card">
            <div class="card-body" style="overflow:auto;">
                <h6 class="mb-2">Referred Users</h6>
                <table class="table table-striped table-hover" style="min-width:1100px;">
                    <thead class="bg-secondary" style="color:#fff;">
                        <tr>
                            <th class="text-left">User ID</th>
                            <th class="text-left">MW ID</th>
                            <th class="text-left">User Email</th>
                            <th class="text-left">User Name</th>
                            <th class="text-left">User Number</th>
                            <th class="text-left">Joined On</th>
                            <th class="text-left">Referral Source</th>
                            <th class="text-left">Date Created</th>
                            <th class="text-left">Validity Date</th>
                            <th class="text-left">MW Status</th>
                            <th class="text-left">User Payment Status</th>
                            <th class="text-left">Referral Amt.</th>
                            <th class="text-left">MW Payment Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($details_q && mysqli_num_rows($details_q) > 0): ?>
                            <?php while($row = mysqli_fetch_array($details_q)): ?>
                                <tr>
                                    <?php
                                        $user_id_display = (($row['is_collaboration'] ?? 'NO') === 'YES') ? ($row['franchisee_id'] ?? 'N/A') : ($row['customer_id'] ?? 'N/A');
                                        $joined_on = !empty($row['referral_date']) ? date('d-m-Y', strtotime($row['referral_date'])) : '-';
                                        $date_created = !empty($row['card_uploaded_date']) ? date('d-m-Y', strtotime($row['card_uploaded_date'])) : '-';
                                        $validity_display = '-';
                                        if (!empty($row['card_validity_date'])) {
                                            $validity_display = date('d-m-Y', strtotime($row['card_validity_date']));
                                        } elseif (!empty($row['card_uploaded_date'])) {
                                            if (($row['complimentary_enabled'] ?? '') === 'Yes') {
                                                $validity_display = date('d-m-Y', strtotime($row['card_uploaded_date'] . ' +1 year'));
                                            } else {
                                                if (($row['d_payment_status'] ?? '') === 'Success' && !empty($row['d_payment_date'])) {
                                                    $validity_display = date('d-m-Y', strtotime($row['d_payment_date'] . ' +1 year'));
                                                } else {
                                                    $validity_display = date('d-m-Y', strtotime($row['card_uploaded_date'] . ' +7 days'));
                                                }
                                            }
                                        }
                                        $mw_status = '7 Day Trial';
                                        if (($row['complimentary_enabled'] ?? '') === 'Yes') {
                                            $mw_status = 'Active';
                                        } else if (($row['d_payment_status'] ?? '') === 'Success') {
                                            $mw_status = 'Active';
                                        }
                                    ?>
                                    <td class="text-left"><?php echo htmlspecialchars((string)$user_id_display); ?></td>
                                    <td class="text-left"><?php echo htmlspecialchars($row['card_id'] ?? '-'); ?></td>
                                    <td class="text-left"><?php echo htmlspecialchars($row['referred_email'] ?? '-'); ?></td>
                                    <td class="text-left"><?php echo htmlspecialchars($row['user_name'] ?? 'Unknown'); ?></td>
                                    <td class="text-left"><?php echo htmlspecialchars($row['user_contact'] ?? 'N/A'); ?></td>
                                    <td class="text-left"><?php echo $joined_on; ?></td>
                                    <td class="text-left"><?php echo (($row['is_collaboration'] ?? 'NO') === 'YES') ? 'Franchisee' : 'MiniWebsite'; ?></td>
                                    <td class="text-left"><?php echo $date_created; ?></td>
                                    <td class="text-left"><?php echo $validity_display; ?></td>
                                    <td class="text-left"><span class="<?php echo ($mw_status === 'Active' ? 'bg-success' : 'bg-pending'); ?>"><?php echo $mw_status; ?></span></td>
                                    <td class="text-left">
                                        <?php if(($row['d_payment_status'] ?? '') === 'Success') { 
                                            $payment_date = !empty($row['d_payment_date']) ? date('d-m-Y', strtotime($row['d_payment_date'])) : date('d-m-Y');
                                            echo '<span class="bg-success">Paid on ' . $payment_date . '</span>';
                                        } else { 
                                            echo '<span class="bg-unpaid">Unpaid</span>'; 
                                        } ?>
                                    </td>
                                    <td class="text-left">₹ <?php echo number_format((float)($row['amount'] ?? 0), 0); ?></td>
                                    <td class="text-left"><?php echo ((($row['d_payment_status'] ?? '') === 'Success') ? '<span class="bg-success">Paid</span>' : '<span class="bg-unpaid">Pending</span>'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="13" class="text-left">No referrals found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>





