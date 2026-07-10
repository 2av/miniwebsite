<?php
/**
 * Send MAIL TEMPLATE 04B — OTP verification to customer when Franchise creates MW account.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../email_config.php';
require_once __DIR__ . '/mail_template_04b_franchise_customer_otp.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * @return bool true if mail accepted for delivery
 */
function sendFranchiseCustomerOtpEmail(
    string $customer_email,
    string $customer_name,
    string $otp,
    string $site_base_url = ''
): bool {
    if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        error_log('sendFranchiseCustomerOtpEmail: invalid customer email');
        return false;
    }

    $base = rtrim($site_base_url !== '' ? $site_base_url : ('https://' . ($_SERVER['HTTP_HOST'] ?? 'www.miniwebsite.in')), '/');
    $terms_url = $base . '/terms_conditions.php';
    $privacy_url = $base . '/privacy_policy.php';

    $tpl = getMailTemplate04bFranchiseCustomerOtp($customer_name, $otp, $terms_url, $privacy_url);

    try {
        if (class_exists(PHPMailer::class)) {
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
            $mail->setFrom(DEFAULT_FROM_EMAIL, DEFAULT_FROM_NAME);
            $mail->addAddress($customer_email);
            $mail->addReplyTo(SUPPORT_EMAIL, DEFAULT_FROM_NAME);
            $mail->isHTML(true);
            $mail->Subject = $tpl['subject'];
            $mail->Body    = $tpl['html'];
            $mail->AltBody = $tpl['text'];
            $mail->send();
            error_log('MAIL 04B franchise customer OTP sent to: ' . $customer_email);
            return true;
        }
    } catch (Exception $e) {
        error_log('MAIL 04B PHPMailer: ' . $e->getMessage());
    }

    $headers = 'From: ' . DEFAULT_FROM_NAME . ' <' . DEFAULT_FROM_EMAIL . ">\r\n";
    $headers .= 'Reply-To: ' . SUPPORT_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $ok = @mail($customer_email, $tpl['subject'], $tpl['html'], $headers);
    if ($ok) {
        error_log('MAIL 04B franchise customer OTP sent via mail() to: ' . $customer_email);
    }
    return (bool) $ok;
}
