<?php
session_start();
if (empty($_SESSION['logged_in'])) {
  header('Location: index.php');
  exit;
}

require 'config.php';

// pull all active members within 6 months or draft
$q = "
  SELECT id, first_name, last_name, company_name, department_name, payment_type,
         valid_until, status
  FROM members
  WHERE (valid_until >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         OR payment_type = 'draft')
  ORDER BY department_name, last_name
";
$result = $conn->query($q);
$members = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>AHF Admin Dashboard</title>
  <link rel="stylesheet" href="admin.css">
  <style>
    :root {
      --pink:#e91e63;
      --dark:#0b0b0b;
      --gray:#1b1b1b;
      --text:#fff;
      --green:#2ecc71;
      --red:#e74c3c;
      --yellow:#f1c40f;
    }
    body {
      background:var(--dark);
      color:var(--text);
      font-family:'Inter',sans-serif;
      margin:0;
      padding:2rem;
    }
    header{
      text-align:center;
      margin-bottom:1.5rem;
    }
    h2{color:var(--pink);margin-bottom:.25rem;}
    .stats{
      display:flex;justify-content:center;gap:1.5rem;margin-bottom:1.5rem;flex-wrap:wrap;
    }
    .stat{
      background:var(--gray);padding:.8rem 1.2rem;border-radius:10px;
      font-weight:600;box-shadow:0 0 10px rgba(233,30,99,.2);
    }
    .stat span{display:block;font-size:.9rem;color:#bbb;}
    .toolbar{
      display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;
      gap:.5rem;margin-bottom:1rem;
    }
    .filters{
      display:flex;gap:.5rem;flex-wrap:wrap;
    }
    .filter{
      background:var(--gray);padding:.4rem .9rem;border-radius:8px;
      cursor:pointer;transition:background .2s ease;font-size:.9rem;
    }
    .filter.active,.filter:hover{background:var(--pink);}
    .view-toggle{
      display:flex;gap:.3rem;
    }
    .toggle-btn{
      background:var(--gray);border:none;color:var(--text);
      padding:.4rem .7rem;border-radius:6px;cursor:pointer;font-weight:600;
    }
    .toggle-btn.active{background:var(--pink);}
    .searchbar{
      flex:1;display:flex;justify-content:flex-end;
    }
    .searchbar input{
      width:100%;max-width:300px;padding:.5rem .8rem;
      border:none;border-radius:8px;background:var(--gray);color:#fff;
    }
    .cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem;}
    .card{
      background:#111;border-radius:10px;padding:1rem;
      box-shadow:0 0 15px rgba(233,30,99,.15);
      transition:transform .2s ease;
    }
    .card:hover{transform:translateY(-3px);}
    .card h3{margin:0;font-size:1.1rem;}
    .card small{color:#aaa;}
    .badge{
      display:inline-block;padding:.25rem .6rem;border-radius:6px;font-size:.8rem;font-weight:600;
    }
    .badge.current{background:var(--green);}
    .badge.due{background:var(--red);}
    .badge.draft{background:var(--yellow);color:#000;}
    table{
      width:100%;border-collapse:collapse;background:#111;border-radius:10px;
      overflow:hidden;margin-top:1rem;
    }
    th,td{padding:.6rem;text-align:left;}
    th{background:var(--pink);}
    tr:nth-child(even){background:#151515;}
    tr:hover{background:#1e1e1e;}
    .drawer{
      position:fixed;top:0;right:-400px;width:400px;height:100%;background:#111;
      box-shadow:-2px 0 10px rgba(0,0,0,.5);transition:right .3s ease;
      padding:1rem;overflow-y:auto;z-index:100;
    }
    .drawer.open{right:0;}
    .drawer h3{margin-top:0;color:var(--pink);}
    .close-drawer{background:none;border:none;color:#fff;font-size:1.5rem;float:right;cursor:pointer;}
  </style>
</head>
<body>
  <header>
    <h2>Andalusia Health & Fitness — Admin CRM</h2>
    <p style="color:#aaa;">Manage members, track dues, and view renewals</p>
  </header>

  <div class="stats">
    <?php
      $total = count($members);
      $current = count(array_filter($members, fn($m)=>$m['status']==='current'));
      $due = count(array_filter($members, fn($m)=>$m['status']==='due'));
      $draft = count(array_filter($members, fn($m)=>$m['payment_type']==='draft'));
    ?>
    <div class="stat"><span>Current</span><?= $current ?></div>
    <div class="stat"><span>Due</span><?= $due ?></div>
    <div class="stat"><span>Draft</span><?= $draft ?></div>
    <div class="stat"><span>Total</span><?= $total ?></div>
  </div>

  <div class="toolbar">
    <div class="filters">
      <div class="filter active" data-filter="all">All</div>
      <div class="filter" data-filter="current">Current</div>
      <div class="filter" data-filter="due">Due</div>
      <div class="filter" data-filter="draft">Draft</div>
    </div>
    <div class="view-toggle">
      <button class="toggle-btn active" data-view="cards">Cards</button>
      <button class="toggle-btn" data-view="table">List</button>
    </div>
    <div class="searchbar">
      <input type="text" id="searchInput" placeholder="Search members...">
    </div>
  </div>

  <div id="cardsView" class="cards">
    <?php foreach ($members as $m): ?>
      <?php
        $name = $m['company_name'] ?: trim($m['first_name'].' '.$m['last_name']);
        $badge = $m['status']==='current' ? 'current' : ($m['payment_type']==='draft' ? 'draft' : 'due');
      ?>
      <div class="card" data-status="<?= $m['status'] ?>" data-payment="<?= $m['payment_type'] ?>">
        <h3><?= htmlspecialchars($name) ?></h3>
        <small><?= htmlspecialchars($m['department_name'] ?? '') ?></small><br>
        <span class="badge <?= $badge ?>"><?= ucfirst($badge) ?></span><br><br>
        <small>Valid Until: <?= htmlspecialchars($m['valid_until'] ?? '—') ?></small><br>
        <button onclick="openDrawer(<?= $m['id'] ?>)" class="toggle-btn" style="margin-top:.5rem;">View</button>
      </div>
    <?php endforeach; ?>
  </div>

  <table id="tableView" style="display:none;">
    <thead>
      <tr><th>ID</th><th>Name</th><th>Department</th><th>Valid Until</th><th>Payment</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php foreach ($members as $m): ?>
        <?php $name = $m['company_name'] ?: trim($m['first_name'].' '.$m['last_name']); ?>
        <tr data-status="<?= $m['status'] ?>" data-payment="<?= $m['payment_type'] ?>">
          <td><?= $m['id'] ?></td>
          <td><?= htmlspecialchars($name) ?></td>
          <td><?= htmlspecialchars($m['department_name']) ?></td>
          <td><?= htmlspecialchars($m['valid_until'] ?? '—') ?></td>
          <td><?= ucfirst($m['payment_type']) ?></td>
          <td><?= ucfirst($m['status']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="drawer" id="drawer">
    <button class="close-drawer" onclick="closeDrawer()">&times;</button>
    <h3>Member Details</h3>
    <div id="drawerContent" style="color:#ddd;font-size:.95rem;">
      Loading...
    </div>
  </div>

  <script>
    const filters=document.querySelectorAll('.filter');
    const cards=document.querySelectorAll('.card');
    const rows=document.querySelectorAll('tbody tr');
    const search=document.getElementById('searchInput');
    const viewBtns=document.querySelectorAll('.toggle-btn');
    const cardsView=document.getElementById('cardsView');
    const tableView=document.getElementById('tableView');

    // filtering
    filters.forEach(f=>{
      f.addEventListener('click',()=>{
        filters.forEach(x=>x.classList.remove('active'));
        f.classList.add('active');
        const val=f.dataset.filter;
        cards.forEach(c=>{c.style.display=(val==='all'||c.dataset.status===val||c.dataset.payment===val)?'block':'none';});
        rows.forEach(r=>{r.style.display=(val==='all'||r.dataset.status===val||r.dataset.payment===val)?'':'none';});
      });
    });

    // search filter
    search.addEventListener('input',()=>{
      const q=search.value.toLowerCase();
      [cards,rows].forEach(list=>{
        list.forEach(el=>{
          const text=el.textContent.toLowerCase();
          el.style.display=text.includes(q)?(el.tagName==='TR'?'':'block'):'none';
        });
      });
    });

    // view toggle
    viewBtns.forEach(b=>{
      b.addEventListener('click',()=>{
        viewBtns.forEach(x=>x.classList.remove('active'));
        b.classList.add('active');
        const view=b.dataset.view;
        cardsView.style.display=view==='cards'?'grid':'none';
        tableView.style.display=view==='table'?'table':'none';
      });
    });

    // drawer
    function openDrawer(id){
      const dr=document.getElementById('drawer');
      const content=document.getElementById('drawerContent');
      dr.classList.add('open');
      fetch(`../api/member-detail.php?id=${id}`).then(r=>r.text()).then(t=>content.innerHTML=t).catch(()=>content.innerHTML='Error loading member.');
    }
    function closeDrawer(){document.getElementById('drawer').classList.remove('open');}
  </script>
</body>
</html>
