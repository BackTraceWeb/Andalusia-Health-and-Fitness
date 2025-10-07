<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: text/html; charset=UTF-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo "<p style='color:#f66;'>Invalid member ID.</p>";
  exit;
}

/* Fetch main member */
$stmt = $pdo->prepare("
  SELECT id, first_name, last_name, company_name, department_name,
         payment_type, valid_from, valid_until, monthly_fee, status
  FROM members
  WHERE id = :id
  LIMIT 1
");
$stmt->execute([':id' => $id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
  echo "<p style='color:#aaa;'>Member not found.</p>";
  exit;
}

/* Fetch dependents (if any) */
$deps = $pdo->prepare("SELECT first_name, last_name, valid_until FROM members WHERE primary_member_id = :pid");
$deps->execute([':pid' => $id]);
$dependents = $deps->fetchAll(PDO::FETCH_ASSOC);

/* Fetch current dues (if any) */
$due = $pdo->prepare("
  SELECT id, period_start, period_end, amount_cents, status
  FROM dues
  WHERE member_id = :mid
  ORDER BY period_end DESC
  LIMIT 1
");
$due->execute([':mid' => $id]);
$invoice = $due->fetch(PDO::FETCH_ASSOC);

/* Format data */
$name = $member['company_name'] ?: trim($member['first_name'] . ' ' . $member['last_name']);
$validUntil = $member['valid_until'] ? date('M d, Y', strtotime($member['valid_until'])) : '—';
$validFrom  = $member['valid_from']  ? date('M d, Y', strtotime($member['valid_from']))  : '—';
$fee = number_format((float)$member['monthly_fee'], 2);
$status = ucfirst($member['status']);
$paymentType = ucfirst($member['payment_type']);
?>
<div style="font-family:Inter, sans-serif; line-height:1.5;">
  <h3 style="color:#e91e63; margin-top:0;"><?= htmlspecialchars($name) ?></h3>
  <p style="color:#bbb; margin:0 0 .5rem 0;">
    <strong>Department:</strong> <?= htmlspecialchars($member['department_name'] ?? '—') ?><br>
    <strong>Payment Type:</strong> <?= htmlspecialchars($paymentType) ?><br>
    <strong>Status:</strong> 
      <span style="color:<?= $member['status'] === 'current' ? '#2ecc71' : ($member['status'] === 'due' ? '#e74c3c' : '#f1c40f') ?>;">
        <?= htmlspecialchars($status) ?>
      </span>
  </p>
  
  <div style="margin-bottom:1rem;">
    <strong>Valid From:</strong> <?= $validFrom ?><br>
    <strong>Valid Until:</strong> <?= $validUntil ?><br>
    <strong>Monthly Fee:</strong> $<?= $fee ?>
  </div>

  <?php if ($invoice): ?>
    <div style="background:#151515; padding:.8rem; border-radius:8px; margin-bottom:1rem;">
      <strong>Last Invoice:</strong><br>
      Period: <?= htmlspecialchars($invoice['period_start']) ?> → <?= htmlspecialchars($invoice['period_end']) ?><br>
      Amount: $<?= number_format($invoice['amount_cents'] / 100, 2) ?><br>
      Status: <?= ucfirst(htmlspecialchars($invoice['status'])) ?>
    </div>
  <?php endif; ?>

  <?php if ($dependents && count($dependents) > 0): ?>
    <div style="margin-top:1rem;">
      <strong>Dependents:</strong>
      <ul style="list-style:none; padding-left:0; margin-top:.5rem;">
        <?php foreach ($dependents as $d): ?>
          <li style="background:#1b1b1b; margin-bottom:.3rem; padding:.5rem .7rem; border-radius:6px;">
            <?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?>
            <small style="color:#aaa;">— Valid until <?= htmlspecialchars($d['valid_until']) ?></small>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div style="margin-top:1.2rem;">
    <a href="../admin/edit-member.php?id=<?= $id ?>" 
       style="display:inline-block;background:#e91e63;color:#fff;padding:.5rem 1rem;
              border-radius:8px;text-decoration:none;font-weight:600;">
      Edit Member
    </a>
  </div>
</div>
