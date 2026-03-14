<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">2,847</div><div class="stat-label">Total Orders</div></div>
  <div class="stat-card green" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">2,614</div><div class="stat-label">Completed</div></div>
  <div class="stat-card orange" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">157</div><div class="stat-label">Pending</div></div>
  <div class="stat-card red" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">76</div><div class="stat-label">Cancelled</div></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="ticketSearch" type="text" placeholder="Search order ID or user..." oninput="filterTicketOrders(this.value)">
    </div>
    <select id="ticketStatusFilter" class="select-filter" onchange="filterTicketOrders()">
      <option>All Status</option><option>Completed</option><option>Pending</option><option>Cancelled</option><option>Refunded</option>
    </select>
    <select id="ticketPaymentFilter" class="select-filter" onchange="filterTicketOrders()">
      <option>All Payment</option><option>MoMo</option><option>VNPay</option><option>PayPal</option><option>Cash</option>
    </select>
    <div class="toolbar-right">
      <span id="ticketOrderCount" style="font-size:12px;color:var(--text-dim);">12 orders</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Order ID</th><th>User</th><th>Movie</th><th>Cinema</th><th>Seats</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody id="ticketOrdersBody"></tbody>
    </table>
  </div>
  <div id="ticketPagination"></div>
</div>

<script>
const ticketOrdersData = [
  {id:'#TK-2031',user:'Nguyen Van A',movie:'Avengers: Doomsday',cinema:'Galaxy',seats:2,total:'$14.00',payment:'MoMo',status:'Completed',date:'2026-03-12 14:32'},
  {id:'#TK-2030',user:'Tran Thi B',movie:'Cosmic Voyage II',cinema:'Premier',seats:4,total:'$28.00',payment:'VNPay',status:'Completed',date:'2026-03-12 13:14'},
  {id:'#TK-2029',user:'Hoang Minh C',movie:'The Last Breath',cinema:'Galaxy',seats:1,total:'$7.00',payment:'MoMo',status:'Pending',date:'2026-03-12 12:05'},
  {id:'#TK-2028',user:'Pham Duc Tuan',movie:'Haunted Echoes',cinema:'Galaxy',seats:3,total:'$21.00',payment:'PayPal',status:'Completed',date:'2026-03-12 11:20'},
  {id:'#TK-2027',user:'Vu Quoc E',movie:'Avengers: Doomsday',cinema:'Landmark',seats:2,total:'$14.00',payment:'VNPay',status:'Cancelled',date:'2026-03-12 10:44'},
  {id:'#TK-2026',user:'Do Thi D',movie:'Cosmic Voyage II',cinema:'Galaxy',seats:2,total:'$16.00',payment:'MoMo',status:'Refunded',date:'2026-03-11 18:12'},
  {id:'#TK-2025',user:'Ly Van F',movie:'The Last Breath',cinema:'Premier',seats:5,total:'$35.00',payment:'Cash',status:'Completed',date:'2026-03-11 17:32'},
  {id:'#TK-2024',user:'Nguyen Van A',movie:'Haunted Echoes',cinema:'Galaxy',seats:1,total:'$7.00',payment:'MoMo',status:'Completed',date:'2026-03-10 21:10'},
  {id:'#TK-2023',user:'Bui Thi G',movie:'Dragon Quest',cinema:'Galaxy',seats:4,total:'$32.00',payment:'VNPay',status:'Pending',date:'2026-03-10 19:08'},
  {id:'#TK-2022',user:'Dang Van H',movie:'Funny Bones 3',cinema:'Premier',seats:2,total:'$13.00',payment:'MoMo',status:'Completed',date:'2026-03-09 18:20'},
  {id:'#TK-2021',user:'Tran Minh I',movie:'Avengers: Doomsday',cinema:'Crescent',seats:6,total:'$48.00',payment:'PayPal',status:'Completed',date:'2026-03-09 15:54'},
  {id:'#TK-2020',user:'Le Thi J',movie:'The Heist',cinema:'Galaxy',seats:2,total:'$14.00',payment:'Cash',status:'Cancelled',date:'2026-03-08 20:05'},
];

function ticketOrderDetailBody(order) {
  return `<div style="display:flex;flex-direction:column;gap:16px;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="field"><label>Order ID</label><input class="input" value="${order.id}" readonly></div>
      <div class="field"><label>Status</label><input class="input" value="${order.status}" readonly></div>
      <div class="field"><label>Customer</label><input class="input" value="${order.user}" readonly></div>
      <div class="field"><label>Payment</label><input class="input" value="${order.payment}" readonly></div>
      <div class="field"><label>Movie</label><input class="input" value="${order.movie}" readonly></div>
      <div class="field"><label>Cinema</label><input class="input" value="${order.cinema}" readonly></div>
      <div class="field"><label>Seats</label><input class="input" value="${order.seats} seats" readonly></div>
      <div class="field"><label>Total</label><input class="input" value="${order.total}" readonly></div>
    </div>
  </div>`;
}

function handleTicketSectionAction() {
  showToast('Ticket orders exported', 'success');
}

function renderTicketOrders(data) {
  const startItem = data.length === 0 ? 0 : 1;
  document.getElementById('ticketOrdersBody').innerHTML = data.map(order => `
    <tr>
      <td class="td-id">${order.id}</td>
      <td class="td-bold">${order.user}</td>
      <td class="td-muted">${order.movie}</td>
      <td class="td-muted">${order.cinema}</td>
      <td>${order.seats} seats</td>
      <td style="font-weight:700;">${order.total}</td>
      <td><span class="badge gray">${order.payment}</span></td>
      <td>${statusBadge(order.status)}</td>
      <td class="td-muted">${order.date}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="openModal('Ticket Order Detail', ticketOrderDetailBody(ticketOrdersData.find(item => item.id === '${order.id}')))"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        <button class="action-btn del" title="Cancel" onclick="showToast('Order ${order.id} cancelled','error')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`).join('');
  document.getElementById('ticketPagination').innerHTML = buildPagination(`Showing ${startItem}-${data.length} of ${data.length} orders`, Math.max(1, Math.ceil(data.length / 10)));
}

function filterTicketOrders(q) {
  const searchInput = document.getElementById('ticketSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedStatus = document.getElementById('ticketStatusFilter')?.value || 'All Status';
  const selectedPayment = document.getElementById('ticketPaymentFilter')?.value || 'All Payment';
  const filtered = ticketOrdersData.filter(order => {
    const matchesQuery = searchTerm === '' || order.id.toLowerCase().includes(searchTerm) || order.user.toLowerCase().includes(searchTerm) || order.movie.toLowerCase().includes(searchTerm);
    const matchesStatus = selectedStatus === 'All Status' || order.status === selectedStatus;
    const matchesPayment = selectedPayment === 'All Payment' || order.payment === selectedPayment;
    return matchesQuery && matchesStatus && matchesPayment;
  });

  renderTicketOrders(filtered);
  document.getElementById('ticketOrderCount').textContent = `${filtered.length} orders`;
}

document.addEventListener('DOMContentLoaded', function () {
  filterTicketOrders();
});
</script>
