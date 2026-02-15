# WordPress Auto-Deployment

This guide explains how to automatically deploy the latest WordPress installation on your domains using the Liberu Control Panel.

## Features

- **One-Click Installation**: Deploy the latest WordPress version with a single click
- **Automatic Updates**: Keep WordPress up-to-date with automatic version checks and updates
- **Database Integration**: Automatically configure WordPress with your database settings
- **Multi-PHP Support**: Choose from PHP versions 8.1, 8.2, 8.3, or 8.4
- **WP-CLI Support**: Automatic installation via WP-CLI when available
- **Secure Configuration**: Automatically generate secure authentication keys and salts

## Prerequisites

Before deploying WordPress, ensure you have:

1. A domain configured in the control panel
2. A MySQL database created for WordPress
3. A server with SSH access configured
4. Sufficient disk space for WordPress installation

## Installing WordPress

### Via Web Interface

1. Navigate to **Applications** → **WordPress** in the control panel
2. Click **Create** to start a new WordPress installation
3. Fill in the installation form:

   **Domain & Database:**
   - Select the domain where WordPress will be installed
   - Choose an existing database for WordPress

   **WordPress Configuration:**
   - **Site Title**: The name of your WordPress site
   - **Site URL**: Full URL including http:// or https://
   - **Installation Path**: Path relative to domain root (default: `/public_html`)
   - **PHP Version**: Select PHP version (8.1-8.4)

   **Administrator Account:**
   - **Admin Username**: WordPress admin username
   - **Admin Email**: Administrator email address
   - **Admin Password**: Strong password (min 8 characters)

4. Click **Create** to save the configuration
5. Click the **Install** button to start the installation process
6. Monitor the installation progress in the **Status** column
7. View installation logs by clicking **View Logs**

### Installation Process

The system performs the following steps automatically:

1. **Download WordPress**: Fetches the latest WordPress version from wordpress.org
2. **Extract Files**: Extracts WordPress files to the specified installation path
3. **Configure wp-config.php**: Creates configuration file with database settings and security keys
4. **Set Permissions**: Applies correct file permissions (755 for directories, 775 for wp-content)
5. **Install via WP-CLI**: If available, completes the installation automatically
6. **Create Admin User**: Sets up the administrator account

### Installation Status

WordPress installations can have the following statuses:

- **Pending**: Configuration saved, waiting for installation
- **Installing**: Installation in progress
- **Installed**: Successfully installed and ready to use
- **Failed**: Installation failed (check logs for details)
- **Updating**: WordPress core update in progress

## Managing WordPress

### Updating WordPress

To update WordPress to the latest version:

1. Go to **Applications** → **WordPress**
2. Find your WordPress installation
3. Click the **Update** button
4. Confirm the update action
5. Monitor the update progress

The system will:
- Check for the latest WordPress version
- Download and install updates via WP-CLI
- Update the version number in the control panel
- Log all update activities

### Viewing Logs

To troubleshoot issues:

1. Find your WordPress installation in the list
2. Click **View Logs**
3. Review the installation or update log
4. Look for error messages or warnings

### Removing WordPress

To remove a WordPress installation:

1. Go to **Applications** → **WordPress**
2. Find your WordPress installation
3. Click the **Delete** button
4. Confirm the deletion

**Note**: This only removes the database record. Files on the server must be removed manually via FTP/SSH or the File Manager.

## Configuration Details

### wp-config.php

The system automatically generates `wp-config.php` with:

- **Database Settings**: Pulled from your database configuration
- **Authentication Keys**: Randomly generated 64-character keys
- **Site URLs**: Configured with WP_HOME and WP_SITEURL
- **Security**: Debug mode disabled for production
- **Character Set**: UTF-8 with utf8mb4 collation

### File Structure

WordPress is installed in the following structure:

```
/var/www/{domain_name}/{install_path}/
├── wp-admin/
├── wp-content/
│   ├── plugins/
│   ├── themes/
│   └── uploads/
├── wp-includes/
├── wp-config.php
└── index.php
```

### Permissions

The system sets the following permissions:

- Directories: `755` (rwxr-xr-x)
- wp-content: `775` (rwxrwxr-x)
- Files: Inherited from parent directory

## WP-CLI Integration

If WP-CLI is installed on your server, the system will:

1. Complete the WordPress installation automatically
2. Create the admin user
3. Configure site settings
4. Enable automatic updates

To install WP-CLI on your server:

```bash
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp
```

## Troubleshooting

### Installation Fails

**Check the logs** for specific error messages:
- Database connection issues: Verify database credentials
- Permission errors: Ensure web server has write access
- Download errors: Check internet connectivity

### Cannot Access WordPress

1. Verify the site URL matches your domain
2. Check NGINX/Apache configuration
3. Ensure DNS is properly configured
4. Verify SSL certificates if using HTTPS

### WP-CLI Not Available

If WP-CLI is not available:
1. The installation will still complete
2. You'll need to finish setup via web browser
3. Navigate to `http://yourdomain.com/wp-admin/install.php`
4. Complete the installation wizard manually

## API Integration

For programmatic WordPress deployments, use the REST API:

```bash
# Create WordPress application
curl -X POST https://your-control-panel.com/api/wordpress \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "domain_id": 1,
    "database_id": 2,
    "site_title": "My Blog",
    "site_url": "https://example.com",
    "admin_username": "admin",
    "admin_email": "admin@example.com",
    "admin_password": "SecurePassword123",
    "php_version": "8.2"
  }'
```

## Best Practices

1. **Use Strong Passwords**: Always use complex passwords for admin accounts
2. **Regular Backups**: Enable automatic backups for your WordPress installation
3. **Keep Updated**: Regularly update WordPress, themes, and plugins
4. **Monitor Logs**: Check installation logs for warnings or errors
5. **SSL Certificates**: Always use HTTPS for WordPress sites
6. **File Permissions**: Don't modify permissions unless necessary

## Security Considerations

- Admin passwords are hashed and stored securely
- Authentication keys are randomly generated
- Database credentials are encrypted
- SSH connections use key-based authentication
- File permissions follow WordPress security best practices

## Support

For issues or questions:
- Check the installation logs for error details
- Review this documentation
- Contact support via GitHub issues
- Visit https://liberu.co.uk for professional support
