<div style="margin-bottom:16px">
  <a href="<?php echo $publicBase; ?>/cart" class="btn btn-ghost btn-sm">← Back to Cart</a>
</div>

<div class="page-header">
  <h1 class="page-title">Checkout</h1>
  <p class="page-subtitle">Complete your order</p>
</div>

<div class="checkout-layout">
  <div class="checkout-form">
    <div class="form-section-title"><span>1</span> Personal Information</div>
    <div class="form-row">
      <div class="form-group"><label>First Name</label><input class="form-control" type="text" value="John"></div>
      <div class="form-group"><label>Last Name</label><input class="form-control" type="text" value="Doe"></div>
    </div>
    <div class="form-group"><label>Email Address</label><input class="form-control" type="email" value="john.doe@email.com"></div>
    <div class="form-group"><label>Phone Number</label><input class="form-control" type="tel" value="+1 555 123 4567"></div>

    <div class="divider" style="margin:20px 0"></div>
    <div class="form-section-title"><span>2</span> Delivery Method</div>
    <div class="radio-group">
      <div class="radio-option selected" onclick="selectOpt(this,'.radio-option')">
        <div class="radio-icon">🎭</div>
        <div><div class="radio-label">Pickup at Cinema</div><div class="radio-desc">Collect at counter — Free</div></div>
      </div>
      <div class="radio-option" onclick="selectOpt(this,'.radio-option')">
        <div class="radio-icon">🚚</div>
        <div><div class="radio-label">Home Delivery</div><div class="radio-desc">Est. 2–3 hours — $4.99</div></div>
      </div>
    </div>

    <div class="divider" style="margin:20px 0"></div>
    <div class="form-section-title"><span>3</span> Payment Method</div>
    <div class="payment-methods">
      <div class="payment-option selected" onclick="selectOpt(this,'.payment-option')">
        <div class="payment-logo">💳</div>
        <div><div class="payment-label">Credit / Debit Card</div><div class="payment-desc">Visa, Mastercard, Amex</div></div>
      </div>
      <div class="payment-option" onclick="selectOpt(this,'.payment-option')">
        <div class="payment-logo">📱</div>
        <div><div class="payment-label">Digital Wallet</div><div class="payment-desc">Apple Pay, Google Pay, PayPal</div></div>
      </div>
      <div class="payment-option" onclick="selectOpt(this,'.payment-option')">
        <div class="payment-logo">🏦</div>
        <div><div class="payment-label">Bank Transfer</div><div class="payment-desc">Direct bank payment</div></div>
      </div>
      <div class="payment-option" onclick="selectOpt(this,'.payment-option')">
        <div class="payment-logo">💵</div>
        <div><div class="payment-label">Cash at Venue</div><div class="payment-desc">Pay at cinema counter</div></div>
      </div>
    </div>

    <div class="divider" style="margin:20px 0"></div>
    <button class="btn btn-primary btn-full btn-lg" onclick="placeOrder()">🎉 Place Order</button>
  </div>

  <div class="checkout-summary-box">
    <div class="summary-title">Order Summary</div>
    <div id="orderItems"></div>
    <div class="divider"></div>
    <div class="summary-row"><label>Subtotal</label><span id="orderSubtotal">$0.00</span></div>
    <div class="summary-row"><label>Discount</label><span style="color:#22c55e">− $0.00</span></div>
    <div class="summary-row"><label>Delivery</label><span>Free</span></div>
    <div class="divider"></div>
    <div class="summary-row total"><label>Total</label><span id="orderTotal" style="color:var(--red)">$0.00</span></div>
  </div>
</div>

<script>
  function selectOpt(el, selector) {
    el.closest('.checkout-form').querySelectorAll(selector).forEach(o=>o.classList.remove('selected'));
    el.classList.add('selected');
  }

  function renderOrderSummary() {
    // Attempt to use cartItems from app.js if available
    const items = (typeof cartItems !== 'undefined' && cartItems.length) ? cartItems : [
      {id:0,name:'Dune: Part Two Ticket × 2',cat:'ticket',price:18,qty:2,img:'https://image.tmdb.org/t/p/w200/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg'}
    ];
    
    const itemsContainer = document.getElementById('orderItems');
    if (itemsContainer) {
      itemsContainer.innerHTML = items.map(i=>`
        <div class="order-item-row">
          <div class="order-item-img">${i.img?`<img src="${i.img}" alt="" onerror="this.style.display='none'">`:''}</div>
          <div style="flex:1"><div class="order-item-name">${i.name}</div><div class="order-item-qty">×${i.qty}</div></div>
          <div class="order-item-price">$${(i.price*i.qty).toFixed(2)}</div>
        </div>`).join('');
    }
    
    const subtotal = items.reduce((s,i)=>s+i.price*i.qty,0);
    const subtotalEl = document.getElementById('orderSubtotal');
    const totalEl = document.getElementById('orderTotal');
    
    if (subtotalEl) subtotalEl.textContent = `$${subtotal.toFixed(2)}`;
    if (totalEl) totalEl.textContent = `$${subtotal.toFixed(2)}`;
  }

  function placeOrder() {
    if (typeof showToast === 'function') {
      showToast('🎉','Order Placed!','Your order has been confirmed. Thank you!');
    } else {
      alert('Order Placed! Your order has been confirmed. Thank you!');
    }
    
    if (typeof cartItems !== 'undefined') {
      cartItems.length = 0;
      if (typeof updateCartBadges === 'function') updateCartBadges();
    }
    
    setTimeout(()=> {
      location.href = window.PUBLIC_BASE_PATH + '/my-orders';
    }, 1500);
  }

  document.addEventListener('DOMContentLoaded', renderOrderSummary);
</script>
