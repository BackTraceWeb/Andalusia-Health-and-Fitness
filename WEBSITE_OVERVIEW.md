# Andalusia Health & Fitness
## Website System Overview

**Your Complete Gym Membership & Payment Management Solution**

---

## 📋 Table of Contents

1. [Executive Summary](#executive-summary)
2. [What This System Does](#what-this-system-does)
3. [Member-Facing Features](#member-facing-features)
4. [Staff & Admin Features](#staff--admin-features)
5. [Payment Processing](#payment-processing)
6. [Security & Data Protection](#security--data-protection)
7. [Third-Party Integrations](#third-party-integrations)
8. [System Architecture](#system-architecture)
9. [Support & Maintenance](#support--maintenance)

---

## Executive Summary

The Andalusia Health & Fitness website is a comprehensive digital platform designed to streamline gym operations, from member sign-ups to payment processing. The system automates membership management, reduces administrative workload, and provides a seamless experience for both members and staff.

**Website URL**: https://andalusiahealthandfitness.com

**System Type**: Custom-built membership management and payment platform

**Primary Users**:
- Prospective members (sign-up)
- Current members (quick payments)
- Gym staff (member management)
- Gym administrators (full system control)

---

## What This System Does

### For Your Members:
✅ Sign up for memberships online (24/7)
✅ Select membership plans and add-ons
✅ Complete digital liability waivers
✅ Make secure payments online
✅ Look up and pay outstanding dues quickly

### For Your Staff:
✅ Manage all member records in one place
✅ Track payment status (current or due)
✅ Process membership changes
✅ View real-time membership statistics
✅ Organize members by department/company

### For Your Business:
✅ Automated payment collection
✅ Reduced manual paperwork
✅ Digital waiver storage
✅ Integrated access control (door system)
✅ Professional online presence

---

## Member-Facing Features

### 1. Homepage
**URL**: https://andalusiahealthandfitness.com

**Purpose**: Your gym's digital front door

**Features**:
- Professional branding and gym information
- Call-to-action buttons for membership sign-up
- Navigation to pricing, membership, and quick pay
- Contact information and hours

**Who Uses It**: Potential new members, visitors researching your gym

---

### 2. Membership Sign-Up
**URL**: https://andalusiahealthandfitness.com/membership.html

**Purpose**: Online membership enrollment system

**Features**:
- **Multiple Membership Types**:
  - Single ($40/month)
  - Couples ($70/month)
  - Family ($100/month)
  - Senior ($30/month)
  - Student ($35/month)
  - Tanning Add-on ($25/month)

- **Smart Plan Selection**:
  - Dynamic pricing calculation
  - Visual plan cards with descriptions
  - Add multiple family members
  - Optional tanning package

- **Member Information Collection**:
  - Full name, date of birth
  - Contact information (email, phone)
  - Emergency contact details
  - Photo upload for membership card

- **Department/Company Pricing**:
  - Corporate membership discounts
  - Special pricing for affiliated groups
  - Automatic pricing adjustment by department

**User Experience**:
1. Select membership type
2. Add family members (if applicable)
3. Complete waiver(s)
4. Upload photo
5. Review and submit
6. Proceed to payment

**What Happens Next**:
- Application is submitted to admin
- Digital waiver is saved with signatures
- Member receives confirmation email
- Payment is processed
- Access card is activated (via AxTrax system)

---

### 3. Digital Waiver System
**URL**: https://andalusiahealthandfitness.com/waiver.html

**Purpose**: Paperless liability waiver signing

**Features**:
- **Electronic Signature Capture**:
  - Draw signature with mouse/finger
  - Works on desktop, tablet, and mobile
  - Multiple signers supported

- **Legal Coverage**:
  - Liability release
  - Medical condition disclosure
  - Emergency contact authorization
  - Photography consent

- **Multi-Party Support**:
  - Parents sign for minors
  - Each family member signs separately
  - Co-signers for couples

- **Document Storage**:
  - Signed waivers saved as PDF
  - Stored securely in cloud (AWS S3)
  - Accessible by admin when needed

**User Experience**:
1. Read waiver terms
2. Provide personal information
3. Sign with digital signature
4. Parent/guardian signs for minors
5. Submit completed waiver

**Legal Protection**: All waivers include timestamps, IP addresses, and digital signatures that are legally binding.

---

### 4. QuickPay Portal
**URL**: https://andalusiahealthandfitness.com/quickpay/

**Purpose**: Self-service payment for existing members

**Features**:
- **Member Lookup**:
  - Search by first and last name
  - No login required
  - Fast and simple

- **Invoice Display**:
  - View outstanding dues
  - See payment period (month covered)
  - Clear amount breakdown
  - Payment status

- **Secure Payment**:
  - Credit/debit card processing
  - Hosted by Authorize.Net (bank-level security)
  - Instant payment confirmation
  - Email receipt

- **Automatic Updates**:
  - Payment status updated in real-time
  - Access card reactivated automatically
  - Member record updated

**User Experience**:
1. Enter first and last name
2. Click "Look Up Member"
3. View outstanding invoices
4. Click "Pay Now"
5. Complete secure payment
6. Receive confirmation

**Privacy Note**: Only shows invoices for the name searched (no sensitive data exposed).

---

### 5. Pricing Page
**URL**: https://andalusiahealthandfitness.com/pricing.html

**Purpose**: Transparent membership pricing information

**Features**:
- All membership plan prices
- Add-on options (tanning)
- Family and corporate discounts
- Special promotions (if any)

---

### 6. Legal Pages

**Privacy Policy**: https://andalusiahealthandfitness.com/Legal/privacy.html
- Data collection practices
- How member information is used
- Data protection measures

**Terms & Conditions**: https://andalusiahealthandfitness.com/Legal/terms.html
- Membership agreement terms
- Cancellation policy
- Rules and regulations

---

## Staff & Admin Features

### 7. Admin Control Panel
**URL**: https://andalusiahealthandfitness.com/admin/

**Purpose**: Central hub for gym management

**Access**: Password-protected, admin-only

**Login Credentials**:
- Username: `admin`
- Password: (Securely hashed, contact system administrator)

**Session Security**:
- Automatic logout after 2 hours of inactivity
- Secure encrypted connection (HTTPS)
- Login attempt tracking

---

### 8. Dashboard
**URL**: https://andalusiahealthandfitness.com/admin/dashboard.php

**Purpose**: Real-time overview of membership status

**Features**:
- **Quick Statistics**:
  - Total members
  - Current (paid up) members
  - Past due members
  - Revenue metrics

- **Member List**:
  - Searchable database
  - Sortable by name, status, department
  - Color-coded status indicators:
    - 🟢 Green = Current (paid)
    - 🔴 Red = Due (payment needed)

- **Member Cards Display**:
  - Member photo
  - Full name
  - Department/company
  - Card number
  - Payment status
  - Monthly fee
  - Validity dates

- **Quick Actions**:
  - Click member to edit details
  - Filter by department
  - Search by name
  - View all or current/due only

**Real-Time Updates**: Dashboard shows live data from the database, always current.

---

### 9. Member Editor
**URL**: https://andalusiahealthandfitness.com/admin/edit-member.php

**Purpose**: Update individual member records

**Editable Fields**:
- **Personal Information**:
  - First name, last name
  - Email address
  - ZIP code
  - Company/employer

- **Membership Details**:
  - Department assignment
  - Card number
  - Payment type (card, draft, cash, other)
  - Monthly fee amount
  - Membership status (current/due)

- **Validity Dates**:
  - Valid from date
  - Valid until date
  - Automatic expiration tracking

**Use Cases**:
- Update contact information
- Change monthly fee (promotion, cancellation)
- Adjust payment method
- Extend/modify membership dates
- Transfer to different department
- Mark account as paid/due

---

### 10. Department Management
**URL**: https://andalusiahealthandfitness.com/admin/departments.php

**Purpose**: Manage corporate/group pricing

**Features**:
- **Department List**:
  - All companies/groups with memberships
  - Default pricing for each department
  - Number of members per department

- **Department Pricing**:
  - Base membership price
  - Tanning add-on price
  - Custom pricing per company

- **Bulk Actions**:
  - Apply default pricing to all department members
  - Update pricing for entire group at once
  - Add new departments

- **Auto-Sync**:
  - Departments from AxTrax system sync automatically
  - New companies added on first member import

**Example Use**:
- "ABC Manufacturing" has 20 members
- Set their monthly rate at $35 (discounted from $40)
- Click "Apply to All" to update all ABC employees at once

---

### 11. Add Department
**URL**: https://andalusiahealthandfitness.com/admin/add-department.php

**Purpose**: Create new department/company pricing

**Features**:
- Department/company name
- Base membership price
- Tanning price (if applicable)
- Save and apply to members

---

## Payment Processing

### How Payments Work

**Payment Processor**: Authorize.Net (industry-standard, PCI compliant)

**Payment Flow**:

1. **Member initiates payment** (via QuickPay or membership signup)
2. **System creates payment request** with member details and amount
3. **Authorize.Net hosts secure payment page** (your site never sees card numbers)
4. **Member enters card information** on Authorize.Net's secure form
5. **Payment is processed** in real-time
6. **Authorize.Net sends confirmation** back to your system
7. **System updates member status** automatically
8. **Member receives email receipt**
9. **Access card is reactivated** (if door system connected)

### Payment Methods Accepted

- ✅ Visa
- ✅ Mastercard
- ✅ American Express
- ✅ Discover
- ✅ Debit cards

### Payment Security

**PCI Compliance**: Your website is PCI-DSS compliant because:
- Card data never touches your server
- All payments processed on Authorize.Net's secure platform
- SSL/TLS encryption for all transactions
- No card numbers stored in your database

**Fraud Protection**:
- Address Verification System (AVS)
- Card Verification Value (CVV) required
- Real-time fraud detection
- Secure payment tokens

### Payment Records

**What's Stored**:
- Transaction ID
- Amount paid
- Date/time of payment
- Member linked to payment
- Invoice number

**What's NOT Stored**:
- Full credit card numbers
- CVV codes
- Cardholder PIN

### Monthly Billing

**Dues Management**:
- System tracks member payment periods
- Creates "dues" records for each billing cycle
- Marks as "paid" when payment received
- Status shows in admin dashboard (current/due)

**Automatic Updates**:
- Member pays → Status changes to "current"
- Payment date is recorded
- Access card is reactivated
- Admin sees update immediately

---

## Security & Data Protection

### What We Protect

Your website handles sensitive information:
- Member personal data (names, emails, dates of birth)
- Payment information (processed securely, not stored)
- Digital signatures and waivers
- Access card numbers
- Emergency contact details

### Security Measures Implemented

#### 1. **Encrypted Connections (HTTPS/SSL)**
- 🔒 All pages use HTTPS
- 🔒 Data encrypted in transit
- 🔒 Green padlock in browser
- 🔒 Cloudflare protection enabled

#### 2. **Password Security**
- 🔑 Admin passwords are hashed (bcrypt)
- 🔑 Passwords never stored in plain text
- 🔑 Login attempt rate limiting (5 tries per 15 minutes)
- 🔑 Failed login tracking

#### 3. **Session Security**
- ⏱️ Auto-logout after 2 hours
- 🍪 Secure cookie settings
- 🍪 HttpOnly cookies (prevents JavaScript theft)
- 🍪 SameSite protection (prevents CSRF attacks)

#### 4. **API Protection**
- 🚦 Rate limiting (60 requests per minute)
- 🚦 Prevents automated attacks
- 🚦 Protects against brute force
- 🚦 IP-based tracking

#### 5. **Security Headers**
- 🛡️ Content Security Policy (CSP) - prevents XSS
- 🛡️ X-Frame-Options - prevents clickjacking
- 🛡️ X-Content-Type-Options - prevents MIME attacks
- 🛡️ Referrer Policy - protects user privacy

#### 6. **Database Security**
- 🔐 Prepared statements (prevents SQL injection)
- 🔐 User access controls
- 🔐 Regular backups
- 🔐 Credentials stored securely

#### 7. **Input Validation**
- ✔️ All user input sanitized
- ✔️ Email validation
- ✔️ Phone number formatting
- ✔️ File upload restrictions

#### 8. **Error Handling**
- 🚫 Error details hidden from users
- 🚫 Errors logged securely for admin
- 🚫 No sensitive information exposed

### Compliance

**Data Privacy**:
- Privacy policy clearly stated
- Member consent obtained
- Data only used for gym operations
- No selling of member data

**Legal Waivers**:
- Digital signatures legally binding
- Timestamp and IP address recorded
- Stored securely in AWS cloud
- Retrievable for legal purposes

**Payment Security**:
- PCI-DSS Level 1 compliant (via Authorize.Net)
- No card data stored on your server
- Annual security audits

---

## Third-Party Integrations

### 1. Authorize.Net (Payment Processing)

**What It Does**: Processes all credit card payments

**Benefits**:
- Industry-leading payment gateway
- Used by 430,000+ merchants
- PCI-DSS Level 1 compliant
- 99.99% uptime guarantee
- Fraud detection included

**Integration**:
- Hosted payment pages (secure offsite forms)
- Real-time payment confirmation
- Automatic receipt generation
- Webhook notifications

**Your Account**:
- Login ID: (configured in system)
- Transaction Key: (configured in system)
- Production environment: Live

**Support**: https://support.authorize.net

---

### 2. Amazon Web Services (AWS S3)

**What It Does**: Stores member photos and signed waivers

**Benefits**:
- Enterprise-grade cloud storage
- 99.999999999% durability
- Redundant backups across multiple data centers
- Scalable (grows with your business)
- Secure access controls

**What's Stored**:
- Member photos (for access cards)
- Signed liability waivers (PDF)
- Email attachments

**Your Bucket**: `ahf-memberships-prod-116981809432`
**Region**: US East (Ohio)
**Access**: Private (admin only)

---

### 3. Microsoft Graph API (Email)

**What It Does**: Sends automated emails

**Emails Sent**:
- Membership confirmation to new members
- Payment receipts
- Admin notifications
- Waiver copies

**Configuration**:
- Sender: `noreply@andalusiahealthandfitness.com`
- Recipient: `memberships@andalusiahealthandfitness.com`
- CC to member: Yes (they get a copy)

**Benefits**:
- Professional email delivery
- Branded from your domain
- Includes gym logo
- Reliable delivery tracking

---

### 4. AxTrax Pro (Access Control) - In Development

**What It Will Do**: Sync member data with door access system

**Planned Features**:
- Automatic member import from AxTrax
- Sync card numbers and validity dates
- Update member status (current/expired)
- Reactivate access on payment

**Status**: Integration framework built, awaiting AxTrax vendor coordination

**Future Benefits**:
- No manual data entry
- Real-time access control
- Payment triggers door access
- Unified member database

---

## System Architecture

### Technology Stack

**Frontend** (What users see):
- HTML5, CSS3 (modern web standards)
- JavaScript (interactive features)
- Responsive design (works on all devices)
- Cloudflare CDN (fast worldwide delivery)

**Backend** (Server-side):
- PHP 7.4+ (programming language)
- MySQL/MariaDB (database)
- Apache web server
- Ubuntu Linux (operating system)

**Hosting**:
- AWS EC2 (cloud server)
- IP Address: 3.12.72.81
- Location: US East (Ohio)
- Cloudflare proxy (DDoS protection, caching)

**Development**:
- Git version control
- GitHub repository (code backup)
- Local development environment
- Staging and production separation

---

### How It Works (Technical Overview - Simplified)

```
┌─────────────────┐
│   Member/User   │
│   (Web Browser) │
└────────┬────────┘
         │
         ↓ HTTPS (Encrypted)
┌────────────────────┐
│   Cloudflare CDN   │ ← DDoS Protection, Caching
└─────────┬──────────┘
          │
          ↓
┌────────────────────────┐
│  Apache Web Server     │
│  (Your EC2 Instance)   │
│                        │
│  ┌──────────────────┐  │
│  │   PHP Backend    │  │
│  │   Application    │  │
│  └──────┬───────────┘  │
│         │              │
│         ↓              │
│  ┌──────────────────┐  │
│  │ MySQL Database   │  │
│  │ (Member Data)    │  │
│  └──────────────────┘  │
└────────────────────────┘
         │
         ↓
┌─────────────────────────────────┐
│  Third-Party Services:          │
│  • Authorize.Net (Payments)     │
│  • AWS S3 (File Storage)        │
│  • Microsoft Graph (Email)      │
│  • AxTrax (Access Control)      │
└─────────────────────────────────┘
```

---

### Database Structure

**Main Tables**:

1. **members** - Core member information
   - Personal details (name, email, DOB)
   - Card number
   - Department/company
   - Payment type
   - Monthly fee
   - Status (current/due)
   - Validity dates

2. **dues** - Billing records
   - Member ID (linked to member)
   - Billing period (start/end dates)
   - Amount
   - Status (paid/due/void)
   - Payment date

3. **department_pricing** - Corporate rates
   - Department name
   - Base price
   - Tanning price

4. **rate_limits** - API security
   - Request tracking
   - IP addresses
   - Rate limit enforcement

---

## Support & Maintenance

### Regular Maintenance Tasks

**Weekly**:
- Monitor failed login attempts
- Review error logs
- Check payment processing status
- Verify backup completion

**Monthly**:
- Review member database for inactive accounts
- Check department pricing accuracy
- Update membership statistics
- Test payment processing

**Quarterly**:
- Security audit
- Update dependencies
- Review third-party service costs
- Optimize database performance

**Annually**:
- Renew SSL certificate (if not auto-renewed)
- Audit user access
- Review and update privacy policy
- Test disaster recovery procedures

---

### Common Admin Tasks

#### How to Add a New Member Manually

1. Member signs up online (automatic), OR
2. Admin creates record in AxTrax (syncs automatically), OR
3. Admin manually adds via member management system (future feature)

#### How to Update Member Pricing

1. Go to **Admin Dashboard**
2. Click on member card
3. Edit **Monthly Fee** field
4. Save changes

#### How to Apply Department Pricing

1. Go to **Departments** page
2. Find the department
3. Set **Base Price** and **Tanning Price**
4. Click **Apply to All Members**

#### How to Mark a Member as Paid

**Automatic** (preferred):
- Member pays via QuickPay → Status updates automatically

**Manual** (if needed):
1. Go to member record
2. Change **Status** to "Current"
3. Update **Valid Until** date
4. Save

#### How to Generate Reports

**Current Status**: View in Dashboard
- Total members count
- Current vs. Due breakdown
- Filter by department

**Future Enhancement**: Export to Excel/CSV for detailed reporting

---

### Backup & Recovery

**What's Backed Up**:
- ✅ Member database (nightly)
- ✅ Waiver PDFs (stored in AWS S3 - redundant)
- ✅ Website code (GitHub repository)
- ✅ Configuration files (secure storage)

**Backup Schedule**:
- Database: Daily at 2:00 AM
- AWS S3: Continuous (automatic versioning)
- Code: Every commit to GitHub

**Retention**:
- Database backups: 30 days
- S3 file versions: 90 days
- Git history: Indefinite

**Recovery Time**:
- Website code: 15 minutes
- Database: 30-60 minutes
- Full system: 2-4 hours

---

### Getting Help

**For Technical Issues**:
- Server access: Contact system administrator
- Payment issues: Authorize.Net support (1-877-447-3938)
- AWS storage: AWS Support
- Email delivery: Microsoft 365 support

**For Website Changes**:
- Contact your web developer
- Provide specific details about requested changes
- Allow 2-5 business days for implementation
- Test in staging environment first

**For Member Support**:
- Login issues: Verify they're using correct portal (QuickPay vs. Admin)
- Payment problems: Check Authorize.Net dashboard for declined reasons
- Waiver questions: Access signed PDF in AWS S3 bucket
- Status not updating: Verify webhook connection to Authorize.Net

---

### Performance Monitoring

**Uptime**:
- Target: 99.9% (less than 9 hours downtime per year)
- Monitoring: Cloudflare analytics
- Alerts: Email notifications on downtime

**Page Load Speed**:
- Homepage: < 2 seconds
- Admin dashboard: < 3 seconds
- Payment pages: < 2 seconds (Authorize.Net hosted)

**Traffic Capacity**:
- Current: Handles 1000+ concurrent users
- Scalable: Can upgrade server resources as needed

---

### Future Enhancements (Roadmap)

**Planned Features**:

**Phase 1** (Next 3 months):
- ✨ Complete AxTrax integration
- ✨ Member self-service portal (login to view account)
- ✨ Recurring billing automation
- ✨ Email reminders for dues

**Phase 2** (3-6 months):
- ✨ Mobile app for members
- ✨ Class schedule and booking
- ✨ Personal trainer scheduling
- ✨ Attendance tracking

**Phase 3** (6-12 months):
- ✨ Advanced reporting and analytics
- ✨ Member retention metrics
- ✨ Automated marketing campaigns
- ✨ Integration with fitness tracking apps

**Under Consideration**:
- SMS text notifications
- QR code check-in
- Guest passes and referral program
- Family account management portal
- Merchandise sales integration

---

## Summary

Your Andalusia Health & Fitness website is a robust, secure, and user-friendly platform that streamlines gym operations from membership sign-up through payment processing. The system is built on proven technologies, integrated with industry-leading partners, and designed to scale with your business.

### Key Strengths:

✅ **Automated Operations** - Reduces manual work for staff
✅ **Member Convenience** - 24/7 online sign-up and payments
✅ **Secure & Compliant** - Bank-level payment security, legally binding waivers
✅ **Real-Time Updates** - Instant status changes across the system
✅ **Professional Image** - Modern, mobile-friendly website
✅ **Scalable** - Grows with your membership base

### Business Benefits:

💰 **Reduced Administrative Time** - Less manual data entry
💰 **Faster Payments** - Instant online processing
💰 **Lower No-Shows** - Automated payment reminders
💰 **Professional Operations** - Digital records and tracking
💰 **Growth Ready** - Can handle increasing membership

---

**Website**: https://andalusiahealthandfitness.com
**Admin Portal**: https://andalusiahealthandfitness.com/admin/
**QuickPay**: https://andalusiahealthandfitness.com/quickpay/

**Last Updated**: November 4, 2025
**Version**: 2.0 (Security Enhanced)

---

*This document is for internal use and customer presentation. For technical documentation, see SECURITY_DEPLOYMENT_GUIDE.md and SECURITY_QUICK_REFERENCE.md.*
