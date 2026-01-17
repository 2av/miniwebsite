<?php
/**
 * Customer Account Created Email Template
 * This template is used when a new customer account is created by a franchisee
 */

function getCustomerAccountCreatedTemplate($customer_name, $customer_email, $customer_password, $franchisee_name, $login_url) {
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Welcome to MiniWebsite - Your Account is Ready!</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f4f4f4;
            }
            .email-container {
                background-color: #ffffff;
                border-radius: 10px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px 20px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: bold;
            }
            .header p {
                margin: 10px 0 0 0;
                font-size: 16px;
                opacity: 0.9;
            }
            .content {
                padding: 30px 20px;
            }
            .welcome-message {
                font-size: 18px;
                color: #2c3e50;
                margin-bottom: 20px;
            }
            .account-details {
                background-color: #f8f9fa;
                border-left: 4px solid #667eea;
                padding: 20px;
                margin: 20px 0;
                border-radius: 5px;
            }
            .account-details h3 {
                margin-top: 0;
                color: #2c3e50;
                font-size: 16px;
            }
            .detail-row {
                display: flex;
                justify-content: space-between;
                margin: 10px 0;
                padding: 8px 0;
                border-bottom: 1px solid #e9ecef;
            }
            .detail-row:last-child {
                border-bottom: none;
            }
            .detail-label {
                font-weight: bold;
                color: #495057;
            }
            .detail-value {
                color: #6c757d;
                font-family: monospace;
            }
            .login-button {
                display: inline-block;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 15px 30px;
                text-decoration: none;
                border-radius: 25px;
                font-weight: bold;
                margin: 20px 0;
                transition: transform 0.2s;
            }
            .login-button:hover {
                transform: translateY(-2px);
                text-decoration: none;
                color: white;
            }
            .security-note {
                background-color: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 5px;
                padding: 15px;
                margin: 20px 0;
                color: #856404;
            }
            .security-note h4 {
                margin-top: 0;
                color: #856404;
            }
            .footer {
                background-color: #f8f9fa;
                padding: 20px;
                text-align: center;
                color: #6c757d;
                font-size: 14px;
            }
            .footer a {
                color: #667eea;
                text-decoration: none;
            }
            .footer a:hover {
                text-decoration: underline;
            }
            .social-links {
                margin: 15px 0;
            }
            .social-links a {
                display: inline-block;
                margin: 0 10px;
                color: #667eea;
                text-decoration: none;
            }
            @media (max-width: 600px) {
                body {
                    padding: 10px;
                }
                .header h1 {
                    font-size: 24px;
                }
                .content {
                    padding: 20px 15px;
                }
                .detail-row {
                    flex-direction: column;
                }
                .detail-label {
                    margin-bottom: 5px;
                }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>🎉 Welcome to MiniWebsite!</h1>
                <p>Your digital business card is ready</p>
            </div>
            
            <div class="content">
                <div class="welcome-message">
                    Hello <strong>' . htmlspecialchars($customer_name) . '</strong>,
                </div>
                
                <p>Great news! Your MiniWebsite account has been successfully created by <strong>' . htmlspecialchars($franchisee_name) . '</strong>. You can now start building your professional digital presence!</p>
                
                <div class="account-details">
                    <h3>📋 Your Account Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">Email Address:</span>
                        <span class="detail-value">' . htmlspecialchars($customer_email) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Temporary Password:</span>
                        <span class="detail-value">' . htmlspecialchars($customer_password) . '</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Account Status:</span>
                        <span class="detail-value">✅ Active</span>
                    </div>
                </div>
                
                <div style="text-align: center;">
                    <a href="' . htmlspecialchars($login_url) . '" class="login-button">
                        🚀 Access Your Account
                    </a>
                </div>
                
                <div class="security-note">
                    <h4>🔒 Security Reminder</h4>
                    <p>For your security, please change your password after your first login. Keep your login credentials safe and never share them with anyone.</p>
                </div>
                
                
                <p>If you have any questions or need assistance, feel free to reach out to our support team. We\'re here to help you make the most of your MiniWebsite!</p>
                
                <p>Best regards,<br>
                <strong>The MiniWebsite Team</strong></p>
            </div>
            
            <div class="footer">
                <div class="social-links">
                    <a href="#">Website</a>
                    <a href="#">Support</a>
                    <a href="#">Help Center</a>
                </div>
                <p>© 2025 MiniWebsite. All rights reserved.</p>
                <p>This email was sent to ' . htmlspecialchars($customer_email) . ' because an account was created for you.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Get plain text version of the email
 */
function getCustomerAccountCreatedTextTemplate($customer_name, $customer_email, $customer_password, $franchisee_name, $login_url) {
    $text = "
WELCOME TO MINIWEBSITE!

Hello " . $customer_name . ",

Great news! Your MiniWebsite account has been successfully created by " . $franchisee_name . ". You can now start building your professional digital presence!

YOUR ACCOUNT DETAILS:
- Email Address: " . $customer_email . "
- Temporary Password: " . $customer_password . "
- Account Status: Active

LOGIN URL: " . $login_url . "

SECURITY REMINDER:
For your security, please change your password after your first login. Keep your login credentials safe and never share them with anyone.

WHAT YOU CAN DO NEXT:
✓ Customize your digital business card
✓ Add your business information and contact details
✓ Upload your logo and professional photos
✓ Share your digital card with clients and contacts
✓ Track engagement and analytics

If you have any questions or need assistance, feel free to reach out to our support team. We're here to help you make the most of your MiniWebsite!

Best regards,
The MiniWebsite Team

---
© 2025 MiniWebsite. All rights reserved.
This email was sent to " . $customer_email . " because an account was created for you.
";
    
    return $text;
}
?>
