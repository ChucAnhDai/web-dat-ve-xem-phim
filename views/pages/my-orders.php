<div class="page-header">
  <h1 class="page-title">My Orders</h1>
  <p class="page-subtitle" id="myOrdersSubtitle">Review signed-in orders or look up a guest order manually with the exact checkout contact details.</p>
</div>

<div class="card" style="margin-bottom:20px;">
  <div style="padding:20px;">
    <div style="font-weight:700;font-size:16px;">Order Access & Control</div>
    <div style="margin-top:6px;color:var(--text2);font-size:14px;line-height:1.6;">
      Signed-in members see shop orders tied to their account. Guest browser-session access is disabled for security, so guest orders are only shown after a manual lookup using the order code together with the exact checkout email and phone number.
    </div>
  </div>
</div>

<div class="orders-access-grid">
  <div class="card orders-panel-card">
    <div class="summary-title" style="margin-bottom:8px;">Current Access</div>
    <div class="catalog-request-status" id="myOrdersAccessStatus">Resolving order access...</div>
    <div class="lookup-help" id="myOrdersAccessMeta" style="margin-top:10px;">Checking whether signed-in account orders can be loaded or whether manual guest lookup is required.</div>
    <div class="orders-summary-grid" style="margin-top:18px;">
      <div class="orders-summary-metric">
        <div class="orders-summary-label">Total</div>
        <div class="orders-summary-value" id="myOrdersTotalStat">0</div>
      </div>
      <div class="orders-summary-metric">
        <div class="orders-summary-label">Pending</div>
        <div class="orders-summary-value" id="myOrdersPendingStat">0</div>
      </div>
      <div class="orders-summary-metric">
        <div class="orders-summary-label">Active</div>
        <div class="orders-summary-value" id="myOrdersActiveStat">0</div>
      </div>
      <div class="orders-summary-metric">
        <div class="orders-summary-label">Issue</div>
        <div class="orders-summary-value" id="myOrdersIssueStat">0</div>
      </div>
    </div>
  </div>

  <div class="card orders-panel-card">
    <div class="summary-title" style="margin-bottom:8px;">Guest Lookup</div>
    <div class="lookup-help">
      Guest orders are not auto-loaded. Enter the guest checkout order code together with the same checkout email and checkout phone number used when placing the order.
    </div>
    <div class="catalog-request-status" id="guestOrderLookupStatus" style="margin-top:12px;">Guest lookup is available for orders placed without signing in.</div>
    <form id="guestOrderLookupForm" class="lookup-form-grid" autocomplete="off">
      <div>
        <label for="guestOrderCode" class="td-muted">Order Code</label>
        <input id="guestOrderCode" class="form-control" type="text" placeholder="SHP-ABC12345" maxlength="40">
      </div>
      <div class="lookup-inline-row">
        <div>
          <label for="guestOrderEmail" class="td-muted">Checkout Email</label>
          <input id="guestOrderEmail" class="form-control" type="email" placeholder="guest@example.com" maxlength="160">
        </div>
        <div>
          <label for="guestOrderPhone" class="td-muted">Checkout Phone</label>
          <input id="guestOrderPhone" class="form-control" type="text" placeholder="0901234567" maxlength="20">
        </div>
      </div>
      <div class="order-detail-actions" style="justify-content:flex-start;margin-top:4px;">
        <button class="btn btn-primary" id="guestOrderLookupBtn" type="submit">Lookup Order</button>
        <button class="btn btn-secondary" id="guestOrderLookupResetBtn" type="button">Clear</button>
      </div>
    </form>
  </div>
</div>

<div class="filter-bar" id="myOrdersFilters">
  <div class="filter-chip active" data-filter="all">All Orders</div>
  <div class="filter-chip" data-filter="pending">Pending</div>
  <div class="filter-chip" data-filter="active">Active</div>
  <div class="filter-chip" data-filter="completed">Completed</div>
  <div class="filter-chip" data-filter="issue">Cancelled / Expired</div>
</div>

<div class="orders-table">
  <div class="table-header">
    <span>Order</span>
    <span>Items</span>
    <span>Placed</span>
    <span>Amount</span>
    <span>Status</span>
    <span>Action</span>
  </div>
  <div id="myOrdersBody"></div>
</div>

<div class="modal-overlay" id="shopOrderModal">
  <div class="modal modal-lg">
    <button class="modal-close" id="shopOrderModalClose" type="button">&times;</button>
    <div id="shopOrderModalContent"></div>
  </div>
</div>

<?php $myOrdersScriptVersion = @filemtime(__DIR__ . '/../../public/assets/js/my-orders.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/js/my-orders.js?v=<?php echo urlencode((string) $myOrdersScriptVersion); ?>"></script>
