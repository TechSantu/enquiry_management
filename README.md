# Enquiry Management

A comprehensive WordPress contact form plugin with task management, admin dashboard, email notifications, spam protection, and bulk import functionality.

## Description

Enquiry Management is a powerful WordPress plugin that transforms your contact form into a complete enquiry management system. It allows you to manage contact submissions as tasks, assign them to team members, track status changes, and organize enquiries efficiently.

## Features

### Core Features
- **Contact Form Shortcode**: Simple `[contact_task_form]` shortcode to display the form anywhere
- **AJAX Form Submission**: Smooth, no-page-reload form submissions
- **Email Notifications**: Automatic email notifications to admin and users upon submission
- **Task Management**: Convert contact submissions into manageable tasks
- **User Assignment**: Assign enquiries to specific team members
- **Status Tracking**: Track enquiry status with custom statuses and colors
- **Change Log**: Complete audit trail of all status changes and comments
- **Internal Comments**: Add internal notes and comments to enquiries

### Spam Protection
- **Honeypot Field**: Hidden field that catches bots automatically
- **Google reCAPTCHA v2**: Checkbox-based CAPTCHA verification
- **Google reCAPTCHA v3**: Invisible, score-based CAPTCHA
- **Admin Controls**: Enable/disable and configure all spam protection from admin panel

### Dynamic Management
- **Custom Statuses**: Create, edit, and delete custom enquiry statuses with colors
- **Dynamic Task Fields**: Add custom fields to task details section
- **Protected Fields**: Form fields are protected from deletion
- **Field Usage Tracking**: See which fields are in use before deletion

### Bulk Operations
- **CSV Import**: Bulk import enquiries from CSV files
- **Column Mapping**: Map CSV columns to existing or new custom fields
- **Preview Before Import**: Review mapped data before final import
- **Auto Field Creation**: Create new custom fields directly from import mapping
- **Delimiter Detection**: Automatically detects comma, semicolon, or tab delimiters

### User Roles
- **Administrator**: Full access to all features
- **Task Manager**: Limited access to manage enquiries only
- **Role-Based Menus**: Task Managers see only relevant admin menus

## Installation

