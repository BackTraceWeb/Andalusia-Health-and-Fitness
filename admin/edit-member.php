<?php
require __DIR__ . '/../_bootstrap.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
  die("<p style='color:#f66;'>Invalid member ID.</p>");
}

$stmt = $pdo->prepare("SELECT * FROM members WHERE id = :id LIMIT 1");
$stmt->execute([':id'=>$id]);
$member = $stmt->fetch();

if (!$member) die("<p style='color:#f66;'>Member not found.</p>");

$deps = $pdo->query("SELECT DISTINCT department_name FROM members WHERE department_name IS NOT NULL ORDER BY department_name")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Member — <?= htmlspecialchars($member['first_name'].' '.$member['last_name']) ?></title>
<link rel="stylesheet" href="admin.css">
<style>
body{background:#111;color:#fff;font-family:sans-serif;margin:0;padding:2em;}
.card{background:#181818;border-radius:10px;padding:1.5em;max-width:600px;margin:auto;}
label{display:block;margin-top:1em;color:#ccc;}
input,select{width:100%;padding:.5em;border:none;border-radius:6px;margin-top:.3em;background:#222;color:#fff;}
button{background:#d81b60;color:#fff;padding:.6em 1.2em;border:none;border-radius:6px;margin-top:1em;cursor:pointer;}
button:hover{background:#e91e63;}
a{color:#d81b60;text-decoration:none;}
</style>
</head>
<body>
<div class="card">
  <h2>Edit Member</h2>
  <form method="POST" action="/api/member-save.php">
    <input type="hidden" name="id" value="<?= $member['id'] ?>">

    <label>First Name</label>
    <input type="text" name="first_name" value="<?= htmlspecialchars($member['first_name']) ?>">

    <label>Last Name</label>
    <input type="text" name="last_name" value="<?= htmlspecialchars($member['last_name']) ?>">

    <label>Department</label>
    <select name="department_name">
      <?php foreach($deps as $dep): ?>
        <option value="<?= htmlspecialchars($dep) ?>" <?= $dep == $member['department_name'] ? 'selected' : '' ?>><?= htmlspecialchars($dep) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Payment Type</label>
    <select name="payment_type">
      <?php foreach(['card','draft','cash','other'] as $pt): ?>
        <option value="<?= $pt ?>" <?= $pt == $member['payment_type'] ? 'selected' : '' ?>><?= ucfirst($pt) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Status</label>
    <select name="status">
      <?php foreach(['current','due','draft'] as $s): ?>
        <option value="<?= $s ?>" <?= $s == $member['status'] ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Valid Until</label>
    <input type="date" name="valid_until" value="<?= $member['valid_until'] ?>">

    <button type="submit">💾 Save Changes</button>
    <a href="dashboard.php">← Back to Dashboard</a>
  </form>
</div>
</body>
</html>
