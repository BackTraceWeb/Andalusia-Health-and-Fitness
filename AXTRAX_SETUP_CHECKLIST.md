# AxTrax Pro Integration Checklist
**Andalusia Health & Fitness**

---

## ✅ What's Already Done

- ✅ **Tailscale VPN** set up and working
- ✅ **Web server** can reach AxTrax machine (100.103.220.72)
- ✅ **AxTrax server** running on port 3000 (HTTP)
- ✅ **Config file** created at `/var/www/andalusiahealthandfitness/config/payments.php`
- ✅ **AxTrax client code** ready at `/api/integrations/axtrax/client.php`
- ✅ **Payment webhook** calls AxTrax sync automatically

---

## ⏳ What You Need from AxTrax Pro Documentation

### 1. API Authentication
**Question**: How does the API authenticate requests?

**Common options**:
- [ ] API Key in header (e.g., `X-API-Key: your_key`)
- [ ] Bearer token (e.g., `Authorization: Bearer your_token`)
- [ ] Basic auth (username:password)
- [ ] Custom header

**Where to find it**: AxTrax Pro admin panel or API documentation

**Example**:
```
Authorization: Bearer abc123xyz456
```

---

### 2. API Endpoint for Updating Member Validity
**Question**: What's the endpoint to extend a member's access?

**Common patterns**:
- [ ] `PUT /api/members/{id}/validity`
- [ ] `PATCH /api/members/{id}`
- [ ] `POST /api/members/{id}/extend`
- [ ] `PUT /api/v1/cardholders/{id}`

**Where to find it**: API documentation, Swagger UI, or support from Rosslare/AxTrax

**Example**:
```
PUT http://100.103.220.72:3000/api/members/123/validity
```

---

### 3. Request Payload Format
**Question**: What data does the API expect?

**Example formats**:
```json
// Option 1: Simple date
{
  "valid_until": "2025-12-04"
}

// Option 2: With card number
{
  "card_number": "12345",
  "expiry_date": "2025-12-04"
}

// Option 3: Full member object
{
  "member_id": 123,
  "validity": {
    "start_date": "2025-11-04",
    "end_date": "2025-12-04"
  }
}
```

**Where to find it**: API documentation or Swagger UI

---

### 4. Member Identifier
**Question**: How does AxTrax identify members?

**Common options**:
- [ ] Member ID (e.g., 123)
- [ ] Card number (e.g., "12345")
- [ ] Email address
- [ ] Custom field

**In your database**:
- Member ID: `members.id`
- Card number: `members.card_number`

**Example**:
```
Member ID: 456
Card Number: 789012
```

---

### 5. Response Format
**Question**: What does a successful response look like?

**Example**:
```json
{
  "success": true,
  "member_id": 123,
  "valid_until": "2025-12-04",
  "updated_at": "2025-11-04T22:15:00Z"
}
```

---

## 📋 Information Gathering Checklist

### From AxTrax Pro Admin Panel:
- [ ] API Key / Bearer Token
- [ ] API documentation URL (if available)
- [ ] Swagger/OpenAPI documentation link
- [ ] Admin credentials (to access API settings)

### From AxTrax Pro Documentation:
- [ ] API endpoint URLs
- [ ] Authentication method
- [ ] Request payload examples
- [ ] Response format examples
- [ ] Error codes and handling

### From Rosslare Support (if needed):
- [ ] Technical support contact
- [ ] API integration guide
- [ ] Sample code or Postman collection
- [ ] Rate limiting info

---

## 🧪 How to Test AxTrax API (Once You Have Details)

### Step 1: Test from AxTrax Machine Locally

On the AxTrax machine (100.103.220.72), open PowerShell:

```powershell
# Test API is accessible
Invoke-WebRequest -Uri "http://localhost:3000/api" -Method GET

# Test authentication (replace with actual endpoint/key)
$headers = @{
    "Authorization" = "Bearer YOUR_API_KEY"
}
Invoke-WebRequest -Uri "http://localhost:3000/api/members" -Method GET -Headers $headers
```

### Step 2: Test from Web Server via Tailscale

SSH into web server:

```bash
ssh -i ~/Downloads/BTS1.pem ubuntu@3.12.72.81

# Test API endpoint (replace with actual endpoint)
curl -H "Authorization: Bearer YOUR_API_KEY" \
     http://100.103.220.72:3000/api/members/123
```

### Step 3: Test Member Validity Update

```bash
# Update member validity to +30 days
curl -X PUT \
     -H "Authorization: Bearer YOUR_API_KEY" \
     -H "Content-Type: application/json" \
     -d '{"valid_until":"2025-12-04"}' \
     http://100.103.220.72:3000/api/members/123/validity
```

---

## 🔧 Configuration Steps (Once You Have API Details)

### Step 1: Update config/payments.php

SSH into server:
```bash
ssh -i ~/Downloads/BTS1.pem ubuntu@3.12.72.81
cd /var/www/andalusiahealthandfitness
sudo nano config/payments.php
```

