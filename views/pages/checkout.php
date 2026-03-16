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

<div style="margin-bottom:24px">
  <a id="ticketCheckoutBackLink" href="<?php echo htmlspecialchars($backToSeatSelection, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-ghost btn-sm">
    <span style="margin-right:8px">←</span> Back to Seat Selection
  </a>
</div>

<div id="ticketCheckoutState"></div>

<div id="ticketCheckoutContent" hidden>
  <div class="page-header">
    <h1 class="page-title">Checkout</h1>
    <p class="page-subtitle">Securely complete your ticket purchase.</p>
  </div>

  <div class="checkout-layout">
    <div class="checkout-main">
      <!-- Step 1: Contact -->
      <div class="checkout-section-card">
        <div class="checkout-step-header">
          <div class="checkout-step-number">1</div>
          <h3 class="checkout-step-title">Contact Information</h3>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="ticketCheckoutName">Full Name</label>
            <input class="form-control" id="ticketCheckoutName" type="text" placeholder="John Doe">
          </div>
          <div class="form-group">
            <label for="ticketCheckoutPhone">Phone Number</label>
            <input class="form-control" id="ticketCheckoutPhone" type="tel" placeholder="+84 901 234 567">
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label for="ticketCheckoutEmail">Email Address</label>
          <input class="form-control" id="ticketCheckoutEmail" type="email" placeholder="john.doe@example.com">
        </div>
      </div>

      <!-- Step 2: Fulfillment -->
      <div class="checkout-section-card">
        <div class="checkout-step-header">
          <div class="checkout-step-number">2</div>
          <h3 class="checkout-step-title">Fulfillment Method</h3>
        </div>
        <div class="radio-group" id="ticketCheckoutFulfillmentGroup">
          <button class="radio-option selected" type="button" data-group="fulfillment" data-value="e_ticket">
            <div class="radio-option-icon">📱</div>
            <div class="radio-option-info">
              <div class="radio-option-label">E-Ticket</div>
              <div class="radio-option-desc">Receive your ticket via email and app.</div>
            </div>
          </button>
          <button class="radio-option" type="button" data-group="fulfillment" data-value="counter_pickup">
            <div class="radio-option-icon">🏢</div>
            <div class="radio-option-info">
              <div class="radio-option-label">Counter Pickup</div>
              <div class="radio-option-desc">Collect your physical ticket at the cinema.</div>
            </div>
          </button>
        </div>
      </div>

      <!-- Step 3: Payment -->
      <div class="checkout-section-card">
        <div class="checkout-step-header">
          <div class="checkout-step-number">3</div>
          <h3 class="checkout-step-title">Payment Method</h3>
        </div>
        <div class="payment-grid" id="ticketCheckoutPaymentGroup">
          <!-- MoMo -->
          <button class="payment-item selected" type="button" data-group="payment" data-value="momo">
            <div class="payment-brand-logo" style="background:#A50064;color:#fff;font-size:10px">MOMO</div>
            <div class="payment-brand-info">
              <div class="payment-brand-name">MoMo Wallet</div>
              <div class="payment-brand-desc">Fast and secure mobile payment.</div>
            </div>
          </button>
          
          <!-- VNPay -->
          <button class="payment-item" type="button" data-group="payment" data-value="vnpay">
            <div class="payment-brand-logo" style="background:#005aaa;color:#fff;font-size:10px">VNPAY</div>
            <div class="payment-brand-info">
              <div class="payment-brand-name">VNPay Gateway</div>
              <div class="payment-brand-desc">Pay via Banking App or International Card.</div>
            </div>
          </button>

          <!-- PayPal -->
          <button class="payment-item" type="button" data-group="payment" data-value="paypal">
            <div class="payment-brand-logo" style="background:#003087;color:#fff;font-size:10px">PAYPAL</div>
            <div class="payment-brand-info">
              <div class="payment-brand-name">PayPal</div>
              <div class="payment-brand-desc">International checkout and credit cards.</div>
            </div>
          </button>

          <!-- Cash -->
          <button class="payment-item" type="button" data-group="payment" data-value="cash">
            <div class="payment-brand-logo" style="background:#4b5563;color:#fff;font-size:10px">CASH</div>
            <div class="payment-brand-info">
              <div class="payment-brand-name">Cash at Counter</div>
              <div class="payment-brand-desc">Pay in person when you arrive.</div>
            </div>
          </button>
        </div>
      </div>

      <!-- Rules Box -->
      <div class="checkout-rules-box">
        <strong>Terms & Conditions:</strong><br>
        Tickets are held for a limited time. If payment is not completed before the countdown expires, your seats will be released. For online gateway payments like VNPay, please ensure you complete the transaction before returning.
      </div>
    </div>

    <!-- Summary Sidebar -->
    <div class="checkout-summary-container">
      <div class="checkout-summary-card">
        <h3 style="margin-bottom:20px;font-size:18px;font-weight:700">Order Summary</h3>
        
        <div class="summary-movie-card">
          <div id="ticketCheckoutPoster" class="summary-movie-poster-placeholder" style="width:80px;height:110px;border-radius:8px;background:var(--bg3);overflow:hidden"></div>
          <div class="summary-movie-details">
            <h4 id="ticketCheckoutMovieTitle">Loading Movie...</h4>
            <div class="summary-movie-info-text">
              <span>📍</span> <span id="ticketCheckoutVenue">Cinema Location</span>
            </div>
            <div class="summary-movie-info-text">
              <span>📅</span> <span id="ticketCheckoutDateTime">Showtime Date</span>
            </div>
          </div>
        </div>

        <div style="margin-bottom:16px">
          <div style="font-size:12px;color:var(--text3);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em">Selected Seats</div>
          <div id="ticketCheckoutSeatList" class="seats-pill-container" style="display:flex;flex-wrap:wrap;gap:6px">
            <span class="seat-tag-pill">None</span>
          </div>
        </div>

        <div class="summary-cost-list">
          <div class="cost-row">
            <label>Subtotal</label>
            <span id="ticketCheckoutSubtotal">0 VND</span>
          </div>
          <div class="cost-row">
            <label>Surcharges</label>
            <span id="ticketCheckoutSurcharge">0 VND</span>
          </div>
          <div class="cost-row">
            <label>Handling Fee</label>
            <span id="ticketCheckoutFees">0 VND</span>
          </div>
          <div class="cost-row total">
            <label>Total</label>
            <span id="ticketCheckoutTotal">0 VND</span>
          </div>
        </div>

        <div style="margin-bottom:24px;background:rgba(255,255,255,0.03);padding:12px;border-radius:8px;font-size:11px;color:var(--text2);">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px">
            <span>Status:</span>
            <span id="ticketCheckoutStatus" style="font-weight:700;color:var(--saas-accent)">PENDING</span>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span>Hold Expires:</span>
            <span id="ticketCheckoutHold" style="color:var(--red)">--:--</span>
          </div>
        </div>

        <button class="checkout-submit-btn" id="ticketCheckoutSubmitBtn" type="button">
          Complete Purchase
        </button>
      </div>
    </div>
  </div>
</div>

<?php $ticketCheckoutScriptVersion = @filemtime(__DIR__ . '/../../public/assets/js/checkout.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/js/checkout.js?v=<?php echo urlencode((string) $ticketCheckoutScriptVersion); ?>"></script>
