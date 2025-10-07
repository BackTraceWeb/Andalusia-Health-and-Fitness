<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: text/html; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo "<p style='color:#f66;'>Invalid member ID</p>";
  exit;
}

/* --- Get main member record --- */
$stmt = $pdo->prepare("
  SELECT id, first_name, last_name, company_name, department_name, card_number,
         payment_type, monthly_fee, valid_from, valid_until, status, updated_at
  FROM members
  WHERE id = :id
  LIMIT 1
");
$stmt->execute([':id' => $id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$member) {
  echo "<p style='color:#888;'>Member not found.</p>";
  exit;
}

/* --- Dependents --- */
$deps = $pdo->prepare("SELECT first_name, last_name, valid_until FROM members WHERE primary_member_id = :pid ORDER BY first_name");
$deps->execute([':pid' => $id]);
$dependents = $deps->fetchAll(PDO::FETCH_ASSOC);

/* --- Recent dues --- */
$dues = $pdo->prepare("
  SELECT id, period_start, period_end, amount_cents, status, updated_at
  FROM dues
  WHERE member_id = :mid
  ORDER BY period_end DESC
  LIMIT 5
");
$dues->execute([':mid' => $id]);
$duelist = $dues->fetchAll(PDO::FETCH_ASSOC);

/* --- Formatting helpers --- */
function safe($v){ return htmlspecialchars($v ?? '', ENT_QUOTES); }
function moneyFmt($cents){ return '$'.number_format(($cents/100), 2); }
function dateFmt($d){ return $d ? date('M d, Y', strtotime($d)) : '—'; }

$name = trim(($member['company_name'] ?: "{$member['first_name']} {$member['last_name']}"));
?>
<style>
  .crm-card { font-family:'Segoe UI',sans-serif; color:#eee; line-height:1.5; }
  .crm-header { display:flex; align-items:center; justify-content:space-between; }
  .crm-name { font-size:1.2rem; color:#d81b60; margin:0; }
  .crm-meta { color:#aaa; font-size:0.85rem; }
  .crm-section { background:#1b1b1b; margin-top:1rem; border-radius:10px; padding:0.8rem 1rem; }
  .crm-section h4 { margin:0 0 .5rem; color:#d81b60; font-size:0.95rem; }
  .crm-grid { display:grid; grid-template-columns:1fr 1fr; gap:.3rem .8rem; font-size:0.9rem; }
  .crm-grid div span { color:#ccc; }
  .crm-tag { display:inline-block; background:#333; padding:0.1rem 0.5rem; border-radius:6px; font-size:0.8rem; }
  .crm-tag.current { background:#2ecc71; color:#fff; }
  .crm-tag.due { background:#e74c3c; color:#fff; }
  .crm-tag.draft { background:#999; color:#fff; }
  ul.deplist { list-style:none; padding-left:0; margin:0; }
  ul.deplist li { background:#111; margin-bottom:3px; padding:4px 8px; border-radius:6px; font-size:0.9rem; }
  table.dues { width:100%; border-collapse:collapse; font-size:0.85rem; }
  table.dues th, table.dues td { padding:4px 6px; border-bottom:1px solid #222; text-align:left; }
  table.dues th { color:#d81b60; text-transform:uppercase; font-size:0.75rem; }
  .note-box { margin-top:1rem; }
  .note-box textarea {
    width:100%; background:#0d0d0d; color:#fff;
    border:1px solid #333; border-radius:6px;
    min-height:70px; padding:6px; resize:vertical;
  }
  .note-box button {
    margin-top:.5rem; background:#d81b60; border:none;
    padding:6px 12px; border-radius:6px; color:#fff; cursor:pointer;
  }
  .note-box button:hover { background:#e33d7d; }
</style>

<div class="crm-card">
  <div class="crm-header">
    <h3 class="crm-name"><?= safe($name) ?></h3>
    <span class="crm-tag <?= safe(strtolower($member['status'] ?? '')) ?>"><?= ucfirst($member['status'] ?? '-') ?></span>
  </div>
  <div class="crm-meta">
    Card #<?= safe($member['card_number']) ?> &bull; <?= ucfirst($member['payment_type'] ?? 'N/A') ?>
  </div>

  <div class="crm-section">
    <h4>Membership Details</h4>
    <div class="crm-grid">
      <div><span>Department:</span> <?= safe($member['department_name']) ?></div>
      <div><span>Valid From:</span> <?= dateFmt($member['valid_from']) ?></div>
      <div><span>Valid Until:</span> <?= dateFmt($member['valid_until']) ?></div>
      <div><span>Monthly Fee:</span> $<?= number_format($member['monthly_fee'], 2) ?></div>
      <div><span>Last Updated:</span> <?= dateFmt($member['updated_at']) ?></div>
    </div>
  </div>

  <?php if ($dependents): ?>
  <div class="crm-section">
    <h4>Dependents</h4>
    <ul class="deplist">
      <?php foreach ($dependents as $d): ?>
        <li><?= safe($d['first_name'].' '.$d['last_name']) ?> — valid until <?= dateFmt($d['valid_until']) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php if ($duelist): ?>
  <div class="crm-section">
    <h4>Recent Dues</h4>
    <table class="dues">
      <thead><tr><th>Period</th><th>Amount</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($duelist as $d): ?>
          <tr>
            <td><?= dateFmt($d['period_start']) ?> → <?= dateFmt($d['period_end']) ?></td>
            <td><?= moneyFmt($d['amount_cents']) ?></td>
            <td><?= ucfirst(safe($d['status'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="crm-section note-box">
    <h4>Internal Notes</h4>
    <textarea placeholder="Type a quick note about this member..."></textarea>
    <button disabled>Save Note (coming soon)</button>
  </div>
</div>
