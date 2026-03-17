<?php
/**
 * Cleanup Stale Payment Intents
 * Removes payment intents older than 24 hours that were never processed
 */
declare(strict_types=1);

require __DIR__ . '/../../_bootstrap.php';

echo '=== Cleanup Stale Payment Intents - ' . date('Y-m-d H:i:s') . ' ===' . PHP_EOL . PHP_EOL;

try {
    $pdo = pdo();
    
    // Find stale intents (older than 24 hours, not processed)
    $stmt = $pdo->query('
        SELECT id, member_id, invoice_id, created_at
        FROM payment_intents
        WHERE processed_at IS NULL
        AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ');
    
    $staleIntents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = count($staleIntents);
    
    if ($count === 0) {
        echo 'No stale payment intents to clean up.' . PHP_EOL;
        exit(0);
    }
    
    echo "Found $count stale payment intent(s) to delete:" . PHP_EOL;
    foreach ($staleIntents as $intent) {
        echo sprintf(
            '  - Intent #%d: Member %d, Invoice %d, Created %s' . PHP_EOL,
            $intent['id'],
            $intent['member_id'],
            $intent['invoice_id'],
            $intent['created_at']
        );
    }
    
    // Delete stale intents
    $deleteStmt = $pdo->prepare('
        DELETE FROM payment_intents
        WHERE processed_at IS NULL
        AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ');
    $deleteStmt->execute();
    
    echo PHP_EOL . "✓ Deleted $count stale payment intent(s)" . PHP_EOL;
    
} catch (Exception $e) {
    echo '✗ Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo PHP_EOL . '=== Cleanup Complete ===' . PHP_EOL;
exit(0);
