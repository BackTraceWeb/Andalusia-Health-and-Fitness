<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: text/html; charset=utf-8');

$pdo = pdo();

// --- Ensure departments from members are mirrored into department_pricing ---
$missingDepts = $pdo->query("
  SELECT DISTINCT m.department_name
  FROM members m
  LEFT JOIN department_pricing d ON m.department_name = d.department_name
  WHERE m.department_name IS NOT NULL
    AND m.department_name <> ''
    AND d.department_name IS NULL
")->fetchAll(PDO::FETCH_COLUMN);

if ($missingDepts) {
  $ins = $pdo->prepare("
    INSERT INTO department_pricing (department_name, base_price, tanning_addon)
    VALUES (:name, 0.00, 0.00)
  ");
  foreach ($missingDepts as $deptName) {
    $ins->execute([':name' => $deptName]);
    file_put_contents(
      __DIR__ . '/../logs/axtrax-sync.log',
      date('c') . " - Auto-added missing department from members: {$deptName}\n",
      FILE_APPEND
    );
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Departments — Andalusia Health & Fitness</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<style>
:root {
  --brand:#d81b60;
  --bg:#111;
  --text:#f2f2f2;
  --gray:#aaa;
  --card:#1c1c1c;
  --hover:#e33d7d;
}
* { box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
body {
  margin:0; background:var(--bg); color:var(--text);
  padding:2rem; display:flex; flex-direction:column; align-items:center;
}
h1 {
  color:var(--brand); margin-bottom:0.3rem;
}
p.subtitle {
  color:var(--gray); margin-bottom:1.5rem;
}
table {
  border-collapse:collapse; width:90%; max-width:900px;
  background:var(--card); border-radius:8px; overflow:hidden;
}
th, td {
  padding:0.6rem 1rem; text-align:left;
  border-bottom:1px solid #222;
}
th { background:var(--brand); color:#fff; text-transform:uppercase; font-size:0.75rem; }
tr:hover td { background:#1a1a1a; }
input[type="text"], input[type="number"] {
  background:#222; color:var(--text);
  border:none; padding:0.4rem 0.6rem; border-radius:4px;
  width:100%;
}
button {
  background:var(--brand); border:none; color:#fff;
  padding:0.4rem 0.8rem; border-radius:6px;
  cursor:pointer; transition:background 0.2s;
}
button:hover { background:var(--hover); }
button.small { font-size:0.8rem; padding:0.3rem 0.6rem; margin-left:0.3rem; }
form.add-dept {
  margin-top:1.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;
  justify-content:center;
}
form.add-dept input { width:200px; }
.back {
  display:inline-block; margin-top:1.5rem;
  text-decoration:none; color:var(--gray);
  padding:0.5rem 1rem; background:#222; border-radius:6px;
}
.back:hover { color:#fff; background:#333; }
.status {
  margin-top:1rem; font-size:0.9rem;
}
</style>
</head>
<body>
  <h1>Departments</h1>
  <p class="subtitle">Manage department names, default pricing, and apply base fees to members</p>

  <table id="dept-table">
    <thead>
      <tr><th>ID</th><th>Name</th><th>Base Price</th><th>Tanning Add-on</th><th>Actions</th></tr>
    </thead>
    <tbody><tr><td colspan="5" style="text-align:center;">Loading...</td></tr></tbody>
  </table>

  <form class="add-dept" id="addDeptForm">
    <input type="text" name="department_name" placeholder="New Department" required>
    <input type="number" step="0.01" name="base_price" placeholder="Base Price" required>
    <input type="number" step="0.01" name="tanning_price" placeholder="Tanning Price">
    <button type="submit">＋ Add Department</button>
  </form>

  <a href="dashboard.php" class="back">← Back to Dashboard</a>
  <div class="status" id="status"></div>

<script>
const tbody = document.querySelector('#dept-table tbody');
const status = document.getElementById('status');

async function loadDepartments(){
  const res = await fetch('/api/departments-list.php');
  const data = await res.json();
  renderTable(data.departments || []);
}

function renderTable(list){
  if(!list.length){
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No departments found</td></tr>`;
    return;
  }
  tbody.innerHTML = list.map(d=>`
    <tr data-id="${d.dept_price_id}">
      <td>${d.dept_price_id}</td>
      <td><input type="text" value="${d.department_name}"></td>
      <td><input type="number" step="0.01" value="${d.base_price}"></td>
      <td><input type="number" step="0.01" value="${d.tanning_price}"></td>
      <td>
        <button onclick="saveDept(${d.dept_price_id}, this)">💾 Save</button>
        <button class="small" onclick="applyDefault(${d.dept_price_id}, '${d.department_name}')">💲 Apply Default Fee</button>
      </td>
    </tr>
  `).join('');
}

async function saveDept(id, btn){
  const row = btn.closest('tr');
  const name = row.querySelector('td:nth-child(2) input').value;
  const base = row.querySelector('td:nth-child(3) input').value;
  const tan  = row.querySelector('td:nth-child(4) input').value;
  const res = await fetch('/api/department-save.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({id, department_name:name, base_price:base, tanning_price:tan})
  });
  const out = await res.json();
  status.textContent = out.ok ? '✅ Saved successfully' : '❌ Save failed';
  status.style.color = out.ok ? '#4caf50' : '#f44336';
  loadDepartments();
}

async function applyDefault(id, name){
  if(!confirm(`Apply default fee to all members in ${name}?`)) return;
  const res = await fetch('/api/department-apply-default.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({department_id:id})
  });
  const out = await res.json();
  status.textContent = out.ok ? `✅ Applied default fee to ${out.updated} members in ${name}` : '❌ Failed to apply';
  status.style.color = out.ok ? '#4caf50' : '#f44336';
}

document.getElementById('addDeptForm').addEventListener('submit', async e=>{
  e.preventDefault();
  const form = e.target;
  const data = Object.fromEntries(new FormData(form).entries());
  const res = await fetch('/api/department-save.php',{
    method:'POST', headers:{'Content-Type':'application/json'},
    body:JSON.stringify(data)
  });
  const out = await res.json();
  status.textContent = out.ok ? '✅ Added successfully' : '❌ Add failed';
  status.style.color = out.ok ? '#4caf50' : '#f44336';
  form.reset();
  loadDepartments();
});
loadDepartments();
</script>
</body>
</html>
