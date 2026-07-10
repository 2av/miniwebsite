<?php
/**
 * MAIL TEMPLATE 04B — OTP to MW user when Franchise creates account
 * Includes Terms & Conditions and Privacy Policy links.
 */

/**
 * @return array{subject: string, html: string, text: string}
 */
function getMailTemplate04bFranchiseCustomerOtp(
    string $customer_name,
    string $otp,
    string $terms_url,
    string $privacy_url
): array {
    $safeName = htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $safeTerms = htmlspecialchars($terms_url, ENT_QUOTES, 'UTF-8');
    $safePrivacy = htmlspecialchars($privacy_url, ENT_QUOTES, 'UTF-8');

    $subject = 'Verify your email — Mini Website account created by your Franchise';

    $disclaimer = 'Make sure you read &amp; aware of the &quot;Terms &amp; Conditions &amp; Privacy Policy&quot; before proceed.';

    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>'
        . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8')
        . '</title></head><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">'
        . '<p>Hi <strong>' . $safeName . '</strong>,</p>'
        . '<p>Your Franchise has initiated creation of your Mini Website account on MiniWebsite.in.</p>'
        . '<p>Please verify your email address using the OTP below to complete account setup:</p>'
        . '<p style="font-size:22px;font-weight:bold;letter-spacing:4px;margin:20px 0;">' . $safeOtp . '</p>'
        . '<p>This OTP is valid for <strong>10 minutes</strong>.</p>'
        . '<p style="margin:20px 0;padding:12px 16px;background:#fff8e6;border-left:4px solid #ffbe17;">'
        . '<strong>Important:</strong> ' . $disclaimer . '</p>'
        . '<ul style="margin:0 0 1em 1.2em;padding:0;">'
        . '<li><a href="' . $safeTerms . '" target="_blank" rel="noopener noreferrer">Terms &amp; Conditions</a></li>'
        . '<li><a href="' . $safePrivacy . '" target="_blank" rel="noopener noreferrer">Privacy Policy</a></li>'
        . '</ul>'
        . '<p>If you did not expect this email, please contact your Franchise or our support team.</p>'
        . '<p>Best regards,<br>Team MiniWebsite.in<br><a href="https://www.miniwebsite.in">www.miniwebsite.in</a></p>'
        . '</body></html>';

    $text = "Hi {$customer_name},\n\n"
        . "Your Franchise has initiated creation of your Mini Website account on MiniWebsite.in.\n\n"
        . "Your OTP is: {$otp}\n"
        . "This OTP is valid for 10 minutes.\n\n"
        . "Important: Make sure you read & aware of the \"Terms & Conditions & Privacy Policy\" before proceed.\n"
        . "Terms & Conditions: {$terms_url}\n"
        . "Privacy Policy: {$privacy_url}\n\n"
        . "Best regards,\nTeam MiniWebsite.in\nwww.miniwebsite.in\n";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}
