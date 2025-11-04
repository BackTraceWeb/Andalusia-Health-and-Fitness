# Security Deployment Guide
**Andalusia Health & Fitness - Security Improvements**

## Overview
This guide covers the security improvements made to the application and the steps required to deploy them safely to your Apache server.

---

## 🔒 Security Improvements Made

### 1. **Centralized Configuration System**
- ✅ Created `config/.env.php` for all sensitive credentials
- ✅ Created `config/.env.example.php` as a template (safe to commit)
- ✅ Updated all files to load from centralized config
- ✅ No more hardcoded credentials in code

### 2. **Password Security**
- ✅ Implemented bcrypt password hashing for admin accounts
- ✅ Replaced plain text password storage
- ✅ Added login rate limiting (5 attempts per 15 minutes)
- ✅ Added failed login attempt logging

### 3. **Session Security**
- ✅ Session timeout (2 hours configurable)
- ✅ HTTP-only cookies (prevents XSS cookie theft)
- ✅ Secure cookie flags (for HTTPS)
- ✅ SameSite cookie policy (prevents CSRF)
- ✅ Session regeneration on login (prevents session fixation)

### 4. **CSRF Protection**
- ✅ CSRF token generation and validation
- ✅ Helper functions for forms: `csrf_field()` and `verify_csrf()`
- ✅ Protected admin pages

### 5. **Security Headers**
- ✅ Content-Security-Policy (prevents XSS attacks)
- ✅ X-Frame-Options (prevents clickjacking)
- ✅ X-Content-Type-Options (prevents MIME sniffing)
- ✅ X-XSS-Protection (browser XSS filter)
- ✅ Referrer-Policy
- ✅ Permissions-Policy
- ✅ HSTS (when HTTPS is enabled)

### 6. **Rate Limiting**
- ✅ API endpoint rate limiting (60 requests per minute default)
- ✅ Automatic IP-based tracking
- ✅ Database-backed rate limit storage
- ✅ Protected endpoints: member-save, department-save, quickpay-lookup, complete-signup

### 7. **Error Handling**
- ✅ Error display disabled in production (configurable)
- ✅ Errors logged to files instead of displayed
- ✅ Safe error messages to users

### 8. **Authentication Improvements**
- ✅ All admin pages now require authentication
- ✅ Shared `_auth.php` helper for consistent auth checks
- ✅ Auto-redirect to login page for unauthenticated users

---

## 📋 Deployment Steps

### **STEP 1: Commit Safe Files to GitHub**

First, make sure you're in the project directory, then commit the safe files:

```bash
cd /Users/raines/Documents/github/Andalusia-Health-and-Fitness

# Review what will be committed
git status

# Add the safe files (config/.env.php is already in .gitignore)
git add .
git commit -m "Security improvements: centralized config, password hashing, CSRF protection, rate limiting, and security headers

🤖 Generated with Claude Code

Co-Authored-By: Claude <noreply@anthropic.com>"

# Push to GitHub
git push origin master
```

