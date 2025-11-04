<?php
require __DIR__ . '/_auth.php';
verify_csrf(); // Verify CSRF token for POST requests

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $department_name = $conn->real_escape_string($_POST['department_name']);
  $base_price = floatval($_POST['base_price']);
  $tanning_price = floatval($_POST['tanning_price']);

  $insert = $conn->query("INSERT INTO department_pricing (department_name, base_price, tanning_price)
                          VALUES ('$department_name', $base_price, $tanning_price)");

  if ($insert) {
    header("Location: departments.php");
    exit;
  } else {
    $error = "Failed to add department: " . $conn->error;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Department</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <div class="content">
    <h2>Add New Department</h2>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    <form method="POST">
      <label>Department Name</label>
      <input type="text" name="department_name" required>

      <label>Base Price</label>
      <input type="number" step="0.01" name="base_price" required>

      <label>Tanning Add-on Price</label>
      <input type="number" step="0.01" name="tanning_price" value="0.00" required>

      <button type="submit">Add Department</button>
      <a href="departments.php" style="margin-left:10px;">Cancel</a>
    </form>
  </div>
</body>
</html>
