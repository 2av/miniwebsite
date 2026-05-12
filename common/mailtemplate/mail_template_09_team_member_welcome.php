<?php
/**
 * MAIL TEMPLATE 09 — Team Member Welcome with Login Credentials
 */

/**
 * @return array{subject: string, html: string, text: string}
 */
function getMailTemplate09TeamMemberWelcome(
    string $team_member_name,
    string $username,
    string $password,
    string $login_url
): array {
    $safeName = htmlspecialchars($team_member_name, ENT_QUOTES, 'UTF-8');
    $safeUser = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safePass = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
    $safeUrl  = htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8');

    $subject = 'Welcome to MiniWebsite.in - Your Login Credentials';

    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>'
        . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8')
        . '</title></head><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">'
        . '<p>Dear <strong>' . $safeName . '</strong>,</p>'
        . '<p>Welcome to MiniWebsite.in!</p>'
        . '<p>We are pleased to have you join our team as a Field Sales Executive (FSE).</p>'
        . '<p>Below are your login credentials to access the MiniWebsite Sales Dashboard:</p>'
        . '<ul style="margin:0 0 1em 1.2em;padding:0;">'
        . '<li><strong>Username:</strong> ' . $safeUser . '</li>'
        . '<li><strong>Password:</strong> ' . $safePass . '</li>'
        . '</ul>'
        . '<p><a href="' . $safeUrl . '" style="display:inline-block;background:#0d6efd;color:#fff;padding:10px 16px;text-decoration:none;border-radius:4px;">Login to Team Dashboard</a></p>'
        . '<p><strong>Important Security Instruction:</strong><br>'
        . 'Please do not share your username and password with anyone under any circumstances. '
        . 'You are solely responsible for maintaining the confidentiality of your account.</p>'
        . '<p>You will soon receive training to help you understand:</p>'
        . '<ul style="margin:0 0 1em 1.2em;padding:0;">'
        . '<li>MiniWebsite.in product and services</li>'
        . '<li>Sales process and customer handling</li>'
        . '<li>Dashboard usage and reporting</li>'
        . '<li>Company policies and procedures</li>'
        . '</ul>'
        . '<p>This training will enable you to start your sales work confidently and effectively.</p>'
        . '<p>If you have any questions or face any difficulty in logging in, please contact your reporting manager or the support team.</p>'
        . '<p>We wish you great success with MiniWebsite.in.</p>'
        . '<p>Best Regards,<br>Team MiniWebsite<br>+91-8766203925<br><a href="https://www.miniwebsite.in">www.MiniWebsite.in</a></p>'
        . '</body></html>';

    $text = "Dear {$team_member_name},\n\n"
        . "Welcome to MiniWebsite.in!\n"
        . "We are pleased to have you join our team as a Field Sales Executive (FSE).\n\n"
        . "Below are your login credentials to access the MiniWebsite Sales Dashboard:\n"
        . "Username: {$username}\n"
        . "Password: {$password}\n\n"
        . "Login URL: {$login_url}\n\n"
        . "Important Security Instruction:\n"
        . "Please do not share your username and password with anyone under any circumstances. "
        . "You are solely responsible for maintaining the confidentiality of your account.\n\n"
        . "You will soon receive training to help you understand:\n"
        . "- MiniWebsite.in product and services\n"
        . "- Sales process and customer handling\n"
        . "- Dashboard usage and reporting\n"
        . "- Company policies and procedures\n\n"
        . "This training will enable you to start your sales work confidently and effectively.\n\n"
        . "If you have any questions or face any difficulty in logging in, please contact your reporting manager or the support team.\n"
        . "We wish you great success with MiniWebsite.in.\n\n"
        . "Best Regards,\n"
        . "Team MiniWebsite\n"
        . "+91-8766203925\n"
        . "www.MiniWebsite.in\n";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}
