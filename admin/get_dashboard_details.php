<?php
require_once(__DIR__ . '/../app/config/database.php');

if (!isset($_SESSION['admin_email'])) {
    header('Location: login.php');
    exit;
}

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('safe_h')) {
    function safe_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$user_email = isset($_GET['user_email']) ? strtolower(trim($_GET['user_email'])) : '';

if (empty($user_email)) {
    echo '<div class="alert alert-danger m-0">Invalid user email provided.</div>';
    exit;
}

// Get website details from digi_card table
$website_list = [];
$website_details_query = mysqli_query($connect, "SELECT id, d_comp_name, card_id, d_card_status, d_payment_status, d_payment_date, uploaded_date, validity_date, complimentary_enabled, f_user_email FROM digi_card WHERE user_email = '".mysqli_real_escape_string($connect, $user_email)."' ORDER BY uploaded_date DESC");

if ($website_details_query) {
    while ($website = mysqli_fetch_array($website_details_query)) {
        $website_list[] = $website;
    }
} else {
    echo '<div class="alert alert-danger m-0">Error loading website details.</div>';
    exit;
}

?>

<?php if (!empty($website_list)): ?>
<div style="padding: 15px;">
    <div style="max-height: 600px; overflow-y: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; position: sticky; top: 0;">
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">MW ID</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Company Name</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Date Created</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Validity Date</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">MW Status</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">User Payment Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($website_list as $website): 
                    // Calculate validity date
                    if(!empty($website['validity_date']) && $website['validity_date'] != '0000-00-00 00:00:00') {
                        $validity_date = date('d-m-Y', strtotime($website['validity_date']));
                    } else {
                        if($website['d_payment_status'] == 'Success') {
                            $validity_date = date('d-m-Y', strtotime($website['d_payment_date'] . ' +1 year'));
                        } else {
                            $validity_date = date('d-m-Y', strtotime($website['uploaded_date'] . ' +7 days'));
                        }
                    }
                    
                    // Determine MW Status
                    if($website['complimentary_enabled'] == 'Yes') {
                        $status_class = 'bg-success';
                        $status_text = 'Active';
                    } else if ($website['d_payment_status'] == 'Success') {
                        $is_expired = (!empty($website['validity_date']) && $website['validity_date'] != '0000-00-00 00:00:00') ? (strtotime($website['validity_date']) < time()) : false;
                        if ($is_expired) {
                            $status_class = 'bg-secondary lightGray';
                            $status_text = 'Expired <br/>on ' . date('d-m-Y', strtotime($website['validity_date']));
                        } else {
                            $status_class = 'bg-success';
                            $status_text = 'Active';
                        }
                    } else {
                        $trial_end = date('Y-m-d H:i:s', strtotime($website['uploaded_date'] . ' +7 days'));
                        if (strtotime($trial_end) < time()) {
                            $status_class = 'bg-secondary lightGray';
                            $status_text = 'Inactive';
                        } else {
                            $status_class = 'bg-pending';
                            $status_text = '7 Day Trial';
                        }
                    }
                ?>
                <tr style="border-bottom: 1px solid #dee2e6;">
                    <td style="padding: 12px;"><?php echo safe_h($website['id']); ?></td>
                    <td style="padding: 12px;"><?php echo safe_h($website['d_comp_name'] ?: 'Unnamed'); ?></td>
                    <td style="padding: 12px;"><?php echo date('d-m-Y', strtotime($website['uploaded_date'])); ?></td>
                    <td style="padding: 12px;"><?php echo $validity_date; ?></td>
                    <td style="padding: 12px; text-align: left;">
                        <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background-color: <?php 
                            if($status_class === 'bg-success') echo '#d4edda';
                            elseif($status_class === 'bg-pending') echo '#fff3cd';
                            else echo '#e2e3e5';
                        ?>; color: <?php 
                            if($status_class === 'bg-success') echo '#155724';
                            elseif($status_class === 'bg-pending') echo '#856404';
                            else echo '#383d41';
                        ?>;">
                            <?php echo $status_text; ?>
                        </span>
                    </td>
                    <td style="padding: 12px; text-align: left;">
                        <?php if($website['complimentary_enabled'] == 'Yes') { ?>
                            <span style="display: inline-block; background-color: #e7f3ff; color: #0c5aa0; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Complimentary</span>
                        <?php } else if($website['d_payment_status'] == 'Success') { 
                            $paid_on = !empty($website['d_payment_date']) ? date('d-m-Y', strtotime($website['d_payment_date'])) : '';
                            if ($paid_on) { ?>
                                <span style="display: inline-block; background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Paid on <?php echo $paid_on; ?></span>
                            <?php } else { ?>
                                <span style="display: inline-block; background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Paid</span>
                            <?php } 
                        } else { ?>
                            <span style="display: inline-block; background-color: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Pending</span>
                        <?php } ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div style="padding: 20px;">
    <div style="background-color: #e7f3ff; border-left: 4px solid #2196F3; padding: 12px; border-radius: 4px; color: #0c5aa0; font-size: 13px;">
        <strong>ℹ️ No websites created</strong> - This user hasn't created any Mini Websites yet.
    </div>
</div>
<?php endif; ?>
