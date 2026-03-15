<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div id="ticketOrdersTotalStat" style="font-size:28px;font-family:'Bebas Neue',sans-serif;">0</div><div class="stat-label">Ticket Orders</div></div>
  <div class="stat-card green" style="padding:16px;"><div id="ticketOrdersPaidStat" style="font-size:28px;font-family:'Bebas Neue',sans-serif;">0</div><div class="stat-label">Paid Orders</div></div>
  <div class="stat-card orange" style="padding:16px;"><div id="ticketOrdersPendingStat" style="font-size:28px;font-family:'Bebas Neue',sans-serif;">0</div><div class="stat-label">Pending Holds</div></div>
  <div class="stat-card red" style="padding:16px;"><div id="ticketOrdersRiskStat" style="font-size:28px;font-family:'Bebas Neue',sans-serif;">0</div><div class="stat-label">Cancelled or Refunded</div></div>
</div>

<div class="card" style="margin-bottom:20px;">
  <div class="card-body">
    <div class="card-title">Live Data Scope</div>
    <div class="card-sub" style="margin-top:6px;">
      This admin screen reads directly from <code>ticket_orders</code>, <code>ticket_details</code>, and <code>payments</code>.
      Search, filters, and the detail modal now reflect persisted ticket operations instead of preview fixtures.
    </div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="ticketSearch" type="text" placeholder="Search order code, contact, or movie..." oninput="filterTicketOrders(this.value)">
    </div>
    <select id="ticketStatusFilter" class="select-filter" onchange="filterTicketOrders()">
      <option value="all">All Status</option>
      <option value="paid">Paid</option>
      <option value="pending">Pending</option>
      <option value="cancelled">Cancelled</option>
      <option value="expired">Expired</option>
      <option value="refunded">Refunded</option>
    </select>
    <select id="ticketPaymentFilter" class="select-filter" onchange="filterTicketOrders()">
      <option value="all">All Payment</option>
      <option value="momo">MoMo</option>
      <option value="vnpay">VNPay</option>
      <option value="paypal">PayPal</option>
      <option value="cash">Cash</option>
    </select>
    <div class="toolbar-right">
      <span id="ticketOrderCount" style="font-size:12px;color:var(--text-dim);">0 orders</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Order</th>
          <th>Contact</th>
          <th>Screening</th>
          <th>Seats</th>
          <th>Fulfillment</th>
          <th>Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Hold</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="ticketOrdersBody"></tbody>
    </table>
  </div>
  <div id="ticketPagination"></div>
</div>

<?php $ticketOrdersPreviewVersion = @filemtime(__DIR__ . '/../../../../../public/assets/admin/ticket-orders-preview.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/ticket-orders-preview.js?v=<?php echo urlencode((string) $ticketOrdersPreviewVersion); ?>"></script>
