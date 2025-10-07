<?php
session_start();
if (empty($_SESSION['logged_in'])) {
  header('Location: index.php');
  exit;
}
require 'config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid member ID.");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $first_name = $conn->real_escape_string($_POST['first_name']);
  $last_name = $conn->real_escape_string($_POST['last_name']);
  $company_name = $conn->real_escape_string($_POST['company_name']);
  $email = $conn->real_escape_string($_POST['email']);
  $card_number = $conn->real_escape_string($_POST['card_number']);
  $valid_from = $conn->real_escape_string($_POST['valid_from']);
  $valid_until = $conn->real_escape_string($_POST['valid_until']);
  $monthly_fee = floatval($_POST['monthly_fee']);
  $payment_type = $conn->real_escape_string($_POST['payment_type']);
  $is_primary = isset($_POST['is_primary']) ? 1 : 0;
  $primary_member_id = $is_primary ? 'NULL' : intval($_POST['primary_member_id']);
  $amount_cents = intval($monthly_fee * 100);

  // Update member record
  $update = $conn->query("UPDATE members 
                          SET first_name='$first_name', last_name='$last_name', company_name='$company_name',
                              email='$email', card_number='$card_number', valid_from='$valid_from',
                              valid_until='$valid_until', monthly_fee=$monthly_fee, payment_type='$payment_type',
                              is_primary=$is_primary, primary_member_id=$primary_member_id
                          WHERE id=$id");

  if ($update) {
    // Sync dues
    $checkDues = $conn->query("SELECT id FROM dues WHERE member_id = $id LIMIT 1");
    if ($checkDues && $checkDues->num_rows > 0) {
        $conn->query("UPDATE dues 
                      SET amount_cents = $amount_cents, updated_at = NOW() 
                      WHERE member_id = $id");
    } else {
        $period_start = date('Y-m-01');
        $period_end   = date('Y-m-t');
        $conn->query("INSERT INTO dues (member_id, period_start, period_end, amount_cents, status, created_at, updated_at)
                      VALUES ($id, '$period_start', '$period_end', $amount_cents, 'due', NOW(), NOW())");
    }

    header("Location: dashboard.php");
    exit;
  } else {
    $error = "Failed to update member: " . $conn->error;
  }
}

// Load current member
$result = $conn->query("SELECT * FROM members WHERE id=$id LIMIT 1");
$member = $result->fetch_assoc();
if (!$member) die("Member not found.");

// Load potential primary members for dropdown
$primaries = $conn->query("SELECT id, first_name, last_name, company_name FROM members WHERE is_primary = 1 AND id != $id ORDER BY last_name, company_name");

// Load dependents for this member if they are primary
$dependents = $member['is_primary'] ? 
  $conn->query("SELECT * FROM members WHERE primary_member_id = $id ORDER BY last_name, company_name") : null;

// Determine display name
$displayName = $member['company_name'] ? $member['company_name'] : trim($member['first_name'].' '.$member['last_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit <?= htmlspecialchars($member['company_name'] ? 'Company' : 'Member') ?></title>
  <link rel="stylesheet" href="admin.css">
  <style>
    .dependents-table {
      margin-top: 30px;
      background: #fff;
      color: #000;
      border-radius: 10px;
      padding: 10px;
    }
    .dependents-table table {
      width: 100%;
      border-collapse: collapse;
    }
    .dependents-table th, .dependents-table td {
      padding: 10px;
      border-bottom: 1px solid #ddd;
    }
    .dependents-table th {
      background: #ff1e78;
      color: #fff;
    }
  </style>
</head>
<body>
  <div class="content">
    <h2>Edit <?= htmlspecialchars($member['company_name'] ? 'Company' : 'Member') ?>: <?= htmlspecialchars($displayName) ?></h2>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    <form method="POST">
      <label>First Name</label>
      <input type="text" name="first_name" value="<?= htmlspecialchars($member['first_name']) ?>">

      <label>Last Name</label>
      <input type="text" name="last_name" value="<?= htmlspecialchars($member['last_name']) ?>">

      <label>Company Name</label>
      <input type="text" name="company_name" value="<?= htmlspecialchars($member['company_name']) ?>">

      <label>Email</label>
      <input type="email" name="email" value="<?= htmlspecialchars($member['email']) ?>">

      <label>Card Number</label>
      <input type="text" name="card_number" value="<?= htmlspecialchars($member['card_number']) ?>">

      <label>Valid From</label>
      <input type="date" name="valid_from" value="<?= $member['valid_from'] ?>">

      <label>Valid Until</label>
      <input type="date" name="valid_until" value="<?= $member['valid_until'] ?>">

      <label>Monthly Fee</label>
      <input type="number" step="0.01" name="monthly_fee" value="<?= $member['monthly_fee'] ?>">

      <label>Payment Type</label>
      <select name="payment_type" required>
        <option value="card" <?= ($member['payment_type'] === 'card') ? 'selected' : '' ?>>Card</option>
        <option value="draft" <?= ($member['payment_type'] === 'draft') ? 'selected' : '' ?>>Draft</option>
        <option value="cash" <?= ($member['payment_type'] === 'cash') ? 'selected' : '' ?>>Cash</option>
        <option value="other" <?= ($member['payment_type'] === 'other') ? 'selected' : '' ?>>Other</option>
      </select>

      <label><input type="checkbox" name="is_primary" <?= $member['is_primary'] ? 'checked' : '' ?>> Primary Member</label>

      <?php if (!$member['is_primary']): ?>
      <label>Primary Member</label>
      <select name="primary_member_id">
        <option value="">-- Select Primary --</option>
        <?php while($p = $primaries->fetch_assoc()): 
          $pName = $p['company_name'] ? $p['company_name'] : trim($p['first_name'].' '.$p['last_name']);
        ?>
          <option value="<?= $p['id'] ?>" <?= ($member['primary_member_id'] == $p['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($pName) ?>
          </option>
        <?php endwhile; ?>
      </select>
      <?php endif; ?>

      <button type="submit">Save Changes</button>
      <a href="dashboard.php" style="margin-left:10px;">Cancel</a>
    </form>

    <?php if ($member['is_primary'] && $dependents && $dependents->num_rows > 0): ?>
    <div class="dependents-table">
      <h3>Dependents</h3>
      <table>
        <thead>
          <tr><th>ID</th><th>Name / Company</th><th>Email</th><th>Card #</th><th>Payment Type</th></tr>
        </thead>
        <tbody>
          <?php while($d = $dependents->fetch_assoc()): 
            $depName = $d['company_name'] ? $d['company_name'] : trim($d['first_name'].' '.$d['last_name']);
          ?>
            <tr>
              <td><?= $d['id'] ?></td>
              <td><?= htmlspecialchars($depName) ?></td>
              <td><?= htmlspecialchars($d['email']) ?></td>
              <td><?= htmlspecialchars($d['card_number']) ?></td>
              <td><?= htmlspecialchars($d['payment_type']) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>
