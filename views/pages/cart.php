<div class="page-header">
  <h1 class="page-title">Shopping Cart</h1>
  <p class="page-subtitle">3 items in your cart</p>
</div>

<div class="cart-layout">
  <div>
    <div class="cart-table">
      <div class="cart-header-row">
        <span>Product</span>
        <span>Price</span>
        <span>Quantity</span>
        <span>Subtotal</span>
        <span></span>
      </div>
      <div id="cartItemsList"></div>
    </div>
  </div>
  <div class="cart-summary">
    <div class="summary-title">Order Summary</div>
    <div class="promo-input-wrap">
      <input type="text" placeholder="Promo code...">
      <button class="btn btn-outline btn-sm" type="button">Apply</button>
    </div>
    <div class="divider"></div>
    <div class="summary-row">
      <label>Subtotal (3 items)</label>
      <span>$41.97</span>
    </div>
    <div class="summary-row">
      <label>Discount</label>
      <span style="color:#22c55e">− $5.00</span>
    </div>
    <div class="summary-row">
      <label>Delivery</label>
      <span>Free</span>
    </div>
    <div class="divider"></div>
    <div class="summary-row total">
      <label>Total</label>
      <span style="color:var(--red)">$36.97</span>
    </div>
    <button class="btn btn-primary btn-full btn-lg" type="button" style="margin-top:16px">Checkout →</button>
    <button class="btn btn-ghost btn-full btn-sm" onclick="navigateTo('shop')" style="margin-top:8px">← Continue Shopping</button>
  </div>
</div>
