# Setup Instructions

## Prerequisites
- PHP 8.3.6 or higher
- Composer
- Apache 2.4.58 or higher
- MySQL 8.0.43 (optional, for future database features)
- Mandrill SMTP account credentials

## Installation Steps

1. **Install Composer Dependencies**
   ```bash
   cd /var/www/wedding.stephens.page
   composer install
   ```

2. **Configure Environment Variables**
   Create a `.env` file in the `private/` directory with the following:
   ```env
   # Database Configuration (if needed)
   DB_HOST=localhost
   DB_NAME=wedding_db
   DB_USER=wedding_user
   DB_PASS=your_password_here

   # Mandrill SMTP Configuration
   MANDRILL_SMTP_HOST=smtp.mandrillapp.com
   MANDRILL_SMTP_PORT=587
   MANDRILL_SMTP_USER=your_mandrill_username
   MANDRILL_SMTP_PASS=your_mandrill_api_key

   # Email Recipients
   RSVP_EMAIL=melissa.longua@gmail.com
   CONTACT_EMAIL=melissa.longua@gmail.com
   ```

3. **Apache Configuration**
   Ensure your Apache virtual host is configured to:
   - Point the document root to `/var/www/wedding.stephens.page/public`
   - Allow `.htaccess` overrides
   - Enable mod_rewrite
   - Enable mod_php

   Example virtual host configuration:
   ```apache
   <VirtualHost *:80>
       ServerName wedding.stephens.page
       DocumentRoot /var/www/wedding.stephens.page/public
       
       <Directory /var/www/wedding.stephens.page/public>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

4. **SSL Certificate (Optional but Recommended)**
   Use certbot to set up SSL:
   ```bash
   sudo certbot --apache -d wedding.stephens.page
   ```

5. **File Permissions**
   Ensure Apache can read files:
   ```bash
   sudo chown -R www-data:www-data /var/www/wedding.stephens.page
   sudo chmod -R 755 /var/www/wedding.stephens.page
   ```

## Testing

1. Visit `https://wedding.stephens.page` to see the home page
2. Test the RSVP form - check that emails are sent to melissa.longua@gmail.com
3. Test the contact form - verify email delivery
4. Check all navigation links work on both desktop and mobile views

## Notes

- Photos and videos are served through `assets.php` for security (they remain in the private directory)
- The countdown timer updates hourly
- Mobile navigation uses a hamburger menu
- Desktop navigation shows links below the header






