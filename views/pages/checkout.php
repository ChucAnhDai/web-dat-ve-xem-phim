<?php
$checkoutShowtimeId = trim((string) ($_GET['showtime_id'] ?? ''));
$checkoutSeatIds = trim((string) ($_GET['seat_ids'] ?? ''));
$checkoutSeats = trim((string) ($_GET['seats'] ?? ''));
$checkoutSlug = trim((string) ($_GET['slug'] ?? ''));
$backQuery = array_filter([
    $checkoutShowtimeId !== '' ? 'showtime_id=' . rawurlencode($checkoutShowtimeId) : null,
    $checkoutSeatIds !== '' ? 'seat_ids=' . rawurlencode($checkoutSeatIds) : null,
    $checkoutSeats !== '' ? 'seats=' . rawurlencode($checkoutSeats) : null,
    $checkoutSlug !== '' ? 'slug=' . rawurlencode($checkoutSlug) : null,
]);
$backToSeatSelection = $publicBase . '/seat-selection' . ($backQuery !== [] ? '?' . implode('&', $backQuery) : '');
?>

<div style="margin-bottom:16px">
  <a id="ticketCheckoutBackLink" href="<?php echo htmlspecialchars($backToSeatSelection, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-ghost btn-sm">Back to Seat Selection</a>
</div>

<div id="ticketCheckoutState"></div>

<div id="ticketCheckoutContent" hidden>
  <div class="page-header">
    <h1 class="page-title">Ticket Checkout</h1>
    <p class="page-subtitle">Complete your held seats and create a live ticket order from the current session.</p>
  </div>

  <div class="checkout-layout">
    <div class="checkout-form">
      <div class="form-section-title"><span>1</span> Contact Information</div>
      <div class="form-row">
        <div class="form-group"><label for="ticketCheckoutName">Contact Name</label><input class="form-control" id="ticketCheckoutName" type="text" placeholder="Nguyen Van A"></div>
        <div class="form-group"><label for="ticketCheckoutPhone">Contact Phone</label><input class="form-control" id="ticketCheckoutPhone" type="tel" placeholder="0901234567"></div>
      </div>
      <div class="form-group"><label for="ticketCheckoutEmail">Contact Email</label><input class="form-control" id="ticketCheckoutEmail" type="email" placeholder="ticket@example.com"></div>

      <div class="divider" style="margin:20px 0"></div>
      <div class="form-section-title"><span>2</span> Fulfillment Method</div>
      <div class="radio-group" id="ticketCheckoutFulfillmentGroup">
        <button class="radio-option selected" type="button" data-group="fulfillment" data-value="e_ticket">
          <div class="radio-icon">ET</div>
          <div><div class="radio-label">E-ticket</div><div class="radio-desc">Matches ticket_orders.fulfillment_method = e_ticket</div></div>
        </button>
        <button class="radio-option" type="button" data-group="fulfillment" data-value="counter_pickup">
          <div class="radio-icon">CP</div>
          <div><div class="radio-label">Counter Pickup</div><div class="radio-desc">Matches ticket_orders.fulfillment_method = counter_pickup</div></div>
        </button>
      </div>

      <div class="divider" style="margin:20px 0"></div>
      <div class="form-section-title"><span>3</span> Payment Method</div>
      <div class="payment-methods" id="ticketCheckoutPaymentGroup">
        <button class="payment-option selected" type="button" data-group="payment" data-value="momo">
          <div class="payment-logo">MM</div>
          <div><div class="payment-label">MoMo</div><div class="payment-desc">Maps to <code>payments.payment_method = momo</code> for e-wallet checkout.</div></div>
        </button>
        <button class="payment-option" type="button" data-group="payment" data-value="vnpay">
          <div class="payment-logo">VN</div>
          <div><div class="payment-label">VNPay</div><div class="payment-desc">Maps to <code>payments.payment_method = vnpay</code> for online redirect gateway checkout.</div></div>
        </button>
        <button class="payment-option" type="button" data-group="payment" data-value="paypal">
          <div class="payment-logo">PP</div>
          <div><div class="payment-label">PayPal</div><div class="payment-desc">Maps to <code>payments.payment_method = paypal</code> for international checkout.</div></div>
        </button>
        <button class="payment-option" type="button" data-group="payment" data-value="cash">
          <div class="payment-logo">CA</div>
          <div><div class="payment-label">Cash</div><div class="payment-desc">Maps to <code>payments.payment_method = cash</code> for counter settlement.</div></div>
        </button>
      </div>

      <div class="divider" style="margin:20px 0"></div>
      <div class="card" style="border:1px solid var(--border);">
        <div style="padding:20px;">
          <div style="font-weight:700;font-size:16px;">Checkout Rule</div>
          <div style="margin-top:6px;color:var(--text2);font-size:14px;line-height:1.6;">
            Pricing, seat validation, order creation, ticket issuance, and payment snapshots are now computed on the server.
            If the hold expires or a seat becomes invalid, checkout will stop before any order is written.
            VNPay now creates a short-lived pending ticket order, redirects you to the gateway, and waits for a verified callback before the order is marked as paid.
            Unfinished checkout sessions are released automatically after the payment window closes, and active pending checkouts can be restored if the customer returns before expiry.
          </div>
        </div>
      </div>

      <div class="divider" style="margin:20px 0"></div>
      <button class="btn btn-primary btn-full btn-lg" id="ticketCheckoutSubmitBtn" type="button">Complete Ticket Order</button>
    </div>

    <div class="checkout-summary-box">
      <div class="summary-title">Ticket Order Summary</div>
      <div class="summary-movie" style="margin-bottom:16px;">
        <div class="order-item-img" style="width:70px;height:100px;border-radius:6px;overflow:hidden" id="ticketCheckoutPoster"></div>
        <div class="summary-movie-info">
          <h4 id="ticketCheckoutMovieTitle">Movie Title</h4>
          <p id="ticketCheckoutVenue">Cinema - Room</p>
          <p id="ticketCheckoutDateTime">Date - Time</p>
        </div>
      </div>
      <div class="summary-row"><label>Selected Seats</label></div>
      <div class="seats-display" id="ticketCheckoutSeatList"><span class="seat-tag">None selected</span></div>
      <div class="divider"></div>
      <div class="summary-row"><label>Order Status Preview</label><span id="ticketCheckoutStatus">pending</span></div>
      <div class="summary-row"><label>Hold Expires</label><span id="ticketCheckoutHold">Not started</span></div>
      <div class="divider"></div>
      <div class="summary-row"><label>Subtotal</label><span id="ticketCheckoutSubtotal">0 VND</span></div>
      <div class="summary-row"><label>Seat Surcharge</label><span id="ticketCheckoutSurcharge">0 VND</span></div>
      <div class="summary-row"><label>Fees</label><span id="ticketCheckoutFees">0 VND</span></div>
      <div class="divider"></div>
      <div class="summary-row total"><label>Total</label><span id="ticketCheckoutTotal" style="color:var(--red)">0 VND</span></div>
    </div>
  </div>
</div>

<?php $ticketCheckoutScriptVersion = @filemtime(__DIR__ . '/../../public/assets/js/checkout.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/js/checkout.js?v=<?php echo urlencode((string) $ticketCheckoutScriptVersion); ?>"></script>
