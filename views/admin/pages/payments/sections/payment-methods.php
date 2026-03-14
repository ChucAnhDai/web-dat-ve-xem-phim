<div class="grid-2" style="margin-bottom:20px;">
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Methods Overview</div><div class="card-sub">Performance by payment channel</div></div>
    </div>
    <div class="card-body" id="paymentMethodOverview"></div>
  </div>
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Settlement Summary</div><div class="card-sub">Current payout and fee rules</div></div>
    </div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:16px;">
        <div><div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:13px;font-weight:600;">MoMo</span><span style="font-size:12px;color:var(--text-dim);">Fee 2.4% / T+1</span></div><div class="progress-bar" style="height:8px;"><div class="progress-fill" style="width:40.2%;background:#AE2070;"></div></div></div>
        <div><div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:13px;font-weight:600;">VNPay</span><span style="font-size:12px;color:var(--text-dim);">Fee 2.1% / T+1</span></div><div class="progress-bar" style="height:8px;"><div class="progress-fill" style="width:36.6%;background:#003DA5;"></div></div></div>
        <div><div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:13px;font-weight:600;">PayPal</span><span style="font-size:12px;color:var(--text-dim);">Fee 3.9% / T+2</span></div><div class="progress-bar" style="height:8px;"><div class="progress-fill" style="width:17.3%;background:#009CDE;"></div></div></div>
        <div><div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:13px;font-weight:600;">Cash</span><span style="font-size:12px;color:var(--text-dim);">No gateway fee / instant</span></div><div class="progress-bar" style="height:8px;"><div class="progress-fill" style="width:5.9%;background:var(--green);"></div></div></div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="methodSearch" type="text" placeholder="Search method..." oninput="filterPaymentMethods(this.value)">
    </div>
    <select id="methodStatusFilter" class="select-filter" onchange="filterPaymentMethods()">
      <option>All Status</option>
      <option>Active</option>
      <option>Maintenance</option>
      <option>Disabled</option>
    </select>
    <div class="toolbar-right">
      <span id="methodCount" style="font-size:12px;color:var(--text-dim);">4 methods</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Method</th><th>Type</th><th>Transactions</th><th>Revenue</th><th>Fee</th><th>Settlement</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody id="paymentMethodsBody"></tbody>
    </table>
  </div>
</div>

<script>
const paymentMethodRecords = [
  {name:'MoMo Wallet',short:'MoMo',type:'E-wallet',transactions:4218,revenue:'$42,180',fee:'2.4%',settlement:'T+1',status:'Active',color:'#AE2070'},
  {name:'VNPay',short:'VNPay',type:'Gateway',transactions:3840,revenue:'$38,400',fee:'2.1%',settlement:'T+1',status:'Active',color:'#003DA5'},
  {name:'PayPal',short:'PP',type:'International',transactions:820,revenue:'$18,200',fee:'3.9%',settlement:'T+2',status:'Maintenance',color:'#009CDE'},
  {name:'Cash',short:'Cash',type:'Counter',transactions:620,revenue:'$6,200',fee:'0%',settlement:'Instant',status:'Active',color:'#22C55E'},
];

