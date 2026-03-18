<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div>
      <div class="card-title">Fulfillment Queue</div>
      <div class="card-sub">Orders waiting for confirmation, packing, pickup, or shipment.</div>
    </div>
  </div>
  <div class="card-body" id="shopOrderQueue"></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="shopOrderDetailSearch" type="text" placeholder="Search order, customer, product, or SKU...">
    </div>
    <select id="shopOrderDetailStatus" class="select-filter">
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
    <select id="shopOrderDetailFulfillment" class="select-filter">
      <option value="all">All Fulfillment</option>
      <option value="pickup">Pickup</option>
      <option value="delivery">Delivery</option>
    </select>
    <div class="toolbar-right">
      <span id="shopOrderDetailCount" style="font-size:12px;color:var(--text-dim);">0 detail rows</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Order</th>
          <th>Customer</th>
          <th>Product</th>
          <th>Qty</th>
          <th>Unit Price</th>
          <th>Line Total</th>
          <th>Fulfillment</th>
          <th>Status</th>
          <th>Payment</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="shopOrderDetailsBody"></tbody>
    </table>
  </div>
  <div id="shopOrderDetailsPagination"></div>
</div>

<?php $shopOrdersAdminVersion = @filemtime(__DIR__ . '/../../../../../public/assets/admin/shop-orders-admin.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/shop-orders-admin.js?v=<?php echo urlencode((string) $shopOrdersAdminVersion); ?>"></script>
