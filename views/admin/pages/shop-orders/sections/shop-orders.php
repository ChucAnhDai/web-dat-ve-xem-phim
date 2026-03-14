<div class="card" style="margin-bottom:20px;">
  <div class="card-body" style="padding:24px;">
    <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:4px;letter-spacing:0.5px;text-transform:uppercase;">Order #SH-0042 / Live Status</div>
    <div style="font-size:13px;color:var(--text-dim);margin-bottom:20px;">Customer: Hoang Minh C / 3 items / $22.50 / MoMo</div>
    <div class="pipeline">
      <div class="pipe-step done"><div class="pipe-circle">1</div><div class="pipe-label">PLACED</div></div>
      <div class="pipe-step done"><div class="pipe-circle">2</div><div class="pipe-label">CONFIRMED</div></div>
      <div class="pipe-step done"><div class="pipe-circle">3</div><div class="pipe-label">PACKED</div></div>
      <div class="pipe-step active"><div class="pipe-circle">4</div><div class="pipe-label">SHIPPED</div></div>
      <div class="pipe-step"><div class="pipe-circle">5</div><div class="pipe-label">DELIVERED</div></div>
    </div>
  </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div style="font-size:22px;font-family:'Bebas Neue',sans-serif;">847</div><div class="stat-label">Total Orders</div></div>
  <div class="stat-card orange" style="padding:16px;"><div style="font-size:22px;font-family:'Bebas Neue',sans-serif;">42</div><div class="stat-label">Pending</div></div>
  <div class="stat-card blue" style="padding:16px;"><div style="font-size:22px;font-family:'Bebas Neue',sans-serif;">120</div><div class="stat-label">Confirmed</div></div>
  <div class="stat-card purple" style="padding:16px;"><div style="font-size:22px;font-family:'Bebas Neue',sans-serif;">95</div><div class="stat-label">Shipped</div></div>
  <div class="stat-card green" style="padding:16px;"><div style="font-size:22px;font-family:'Bebas Neue',sans-serif;">590</div><div class="stat-label">Delivered</div></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="shopOrderSearch" type="text" placeholder="Search orders..." oninput="filterShopOrders(this.value)">
    </div>
    <select id="shopOrderStatus" class="select-filter" onchange="filterShopOrders()">
      <option>All Status</option><option>Pending</option><option>Confirmed</option><option>Shipped</option><option>Delivered</option><option>Cancelled</option>
    </select>
    <select id="shopOrderPayment" class="select-filter" onchange="filterShopOrders()">
      <option>All Payment</option><option>MoMo</option><option>VNPay</option><option>PayPal</option><option>Cash</option>
    </select>
    <div class="toolbar-right">
      <span id="shopOrderCount" style="font-size:12px;color:var(--text-dim);">10 orders</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Order ID</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Address</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody id="shopOrdersBody"></tbody>
    </table>
  </div>
  <div id="shopOrdersPagination"></div>
</div>

<script>
const shopOrdersData = [
  {id:'#SH-0042',user:'Hoang Minh C',items:3,total:'$22.50',payment:'MoMo',status:'Shipped',addr:'12 Le Loi, District 1, HCM',date:'2026-03-12'},
  {id:'#SH-0041',user:'Nguyen Van A',items:2,total:'$11.70',payment:'VNPay',status:'Delivered',addr:'45 Hai Ba Trung, District 3, HCM',date:'2026-03-11'},
  {id:'#SH-0040',user:'Tran Thi B',items:5,total:'$45.00',payment:'PayPal',status:'Pending',addr:'88 Nguyen Hue, District 1, HCM',date:'2026-03-11'},
  {id:'#SH-0039',user:'Vu Quoc E',items:1,total:'$3.20',payment:'Cash',status:'Delivered',addr:'CineShop Galaxy Counter',date:'2026-03-10'},
  {id:'#SH-0038',user:'Le Admin',items:4,total:'$67.00',payment:'MoMo',status:'Confirmed',addr:'33 Dong Khoi, District 1, HCM',date:'2026-03-10'},
  {id:'#SH-0037',user:'Pham Duc Tuan',items:2,total:'$24.00',payment:'VNPay',status:'Shipped',addr:'22 Pasteur, District 3, HCM',date:'2026-03-09'},
  {id:'#SH-0036',user:'Bui Thi G',items:6,total:'$38.50',payment:'MoMo',status:'Delivered',addr:'100 Cach Mang Thang 8, District 10',date:'2026-03-09'},
  {id:'#SH-0035',user:'Dang Van H',items:1,total:'$8.50',payment:'Cash',status:'Delivered',addr:'CineShop Premier Counter',date:'2026-03-08'},
  {id:'#SH-0034',user:'Tran Minh I',items:3,total:'$51.00',payment:'PayPal',status:'Cancelled',addr:'15 Ly Tu Trong, District 1, HCM',date:'2026-03-08'},
  {id:'#SH-0033',user:'Le Thi J',items:2,total:'$13.00',payment:'VNPay',status:'Delivered',addr:'78 Vo Van Tan, District 3, HCM',date:'2026-03-07'},
];

