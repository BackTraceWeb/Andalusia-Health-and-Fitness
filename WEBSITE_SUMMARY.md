# Andalusia Health & Fitness
## Website Quick Reference Guide

**🌐 Website**: https://andalusiahealthandfitness.com

---

## 📱 What Your Members See

### 🏠 Homepage
- Professional gym branding
- Easy navigation to sign up or make payments
- Contact information and pricing

### 💳 QuickPay Portal
**URL**: `/quickpay/`
- Members look themselves up by name
- View outstanding dues
- Pay online instantly
- Access automatically reactivated

### ✍️ Membership Sign-Up
**URL**: `/membership.html`
- Choose plan: Single, Couples, Family, Senior, Student
- Add tanning package ($25/mo)
- Complete digital waiver
- Upload photo
- Pay online
- Done!

### 📋 Digital Waiver
**URL**: `/waiver.html`
- Sign with finger or mouse
- Legally binding
- Saved as PDF in cloud
- Parents sign for kids

---

## 🔐 What Staff See (Admin Portal)

### 👤 Login
**URL**: `/admin/`
- Username: `admin`
- Secure password
- Auto-logout after 2 hours

### 📊 Dashboard
**URL**: `/admin/dashboard.php`

**At-a-Glance Stats**:
- Total members
- Current (paid) members
- Past due members

**Member Cards**:
- 🟢 Green = Current (good standing)
- 🔴 Red = Due (needs payment)

**Quick Info on Each Member**:
- Name & photo
- Card number
- Company/department
- Monthly fee
- Status

**Search & Filter**:
- Search by name
- Filter by department
- Sort by status

### ✏️ Edit Members
**URL**: `/admin/edit-member.php`
- Update contact info
- Change monthly fee
- Adjust payment type
- Set validity dates
- Change department

### 🏢 Manage Departments
**URL**: `/admin/departments.php`
- Corporate/group pricing
- Set custom rates per company
- Apply pricing to all members at once
- Example: "ABC Manufacturing" = $35/month

---

## 💰 How Payments Work

```
Member Pays Online
        ↓
Authorize.Net Processes (Secure)
        ↓
System Gets Confirmation
        ↓
Member Status → Current ✅
        ↓
Access Card Reactivated 🚪
        ↓
Email Receipt Sent 📧
```

**Payment Processor**: Authorize.Net
**Security**: PCI-DSS Level 1 Compliant
**Cards Accepted**: Visa, Mastercard, Amex, Discover

---

## 🔒 Security Features

| Feature | Protection |
|---------|-----------|
| 🔐 **Password Hashing** | Admin passwords encrypted |
| 🍪 **Session Security** | Auto-logout, secure cookies |
| 🚦 **Rate Limiting** | Prevents automated attacks |
| 🛡️ **Security Headers** | Protects against XSS, clickjacking |
| 🔒 **HTTPS/SSL** | All data encrypted in transit |
| 🚫 **CSRF Protection** | Prevents form hijacking |
| 📊 **Login Tracking** | Failed attempt monitoring |

---

## ☁️ What's Stored Where

### 💾 Your Server Database
- Member names & contact info
- Card numbers
- Payment status
- Billing records
- Department information

### 📦 Amazon S3 Cloud
- Member photos
- Signed waiver PDFs
- Email attachments

### 💳 Authorize.Net
- Payment processing only
- Card data NEVER stored on your server

---

## 📈 Membership Plans

| Plan | Price | Details |
|------|-------|---------|
| **Single** | $40/mo | Individual membership |
| **Couples** | $70/mo | Two adults |
| **Family** | $100/mo | 2 adults + kids |
| **Senior** | $30/mo | 55+ discount |
| **Student** | $35/mo | With valid ID |
| **Tanning** | +$25/mo | Add-on package |

*Department pricing may vary - see Departments page*

---

## 🔧 Common Tasks

### How Members Pay
1. Go to `/quickpay/`
2. Enter first & last name
3. Click "Look Up"
4. Click "Pay Now"
5. Complete secure payment

### How to Update a Member
1. Login to admin
2. Find member on dashboard
3. Click member card
4. Edit fields
5. Save

### How to Apply Department Pricing
1. Go to Departments page
2. Set base price & tanning price
3. Click "Apply to All"
4. All department members updated

### How to Check Payment Status
1. Dashboard → Member cards
2. 🟢 Green = Paid
3. 🔴 Red = Due

---

## 🆘 Quick Support

### Member Can't Login to QuickPay?
- QuickPay doesn't require login
- They just search by name
- Check spelling of name in database

### Payment Didn't Update Status?
- Check Authorize.Net dashboard
- Verify webhook is working
- May take 1-2 minutes to sync

### Member Forgot Their Info?
- Look them up in admin dashboard
- Provide their card number
- Confirm email on file

### Need to Change Admin Password?
```bash
php generate-password-hash.php new_password
# Then update config/.env.php
```

---

## 📊 System Health

**Uptime Target**: 99.9%
**Page Load Speed**: < 2 seconds
**Backup Schedule**: Daily
**Security Updates**: Automatic

---

## 🚀 Recent Improvements (November 2025)

✅ Enhanced password security (bcrypt hashing)
✅ Session timeout protection
✅ Rate limiting on API endpoints
✅ Comprehensive security headers
✅ CSRF protection on forms
✅ Login attempt tracking
✅ Production error handling
✅ Centralized configuration

---

## 📞 Contact Info

**Website**: https://andalusiahealthandfitness.com
**Admin Portal**: https://andalusiahealthandfitness.com/admin/
**QuickPay**: https://andalusiahealthandfitness.com/quickpay/

**Technical Support**: Contact your web developer
**Payment Support**: Authorize.Net (1-877-447-3938)

---

## 📱 Mobile Friendly

✅ All pages work on phones & tablets
✅ Responsive design
✅ Easy navigation on small screens
✅ Touch-friendly buttons

---

## 🎯 Key Benefits

| For Members | For Staff | For Business |
|------------|-----------|--------------|
| ✅ Pay online 24/7 | ✅ Easy member mgmt | ✅ Automated billing |
| ✅ No waiting in line | ✅ Real-time status | ✅ Digital records |
| ✅ Instant confirmation | ✅ Search & filter | ✅ Reduced paperwork |
| ✅ Email receipts | ✅ Bulk updates | ✅ Professional image |
| ✅ Self-service | ✅ One dashboard | ✅ Scalable system |

---

**Last Updated**: November 4, 2025
**System Version**: 2.0 (Security Enhanced)

*For detailed information, see WEBSITE_OVERVIEW.md*
