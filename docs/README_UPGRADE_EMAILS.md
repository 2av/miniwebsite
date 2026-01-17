# Upgrade Email Templates

This folder contains email templates for handling various upgrade scenarios in the MiniWebsite.in system.

## Files Overview

### 1. `upgrade_plan_details.php`
Contains basic upgrade email templates with payment details.

### 2. `upgrade_email_templates.php`
Comprehensive templates for all upgrade scenarios including:
- Basic Free to Standard Plan
- Standard to Premium Plan  
- Basic Free to Creator Plan (Confirmation)
- Generic upgrade templates
- Upgrade with remaining amount

### 3. `send_upgrade_email.php`
Helper functions to send upgrade emails with different configurations.

## Usage Examples

### Basic Upgrade with Payment Details
```php
require_once('common/mailtemplate/send_upgrade_email.php');

// Send upgrade plan details email
$result = sendUpgradeEmail(
    'John Doe', 
    'john@example.com', 
    'plan_details', 
    [
        'current_plan' => 'Basic Free Plan',
        'upgrade_plan' => 'Standard Plan',
        'amount' => 5000,
        'gst_amount' => 900,
        'total_amount' => 5900
    ]
);
```

### Upgrade Confirmation
```php
// Send upgrade confirmation email
$result = sendUpgradeEmail(
    'John Doe', 
    'john@example.com', 
    'confirmation', 
    [
        'current_plan' => 'Basic Free Plan',
        'upgrade_plan' => 'Creator Plan'
    ]
);
```

### Upgrade with Remaining Amount
```php
// Send upgrade with remaining amount email
$result = sendUpgradeEmail(
    'John Doe', 
    'john@example.com', 
    'remaining_amount', 
    [
        'current_plan' => 'Standard Plan',
        'upgrade_plan' => 'Premium Plan',
        'remaining_amount' => 2950
    ]
);
```

### Specific Upgrade Scenarios
```php
// Send specific upgrade email based on plan types
$result = sendUpgradeEmail(
    'John Doe', 
    'john@example.com', 
    'specific', 
    [
        'current_plan' => 'Basic Free Plan',
        'upgrade_plan' => 'Standard Plan'
    ]
);
```

## Email Types

### 1. Plan Details Email
- Used when user needs to pay for upgrade
- Includes payment amount and GST details
- Contains payment link

### 2. Confirmation Email
- Used when upgrade is completed automatically
- No payment required
- Confirms the upgrade completion

### 3. Remaining Amount Email
- Used for partial payments or upgrade scenarios
- Shows remaining amount to be paid
- Includes payment link

## Template Variables

- `$user_name` - User's name
- `$user_email` - User's email address
- `$current_plan` - Current plan name
- `$upgrade_plan` - Target plan name
- `$amount` - Base amount (before GST)
- `$gst_amount` - GST amount
- `$total_amount` - Total amount including GST
- `$remaining_amount` - Remaining amount to be paid

## Integration

These templates are designed to work with the existing email system in `email_config.php`. Make sure to include the email configuration file when using these templates.

## Customization

You can modify the email templates by editing the respective PHP files. All templates follow a consistent structure and can be easily customized for different branding or messaging requirements.
