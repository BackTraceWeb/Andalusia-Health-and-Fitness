<?php
/**
 * Admin API: Set Member Status
 * Change a member's status between 'current' and 'due'
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
    $newStatus = isset($_GET['status']) ? trim($_GET['status']) : '';

    if ($memberId <= 0) {
        throw new Exception('Invalid member ID');
    }

    if (!in_array($newStatus, ['current', 'due'])) {
        throw new Exception('Invalid status. Must be "current" or "due"');
    }

    $pdo = pdo();

    // Get current member info
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, status, valid_until, monthly_fee FROM members WHERE id = ?");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        throw new Exception('Member not found');
    }

    // Update member status
    if ($newStatus === 'due') {
        // Set to due: update status and set valid_until to past
        $pastDate = date('Y-m-d', strtotime('-1 day'));
        $stmt = $pdo->prepare("
            UPDATE members
            SET status = 'due',
                valid_until = :past_date,
                updated_at = NOW()
            WHERE id = :member_id
        ");
        $stmt->execute([
            ':past_date' => $pastDate,
            ':member_id' => $memberId
        ]);

        // Create a due invoice if one doesn't exist
        $stmt = $pdo->prepare("
            SELECT id FROM dues
            WHERE member_id = :member_id
              AND status = 'due'
            LIMIT 1
        ");
        $stmt->execute([':member_id' => $memberId]);
        $existingInvoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingInvoice) {
            $periodStart = date('Y-m-01');
            $periodEnd = date('Y-m-t');
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
        }

    } else {
        // Set to current: update status and set valid_until to future
        $futureDate = date('Y-m-d', strtotime('+30 days'));
        $stmt = $pdo->prepare("
            UPDATE members
            SET status = 'current',
                valid_until = :future_date,
                updated_at = NOW()
            WHERE id = :member_id
        ");
        $stmt->execute([
            ':future_date' => $futureDate,
            ':member_id' => $memberId
        ]);
    }

    echo json_encode([
        'ok' => true,
        'message' => "Member status updated to '$newStatus'",
        'member_id' => $memberId,
        'new_status' => $newStatus
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
