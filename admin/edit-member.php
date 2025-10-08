<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: text/html; charset=UTF-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo "Invalid member ID";
  exit;
}

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
  color:var(--text);
  border:none;
  border-radius:6px;
  width:100%;
}
input:focus, select:focus { outline:1px solid var(--brand); }
button {
  background:var(--brand);
  border:none;
  color:#fff;
  padding:0.7rem 1.4rem;
  border-radius:8px;
  cursor:pointer;
  font-weight:600;
  transition:background 0.2s;
}
button:hover { background:var(--hover); }
.back {
  display:inline-block;
  background:#222;
  color:var(--gray);
  padding:0.6rem 1rem;
  border-radius:6px;
  text-decoration:none;
  margin-top:1.5rem;
}
.back:hover { background:#333; color:#fff; }
.success {
  background:#155724;
  color:#d4edda;
  padding:0.6rem 1rem;
  border-radius:6px;
  text-align:center;
  margin-top:1rem;
  display:none;
}
.error {
  background:#721c24;
  color:#f8d7da;
  padding:0.6rem 1rem;
  border-radius:6px;
  text-align:center;
  margin-top:1rem;
  display:none;
}
</style>
</head>
<body>
  <div class="container">
    <h1>Edit Member</h1>

    <form id="editForm">
      <input type="hidden" name="id" value="<?=$member['id']?>">

      <div>
        <label>First Name</label>
        <input type="text" name="first_name" value="<?=htmlspecialchars($member['first_name'])?>">
      </div>
      <div>
        <label>Last Name</label>
        <input type="text" name="last_name" value="<?=htmlspecialchars($member['last_name'])?>">
      </div>
      <div>
        <label>Department</label>
        <select name="department_name">
          <option value="">-- None --</option>
          <?php foreach ($depts as $d): ?>
            <option value="<?=htmlspecialchars($d)?>" <?=($member['department_name']===$d?'selected':'')?>>
              <?=htmlspecialchars($d)?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Payment Type</label>
        <select name="payment_type">
          <?php
          $types = ['card'=>'Card','draft'=>'Draft','cash'=>'Cash','other'=>'Other'];
          foreach($types as $k=>$v): ?>
            <option value="<?=$k?>" <?=($member['payment_type']===$k?'selected':'')?>><?=$v?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Monthly Fee ($)</label>
        <input type="number" name="monthly_fee" step="0.01" value="<?=htmlspecialchars($member['monthly_fee'])?>">
      </div>
      <div>
        <label>Valid From</label>
        <input type="date" name="valid_from" value="<?=$member['valid_from']?>">
      </div>
      <div>
        <label>Valid Until</label>
        <input type="date" name="valid_until" value="<?=$member['valid_until']?>">
      </div>

      <button type="submit">💾 Save Changes</button>
    </form>

    <a href="dashboard.php" class="back">← Back to Dashboard</a>

    <div class="success" id="msg-success">✅ Saved successfully!</div>
    <div class="error" id="msg-error">❌ Save failed.</div>
  </div>

<script>
document.getElementById('editForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const form = e.target;
  const msgOk=document.getElementById('msg-success');
  const msgErr=document.getElementById('msg-error');
  msgOk.style.display=msgErr.style.display='none';

  try {
    const formData = new FormData(form); // ← real HTML form post
    const res = await fetch('/api/member-save.php', {
      method: 'POST',
      body: formData
    });

    const out = await res.json();
    console.log('member-save response:', out); // for debugging in console

    if (out.ok === true) {
      msgOk.textContent = '✅ Member saved successfully!';
      msgOk.style.display = 'block';
    } else {
      msgErr.textContent = '❌ ' + (out.error || out.message || 'Save failed.');
      msgErr.style.display = 'block';
    }
  } catch (err) {
    console.error('Save error:', err);
    msgErr.textContent = '❌ Network or server error.';
    msgErr.style.display = 'block';
  }
});
</script>

</body>
</html>
