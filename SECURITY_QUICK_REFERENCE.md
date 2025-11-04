# Security Quick Reference
**Andalusia Health & Fitness**

## 🔑 Common Tasks

### Generate a New Admin Password Hash
```bash
php -r "echo password_hash('your_new_password', PASSWORD_BCRYPT) . PHP_EOL;"
```
Then update `config/.env.php` with the output.

---

### Change Admin Password
1. SSH into server
2. Generate new hash (command above)
3. Edit config:
   ```bash
   nano config/.env.php
   ```
4. Update `ADMIN_PASS_HASH` value
5. Save and exit

---

### Adjust Rate Limiting
Edit `config/.env.php`:
```php
'RATE_LIMIT_ENABLED' => true,      // Enable/disable
'RATE_LIMIT_REQUESTS' => 60,       // Max requests
'RATE_LIMIT_WINDOW' => 60,         // Time window (seconds)
```

---

### View Failed Login Attempts
```bash
tail -f /var/log/apache2/error.log | grep "Failed login"
```

---

### Clear Rate Limiting for an IP
```bash
mysql -u ahf_web -p ahf
DELETE FROM rate_limits WHERE identifier LIKE '%192.168.1.1%';
EXIT;
```

---

### Check Security Headers
```bash
curl -I https://andalusiahealthandfitness.com/admin/
```
Look for: `Content-Security-Policy`, `X-Frame-Options`, `X-XSS-Protection`

---

### View Application Logs
```bash
# All logs
tail -f logs/*.log

# Specific log
tail -f logs/member-save-debug.log
```

---

### Disable Error Display (Production)
In `config/.env.php`:
```php
'DISPLAY_ERRORS' => false,
```

---

### Enable HTTPS Mode
In `config/.env.php`:
```php
'SESSION_COOKIE_SECURE' => true,
```

---

### Adjust Session Timeout
In `config/.env.php`:
```php
'SESSION_LIFETIME' => 7200,  // 2 hours = 7200 seconds
```

---

## 🚨 Emergency Commands

### Lock Out All Users (Maintenance Mode)
```bash
# Temporarily disable rate limiting or set very low limits
nano config/.env.php
# Set: 'RATE_LIMIT_REQUESTS' => 1
```

### Reset Admin Session
```bash
# On server:
rm -rf /tmp/sess_*
# Or restart Apache:
sudo systemctl restart apache2
```

### View Recent Security Events
```bash
# Failed logins
grep "Failed login" /var/log/apache2/error.log | tail -20

# Rate limit hits
tail -20 logs/*.log | grep "rate_limit"
```

---

## 📁 Important Files

| File | Purpose | Commit? |
|------|---------|---------|
| `config/.env.php` | Real credentials | ❌ NO |
| `config/.env.example.php` | Template | ✅ YES |
| `_bootstrap.php` | Config loader | ✅ YES |
| `_security_headers.php` | Security headers | ✅ YES |
| `_rate_limit.php` | Rate limiting | ✅ YES |
| `admin/_auth.php` | Admin authentication | ✅ YES |
| `.gitignore` | Excluded files | ✅ YES |

---

## 🔒 Security Levels

### Current Protection:
- ✅ Password hashing (bcrypt)
- ✅ Session security (2-hour timeout)
- ✅ CSRF protection
- ✅ Rate limiting (60/min)
- ✅ Security headers
- ✅ Input validation
- ✅ Failed login tracking

### Not Yet Implemented:
- ⚠️ Two-factor authentication (2FA)
- ⚠️ IP whitelist for admin panel
- ⚠️ Brute force IP blocking
- ⚠️ Security monitoring/alerts
- ⚠️ Regular security audits

---

## 📞 Quick Diagnostics

### Website Not Loading?
```bash
# Check Apache status
sudo systemctl status apache2

# Check error logs
sudo tail -20 /var/log/apache2/error.log

# Check config file exists
ls -la config/.env.php
```

### Can't Login to Admin?
```bash
# Check password hash in config
cat config/.env.php | grep ADMIN_PASS_HASH

# Generate new hash
php -r "echo password_hash('fit2025!', PASSWORD_BCRYPT);"

# Check session directory permissions
ls -la /tmp/sess_* | head -5
```

### API Endpoints Not Working?
```bash
# Check rate limiting table
mysql -u ahf_web -p ahf -e "SELECT COUNT(*) FROM rate_limits;"

# View recent rate limits
mysql -u ahf_web -p ahf -e "SELECT * FROM rate_limits ORDER BY id DESC LIMIT 10;"

# Clear all rate limits
mysql -u ahf_web -p ahf -e "TRUNCATE rate_limits;"
```

---

## 🎯 Best Practices

1. **Never commit credentials** - Always use `config/.env.php`
2. **Keep passwords secure** - Use strong, unique passwords
3. **Monitor logs regularly** - Check for suspicious activity
4. **Update dependencies** - Keep PHP and libraries current
5. **Backup the config** - Keep secure backups of `config/.env.php`
6. **Test before deploying** - Always test changes in dev first
7. **Use HTTPS** - Enable SSL/TLS for production
8. **Rotate credentials** - Change passwords periodically

---

## 📈 Monitoring

### Check for Suspicious Activity
```bash
# Multiple failed logins from same IP
grep "Failed login" /var/log/apache2/error.log | awk '{print $NF}' | sort | uniq -c | sort -rn

# High rate limit hits
mysql -u ahf_web -p ahf -e "SELECT identifier, endpoint, SUM(request_count) as total FROM rate_limits GROUP BY identifier ORDER BY total DESC LIMIT 10;"
```

### Weekly Maintenance Tasks
1. Review error logs
2. Check rate limit database size
3. Verify backups are current
4. Test admin login
5. Review security headers
6. Check for failed login patterns
7. Update dependencies if needed

---

**Need help?** Check the full `SECURITY_DEPLOYMENT_GUIDE.md` for detailed instructions.
