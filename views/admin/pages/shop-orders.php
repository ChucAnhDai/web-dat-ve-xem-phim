<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">›</span><span>Shop Orders</span></div>
      <h1 class="page-title">Shop Orders</h1>
      <p class="page-sub">Product purchase management</p>
    </div>
    <button class="btn btn-ghost" onclick="showToast('Exported!','success')">Export CSV</button>
  </div>

  <div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:24px;">
      <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:4px;letter-spacing:0.5px;text-transform:uppercase;">Order #SH-20240312-0042 — Live Status</div>
      <div style="font-size:13px;color:var(--text-dim);margin-bottom:20px;">Customer: Hoang Minh C · 3 items · $22.50 · MoMo</div>
      <div class="pipeline">
        <div class="pipe-step done"><div class="pipe-circle">✓</div><div class="pipe-label">PLACED</div></div>
        <div class="pipe-step done"><div class="pipe-circle">✓</div><div class="pipe-label">CONFIRMED</div></div>
        <div class="pipe-step done"><div class="pipe-circle">✓</div><div class="pipe-label">PACKED</div></div>
        <div class="pipe-step active"><div class="pipe-circle">→</div><div class="pipe-label">SHIPPED</div></div>
        <div class="pipe-step"><div class="pipe-circle">5</div><div class="pipe-label">DELIVERED</div></div>
      </div>
    </div>
  </div>

  <div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
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
        <input type="text" placeholder="Search orders...">
      </div>
      <select class="select-filter"><option>All Status</option><option>Pending</option><option>Confirmed</option><option>Shipped</option><option>Delivered</option><option>Cancelled</option></select>
      <select class="select-filter"><option>All Payment</option><option>MoMo</option><option>VNPay</option><option>PayPal</option><option>Cash</option></select>
      <input type="date" class="select-filter" style="width:auto;">
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Order ID</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Address</th><th>Date</th><th>Actions</th>
        </tr></thead>
        <tbody id="shopOrdersBody"></tbody>
      </table>
    </div>
    <div id="shopPagination"></div>
  </div>
</div>

<script>
const shopOrders = [
  {id:'#SH-0042',user:'Hoang Minh C',items:3,total:'$22.50',payment:'MoMo',status:'Shipped',addr:'12 Le Loi, Q1, HCM',date:'Mar 12, 2026'},
  {id:'#SH-0041',user:'Nguyen Van A',items:2,total:'$11.70',payment:'VNPay',status:'Delivered',addr:'45 Hai Ba Trung, Q3, HCM',date:'Mar 11, 2026'},
  {id:'#SH-0040',user:'Tran Thi B',items:5,total:'$45.00',payment:'PayPal',status:'Pending',addr:'88 Nguyen Hue, Q1, HCM',date:'Mar 11, 2026'},
  {id:'#SH-0039',user:'Vu Quoc E',items:1,total:'$3.20',payment:'Cash',status:'Delivered',addr:'CineShop Galaxy Counter',date:'Mar 10, 2026'},
  {id:'#SH-0038',user:'Le Admin',items:4,total:'$67.00',payment:'MoMo',status:'Confirmed',addr:'33 Dong Khoi, Q1, HCM',date:'Mar 10, 2026'},
  {id:'#SH-0037',user:'Pham Duc Tuan',items:2,total:'$24.00',payment:'VNPay',status:'Shipped',addr:'22 Pasteur, Q3, HCM',date:'Mar 9, 2026'},
  {id:'#SH-0036',user:'Bui Thi G',items:6,total:'$38.50',payment:'MoMo',status:'Delivered',addr:'100 Cach Mang Thang 8, Q10',date:'Mar 9, 2026'},
  {id:'#SH-0035',user:'Dang Van H',items:1,total:'$8.50',payment:'Cash',status:'Delivered',addr:'CineShop Premier Counter',date:'Mar 8, 2026'},
  {id:'#SH-0034',user:'Tran Minh I',items:3,total:'$51.00',payment:'PayPal',status:'Cancelled',addr:'15 Ly Tu Trong, Q1, HCM',date:'Mar 8, 2026'},
  {id:'#SH-0033',user:'Le Thi J',items:2,total:'$13.00',payment:'VNPay',status:'Delivered',addr:'78 Vo Van Tan, Q3, HCM',date:'Mar 7, 2026'},
];

