<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div id="shopOrdersTotalStat" style="font-size:28px;font-family:'Bebas Neue',sans-serif;">0</div><div class="stat-label">Shop Orders</div></div>
  <div class="stat-card orange" style="padding:16px;"><div id="shopOrdersPendingStat" style="font-size:28px;font-family:'Bebas Neue',sans-serif;">0</div><div class="stat-label">Pending</div></div>
  <div class="stat-card blue" style="padding:16px;"><div id="shopOrdersActiveStat" style="font-size:28px;font-family:'Bebas Neue',sans-serif;">0</div><div class="stat-label">In Fulfillment</div></div>
  <div class="stat-card green" style="padding:16px;"><div id="shopOrdersCompletedStat" style="font-size:28px;font-family:'Bebas Neue',sans-serif;">0</div><div class="stat-label">Completed</div></div>
  <div class="stat-card red" style="padding:16px;"><div id="shopOrdersIssueStat" style="font-size:28px;font-family:'Bebas Neue',sans-serif;">0</div><div class="stat-label">Cancelled / Issue</div></div>
</div>

<div class="card" style="margin-bottom:20px;">
  <div class="card-body">
    <div class="card-title">Live Data Scope</div>
    <div class="card-sub" style="margin-top:6px;">
      This admin screen now reads directly from <code>shop_orders</code>, <code>order_details</code>, and <code>payments</code>.
      Search, filters, detail modals, and status transitions are synced with the live checkout flow.
    </div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="shopOrderSearch" type="text" placeholder="Search order code, contact, or account...">
    </div>
    <select id="shopOrderStatus" class="select-filter">
      <option value="all">All Status</option>
      <option value="pending">Pending</option>
      <option value="confirmed">Confirmed</option>
      <option value="preparing">Preparing</option>
      <option value="ready">Ready</option>
      <option value="shipping">Shipping</option>
      <option value="completed">Completed</option>
      <option value="cancelled">Cancelled</option>
      <option value="expired">Expired</option>
      <option value="refunded">Refunded</option>
    </select>
    <select id="shopOrderPayment" class="select-filter">
      <option value="all">All Payment</option>
      <option value="cash">Cash</option>
      <option value="vnpay">VNPay</option>
    </select>
    <select id="shopOrderFulfillment" class="select-filter">
      <option value="all">All Fulfillment</option>
      <option value="pickup">Pickup</option>
      <option value="delivery">Delivery</option>
    </select>
    <div class="toolbar-right">
      <span id="shopOrderCount" style="font-size:12px;color:var(--text-dim);">0 orders</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Order</th>
          <th>Customer</th>
          <th>Items</th>
          <th>Total</th>
          <th>Payment</th>
          <th>Fulfillment</th>
          <th>Status</th>
          <th>Shipping</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="shopOrdersBody"></tbody>
    </table>
  </div>
  <div id="shopOrdersPagination"></div>
</div>

<?php $shopOrdersAdminVersion = @filemtime(__DIR__ . '/../../../../../public/assets/admin/shop-orders-admin.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/shop-orders-admin.js?v=<?php echo urlencode((string) $shopOrdersAdminVersion); ?>"></script>
