<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div>
      <div class="card-title">Seat Hold Queue</div>
      <div class="card-sub">Active rows from <code>ticket_seat_holds</code> with the current hold expiry window.</div>
    </div>
  </div>
  <div class="card-body" id="ticketQueue"></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="ticketDetailSearch" type="text" placeholder="Search ticket code, order code, or customer..." oninput="filterTicketDetails(this.value)">
    </div>
    <select id="ticketDetailStatus" class="select-filter" onchange="filterTicketDetails()">
      <option value="all">All Status</option>
      <option value="paid">Paid</option>
      <option value="pending">Pending</option>
      <option value="used">Used</option>
      <option value="cancelled">Cancelled</option>
      <option value="refunded">Refunded</option>
      <option value="expired">Expired</option>
    </select>
    <div class="toolbar-right">
      <span id="ticketDetailCount" style="font-size:12px;color:var(--text-dim);">0 tickets</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Ticket</th>
          <th>Order</th>
          <th>Movie</th>
          <th>Showtime</th>
          <th>Seat</th>
          <th>Customer</th>
          <th>Status</th>
          <th>Line Price</th>
          <th>QR Payload</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="ticketDetailsBody"></tbody>
    </table>
  </div>
  <div id="ticketPagination"></div>
</div>

<?php $ticketDetailsPreviewVersion = @filemtime(__DIR__ . '/../../../../../public/assets/admin/ticket-details-preview.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/ticket-details-preview.js?v=<?php echo urlencode((string) $ticketDetailsPreviewVersion); ?>"></script>
