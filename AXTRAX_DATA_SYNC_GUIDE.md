# AxTrax Pro Data Sync Guide
**PowerShell Scripts via NinjaOne RMM**

---

## Overview

Your system uses **NinjaOne RMM** to run PowerShell scripts on the AxTrax Pro machine that:
1. Query the AxTrax Pro database (SQL Server)
2. Export member and dues data
3. POST JSON to your web server API endpoints

**When AxTrax upgraded to Pro**, the database schema changed and your scripts need updating.

---

## API Endpoints

### 1. Members Sync Endpoint
**URL**: `https://andalusiahealthandfitness.com/api/integrations/axtrax/members-ingest.php`

**Authentication**:
```
X-AHF-Bridge-Key: <value from /config/bridge.key>
```

**Method**: `POST`

**Expected JSON Format**:
```json
{
  "members": [
    {
      "user_id": 123,
      "first_name": "John",
      "last_name": "Doe",
      "department_id": 5,
      "department_name": "ABC Manufacturing",
      "card_number": "12345",
      "valid_from": "2025-01-01",
      "valid_until": "2025-12-31",
      "updated_at": "2025-11-04 10:30:00"
    }
  ]
}
```

**Required Fields**:
- `user_id` (integer) - Member ID from AxTrax
- `first_name` (string) - Member's first name
- `last_name` (string) - Member's last name

**Optional Fields**:
- `department_id` (integer) - Department/company ID
- `department_name` (string) - Department/company name
- `card_number` (string) - Access card number
- `valid_from` (date) - Membership start date (YYYY-MM-DD)
- `valid_until` (date) - Membership end date (YYYY-MM-DD)
- `updated_at` (datetime) - Last update timestamp

**What It Does**:
- Inserts new members or updates existing ones
- Calculates member `status` based on `valid_until` date:
  - If `valid_until >= today` → status = "current"
  - If `valid_until < today` → status = "due"

---

### 2. Dues Sync Endpoint
**URL**: `https://andalusiahealthandfitness.com/api/integrations/axtrax/dues-ingest.php`

**Authentication**: Same as members

**Method**: `POST`

**Expected JSON Format**:
```json
{
  "period_start": "2025-11-01",
  "period_end": "2025-11-30",
  "full_refresh": true,
  "dues": [
    {
      "member_id": 123,
      "period_start": "2025-11-01",
      "period_end": "2025-11-30",
      "currency": "USD",
      "status": "due"
    }
  ]
}
```

**Required Fields**:
- `member_id` (integer) - Must match a member in the database
- `period_start` (date) - Billing period start (YYYY-MM-DD)
- `period_end` (date) - Billing period end (YYYY-MM-DD)

**Optional Fields**:
- `currency` (string) - Default: "USD"
- `status` (string) - Default: "due"
- `full_refresh` (boolean) - If true, voids any existing dues not in this sync

**Important**: The `amount_cents` is automatically pulled from the member's `monthly_fee` in the database. The PowerShell script does NOT need to send the amount.

---

## PowerShell Script Template

### Check Bridge Key First

```powershell
# Read the bridge key from your server
$bridgeKey = Get-Content "C:\path\to\bridge.key" -Raw
$bridgeKey = $bridgeKey.Trim()
```

---

### Script 1: Sync Members

