<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card green" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">$104,980</div>
    <div class="stat-label">Total Revenue</div>
  </div>
  <div class="stat-card blue" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">9,498</div>
    <div class="stat-label">Transactions</div>
  </div>
  <div class="stat-card gold" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">$18,240</div>
    <div class="stat-label">This Month</div>
  </div>
  <div class="stat-card red" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">132</div>
    <div class="stat-label">Refunded / Failed</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="paymentSearch" type="text" placeholder="Search transaction or order..." oninput="filterPayments(this.value)">
    </div>
    <select id="paymentMethodFilter" class="select-filter" onchange="filterPayments()">
      <option>All Methods</option>
      <option>MoMo</option>
      <option>VNPay</option>
      <option>PayPal</option>
      <option>Cash</option>
    </select>
    <select id="paymentStatusFilter" class="select-filter" onchange="filterPayments()">
      <option>All Status</option>
      <option>Success</option>
      <option>Failed</option>
      <option>Refunded</option>
    </select>
    <div class="toolbar-right">
      <span id="paymentCount" style="font-size:12px;color:var(--text-dim);">10 payments shown</span>
      <button class="btn btn-ghost btn-sm" onclick="showToast('Payment report exported','success')">Export</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Payment ID</th><th>Method</th><th>Transaction Code</th><th>Amount</th><th>Order Ref</th><th>Type</th><th>Status</th><th>Date</th>
      </tr></thead>
      <tbody id="paymentsBody"></tbody>
    </table>
  </div>
  <div id="paymentsPagination"></div>
</div>

<script>
const paymentRecords = [
  {id:'#PAY-9498',method:'MoMo',txn:'TXN4A7C91E2001A',amount:'$14.00',order:'#TK-2031',type:'Ticket',status:'Success',date:'2026-03-12 14:00'},
  {id:'#PAY-9497',method:'VNPay',txn:'TXN4A7C91E2001B',amount:'$28.00',order:'#TK-2030',type:'Ticket',status:'Success',date:'2026-03-12 13:45'},
  {id:'#PAY-9496',method:'PayPal',txn:'TXN4A7C91E2001C',amount:'$22.50',order:'#SH-0042',type:'Shop',status:'Success',date:'2026-03-12 12:30'},
  {id:'#PAY-9495',method:'Cash',txn:'TXN4A7C91E2001D',amount:'$7.00',order:'#TK-2029',type:'Ticket',status:'Failed',date:'2026-03-12 12:05'},
  {id:'#PAY-9494',method:'MoMo',txn:'TXN4A7C91E2001E',amount:'$11.70',order:'#SH-0041',type:'Shop',status:'Success',date:'2026-03-11 19:18'},
  {id:'#PAY-9493',method:'MoMo',txn:'TXN4A7C91E2001F',amount:'$16.00',order:'#TK-2026',type:'Ticket',status:'Refunded',date:'2026-03-11 16:42'},
  {id:'#PAY-9492',method:'VNPay',txn:'TXN4A7C91E20020',amount:'$35.00',order:'#TK-2025',type:'Ticket',status:'Success',date:'2026-03-11 15:22'},
  {id:'#PAY-9491',method:'Cash',txn:'TXN4A7C91E20021',amount:'$8.50',order:'#SH-0035',type:'Shop',status:'Success',date:'2026-03-08 11:12'},
  {id:'#PAY-9490',method:'PayPal',txn:'TXN4A7C91E20022',amount:'$45.00',order:'#SH-0040',type:'Shop',status:'Failed',date:'2026-03-11 10:20'},
  {id:'#PAY-9489',method:'MoMo',txn:'TXN4A7C91E20023',amount:'$21.00',order:'#TK-2028',type:'Ticket',status:'Success',date:'2026-03-12 11:20'},
];

function handlePaymentsSectionAction() {
  showToast('Payment report exported', 'success');
}

function renderPayments(data) {
  const startItem = data.length === 0 ? 0 : 1;
  document.getElementById('paymentsBody').innerHTML = data.map(payment => `
    <tr>
      <td class="td-id">${payment.id}</td>
      <td><span class="badge gray">${payment.method}</span></td>
      <td class="td-mono">${payment.txn}</td>
      <td style="font-weight:700;">${payment.amount}</td>
      <td class="td-id">${payment.order}</td>
      <td><span class="badge ${payment.type === 'Ticket' ? 'red' : 'blue'}">${payment.type}</span></td>
      <td>${statusBadge(payment.status)}</td>
      <td class="td-muted">${payment.date}</td>
    </tr>`).join('');
  document.getElementById('paymentsPagination').innerHTML = buildPagination(`Showing ${startItem}-${data.length} of ${data.length} payments`, Math.max(1, Math.ceil(data.length / 10)));
}

function filterPayments(q) {
  const searchInput = document.getElementById('paymentSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedMethod = document.getElementById('paymentMethodFilter')?.value || 'All Methods';
  const selectedStatus = document.getElementById('paymentStatusFilter')?.value || 'All Status';
  const filtered = paymentRecords.filter(payment => {
    const matchesQuery = searchTerm === '' ||
      payment.id.toLowerCase().includes(searchTerm) ||
      payment.order.toLowerCase().includes(searchTerm) ||
      payment.txn.toLowerCase().includes(searchTerm);
    const matchesMethod = selectedMethod === 'All Methods' || payment.method === selectedMethod;
    const matchesStatus = selectedStatus === 'All Status' || payment.status === selectedStatus;
    return matchesQuery && matchesMethod && matchesStatus;
  });

  renderPayments(filtered);
  document.getElementById('paymentCount').textContent = `${filtered.length} payments shown`;
}

document.addEventListener('DOMContentLoaded', function () {
  filterPayments();
});
</script>
