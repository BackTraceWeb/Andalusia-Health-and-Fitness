<?php
/**
 * Admin API: Delete Invoice
 * Delete a specific invoice by ID
 */
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../_bootstrap.php';

session_start();
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

try {
    $memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
    $invoiceId = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;

    if ($memberId <= 0) {
        throw new Exception('Invalid member ID');
    }

    if ($invoiceId <= 0) {
        throw new Exception('Invalid invoice ID');
    }

    $pdo = pdo();

    // Verify invoice exists and belongs to this member
    $stmt = $pdo->prepare("
        SELECT id, member_id, status, amount_cents
        FROM dues
        WHERE id = :invoice_id AND member_id = :member_id
    ");
    $stmt->execute([
        ':invoice_id' => $invoiceId,
        ':member_id' => $memberId
    ]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new Exception('Invoice not found or does not belong to this member');
    }

    // Delete the invoice
    $stmt = $pdo->prepare("DELETE FROM dues WHERE id = :invoice_id");
    $stmt->execute([':invoice_id' => $invoiceId]);

    echo json_encode([
        'ok' => true,
        'message' => "Invoice #$invoiceId deleted",
        'member_id' => $memberId,
        'invoice_id' => $invoiceId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
