<?php
header('Content-Type: application/json');

// ------------------------------------------------------------------
// Load global bootstrap for database connection
// ------------------------------------------------------------------
require_once __DIR__ . '/../_bootstrap.php';
$pdo = pdo();

if (!$pdo) {
  echo json_encode(['ok' => false, 'error' => 'db_not_connected']);
  exit;
}

// ------------------------------------------------------------------
// INPUT HANDLING
// ------------------------------------------------------------------
$id     = isset($_GET['id']) ? intval($_GET['id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($id <= 0 && $search === '') {
  echo json_encode(['ok' => false, 'error' => 'missing_parameters']);
  exit;
}

try {
  // ----------------------------------------------------------------
  // LOOKUP MEMBER
  // ----------------------------------------------------------------
  if ($id > 0) {
    // Direct ID lookup
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$id]);
  } else {
    // Split the search into first and last name parts
    $parts = preg_split('/\s+/', $search);
    $first = $parts[0] ?? '';
    $last  = $parts[1] ?? '';

    if ($first && $last) {
      // Flexible matching to handle incomplete names from AxTrax sync
      // Matches: exact match, first-only, last-only, or concatenated full name
      $stmt = $pdo->prepare("
        SELECT * FROM members
        WHERE (LOWER(first_name) = LOWER(?) AND LOWER(last_name) = LOWER(?))
           OR (LOWER(first_name) = LOWER(?) AND LOWER(last_name) = '')
           OR (LOWER(first_name) = '' AND LOWER(last_name) = LOWER(?))
           OR (LOWER(CONCAT(first_name, ' ', last_name)) = LOWER(?))
        ORDER BY id DESC
        LIMIT 1
      ");
      $stmt->execute([$first, $last, $first, $last, $search]);
    } else {
      // Fallback: partial search (for admin or debugging)
      $stmt = $pdo->prepare("
        SELECT * FROM members
        WHERE LOWER(first_name) LIKE LOWER(?)
           OR LOWER(last_name) LIKE LOWER(?)
        ORDER BY id DESC
        LIMIT 1
      ");
      $stmt->execute(["%$search%", "%$search%"]);
    }
  }

  $member = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$member) {
    echo json_encode(['ok' => false, 'error' => 'member_not_found']);
    exit;
  }

  // ----------------------------------------------------------------
  // DETERMINE STATUS
  // ----------------------------------------------------------------
  $isDraft = (int)($member['is_draft'] ?? 0) === 1;
  $allowQuickPay = (int)($member['allow_quickpay'] ?? 0) === 1;
  $validUntil = !empty($member['valid_until']) ? strtotime($member['valid_until']) : 0;
  $now = time();

  // BUSINESS LOGIC:
  // 1. Draft members → Always "current" (automatic payment), QuickPay only if staff enables it
  // 2. Non-draft (card/cash/other) → Check if valid_until is past
  // 3. If valid_until < today → DUE (needs payment)

  if ($isDraft) {
    // Draft members: Always "current" status (automatic payment)
    // QuickPay only available if staff explicitly enables it (when draft fails)
    $status = 'current';
  } elseif ($validUntil && $validUntil < $now) {
    // Expired: valid_until is in the past
    $status = 'due';
  } else {
    // Not expired yet
    $status = 'current';
  }

  // ----------------------------------------------------------------
  // GET OR CREATE DUES INVOICE IF MEMBER IS DUE
  // ----------------------------------------------------------------
  $due = null;
  $amountCents = (int)round($member['monthly_fee'] * 100);

  // Create invoices for:
  // 1. Non-draft members who are due (normal flow)
  // 2. Draft members ONLY if staff enabled QuickPay (backup when automatic payment fails)
  $shouldCreateInvoice = ($status === 'due' && !$isDraft) || ($isDraft && $allowQuickPay);

  if ($shouldCreateInvoice) {
    // Calculate current billing period
    $periodStart = (new DateTime('first day of this month'))->format('Y-m-d');
    $periodEnd   = (new DateTime('last day of this month'))->format('Y-m-d');

    // First check if there's ANY invoice (paid or unpaid) for current period
    $periodCheck = $pdo->prepare("
      SELECT *
      FROM dues
      WHERE member_id = ? AND period_start = ? AND period_end = ?
      ORDER BY id DESC
      LIMIT 1
    ");
    $periodCheck->execute([$member['id'], $periodStart, $periodEnd]);
    $existingInvoice = $periodCheck->fetch(PDO::FETCH_ASSOC);

    // If already paid for this period, don't show any invoice (prevents double payment)
    if ($existingInvoice && $existingInvoice['status'] === 'paid') {
      $due = null;
    } else if ($existingInvoice && $existingInvoice['status'] === 'due') {
      // Unpaid invoice exists for current period - use it
      $due = $existingInvoice;
    } else {
      // No invoice exists for current period - look for any other unpaid invoice
      $dueCheck = $pdo->prepare("
        SELECT *
        FROM dues
        WHERE member_id = ? AND status = 'due'
        ORDER BY period_end DESC, id DESC
        LIMIT 1
      ");
      $dueCheck->execute([$member['id']]);
      $due = $dueCheck->fetch(PDO::FETCH_ASSOC);
    }

    // Auto-create invoice if none exists and not already paid for this period
    if (!$due && (!$existingInvoice || $existingInvoice['status'] !== 'paid')) {

      try {
        $ins = $pdo->prepare("
          INSERT INTO dues (member_id, period_start, period_end, amount_cents, currency, status)
          VALUES (:mid, :ps, :pe, :amt, 'USD', 'due')
        ");
        $ins->execute([
          ':mid' => $member['id'],
          ':ps'  => $periodStart,
          ':pe'  => $periodEnd,
          ':amt' => $amountCents,
        ]);

        $invoiceId = $pdo->lastInsertId();
        $due = [
          'id'           => $invoiceId,
          'member_id'    => $member['id'],
          'period_start' => $periodStart,
          'period_end'   => $periodEnd,
          'amount_cents' => $amountCents,
          'currency'     => 'USD',
          'status'       => 'due',
        ];

        error_log("QuickPay: Auto-created invoice #{$invoiceId} for member {$member['id']}");
      } catch (PDOException $e) {
        // If duplicate key error (23000), query for the existing invoice
        if ($e->getCode() == '23000' || strpos($e->getMessage(), 'Duplicate entry') !== false) {
          error_log("QuickPay: Duplicate invoice exists for member {$member['id']}, fetching existing...");

          // Re-query for the existing invoice with these exact dates
          $dueCheck2 = $pdo->prepare("
            SELECT * FROM dues
            WHERE member_id = ? AND period_start = ? AND period_end = ? AND status = 'due'
            LIMIT 1
          ");
          $dueCheck2->execute([$member['id'], $periodStart, $periodEnd]);
          $due = $dueCheck2->fetch(PDO::FETCH_ASSOC);

          if (!$due) {
            // Still couldn't find it - this shouldn't happen, throw the original error
            throw $e;
          }
        } else {
          // Some other database error - rethrow it
          throw $e;
        }
      }
    }
  }

    // ----------------------------------------------------------------
  // OUTPUT JSON
  // ----------------------------------------------------------------
  echo json_encode([
    'ok' => true,
    'member' => [
      'id'              => (int)$member['id'],
      'first_name'      => $member['first_name'],
      'last_name'       => $member['last_name'],
      'department_name' => $member['department_name'] ?? '',
      'payment_type'    => $member['payment_type'] ?? '',
      'monthly_fee'     => $member['monthly_fee'] ?? '0.00',
      'valid_from'      => $member['valid_from'] ?? null,
      'valid_until'     => $member['valid_until'] ?? null,
      'status'          => $status,
      'is_draft'        => $isDraft ? 1 : 0,
      'allow_quickpay'  => $allowQuickPay ? 1 : 0,
      'notes'           => $member['notes'] ?? '',
    ],
    // ✅ rename dues → invoice so JS reads it properly
    'invoice' => $due ? [
      'id'            => (int)$due['id'],
      'period_start'  => $due['period_start'] ?? '',
      'period_end'    => $due['period_end'] ?? '',
      'amount_cents'  => isset($due['amount_cents']) ? (int)$due['amount_cents'] : 0,
      'status'        => $due['status'] ?? 'due'
    ] : null,
    'status' => $status
  ]);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'server_error',
    'message' => $e->getMessage()
  ]);
  exit;
}

