<?php
/**
 * Upgrade Plan Details Email Template
 * Template for upgrade plan emails with payment details
 */

function getUpgradePlanDetailsEmail($user_name, $user_email, $current_plan, $upgrade_plan, $amount, $gst_amount, $total_amount) {
    $subject = 'MiniWebsite.in – Upgrade Plan Details';
    
    $message = "Hi $user_name,\n\n";
    $message .= "We are excited that you would like to upgrade your current plan! \n\n\n";
    $message .= "Upgrade Plan Details: \"$current_plan\" to \"$upgrade_plan\"\n\n";
    $message .= "Pay plan fees to upgrade (Non-Refundable)\n";
    $message .= "👉 Amount: ₹" . number_format($amount, 0) . " + 18% GST = ₹" . number_format($total_amount, 0) . "/-\n";
    $message .= "👉 Payment Link: https://miniwebsite.in/franchisee-distributer-agreement.php?email=" . urlencode($user_email) . "\n\n";
    $message .= "After the payment is done, the commission amount will automatically update as per your upgraded plan, ready for you to start.\n";
    $message .= "If you have any questions or need assistance, feel free to reach out to our support team.\n\n";
    $message .= "Best regards,\n";
    $message .= "Team MiniWebsite.in\n";
    $message .= "www.miniwebsite.in";
    
    return [
        'subject' => $subject,
        'message' => $message
    ];
}

/**
 * Upgrade Plan Confirmation Email Template
 * Template for upgrade confirmation emails (when upgrade is completed)
 */

function getUpgradePlanConfirmationEmail($user_name, $user_email, $current_plan, $upgrade_plan) {
    $subject = 'MiniWebsite.in – Upgrade Plan Confirmation';
    
    $message = "Hi $user_name,\n\n";
    $message .= "🎉 Great news! Your plan is being upgraded from the \"$current_plan\" to the \"$upgrade_plan\". \n";
    $message .= "The upgrade will be completed within 2 hours, and your commission rate will automatically update once it's done.\n";
    $message .= "You can start growing your business right away by sharing your referral links with your audience.\n\n";
    $message .= "If you have any questions or need assistance, feel free to reach out to our support team.\n\n";
    $message .= "Best regards,\n";
    $message .= "Team MiniWebsite.in\n";
    $message .= "www.miniwebsite.in";
    
    return [
        'subject' => $subject,
        'message' => $message
    ];
}

/**
 * Upgrade Plan Pending Email Template
 * Template for upgrade pending emails (when upgrade is in progress)
 */

function getUpgradePlanPendingEmail($user_name, $user_email, $current_plan, $upgrade_plan, $remaining_amount) {
    $subject = 'MiniWebsite.in – Upgrade Plan Pending';
    
    $message = "Hi $user_name,\n\n";
    $message .= "We are excited that you would like to upgrade your current plan! \n\n\n";
    $message .= "Upgrade Plan Details: \"$current_plan\" to \"$upgrade_plan\"\n\n";
    $message .= "Pay remaining amount to complete upgrade (Non-Refundable)\n";
    $message .= "👉 Remaining Amount: ₹" . number_format($remaining_amount, 0) . "/- (GST Included)\n";
    $message .= "👉 Payment Link: https://miniwebsite.in/franchisee-distributer-agreement.php?email=" . urlencode($user_email) . "\n\n";
    $message .= "After the payment is done, the commission amount will automatically update as per your upgraded plan, ready for you to start.\n";
    $message .= "If you have any questions or need assistance, feel free to reach out to our support team.\n\n";
    $message .= "Best regards,\n";
    $message .= "Team MiniWebsite.in\n";
    $message .= "www.miniwebsite.in";
    
    return [
        'subject' => $subject,
        'message' => $message
    ];
}
?>
