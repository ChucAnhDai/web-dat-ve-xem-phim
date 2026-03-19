<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;">
    <div id="paymentMethodTotalStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Configured Methods</div>
  </div>
  <div class="stat-card green" style="padding:16px;">
    <div id="paymentMethodActiveStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Active</div>
  </div>
  <div class="stat-card orange" style="padding:16px;">
    <div id="paymentMethodMaintenanceStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Maintenance</div>
  </div>
  <div class="stat-card red" style="padding:16px;">
    <div id="paymentMethodDisabledStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Disabled</div>
  </div>
</div>

<div class="grid-2" style="margin-bottom:20px;">
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Methods Overview</div>
        <div class="card-sub">Live method performance synced from <code>payment_methods</code> and <code>payments</code></div>
      </div>
    </div>
    <div class="card-body" id="paymentMethodOverview"></div>
  </div>
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Settlement Summary</div>
        <div class="card-sub">Revenue mix and payout posture across configured payment channels</div>
      </div>
    </div>
    <div class="card-body" id="paymentMethodSettlementSummary"></div>
  </div>
</div>

<div class="card">
  <div class="card-body" style="border-bottom:1px solid var(--border);">
    <div class="card-title">Live Payment Method Catalog</div>
    <div class="card-sub" style="margin-top:6px;">
      Manage payment channel metadata, fees, redirect capability, settlement cycles, and operational status directly from SQL-backed admin APIs.
    </div>
  </div>
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="methodSearch" type="text" placeholder="Search code, method, provider...">
    </div>
    <select id="methodStatusFilter" class="select-filter">
      <option value="">All Status</option>
      <option value="active">Active</option>
      <option value="maintenance">Maintenance</option>
      <option value="disabled">Disabled</option>
    </select>
    <select id="methodTypeFilter" class="select-filter">
      <option value="">All Types</option>
      <option value="e_wallet">E-wallet</option>
      <option value="gateway">Gateway</option>
      <option value="international">International</option>
      <option value="counter">Counter</option>
    </select>
    <div class="toolbar-right">
      <span id="methodCount" style="font-size:12px;color:var(--text-dim);">0 methods</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Code</th>
          <th>Method</th>
          <th>Type</th>
          <th>Transactions</th>
          <th>Revenue</th>
          <th>Fee</th>
          <th>Settlement</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="paymentMethodsBody"></tbody>
    </table>
  </div>
  <div id="paymentMethodsPagination"></div>
</div>
