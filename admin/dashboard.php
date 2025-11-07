<?php
require __DIR__ . '/_auth.php';
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
  --brand-light:#f06292;
  --bg:#0a0b0d;
  --text:#f2f2f2;
  --gray:#aaa;
  --card:#1a1d23;
  --hover:#2a2d35;
  --success:#4caf50;
  --warning:#ff9800;
  --danger:#f44336;
  --info:#2196f3;
}

* { box-sizing:border-box; font-family:'Segoe UI',sans-serif; }

body {
  margin:0; background:var(--bg); color:var(--text);
  padding:2rem; display:flex; flex-direction:column;
  min-height:100vh;
}

h1 {
  text-align:center; color:var(--brand); margin-top:0; margin-bottom:2rem;
  font-size:2rem; font-weight:700; letter-spacing:0.5px;
}

/* Stats Cards */
.stats {
  display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
  gap:1.5rem; margin-bottom:2rem; max-width:1400px; margin-left:auto; margin-right:auto;
}

.stat {
  background:linear-gradient(135deg, #1e2228 0%, #2a2f38 100%);
  padding:1.5rem; border-radius:12px; text-align:center;
  box-shadow:0 4px 12px rgba(0,0,0,0.3);
  border:1px solid rgba(255,255,255,0.05);
  transition:transform 0.2s ease, box-shadow 0.2s ease;
  position:relative;
  overflow:hidden;
}

.stat:hover {
  transform:translateY(-4px);
  box-shadow:0 8px 20px rgba(216,27,96,0.3);
}

.stat::before {
  content:'';
  position:absolute;
  top:0; right:0;
  width:100px; height:100px;
  background:radial-gradient(circle, rgba(216,27,96,0.1) 0%, transparent 70%);
  border-radius:50%;
}

.stat-icon {
  font-size:2rem;
  margin-bottom:0.5rem;
  opacity:0.8;
}

.stat strong {
  font-size:2.5rem; display:block; font-weight:800;
  background:linear-gradient(135deg, var(--brand) 0%, var(--brand-light) 100%);
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  background-clip:text;
}

.stat-label {
  font-size:0.9rem; color:var(--gray);
  text-transform:uppercase; letter-spacing:1px;
  margin-top:0.5rem;
}

/* Filters */
.filters {
  display:flex; justify-content:center; gap:0.75rem; margin-bottom:1.5rem;
  flex-wrap:wrap;
}

.filters button {
  background:#222529; border:1px solid rgba(255,255,255,0.1);
  color:var(--text); padding:0.65rem 1.2rem; border-radius:8px;
  cursor:pointer; font-weight:600; font-size:0.9rem;
  transition:all 0.2s ease; display:inline-flex; align-items:center; gap:0.5rem;
}

.filters button:hover {
  background:#2a2d35; border-color:var(--brand); transform:translateY(-2px);
}

.filters button.active {
  background:var(--brand); border-color:var(--brand);
  box-shadow:0 4px 12px rgba(216,27,96,0.4);
}

/* Search */
.search-box {
  display:flex; justify-content:center; margin-bottom:1.5rem;
}

.search-box input {
  width:100%; max-width:500px; padding:0.75rem 1.2rem;
  border-radius:10px; border:1px solid rgba(255,255,255,0.1);
  background:#222529; color:var(--text); font-size:1rem;
  transition:all 0.2s ease;
}

.search-box input:focus {
  outline:none; border-color:var(--brand);
  box-shadow:0 0 0 3px rgba(216,27,96,0.2);
}

/* Table Container */
.table-container {
  max-width:1400px; margin:0 auto; width:100%;
  background:var(--card); border-radius:12px;
  box-shadow:0 4px 12px rgba(0,0,0,0.3);
  overflow:hidden;
}

table {
  border-collapse:collapse; width:100%;
}

th, td {
  text-align:left; padding:1rem 1.25rem;
}

th {
  background:linear-gradient(135deg, #1a1d23 0%, #222529 100%);
  color:var(--gray); text-transform:uppercase;
  font-size:0.75rem; font-weight:700; letter-spacing:1px;
  border-bottom:2px solid var(--brand);
  cursor:pointer; user-select:none;
  transition:background 0.2s ease;
}

th:hover {
  background:#2a2d35;
}

th.sortable::after {
  content:'⇅';
  margin-left:0.5rem;
  opacity:0.4;
}

th.sorted-asc::after {
  content:'↑';
  opacity:1;
  color:var(--brand);
}

th.sorted-desc::after {
  content:'↓';
  opacity:1;
  color:var(--brand);
}

tbody tr {
  border-bottom:1px solid rgba(255,255,255,0.05);
  transition:background 0.2s ease;
}

tbody tr:hover {
  background:#2a2d35; cursor:pointer;
}

/* Status Badges */
.badge {
  display:inline-block; padding:0.35rem 0.75rem;
  border-radius:12px; font-size:0.75rem; font-weight:700;
  text-transform:uppercase; letter-spacing:0.5px;
}

.badge-current {
  background:rgba(76,175,80,0.2); color:var(--success);
  border:1px solid rgba(76,175,80,0.4);
}

.badge-due {
  background:rgba(244,67,54,0.2); color:var(--danger);
  border:1px solid rgba(244,67,54,0.4);
}

.badge-draft {
  background:rgba(33,150,243,0.2); color:var(--info);
  border:1px solid rgba(33,150,243,0.4);
}

.badge-manual {
  background:rgba(255,152,0,0.2); color:var(--warning);
  border:1px solid rgba(255,152,0,0.4);
}

/* Detail Panel */
.detail {
  position:fixed; top:0; right:0; width:450px; height:100%;
  background:linear-gradient(135deg, #1a1d23 0%, #222529 100%);
  color:var(--text); padding:2rem;
  border-left:3px solid var(--brand); overflow-y:auto; display:none;
  box-shadow:-4px 0 20px rgba(0,0,0,0.5);
}

.detail h2 {
  color:var(--brand); margin-top:0; font-size:1.5rem;
  margin-bottom:1.5rem;
}

.detail-close {
  position:absolute; right:20px; top:20px;
  cursor:pointer; color:#fff; font-size:28px;
  width:40px; height:40px; display:flex; align-items:center;
  justify-content:center; border-radius:50%;
  background:rgba(255,255,255,0.1);
  transition:all 0.2s ease;
}

.detail-close:hover {
  background:var(--danger); transform:rotate(90deg);
}

.detail-info {
  background:rgba(255,255,255,0.03);
  padding:1rem; border-radius:8px;
  margin-bottom:1rem; border-left:3px solid var(--brand);
}

.detail-info p {
  margin:0.5rem 0; display:flex; justify-content:space-between;
}

.detail-info strong {
  color:var(--gray);
}

.edit-btn {
  display:inline-block; background:var(--brand); color:#fff;
  border:none; border-radius:8px; padding:0.75rem 1.5rem;
  text-decoration:none; margin-top:1rem; cursor:pointer;
  font-weight:600; transition:all 0.2s ease;
  box-shadow:0 4px 12px rgba(216,27,96,0.4);
}

.edit-btn:hover {
  background:var(--brand-light); transform:translateY(-2px);
  box-shadow:0 6px 16px rgba(216,27,96,0.5);
}

/* Mobile responsiveness */
@media (max-width: 768px) {
  body { padding: 1rem; }
  h1 { font-size: 1.5rem; }

  .stats { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
  .stat { padding: 1rem; }
  .stat strong { font-size: 2rem; }

  .filters { gap: 0.5rem; }
  .filters button { padding: 0.5rem 0.9rem; font-size: 0.85rem; }

  .search-box input { max-width: 100%; }

  .table-container { overflow-x: auto; }

  th, td { padding: 0.75rem; font-size: 0.85rem; }

  .detail {
    width: 100%; left: 0; right: 0;
    border-left: none; border-top: 3px solid var(--brand);
  }
}

@media (max-width: 480px) {
  .stats { grid-template-columns: 1fr; }

  /* Hide department and payment columns on tiny screens */
  th:nth-child(3), td:nth-child(3),
  th:nth-child(4), td:nth-child(4) {
    display: none;
  }
}
</style>
</head>
<body>

<h1>🏋️ Andalusia Health & Fitness — Admin CRM</h1>

<div class="stats">
  <div class="stat">
    <div class="stat-icon">✅</div>
    <strong id="stat-current">0</strong>
    <div class="stat-label">Current</div>
  </div>
  <div class="stat">
    <div class="stat-icon">⚠️</div>
    <strong id="stat-due">0</strong>
    <div class="stat-label">Due</div>
  </div>
  <div class="stat">
    <div class="stat-icon">💳</div>
    <strong id="stat-draft">0</strong>
    <div class="stat-label">Draft</div>
  </div>
  <div class="stat">
    <div class="stat-icon">👥</div>
    <strong id="stat-total">0</strong>
    <div class="stat-label">Total</div>
  </div>
</div>

<div class="filters">
  <button data-filter="all" class="active">📋 All</button>
  <button data-filter="current">✅ Current</button>
  <button data-filter="due">⚠️ Due</button>
  <button data-filter="draft">💳 Draft</button>
  <button data-filter="cards">🔑 Cards</button>
  <button onclick="location='departments.php'">🏢 Departments</button>
</div>

<div class="search-box">
  <input type="text" id="search" placeholder="🔍 Search by name, card, or department...">
</div>

<div class="table-container">
  <table id="members-table">
    <thead>
      <tr>
        <th class="sortable" data-column="id">ID</th>
        <th class="sortable" data-column="name">Name</th>
        <th class="sortable" data-column="department">Department</th>
        <th class="sortable" data-column="payment">Payment</th>
        <th class="sortable" data-column="fee">Monthly Fee</th>
        <th class="sortable" data-column="valid_until">Valid Until</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="7" style="text-align:center; padding:2rem;">Loading members...</td></tr>
    </tbody>
  </table>
</div>

<div class="detail" id="member-detail">
  <span class="detail-close" onclick="closeDetail()">×</span>
  <h2>Member Details</h2>
  <div id="detail-content"></div>
</div>

<script>
let allMembers = [];
let currentSort = { column: 'id', direction: 'asc' };

async function loadMembers() {
  const res = await fetch('/api/members-list.php');
  const data = await res.json();

  if (!data.ok || !data.members) {
    document.querySelector('#members-table tbody').innerHTML =
      '<tr><td colspan="7" style="text-align:center; padding:2rem;">Failed to load members.</td></tr>';
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

function getStatusBadge(status) {
  if (status === 'current') return '<span class="badge badge-current">✓ Current</span>';
  if (status === 'due') return '<span class="badge badge-due">! Due</span>';
  return '<span class="badge">' + status + '</span>';
}

function getPaymentBadge(type) {
  if (type === 'draft') return '<span class="badge badge-draft">Draft</span>';
  return '<span class="badge badge-manual">Manual</span>';
}

function sortTable(column) {
  // Toggle direction if same column
  if (currentSort.column === column) {
    currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
  } else {
    currentSort.column = column;
    currentSort.direction = 'asc';
  }

  // Update header styling
  document.querySelectorAll('th').forEach(th => {
    th.classList.remove('sorted-asc', 'sorted-desc');
  });

  const th = document.querySelector(`th[data-column="${column}"]`);
  if (th) {
    th.classList.add(currentSort.direction === 'asc' ? 'sorted-asc' : 'sorted-desc');
  }

  // Sort the data
  const sorted = [...allMembers].sort((a, b) => {
    let aVal, bVal;

    if (column === 'id') {
      aVal = parseInt(a.id);
      bVal = parseInt(b.id);
    } else if (column === 'name') {
      aVal = (a.first_name || '') + ' ' + (a.last_name || '');
      bVal = (b.first_name || '') + ' ' + (b.last_name || '');
    } else if (column === 'department') {
      aVal = a.department_name || '';
      bVal = b.department_name || '';
    } else if (column === 'payment') {
      aVal = a.payment_type || '';
      bVal = b.payment_type || '';
    } else if (column === 'fee') {
      aVal = parseFloat(a.monthly_fee || 0);
      bVal = parseFloat(b.monthly_fee || 0);
    } else if (column === 'valid_until') {
      aVal = a.valid_until || '';
      bVal = b.valid_until || '';
    }

    if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
    if (aVal > bVal) return currentSort.direction === 'asc' ? 1 : -1;
    return 0;
  });

  renderTable(sorted);
}

function renderTable(list) {
  const tbody = document.querySelector('#members-table tbody');
  if (!list.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:2rem;">No members found.</td></tr>';
    return;
  }

  tbody.innerHTML = list.map(m => `
    <tr onclick="showDetail(${m.id})">
      <td><strong>#${m.id}</strong></td>
      <td>${m.first_name} ${m.last_name || ''}</td>
      <td>${m.department_name || '<span style="color:#666;">—</span>'}</td>
      <td>${getPaymentBadge(m.payment_type)}</td>
      <td><strong>$${m.monthly_fee}</strong></td>
      <td>${m.valid_until || '<span style="color:#666;">—</span>'}</td>
      <td>${getStatusBadge(m.status)}</td>
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

// Sortable columns
document.querySelectorAll('th.sortable').forEach(th => {
  th.addEventListener('click', () => sortTable(th.dataset.column));
});

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
    <h3 style="margin-top:0;">${m.first_name} ${m.last_name || ''}</h3>
    <div style="margin-bottom:1.5rem;">${getStatusBadge(m.status)}</div>

    <div class="detail-info">
      <p><strong>Member ID:</strong> <span>#${m.id}</span></p>
      <p><strong>Department:</strong> <span>${m.department_name || '—'}</span></p>
      <p><strong>Company:</strong> <span>${m.company_name || '—'}</span></p>
    </div>

    <div class="detail-info">
      <p><strong>Payment Type:</strong> <span>${getPaymentBadge(m.payment_type)}</span></p>
      <p><strong>Monthly Fee:</strong> <span style="color:var(--brand); font-weight:700;">$${m.monthly_fee}</span></p>
    </div>

    <div class="detail-info">
      <p><strong>Valid From:</strong> <span>${m.valid_from || '—'}</span></p>
      <p><strong>Valid Until:</strong> <span>${m.valid_until || '—'}</span></p>
      <p><strong>Last Updated:</strong> <span>${m.updated_at || '—'}</span></p>
    </div>

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
