<?php
/**
 * Shared franchisee email templates.
 * Keep franchisee-related email copy in one place.
 */

if (!function_exists('buildFranchiseeWelcomeEmail')) {
    /**
     * Build franchisee welcome email.
     *
     * @param string $userName
     * @param string $userEmail
     * @param string $userPassword
     * @param array<string,mixed> $options
     * @return array{subject:string,message:string}
     */
    function buildFranchiseeWelcomeEmail($userName, $userEmail, $userPassword, array $options = [])
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'miniwebsite.in';
        $subject = "Welcome to MiniWebsite.in – Your Franchise Account is Ready!";
        $includePaymentProcessedLine = !empty($options['include_payment_processed_line']);
        $includePaymentStep = !empty($options['include_payment_step']);
        $compactLoginBlock = !empty($options['compact_login_block']);

        $introLine = $includePaymentProcessedLine
            ? "We are excited to have you on board! Your franchise account has been successfully created and your payment has been processed. You can now log in using your email and password at the link below:"
            : "We are excited to have you on board! Your franchise account has been successfully created. You can now log in using your email and password at the link below:";

        $message = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <p style="color: #333; font-size: 16px; line-height: 1.6;">Hi <strong>' . htmlspecialchars($userName) . '</strong>,</p>
            
            <p style="color: #333; font-size: 16px; line-height: 1.6;">Thank you for registering as a franchise with MiniWebsite.in.</p>
            
            <p style="color: #333; font-size: 16px; line-height: 1.6;">' . $introLine . '</p>';

        if ($compactLoginBlock) {
            $message .= '
            <p style="color: #333; font-size: 16px; line-height: 1.6;">👉 <a href="https://' . $host . '/panel/franchisee-login/login.php" style="color: #007bff; text-decoration: none;">(Franchisee Login details)</a></p>
            
            <br><br>';
        } else {
            $message .= '
            <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h3 style="color: #333; font-size: 18px; margin-top: 0; margin-bottom: 15px;">🔐 Your Login Details:</h3>
                <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 10px 0;"><strong>Email ID:</strong> ' . htmlspecialchars($userEmail) . '</p>
                <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 10px 0;"><strong>Password:</strong> ' . htmlspecialchars($userPassword) . '</p>
                <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 10px 0;">👉 <a href="https://' . $host . '/panel/franchisee-login/login.php" style="color: #007bff; text-decoration: none;">Click here to login</a></p>
            </div>
            
            <br>';
        }

        $message .= '
            <p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>Follow these simple steps to activate your franchise:</strong></p>';

        if ($includePaymentStep) {
            $message .= '
            <p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>1. Pay the One-Time Franchise Fee (Non-Refundable)</strong><br>
            Amount: ₹30,000 + 18% GST = ₹35,400<br>
            <a href="https://' . $host . '/franchise_agreement.php?email=' . urlencode($userEmail) . '" style="color: #007bff; text-decoration: none;">(Click to Pay)</a></p>
            
            <p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>2. After payment, complete your document Verification from your Dashboard.</strong></p>
            
            <p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>3. After the documents get verified, you can access your Marketing Kit and Onboarding Material from your dashboard only.</strong></p>';
        } else {
            $message .= '
            <p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>1. Complete your document Verification from your Dashboard.</strong></p>
            
            <p style="color: #333; font-size: 16px; line-height: 1.6;"><strong>2. After the documents get verified, you can access your Marketing Kit and Onboarding Material from your dashboard only.</strong></p>';
        }

        $message .= '
            <br>
            
            <p style="color: #333; font-size: 16px; line-height: 1.6;">That\'s it! Once these steps are completed, you are officially part of the MiniWebsite.in franchise network. You can begin building your business and start earning right away.</p>
            
            <p style="color: #333; font-size: 16px; line-height: 1.6;">If you have any questions or need assistance, feel free to reach out to our support team.</p>
            
            <br>
            
            <p style="color: #333; font-size: 16px; line-height: 1.6;">Best regards,<br>
            Team MiniWebsite.in<br>
            <a href="https://www.miniwebsite.in">www.miniwebsite.in</a></p>
        </div>';

        return [
            'subject' => $subject,
            'message' => $message,
        ];
    }
}

if (!function_exists('buildFranchiseeVerificationEmail')) {
    /**
     * Build franchisee document verification email.
     *
     * @param string $franchiseeName
     * @param string $action approve|reject
     * @param string $remarks
     * @return array{subject:string,message:string}
     */
    function buildFranchiseeVerificationEmail($franchiseeName, $action, $remarks = '')
    {
        $subject = "MiniWebsite.in – Document verification";
        $name = !empty($franchiseeName) ? $franchiseeName : 'Franchisee';

        if ($action === 'approve') {
            $message = "Hi " . $name . ",<br><br>";
            $message .= "Thank you for registering as a franchise with MiniWebsite.in.<br><br>";
            $message .= "Congratulations! The verification documents are approved by Miniwebsite Team.<br>";
            $message .= "You can access your Franchise Kit from your Dashboard and start your business immediately.<br><br>";
            $message .= "If you have any questions or need assistance, feel free to reach out to our support team.<br><br>";
            $message .= "Best regards,<br>";
            $message .= "Team MiniWebsite.in<br>";
            $message .= "<a href=\"https://www.miniwebsite.in\">www.miniwebsite.in</a>";
        } else {
            $message = "Hi " . $name . ",<br><br>";
            $message .= "Thank you for registering as a franchise with MiniWebsite.in.<br><br>";
            $message .= "The documents uploaded for verification is not approved by Miniwebsite Team.<br><br>";
            $message .= "Please check the reason:<br>";
            $message .= (!empty($remarks) ? $remarks : "Please upload clear and valid documents.") . "<br><br>";
            $message .= "If you have any questions or need assistance, feel free to reach out to our support team.<br><br>";
            $message .= "Best regards,<br>";
            $message .= "Team MiniWebsite.in<br>";
            $message .= "<a href=\"https://www.miniwebsite.in\">www.miniwebsite.in</a>";
        }

        return [
            'subject' => $subject,
            'message' => $message,
        ];
    }
}
?>
