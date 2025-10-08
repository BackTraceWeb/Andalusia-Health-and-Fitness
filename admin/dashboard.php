<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Andalusia Health & Fitness — Admin CRM</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
  padding:2rem; display:flex; flex-direction:column;
}
h1 { text-align:center; color:var(--brand); margin-top:0; }

.stats {
  display:flex; justify-content:center; gap:2rem; flex-wrap:wrap; margin-bottom:1.5rem;
}
.stat {
  background:var(--card); padding:1rem 2rem; border-radius:10px; text-align:center;
  box-shadow:0 0 8px rgba(216,27,96,0.25);
}
.stat strong { font-size:1.8rem; display:block; }

.filters {
  display:flex; justify-content:center; gap:0.5rem; margin-bottom:1rem;
}
.filters button {
  background:#222; border:none; color:var(--text);
  padding:0.5rem 1rem; border-radius:6px; cursor:pointer;
}
.filters button.active, .filters button:hover { background:var(--brand); }

.search-box {
  display:flex; justify-content:center; margin-bottom:1rem;
}
.search-box input {
  width:320px; padding:0.5rem 1rem; border-radius:6px; border:none;
  background:#222; color:var(--text);
}

table {
  border-collapse:collapse; width:100%; margin-top:1rem;
}
th, td {
  text-align:left; padding:0.6rem 1rem;
}
th {
  background:var(--brand); color:#fff; text-transform:uppercase;
  font-size:0.75rem;
}
tr:nth-child(even) { background:#1a1a1a; }
tr:hover { background:#2a2a2a; cursor:pointer; }

.detail {
  position:fixed; top:0; right:0; width:400px; height:100%;
  background:#0f0f0f; color:var(--text); padding:1.5rem;
  border-left:2px solid var(--brand); overflow-y:auto; display:none;
}
.detail h2 { color:var(--brand); margin-top:0; }
.detail-close {
  position:absolute; right:10px; top:10px; cursor:pointer; color:#fff; font-size:20px;
}
.edit-btn {
  display:inline-block; background:var(--brand); color:#fff;
  border:none; border-radius:6px; padding:0.5rem 1rem;
  text-decoration:none; margin-top:1rem; cursor:pointer;
}
</style>
</head>
<body>

<h1>Andalusia Health & Fitness — Admin CRM</h1>

<div class="stats">
  <div class="stat"><strong id="stat-current">0</strong>Current</div>
  <div class="stat"><strong id="stat-due">0</strong>Due</div>
  <div class="stat"><strong id="stat-draft">0</strong>Draft</div>
  <div class="stat"><strong id="stat-total">0</strong>Total</div>
</div>

<div class="filters">
  <button data-filter="all" class="active">All</button>
  <button data-filter="current">Current</button>
  <button data-filter="due">Due</button>
  <button data-filter="draft">Draft</button>
  <button data-filter="cards">Cards</button>
  <button onclick="location='departments.php'">Departments</button>
</div>

<div class="search-box">
  <input type="text" id="search" placeholder="Search by name, card, or department...">
</div>

<table id="members-table">
  <thead>
    <tr>
      <th>ID</th>
      <th>Name</th>
      <th>Department</th>
      <th>Payment</th>
      <th>Monthly Fee</th>
      <th>Valid Until</th>
    </tr>
  </thead>
  <tbody>
    <tr><td colspan="6" style="text-align:center;">Loading members...</td></tr>
  </tbody>
</table>

<div class="detail" id="member-detail">
  <span class="detail-close" onclick="closeDetail()">×</span>
  <h2>Member Details</h2>
  <div id="detail-content"></div>
</div>

<script>
let allMembers = [];

async function loadMembers() {
  const res = await fetch('/api/members-list.php');
  const data = await res.json();

  if (!data.ok || !data.members) {
    document.querySelector('#members-table tbody').innerHTML =
      '<tr><td colspan="6" style="text-align:center;">Failed to load members.</td></tr>';
    return;
  }

  allMembers = data.members;
  updateStats();
  renderTable(allMembers);
}

function updateStats() {
  const total = allMembers.length;
  const current = allMembers.filter(m => m.status === 'current').length;
  const due = allMembers.filter(m => m.status === 'due').length;
  const draft = allMembers.filter(m => m.payment_type === 'draft').length;

  document.getElementById('stat-total').textContent = total;
  document.getElementById('stat-current').textContent = current;
  document.getElementById('stat-due').textContent = due;
  document.getElementById('stat-draft').textContent = draft;
}

function renderTable(list) {
  const tbody = document.querySelector('#members-table tbody');
  if (!list.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No members found.</td></tr>';
    return;
  }

  tbody.innerHTML = list.map(m => `
    <tr onclick="showDetail(${m.id})">
      <td>${m.id}</td>
      <td>${m.first_name} ${m.last_name || ''}</td>
      <td>${m.department_name || ''}</td>
      <td>${m.payment_type}</td>
      <td>$${m.monthly_fee}</td>
      <td>${m.valid_until || ''}</td>
    </tr>
  `).join('');
}

function filterMembers(type) {
  document.querySelectorAll('.filters button').forEach(b => b.classList.remove('active'));
  document.querySelector(`.filters button[data-filter="${type}"]`).classList.add('active');

  let filtered = allMembers;
  if (type === 'current') filtered = allMembers.filter(m => m.status === 'current');
  else if (type === 'due') filtered = allMembers.filter(m => m.status === 'due');
  else if (type === 'draft') filtered = allMembers.filter(m => m.payment_type === 'draft');

  renderTable(filtered);
}

document.querySelectorAll('.filters button[data-filter]').forEach(btn => {
  btn.addEventListener('click', () => filterMembers(btn.dataset.filter));
});

document.getElementById('search').addEventListener('input', e => {
  const q = e.target.value.toLowerCase();
  const filtered = allMembers.filter(m =>
    (m.first_name && m.first_name.toLowerCase().includes(q)) ||
    (m.last_name && m.last_name.toLowerCase().includes(q)) ||
    (m.department_name && m.department_name.toLowerCase().includes(q))
  );
  renderTable(filtered);
});

async function showDetail(id) {
  const res = await fetch(`/api/member-detail.php?id=${id}`);
  const data = await res.json();

  const box = document.getElementById('member-detail');
  const content = document.getElementById('detail-content');
  box.style.display = 'block';

  if (!data.ok || !data.member) {
    content.innerHTML = '<p>Failed to load member details.</p>';
    return;
  }

  const m = data.member;
  content.innerHTML = `
    <h3>${m.first_name} ${m.last_name || ''}</h3>
    <p><strong>Department:</strong> ${m.department_name || '—'}</p>
    <p><strong>Payment Type:</strong> ${m.payment_type}</p>
    <p><strong>Status:</strong> <span style="color:${m.status === 'current' ? '#4caf50' : '#f44336'};">${m.status}</span></p>
    <p><strong>Valid From:</strong> ${m.valid_from || '—'}</p>
    <p><strong>Valid Until:</strong> ${m.valid_until || '—'}</p>
    <p><strong>Monthly Fee:</strong> $${m.monthly_fee}</p>
    <p><strong>Company:</strong> ${m.company_name || '—'}</p>
    <p><strong>Last Updated:</strong> ${m.updated_at || '—'}</p>
    <a href="edit-member.php?id=${m.id}" class="edit-btn">✏️ Edit Member</a>
  `;
}

function closeDetail() {
  document.getElementById('member-detail').style.display = 'none';
}

loadMembers();
</script>
</body>
</html>