function paymentMethodFormBody(method = {}) {
  const type = method.type || 'E-wallet';
  const status = method.status || 'Active';
  const capabilities = method.capabilities || ['Refund support', 'Payout alerts', 'Fraud checks'];

  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Gateway Profile</div>
      <div class="surface-card-copy">Configure fees, payout timing, and support expectations so finance and operations teams preview the same settlement story.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Method Name</label><input class="input" placeholder="MoMo Wallet" value="${method.name || ''}"></div>
      <div class="field"><label>Type</label><select class="select">${buildOptions(['E-wallet', 'Gateway', 'International', 'Counter'], type)}</select></div>
      <div class="field"><label>Status</label><select class="select">${buildOptions(['Active', 'Maintenance', 'Disabled'], status)}</select></div>
      <div class="field"><label>Brand Accent</label><input class="input" placeholder="#AE2070" value="${method.color || ''}"></div>
      <div class="field"><label>Gateway Fee</label><input class="input" placeholder="2.4%" value="${method.fee || ''}"></div>
      <div class="field"><label>Settlement</label><input class="input" placeholder="T+1" value="${method.settlement || ''}"></div>
      <div class="field"><label>Minimum Payout</label><input class="input" placeholder="$50" value="${method.minimum || ''}"></div>
      <div class="field"><label>Support Contact</label><input class="input" placeholder="payments@cineshop.com" value="${method.contact || ''}"></div>
      <div class="field"><label>Fraud Rule</label><select class="select">${buildOptions(['Standard screening', 'Strict screening', 'Manual review'], method.risk || 'Standard screening')}</select></div>
      <div class="field"><label>Refund Window</label><input class="input" placeholder="Up to 24h" value="${method.refunds || ''}"></div>
      <div class="field form-full"><label>Capabilities</label>
        <div class="check-grid">
          ${['Refund support', 'Payout alerts', 'Fraud checks', 'Recurring token', 'QR checkout', 'Chargeback sync'].map(capability => `
            <label class="check-option">
              <input type="checkbox"${capabilities.includes(capability) ? ' checked' : ''}>
              <span>${capability}</span>
            </label>`).join('')}
        </div>
      </div>
      <div class="field form-full"><label>Operations Note</label><textarea class="textarea" placeholder="Settlement or support notes">${method.notes || ''}</textarea></div>
      <div class="field form-full"><label>Preview</label>
        <div class="preview-banner">
          <div class="preview-banner-title">${method.name || 'Payment method preview'}</div>
          <div class="preview-banner-copy">${type} channel with ${method.fee || 'pending fee'} gateway fee and ${method.settlement || 'pending settlement'} payout timing.</div>
          <div class="meta-pills">
            <span class="badge blue">${type}</span>
            <span class="badge ${status === 'Disabled' ? 'red' : status === 'Maintenance' ? 'orange' : 'green'}">${status}</span>
            <span class="badge gold">${method.settlement || 'T+?'}</span>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function openPaymentMethodModal(title, method = {}) {
  const isEdit = /^Edit/i.test(title);
  openModal(title, paymentMethodFormBody(method), {
    description: isEdit
      ? 'Update settlement timing, support rules, and gateway capabilities for this payment method.'
      : 'Create a new payment channel profile with fees, risk rules, and settlement expectations.',
    note: 'UI preview only. Payment method settings are not persisted yet.',
    submitLabel: isEdit ? 'Update Method' : 'Create Method',
    successMessage: isEdit ? 'Payment method preview updated!' : 'Payment method preview staged!',
  });
}

function handlePaymentsSectionAction() {
  openPaymentMethodModal('Add Payment Method');
}

function renderPaymentMethodOverview() {
  document.getElementById('paymentMethodOverview').innerHTML = paymentMethodRecords.map(method => `
    <div class="payment-method" onclick="showToast('Viewing ${method.name}','info')">
      <div class="pm-icon" style="background:${method.color};color:#fff;font-size:10px;font-weight:700;">${method.short}</div>
      <div><div class="pm-name">${method.name}</div><div class="pm-sub">${method.type} / ${method.transactions.toLocaleString()} transactions</div></div>
      <div class="pm-amount" style="color:var(--green);">${method.revenue}</div>
    </div>`).join('');
}

function renderPaymentMethods(data) {
  document.getElementById('paymentMethodsBody').innerHTML = data.map(method => `
    <tr>
      <td><div class="td-bold">${method.name}</div></td>
      <td class="td-muted">${method.type}</td>
      <td style="font-weight:700;">${method.transactions.toLocaleString()}</td>
      <td style="font-weight:700;color:var(--green);">${method.revenue}</td>
      <td class="td-muted">${method.fee}</td>
      <td class="td-muted">${method.settlement}</td>
      <td>${statusBadge(method.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="showToast('Viewing ${method.name}','info')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        <button class="action-btn edit" title="Edit" onclick="openPaymentMethodModal('Edit Payment Method', {name:'${method.name}',type:'${method.type}',fee:'${method.fee}',settlement:'${method.settlement}',status:'${method.status}',color:'${method.color}'})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
      </div></td>
    </tr>`).join('');
}

function filterPaymentMethods(q) {
  const searchInput = document.getElementById('methodSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedStatus = document.getElementById('methodStatusFilter')?.value || 'All Status';
  const filtered = paymentMethodRecords.filter(method => {
    const matchesQuery = searchTerm === '' || method.name.toLowerCase().includes(searchTerm) || method.type.toLowerCase().includes(searchTerm);
    const matchesStatus = selectedStatus === 'All Status' || method.status === selectedStatus;
    return matchesQuery && matchesStatus;
  });

  renderPaymentMethods(filtered);
  document.getElementById('methodCount').textContent = `${filtered.length} methods`;
}

document.addEventListener('DOMContentLoaded', function () {
  renderPaymentMethodOverview();
  filterPaymentMethods();
});
</script>
