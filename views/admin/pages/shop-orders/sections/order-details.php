<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div><div class="card-title">Fulfillment Queue</div><div class="card-sub">Orders ready for packing or pickup</div></div>
  </div>
  <div class="card-body" id="orderQueue"></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="orderDetailSearch" type="text" placeholder="Search detail rows..." oninput="filterOrderDetails(this.value)">
    </div>
    <select id="orderDetailStatus" class="select-filter" onchange="filterOrderDetails()">
      <option>All Status</option><option>Pending</option><option>Confirmed</option><option>Shipped</option><option>Delivered</option>
    </select>
    <div class="toolbar-right">
      <span id="orderDetailCount" style="font-size:12px;color:var(--text-dim);">6 detail rows</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Order</th><th>Customer</th><th>Item</th><th>Qty</th><th>Unit Price</th><th>Delivery</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody id="orderDetailsBody"></tbody>
    </table>
  </div>
</div>

<script>
const orderDetailsData = [
  {order:'#SH-0042',customer:'Hoang Minh C',item:'Large Popcorn Combo',qty:2,unit:'$8.50',delivery:'Courier',status:'Shipped'},
  {order:'#SH-0042',customer:'Hoang Minh C',item:'Coca-Cola 500ml',qty:1,unit:'$3.20',delivery:'Courier',status:'Shipped'},
  {order:'#SH-0041',customer:'Nguyen Van A',item:'Movie Mug',qty:1,unit:'$22.00',delivery:'Pickup',status:'Delivered'},
  {order:'#SH-0040',customer:'Tran Thi B',item:'CineShop Hoodie',qty:1,unit:'$45.00',delivery:'Courier',status:'Pending'},
  {order:'#SH-0038',customer:'Le Admin',item:'Combo Bundle',qty:4,unit:'$16.75',delivery:'Courier',status:'Confirmed'},
  {order:'#SH-0037',customer:'Pham Duc Tuan',item:'Hot Dog Bundle',qty:2,unit:'$12.00',delivery:'Courier',status:'Shipped'},
];

function handleShopOrderSectionAction() {
  const firstRow = orderDetailsData[0];
  openModal('Order Detail', `<div class="form-grid"><div class="field"><label>Order</label><input class="input" value="${firstRow.order}" readonly></div><div class="field"><label>Status</label><input class="input" value="${firstRow.status}" readonly></div><div class="field form-full"><label>Item</label><input class="input" value="${firstRow.item}" readonly></div></div>`);
}

function renderOrderQueue() {
  const queueItems = [
    {id:'#SH-0040',label:'Awaiting confirmation',status:'Pending'},
    {id:'#SH-0038',label:'Ready to pack',status:'Confirmed'},
    {id:'#SH-0037',label:'Handed to courier',status:'Shipped'},
  ];
  document.getElementById('orderQueue').innerHTML = queueItems.map(item => `
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
      <div><div class="td-bold">${item.id}</div><div class="td-muted">${item.label}</div></div>
      <div>${statusBadge(item.status)}</div>
    </div>`).join('');
}

function renderOrderDetails(data) {
  document.getElementById('orderDetailsBody').innerHTML = data.map(row => `
    <tr>
      <td class="td-id">${row.order}</td>
      <td class="td-bold">${row.customer}</td>
      <td class="td-muted">${row.item}</td>
      <td style="font-weight:700;">${row.qty}</td>
      <td style="font-weight:700;color:var(--gold);">${row.unit}</td>
      <td class="td-muted">${row.delivery}</td>
      <td>${statusBadge(row.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="showToast('Viewing ${row.order}','info')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
      </div></td>
    </tr>`).join('');
}

function filterOrderDetails(q) {
  const searchInput = document.getElementById('orderDetailSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedStatus = document.getElementById('orderDetailStatus')?.value || 'All Status';
  const filtered = orderDetailsData.filter(row => {
    const matchesQuery = searchTerm === '' || row.order.toLowerCase().includes(searchTerm) || row.customer.toLowerCase().includes(searchTerm) || row.item.toLowerCase().includes(searchTerm);
    const matchesStatus = selectedStatus === 'All Status' || row.status === selectedStatus;
    return matchesQuery && matchesStatus;
  });

  renderOrderDetails(filtered);
  document.getElementById('orderDetailCount').textContent = `${filtered.length} detail rows`;
}

document.addEventListener('DOMContentLoaded', function () {
  renderOrderQueue();
  filterOrderDetails();
});
</script>