1. Upload the plugin files to `/wp-content/plugins/contact-task-form/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create the necessary database tables and user roles

## Configuration

### Basic Setup

1. **Add the Form to Your Site**
   - Use the shortcode `[contact_task_form]` in any page, post, or widget
   - The form will automatically include all configured fields

2. **Configure Spam Protection**
   - Go to **Enquiries → Spam Protection**
   - Enable Honeypot (recommended, enabled by default)
   - Enable CAPTCHA and choose v2 or v3
   - For reCAPTCHA, get your keys from [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin)
   - For v3, set minimum score (recommended: 0.5)

### Form Fields

The contact form includes the following fields:
- **Name** (required) *
- **Company Name** (optional)
- **Designation** (optional)
- **Phone Number** (required) *
- **Email Address** (required) *
- **Nature of Trustee** (optional)
- **Enquiry/Message** (required) *

### Custom Statuses

1. Go to **Enquiries → Manage Statuses**
2. Add new statuses with custom names and colors
3. Reorder statuses by drag-and-drop
4. View usage count before deleting
5. Migrate enquiries when deleting statuses

### Custom Task Fields

1. Go to **Enquiries → Manage Additional Task Fields**
2. Add custom fields with labels, types, and meta keys
3. Field types supported: Text, Email, Textarea, Number, URL, Phone
4. Fields appear in "Additional Task Details" meta box
5. Protected fields (used in form) cannot be deleted

## Usage

### For Administrators

#### Managing Enquiries
- View all enquiries in **Enquiries** menu
- Filter by status using the dropdown
- Edit individual enquiries to update details
- Assign enquiries to team members
- Change status and add internal comments
- View complete change log for each enquiry

#### Bulk Import
1. Go to **Enquiries → Bulk Import**
2. **Step 1**: Upload CSV file (max size based on PHP settings)
3. **Step 2**: Map CSV columns to fields
   - Map to existing fields or create new ones
   - Required fields: Name, Email, Phone, Message
   - Optional fields: Company Name, Designation, Nature of Trustee
4. **Step 3**: Preview mapped data
5. Click "Import All Rows" to complete import

#### Spam Protection Settings
- **Enquiries → Spam Protection**
- Toggle Honeypot on/off
- Enable/disable CAPTCHA
- Choose CAPTCHA type (v2 or v3)
- Configure reCAPTCHA keys
- Set v3 minimum score threshold

### For Task Managers

Task Managers have limited access:
- Can view and edit enquiries
- Can change status and add comments
- Cannot assign tasks to others
- Cannot access settings or bulk import
- Cannot manage statuses or custom fields

## Form Fields Details

### Protected Fields (Used in Contact Form)
These fields are automatically created and cannot be deleted:
- Name (required)
- Company Name (optional)
- Designation (optional)
- Contact Email (required)
- Phone Number (required)
- Nature of Trustee (optional)
- Message/Enquiry (required)

### Custom Fields
Additional fields can be added through:
- **Enquiries → Manage Additional Task Fields**
- Bulk import mapping interface

## Email Notifications

### Admin Email
Sent to site administrator when form is submitted, includes:
- All form field data
- Direct link to view enquiry in dashboard
- Submission timestamp

### User Email
Sent to the submitter confirming receipt, includes:
- Thank you message
- Link to contact page

## Spam Protection

### Honeypot
- Hidden field that should remain empty
- Bots often fill it, allowing automatic spam detection
- No user interaction required
- Enabled by default

### Google reCAPTCHA v2
- Checkbox-based verification
- User clicks "I'm not a robot"
- Requires separate site/secret keys
- Visible to users

### Google reCAPTCHA v3
- Invisible, runs automatically
- Score-based (0.0 = bot, 1.0 = human)
- Configurable minimum score
- Better user experience
- Requires separate site/secret keys

## Bulk Import

### CSV Format Requirements
- First row must contain column headers
- Required columns: **Name**, **Email**, **Phone**, **Message**
- Optional columns: **Company Name**, Designation, Nature of Trustee
- Additional columns can be mapped to custom fields

### Import Process
1. Upload CSV file (supports comma, semicolon, or tab delimiters)
2. Map each CSV column to a field:
   - Select existing field from dropdown
   - Or create new field by selecting "Create New Field"
3. Preview first 10 rows of mapped data
4. Review field mappings in debug section
5. Import all rows or cancel

### Import Features
- Auto-detects CSV delimiter
- Validates required fields (Name, Email, Phone, Message)
- Validates field lengths and formats (phone: 7-15 digits, email format, etc.)
- Skips invalid rows and reports specific errors
- Creates new custom fields on-the-fly
- Sets default status for imported enquiries
- Creates audit log entries for imports
- Detailed error reporting for troubleshooting

## Status Management

### Creating Statuses
- Go to **Enquiries → Manage Statuses**
- Enter status name and choose color
- Status appears immediately in dropdowns

### Editing Statuses
- Click "Edit" on any status
- Update name, color, or order
- Changes apply to all existing enquiries

### Deleting Statuses
- View usage count before deletion
- Migrate enquiries to another status
- Cannot delete status if enquiries are using it

## Custom Task Fields

### Adding Fields
1. Go to **Enquiries → Manage Additional Task Fields**
2. Enter field label
3. Select field type
4. Optionally specify meta key (auto-generated if empty)
5. Field appears in "Additional Task Details" meta box

### Field Types
- **Text**: Single-line text input
- **Email**: Email validation
- **Textarea**: Multi-line text
- **Number**: Numeric input
- **URL**: URL validation
- **Phone**: Phone number format

### Field Protection
- Fields used in contact form cannot be deleted
- Fields with existing data show usage count
- Fields with data cannot be deleted (migrate data first)

## Technical Details

### Database
- Uses WordPress Custom Post Type: `contact_enquiry`
- Stores field data as post meta
- Change log stored as serialized post meta
- Settings stored in WordPress options table

### Hooks and Filters
The plugin uses standard WordPress hooks:
- `wp_ajax_ctf_submit_form` - Form submission handler
- `add_meta_boxes` - Admin meta boxes
- `admin_menu` - Admin menu registration
- Various WordPress filters for customization

### File Structure
```
contact-task-form/
├── contact-task-form.php (Main plugin file)
├── includes/
│   ├── form-handler.php (Shortcode and form rendering)
│   ├── ajax-handler.php (AJAX form processing)
│   ├── email-functions.php (Email notifications)
│   ├── task-system.php (CPT and meta boxes)
│   ├── admin-page.php (List table customization)
│   ├── status-management.php (Status CRUD operations)
│   ├── status-admin-page.php (Status management UI)
│   ├── task-details-management.php (Field CRUD operations)
│   ├── task-fields-admin-page.php (Field management UI)
│   ├── bulk-import.php (CSV import functionality)
│   └── spam-protection-settings.php (Spam protection admin)
├── assets/
│   ├── style.css (Frontend styles)
│   ├── script.js (Frontend JavaScript)
│   └── admin.css (Admin styles)
└── README.md (This file)
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- jQuery (included with WordPress)

## Security

- All form inputs are sanitized and validated
- Nonce verification on all form submissions
- Capability checks for all admin functions
- SQL injection protection via WordPress APIs
- XSS protection via output escaping
- CSRF protection via nonces

## Rate Limiting

- IP-based rate limiting: 1 submission per 30 seconds
- Prevents spam and abuse
- Automatic cleanup of rate limit data

## Troubleshooting

### Form Not Submitting
- Check browser console for JavaScript errors
- Verify AJAX URL is correct
- Check nonce is valid
- Ensure CAPTCHA is completed (if enabled)

### Import Not Working
- Check CSV file format (headers in first row)
- Verify required columns (Name, Email, Phone, Message) are mapped
- Check PHP upload limits in settings
- Review error messages in import preview
- Ensure phone numbers have 7-15 digits
- Verify email addresses are valid format
- Check field length requirements (Name: 2-100, Company Name: 2-200 if provided, Message: max 2000)

### CAPTCHA Issues
- Verify site key and secret key are correct
- For v3, check minimum score setting
- Ensure reCAPTCHA script is loading (check browser console)
- Verify keys match the selected version (v2 vs v3)

### Email Not Sending
- Check WordPress email configuration
- Verify admin email in WordPress settings
- Check spam folder
- Test with WordPress mail debugging

## Support

For issues, feature requests, or questions:
1. Check this README first
2. Review WordPress error logs
3. Enable WordPress debug mode for detailed errors
4. Contact plugin support

## Changelog

### Version 2.0
- Added spam protection (Honeypot, reCAPTCHA v2/v3)
- Added bulk import functionality
- Added dynamic status management
- Added custom task fields management
- Improved admin interface
- Enhanced email notifications
- Added change log tracking
- Added internal comments system

## License

This plugin is proprietary software. All rights reserved.

## Author

Santu Maity

---

**Note**: This plugin requires proper WordPress installation and configuration. Ensure your server meets the minimum requirements before installation.

