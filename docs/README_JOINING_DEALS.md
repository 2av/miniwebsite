# Joining Deals Email Templates

This folder contains email templates for different joining deal types in the MiniWebsite system.

## Template Files

### 1. `joining_deal_creator.php`
- **Deal Type**: CREATOR
- **Purpose**: Creator Collaboration Partnership
- **Function**: `getCreatorJoiningDealEmail($user_name, $user_email)`
- **Subject**: "MiniWebsite.in – Creator Collaboration Partnership"

### 2. `joining_deal_basic_free.php`
- **Deal Type**: BASIC_FREE
- **Purpose**: Digital Marketing Partner
- **Function**: `getBasicFreeJoiningDealEmail($user_name, $user_email)`
- **Subject**: "MiniWebsite.in – Digital Marketing Partner"

### 3. `joining_deal_standard.php`
- **Deal Type**: STANDARD
- **Purpose**: Franchise Distributor Partner (Standard Plan)
- **Function**: `getStandardJoiningDealEmail($user_name, $user_email)`
- **Subject**: "MiniWebsite.in – Franchise Distributor Partner"
- **Features**: Payment instructions for ₹5,900/-

### 4. `joining_deal_premium.php`
- **Deal Type**: PREMIUM
- **Purpose**: Franchise Distributor Partner (Premium Plan)
- **Function**: `getPremiumJoiningDealEmail($user_name, $user_email)`
- **Subject**: "MiniWebsite.in – Franchise Distributor Partner"
- **Features**: Payment instructions for ₹8,850/-

## Master Template Loader

### `joining_deal_templates.php`
Centralized template management with helper functions:

- `getJoiningDealEmailTemplate($joining_deal, $user_name, $user_email)` - Load template by deal type
- `getAvailableJoiningDealTypes()` - Get all available deal types
- `isValidJoiningDealType($joining_deal)` - Validate deal type

## Usage

```php
// Load master template loader
require_once('../common/mailtemplate/joining_deal_templates.php');

// Get email template
$email_data = getJoiningDealEmailTemplate('CREATOR', 'John Doe', 'john@example.com');

if($email_data) {
    $subject = $email_data['subject'];
    $message = $email_data['message'];
    // Send email...
}
```

## Template Structure

Each template function returns an array with:
- `subject` - Email subject line
- `message` - Email body content

## Adding New Templates

1. Create new template file: `joining_deal_[type].php`
2. Add function: `get[Type]JoiningDealEmail($user_name, $user_email)`
3. Update `joining_deal_templates.php` switch statement
4. Test the new template

## Email Content Features

- **Personalized greetings** with user name
- **Professional formatting** with proper line breaks
- **Clear instructions** for paid plans
- **Contact information** for support
- **Brand consistency** with MiniWebsite.in branding
