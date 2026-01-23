<?php
/**
 * Franchise Distributor Partner (Standard Plan) Email Template
 * Template for STANDARD joining deal emails
 */

function getStandardJoiningDealEmail($user_name, $user_email) {
    $subject = 'MiniWebsite.in – Franchise Distributor Partner';
    
    $message = "Hi $user_name,\n\n";
    $message .= "We are excited to have you on board! \n\n";
    $message .= "Follow these simple steps to join us (As a Franchise Distributor Partner).\n";
    $message .= "1. Pay one-time fee to join us with a Standard Plan (Non-Refundable)\n";
    $message .= "👉 Amount: ₹5,000 + 18% GST = ₹5,900/-\n";
    $message .= "👉 Payment Link: https://miniwebsite.in/franchisee-distributer-agreement.php?email=" . urlencode($user_email) . "\n";
    $message .= "2. After the payment, it will take around 48 hrs to setup your personalized dashboard along with the Creator Kit, ready for you to start.\n\n";
    $message .= "That's it! 🎉 Once these steps are completed, you will become an official Franchise Distributor Partner of MiniWebsite.in network. ";
    $message .= "You can begin building your business and start earning right away.\n\n";
    $message .= "IMPORTANT: Please save your payment transaction ID for reference.\n\n";
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
