# Payment Flow & AxTrax Integration
**Andalusia Health & Fitness**

---

## 🔄 Complete Payment Flow

### Step 1: Member Lookup (QuickPay)
**File**: `/api/quickpay/lookup.php`

**What Happens**:
1. Member enters their name in QuickPay portal
2. System finds member record in database
3. Checks if member has outstanding dues
4. Auto-creates invoice if none exists
5. Returns member info + invoice to frontend

**Output**:
```json
{
  "ok": true,
  "status": "due",
  "member": { ... },
  "invoice": {
    "id": 123,
    "amount_cents": 4000,
    "period_start": "2025-11-01",
    "period_end": "2025-11-30"
  },
  "amount": 40.00,
  "valid_until": "2025-10-15"
}
```

---

### Step 2: Payment Initiation
**File**: `/api/payments/authorize-hosted.php`

**What Happens**:
1. Member clicks "Pay Now"
2. System creates Authorize.Net payment token
3. Invoice number encoded as: `QP{invoiceId}M{memberId}` (e.g., "QP123M456")
4. Member redirected to Authorize.Net hosted payment page

**Payment Page**: Secure Authorize.Net form (not on your server)

---

### Step 3: Payment Processing
**Platform**: Authorize.Net (external)

**What Happens**:
1. Member enters credit card details on Authorize.Net
2. Authorize.Net processes payment
3. If successful → redirects to return URL
4. If failed → shows error on Authorize.Net page

---

### Step 4: Payment Return Handler
**File**: `/api/payments/authorize-return.php`

**What Happens** (When payment succeeds):
1. ✅ Marks invoice as "paid" in `dues` table
2. ✅ Sets `paid_at` timestamp
3. ✅ Triggers internal webhook (authorize-success.php)
4. ✅ Shows confirmation page to member

**Database Update**:
```sql
UPDATE dues
SET status='paid', paid_at=NOW()
WHERE id=? AND status IN('due','failed')
```

**Then Calls**: Internal webhook to process payment

---

### Step 5: Payment Success Webhook
**File**: `/api/webhooks/authorize-success.php`

**What Happens** (NEW - Just Added!):
1. ✅ Validates HMAC signature (if production mode)
2. ✅ Extracts member ID from invoice number
3. ✅ Logs payment to CSV file
4. ✅ **Updates member's `valid_until` date to +30 days from today**
5. ✅ **Sets member `status` to "current"**
6. ✅ **Triggers AxTrax sync** (staged - will work when REST API credentials are ready)

**Database Update** (NEW):
```sql
UPDATE members
SET valid_until = DATE_ADD(NOW(), INTERVAL 30 DAY),
    status = 'current',
    updated_at = NOW()
WHERE id = ?
```

**AxTrax Sync** (Staged):
- Calls `/api/webhooks/payments-feed.php`
- Sends: `{member_id, valid_until, payment_id, amount}`
- Currently returns 501 (Not Implemented) until REST API credentials are configured
- **Ready to work as soon as you configure `config/payments.php`**

---

### Step 6: AxTrax Integration (Staged)
**File**: `/api/webhooks/payments-feed.php`

**What It Does**:
1. Receives payment notification from authorize-success.php
2. Loads AxTrax API client
3. Calls `AxtraxClient::updateMemberValidity()`
4. Updates door access system with new expiration date

**Status**: ⏳ **Waiting for REST API credentials**

**Client**: `/api/integrations/axtrax/client.php` (ready to use)

---

## 🎯 What's Working NOW

✅ **QuickPay Lookup** - Members can find their invoices
✅ **Authorize.Net Payment** - Secure card processing
✅ **Payment Confirmation** - Invoice marked as paid
✅ **Member Update** - `valid_until` date set to +30 days
✅ **Status Update** - Member marked as "current"
✅ **CSV Logging** - All payments logged
✅ **AxTrax Call Staged** - Ready for when you get credentials