```powershell
# AxTrax Pro Member Sync Script
# Runs via NinjaOne RMM

# Configuration
$webServerUrl = "https://andalusiahealthandfitness.com/api/integrations/axtrax/members-ingest.php"
$bridgeKeyPath = "C:\AxTrax\bridge.key"  # Adjust path
$bridgeKey = (Get-Content $bridgeKeyPath -Raw).Trim()

# AxTrax Pro Database Connection
$axtraxServer = "localhost"  # Or AxTrax SQL Server IP
$axtraxDatabase = "AxTraxPro"  # Check actual database name
$axtraxUser = "axtrax_readonly"  # Use read-only account
$axtraxPassword = "your_password"

# Build connection string
$connectionString = "Server=$axtraxServer;Database=$axtraxDatabase;User Id=$axtraxUser;Password=$axtraxPassword;TrustServerCertificate=True;"

try {
    # Connect to AxTrax Pro database
    $connection = New-Object System.Data.SqlClient.SqlConnection
    $connection.ConnectionString = $connectionString
    $connection.Open()

    # Query AxTrax Pro members table
    # NOTE: Adjust table/column names for AxTrax Pro schema
    $query = @"
        SELECT
            CardHolderId AS user_id,
            FirstName AS first_name,
            LastName AS last_name,
            DepartmentId AS department_id,
            DepartmentName AS department_name,
            CardNumber AS card_number,
            ValidFrom AS valid_from,
            ValidUntil AS valid_until,
            LastModified AS updated_at
        FROM CardHolders
        WHERE IsActive = 1
"@

    $command = $connection.CreateCommand()
    $command.CommandText = $query
    $adapter = New-Object System.Data.SqlClient.SqlDataAdapter $command
    $dataset = New-Object System.Data.DataSet
    $adapter.Fill($dataset) | Out-Null

    # Convert to JSON array
    $members = @()
    foreach ($row in $dataset.Tables[0].Rows) {
        $member = @{
            user_id = [int]$row["user_id"]
            first_name = $row["first_name"].ToString()
            last_name = $row["last_name"].ToString()
            department_id = if ($row["department_id"] -is [DBNull]) { $null } else { [int]$row["department_id"] }
            department_name = $row["department_name"].ToString()
            card_number = $row["card_number"].ToString()
            valid_from = if ($row["valid_from"] -is [DBNull]) { $null } else { $row["valid_from"].ToString("yyyy-MM-dd") }
            valid_until = if ($row["valid_until"] -is [DBNull]) { $null } else { $row["valid_until"].ToString("yyyy-MM-dd") }
            updated_at = $row["updated_at"].ToString("yyyy-MM-dd HH:mm:ss")
        }
        $members += $member
    }

    # Build payload
    $payload = @{
        members = $members
    } | ConvertTo-Json -Depth 10

    # Send to web server
    $headers = @{
        "Content-Type" = "application/json"
        "X-AHF-Bridge-Key" = $bridgeKey
    }

    $response = Invoke-RestMethod -Uri $webServerUrl -Method Post -Body $payload -Headers $headers

    Write-Host "Success: $($response.upserts) members synced, $($response.skipped) skipped"

} catch {
    Write-Host "ERROR: $($_.Exception.Message)"
    exit 1
} finally {
    if ($connection.State -eq 'Open') {
        $connection.Close()
    }
}
```

---

### Script 2: Sync Dues

```powershell
# AxTrax Pro Dues Sync Script
# Runs via NinjaOne RMM

# Configuration
$webServerUrl = "https://andalusiahealthandfitness.com/api/integrations/axtrax/dues-ingest.php"
$bridgeKeyPath = "C:\AxTrax\bridge.key"
$bridgeKey = (Get-Content $bridgeKeyPath -Raw).Trim()

# Current billing period
$periodStart = (Get-Date).ToString("yyyy-MM-01")
$periodEnd = (Get-Date -Day 1).AddMonths(1).AddDays(-1).ToString("yyyy-MM-dd")

# AxTrax Pro Database Connection
$axtraxServer = "localhost"
$axtraxDatabase = "AxTraxPro"
$axtraxUser = "axtrax_readonly"
$axtraxPassword = "your_password"

$connectionString = "Server=$axtraxServer;Database=$axtraxDatabase;User Id=$axtraxUser;Password=$axtraxPassword;TrustServerCertificate=True;"

try {
    $connection = New-Object System.Data.SqlClient.SqlConnection
    $connection.ConnectionString = $connectionString
    $connection.Open()

    # Query members who need billing this period
    # Adjust this query based on AxTrax Pro schema
    $query = @"
        SELECT DISTINCT
            CardHolderId AS member_id
        FROM CardHolders
        WHERE IsActive = 1
          AND (ValidUntil IS NULL OR ValidUntil < GETDATE())
          AND PaymentType != 'Draft'  -- Skip auto-draft members
"@

    $command = $connection.CreateCommand()
    $command.CommandText = $query
    $adapter = New-Object System.Data.SqlClient.SqlDataAdapter $command
    $dataset = New-Object System.Data.DataSet
    $adapter.Fill($dataset) | Out-Null

    # Build dues array
    $dues = @()
    foreach ($row in $dataset.Tables[0].Rows) {
        $due = @{
            member_id = [int]$row["member_id"]
            period_start = $periodStart
            period_end = $periodEnd
            currency = "USD"
            status = "due"
        }
        $dues += $due
    }

    # Build payload
    $payload = @{
        period_start = $periodStart
        period_end = $periodEnd
        full_refresh = $true
        dues = $dues
    } | ConvertTo-Json -Depth 10

    # Send to web server
    $headers = @{
        "Content-Type" = "application/json"
        "X-AHF-Bridge-Key" = $bridgeKey
    }

    $response = Invoke-RestMethod -Uri $webServerUrl -Method Post -Body $payload -Headers $headers

    Write-Host "Success: $($response.upserts) dues synced"

} catch {
    Write-Host "ERROR: $($_.Exception.Message)"
    exit 1
} finally {
    if ($connection.State -eq 'Open') {
        $connection.Close()
    }
}
```

---

## Finding AxTrax Pro Database Schema

### Step 1: Connect to AxTrax SQL Server

On the AxTrax machine, open **SQL Server Management Studio** (SSMS) or PowerShell:

