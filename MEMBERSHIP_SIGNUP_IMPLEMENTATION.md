# Membership Signup Flow - Implementation Complete ✅

**Andalusia Health & Fitness**
**Date**: November 5, 2025
**Status**: ✅ Ready for Testing

---

## Overview

The complete membership signup workflow has been implemented. Members can now:
1. Choose a membership plan and add-ons
2. Fill out and sign waivers digitally
3. Pay securely via Authorize.Net
4. Receive automatic email confirmation to staff
5. See a welcoming thank you page

---

## What Was Built

### 1. ✅ Waiver Signature Capture (waiver.html)
**File**: `waiver.html`

**Improvements Made**:
- Added signature_pad.js library (CDN)
- Implemented signature capture as PNG base64
- Scroll-to-enable feature (must read waiver before signing)
- Multi-waiver support (loops for Family plans)
- Save signatures to sessionStorage
- Notify parent window of completion
- Clear and Undo buttons
- Auto-close after final waiver

**Flow**:
```
Member clicks "Fill Waivers Now" → Opens waiver.html popup
→ Scroll through waiver text → Canvas enables
→ Sign and submit → Saves to sessionStorage['ahfSignup'].waivers[]
→ Next waiver or close window
→ Parent window updated with "X / Y completed"
```

---

### 2. ✅ Membership Form Integration (membership.html)
**File**: `membership.html`

**Improvements Made**:
- Listen for waiver completion messages (postMessage API)
- Update waiver status counter dynamically
- Validate all waivers completed before submission
- Save complete form data to sessionStorage
- Redirect to payments.html (not GET submission)

**Data Saved to sessionStorage**:
```javascript
{
  first_name, last_name, email, phone, address1, city, state, zip,
  plan, plan_amount, add_tanning, extra_members, fob_count,
  waive_initiation, waiver_count, monthly_total, today_total,
  waivers: [{ name, email, sig_type, signature_png }, ...]
}
```

---

### 3. ✅ Payment Bridge (payments.php)
**File**: `payments.php` (NEW)

**Purpose**: Frontend page that reads sessionStorage and calls backend API

**Features**:
- Reads signup data from sessionStorage
- Validates data exists
- Creates invoice number (MS + timestamp)
- POSTs to `api/payments/authorize-membership.php`
- Receives Authorize.Net token
- Auto-redirects to Authorize.Net hosted payment page

**Error Handling**:
- Missing data → redirect to membership.html
- API errors → display error message
- Network errors → user-friendly message

---

### 4. ✅ Authorize.Net Membership API (api/payments/authorize-membership.php)
**File**: `api/payments/authorize-membership.php` (NEW)

**Purpose**: Backend API that creates Authorize.Net hosted payment token

**Features**:
- Accepts POST with amount, invoice, customer info
- Calls Authorize.Net getHostedPaymentPageRequest API
- Sets return URL to `authorize-return.php?type=membership`
- Sets cancel URL to membership.html
- Logs requests and responses
- Returns token as JSON

**Return URLs**:
- Success: `authorize-return.php?type=membership`
- Cancel: `membership.html`

---

### 5. ✅ Payment Return Handler (authorize-return.php)
**File**: `api/payments/authorize-return.php` (MODIFIED)

**Improvements Made**:
- Detects flow type: `?type=membership` vs QuickPay
- Routes to `authorize-return-membership.php` for membership signups
- Keeps existing QuickPay flow intact

**Flow Detection**:
```php
$flowType = $_GET['type'] ?? 'quickpay';
if ($flowType === 'membership') {
    include __DIR__ . '/authorize-return-membership.php';
    exit;
}
// Continue with QuickPay flow...
```

---

### 6. ✅ Membership Return Page (authorize-return-membership.php)
**File**: `api/payments/authorize-return-membership.php` (NEW)

**Purpose**: Frontend page shown after successful payment

**Features**:
- Displays "Payment Successful" message
- Reads sessionStorage data via JavaScript
- POSTs to `api/process-membership.php`
- Redirects to `thank-you.html` on success
- Clear sessionStorage after success
- Error handling with helpful messages

---

### 7. ✅ Membership Processor (api/process-membership.php)
**File**: `api/process-membership.php` (NEW)

**Purpose**: Backend processor that creates member record and sends email

**Features**:

**Database Integration**:
- INSERTs new member into `members` table
- Sets `valid_until` = today + 30 days
- Sets `status` = 'current'
- Sets `payment_type` = 'draft' or 'manual'
- Logs signup to `logs/membership-signups.log`

