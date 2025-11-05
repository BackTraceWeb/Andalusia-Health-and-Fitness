# Payment Webhook Flow Architecture

## Overview

This document describes the correct payment webhook flow for Andalusia Health & Fitness. The architecture ensures that AxTrax (door access control system) is the source of truth for member access status.

## Current Status

**Status**: ✅ Architecture implemented, awaiting AxTrax REST API configuration
**Date**: 2025-11-05
**Mode**: TEMPORARY fallback - updates our DB directly until AxTrax is configured

## The Correct Flow

### 1. User Completes Payment

**QuickPay Flow** (Existing member paying dues):
```
User → QuickPay Portal → authorize-hosted.php → Authorize.Net Hosted Payment Page → Payment Success
```

**Membership Signup Flow** (New member):
```
User → Membership Form → Waiver → payments.php → Authorize.Net Hosted Payment Page → Payment Success
```

### 2. Authorize.Net Webhook

After successful payment, Authorize.Net sends a webhook to:
```
POST https://andalusiahealthandfitness.com/api/webhooks/authorize-success.php
```

**What this endpoint does:**
- ✅ Validates HMAC signature (production mode only)
- ✅ Logs payment to CSV (`payments.csv`)
- ✅ Parses member ID from invoice number (format: `QP{duesId}M{memberId}`)
- ✅ Calls AxTrax REST API with updated `valid_until` date
- ⏳ **TEMPORARY**: Updates our database directly until AxTrax is configured

### 3. AxTrax REST API Call

Our webhook calls AxTrax REST API:
```php
$axtrax = AxtraxClient::buildFromConfig();
$axtraxResponse = $axtrax->updateMemberValidity($memberId, $validUntil);
```

**What AxTrax does** (when configured):
- Updates its door access control database
- Sets member access card validity period
- Activates/reactivates member access
- Calls back to our webhook endpoint

### 4. AxTrax Callback

After AxTrax updates its database, it calls back to:
```
POST https://andalusiahealthandfitness.com/api/webhooks/axtrax-callback.php
Authorization: Bearer {AXTRAX_CALLBACK_TOKEN}
Content-Type: application/json

{
  "member_id": 1234,
  "valid_until": "2025-12-05",
  "invoice_id": 567 (optional)
}
```

**What this endpoint does:**
- ✅ Validates bearer token authentication
- ✅ Updates member record: `status='current'`, `valid_until=<date>`
- ✅ Marks invoice as paid (if `invoice_id` provided)
- ✅ Returns success confirmation

### 5. User Return Flow

After payment, user is redirected to:
```
GET https://andalusiahealthandfitness.com/api/payments/authorize-return.php?memberId={id}&invoiceId={id}
```

**What this endpoint does:**
- ✅ Marks invoice as paid in database (immediate user feedback)
- ✅ Displays success page to user
- ⚠️ Does NOT update member record (that comes from AxTrax callback)

## Why This Architecture?

### The Problem with Direct Updates

Previously, our webhook updated the member database directly:
```
❌ Authorize.Net → Our Webhook → Update Our Database → Try to sync with AxTrax
```

**Issues:**
- Our database and AxTrax database could become out of sync
- If AxTrax update failed, member has database access but no door access
- No guarantee that card activation succeeded
- Difficult to reconcile discrepancies

### The Solution: AxTrax as Source of Truth

New architecture makes AxTrax the authoritative source:
```
✅ Authorize.Net → Our Webhook → AxTrax REST API → AxTrax Updates → AxTrax Callback → Update Our Database
```

**Benefits:**
- ✅ AxTrax confirms card activation before we update our database
- ✅ Single source of truth for member access status
- ✅ Guaranteed synchronization between systems
- ✅ Easier troubleshooting and reconciliation
- ✅ Member access is never granted without door access

## File Reference

### Modified Files

1. **`api/webhooks/authorize-success.php`**
   - Calls AxTrax REST API instead of direct DB update
   - TEMPORARY: Falls back to direct update until AxTrax configured
   - Lines 74-126

2. **`api/payments/authorize-return.php`**
   - Removed internal webhook trigger
   - Only marks invoice as paid for user feedback
   - Lines 100-105

### New Files

3. **`api/webhooks/axtrax-callback.php`** ✨ NEW
   - Receives callbacks from AxTrax
   - Updates member record after AxTrax confirms
   - Marks invoice as paid
   - Bearer token authentication

### Supporting Files