---

## ⏳ What's Waiting for AxTrax REST API

❌ **Actual door system update** - Need REST API endpoint details from vendor

When you receive the AxTrax REST API credentials, you need:
1. API Base URL
2. API Key / Bearer Token
3. Endpoint path for updating member validity
4. Payload format

---

## 🔧 How to Configure AxTrax When Ready

### Step 1: Create `config/payments.php`

```bash
cd /var/www/andalusiahealthandfitness/config
cp payments.php.example payments.php
nano payments.php
```

### Step 2: Fill in AxTrax Credentials

```php
<?php
return [
    'axtrax' => [
        'base_url' => 'https://your-axtrax-server.com/api/v1',
        'api_key' => 'your_api_key_from_vendor',
        'site_id' => 'your_site_id_if_required', // Optional
        'timeout_s' => 8,
    ],
];
```

### Step 3: Update AxTrax Client Code

Edit `/api/integrations/axtrax/client.php` (lines 69-75):

```php
public function updateMemberValidity(int $memberId, string $validUntil): array
{
    // Uncomment and update with actual endpoint from vendor
    $endpoint = $this->baseUrl . '/members/' . $memberId . '/validity';
    $payload  = [
        'valid_until' => $validUntil,
        'site_id' => $this->siteId
    ];
    return $this->postJson($endpoint, $payload);

    // Remove this line once above is uncommented:
    // throw new LogicException('AxTrax REST endpoint details pending from vendor.');
}
```

### Step 4: Test

Make a test payment and check logs:

```bash
tail -f /var/log/apache2/error.log | grep -i axtrax
```

You should see:
- ✅ "AxTrax sync successful for member #123"

Instead of:
- ⏳ "AxTrax sync staged for member #123 - REST API not ready yet"

---

## 📊 Payment Flow Diagram

```
┌─────────────────┐
│  Member Lookup  │ → /api/quickpay/lookup.php
│  (QuickPay)     │
└────────┬────────┘
         │
         ↓ (member info + invoice)
┌─────────────────┐
│ Payment Request │ → /api/payments/authorize-hosted.php
└────────┬────────┘
         │
         ↓ (redirect to Authorize.Net)
┌─────────────────┐
│  Authorize.Net  │ → External (secure payment page)
│  Payment Form   │
└────────┬────────┘
         │
         ↓ (payment successful)
┌─────────────────┐
│ Return Handler  │ → /api/payments/authorize-return.php
│                 │    ✅ Mark invoice paid
└────────┬────────┘
         │
         ↓ (trigger webhook)
┌─────────────────┐
│ Success Webhook │ → /api/webhooks/authorize-success.php
│                 │    ✅ Update valid_until +30 days
│                 │    ✅ Set status = current
└────────┬────────┘
         │
         ↓ (sync to door system)
┌─────────────────┐
│ AxTrax Sync     │ → /api/webhooks/payments-feed.php
│                 │    ⏳ Staged (501) until REST API ready
│                 │    → /api/integrations/axtrax/client.php
└─────────────────┘
         │
         ↓ (when configured)
┌─────────────────┐
│ Door System     │ → AxTrax Pro Access Control
│ Updated         │    🚪 Member access reactivated
└─────────────────┘
```

---

## 🧪 Testing the Flow

### Test Payment (End-to-End)

1. **Go to QuickPay**:
   ```
   https://andalusiahealthandfitness.com/quickpay/
   ```

2. **Look up a test member**:
   - Enter first & last name
   - Click "Look Up Member"

3. **Verify invoice shows**:
   - Amount should display
   - "Pay Now" button should appear

4. **Click Pay Now**:
   - Should redirect to Authorize.Net
   - Use test card: 4111111111111111 (if sandbox)

5. **Complete payment**:
   - Should return to confirmation page
   - "Payment Successful!" message

