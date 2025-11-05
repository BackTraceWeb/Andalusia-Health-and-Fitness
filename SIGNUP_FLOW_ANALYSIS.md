# Membership Signup Flow Analysis
**Andalusia Health & Fitness**
**Date**: November 5, 2025

---

## Current Status

### ✅ What's Already Built

**1. membership.html** - Frontend signup form
- Plan selection (Single $35, Couples $55, Family $65, Senior $25, Student $30, Tanning Only $45)
- Add-ons: Tanning ($27.50), Extra family members ($10/each)
- Fob count ($15 each)
- Draft signup checkbox (waives $20 initiation fee)
- Price calculator (real-time)
- Primary member info form
- Waiver count calculator
- Submits to `payments.html` via GET

**2. waiver.html** - Waiver form with signature pad
- Full waiver text displayed
- Signature pad (canvas)
- Signer info (name, email, adult/minor)
- Multi-waiver support (tracks current/total)
- ⚠️ **INCOMPLETE**: Placeholder logic only, doesn't save signatures

**3. payments.html** - Checkout confirmation
- Displays total amount
- Reads from sessionStorage
- Submits to `payments.php` (doesn't exist yet)

**4. api/complete-signup.php** - Final backend processor
- Uploads member photo to S3
- Generates waiver PDFs from signatures
- Creates membership summary PDF
- Emails everything to memberships@andalusiahealthandfitness.com
- Uses Microsoft Graph API

**5. api/payments/authorize-hosted.php** - Authorize.Net integration
- Creates hosted payment page token
- Redirects to Authorize.Net

**6. api/payments/authorize-return.php** - Payment return handler
- Processes successful payments
- Updates database

---

## ❌ What's Missing

### Critical Missing Pieces:

1. **payments.php** - Doesn't exist
   - Should integrate with `api/payments/authorize-hosted.php`
   - Should pass membership data to Authorize.Net

2. **Waiver Signature Capture**
   - waiver.html has canvas but no save logic
   - Need to capture signature as PNG
   - Need to store in sessionStorage or send to server

3. **Database Integration**
   - No code to INSERT new member into `members` table
   - Need to store: name, email, phone, address, plan, monthly_fee, etc.

4. **AxTrax Integration**
   - No code to create member in AxTrax system
   - Should happen after successful payment

5. **Thank You Page**
   - No success page after payment
   - Should display: "Welcome to Andalusia Health! Please come by and get your fob"

6. **Flow Connection**
   - membership.html → payments.html (broken)
   - waivers → sessionStorage (incomplete)
   - payments → database (missing)
   - database → email (exists but not connected)

---

## 🎯 Desired Workflow

```
┌──────────────────────────────────────────────────────────────┐
│ STEP 1: Member fills out membership.html                     │
├──────────────────────────────────────────────────────────────┤
│ - Choose plan                                                 │
│ - Select draft or pay initiation                             │
│ - Choose number of fobs                                       │
│ - Add tanning addon                                           │
│ - Enter primary member info                                   │
│ - Click "Fill Waivers Now" button                           │
└──────────────────────────────────────────────────────────────┘
                           ↓
┌──────────────────────────────────────────────────────────────┐
│ STEP 2: Waiver wizard opens in popup (waiver.html)          │
├──────────────────────────────────────────────────────────────┤
│ - Loop through each waiver (1 to N)                          │
│ - Collect: name, email, adult/minor                          │
│ - Display full waiver text                                   │
│ - Capture signature on canvas                                │
│ - Save signature as PNG to sessionStorage                    │
│ - Move to next waiver or close window                        │
└──────────────────────────────────────────────────────────────┘
                           ↓
┌──────────────────────────────────────────────────────────────┐
│ STEP 3: Return to membership.html                            │
├──────────────────────────────────────────────────────────────┤
│ - See "X / Y waivers completed" status                       │
│ - Check agreement checkbox                                   │
│ - Click "Continue to Payment"                                │
│ - Submit form → saves to sessionStorage                      │
│ - Redirect to payments.html                                  │
└──────────────────────────────────────────────────────────────┘
                           ↓
┌──────────────────────────────────────────────────────────────┐
│ STEP 4: Confirm on payments.html                             │
├──────────────────────────────────────────────────────────────┤
│ - Display: "Today's Total: $XX.XX"                           │
│ - Button: "Continue to Secure Payment"                       │
│ - Submit to payments.php                                     │
└──────────────────────────────────────────────────────────────┘
                           ↓
┌──────────────────────────────────────────────────────────────┐
│ STEP 5: payments.php processes (NEW FILE NEEDED)             │
├──────────────────────────────────────────────────────────────┤
│ - Read sessionStorage data                                   │
│ - Call api/payments/authorize-hosted.php                     │
│ - Get Authorize.Net token                                    │
│ - Redirect to Authorize.Net hosted payment page              │
└──────────────────────────────────────────────────────────────┘
                           ↓
┌──────────────────────────────────────────────────────────────┐
│ STEP 6: Authorize.Net payment page (external)                │
├──────────────────────────────────────────────────────────────┤
│ - Member enters credit card                                  │
│ - Payment processed by Authorize.Net                         │
│ - Redirect back to authorize-return.php                      │
└──────────────────────────────────────────────────────────────┘
                           ↓
┌──────────────────────────────────────────────────────────────┐
│ STEP 7: api/payments/authorize-return.php                    │
├──────────────────────────────────────────────────────────────┤
│ - Verify payment successful                                  │
│ - INSERT member into database                                │
│ - Create member in AxTrax (if credentials available)         │
│ - Upload waivers + photo to S3                               │
│ - Generate PDFs                                              │
│ - Email to memberships@andalusiahealthandfitness.com         │
│ - Redirect to thank-you.html                                 │
└──────────────────────────────────────────────────────────────┘
                           ↓
┌──────────────────────────────────────────────────────────────┐
│ STEP 8: thank-you.html (SUCCESS!)                            │
├──────────────────────────────────────────────────────────────┤
│ "Welcome to Andalusia Health & Fitness!                      │
│  Please come by and get your fob."                           │
│                                                               │
│ Email sent to: memberships@andalusiahealthandfitness.com     │
└──────────────────────────────────────────────────────────────┘
```

---

## 📋 Implementation Checklist

### Phase 1: Fix Waiver Capture ⏳
- [ ] Add signature_pad.js library to waiver.html
- [ ] Implement signature save to PNG
- [ ] Store signatures in sessionStorage as base64
- [ ] Track waiver completion status
- [ ] Update "X / Y completed" counter in membership.html

### Phase 2: Create payments.php ⏳
- [ ] Create new payments.php file
- [ ] Read membership data from sessionStorage
- [ ] Call api/payments/authorize-hosted.php
- [ ] Redirect to Authorize.Net with token

### Phase 3: Database Integration ⏳
- [ ] Update api/payments/authorize-return.php
- [ ] Add INSERT INTO members (...)
- [ ] Calculate valid_until date (today + 30 days)
- [ ] Set status = 'current'
- [ ] Set payment_type = 'draft' if checkbox was checked

### Phase 4: Complete Signup Backend ⏳
- [ ] Move waiver/photo upload logic to authorize-return.php
- [ ] Read signatures from POST data
- [ ] Generate PDFs
- [ ] Upload to S3
- [ ] Email memberships@

### Phase 5: AxTrax Integration ⏳
- [ ] Add AxTrax member creation in authorize-return.php
- [ ] Use existing api/integrations/axtrax/client.php
- [ ] Create member with valid_until date
- [ ] Handle errors gracefully

### Phase 6: Thank You Page ⏳
- [ ] Create thank-you.html
- [ ] Display success message
- [ ] Show next steps (come get fob)
- [ ] Link to homepage

### Phase 7: Testing ⏳
- [ ] Test full flow end-to-end
- [ ] Test with different plans (Single, Family, etc.)
- [ ] Test with/without draft signup
- [ ] Test with multiple waivers
- [ ] Verify email delivery
- [ ] Verify database records
- [ ] Test payment success/failure scenarios

---

## 🔧 Files to Create/Modify

### New Files Needed:
1. `payments.php` - Payment integration page
2. `thank-you.html` - Success page
3. `js/signature_pad.min.js` - Signature library (or CDN)

### Files to Modify:
1. `waiver.html` - Add signature capture logic
2. `membership.html` - Fix form submission to save to sessionStorage
3. `api/payments/authorize-return.php` - Add database insert + email trigger
4. `payments.html` - Update to properly pass data to payments.php

---

## 💾 Database Schema

### members table structure needed:
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
    card_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## 🎨 sessionStorage Data Structure

```javascript
{
  // From membership.html
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "phone": "(334) 555-1234",
  "address1": "123 Main St",
  "city": "Andalusia",
  "state": "AL",
  "zip": "36420",
  "plan": "Single|35",
  "add_tanning": true,
  "extra_members": 0,
  "fob_count": 1,
  "waive_initiation": false,
  "monthly_total": "62.50",
  "today_total": "90.00",
  "waiver_count": 1,

  // From waiver.html
  "waivers": [
    {
      "name": "John Doe",
      "email": "john@example.com",
      "sig_type": "adult",
      "signature_png": "data:image/png;base64,iVBORw0KGgoAAAANS..."
    }
  ]
}
```

---

## 🚀 Next Steps

**Priority 1 (Critical Path):**
1. Fix waiver signature capture
2. Create payments.php
3. Add database insert to authorize-return.php
4. Create thank-you.html
5. Test end-to-end

**Priority 2 (Enhancement):**
1. Add AxTrax integration
2. Add photo upload
3. Better error handling
4. Email confirmation to member (CC)

---

**Last Updated**: November 5, 2025
**Status**: Ready to implement
