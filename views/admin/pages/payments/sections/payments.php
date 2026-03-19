<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card green" style="padding:16px;">
    <div id="paymentCapturedStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0 VND</div>
    <div class="stat-label">Captured Value</div>
  </div>
  <div class="stat-card blue" style="padding:16px;">
    <div id="paymentTotalStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Payment Records</div>
  </div>
  <div class="stat-card gold" style="padding:16px;">
    <div id="paymentVnpayMonthStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0 VND</div>
    <div class="stat-label">VNPay This Month</div>
  </div>
  <div class="stat-card red" style="padding:16px;">
    <div id="paymentIssueStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Refunded / Failed / Expired</div>
  </div>
</div>

<div class="card" style="margin-bottom:20px;">
  <div class="card-body">
    <div class="card-title">Live Data Scope</div>
    <div class="card-sub" style="margin-top:6px;">
      This admin screen now reads directly from <code>payments</code>, <code>ticket_orders</code>, <code>shop_orders</code>, and <code>payment_methods</code>.
      Search, filters, detail modals, and exports are synchronized with the latest SQL payment records.
    </div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="paymentSearch" type="text" placeholder="Search payment code, gateway ref, order, or customer...">
    </div>
    <select id="paymentMethodFilter" class="select-filter">
      <option value="">All Methods</option>
    </select>
    <select id="paymentStatusFilter" class="select-filter">
      <option value="">All Status</option>
      <option value="pending">Pending</option>
      <option value="processing">Processing</option>
      <option value="success">Success</option>
      <option value="failed">Failed</option>
      <option value="refunded">Refunded</option>
      <option value="cancelled">Cancelled</option>
      <option value="expired">Expired</option>
    </select>
    <select id="paymentScopeFilter" class="select-filter">
      <option value="">All Scope</option>
      <option value="ticket">Ticket</option>
      <option value="shop">Shop</option>
    </select>
    <div class="toolbar-right">
      <span id="paymentCount" style="font-size:12px;color:var(--text-dim);">0 payments</span>
      <button class="btn btn-ghost btn-sm" id="paymentExportBtn" type="button">Export CSV</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Payment Code</th>
          <th>Method</th>
          <th>Gateway Ref</th>
          <th>Amount</th>
          <th>Order Ref</th>
          <th>Scope</th>
          <th>Status</th>
          <th>Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="paymentsBody"></tbody>
    </table>
  </div>
  <div id="paymentsPagination"></div>
</div>