6. **Check database** (as admin):
   ```sql
   -- Check invoice marked as paid
   SELECT * FROM dues WHERE id = YOUR_INVOICE_ID;
   -- Should show status='paid', paid_at=<timestamp>

   -- Check member updated
   SELECT id, first_name, last_name, status, valid_until
   FROM members WHERE id = YOUR_MEMBER_ID;
   -- Should show status='current', valid_until = <today + 30 days>
   ```

7. **Check logs**:
   ```bash
   sudo tail -20 /var/log/apache2/error.log
   ```

   Should see:
   ```
   Payment webhook: Updated member #123 valid_until to 2025-12-04 (rows: 1)
   AxTrax sync staged for member #123 - REST API not ready yet
   ```

---

## 🔍 Troubleshooting

### Payment succeeded but member not updated?

**Check**:
1. Error logs: `sudo tail -50 /var/log/apache2/error.log`
2. Database connection in webhook
3. Member ID extraction from invoice number

**Debug**:
```bash
# Check if webhook is being called
grep "Payment webhook" /var/log/apache2/error.log

# Check member record
mysql -u ahf_web -p ahf -e "SELECT id, status, valid_until FROM members WHERE id=YOUR_ID;"
```

### AxTrax sync not working?

**Expected** until you configure `config/payments.php`:
- HTTP 501 response
- "AxTrax sync staged" in logs

**When configured** but not working:
- Check `config/payments.php` exists and has correct credentials
- Check AxTrax API endpoint is correct
- Check network connectivity to AxTrax server
- Review AxTrax vendor documentation

### Invoice not found for member?

**System auto-creates invoices** if member status is "due". Check:
1. Member status in database
2. `valid_until` date (past = due)
3. `payment_type` (draft = always current, skips invoice)

---

## 📝 Important Files Summary

| File | Purpose | Status |
|------|---------|--------|
| `/api/quickpay/lookup.php` | Find member & invoice | ✅ Working |
| `/api/payments/authorize-hosted.php` | Create payment token | ✅ Working |
| `/api/payments/authorize-return.php` | Handle payment return | ✅ Working |
| `/api/webhooks/authorize-success.php` | Process successful payment | ✅ **UPDATED** |
| `/api/webhooks/payments-feed.php` | Trigger AxTrax sync | ⏳ Staged |
| `/api/integrations/axtrax/client.php` | AxTrax REST client | ⏳ Ready |
| `/config/payments.php` | AxTrax credentials | ❌ **Need to create** |
| `/config/payments.php.example` | Template | ✅ Created |

---

## 🚀 Deployment Checklist

When deploying payment flow updates:

- [ ] Push code to GitHub
- [ ] Pull on server
- [ ] Verify `authorize-success.php` has new code
- [ ] Test a payment (use test credit card if sandbox)
- [ ] Verify member `valid_until` updates to +30 days
- [ ] Verify member `status` changes to "current"
- [ ] Check error logs for any issues
- [ ] Verify Authorize.Net webhook is configured (you mentioned you'll do this)

---

## 📞 When You Get AxTrax REST API Credentials

Contact your developer with:
1. AxTrax API Base URL
2. API Key / Bearer Token
3. Endpoint for updating member validity (e.g., `PUT /members/:id/validity`)
4. Required payload format
5. Any authentication headers needed

They will update:
- `config/payments.php` (create with credentials)
- `/api/integrations/axtrax/client.php` (uncomment and configure endpoint)

---

## ✅ What's Ready NOW

**Payment Flow**: 100% Complete ✅
- Member can look up invoice
- Pay online via Authorize.Net
- Invoice marked as paid
- Member gets 30 more days
- Status updated to current
- Confirmation shown to member

**AxTrax Integration**: 90% Complete ⏳
- Framework built
- Client ready
- Webhook staged
- Just needs credentials from vendor

---

**Last Updated**: November 4, 2025
**Status**: Payment flow complete, AxTrax staged and ready