```powershell
# List databases
sqlcmd -S localhost -Q "SELECT name FROM sys.databases"

# List tables in AxTrax database
sqlcmd -S localhost -d AxTraxPro -Q "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES"

# Describe a table structure
sqlcmd -S localhost -d AxTraxPro -Q "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='CardHolders'"
```

### Step 2: Common AxTrax Pro Tables

**Typical schema** (adjust based on your actual installation):

- **CardHolders** - Member records
  - `CardHolderId` (int) - Unique member ID
  - `FirstName` (varchar)
  - `LastName` (varchar)
  - `CardNumber` (varchar)
  - `DepartmentId` (int)
  - `DepartmentName` (varchar)
  - `ValidFrom` (datetime)
  - `ValidUntil` (datetime)
  - `IsActive` (bit)
  - `LastModified` (datetime)

- **Departments** - Company/group records
  - `DepartmentId` (int)
  - `DepartmentName` (varchar)
  - `Description` (varchar)

- **Transactions** or **AccessLog** - Door access events
  - Used for attendance tracking (if needed)

### Step 3: Export Schema Documentation

```powershell
# Export table list
sqlcmd -S localhost -d AxTraxPro -Q "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES" -o "C:\temp\axtrax_tables.txt"

# Export all columns
sqlcmd -S localhost -d AxTraxPro -Q "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS ORDER BY TABLE_NAME, ORDINAL_POSITION" -o "C:\temp\axtrax_schema.txt"
```

---

## NinjaOne RMM Setup

### Create Custom Field for Bridge Key

1. In NinjaOne, go to **Administration → Custom Fields**
2. Create new **Secure** custom field: `axtrax_bridge_key`
3. Set value to your bridge key (from `/config/bridge.key` on web server)

### Create Automated Task

1. **Name**: AxTrax Member Sync
2. **Type**: PowerShell Script
3. **Schedule**: Every 15 minutes (or hourly)
4. **Device**: AxTrax Pro machine
5. **Script**: Use member sync script above
6. **Variables**:
   - `$bridgeKey = Ninja-Property-Get axtrax_bridge_key`

### Error Handling

Add to your scripts:

```powershell
# Log to NinjaOne
if ($response.ok) {
    Write-Output "✅ Sync successful: $($response.upserts) records"
    exit 0  # Success
} else {
    Write-Output "❌ Sync failed: $($response.error)"
    exit 1  # Failure - will show in NinjaOne alerts
}
```

---

## Troubleshooting

### Test API Endpoint Manually

```powershell
# Test connection from AxTrax machine
$testPayload = @{
    members = @(
        @{
            user_id = 999
            first_name = "Test"
            last_name = "User"
        }
    )
} | ConvertTo-Json

$headers = @{
    "Content-Type" = "application/json"
    "X-AHF-Bridge-Key" = "your_bridge_key_here"
}

Invoke-RestMethod -Uri "https://andalusiahealthandfitness.com/api/integrations/axtrax/members-ingest.php" -Method Post -Body $testPayload -Headers $headers
```

### Common Issues

**1. "unauthorized" Error**
- Check `X-AHF-Bridge-Key` header matches `/config/bridge.key` on server

**2. "bad_payload" Error**
- JSON format incorrect
- Missing required `members` array
- Check your `ConvertTo-Json` depth parameter

**3. SQL Connection Failed**
- Check SQL Server is running
- Verify connection string
- Ensure account has read permissions

**4. Column Names Changed**
- Run schema export queries
- Compare with query in PowerShell script
- Update `SELECT` statement column names/aliases

### Check Web Server Logs

```bash
# On web server
sudo tail -f /var/log/apache2/error.log | grep members-ingest
```

---

## Migration Checklist

When AxTrax upgraded to Pro:

- [ ] Export new AxTrax Pro database schema
- [ ] Identify new table/column names
- [ ] Update PowerShell query in member sync script
- [ ] Update PowerShell query in dues sync script
- [ ] Test with small dataset
- [ ] Run full sync
- [ ] Verify data in AHF database
- [ ] Set up automated NinjaOne task
- [ ] Monitor for errors

---

## Quick Reference

| Item | Value |
|------|-------|
| **Members API** | `https://andalusiahealthandfitness.com/api/integrations/axtrax/members-ingest.php` |
| **Dues API** | `https://andalusiahealthandfitness.com/api/integrations/axtrax/dues-ingest.php` |
| **Auth Header** | `X-AHF-Bridge-Key` |
| **Bridge Key File** | `/var/www/andalusiahealthandfitness/config/bridge.key` |
| **AxTrax Machine** | 100.103.220.72 (Tailscale) |
| **Web Server** | 100.83.179.25 (Tailscale) |

---

**Last Updated**: November 4, 2025
**Status**: Waiting for AxTrax Pro schema details to update PowerShell scripts