function shopOrderDetailBody(order) {
  return `<div style="display:flex;flex-direction:column;gap:16px;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="field"><label>Order ID</label><input class="input" value="${order.id}" readonly></div>
      <div class="field"><label>Status</label><input class="input" value="${order.status}" readonly></div>
      <div class="field"><label>Customer</label><input class="input" value="${order.user}" readonly></div>
      <div class="field"><label>Payment</label><input class="input" value="${order.payment}" readonly></div>
      <div class="field form-full"><label>Address</label><input class="input" value="${order.addr}" readonly></div>
      <div class="field"><label>Items</label><input class="input" value="${order.items}" readonly></div>
      <div class="field"><label>Total</label><input class="input" value="${order.total}" readonly></div>
    </div>
  </div>`;
}

function handleShopOrderSectionAction() {
  showToast('Shop orders exported', 'success');
}

function renderShopOrders(data) {
  const startItem = data.length === 0 ? 0 : 1;
  document.getElementById('shopOrdersBody').innerHTML = data.map(order => `
    <tr>
      <td class="td-id">${order.id}</td>
      <td class="td-bold">${order.user}</td>
      <td>${order.items} item${order.items > 1 ? 's' : ''}</td>
      <td style="font-weight:700;color:var(--gold);">${order.total}</td>
      <td><span class="badge gray">${order.payment}</span></td>
      <td>${statusBadge(order.status)}</td>
      <td class="td-muted" style="font-size:12px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${order.addr}</td>
      <td class="td-muted">${order.date}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="openModal('Order Detail', shopOrderDetailBody(shopOrdersData.find(item => item.id === '${order.id}')))"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        <button class="action-btn edit" title="Edit" onclick="openModal('Order Detail', shopOrderDetailBody(shopOrdersData.find(item => item.id === '${order.id}')))"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" title="Cancel" onclick="showToast('${order.id} cancelled','error')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`).join('');
  document.getElementById('shopOrdersPagination').innerHTML = buildPagination(`Showing ${startItem}-${data.length} of ${data.length} orders`, Math.max(1, Math.ceil(data.length / 10)));
}

function filterShopOrders(q) {
  const searchInput = document.getElementById('shopOrderSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedStatus = document.getElementById('shopOrderStatus')?.value || 'All Status';
  const selectedPayment = document.getElementById('shopOrderPayment')?.value || 'All Payment';
  const filtered = shopOrdersData.filter(order => {
    const matchesQuery = searchTerm === '' || order.id.toLowerCase().includes(searchTerm) || order.user.toLowerCase().includes(searchTerm) || order.addr.toLowerCase().includes(searchTerm);
    const matchesStatus = selectedStatus === 'All Status' || order.status === selectedStatus;
    const matchesPayment = selectedPayment === 'All Payment' || order.payment === selectedPayment;
    return matchesQuery && matchesStatus && matchesPayment;
  });

  renderShopOrders(filtered);
  document.getElementById('shopOrderCount').textContent = `${filtered.length} orders`;
}

document.addEventListener('DOMContentLoaded', function () {
  filterShopOrders();
});
</script>
