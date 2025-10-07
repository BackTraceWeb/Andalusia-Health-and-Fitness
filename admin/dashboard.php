<?php
require __DIR__ . '/../_bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Member Dashboard — Andalusia Health & Fitness</title>
<link rel="stylesheet" href="admin.css">
<style>
:root {
  --brand:#d81b60;
  --bg:#111;
  --card:#181818;
  --text:#fff;
}
body {
  background:var(--bg);
  color:var(--text);
  font-family: 'Segoe UI', Roboto, sans-serif;
  margin:0; padding:0;
}
nav {
  background:var(--card);
  padding:1em 2em;
  display:flex;
  justify-content:space-between;
  align-items:center;
  border-bottom:2px solid var(--brand);
}
nav h1 {
  font-size:1.3em;
  color:var(--brand);
  margin:0;
}
nav a {
  color:var(--text);
  text-decoration:none;
  margin-left:1.5em;
  font-weight:500;
}
nav a:hover { color:var(--brand); }

.container {
  max-width:1100px;
  margin:2em auto;
  background:var(--card);
  padding:2em;
  border-radius:12px;
  box-shadow:0 0 10px rgba(0,0,0,0.5);
}

h2 {
  text-align:center;
  color:var(--brand);
  margin-bottom:1em;
}

table {
  width:100%;
  border-collapse:collapse;
  color:#ddd;
}
th, td {
  padding:0.7em;
  text-align:left;
}
th {
  background:var(--brand);
  color:#fff;
}
tr:nth-child(even) { background:#1e1e1e; }
tr:hover { background:#222; }

.btn-edit {
  background:var(--brand);
  color:#fff;
  padding:0.3em 0.8em;
  border-radius:5px;
  text-decoration:none;
  font-size:0.9em;
}
.btn-edit:hover {
  background:#e91e63;
}

.footer {
  text-align:center;
  margin-top:2em;
  font-size:0.9em;
  color:#999;
}
</style>
</head>
<body>

<nav>
  <h1>Andalusia Health & Fitness CRM</h1>
  <div>
    <a href="dashboard.php">Dashboard</a>
    <a href="departments.php">Departments</a>
    <a href="logout.php">Logout</a>
  </div>
</nav>

<div class="container">
  <h2>Active Members</h2>
  <table id="membersTable">
    <thead>
      <tr>
        <th>ID</th>
        <th>First</th>
        <th>Last</th>
        <th>Department</th>
        <th>Payment</th>
        <th>Valid Until</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>

<div class="footer">© <?=date('Y')?> Andalusia Health & Fitness</div>

<script>
async function loadMembers() {
  const res = await fetch('/api/members-list.php');
  const data = await res.json();
  const tbody = document.querySelector('#membersTable tbody');
  tbody.innerHTML = '';

  if (!data.members || data.members.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">No members found</td></tr>`;
    return;
  }

  data.members.forEach(m => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${m.id}</td>
      <td>${m.first_name || ''}</td>
      <td>${m.last_name || ''}</td>
      <td>${m.department_name || '-'}</td>
      <td>${m.payment_type || '-'}</td>
      <td>${m.valid_until || '-'}</td>
      <td><a class="btn-edit" href="edit-member.php?id=${m.id}">✏️ Edit</a></td>
    `;
    tbody.appendChild(tr);
  });
}

loadMembers();
</script>

</body>
</html>