Update with actual values:
```php
<?php
return [
    'axtrax' => [
        'base_url' => 'http://100.103.220.72:3000',
        'api_key' => 'ACTUAL_API_KEY_FROM_AXTRAX',  // ← Update this
        'site_id' => null,  // Or actual site ID if needed
        'timeout_s' => 8,
    ],
];
```

### Step 2: Update AxTrax Client Code

```bash
sudo nano api/integrations/axtrax/client.php
```

Find line 69 (the `updateMemberValidity` function) and update with actual endpoint:

```php
public function updateMemberValidity(int $memberId, string $validUntil): array
{
    if ($memberId <= 0) {
        throw new InvalidArgumentException('Member ID must be positive.');
    }
    if ($validUntil === '') {
        throw new InvalidArgumentException('validUntil cannot be empty.');
    }

    // ACTUAL ENDPOINT (replace with real path from documentation)
    $endpoint = $this->baseUrl . '/api/members/' . $memberId . '/validity';

    // ACTUAL PAYLOAD (replace with real format from documentation)
    $payload = [
        'valid_until' => $validUntil,
        // Add other required fields from documentation
    ];

    return $this->postJson($endpoint, $payload);

    // REMOVE THIS LINE once above is configured:
    // throw new LogicException('AxTrax REST endpoint details pending from vendor.');
}
```

### Step 3: Test Payment Flow

1. Make a test payment via QuickPay
2. Check logs:
   ```bash
   sudo tail -f /var/log/apache2/error.log | grep -i axtrax
   ```

3. Should see:
   ```
   AxTrax sync successful for member #123: {"success":true,...}
   ```

   Instead of:
   ```
   AxTrax sync staged for member #123 - REST API not ready yet
   ```

---

## 🔍 Common AxTrax Pro API Patterns

Based on typical access control systems:

### Pattern 1: RESTful Member API
```
GET    /api/members/{id}           - Get member details
PUT    /api/members/{id}/validity  - Update expiration date
POST   /api/members                - Create new member
DELETE /api/members/{id}           - Deactivate member
```

### Pattern 2: Cardholder API
```
GET    /api/cardholders/{cardNumber}
PUT    /api/cardholders/{cardNumber}/expiry
POST   /api/cardholders
```

### Pattern 3: Access Control API
```
POST   /api/access/grant
POST   /api/access/revoke
PUT    /api/access/{id}/expiry
```

**Your actual API may vary** - consult AxTrax Pro documentation.

---

## 📊 Current System Status

### Connection Status
```
Web Server (AWS)
  Tailscale IP: 100.83.179.25
       ↓ (VPN Tunnel - Encrypted)
AxTrax Pro Machine (Gym)
  Tailscale IP: 100.103.220.72
  HTTP Port: 3000
  Server: Kestrel/ASP.NET
  Status: ✅ REACHABLE
```

### Payment Flow Status
```
1. Member pays via QuickPay           ✅ Working
2. Payment processed by Authorize.Net ✅ Working
3. Invoice marked as paid             ✅ Working
4. Member valid_until +30 days        ✅ Working
5. Member status = 'current'          ✅ Working
6. Trigger AxTrax sync webhook        ✅ Working
7. Call AxTrax API to update doors    ⏳ Staged (needs API details)
```

---

## 📞 Where to Get Help

### AxTrax Pro / Rosslare Support:
- **Website**: https://rosslare.com
- **Support**: Contact your AxTrax Pro vendor/installer
- **Documentation**: Request API integration guide

### Ask for:
- "REST API documentation for AxTrax Pro"
- "API integration guide"
- "How to programmatically update member validity dates"
- "API credentials for external integrations"

### Information to Provide Them:
- You have AxTrax Pro installed at your facility
- You want to integrate with your membership website
- You need to update member expiration dates via API
- You're calling from a web server via Tailscale VPN

---

## ✅ Next Steps

1. [ ] Access AxTrax Pro admin panel on the gym machine
2. [ ] Look for "API Settings" or "Integrations"
3. [ ] Generate or find API Key/Token
4. [ ] Check for API documentation link
5. [ ] Test API endpoint locally on the AxTrax machine
6. [ ] Update `config/payments.php` with API key
7. [ ] Update `api/integrations/axtrax/client.php` with endpoint
8. [ ] Test with a real payment
9. [ ] Verify door access updates automatically

---

## 📝 Quick Reference

| Item | Value |
|------|-------|
| **AxTrax Machine IP** | 100.103.220.72 (Tailscale) |
| **AxTrax Port** | 3000 (HTTP) |
| **Web Server IP** | 100.83.179.25 (Tailscale) |
| **Config File** | `/var/www/andalusiahealthandfitness/config/payments.php` |
| **Client Code** | `/var/www/andalusiahealthandfitness/api/integrations/axtrax/client.php` |
| **Test Connectivity** | `ping 100.103.220.72` |
| **Test API** | `curl http://100.103.220.72:3000/api` |

---

**Last Updated**: November 4, 2025
**Status**: Tailscale VPN connected, waiting for AxTrax API documentation
