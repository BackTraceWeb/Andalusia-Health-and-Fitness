#!/usr/bin/env php
<?php
/**
 * Process Stuck Payment Intents
 *
 * Processes payment intents that were created but never processed
 * (e.g., user didn't click "Continue" on Authorize.Net success page)
 *
 * Run this as a cron job every 5 minutes:
 * */5 * * * * php /var/www/andalusiahealthandfitness/api/payments/process-stuck-intents.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../axtrax-config.php';
require_once __DIR__ . '/../axtrax-helpers.php';

$pdo = pdo();
$config = require __DIR__ . '/../axtrax-config.php';

// Find payment intents created 5-60 minutes ago that haven't been processed
$stmt = $pdo->prepare("
    SELECT id, member_id, invoice_id, created_at
    FROM payment_intents
    WHERE processed_at IS NULL
      AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)
      AND created_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY created_at ASC
");
$stmt->execute();
$stuckIntents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($stuckIntents)) {
    echo "[" . date('c') . "] No stuck payment intents found\n";
    exit(0);
}

echo "[" . date('c') . "] Found " . count($stuckIntents) . " stuck payment intent(s)\n";

foreach ($stuckIntents as $intent) {
    $intentId = $intent['id'];
    $memberId = $intent['member_id'];
    $invoiceId = $intent['invoice_id'];

    echo "[" . date('c') . "] Processing intent #$intentId (member #$memberId, invoice #$invoiceId)...\n";

    try {
        // Get member details
        $stmt = $pdo->prepare('SELECT email, first_name, last_name FROM members WHERE id = ?');
        $stmt->execute([$memberId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member) {
            echo "[" . date('c') . "] ERROR: Member #$memberId not found, skipping\n";
            continue;
        }

        // Check if invoice is still due (might have been paid via webhook already)
        $stmt = $pdo->prepare('SELECT status FROM dues WHERE id = ?');
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($invoice['status'] === 'paid') {
            echo "[" . date('c') . "] Invoice #$invoiceId already paid (processed by webhook), marking intent as processed\n";
            $stmt = $pdo->prepare("UPDATE payment_intents SET processed_at = NOW() WHERE id = ?");
            $stmt->execute([$intentId]);
            continue;
        }

        // Calculate new valid_until date
        $newValidUntil = date('Y-m-d', strtotime('+30 days'));

        // Extend membership in AxTrax
        $axtraxSuccess = axtraxExtendMembership(
            $member['email'],
            $member['first_name'],
            $member['last_name'],
            30,
            $config
        );

        if ($axtraxSuccess) {
            echo "[" . date('c') . "] AxTrax API - Successfully extended membership for member #$memberId\n";
        } else {
            echo "[" . date('c') . "] WARNING: AxTrax API failed for member #$memberId (updating database anyway)\n";
        }

        // Update member in database
        $stmt = $pdo->prepare("
            UPDATE members
            SET valid_until = :valid_until,
                status = 'current',
                updated_at = NOW()
            WHERE id = :member_id
        ");
        $stmt->execute([
            ':valid_until' => $newValidUntil,
            ':member_id' => $memberId
        ]);

        // Mark invoice as paid
        $stmt = $pdo->prepare("
            UPDATE dues
            SET status = 'paid',
                paid_at = NOW()
            WHERE id = :dues_id AND status IN ('due', 'failed')
        ");
        $stmt->execute([':dues_id' => $invoiceId]);

        // Mark intent as processed
        $stmt = $pdo->prepare("UPDATE payment_intents SET processed_at = NOW() WHERE id = ?");
        $stmt->execute([$intentId]);

        echo "[" . date('c') . "] ✓ Successfully processed intent #$intentId\n";

    } catch (Exception $e) {
        echo "[" . date('c') . "] ERROR processing intent #$intentId: " . $e->getMessage() . "\n";
    }
}

echo "[" . date('c') . "] Cleanup complete\n";
