<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: text/html; charset=UTF-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo "Invalid member ID";
  exit;
}

$pdo = pdo();

// Fetch member record
$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$member) {
  http_response_code(404);
  echo "Member not found.";
  exit;
}

// Fetch all unique department names (from both department_pricing and members)
$stmt = $pdo->query("
  SELECT department_name AS name FROM department_pricing
  UNION
  SELECT DISTINCT department_name AS name FROM members
  WHERE department_name IS NOT NULL AND department_name <> ''
  ORDER BY name
");
$depts = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Edit Member — <?=htmlspecialchars($member['first_name'].' '.$member['last_name'])?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<style>
:root {
  --brand:#d81b60;
  --bg:#111;
  --text:#f2f2f2;
  --gray:#999;
  --card:#1c1c1c;
  --hover:#e33d7d;
}
* { box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
body {
  margin:0;
  background:var(--bg);
  color:var(--text);
  padding:2rem;
}
.container {
  max-width:650px;
  margin:0 auto;
  background:var(--card);
  padding:2rem;
  border-radius:12px;
  box-shadow:0 0 12px rgba(216,27,96,0.25);
}
h1 {
  color:var(--brand);
  text-align:center;
  margin-top:0;
}
form {
  display:flex;
  flex-direction:column;
  gap:1rem;
  margin-top:1rem;
}
label {
  font-size:0.9rem;
  color:var(--gray);
}
input, select {
  padding:0.5rem 0.75rem;
  background:#222;

