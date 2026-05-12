<?php
/**
 * MAIL TEMPLATE 04A — MW to Franchise
 * Acknowledgement when a franchise creates a new Mini Website (customer account).
 */

/**
 * @return array{subject: string, html: string, text: string}
 */
function getMailTemplate04aFranchiseCustomerAck(
    string $franchisee_display_name,
    string $customer_name,
    string $customer_email,
    string $company_name,
    string $customer_mobile
): array {
    $greet = htmlspecialchars($franchisee_display_name, ENT_QUOTES, 'UTF-8');
    $cname = htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8');
    $cemail = htmlspecialchars($customer_email, ENT_QUOTES, 'UTF-8');
    $ccomp = htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8');
    $cmobile = htmlspecialchars($customer_mobile, ENT_QUOTES, 'UTF-8');

    $subject = '🎉 Congratulations! - Your Customer Account is Created!';

    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</title></head><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">'
        . '<p>Hi <strong>' . $greet . '</strong>,</p>'
        . '<p>Congratulations! You have successfully created a new Mini Website for your customer.</p>'
        . '<p><strong>Customer Account Details:</strong></p>'
        . '<ul style="margin: 0 0 1em 1.2em; padding: 0;">'
        . '<li><strong>Name:</strong> ' . $cname . '</li>'
        . '<li><strong>Email:</strong> ' . $cemail . '</li>'
        . '<li><strong>Company Name:</strong> ' . $ccomp . '</li>'
        . '<li><strong>Mobile Number:</strong> ' . $cmobile . '</li>'
        . '</ul>'
        . '<p>Best regards,<br>Team MiniWebsite.in<br><a href="https://www.miniwebsite.in">www.miniwebsite.in</a></p>'
        . '</body></html>';

    $text = "Hi {$franchisee_display_name},\n\n"
        . "Congratulations! You have successfully created a new Mini Website for your customer.\n\n"
        . "Customer Account Details:\n"
        . "Name: {$customer_name}\n"
        . "Email: {$customer_email}\n"
        . "Company Name: {$company_name}\n"
        . "Mobile Number: {$customer_mobile}\n\n"
        . "Best regards,\nTeam MiniWebsite.in\nwww.miniwebsite.in\n";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

/**
 * First word of name for greeting (e.g. "Ajeet Kumar" → "Ajeet").
 */
function franchise_ack_greeting_name(string $franchisee_full_name): string
{
    $t = trim($franchisee_full_name);
    if ($t === '') {
        return 'Franchise Partner';
    }
    $parts = preg_split('/\s+/u', $t, 2);
    return $parts[0] ?? $t;
}
