<?php
/**
 * Clear Payment Session
 * Clears payment data from session after processing
 */
declare(strict_types=1);
session_start();

// Clear payment session data
unset($_SESSION['quickpay_payment']);

echo json_encode(['ok' => true]);
