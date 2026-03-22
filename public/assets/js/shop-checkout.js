(function unifiedCheckoutModule() {
  const state = {
    payload: null,
    selectedFulfillment: 'pickup',
    selectedPayment: '',
    isSubmitting: false
  };

  const dom = {};

  function init() {
    dom.state = document.getElementById('shopCheckoutState');
    dom.content = document.getElementById('shopCheckoutContent');
    if (!dom.state || !dom.content) {
      return;
    }

    cacheDom();
    bindEvents();
    renderStateCard(
      'Loading checkout...',
      'Preparing one checkout flow for your products, held tickets, and any unfinished payment.'
    );
    void loadCheckout();
  }

  function cacheDom() {
    dom.subtitle = document.getElementById('shopCheckoutSubtitle');
    dom.meta = document.getElementById('shopCheckoutMeta');
    dom.status = document.getElementById('shopCheckoutRequestStatus');
    dom.name = document.getElementById('shopCheckoutName');
    dom.email = document.getElementById('shopCheckoutEmail');
    dom.phone = document.getElementById('shopCheckoutPhone');
    dom.fulfillmentGroup = document.getElementById('shopCheckoutFulfillmentGroup');
    dom.fulfillmentCard = dom.fulfillmentGroup?.closest('.checkout-section-card') || null;
    dom.deliveryFields = document.getElementById('shopCheckoutDeliveryFields');
    dom.address = document.getElementById('shopCheckoutAddress');
    dom.city = document.getElementById('shopCheckoutCity');
    dom.district = document.getElementById('shopCheckoutDistrict');
    dom.paymentGroup = document.getElementById('shopCheckoutPaymentGroup');
    dom.items = document.getElementById('shopCheckoutItems');
    dom.subtotalLabel = document.getElementById('shopCheckoutSubtotalLabel');
    dom.subtotal = document.getElementById('shopCheckoutSubtotal');
    dom.shippingLabel = document.getElementById('shopCheckoutShippingLabel');
    dom.shipping = document.getElementById('shopCheckoutShipping');
    dom.total = document.getElementById('shopCheckoutTotal');
    dom.ruleBox = document.getElementById('shopCheckoutRuleBox');
    dom.summaryNote = document.getElementById('shopCheckoutSummaryNote');
    dom.submit = document.getElementById('shopCheckoutSubmitBtn');
  }

  function bindEvents() {
    dom.submit?.addEventListener('click', () => {
      void submitCheckout();
    });
  }

  async function loadCheckout() {
    setStatus('Loading checkout...', false);

    try {
      state.payload = await fetchCheckout(checkoutApiUrl(), { method: 'GET' });

      if (state.payload?.active_order?.resume_available) {
        renderActiveOrderState(state.payload.active_order);
        return;
      }

      if (!state.payload?.checkout_ready || state.payload?.summary?.is_empty) {
        renderEmptyState();
        return;
      }

      applyDefaults();
      renderCheckout();
      await hydrateProfileDefaults();

      const syncNotice = checkoutSyncMessage(state.payload?.sync);
      if (syncNotice) {
        setStatus(syncNotice, false);
        if (typeof showToast === 'function') {
          showToast('i', 'Checkout synced', syncNotice);
        }
      }
    } catch (error) {
      renderStateCard('Checkout unavailable.', error.message || 'Unable to load checkout right now.');
      setStatus(error.message || 'Checkout request failed.', true);
    }
  }

  function applyDefaults() {
    const defaults = state.payload?.defaults || {};
    state.selectedFulfillment = String(defaults.fulfillment_method || defaultFulfillment());
    state.selectedPayment = String(defaults.payment_method || '');
    ensureAllowedPaymentSelected();
  }

  function renderCheckout() {
    dom.state.innerHTML = '';
    dom.content.hidden = false;

    if (dom.subtitle) {
      dom.subtitle.textContent = checkoutSubtitle();
    }
    if (dom.meta) {
      dom.meta.textContent = checkoutMeta();
    }
    if (dom.ruleBox) {
      dom.ruleBox.textContent = checkoutRuleCopy();
    }

    renderFulfillmentOptions();
    renderPaymentOptions();
    renderDeliveryFields();
    renderSummary();
    setStatus('Checkout is ready.', false);
    setSubmitState(false);
  }

  function renderEmptyState() {
    dom.content.hidden = true;
    renderStateCard(
      'Your cart is empty.',
      'Add shop products or reserve movie seats before starting checkout.'
    );
    setStatus('Cart is empty.', false);
  }

  function renderStateCard(title, message, actionsHtml = '') {
    dom.content.hidden = true;
    dom.state.innerHTML = `
      <div class="detail-state-card">
        <div>
          <strong>${escapeHtml(title)}</strong>
          <div style="margin-top:8px;">${escapeHtml(message)}</div>
          ${actionsHtml ? `<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:18px;">${actionsHtml}</div>` : ''}
        </div>
      </div>
    `;
  }

  function renderFulfillmentOptions() {
    const options = Array.isArray(state.payload?.fulfillment_methods) ? state.payload.fulfillment_methods : [];
    if (!dom.fulfillmentGroup) {
      return;
    }

    if (!hasProducts() && dom.fulfillmentCard) {
      dom.fulfillmentCard.style.display = '';
    }

    dom.fulfillmentGroup.innerHTML = options.map(option => {
      const isSelected = option.code === state.selectedFulfillment;
      const isSingleTicketOption = !hasProducts() && option.code === 'e_ticket';

      return `
        <button
          class="radio-option ${isSelected ? 'selected' : ''}"
          type="button"
          data-fulfillment="${escapeHtml(option.code)}"
          ${isSingleTicketOption ? 'disabled' : ''}
        >
          <div class="radio-info">
            <div class="radio-label">${escapeHtml(option.label)}</div>
            <div class="radio-desc">${escapeHtml(option.description || '')}</div>
          </div>
        </button>
      `;
    }).join('');

    dom.fulfillmentGroup.querySelectorAll('[data-fulfillment]').forEach(button => {
      button.addEventListener('click', () => {
        if (button.disabled) {
          return;
        }

        state.selectedFulfillment = String(button.dataset.fulfillment || defaultFulfillment());
        ensureAllowedPaymentSelected();
        renderFulfillmentOptions();
        renderPaymentOptions();
        renderDeliveryFields();
        renderSummary();
      });
    });
  }

  function renderPaymentOptions() {
    const methods = Array.isArray(state.payload?.payment_methods) ? state.payload.payment_methods : [];
    if (!dom.paymentGroup) {
      return;
    }

    dom.paymentGroup.innerHTML = methods.map(method => {
      const allowed = isPaymentAllowedForFulfillment(method, state.selectedFulfillment);

      return `
        <button
          class="payment-option ${method.code === state.selectedPayment ? 'selected' : ''}"
          type="button"
          data-payment="${escapeHtml(method.code)}"
          ${allowed ? '' : 'disabled'}
        >
          <div class="payment-logo">${escapeHtml(paymentLogo(method.code))}</div>
          <div>
            <div class="payment-label">${escapeHtml(method.name || method.code)}</div>
            <div class="payment-desc">${escapeHtml(method.description || '')}</div>
          </div>
        </button>
      `;
    }).join('');

    dom.paymentGroup.querySelectorAll('[data-payment]').forEach(button => {
      button.addEventListener('click', () => {
        if (button.disabled) {
          return;
        }

        state.selectedPayment = String(button.dataset.payment || '');
        renderPaymentOptions();
        renderSummaryNote();
        setSubmitState(false);
      });
    });
  }

  function renderDeliveryFields() {
    const isDelivery = hasProducts() && state.selectedFulfillment === 'delivery';
    if (dom.deliveryFields) {
      dom.deliveryFields.hidden = !isDelivery;
    }
  }

  function renderSummary() {
    const summary = normalizedSummary();
    if (dom.items) {
      dom.items.innerHTML = `${renderTicketSelectionLine()}${renderProductLines()}` || `
        <div class="lookup-help">No checkout lines are ready yet.</div>
      `;
    }

    if (dom.subtotalLabel) {
      dom.subtotalLabel.textContent = `Subtotal (${itemLabel(summary.item_count)})`;
    }
    if (dom.subtotal) {
      dom.subtotal.textContent = formatAmount(summary.subtotal_price, summary.currency);
    }
    if (dom.shippingLabel) {
      dom.shippingLabel.textContent = shippingLabel();
    }
    if (dom.shipping) {
      dom.shipping.textContent = formatAmount(currentShippingAmount(), summary.currency);
    }
    if (dom.total) {
      dom.total.textContent = formatAmount(summary.total_price + currentShippingAmount(), summary.currency);
    }

    renderSummaryNote();
  }

  function renderSummaryNote() {
    if (!dom.summaryNote) {
      return;
    }

    if (isMixedCart()) {
      dom.summaryNote.textContent = 'Tickets and shop products will be committed inside one checkout. Mixed orders use VNPay so seat reservations and inventory stay consistent.';
      return;
    }

    if (hasTickets() && !hasProducts()) {
      dom.summaryNote.textContent = state.selectedPayment === 'vnpay'
        ? 'VNPay ticket checkout creates one pending order and issues your e-tickets after payment is confirmed.'
        : 'Cash ticket checkout confirms the order immediately and sends the tickets as e-tickets.';
      return;
    }

    if (state.selectedPayment === 'vnpay') {
      dom.summaryNote.textContent = 'VNPay creates one pending order, reserves stock, and redirects you to the secure payment gateway. Unpaid checkouts expire automatically.';
      return;
    }

    dom.summaryNote.textContent = state.selectedFulfillment === 'delivery'
      ? 'Delivery orders keep their pending state until payment and fulfillment are confirmed.'
      : 'Counter pickup orders remain pending until payment is confirmed or the counter completes the handoff.';
  }

  async function hydrateProfileDefaults() {
    const token = resolveAuthToken();
    if (!token) {
      return;
    }

    try {
      const profile = await fetchCheckout(appUrl('/api/auth/profile'), { method: 'GET' });
      const user = profile?.data || profile || {};
      if (dom.name && !String(dom.name.value || '').trim()) dom.name.value = String(user.name || '').trim();
      if (dom.email && !String(dom.email.value || '').trim()) dom.email.value = String(user.email || '').trim();
      if (dom.phone && !String(dom.phone.value || '').trim()) dom.phone.value = String(user.phone || '').trim();
    } catch (error) {
      // Prefill is optional.
    }
  }

  async function submitCheckout() {
    if (state.isSubmitting) {
      return;
    }

    const body = {
      contact_name: String(dom.name?.value || '').trim(),
      contact_email: String(dom.email?.value || '').trim(),
      contact_phone: String(dom.phone?.value || '').trim(),
      fulfillment_method: state.selectedFulfillment,
      payment_method: state.selectedPayment,
      shipping_address_text: String(dom.address?.value || '').trim(),
      shipping_city: String(dom.city?.value || '').trim(),
      shipping_district: String(dom.district?.value || '').trim()
    };

    if (!body.contact_name) return checkoutError('Contact name is required.');
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(body.contact_email)) return checkoutError('A valid contact email is required.');
    if (!/^[0-9+\s().-]{9,20}$/.test(body.contact_phone)) return checkoutError('A valid contact phone is required.');
    if (hasProducts() && body.fulfillment_method === 'delivery' && (!body.shipping_address_text || !body.shipping_city || !body.shipping_district)) {
      return checkoutError('Delivery orders require address, city, and district.');
    }

    setSubmitState(true);

    try {
      const idempotencyKey = createIdempotencyKey();
      const result = await fetchCheckout(checkoutApiUrl(), {
        method: 'POST',
        headers: { 'X-Idempotency-Key': idempotencyKey },
        body
      });
      const order = result?.order || {};
      const guestOrder = isGuestCheckoutOrder(order);
      const primaryHref = guestOrder ? guestLookupUrl() : appUrl('/my-orders');
      const primaryLabel = guestOrder ? 'Open Order Lookup' : 'Open My Orders';
      const guestLookupCopy = guestOrder
        ? ` Save order ${order.order_code || ''} and use the same checkout email and phone on the lookup page whenever you need to review it later.`
        : '';
      const orderScopeLabel = orderScopeLabel(order.order_scope || checkoutScope());

      if (window.shopCartRuntime?.refresh) {
        void window.shopCartRuntime.refresh();
      }

      if (result.redirect_url) {
        renderStateCard(
          'Redirecting to VNPay...',
          `${orderScopeLabel} order ${order.order_code || ''} was created successfully and is now being handed off to the payment gateway.${guestLookupCopy}`,
          `<a class="btn btn-primary btn-sm" href="${escapeHtml(result.redirect_url)}">Open VNPay Checkout</a>`
        );
        window.location.href = result.redirect_url;
        return;
      }

      renderStateCard(
        order.payment_status === 'success' ? 'Order completed successfully.' : 'Order created successfully.',
        finalSuccessCopy(order, guestLookupCopy),
        `<a class="btn btn-primary btn-sm" href="${primaryHref}">${primaryLabel}</a><a class="btn btn-ghost btn-sm" href="${escapeHtml(continueShoppingHref())}">Continue Shopping</a>`
      );
      if (typeof showToast === 'function') {
        showToast('+', 'Order created', `${orderScopeLabel} checkout completed successfully.`);
      }
    } catch (error) {
      checkoutError(error.message || 'Unable to create the order.');
      if (error?.payload?.errors?.cart_sync) {
        await loadCheckout();
      }
    } finally {
      setSubmitState(false);
    }
  }

  function renderActiveOrderState(activeOrder) {
    const order = activeOrder?.order || {};
    const orderCode = order.order_code || 'N/A';
    const guestOrder = isGuestCheckoutOrder(order);
    const actionPrimary = activeOrder?.redirect_url
      ? `<a class="btn btn-primary btn-sm" href="${escapeHtml(activeOrder.redirect_url)}">Continue to VNPay</a>`
      : `<a class="btn btn-primary btn-sm" href="${guestOrder ? guestLookupUrl() : appUrl('/my-orders')}">${guestOrder ? 'Open Order Lookup' : 'Open My Orders'}</a>`;
    const restoreCopy = guestOrder
      ? `A ${orderScopeLabel(order.order_scope)} guest checkout is already waiting for payment confirmation. Use the same checkout email and phone on the lookup page if you need to review it later.`
      : `A ${orderScopeLabel(order.order_scope)} checkout is already waiting for payment confirmation. Resume that flow instead of creating a duplicate order.`;

    renderStateCard(
      `Pending order ${orderCode} restored.`,
      restoreCopy,
      `${actionPrimary}<a class="btn btn-ghost btn-sm" href="${appUrl('/cart')}">Back to Cart</a>`
    );
    setStatus('Pending checkout restored.', false);
  }

  function renderTicketSelectionLine() {
    const selection = state.payload?.ticket_selection || {};
    if (!selection || selection.is_empty) {
      return '';
    }

    const showtime = selection.showtime || {};
    const seatList = Array.isArray(selection.seats)
      ? selection.seats.map(seat => seat.label).filter(Boolean).join(', ')
      : '';
    const schedule = [showtime.cinema_name, showtime.room_name].filter(Boolean).join(' - ');
    const poster = showtime.poster_url
      ? `<img src="${escapeHtml(showtime.poster_url)}" alt="${escapeHtml(showtime.movie_title || 'Movie poster')}" loading="lazy">`
      : '';

    return `
      <div class="order-item-row">
        <div class="order-item-img">${poster}</div>
        <div>
          <div class="order-item-name">${escapeHtml(showtime.movie_title || 'Movie tickets')}</div>
          <div class="order-item-qty">${escapeHtml(schedule || 'Cinema screening')}</div>
          <div class="order-item-qty">${escapeHtml(seatList || `${Number(selection.seat_count || 0)} seat(s)`)}</div>
        </div>
        <div class="order-item-price">${formatAmount(selection.total_price || 0, selection.currency || normalizedSummary().currency)}</div>
      </div>
    `;
  }

  function renderProductLines() {
    const cart = state.payload?.cart || {};
    const items = Array.isArray(cart.items) ? cart.items : [];

    return items.map(item => `
      <div class="order-item-row">
        <div class="order-item-img">
          ${item.primary_image_url ? `<img src="${escapeHtml(item.primary_image_url)}" alt="${escapeHtml(item.primary_image_alt || item.name || 'Product')}" loading="lazy">` : ''}
        </div>
        <div>
          <div class="order-item-name">${escapeHtml(item.name || 'Product')}</div>
          <div class="order-item-qty">Qty ${Number(item.quantity || 0)}</div>
        </div>
        <div class="order-item-price">${formatAmount(item.line_total || 0, item.currency || cart.currency || normalizedSummary().currency)}</div>
      </div>
    `).join('');
  }

  function ensureAllowedPaymentSelected() {
    const methods = Array.isArray(state.payload?.payment_methods) ? state.payload.payment_methods : [];
    const allowedMethods = methods.filter(method => isPaymentAllowedForFulfillment(method, state.selectedFulfillment));

    if (allowedMethods.some(method => method.code === state.selectedPayment)) {
      return;
    }

    state.selectedPayment = String(allowedMethods[0]?.code || methods[0]?.code || '');
  }

  function isPaymentAllowedForFulfillment(method, fulfillment) {
    const allowedMethods = Array.isArray(method?.allowed_fulfillment_methods)
      ? method.allowed_fulfillment_methods
      : [];

    return allowedMethods.length === 0 || allowedMethods.includes(fulfillment);
  }

  function hasProducts() {
    return Boolean(state.payload?.requirements?.contains_products);
  }

  function hasTickets() {
    return Boolean(state.payload?.requirements?.contains_tickets);
  }

  function isMixedCart() {
    return hasProducts() && hasTickets();
  }

  function normalizedSummary() {
    const summary = state.payload?.summary || {};

    return {
      currency: String(summary.currency || 'VND'),
      item_count: Number(summary.item_count || 0),
      subtotal_price: Number(summary.subtotal_price || 0),
      total_price: Number(summary.total_price || 0)
    };
  }

  function currentShippingAmount() {
    if (!hasProducts() || state.selectedFulfillment !== 'delivery') {
      return 0;
    }

    return Number(state.payload?.pricing?.delivery_shipping_amount || 0);
  }

  function shippingLabel() {
    if (!hasProducts()) {
      return 'Ticket Delivery';
    }

    return state.selectedFulfillment === 'delivery' ? 'Delivery' : 'Pickup';
  }

  function checkoutScope() {
    if (isMixedCart()) return 'mixed';
    if (hasTickets()) return 'ticket';
    return 'shop';
  }

  function checkoutSubtitle() {
    const summary = normalizedSummary();
    if (isMixedCart()) {
      return `${itemLabel(summary.item_count)} including tickets ready for one checkout`;
    }
    if (hasTickets()) {
      return `${Number(state.payload?.ticket_selection?.seat_count || 0)} seat(s) ready for e-ticket checkout`;
    }

    return `${itemLabel(summary.item_count)} ready for checkout`;
  }

  function checkoutMeta() {
    if (isMixedCart()) {
      return 'Products and tickets are validated together so one payment can reserve inventory and seats consistently.';
    }
    if (hasTickets()) {
      return 'Held seats, pricing, and ticket delivery are validated on the server before the order is committed.';
    }

    return 'The checkout service snapshots pricing, stock, and payment state before any order is committed.';
  }

  function checkoutRuleCopy() {
    if (isMixedCart()) {
      return 'Mixed checkout uses one transaction to create the shop order, ticket order, and payment record together. VNPay is required so seats and inventory stay in sync.';
    }
    if (hasTickets()) {
      return 'Ticket checkout keeps seat reservations consistent with payment state. Pending VNPay payments expire automatically if they are not completed in time.';
    }

    return 'Order totals are recalculated on the server, stock is reserved inside the checkout transaction, and pending shop orders expire automatically if they are not completed in time.';
  }

  function finalSuccessCopy(order, guestLookupCopy) {
    const paymentMethod = humanize(order.payment_method || state.selectedPayment || 'payment');
    const scopeLabel = orderScopeLabel(order.order_scope || checkoutScope());

    if (order.payment_status === 'success') {
      return `${scopeLabel} order ${order.order_code || ''} has been confirmed with payment method ${paymentMethod}.${guestLookupCopy}`;
    }

    return `${scopeLabel} order ${order.order_code || ''} is pending confirmation with payment method ${paymentMethod}.${guestLookupCopy}`;
  }

  function continueShoppingHref() {
    return hasProducts() ? appUrl('/shop') : appUrl('/movies');
  }

  function defaultFulfillment() {
    return hasProducts() ? 'pickup' : 'e_ticket';
  }

  function resolveAuthToken() {
    if (typeof window.getAuthToken === 'function') {
      const token = window.getAuthToken();
      if (token) {
        return token;
      }
    }

    try {
      return window.localStorage?.getItem('cinemax_token')
        || window.sessionStorage?.getItem('cinemax_token')
        || '';
    } catch (error) {
      return '';
    }
  }

  async function fetchCheckout(url, options = {}) {
    const headers = Object.assign(
      { Accept: 'application/json' },
      options.body ? { 'Content-Type': 'application/json' } : {},
      options.headers || {}
    );
    const token = resolveAuthToken();
    if (token && !headers.Authorization) {
      headers.Authorization = `Bearer ${token}`;
    }

    const response = await fetch(url, {
      method: String(options.method || 'GET').toUpperCase(),
      headers,
      credentials: 'same-origin',
      cache: 'no-store',
      body: options.body ? JSON.stringify(options.body) : undefined
    });
    const raw = await response.text();
    let payload = {};

    if (raw.trim() !== '') {
      try {
        payload = JSON.parse(raw);
      } catch (error) {
        throw new Error('Checkout service returned an invalid response. Please try again.');
      }
    }

    if (!response.ok) {
      const error = new Error(firstErrorMessage(payload?.errors, 'Request failed.'));
      error.payload = payload;
      throw error;
    }

    return payload?.data || {};
  }

  function setSubmitState(isBusy) {
    state.isSubmitting = Boolean(isBusy);
    if (!dom.submit) {
      return;
    }

    dom.submit.disabled = state.isSubmitting;
    dom.submit.textContent = state.isSubmitting
      ? (state.selectedPayment === 'vnpay' ? 'Redirecting to VNPay...' : 'Creating Order...')
      : submitButtonLabel();
  }

  function submitButtonLabel() {
    if (state.selectedPayment === 'vnpay') {
      return 'Continue to VNPay';
    }

    if (hasTickets() && !hasProducts()) {
      return 'Confirm Tickets';
    }

    return 'Place Order';
  }

  function setStatus(message, isError) {
    if (!dom.status) {
      return;
    }

    dom.status.textContent = message;
    dom.status.style.color = isError ? '#fca5a5' : '';
  }

  function checkoutError(message) {
    if (typeof showToast === 'function') {
      showToast('!', 'Checkout', message);
    }
  }

  function itemLabel(count) {
    const total = Math.max(0, Number(count || 0));
    return `${total} item${total === 1 ? '' : 's'}`;
  }

  function checkoutSyncMessage(sync) {
    const adjusted = Math.max(0, Number(sync?.adjusted_items || 0));
    const removed = Math.max(0, Number(sync?.removed_items || 0));
    if (adjusted <= 0 && removed <= 0) {
      return '';
    }

    const parts = [];
    if (adjusted > 0) {
      parts.push(`${adjusted} item(s) were adjusted to match current stock or price.`);
    }
    if (removed > 0) {
      parts.push(`${removed} unavailable item(s) were removed from checkout.`);
    }

    return parts.join(' ');
  }

  function formatAmount(amount, currency) {
    if (typeof formatShopCurrency === 'function') {
      return formatShopCurrency(amount, currency);
    }

    const normalizedCurrency = String(currency || 'VND').toUpperCase();
    return new Intl.NumberFormat(normalizedCurrency === 'VND' ? 'vi-VN' : 'en-US', {
      style: 'currency',
      currency: normalizedCurrency,
      maximumFractionDigits: normalizedCurrency === 'VND' ? 0 : 2
    }).format(Number(amount || 0));
  }

  function paymentLogo(code) {
    if (code === 'vnpay') return 'VNP';
    if (code === 'cash') return 'CAS';
    return String(code || 'PAY').slice(0, 3).toUpperCase();
  }

  function humanize(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
  }

  function orderScopeLabel(scope) {
    if (scope === 'mixed') return 'Combined';
    if (scope === 'ticket') return 'Ticket';
    return 'Shop';
  }

  function createIdempotencyKey() {
    if (window.crypto?.randomUUID) {
      return `checkout-${window.crypto.randomUUID()}`;
    }

    return `checkout-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;
  }

  function checkoutApiUrl() {
    return appUrl('/api/shop/checkout');
  }

  function firstErrorMessage(errors, fallback) {
    if (!errors || typeof errors !== 'object') {
      return fallback;
    }

    for (const messages of Object.values(errors)) {
      if (Array.isArray(messages) && messages.length) {
        return String(messages[0]);
      }
    }

    return fallback;
  }

  function isGuestCheckoutOrder(order) {
    const userId = Number(order?.user_id || 0);
    return !Number.isInteger(userId) || userId <= 0;
  }

  function guestLookupUrl() {
    return appUrl('/my-orders?lookup=1');
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function appUrl(path) {
    const basePath = typeof window.APP_BASE_PATH === 'string' ? window.APP_BASE_PATH : '';
    const normalizedPath = String(path || '').startsWith('/') ? path : `/${path}`;

    return `${basePath}${normalizedPath}`;
  }

  document.addEventListener('DOMContentLoaded', init);
})();
