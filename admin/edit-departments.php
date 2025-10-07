<?php
session_start();
if (empty($_SESSION['logged_in'])) {
  header('Location: index.php');
  exit;
}
require 'config.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid department ID.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $department_name = $conn->real_escape_string($_POST['department_name']);
  $base_price = floatval($_POST['base_price']);
  $tanning_price = floatval($_POST['tanning_price']);

  $update = $conn->query("UPDATE department_pricing
                          SET department_name='$department_name', base_price=$base_price, tanning_price=$tanning_price
                          WHERE dept_price_id=$id");

  if ($update) {
    header("Location: departments.php");
    exit;
  } else {
    $error = "Failed to update department: " . $conn->error;
  }
}

$result = $conn->query("SELECT * FROM department_pricing WHERE dept_price_id=$id LIMIT 1");
$dept = $result->fetch_assoc();
if (!$dept) die("Department not found.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Department</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <div class="content">
    <h2>Edit Department: <?= htmlspecialchars($dept['department_name']) ?></h2>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    <form method="POST">
      <label>Department Name</label>
      <input type="text" name="department_name" value="<?= htmlspecialchars($dept['department_name']) ?>" required>

      <label>Base Price</label>
      <input type="number" step="0.01" name="base_price" value="<?= $dept['base_price'] ?>" required>

      <label>Tanning Add-on Price</label>
      <input type="number" step="0.01" name="tanning_price" value="<?= $dept['tanning_price'] ?>" required>

      <button type="submit">Save Changes</button>
      <a href="departments.php" style="margin-left:10px;">Cancel</a>
    </form>
  </div>
</body>
</html>
