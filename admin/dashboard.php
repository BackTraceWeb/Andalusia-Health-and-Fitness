<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../_bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Andalusia Health & Fitness — Admin CRM</title>
  <style>
    :root {
      --brand:#d81b60;
      --bg:#111;
      --text:#f2f2f2;
      --gray:#999;
      --card:#1c1c1c;
      --hover:#e33d7d;
    }
    * { box-sizing:border-box; font-family: 'Segoe UI', sans-serif; }
    body {
      margin:0; display:flex; height:100vh; color:var(--text);
      background:var(--bg); overflow:hidden;
    }

    /* Left main section */
    .main {
      flex:2.2; display:flex; flex-direction:column;
      padding:1.5rem 2rem; overflow:hidden;
    }

    h1 {
      color:var(--brand);
      font-size:1.6rem;
      text-align:center;
      margin:0.3rem 0 1rem;
    }
    p.subtitle {
      text-align:center; color:var(--gray);
      margin-bottom:1.5rem;
    }

    /* Stat cards */
    .stats {
      display:flex; justify-content:center; gap:1rem; flex-wrap:wrap;
    }
    .stat {
      background:var(--card);
      padding:0.75rem 1.25rem;
      border-radius:12px;
      text-align:center;
      box-shadow:0 0 12px rgba(216,27,96,0.25);
      transition:transform 0.2s;
    }
    .stat:hover { transform:scale(1.05); }
    .stat h3 { margin:0; font-size:1rem; color:var(--gray); }
    .stat p { margin:0.2rem 0 0; font-size:1.3rem; color:var(--text); }

    /* Filters */
    .filters {
      margin:1.5rem 0 1rem;
      display:flex; justify-content:center; flex-wrap:wrap; gap:0.5rem;
    }
    .filters button {
      border:none; padding:0.4rem 0.9rem;
      border-radius:8px; background:#222; color:var(--text);
      cursor:pointer; transition:background 0.2s, transform 0.1s;
    }
    .filters button.active {
      background:var(--brand);
    }
    .filters button:hover { background:var(--hover); }

    /* Search */
    .searchbar {
      display:flex; justify-content:center; margin-bottom:1rem;
    }
    .searchbar input {
      width:70%; max-width:450px;
      padding:0.5rem 0.9rem;
      border:none; border-radius:6px;
      background:#222; color:var(--text);
      outline:none;
    }

    /* Member table */
    .table {
      flex:1; overflow-y:auto; border-radius:12px;
      border:1px solid #222; background:#0d0d0d;
    }
    table {
      width:100%; border-collapse:collapse;
      color:var(--text); font-size:0.9rem;
    }
    th, td {
      padding:0.65rem 0.9rem;
      border-bottom:1px solid #222;
      text-align:left;
    }
    th {
      background:var(--brand);
      color:#fff; text-transform:uppercase;
      font-size:0.75rem;
      position:sticky; top:0;
    }
    tr:hover td { background:#1a1a1a; cursor:pointer; }

    /* Right detail panel */
    .detail {
      flex:1; border-left:2px solid #222; background:#0b0b0b;
      display:flex; flex-direction:column; padding:1rem;
      overflow-y:auto; transition:transform 0.3s ease;
    }
    .detail.hidden { transform:translateX(100%); opacity:0; }
    .detail.visible { transform:translateX(0); opacity:1; }

    .detail h2 { color:var(--brand); font-size:1.1rem; margin-top:0; }
    .close-btn {
      position:absolute; top:15px; right:25px;
      background:none; border:none; color:var(--text);
      font-size:1.4rem; cursor:pointer;
    }
  </style>
</head>
<body>
  <div class="main">
    <h1>Andalusia Health & Fitness — Admin CRM</h1>
    <p class="subtitle">Manage members, track dues, and view renewals</p>

    <div class="stats">
      <div class="stat"><h3>Current</h3><p id="stat-current">0</p></div>
      <div class="stat"><h3>Due</h3><p id="stat-due">0</p></div>
      <div class="stat"><h3>Draft</h3><p id="stat-draft">0</p></div>
      <div class="stat"><h3>Total</h3><p id="stat-total">0</p></div>
    </div>

    <div class="filters">
      <button class="active" data-filter="all">All</button>
      <button data-filter="current">Current</button>
      <button data-filter="due">Due</button>
      <button data-filter="draft">Draft</button>
      <button data-filter="cards">Cards</button>
    </div>

    <div class="searchbar">
      <input id="search" type="text" placeholder="Search by name, card, or department..." />
    </div>

    <div class="table">
      <table id="members-table">
        <thead>
          <tr>
            <th>ID</th><th>Name</th><th>Department</th><th>Payment</th><th>Status</th><th>Valid Until</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="6" style="text-align:center;color:#777;">Loading members...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="detail hidden" id="member-detail">
    <button class="close-btn" onclick="closeDetail()">×</button>
    <h2>Member Details</h2>
    <div id="detail-content" style="color:var(--text); font-size:0.9rem;">Select a member to view details</div>
  </div>

  <script>
  const tableBody = document.querySelector('#members-table tbody');
  const detailPanel = document.getElementById('member-detail');
  const detailContent = document.getElementById('detail-content');
  const searchInput = document.getElementById('search');

  async function loadMembers() {
    const res = await fetch('/api/members-list.php'); // you can make this endpoint
    const data = await res.json().catch(()=>({members:[]}));
    const members = data.members || [];

    document.getElementById('stat-current').textContent = members.filter(m=>m.status==='current').length;
    document.getElementById('stat-due').textContent = members.filter(m=>m.status==='due').length;
    document.getElementById('stat-draft').textContent = members.filter(m=>m.payment_type==='draft').length;
    document.getElementById('stat-total').textContent = members.length;

    renderMembers(members);
    window._members = members;
  }

  function renderMembers(list) {
    if (!list.length) {
      tableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:#777;">No members found.</td></tr>`;
      return;
    }
    tableBody.innerHTML = list.map(m=>`
      <tr onclick="openDetail(${m.id})">
        <td>${m.id}</td>
        <td>${m.first_name} ${m.last_name}</td>
        <td>${m.department_name||'-'}</td>
        <td>${m.payment_type||'-'}</td>
        <td>${m.status||'-'}</td>
        <td>${m.valid_until||'-'}</td>
      </tr>`).join('');
  }

  function filterMembers(type) {
    document.querySelectorAll('.filters button').forEach(b=>b.classList.remove('active'));
    document.querySelector(`.filters button[data-filter="${type}"]`).classList.add('active');
    const base = window._members || [];
    let filtered = base;
    if (type==='current') filtered = base.filter(m=>m.status==='current');
    else if (type==='due') filtered = base.filter(m=>m.status==='due');
    else if (type==='draft') filtered = base.filter(m=>m.payment_type==='draft');
    renderMembers(filtered);
  }

  function searchMembers() {
    const term = searchInput.value.toLowerCase();
    const base = window._members || [];
    const filtered = base.filter(m =>
      `${m.first_name} ${m.last_name}`.toLowerCase().includes(term) ||
      (m.department_name||'').toLowerCase().includes(term) ||
      (m.card_number||'').includes(term)
    );
    renderMembers(filtered);
  }

  async function openDetail(id) {
    detailPanel.classList.remove('hidden');
    detailPanel.classList.add('visible');
    detailContent.innerHTML = '<p style="color:#888;">Loading...</p>';
    try {
      const res = await fetch(`/api/member-detail.php?id=${id}`);
      const html = await res.text();
      detailContent.innerHTML = html;
    } catch(e){
      detailContent.innerHTML = '<p style="color:#f66;">Failed to load member details.</p>';
    }
  }

  function closeDetail(){
    detailPanel.classList.add('hidden');
    detailPanel.classList.remove('visible');
  }

  document.querySelectorAll('.filters button').forEach(b=>{
    b.addEventListener('click', ()=>filterMembers(b.dataset.filter));
  });
  searchInput.addEventListener('input', searchMembers);
  loadMembers();
  </script>
</body>
</html>
