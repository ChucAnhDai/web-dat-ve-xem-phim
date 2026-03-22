<div id="shopCheckoutState">
  <div class="detail-state-card">
    <div>
      <strong>Loading checkout...</strong>
      <div style="margin-top:8px;">Preparing your combined checkout and validating the current cart snapshot.</div>
    </div>
  </div>
</div>

<div id="shopCheckoutContent" hidden>
  <div class="page-header">
    <h1 class="page-title">CHECKOUT</h1>
    <p class="page-subtitle" id="shopCheckoutSubtitle">Loading your checkout...</p>
  </div>

  <div class="catalog-meta">
    <div id="shopCheckoutMeta">We verify products, tickets, totals, and payment availability on the server before creating any order.</div>
    <div class="catalog-request-status" id="shopCheckoutRequestStatus">Connecting to checkout</div>
  </div>

  <div class="checkout-layout">
    <div class="checkout-form">
      <div class="checkout-section-card">
        <div class="checkout-step-header">
          <div class="checkout-step-number">1</div>
          <h3 class="checkout-step-title">Contact Information</h3>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="shopCheckoutName">Full Name</label>
            <input class="form-control" id="shopCheckoutName" type="text" placeholder="Nguyen Van A">
          </div>
          <div class="form-group">
            <label for="shopCheckoutPhone">Phone Number</label>
            <input class="form-control" id="shopCheckoutPhone" type="tel" placeholder="0901 234 567">
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label for="shopCheckoutEmail">Email Address</label>
          <input class="form-control" id="shopCheckoutEmail" type="email" placeholder="you@example.com">
        </div>
      </div>

      <div class="checkout-section-card">
        <div class="checkout-step-header">
          <div class="checkout-step-number">2</div>
          <h3 class="checkout-step-title">Delivery & Fulfillment</h3>
        </div>
        <div class="radio-group" id="shopCheckoutFulfillmentGroup"></div>

        <div id="shopCheckoutDeliveryFields" hidden style="margin-top:20px">
          <div class="form-group">
            <label for="shopCheckoutAddress">Delivery Address</label>
            <textarea class="form-control" id="shopCheckoutAddress" rows="3" placeholder="Street, ward, building, notes..."></textarea>
          </div>
          <div class="form-row" style="margin-bottom:0">
            <div class="form-group">
              <label for="shopCheckoutCity">City</label>
              <input class="form-control" id="shopCheckoutCity" type="text" placeholder="Ho Chi Minh City">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label for="shopCheckoutDistrict">District</label>
              <input class="form-control" id="shopCheckoutDistrict" type="text" placeholder="District 1">
            </div>
          </div>
        </div>
      </div>

      <div class="checkout-section-card">
        <div class="checkout-step-header">
          <div class="checkout-step-number">3</div>
          <h3 class="checkout-step-title">Payment Method</h3>
        </div>
        <div class="payment-methods" id="shopCheckoutPaymentGroup"></div>
      </div>

      <div class="checkout-rules-box" id="shopCheckoutRuleBox">
        Order totals are recalculated on the server, inventory and seat reservations are protected inside the checkout transaction, and pending payments expire automatically if they are not completed in time.
      </div>
    </div>

    <div class="checkout-summary-box">
      <div class="summary-title">Order Summary</div>
      <div id="shopCheckoutItems"></div>
      <div class="divider"></div>
      <div class="summary-row">
        <label id="shopCheckoutSubtotalLabel">Subtotal</label>
        <span id="shopCheckoutSubtotal">0 ₫</span>
      </div>
      <div class="summary-row">
        <label id="shopCheckoutShippingLabel">Shipping</label>
        <span id="shopCheckoutShipping">0 ₫</span>
      </div>
      <div class="summary-row total">
        <label>Total</label>
        <span id="shopCheckoutTotal" style="color:var(--red)">0 ₫</span>
      </div>
      <div class="checkout-rules-box" id="shopCheckoutSummaryNote" style="margin-top:16px">
        Final payment instructions will match the selected fulfillment and payment method.
      </div>
      <button class="checkout-submit-btn" id="shopCheckoutSubmitBtn" type="button" style="margin-top:16px">Place Order</button>
      <button class="btn btn-ghost btn-full btn-sm" onclick="navigateTo('cart')" type="button" style="margin-top:10px">Back to Cart</button>
    </div>
  </div>
</div>

<?php $shopCheckoutScriptVersion = @filemtime(__DIR__ . '/../../public/assets/js/shop-checkout.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/js/shop-checkout.js?v=<?php echo urlencode((string) $shopCheckoutScriptVersion); ?>"></script>
