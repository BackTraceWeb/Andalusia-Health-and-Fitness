<?php
require __DIR__ . '/_auth.php';
require __DIR__ . '/../_bootstrap.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');

    if ($firstName && $lastName) {
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
                $error = "Member not found: $firstName $lastName";
            } else {
                $memberId = $member['id'];

                // Reset member status to expired
                $pastDate = date('Y-m-d', strtotime('-7 days'));
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
                $invoicesReset = $stmt->rowCount();

                // Check if there's a due invoice, create if not
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM dues
                    WHERE member_id = :member_id AND status = 'due'
                ");
                $stmt->execute([':member_id' => $memberId]);
                $hasDue = $stmt->fetchColumn() > 0;

                if (!$hasDue) {
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

                $message = "✅ Successfully reset {$member['first_name']} {$member['last_name']} (ID: $memberId)";
                $message .= "<br>Status set to 'expired', valid_until set to $pastDate";
                if ($invoicesReset > 0) {
                    $message .= "<br>Reset $invoicesReset paid invoice(s) back to 'due'";
                }
            }
        } catch (Throwable $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Please enter both first and last name";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Member Status - Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="admin.css">
<style>
body {
  background:#111; color:#f2f2f2; font-family:'Segoe UI',sans-serif;
  padding:2rem;
}
.container {
  max-width:600px; margin:0 auto;
  background:#1c1c1c; padding:2rem; border-radius:10px;
  box-shadow:0 0 15px rgba(216,27,96,0.3);
}
h1 { color:#d81b60; text-align:center; }
.form-group {
  margin-bottom:1.5rem;
}
label {
  display:block; margin-bottom:0.5rem; color:#aaa;
  font-weight:600; text-transform:uppercase; font-size:0.85rem;
}
input {
  width:100%; padding:0.75rem; border-radius:6px;
  border:1px solid #333; background:#0a0a0a; color:#fff;
  font-size:1rem;
}
button {
  width:100%; padding:0.75rem; border-radius:6px;
  border:none; background:#d81b60; color:#fff;
  font-size:1rem; font-weight:600; cursor:pointer;
}
button:hover { background:#e33d7d; }
.message {
  padding:1rem; border-radius:6px; margin-bottom:1rem;
}
.message.success {
  background:#1b4d1b; border:1px solid #4caf50; color:#4caf50;
}
.message.error {
  background:#4d1b1b; border:1px solid #f44336; color:#f44336;
}
.back-link {
  display:block; text-align:center; margin-top:1.5rem;
  color:#d81b60; text-decoration:none;
}
.warning {
  background:#4d3b1b; border:1px solid #ff9800; color:#ff9800;
  padding:1rem; border-radius:6px; margin-bottom:1.5rem;
}
</style>
</head>
<body>

<div class="container">
  <h1>Reset Member Status</h1>

  <div class="warning">
    ⚠️ This tool is for TESTING ONLY. It will:
    <ul>
      <li>Set member status to 'expired'</li>
      <li>Set valid_until to 7 days ago</li>
      <li>Reset paid invoices back to 'due'</li>
    </ul>
    This ONLY affects our internal database, not AxTrax.
  </div>

  <?php if ($message): ?>
    <div class="message success"><?= $message ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="message error"><?= $error ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>First Name</label>
      <input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? 'Asia') ?>">
    </div>

    <div class="form-group">
      <label>Last Name</label>
      <input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? 'Pierce') ?>">
    </div>

    <button type="submit">Reset Member to "Due" Status</button>
  </form>

  <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
</div>

</body>
</html>