**IMPORTANT**: The `config/.env.php` file contains your real credentials and will NOT be pushed (it's in .gitignore). This is correct!

---

### **STEP 2: Pull Changes on Apache Server**

SSH into your Apache server and pull the changes:

```bash
# SSH into your server
ssh user@your-server.com

# Navigate to your web directory
cd /var/www/html/andalusiahealthandfitness  # Adjust path as needed

# Pull the latest changes
git pull origin master
```

---

### **STEP 3: Create Config File on Server**

On your Apache server, create the `config/.env.php` file with your real credentials:

```bash
# Make sure you're in the project directory
cd /var/www/html/andalusiahealthandfitness  # Adjust path as needed

# Copy the example file
cp config/.env.example.php config/.env.php

# Edit the config file with your credentials
nano config/.env.php
```

**Update these values in `config/.env.php`:**

1. **Database credentials** (your current values):
   ```php
   'DB_DSN'  => 'mysql:host=127.0.0.1;dbname=ahf;charset=utf8mb4',
   'DB_USER' => 'ahf_web',
   'DB_PASS' => 'AhfWeb@2024!',
   ```

2. **Admin password hash** - Generate a new hash for your password:
   ```bash
   # On the server, run this to generate a password hash:
   php -r "echo password_hash('fit2025!', PASSWORD_BCRYPT) . PHP_EOL;"

   # Copy the output and paste it into config/.env.php
   ```

   Then update in `config/.env.php`:
   ```php
   'ADMIN_PASS_HASH' => '$2y$10$...your_hash_here...',
   ```

3. **Authorize.Net credentials** (your current values):
   ```php
   'AUTH_ENV' => 'PROD',
   'AUTH_LOGIN_ID' => '75aKSj4J5',
   'AUTH_TRANSACTION_KEY' => '27Pdsz96u2EsC693',
   'AUTH_SIGNATURE_KEY_HEX' => '1402B82DA340E43F9D13A8B85FF320919BCE81D56307534ED467BD00471C9C669201D0001F58F519D38631588862943BE06BBD9BC99F22EE38FF34B77E36EE87',
   ```

4. **Enable HTTPS settings** (if you have SSL configured):
   ```php
   'SESSION_COOKIE_SECURE' => true,  // Change to true for HTTPS
   ```

5. **Set production mode**:
   ```php
   'DISPLAY_ERRORS' => false,  // Should be false in production
   ```

Save and exit (Ctrl+X, then Y, then Enter in nano).

---

### **STEP 4: Set File Permissions**

Make sure the config file is readable by Apache but not publicly accessible:

```bash
# Set proper ownership
sudo chown www-data:www-data config/.env.php

# Set permissions (readable only by owner and group)
sudo chmod 640 config/.env.php

# Ensure Apache can write to logs directory
sudo mkdir -p logs
sudo chown -R www-data:www-data logs
sudo chmod 755 logs
```

---

### **STEP 5: Create Rate Limiting Database Table**

The rate limiting system will automatically create its table, but you can verify:

```bash
# Connect to MySQL
mysql -u ahf_web -p ahf

# Check if the table was created
SHOW TABLES LIKE 'rate_limits';

# If you want to manually create it:
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    request_count INT DEFAULT 1,
    window_start INT NOT NULL,
    INDEX idx_identifier_endpoint (identifier, endpoint),
    INDEX idx_window (window_start)
);

# Exit MySQL
EXIT;
```

---

### **STEP 6: Test the Deployment**

1. **Test Admin Login**:
   - Go to: `https://andalusiahealthandfitness.com/admin/`
   - Login with: username `admin`, password `fit2025!`
   - Should redirect to dashboard after successful login

2. **Test Rate Limiting**:
   - Try accessing an API endpoint rapidly (e.g., quickpay lookup)
   - After 60 requests in 60 seconds, you should get a 429 error

3. **Test Error Handling**:
   - Check that PHP errors are NOT displayed to users
   - Errors should be logged to `logs/` directory

4. **Test Security Headers**:
   - Use browser DevTools → Network tab
   - Check response headers for security headers (CSP, X-Frame-Options, etc.)

5. **Test Session Timeout**:
   - Login to admin panel
   - Wait 2 hours (or adjust timeout in config for testing)
   - Refresh page - should redirect to login

---

## 🔧 Configuration Options

All configuration is in `config/.env.php`. Key settings:

```php
// Session timeout (in seconds)
'SESSION_LIFETIME' => 7200,  // 2 hours

// Rate limiting
'RATE_LIMIT_ENABLED' => true,
'RATE_LIMIT_REQUESTS' => 60,  // requests per window
'RATE_LIMIT_WINDOW' => 60,    // seconds

// Security
'DISPLAY_ERRORS' => false,           // false in production
'SESSION_COOKIE_SECURE' => true,     // true for HTTPS
'SESSION_COOKIE_HTTPONLY' => true,   // recommended
'SESSION_COOKIE_SAMESITE' => 'Strict',
```

---

## 🚨 Important Security Notes

### **DO NOT Commit These Files:**
- ❌ `config/.env.php` (contains real credentials)
- ❌ `config/bridge.key` (AxTrax key)
- ❌ `logs/*.log` (may contain sensitive data)

### **Safe to Commit:**
- ✅ `config/.env.example.php` (template only)
- ✅ `_bootstrap.php` (loads from config)
- ✅ `_security_headers.php` (no credentials)
- ✅ `_rate_limit.php` (no credentials)
- ✅ `admin/_auth.php` (no credentials)

### **Backup Your Credentials:**
Keep a secure backup of your `config/.env.php` file in a password manager or secure location, NOT in git.

---

## 🔄 Future Updates Workflow

When making future changes:

1. **Edit files in Visual Studio Code**
2. **Test locally if possible**
3. **Commit to GitHub**:
   ```bash
   git add .
   git commit -m "Your commit message"
   git push origin master
   ```
4. **Pull on server**:
   ```bash
   ssh user@server
   cd /var/www/html/andalusiahealthandfitness
   git pull origin master
   ```
5. **The `config/.env.php` file on server will NOT be affected** (it's ignored by git)

---

## 🐛 Troubleshooting

### **"Database connection failed"**
- Check `config/.env.php` exists on server
- Verify database credentials are correct
- Check file permissions (should be readable by Apache)

### **"Configuration error"**
- Check that `config/.env.php` is properly formatted
- Ensure it returns an array (not empty)
- Check PHP error logs: `tail -f /var/log/apache2/error.log`

### **Admin login fails**
- Generate a new password hash: `php -r "echo password_hash('your_password', PASSWORD_BCRYPT);"`
- Update `ADMIN_PASS_HASH` in `config/.env.php`
- Clear browser cookies and try again

### **Rate limiting too strict**
- Increase `RATE_LIMIT_REQUESTS` in config
- Or disable temporarily: `'RATE_LIMIT_ENABLED' => false`

### **Session times out too quickly**
- Increase `SESSION_LIFETIME` in config (in seconds)
- Default is 7200 (2 hours)

---

## 📞 Support

If you encounter issues during deployment:
1. Check Apache error logs: `sudo tail -f /var/log/apache2/error.log`
2. Check application logs: `tail -f logs/*.log`
3. Verify file permissions
4. Ensure `config/.env.php` exists and has correct values

---

## ✅ Deployment Checklist

Before going live, verify:

- [ ] `config/.env.php` created on server with correct credentials
- [ ] Admin password hash generated and configured
- [ ] `DISPLAY_ERRORS` set to `false`
- [ ] `SESSION_COOKIE_SECURE` set to `true` (if using HTTPS)
- [ ] File permissions correct (640 for config, 755 for logs)
- [ ] Tested admin login
- [ ] Tested API endpoints
- [ ] Verified security headers in browser
- [ ] Checked error logs for issues
- [ ] Backed up credentials securely

---

**🎉 Congratulations! Your application is now significantly more secure!**
