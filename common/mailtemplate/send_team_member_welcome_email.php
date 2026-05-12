<?php
/**
 * Send MAIL TEMPLATE 09 to newly created Team member.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../email_config.php';
require_once __DIR__ . '/mail_template_09_team_member_welcome.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * @return bool true if mail accepted for delivery
 */
function sendTeamMemberWelcomeEmail(
    string $team_member_email,
    string $team_member_name,
    string $team_member_password
): bool {
    if (!filter_var($team_member_email, FILTER_VALIDATE_EMAIL)) {
        error_log('sendTeamMemberWelcomeEmail: invalid team member email');
        return false;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'www.miniwebsite.in';
    $loginUrl = $scheme . '://' . $host . '/login/team.php';

    $tpl = getMailTemplate09TeamMemberWelcome(
        trim($team_member_name) !== '' ? $team_member_name : 'Team Member',
        $team_member_email,
        $team_member_password,
        $loginUrl
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
            $mail->addAddress($team_member_email, $team_member_name);
            $mail->addReplyTo(SUPPORT_EMAIL, DEFAULT_FROM_NAME);
            $mail->isHTML(true);
            $mail->Subject = $tpl['subject'];
            $mail->Body    = $tpl['html'];
            $mail->AltBody = $tpl['text'];
            $mail->send();
            error_log('MAIL 09 team welcome sent to: ' . $team_member_email);
            return true;
        }
    } catch (Exception $e) {
        error_log('MAIL 09 PHPMailer: ' . $e->getMessage());
    }

    $headers = 'From: ' . DEFAULT_FROM_NAME . ' <' . DEFAULT_FROM_EMAIL . ">\r\n";
    $headers .= 'Reply-To: ' . SUPPORT_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $ok = @mail($team_member_email, $tpl['subject'], $tpl['html'], $headers);
    if ($ok) {
        error_log('MAIL 09 team welcome sent via mail() to: ' . $team_member_email);
    }
    return (bool) $ok;
}
