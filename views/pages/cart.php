<div class="page-header">
  <h1 class="page-title">SHOPPING CART</h1>
  <p class="page-subtitle" id="cartSubtitle">Loading your cart...</p>
</div>

<div class="catalog-meta">
  <div id="cartSyncNotice">Your cart is kept in sync across product pages and your account session.</div>
  <div class="catalog-request-status" id="cartRequestStatus">Connecting to cart</div>
</div>

<!-- Cart Content (Hidden when empty) -->
<div id="cartContent" class="cart-layout">
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
    <div class="cart-actions-bottom">
      <button class="btn-clear-cart" id="cartClearButton" type="button">
        Clear Cart
      </button>
    </div>
  </div>
  <div class="cart-summary">
    <div class="summary-title">Order Summary</div>
    <div class="promo-input-wrap">
      <input type="text" placeholder="Promo code..." disabled>
      <button class="btn btn-outline btn-sm" type="button" disabled>Apply</button>
    </div>
    <div class="divider"></div>
    <div class="summary-row">
      <label id="summarySubtotalLabel">Subtotal (0 items)</label>
      <span id="summarySubtotal">0 ₫</span>
    </div>
    <div class="summary-row">
      <label>Discount</label>
      <span id="summaryDiscount" style="color:#22c55e">0 ₫</span>
    </div>
    <div class="summary-row">
      <label>Delivery</label>
      <span id="summaryFee">0 ₫</span>
    </div>
    <div class="divider"></div>
    <div class="summary-row total">
      <label>Total</label>
      <span id="summaryTotal" style="color:var(--red)">0 ₫</span>
    </div>

    <button class="btn btn-primary btn-full btn-lg" id="cartCheckoutButton" type="button" style="margin-top:16px">Proceed to Checkout</button>
    <button class="btn btn-ghost btn-full btn-sm" onclick="navigateTo('shop')" type="button" style="margin-top:8px">Continue Shopping</button>
  </div>
</div>

<!-- Empty State Area (Hidden by default) -->
<div id="cartEmptyState" class="empty-state-full" style="display:none">
  <div class="empty-state-container">
    <div class="empty-cart-icon">
      <svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M3 3H5L5.4 5M5.4 5H21L17 13H7M5.4 5L7 13M7 13L4.707 15.293C4.077 15.923 4.523 17 5.414 17H19M17 17C15.8954 17 15 17.8954 15 19C15 20.1046 15.8954 21 17 21C18.1046 21 19 20.1046 19 19C19 17.8954 18.1046 17 17 17ZM9 17C7.89543 17 7 17.8954 7 19C7 20.1046 7.89543 21 9 21C10.1046 21 11 20.1046 11 19C11 17.8954 10.1046 17 9 17Z" stroke="var(--text3)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <h2 class="empty-title" id="cartEmptyTitle">Your cart is empty</h2>
    <p class="empty-desc" id="cartEmptyDescription">Browse our shop to add items.</p>
    <button class="btn btn-primary btn-lg empty-btn" onclick="navigateTo('shop')">Browse Shop</button>
  </div>
</div>
