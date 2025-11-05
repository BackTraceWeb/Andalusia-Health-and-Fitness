# Credential Rotation Guide
**Date**: November 4, 2025
**Status**: 🚨 URGENT - Credentials Exposed in Git History

---

## 🎯 Overview

After the `.git` directory breach, ALL credentials in git history must be rotated.

---

## 📋 Rotation Checklist

### Andalusia Health & Fitness

- [x] **Authorize.Net Keys** - ✅ Already rotated by you
- [ ] **Database Password** - Change MySQL password
- [ ] **Admin Password** - Generate new hash
- [ ] **Bridge Key** - Generate new key for NinjaOne
- [ ] **Update config/.env.php** - Apply all new credentials

### BackTrace

- [ ] **Microsoft OAuth** - Rotate in Azure Portal
- [ ] **reCAPTCHA** - Rotate in Google Console
- [ ] **Deploy to Server** - Update config and test

---

## 🔒 Part 1: Andalusia Health & Fitness

### Step 1: Prepare New Credentials

**New Database Password** (generated):
```
qmhUVaSvt6a6QaawDDemAeXPfbrewyFcsLwOLkueQq4=
```

**New Bridge Key** (generated):
```
33a909fa86c6e0ba5ce9302e51b3ad7b50b8a9a9d193642c4907fb5f80912f9b
```

**New Admin Password** (example - you can choose your own):
```
Plain Text: FitSecure2025!
Bcrypt Hash: (will generate on server)
```

---

### Step 2: Update Authorize.Net Keys in Config

**You already rotated these in Authorize.Net dashboard. Now update the server config:**

```bash
# SSH to server
ssh -i ~/Downloads/BTS1.pem ubuntu@3.12.72.81

# Edit config file
cd /var/www/andalusiahealthandfitness
sudo nano config/.env.php
```

**Update these lines** (use your NEW keys from Authorize.Net):
```php
'AUTH_LOGIN_ID' => 'YOUR_NEW_LOGIN_ID',
'AUTH_TRANSACTION_KEY' => 'YOUR_NEW_TRANSACTION_KEY',
'AUTH_SIGNATURE_KEY' => 'YOUR_NEW_SIGNATURE_KEY',
```

**Save**: `Ctrl+O`, `Enter`, `Ctrl+X`

---

### Step 3: Change Database Password

```bash
# Still on server, connect to MySQL
sudo mysql

# Run these commands:
ALTER USER 'ahf_web'@'localhost' IDENTIFIED BY 'qmhUVaSvt6a6QaawDDemAeXPfbrewyFcsLwOLkueQq4=';
FLUSH PRIVILEGES;
EXIT;
```

**Now update config/.env.php:**
```bash
sudo nano config/.env.php
```

**Update this line:**
```php
'DB_PASS' => 'qmhUVaSvt6a6QaawDDemAeXPfbrewyFcsLwOLkueQq4=',
```

**Save**: `Ctrl+O`, `Enter`, `Ctrl+X`

---

### Step 4: Generate New Admin Password Hash

```bash
# On server, generate bcrypt hash
php -r "echo password_hash('FitSecure2025!', PASSWORD_BCRYPT) . PHP_EOL;"
```

**Copy the output** (will look like: `$2y$10$abcd...`)

**Update config/.env.php:**
```bash
sudo nano config/.env.php
```

**Update this line:**
```php
'ADMIN_PASS_HASH' => '$2y$10$YOUR_NEW_HASH_HERE',
```

**Save**: `Ctrl+O`, `Enter`, `Ctrl+X`

---

### Step 5: Rotate Bridge Key

**Update config file:**
```bash
sudo nano /var/www/andalusiahealthandfitness/config/bridge.key
```

**Replace content with:**
```
33a909fa86c6e0ba5ce9302e51b3ad7b50b8a9a9d193642c4907fb5f80912f9b
```

**Save**: `Ctrl+O`, `Enter`, `Ctrl+X`

**Set permissions:**
```bash
sudo chmod 600 config/bridge.key
sudo chown www-data:www-data config/bridge.key
```

---

### Step 6: Update NinjaOne Scripts

**You need to update your PowerShell scripts in NinjaOne with the new bridge key.**

**In NinjaOne RMM:**
1. Go to **Administration → Scripted Actions**
2. Find your AxTrax sync scripts:
   - "AxTrax Member Sync"
   - "AxTrax Dues Sync"