**Email to Staff**:
- Sends email to `memberships@andalusiahealthandfitness.com`
- Uses Microsoft Graph API
- HTML and plain text versions
- Includes:
  - Member info (name, email, phone, address)
  - Membership details (plan, monthly dues, payment type)
  - Payment info (today's total, invoice, status)
  - Next steps for staff (program fobs, setup draft if needed)

**Error Handling**:
- Database errors logged and returned as JSON
- Email errors logged but don't fail signup
- Returns success with member ID

---

### 8. ✅ Thank You Page (thank-you.html)
**File**: `thank-you.html` (NEW)

**Features**:
- Beautiful success page with animated checkmark
- Personalized with member email (from sessionStorage)
- Next steps listed:
  - Visit gym to pick up fobs
  - Bring driver's license
  - Staff will program fob
  - Enjoy 24/7 access
- CTA buttons:
  - Return Home
  - Call Us: (334) 582-2000
- Contact info:
  - Gym address with Google Maps link
  - Email: memberships@andalusiahealthandfitness.com
- Mobile responsive design

---

## Complete Workflow

```
┌────────────────────────────────────────────────────────────────┐
│ STEP 1: membership.html - Member fills out form               │
├────────────────────────────────────────────────────────────────┤
│ - Choose plan (Single $35, Couples $55, Family $65, etc.)     │
│ - Add tanning ($27.50), extra family members ($10 each)        │
│ - Select fob count ($15 each)                                  │
│ - Choose draft signup (waives $20 initiation)                  │
│ - Enter member info (name, email, phone, address)              │
│ - Click "Fill Waivers Now"                                     │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ STEP 2: waiver.html - Popup waiver wizard                     │
├────────────────────────────────────────────────────────────────┤
│ - Loop through waivers (1 to N based on plan)                 │
│ - Scroll through waiver text to enable signing                 │
│ - Sign with signature pad                                      │
│ - Save as PNG to sessionStorage                                │
│ - Next waiver or close window                                  │
│ - Parent window updates "X / Y completed"                      │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ STEP 3: membership.html - Validate and submit                 │
├────────────────────────────────────────────────────────────────┤
│ - Verify all waivers completed                                 │
│ - Check agreement checkbox                                     │
│ - Click "Continue to Payment"                                  │
│ - Save all data to sessionStorage                              │
│ - Redirect to payments.html                                    │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ STEP 4: payments.html - Confirm amount                        │
├────────────────────────────────────────────────────────────────┤
│ - Display "Today's Total: $XX.XX"                              │
│ - Click "Continue to Secure Payment"                           │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ STEP 5: payments.php - Bridge to Authorize.Net                │
├────────────────────────────────────────────────────────────────┤
│ - Read sessionStorage data                                     │
│ - POST to api/payments/authorize-membership.php                │
│ - Get token response                                           │
│ - Auto-redirect to Authorize.Net                               │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ STEP 6: Authorize.Net (External) - Payment                    │
├────────────────────────────────────────────────────────────────┤
│ - Member enters credit card on secure page                     │
│ - Authorize.Net processes payment                              │
│ - Redirect to authorize-return.php?type=membership            │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ STEP 7: authorize-return-membership.php - Process signup      │
├────────────────────────────────────────────────────────────────┤
│ - Display "Payment Successful"                                 │
│ - Read sessionStorage via JavaScript                           │
│ - POST to api/process-membership.php                           │
│   → INSERT into members table                                  │
│   → Send email to memberships@andalusiahealthandfitness.com   │
│ - Clear sessionStorage                                         │
│ - Redirect to thank-you.html                                   │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ STEP 8: thank-you.html - Success!                             │
├────────────────────────────────────────────────────────────────┤
│ "Welcome to Andalusia Health & Fitness!                        │
│  Please come by and get your fob."                             │
│                                                                 │
│ Email sent to: memberships@andalusiahealthandfitness.com       │
│ Member record created in database                              │
└────────────────────────────────────────────────────────────────┘
```

---

## Files Created

### New Files:
1. `payments.php` - Payment bridge page
2. `api/payments/authorize-membership.php` - Authorize.Net API for memberships
3. `api/payments/authorize-return-membership.php` - Membership return handler
4. `api/process-membership.php` - Database insert and email sender
5. `thank-you.html` - Success page

### Modified Files:
1. `waiver.html` - Added signature capture logic
2. `membership.html` - Added sessionStorage save and waiver validation
3. `api/payments/authorize-return.php` - Added flow routing

---

## Database Schema

### members table (required fields):
```sql
CREATE TABLE IF NOT EXISTS members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    email VARCHAR(255),
    phone VARCHAR(20),
    address1 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(50),
    zip VARCHAR(20),
    plan_name VARCHAR(100),
    monthly_fee DECIMAL(10,2),
    payment_type ENUM('draft', 'manual') DEFAULT 'manual',
    status ENUM('current', 'due', 'cancelled') DEFAULT 'current',
    valid_until DATE,
    fob_count INT DEFAULT 1,
    has_tanning BOOLEAN DEFAULT FALSE,
    initiation_paid BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Configuration Requirements

### api/config.php must define:
```php
// Authorize.Net
define('AUTH_LOGIN_ID', 'your_login_id');
define('AUTH_TRANSACTION_KEY', 'your_transaction_key');
define('AUTH_API_URL', 'https://test.authorize.net/xml/v1/request.api'); // or production

// Microsoft Graph (for emails)
define('GRAPH_TENANT_ID', 'your_tenant_id');
define('GRAPH_CLIENT_ID', 'your_client_id');
define('GRAPH_CLIENT_SECRET', 'your_client_secret');
define('GRAPH_SENDER_UPN', 'sender@domain.com');
define('GRAPH_RECIPIENT', 'memberships@andalusiahealthandfitness.com');
```

---

## Testing Checklist

### Before Production:
- [ ] Test with Single membership ($35)
- [ ] Test with Couples membership ($55) - verify 2 waivers
- [ ] Test with Family membership ($65) - verify 3+ waivers
- [ ] Test with extra family members (should add $10/each and extra waivers)
- [ ] Test with Tanning add-on ($27.50 added to monthly)
- [ ] Test with draft signup (should waive $20 initiation fee)
- [ ] Test without draft signup (should add $20 to today's total)
- [ ] Test with multiple fobs (should add $15 each)
- [ ] Verify signature capture works on mobile
- [ ] Verify email arrives at memberships@andalusiahealthandfitness.com
- [ ] Verify member record created in database
- [ ] Verify valid_until date is 30 days from signup
- [ ] Verify payment_type is 'draft' or 'manual' correctly
- [ ] Test payment cancellation (should return to membership.html)
- [ ] Test error handling (network issues, API failures)

### Switch to Production:
- [ ] Update AUTH_API_URL to production Authorize.Net endpoint
- [ ] Use production Authorize.Net credentials
- [ ] Test with real credit card ($1 test charge recommended)
- [ ] Verify emails send correctly
- [ ] Monitor logs for first few signups

---

## Important Notes

### ✅ What This Flow Does:
- Collects member information and plan selection
- Captures digital signatures on waivers
- Processes payment via Authorize.Net
- Saves member to database
- Sends email to staff
- Shows thank you page

### ❌ What This Flow Does NOT Do:
- **Does NOT integrate with AxTrax** (per user requirement: "the quickpay workflow is the only one that calls the axtrax rest api")
- Staff must manually program fobs in AxTrax
- Staff must manually set up draft payment in bank system if member chose draft option

### Manual Steps Required:
1. Check email for new signup notification
2. Program fobs in AxTrax system
3. If draft signup: Set up auto-draft in bank system
4. Wait for member to come pick up fob (bring ID)

---

## Logs and Debugging

### Log Files:
- `logs/membership-signups.log` - One line per signup with member info
- `logs/authorize-membership-YYYY-MM-DD.json` - Authorize.Net API requests/responses
- `logs/authorize-return.log` - Payment return events

### Debugging Tips:
1. Check browser console for JavaScript errors
2. Check sessionStorage in DevTools: `sessionStorage.getItem('ahfSignup')`
3. Check server logs: `tail -f logs/membership-signups.log`
4. Verify email credentials in api/config.php
5. Test Authorize.Net credentials with sandbox first

---

## Security Features

### Payment Security:
- ✅ Credit cards never touch our server
- ✅ Authorize.Net PCI-compliant hosted payment page
- ✅ HTTPS connections required
- ✅ Sensitive data in sessionStorage (cleared after signup)

### Data Security:
- ✅ Database credentials in config file (not in git)
- ✅ API credentials in config file (not in git)
- ✅ Logs directory protected by .htaccess
- ✅ Input validation on all forms

---

## Support and Troubleshooting

### Common Issues:

**"No membership data found"**
- sessionStorage was cleared or expired
- Member refreshed payment page
- Solution: Restart from membership.html

**Email not arriving**
- Check Microsoft Graph credentials
- Check GRAPH_RECIPIENT email address
- Check spam folder
- Check logs for Graph API errors

**Payment successful but no database record**
- Check database connection
- Check api/process-membership.php logs
- Manually insert member using invoice number

**Signature pad not working**
- Check signature_pad.js CDN loaded
- Check browser console for errors
- Try on desktop browser first

---

## Next Steps (Optional Enhancements)

### Future Improvements:
1. Add photo upload during signup
2. Attach waiver PDFs to email
3. Member confirmation email (CC to member)
4. AxTrax auto-integration (if API available)
5. Draft payment setup via API
6. Email receipt to member
7. Admin dashboard to view signups
8. Signature pad on mobile optimization

---

**Status**: ✅ Complete and ready for testing
**Last Updated**: November 5, 2025
**Implementation Time**: ~2 hours

---

## Quick Start

**To test the flow:**
1. Go to https://andalusiahealthandfitness.com/membership.html
2. Fill out form and select a plan
3. Click "Fill Waivers Now" and complete signatures
4. Click "Continue to Payment"
5. Use Authorize.Net test card: 4111111111111111
6. Check email at memberships@andalusiahealthandfitness.com
7. Verify member in database: `SELECT * FROM members ORDER BY id DESC LIMIT 1;`

**Done!** 🎉
