<?php
session_start();
if (empty($_SESSION['logged_in'])) {
  header('Location: index.php');
  exit;
}
require 'config.php';

// Pull all department pricing records
$result = $conn->query("SELECT * FROM department_pricing ORDER BY dept_price_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Departments - Andalusia Health & Fitness</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <div class="topbar">
    <h1>Department Pricing</h1>
    <div>
      <a href="dashboard.php" style="margin-right:15px;">Members</a>
      <a href="logout.php">Logout</a>
    </div>
  </div>

  <div class="content">
    <h2>Departments & Packages</h2>

    <!-- Add Department Button -->
    <div style="text-align:right; margin-bottom:15px;">
      <a href="add-department.php"
         style="background:#ff1e78;
                color:#fff;
                padding:8px 16px;
                border-radius:50px;
                text-decoration:none;
                font-weight:bold;">+ Add Department</a>
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Department Name</th>
          <th>Base Price</th>
          <th>Tanning Add-on</th>
          <th>Default Fee</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?= $row['dept_price_id'] ?></td>
              <td><?= htmlspecialchars($row['department_name']) ?></td>
              <td>$<?= number_format($row['base_price'], 2) ?></td>
              <td>$<?= number_format($row['tanning_price'], 2) ?></td>
              <td>$<?= number_format($row['default_fee'], 2) ?></td>
              <td class="actions">
                <a href="edit-department.php?id=<?= $row['dept_price_id'] ?>">Edit</a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align:center;">No departments found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
