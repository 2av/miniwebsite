# Admin Notification System

## Overview
The Admin Notification System provides real-time notifications for various activities in the MiniWebsite system, including account creation, payments, franchisee verifications, and more.

## Features

### 🔔 Real-time Notifications
- **Notification Bell**: Located in the top-right corner of the admin header
- **Unread Count Badge**: Shows the number of unread notifications
- **Auto-refresh**: Updates every 30 seconds automatically
- **Click to Mark Read**: Click any notification to mark it as read

### 📱 Notification Types
- **Account Creation**: New customer/franchisee accounts
- **Payment**: Payment transactions and wallet recharges
- **Verification**: Document verification status updates
- **Order**: New orders and order updates
- **Franchisee**: Franchisee-related activities
- **General**: System-wide notifications

### 🎯 Priority Levels
- **Low**: Informational notifications
- **Medium**: Standard activity notifications
- **High**: Important alerts requiring attention

### 🔍 Management Features
- **Filter by Type**: Filter notifications by category
- **Filter by Priority**: Filter by importance level
- **Filter by Status**: Show read/unread notifications
- **Search**: Search through notification content
- **Pagination**: Navigate through large numbers of notifications
- **Bulk Actions**: Mark all notifications as read

## Installation

### 1. Database Setup
Run the SQL script to create the notifications table:
```sql
-- Run admin/create_notifications_table.sql
```

### 2. File Structure
```
admin/
├── includes/
│   ├── notification_helper.php      # Core notification functions
│   └── notification_dropdown.php    # Notification bell component
├── ajax/
│   ├── get_notifications_count.php  # Get unread count
│   ├── mark_notification_read.php   # Mark single notification read
│   └── mark_all_notifications_read.php # Mark all as read
├── notifications.php                 # Full notifications page
├── header.php                       # Admin header with notification bell
└── test_notifications.php           # Test script (remove in production)
```

### 3. Integration
The notification system is automatically integrated into:
- Admin header (notification bell)
- Admin sidebar (notifications link with count)
- Account creation process
- Franchisee verification process

## Usage

### Creating Notifications
```php
require_once('includes/notification_helper.php');

createNotification(
    'account_creation',           // Type
    'New Account Created',        // Title
    'A new account was created',  // Message
    'user@example.com',          // User email
    'customer',                   // User type
    null,                         // Related ID
    'medium'                      // Priority
);
```

### Notification Types
- `account_creation` - New user accounts
- `payment` - Financial transactions
- `verification` - Document verifications
- `order` - Order management
- `franchisee` - Franchisee activities
- `general` - System notifications

### Priority Levels
- `low` - Informational
- `medium` - Standard
- `high` - Important

## API Endpoints

### Get Unread Count
```
GET /admin/ajax/get_notifications_count.php
Response: {"success": true, "unread_count": 5}
```

### Mark Single Notification Read
```
POST /admin/ajax/mark_notification_read.php
Body: {"notification_id": 123}
Response: {"success": true, "message": "Notification marked as read"}
```

### Mark All Notifications Read
```
POST /admin/ajax/mark_all_notifications_read.php
Response: {"success": true, "message": "All notifications marked as read"}
```

## Customization

### Adding New Notification Types
1. Add the new type to the `getNotificationIcon()` function in `notification_helper.php`
2. Add corresponding CSS styling in `notification_dropdown.php`
3. Update the filters in `notifications.php`

### Styling
The notification system uses Bootstrap 5 classes and custom CSS. Key classes:
- `.notification-dropdown` - Main notification container
- `.notification-item` - Individual notification items
- `.notification-icon` - Type-specific icons
- `.priority-badge` - Priority level indicators

### JavaScript Functions
- `markAllAsRead()` - Mark all notifications as read
- `updateUnreadCount()` - Update the unread count badge
- `filterNotifications()` - Filter notifications by criteria

## Testing

### Test Script
Use `test_notifications.php` to create sample notifications:
```bash
# Visit in browser
http://your-domain/admin/test_notifications.php
```

### Manual Testing
1. Create a new customer account
2. Verify franchisee documents
3. Check notification bell for new notifications
4. Click notifications to mark as read
5. Visit notifications page for full management

## Troubleshooting

### Common Issues

#### Notifications Not Appearing
- Check if the database table exists
- Verify `notification_helper.php` is included
- Check for JavaScript errors in browser console

#### Notification Bell Not Showing
- Ensure `notification_dropdown.php` is included in header
- Check CSS positioning in header styles
- Verify Bootstrap and Font Awesome are loaded

#### AJAX Errors
- Check file paths in AJAX calls
- Verify database connection in AJAX files
- Check browser network tab for failed requests

### Debug Mode
Enable error logging in `notification_helper.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Security Considerations

### Input Validation
- All user inputs are sanitized using `mysqli_real_escape_string()`
- Notification types and priorities are validated against allowed values
- User emails are validated before storage

### Access Control
- Notifications are only accessible to admin users
- AJAX endpoints include proper authentication checks
- Database queries use prepared statements where possible

### Data Privacy
- User emails are stored for notification context
- Sensitive information is not logged in notifications
- Old notifications can be deleted to maintain privacy

## Performance

### Optimization Tips
- Notifications auto-refresh every 30 seconds
- Pagination limits notifications to 20 per page
- Unread count is cached and updated efficiently
- Database indexes on frequently queried fields

### Scaling
- For high-volume systems, consider:
  - Implementing notification queuing
  - Adding database partitioning
  - Using Redis for notification caching
  - Implementing notification batching

## Future Enhancements

### Planned Features
- **Email Notifications**: Send notifications to admin email
- **Push Notifications**: Browser push notifications
- **Notification Templates**: Customizable notification formats
- **Advanced Filtering**: Date range, user-specific filters
- **Notification Export**: Export notifications to CSV/PDF
- **Mobile App**: Native mobile notification support

### Integration Opportunities
- **Slack/Discord**: Webhook integration for team notifications
- **SMS**: Text message notifications for critical alerts
- **Analytics**: Notification engagement tracking
- **A/B Testing**: Test different notification formats

## Support

For technical support or feature requests:
1. Check this documentation
2. Review the code comments
3. Test with the provided test script
4. Contact the development team

## Changelog

### Version 1.0.0
- Initial notification system implementation
- Basic notification types and priorities
- Real-time notification bell
- Full notifications management page
- AJAX endpoints for dynamic updates
- Integration with account creation and verification processes