4. **`api/integrations/axtrax/client.php`**
   - AxTrax REST API client
   - `updateMemberValidity()` method
   - Awaiting REST API specs from vendor

5. **`config/payments.php`**
   - Contains AxTrax configuration
   - API credentials, base URL, site ID

## Configuration

### Environment Variables

Add to `config/.env.php`:

```php
// AxTrax callback authentication token
'AXTRAX_CALLBACK_TOKEN' => 'generate-secure-random-token-here',
```

### AxTrax Configuration

Update `config/payments.php` when REST API credentials are available:

```php
'axtrax' => [
    'base_url' => 'https://axtrax-api-url.com',
    'api_key' => 'your-api-key-here',
    'site_id' => 'your-site-id',
    'timeout_s' => 8
],
```

### AxTrax Setup Required

Provide AxTrax with our callback endpoint:
```
URL: https://andalusiahealthandfitness.com/api/webhooks/axtrax-callback.php
Method: POST
Authentication: Bearer {AXTRAX_CALLBACK_TOKEN}
Content-Type: application/json
```

## Testing

### Test in Sandbox Mode

1. Set sandbox mode in `config/.env.php`:
```php
'AUTH_ENV' => 'SANDBOX',
'AUTH_API_URL' => 'https://apitest.authorize.net/xml/v1/request.api',
```

2. Make a test payment through QuickPay
3. Check logs:
   - `logs/authorize-return.log` - Return handler
   - PHP error_log - Webhook processing
   - `api/webhooks/payments.csv` - Payment records

### Test AxTrax Callback (Manual)

```bash
curl -X POST https://andalusiahealthandfitness.com/api/webhooks/axtrax-callback.php \
  -H "Authorization: Bearer {AXTRAX_CALLBACK_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "member_id": 1234,
    "valid_until": "2025-12-05",
    "invoice_id": 567
  }'
```

Expected response:
```json
{
  "success": true,
  "member_id": 1234,
  "member_updated": true,
  "invoice_updated": true
}
```

## Monitoring

### Log Files

- `api/webhooks/payments.csv` - All successful payments
- `logs/authorize-return.log` - Return handler activity
- PHP error_log - Webhook errors and processing

### Key Log Messages

Look for these in error_log:

```
✅ "Payment webhook: Calling AxTrax REST API for member #1234"
⏳ "AxTrax REST API not configured yet (expected)"
✅ "TEMPORARY: Direct DB update for member #1234"
✅ "AxTrax callback received: {...}"
✅ "AxTrax callback: Updated member #1234"
```

## Troubleshooting

### AxTrax Not Configured Yet

**Symptom**: Logs show "AxTrax REST API not configured yet"
**Status**: Expected behavior - temporary fallback active
**Action**: No action needed - system updates DB directly

### Member Updated But Invoice Still "Due"

**Symptom**: Member shows `status='current'` but invoice shows `status='due'`
**Cause**: Return handler failed (missing parameters)
**Fix**: Check `authorize-return.log` for errors

### AxTrax Callback Returns 401

**Symptom**: AxTrax logs show "Unauthorized" response
**Cause**: Bearer token mismatch
**Fix**: Verify `AXTRAX_CALLBACK_TOKEN` matches in both systems

### Database Update Failed

**Symptom**: Callback returns 500 error
**Cause**: Database connection or query error
**Fix**: Check error_log for PDO exceptions

## Migration Plan

### Phase 1: Architecture Ready ✅ (Current)
- Webhook calls AxTrax REST API
- Callback endpoint created and secured
- TEMPORARY fallback updates DB directly
- Documentation complete

### Phase 2: AxTrax Configuration (Pending)
- Wait for REST API credentials from vendor
- Update `config/payments.php` with credentials
- Provide callback URL to AxTrax
- Test REST API connectivity

### Phase 3: Go Live (Future)
- Remove TEMPORARY direct DB update code
- Monitor logs for successful callbacks
- Verify member access cards activate correctly
- Update documentation with actual API behavior

### Phase 4: Cleanup (Future)
- Remove temporary fallback code from `authorize-success.php` (lines 99-115)
- Archive old `payments-feed.php` if no longer used
- Document final production flow

## Summary

The new webhook architecture ensures reliable, synchronized member access management by making AxTrax the authoritative source of truth. Once AxTrax REST API is configured, the system will automatically transition from the temporary direct update mode to the proper callback flow.

**Key Principle**: Our database reflects what AxTrax has confirmed, not what we hope AxTrax will do.