3. Update the bridge key variable:
   ```powershell
   $bridgeKey = "33a909fa86c6e0ba5ce9302e51b3ad7b50b8a9a9d193642c4907fb5f80912f9b"
   ```
4. Save scripts

**Or update the secure custom field:**
1. Go to **Administration → Custom Fields**
2. Find `axtrax_bridge_key` field
3. Update value to new key
4. Save

---

### Step 7: Test Andalusia Health Site

```bash
# Test database connection
php -r "require '/var/www/andalusiahealthandfitness/_bootstrap.php'; \$pdo = pdo(); echo 'DB OK' . PHP_EOL;"

# Test admin login
curl -I https://andalusiahealthandfitness.com/admin/

# Check for errors
sudo tail -20 /var/log/apache2/error.log
```

**Visit site and test:**
- [ ] Homepage loads: https://andalusiahealthandfitness.com
- [ ] Admin login works: https://andalusiahealthandfitness.com/admin/
  - User: `admin`
  - Pass: `FitSecure2025!` (or your chosen password)
- [ ] QuickPay loads: https://andalusiahealthandfitness.com/quickpay/
- [ ] Make test payment (optional)

---

## 🔒 Part 2: BackTrace

### Step 1: Rotate Microsoft OAuth Credentials

**Azure Portal** (https://portal.azure.com):

1. **Login** to Azure Portal
2. Go to **Azure Active Directory** → **App registrations**
3. Find your app: `BackTrace Email Sender` (or similar)
4. Click **Certificates & secrets**
5. **Delete old secret** (starts with `RBw8Q~...`)
6. Click **+ New client secret**
7. Description: `BackTrace Production - Nov 2025`
8. Expires: 24 months
9. Click **Add**
10. **COPY THE SECRET IMMEDIATELY** (you won't see it again)

**Save these values:**
```
Tenant ID: b86dbc07-4c1e-4028-a331-84b714a09e4c (same)
Client ID: 53b6f2c6-5bf4-4bc9-a84b-53eef558dd3f (same)
Client Secret: [NEW SECRET YOU JUST COPIED]
```

---

### Step 2: Rotate reCAPTCHA Keys

**Google reCAPTCHA Console** (https://www.google.com/recaptcha/admin):

1. **Login** to Google account
2. Go to reCAPTCHA Admin Console
3. Find your site: `back-trace.com`
4. Click **Settings** (gear icon)
5. Click **Delete this key** (confirm)
6. Click **+ Create** (top right)
7. **Label**: `back-trace.com Production`
8. **reCAPTCHA type**: v2 "I'm not a robot"
9. **Domains**: `back-trace.com`
10. Click **Submit**

**Save these values:**
```
Site Key: [YOUR NEW SITE KEY]
Secret Key: [YOUR NEW SECRET KEY]
```

---

### Step 3: Deploy BackTrace to Server

**SSH to server:**
```bash
ssh YOUR_SERVER_USER@YOUR_SERVER_IP
cd /var/www/back-trace.com
```

**Pull latest code:**
```bash
git pull origin main
```

**Create config file:**
```bash
cp config/.env.php.example config/.env.php
nano config/.env.php
```

**Update with YOUR NEW credentials:**
```php
<?php
return [
    // Microsoft OAuth (NEW credentials from Step 1)
    'MS_TENANT_ID' => 'b86dbc07-4c1e-4028-a331-84b714a09e4c',
    'MS_CLIENT_ID' => '53b6f2c6-5bf4-4bc9-a84b-53eef558dd3f',
    'MS_CLIENT_SECRET' => 'YOUR_NEW_SECRET_FROM_AZURE',
    'MS_EMAIL_SENDER' => 'sales@back-trace.com',

    // reCAPTCHA (NEW keys from Step 2)
    'RECAPTCHA_SITE_KEY' => 'YOUR_NEW_SITE_KEY',
    'RECAPTCHA_SECRET_KEY' => 'YOUR_NEW_SECRET_KEY',

    // Security Settings (keep as-is)
    'SESSION_TIMEOUT' => 1800,
    'CSRF_TOKEN_NAME' => 'csrf_token',
    'RATE_LIMIT_ENABLED' => true,
    'RATE_LIMIT_MAX_REQUESTS' => 5,
    'RATE_LIMIT_WINDOW_SECONDS' => 3600,
    'SECURITY_HEADERS_ENABLED' => true,
    'CSP_ENABLED' => true,
    'ERROR_REPORTING' => E_ALL,
    'DISPLAY_ERRORS' => false,
    'LOG_ERRORS' => true,
];
```

**Save**: `Ctrl+O`, `Enter`, `Ctrl+X`

**Set permissions:**
```bash
sudo chown www-data:www-data config/.env.php
sudo chmod 600 config/.env.php
```

---

### Step 4: Update BackTrace reCAPTCHA Site Key in HTML

**Update all HTML files that have reCAPTCHA:**

The site key is public, so you need to update the HTML:

```bash
cd /var/www/back-trace.com

# Update contact.html if still using it
nano contact.html
```

**Find and replace:**
```html
<!-- OLD -->
<div class="g-recaptcha" data-sitekey="6LcAJgArAAAAAJkNX4nw0-ewsYqLBj4wLmhEJZXP"></div>

<!-- NEW (use your new site key from Step 2) -->
<div class="g-recaptcha" data-sitekey="YOUR_NEW_SITE_KEY"></div>
```

**Note**: If using `contact-form.php`, it will auto-load from config:
```php
<div class="g-recaptcha" data-sitekey="<?php echo e(config('RECAPTCHA_SITE_KEY')); ?>"></div>
```

---

### Step 5: Test BackTrace Site

```bash
# Test form page loads
curl -I https://back-trace.com/contact-form.php

# Check for errors
sudo tail -20 /var/log/apache2/error.log
```

**Manual testing:**
1. Visit: https://back-trace.com/contact-form.php
2. Fill out form
3. Complete reCAPTCHA
4. Submit
5. Check if email arrives at sales@back-trace.com

---

## 🔍 Verification Checklist

### Andalusia Health & Fitness
- [ ] New Authorize.Net keys in config/.env.php
- [ ] Database password changed in MySQL
- [ ] Database password updated in config/.env.php
- [ ] Admin password hash generated and updated
- [ ] Bridge key rotated in config/bridge.key
- [ ] NinjaOne scripts updated with new bridge key
- [ ] Site loads without errors
- [ ] Admin login works with new password
- [ ] QuickPay portal loads
- [ ] Payment test successful (optional)

### BackTrace
- [ ] Microsoft OAuth secret rotated in Azure
- [ ] reCAPTCHA keys rotated in Google Console
- [ ] Code pulled to server
- [ ] config/.env.php created with NEW credentials
- [ ] File permissions set (600, owned by www-data)
- [ ] reCAPTCHA site key updated in HTML (if needed)
- [ ] Contact form loads
- [ ] Form submission works
- [ ] Email arrives
- [ ] Security headers present (check DevTools)

---

## 📊 Credential Status

### Before (Exposed in Git)
| Credential | Status | Risk |
|------------|--------|------|
| Auth.Net Transaction Key | ❌ Exposed | 🔴 High (payment processing) |
| Database Password | ❌ Exposed | 🔴 High (full DB access) |
| Admin Password | ❌ Exposed | 🔴 High (admin panel) |
| Bridge Key | ❌ Exposed | 🟡 Medium (NinjaOne sync) |
| MS OAuth Secret | ❌ Exposed | 🔴 High (send emails as you) |
| reCAPTCHA Secret | ❌ Exposed | 🟡 Medium (bypass bot protection) |

### After Rotation
| Credential | Status | Risk |
|------------|--------|------|
| Auth.Net Transaction Key | ✅ Rotated | ✅ Secure |
| Database Password | ⏳ In Progress | ⏳ Pending |
| Admin Password | ⏳ In Progress | ⏳ Pending |
| Bridge Key | ⏳ In Progress | ⏳ Pending |
| MS OAuth Secret | ⏳ In Progress | ⏳ Pending |
| reCAPTCHA Secret | ⏳ In Progress | ⏳ Pending |

---

## 📞 Support

**Issues during rotation?**
- Database connection fails → Check password in both MySQL and config/.env.php
- Admin login fails → Regenerate password hash
- BackTrace emails fail → Verify MS OAuth secret is correct
- reCAPTCHA fails → Check site key matches in HTML and config

---

## ✅ Final Steps

**After all rotations complete:**

1. **Test both sites thoroughly**
2. **Monitor logs for 24 hours** for any issues
3. **Update this checklist** with completion dates
4. **Store new credentials securely** (password manager)
5. **Schedule next security review** (monthly)

---

**Generated**: November 4, 2025
**Status**: 📋 Ready to Execute
**Priority**: 🚨 URGENT
