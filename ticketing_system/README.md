# Professional Ticketing System

A comprehensive PHP-based ticketing system with approval workflow, user management, and reporting capabilities.

## Features

### Core Functionality
- **Ticket Management**: Create, view, update, and track tickets
- **Approval Workflow**: Department-based approval system
- **User Management**: Role-based access control (Admin, Approver, User)
- **Category Management**: Organize tickets by categories and departments
- **Reporting**: Detailed analytics and ticket closing time reports

### User Roles
1. **Admin**: Full system access, user management, reports
2. **Approver**: Can approve/reject tickets for assigned categories
3. **User**: Can create and view their own tickets

### Key Features
- **Dashboard**: Overview of ticket statistics and recent activity
- **Approval System**: Category-based approver assignments
- **Ticket Status Tracking**: Pending, Approved, Rejected, Cancelled, Closed
- **Priority Levels**: Low, Medium, High, Urgent
- **Comment System**: Track ticket history and communications
- **Password Management**: Forgot/reset password functionality
- **Responsive Design**: Works on desktop and mobile devices

## Installation

### Prerequisites
- XAMPP (Apache, PHP 7.4+, MariaDB/MySQL)
- Web browser

### Setup Instructions

1. **Install XAMPP**
   - Download and install XAMPP from https://www.apachefriends.org/
   - Start Apache and MySQL services

2. **Database Setup**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `ticketing_system`
   - Import the `database.sql` file to create tables and sample data

3. **File Setup**
   - Copy the `ticketing_system` folder to your XAMPP `htdocs` directory
   - Ensure all files have proper read/write permissions

4. **Configuration**
   - Open `config.php` and verify database settings:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'ticketing_system');
     ```

5. **Access the System**
   - Open your web browser
   - Navigate to `http://localhost/ticketing_system/`
   - Login with default admin credentials:
     - Username: `admin`
     - Password: `admin123`

## File Structure

```
ticketing_system/
├── config.php                     # Database configuration and common functions
├── database.sql                   # Database schema and sample data
├── style.css                      # Main stylesheet
├── index.php                      # Dashboard/homepage
├── login.php                      # User authentication
├── logout.php                     # Session termination
├── create_ticket.php              # Ticket creation form
├── view_ticket.php                # Ticket details and comments
├── approvals.php                  # Approval interface for approvers
├── process_ticket.php             # Ticket status changes (cancel, close, reopen)
├── assign_ticket.php              # Ticket assignment (admin only)
├── manage_users.php               # User management (admin only)
├── manage_categories.php          # Category management (admin only)
├── manage_category_approvers.php  # Approver assignments (admin only)
├── report.php                     # Analytics and reports (admin only)
├── profile.php                    # User profile management
├── change_password.php            # Password change form
├── forgot_password.php            # Password reset request
├── reset_password.php             # Password reset form
├── get_approvers.php              # AJAX endpoint for approver data
├── includes/
│   └── header.php                 # Common header navigation
└── README.md                      # This documentation
```

## Usage Guide

### For Users
1. **Creating Tickets**
   - Click "Create Ticket" from dashboard
   - Fill in title, category, priority, and description
   - Submit for approval

2. **Tracking Tickets**
   - View ticket status on dashboard
   - Click ticket number to see details
   - Add comments to communicate with approvers

### For Approvers
1. **Reviewing Tickets**
   - Access "Approvals" section
   - Review pending tickets for your assigned categories
   - Approve or reject with optional comments

### For Administrators
1. **User Management**
   - Add new users with appropriate roles
   - Activate/deactivate user accounts
   - Reset user passwords

2. **Category Management**
   - Create categories for different departments
   - Assign approvers to categories
   - Manage category status

3. **Reports**
   - View ticket closing time analytics
   - Generate reports by date range and category
   - Monitor system performance metrics

## Database Schema

### Main Tables
- **users**: User accounts and profiles
- **categories**: Ticket categories and departments
- **tickets**: Main ticket data
- **category_approvers**: Approver assignments
- **ticket_comments**: Ticket history and comments
- **password_reset_tokens**: Password reset functionality

## Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection prevention with prepared statements
- Session-based authentication
- Role-based access control
- CSRF protection through form validation
- Input sanitization and validation

## Customization

### Adding New Ticket Status
1. Update the status ENUM in the `tickets` table
2. Add corresponding CSS classes in `style.css`
3. Update status handling in relevant PHP files

### Modifying User Roles
1. Update the role ENUM in the `users` table
2. Modify role checking functions in `config.php`
3. Update navigation and access controls

### Styling Changes
- Modify `style.css` for visual customizations
- Update color schemes and layout as needed
- Ensure responsive design is maintained

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify MySQL service is running
   - Check database credentials in `config.php`
   - Ensure database exists and is accessible

2. **Login Issues**
   - Verify default admin user exists in database
   - Check password hash in users table
   - Clear browser cache and cookies

3. **Permission Errors**
   - Ensure proper file permissions on server
   - Check Apache configuration
   - Verify PHP error reporting settings

### Error Logging
- Enable PHP error reporting for debugging
- Check Apache error logs for server issues
- Monitor database query errors

## Support and Maintenance

### Regular Maintenance
- Backup database regularly
- Monitor system performance
- Update PHP and database versions as needed
- Review and clean old password reset tokens

### Performance Optimization
- Add database indexes for frequently queried columns
- Implement caching for dashboard statistics
- Optimize large report queries
- Consider pagination for large ticket lists

## License

This ticketing system is provided as-is for educational and commercial use. Modify and distribute according to your needs.

## Version History

- **v1.0**: Initial release with core ticketing functionality
- Features: User management, ticket creation, approval workflow, reporting

---

For technical support or feature requests, please contact your system administrator.
