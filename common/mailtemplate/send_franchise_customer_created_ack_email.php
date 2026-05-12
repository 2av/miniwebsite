<?php
/**
 * Send MAIL TEMPLATE 04A to franchisee (acknowledgement: new customer MW created).
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../email_config.php';
require_once __DIR__ . '/mail_template_04a_franchise_customer_ack.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * @return bool true if mail accepted for delivery
 */
function sendFranchiseCustomerCreatedAckEmail(
    string $franchisee_email,
    string $franchisee_name,
    string $customer_name,
    string $customer_email,
    string $company_name,
    string $customer_mobile
): bool {
    if (!filter_var($franchisee_email, FILTER_VALIDATE_EMAIL)) {
        error_log('sendFranchiseCustomerCreatedAckEmail: invalid franchisee email');
        return false;
    }

    $greet = franchise_ack_greeting_name($franchisee_name);
    $tpl = getMailTemplate04aFranchiseCustomerAck(
        $greet,
        $customer_name,
        $customer_email,
        $company_name,
        $customer_mobile
    );

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
            $mail->addAddress($franchisee_email);
            $mail->addReplyTo(SUPPORT_EMAIL, DEFAULT_FROM_NAME);
            $mail->isHTML(true);
            $mail->Subject = $tpl['subject'];
            $mail->Body    = $tpl['html'];
            $mail->AltBody = $tpl['text'];
            $mail->send();
            error_log('MAIL 04A franchise ack sent to: ' . $franchisee_email);
            return true;
        }
    } catch (Exception $e) {
        error_log('MAIL 04A PHPMailer: ' . $e->getMessage());
    }

    $headers = 'From: ' . DEFAULT_FROM_NAME . ' <' . DEFAULT_FROM_EMAIL . ">\r\n";
    $headers .= 'Reply-To: ' . SUPPORT_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $ok = @mail($franchisee_email, $tpl['subject'], $tpl['html'], $headers);
    if ($ok) {
        error_log('MAIL 04A franchise ack sent via mail() to: ' . $franchisee_email);
    }
    return (bool) $ok;
}
