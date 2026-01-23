<?php
/**
 * Digital Marketing Partner Email Template
 * Template for BASIC_FREE joining deal emails
 */

function getBasicFreeJoiningDealEmail($user_name, $user_email) {
    $subject = 'MiniWebsite.in – Digital Marketing Partner';
    
    $message = "Hi $user_name,\n\n";
    $message .= "🎉 We are excited to have you on board! \n";
    $message .= "You are now an official Digital Marketing Partner of MiniWebsite.in network. ";
    $message .= "You can begin your business and start earning right away by Selling MiniWebsite Franchise to your audiences.\n\n";
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
