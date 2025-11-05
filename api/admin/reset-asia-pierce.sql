-- Reset Asia Pierce's status for testing
-- Run this on the production database

-- Find Asia Pierce's member ID
SELECT @member_id := id,
       @current_status := status,
       @current_valid_until := valid_until
FROM members
WHERE LOWER(TRIM(first_name)) = 'asia'
  AND LOWER(TRIM(last_name)) = 'pierce'
LIMIT 1;

-- Show current status
SELECT
  CONCAT('Member ID: ', @member_id) as info,
  CONCAT('Current Status: ', @current_status) as current_status,
  CONCAT('Current Valid Until: ', @current_valid_until) as current_valid_until;

-- Reset member to expired status
UPDATE members
SET status = 'expired',
    valid_until = DATE_SUB(CURDATE(), INTERVAL 7 DAY),
    updated_at = NOW()
WHERE id = @member_id;

-- Reset most recent paid invoice back to due
UPDATE dues
SET status = 'due',
    paid_at = NULL
WHERE member_id = @member_id
  AND status = 'paid'
ORDER BY period_end DESC
LIMIT 1;

-- Show updated status
SELECT
  id,
  first_name,
  last_name,
  status,
  valid_until,
  updated_at
FROM members
WHERE id = @member_id;

-- Show invoices
SELECT
  id,
  period_start,
  period_end,
  amount_cents / 100 as amount_dollars,
  status,
  paid_at
FROM dues
WHERE member_id = @member_id
ORDER BY period_end DESC
LIMIT 3;
