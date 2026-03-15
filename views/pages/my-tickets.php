<div class="page-header">
  <h1 class="page-title">My Tickets</h1>
  <p class="page-subtitle">View your live ticket history, ticket codes, seat assignments, and QR payloads.</p>
</div>

<div class="card" style="margin-bottom:20px;">
  <div style="padding:20px;">
    <div style="font-weight:700;font-size:16px;">Ticket History</div>
    <div style="margin-top:6px;color:var(--text2);font-size:14px;line-height:1.6;">
      This screen reads directly from <code>ticket_orders</code> and <code>ticket_details</code>. Sign in with the same account used during checkout to see your purchased tickets here.
    </div>
  </div>
</div>

<div class="filter-bar">
  <div class="filter-chip active" data-filter="all">All</div>
  <div class="filter-chip" data-filter="paid">Paid</div>
  <div class="filter-chip" data-filter="pending">Pending</div>
  <div class="filter-chip" data-filter="used">Used</div>
  <div class="filter-chip" data-filter="issue">Cancelled / Refunded</div>
</div>

<div class="tickets-grid" id="ticketsGrid"></div>

<?php $myTicketsScriptVersion = @filemtime(__DIR__ . '/../../public/assets/js/my-tickets.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/js/my-tickets.js?v=<?php echo urlencode((string) $myTicketsScriptVersion); ?>"></script>
