<?php
/**
 * Email Configuration File
 * This file contains the SMTP settings for sending emails
 */

// SMTP Configuration
define('SMTP_HOST', 'p004.bom1.mysecurecloudhost.com');  // Your SMTP server address
define('SMTP_PORT', 465);                   // SMTP port 465 for SSL
define('SMTP_SECURE', 'ssl');               // Using 'ssl' as specified
define('SMTP_AUTH', true);                  // Whether to use SMTP authentication
define('SMTP_USERNAME', 'support@miniwebsite.in');  // Your SMTP username/email
define('SMTP_PASSWORD', 'Kirovahelp@2025');      // Your SMTP password - REPLACE THIS!

// Default Sender Information
define('DEFAULT_FROM_EMAIL', 'support@miniwebsite.in');
define('DEFAULT_FROM_NAME', 'MiniWebsite Support');

// Additional Email Addresses
define('SUPPORT_EMAIL', 'support@miniwebsite.in');
define('ALL_EMAILS', 'allmails@miniwebsite.in');
?>