function viewShopOrder(id) {
  const o = shopOrders.find(x=>x.id===id);
  if(!o) return;
  openModal(`${o.id} — Order Detail`, `
    <div style="display:flex;flex-direction:column;gap:16px;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="field"><label>Order ID</label><input class="input" value="${o.id}" readonly></div>
        <div class="field"><label>Status</label><input class="input" value="${o.status}" readonly></div>
        <div class="field"><label>Customer</label><input class="input" value="${o.user}" readonly></div>
        <div class="field"><label>Payment</label><input class="input" value="${o.payment}" readonly></div>
        <div class="field form-full"><label>Delivery Address</label><input class="input" value="${o.addr}" readonly></div>
        <div class="field"><label>Items Count</label><input class="input" value="${o.items} items" readonly></div>
        <div class="field"><label>Total Amount</label><input class="input" value="${o.total}" readonly></div>
      </div>
      <div style="border-top:1px solid var(--border);padding-top:16px;">
        <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:12px;letter-spacing:0.5px;text-transform:uppercase;">Order Items</div>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <div style="display:flex;align-items:center;gap:12px;padding:10px;background:var(--bg3);border-radius:8px;">
            <span style="font-size:20px;">🍿</span>
            <div style="flex:1;"><div style="font-size:13px;font-weight:600;">Large Popcorn Combo</div><div style="font-size:11px;color:var(--text-muted);">x2 · $8.50 each</div></div>
            <span style="font-weight:700;color:var(--gold);">$17.00</span>
          </div>
          <div style="display:flex;align-items:center;gap:12px;padding:10px;background:var(--bg3);border-radius:8px;">
            <span style="font-size:20px;">🥤</span>
            <div style="flex:1;"><div style="font-size:13px;font-weight:600;">Coca-Cola 500ml</div><div style="font-size:11px;color:var(--text-muted);">x1 · $3.20 each</div></div>
            <span style="font-weight:700;color:var(--gold);">$3.20</span>
          </div>
        </div>
      </div>
      <div style="border-top:1px solid var(--border);padding-top:16px;">
        <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:10px;letter-spacing:0.5px;text-transform:uppercase;">Update Status</div>
        <div style="display:flex;gap:8px;">
          <select class="select" style="flex:1;"><option>Pending</option><option>Confirmed</option><option>Packed</option><option selected>Shipped</option><option>Delivered</option><option>Cancelled</option></select>
          <button class="btn btn-primary" onclick="handleModalSave()">Update</button>
        </div>
      </div>
    </div>`);
}

document.addEventListener('DOMContentLoaded', function(){
  document.getElementById('shopOrdersBody').innerHTML = shopOrders.map(o=>`
    <tr>
      <td class="td-id">${o.id}</td>
      <td class="td-bold">${o.user}</td>
      <td>${o.items} item${o.items>1?'s':''}</td>
      <td style="font-weight:700;color:var(--gold);">${o.total}</td>
      <td><span class="badge gray">${o.payment}</span></td>
      <td>${statusBadge(o.status)}</td>
      <td class="td-muted" style="font-size:12px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${o.addr}</td>
      <td class="td-muted">${o.date}</td>
      <td><div class="actions-row">
        <button class="action-btn view" onclick="viewShopOrder('${o.id}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        <button class="action-btn edit" onclick="viewShopOrder('${o.id}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" onclick="showToast('${o.id} cancelled','error')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`).join('');
  document.getElementById('shopPagination').innerHTML = buildPagination('Showing 1–10 of 847 orders', 85);
});
</script>
