<?php
/**
 * Creator Collaboration Partnership Email Template
 * Template for CREATOR joining deal emails
 */

function getCreatorJoiningDealEmail($user_name, $user_email) {
    $subject = 'MiniWebsite.in – Creator Collaboration Partnership';
    
    $message = "Hi $user_name,\n\n";
    $message .= "🎉 We are excited to have you on board! \n";
    $message .= "You are now an official Creator Collaboration Partner of MiniWebsite.in network. ";
    $message .= "You can begin your business and start earning right away by sharing your referral links to your audiences.\n\n";
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
