<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card green" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">1.049.800.000 đ</div>
    <div class="stat-label">Captured Value</div>
  </div>
  <div class="stat-card blue" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">9,498</div>
    <div class="stat-label">Payment Records</div>
  </div>
  <div class="stat-card gold" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">182.400.000 đ</div>
    <div class="stat-label">VNPay This Month</div>
  </div>
  <div class="stat-card red" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">132</div>
    <div class="stat-label">Refunded / Failed / Expired</div>
  </div>
</div>

<div class="card">
  <div class="card-body" style="border-bottom:1px solid var(--border);">
    <div class="card-title">Schema-Aligned Preview</div>
    <div class="card-sub" style="margin-top:6px;">
      This screen is aligned to <code>payments</code> and previews the fields planned for live payment operations:
      <code>payment_method</code>, <code>payment_status</code>, <code>amount</code>, <code>transaction_code</code>,
      <code>provider_transaction_code</code>, and gateway timestamps.
    </div>
  </div>
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="paymentSearch" type="text" placeholder="Search payment code, gateway ref, or order..." oninput="filterPayments(this.value)">
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
      <option>Pending</option>
      <option>Processing</option>
      <option>Success</option>
      <option>Failed</option>
      <option>Refunded</option>
      <option>Cancelled</option>
      <option>Expired</option>
    </select>
    <div class="toolbar-right">
      <span id="paymentCount" style="font-size:12px;color:var(--text-dim);">10 payments shown</span>
      <button class="btn btn-ghost btn-sm" onclick="showToast('Payment report exported','success')">Export</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Payment Code</th><th>Method</th><th>Gateway Ref</th><th>Amount</th><th>Order Ref</th><th>Scope</th><th>Status</th><th>Updated</th>
      </tr></thead>
      <tbody id="paymentsBody"></tbody>
    </table>
  </div>
  <div id="paymentsPagination"></div>
</div>

<script>
const paymentRecords = [
  {code:'PAY-9498',method:'MoMo',gatewayRef:'MM-240312-001A',amount:'140.000 đ',order:'TKT-2031',scope:'Ticket',status:'Success',updatedAt:'2026-03-12 14:00'},
  {code:'PAY-9497',method:'VNPay',gatewayRef:'VNP-240312-001B',amount:'280.000 đ',order:'TKT-2030',scope:'Ticket',status:'Success',updatedAt:'2026-03-12 13:45'},
  {code:'PAY-9496',method:'PayPal',gatewayRef:'PP-240312-001C',amount:'225.000 đ',order:'SHOP-0042',scope:'Shop',status:'Processing',updatedAt:'2026-03-12 12:30'},
  {code:'PAY-9495',method:'Cash',gatewayRef:'',amount:'70.000 đ',order:'TKT-2029',scope:'Ticket',status:'Failed',updatedAt:'2026-03-12 12:05'},
  {code:'PAY-9494',method:'MoMo',gatewayRef:'MM-240311-001E',amount:'117.000 đ',order:'SHOP-0041',scope:'Shop',status:'Success',updatedAt:'2026-03-11 19:18'},
  {code:'PAY-9493',method:'MoMo',gatewayRef:'MM-240311-001F',amount:'160.000 đ',order:'TKT-2026',scope:'Ticket',status:'Refunded',updatedAt:'2026-03-11 16:42'},
  {code:'PAY-9492',method:'VNPay',gatewayRef:'VNP-240311-0020',amount:'350.000 đ',order:'TKT-2025',scope:'Ticket',status:'Success',updatedAt:'2026-03-11 15:22'},
  {code:'PAY-9491',method:'Cash',gatewayRef:'',amount:'85.000 đ',order:'SHOP-0035',scope:'Shop',status:'Success',updatedAt:'2026-03-08 11:12'},
  {code:'PAY-9490',method:'PayPal',gatewayRef:'PP-240311-0022',amount:'450.000 đ',order:'SHOP-0040',scope:'Shop',status:'Cancelled',updatedAt:'2026-03-11 10:20'},
  {code:'PAY-9489',method:'VNPay',gatewayRef:'VNP-240312-0023',amount:'210.000 đ',order:'TKT-2028',scope:'Ticket',status:'Expired',updatedAt:'2026-03-12 11:20'},
];

function handlePaymentsSectionAction() {
  showToast('Payment report exported', 'success');
}

function renderPayments(data) {
  const startItem = data.length === 0 ? 0 : 1;
  document.getElementById('paymentsBody').innerHTML = data.map(payment => `
    <tr>
      <td class="td-id">${payment.code}</td>
      <td><span class="badge gray">${payment.method}</span></td>
      <td class="td-mono">${payment.gatewayRef || 'Pending callback'}</td>
      <td style="font-weight:700;">${payment.amount}</td>
      <td class="td-id">${payment.order}</td>
      <td><span class="badge ${payment.scope === 'Ticket' ? 'red' : 'blue'}">${payment.scope}</span></td>
      <td>${statusBadge(payment.status)}</td>
      <td class="td-muted">${payment.updatedAt}</td>
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
      payment.code.toLowerCase().includes(searchTerm) ||
      payment.order.toLowerCase().includes(searchTerm) ||
      payment.gatewayRef.toLowerCase().includes(searchTerm);
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
