<?php
/**
 * Reset Member Status Script
 * Used to revert Asia Pierce's record for testing
 */
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

// Member to reset
$firstName = 'Asia';
$lastName = 'Pierce';

try {
    $pdo = pdo();

    // Find member
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, status, valid_until, monthly_fee
        FROM members
        WHERE LOWER(TRIM(first_name)) = LOWER(?)
          AND LOWER(TRIM(last_name)) = LOWER(?)
        LIMIT 1
    ");
    $stmt->execute([$firstName, $lastName]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        echo "❌ Member not found: $firstName $lastName\n";
        exit(1);
    }

    $memberId = $member['id'];
    echo "Found member #$memberId: {$member['first_name']} {$member['last_name']}\n";
    echo "Current status: {$member['status']}\n";
    echo "Current valid_until: {$member['valid_until']}\n\n";

    // Reset member status to expired
    $pastDate = date('Y-m-d', strtotime('-7 days')); // 7 days ago
    $stmt = $pdo->prepare("
        UPDATE members
        SET status = 'expired',
            valid_until = :past_date,
            updated_at = NOW()
        WHERE id = :member_id
    ");
    $stmt->execute([
        ':past_date' => $pastDate,
        ':member_id' => $memberId
    ]);

    echo "✅ Updated member status to 'expired' with valid_until = $pastDate\n";

    // Find and reset any paid invoices back to 'due'
    $stmt = $pdo->prepare("
        SELECT id, period_start, period_end, amount_cents, status
        FROM dues
        WHERE member_id = :member_id
        ORDER BY period_end DESC
        LIMIT 5
    ");
    $stmt->execute([':member_id' => $memberId]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($invoices)) {
        echo "\nNo invoices found. Creating a new 'due' invoice...\n";

        // Create a due invoice for current month
        $periodStart = date('Y-m-01'); // First of this month
        $periodEnd = date('Y-m-t'); // Last of this month
        $amountCents = (int)round($member['monthly_fee'] * 100);

        $stmt = $pdo->prepare("
            INSERT INTO dues (member_id, period_start, period_end, amount_cents, currency, status)
            VALUES (:mid, :ps, :pe, :amt, 'USD', 'due')
        ");
        $stmt->execute([
            ':mid' => $memberId,
            ':ps' => $periodStart,
            ':pe' => $periodEnd,
            ':amt' => $amountCents
        ]);

        $newInvoiceId = $pdo->lastInsertId();
        echo "✅ Created invoice #$newInvoiceId for $periodStart to $periodEnd (\$" . ($amountCents/100) . ")\n";

    } else {
        echo "\nFound " . count($invoices) . " invoice(s):\n";
        foreach ($invoices as $inv) {
            echo "  - Invoice #{$inv['id']}: {$inv['period_start']} to {$inv['period_end']} - Status: {$inv['status']}\n";
        }

        // Reset most recent paid invoice to due
        $stmt = $pdo->prepare("
            UPDATE dues
            SET status = 'due',
                paid_at = NULL
            WHERE member_id = :member_id
              AND status = 'paid'
            ORDER BY period_end DESC
            LIMIT 1
        ");
        $stmt->execute([':member_id' => $memberId]);
        $updated = $stmt->rowCount();

        if ($updated > 0) {
            echo "\n✅ Reset most recent paid invoice back to 'due'\n";
        } else {
            echo "\n⚠️  No paid invoices to reset\n";

            // Check if there's already a due invoice
            $hasDue = false;
            foreach ($invoices as $inv) {
                if ($inv['status'] === 'due') {
                    $hasDue = true;
                    echo "✓ Already has a 'due' invoice (#${inv['id']})\n";
                    break;
                }
            }
        }
    }

    echo "\n✅ Asia Pierce reset complete. She should now show as 'due' in QuickPay.\n";

} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
