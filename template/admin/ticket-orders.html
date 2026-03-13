<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticket Orders — CineShop Admin</title>
<link rel="stylesheet" href="shared.css">
</head>
<body>
<div class="layout">
    <div id="sidebarMount"></div>
<div class="main-wrap" id="mainWrap">
        <div id="headerMount"></div>
<div class="page">
      <div class="page-header">
        <div>
          <div class="breadcrumb"><span>Home</span><span class="sep">›</span><span>Ticket Orders</span></div>
          <h1 class="page-title">Ticket Orders</h1>
          <p class="page-sub">All cinema booking transactions</p>
        </div>
        <div style="display:flex;gap:10px;">
          <button class="btn btn-ghost" onclick="showToast('Exported to CSV','success')">Export CSV</button>
        </div>
      </div>

      <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="stat-card blue" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">2,847</div><div class="stat-label">Total Orders</div></div>
        <div class="stat-card green" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">2,614</div><div class="stat-label">Completed</div></div>
        <div class="stat-card orange" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">157</div><div class="stat-label">Pending</div></div>
        <div class="stat-card red" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">76</div><div class="stat-label">Cancelled</div></div>
      </div>

      <div class="card">
        <div class="toolbar">
          <div class="toolbar-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" placeholder="Search order ID or user...">
          </div>
          <select class="select-filter"><option>All Status</option><option>Completed</option><option>Pending</option><option>Cancelled</option><option>Refunded</option></select>
          <select class="select-filter"><option>All Payment</option><option>MoMo</option><option>VNPay</option><option>PayPal</option><option>Cash</option></select>
          <input type="date" class="select-filter" style="width:auto;">
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr>
              <th>Order ID</th><th>User</th><th>Movie</th><th>Cinema</th><th>Seats</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody id="ticketOrdersBody"></tbody>
          </table>
        </div>
        <div id="ticketPagination"></div>
      </div>
    </div>
  </div>
</div>
<script src="shared.js"></script>
<script>
const ticketOrders = [
  {id:'#TK-2031',user:'Nguyen Van A',movie:'Avengers: Doomsday',cinema:'Galaxy',seats:2,total:'$14.00',payment:'MoMo',status:'Completed',date:'Today 14:32'},
  {id:'#TK-2030',user:'Tran Thi B',movie:'Cosmic Voyage II',cinema:'Premier',seats:4,total:'$28.00',payment:'VNPay',status:'Completed',date:'Today 13:14'},
  {id:'#TK-2029',user:'Hoang Minh C',movie:'The Last Breath',cinema:'Galaxy',seats:1,total:'$7.00',payment:'MoMo',status:'Pending',date:'Today 12:05'},
  {id:'#TK-2028',user:'Pham Duc Tuan',movie:'Haunted Echoes',cinema:'Galaxy',seats:3,total:'$21.00',payment:'PayPal',status:'Completed',date:'Today 11:20'},
  {id:'#TK-2027',user:'Vu Quoc E',movie:'Avengers: Doomsday',cinema:'Landmark',seats:2,total:'$14.00',payment:'VNPay',status:'Cancelled',date:'Today 10:44'},
  {id:'#TK-2026',user:'Do Thi D',movie:'Cosmic Voyage II',cinema:'Galaxy',seats:2,total:'$16.00',payment:'MoMo',status:'Refunded',date:'Mar 11'},
  {id:'#TK-2025',user:'Ly Van F',movie:'The Last Breath',cinema:'Premier',seats:5,total:'$35.00',payment:'Cash',status:'Completed',date:'Mar 11'},
  {id:'#TK-2024',user:'Nguyen Van A',movie:'Haunted Echoes',cinema:'Galaxy',seats:1,total:'$7.00',payment:'MoMo',status:'Completed',date:'Mar 10'},
  {id:'#TK-2023',user:'Bui Thi G',movie:'Dragon Quest',cinema:'Galaxy',seats:4,total:'$32.00',payment:'VNPay',status:'Pending',date:'Mar 10'},
  {id:'#TK-2022',user:'Dang Van H',movie:'Funny Bones 3',cinema:'Premier',seats:2,total:'$13.00',payment:'MoMo',status:'Completed',date:'Mar 9'},
  {id:'#TK-2021',user:'Tran Minh I',movie:'Avengers: Doomsday',cinema:'Crescent',seats:6,total:'$48.00',payment:'PayPal',status:'Completed',date:'Mar 9'},
  {id:'#TK-2020',user:'Le Thi J',movie:'The Heist',cinema:'Galaxy',seats:2,total:'$14.00',payment:'Cash',status:'Cancelled',date:'Mar 8'},
];

function viewOrder(id) {
  const o = ticketOrders.find(x=>x.id===id);
  if(!o) return;
  openModal(`Order ${o.id} — Detail`, `
    <div style="display:flex;flex-direction:column;gap:16px;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="field"><label>Order ID</label><input class="input" value="${o.id}" readonly></div>
        <div class="field"><label>Status</label><input class="input" value="${o.status}" readonly></div>
        <div class="field"><label>Customer</label><input class="input" value="${o.user}" readonly></div>
        <div class="field"><label>Payment</label><input class="input" value="${o.payment}" readonly></div>
        <div class="field"><label>Movie</label><input class="input" value="${o.movie}" readonly></div>
        <div class="field"><label>Cinema</label><input class="input" value="${o.cinema}" readonly></div>
        <div class="field"><label>Seats</label><input class="input" value="${o.seats} seats" readonly></div>
        <div class="field"><label>Total</label><input class="input" value="${o.total}" readonly></div>
      </div>
      <div style="border-top:1px solid var(--border);padding-top:16px;">
        <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:10px;letter-spacing:0.5px;text-transform:uppercase;">Booked Seats</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          ${Array.from({length:o.seats},(_,i)=>`<span class="badge red">C${i+3}</span>`).join('')}
        </div>
      </div>
    </div>`);
}

document.addEventListener('DOMContentLoaded', function(){

  document.getElementById('ticketOrdersBody').innerHTML = ticketOrders.map(o=>`
    <tr>
      <td class="td-id">${o.id}</td>
      <td class="td-bold">${o.user}</td>
      <td class="td-muted">${o.movie}</td>
      <td class="td-muted">${o.cinema}</td>
      <td>${o.seats} seats</td>
      <td style="font-weight:700;">${o.total}</td>
      <td><span class="badge gray">${o.payment}</span></td>
      <td>${statusBadge(o.status)}</td>
      <td class="td-muted">${o.date}</td>
      <td><div class="actions-row">
        <button class="action-btn view" onclick="viewOrder('${o.id}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        <button class="action-btn del" onclick="showToast('Order ${o.id} cancelled','error')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`).join('');
  document.getElementById('ticketPagination').innerHTML = buildPagination('Showing 1–12 of 2,847 orders', 238);
});
</script>
    <div id="footerMount"></div>
</body>
</html>
