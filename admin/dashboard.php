<?php
session_start();
if (empty($_SESSION['logged_in'])) {
  header('Location: index.php');
  exit;
}
require 'config.php';

// --- Search ---
$search = trim($_GET['search'] ?? '');
$searchLike = '%' . $conn->real_escape_string($search) . '%';

// --- Filter (6 months current only) ---
$where = "
  (
    valid_until >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    OR payment_type = 'draft'
  )
";
if ($search !== '') {
  $where .= " AND (
    first_name LIKE '$searchLike'
    OR last_name LIKE '$searchLike'
    OR company_name LIKE '$searchLike'
    OR department_name LIKE '$searchLike'
  )";
}

$q = "
  SELECT id, first_name, last_name, company_name,
         department_name, valid_until, status, payment_type
  FROM members
  WHERE $where
  ORDER BY status DESC, department_name, last_name
";
$result = $conn->query($q);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Member Dashboard — AHF Admin</title>
  <link rel="stylesheet" href="admin.css">
  <style>
    .status-current { color:#2ecc71; font-weight:600; }
    .status-due { color:#e74c3c; font-weight:600; }
   .searchbar {
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  margin: 1rem 0;
}

.searchbar input[type="text"] {
  flex: 1;
  max-width: 400px;
  padding: 0.6rem 1rem;
  border-radius: 8px;
  border: none;
  background: #1b1b1b;
  color: #fff;
  font-size: 1rem;
}

.searchbar input[type="text"]::placeholder {
  color: #999;
}

.searchbar button {
  background: #e91e63;
  color: #fff;
  font-weight: 600;
  border: none;
  border-radius: 8px;
  padding: 0.6rem 1rem;
  cursor: pointer;
  transition: background 0.2s ease;
}

.searchbar button:hover {
  background: #ff4081;
}

    .back-btn {
      display:inline-block;
      background:#444;
      color:#fff;
      padding:.4rem .8rem;
      border-radius:.5rem;
      text-decoration:none;
      margin-bottom:1rem;
      font-weight:600;
    }
  </style>
</head>
<body>
  <div class="content">
    <h2>Active Members (Last 6 Months)</h2>
    <a href="dashboard.php" class="back-btn">⟵ Back to Dashboard</a>

    <form method="get" class="searchbar">
      <input type="text" name="search" placeholder="Search by name, company, or department…" value="<?= htmlspecialchars($search) ?>">
      <button type="submit">Search</button>
    </form>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Name / Company</th>
          <th>Department</th>
          <th>Valid Until</th>
          <th>Payment Type</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
            $displayName = $row['company_name']
              ?: trim($row['first_name'] . ' ' . $row['last_name']);
            $validUntil = $row['valid_until']
              ? date('M d, Y', strtotime($row['valid_until'])) : '—';
          ?>
          <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($displayName) ?></td>
            <td><?= htmlspecialchars($row['department_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($validUntil) ?></td>
            <td><?= ucfirst(htmlspecialchars($row['payment_type'] ?? '')) ?></td>
            <td class="status-<?= htmlspecialchars($row['status']) ?>">
              <?= ucfirst($row['status']) ?>
            </td>
            <td><a href="edit-member.php?id=<?= $row['id'] ?>">Edit</a></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="7">No members found in the last 6 months.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
