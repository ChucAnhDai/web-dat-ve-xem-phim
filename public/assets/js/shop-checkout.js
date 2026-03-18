(function shopCheckoutModule() {
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
    renderStateCard('Loading checkout...', 'Validating your cart and looking for any unfinished shop payment.');
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
    dom.deliveryFields = document.getElementById('shopCheckoutDeliveryFields');
    dom.address = document.getElementById('shopCheckoutAddress');
    dom.city = document.getElementById('shopCheckoutCity');
    dom.district = document.getElementById('shopCheckoutDistrict');
    dom.paymentGroup = document.getElementById('shopCheckoutPaymentGroup');
    dom.items = document.getElementById('shopCheckoutItems');
    dom.subtotalLabel = document.getElementById('shopCheckoutSubtotalLabel');
    dom.subtotal = document.getElementById('shopCheckoutSubtotal');
    dom.shipping = document.getElementById('shopCheckoutShipping');
    dom.total = document.getElementById('shopCheckoutTotal');
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

      if (!state.payload?.checkout_ready || state.payload?.cart?.is_empty) {
        renderEmptyState();
        return;
      }

      applyDefaults();
      renderCheckout();
      await hydrateProfileDefaults();
    } catch (error) {
      renderStateCard('Checkout unavailable.', error.message || 'Unable to load shop checkout right now.');
      setStatus(error.message || 'Checkout request failed', true);
    }
  }

  function applyDefaults() {
    const defaults = state.payload?.defaults || {};
    state.selectedFulfillment = String(defaults.fulfillment_method || 'pickup');
    state.selectedPayment = String(defaults.payment_method || '');
    ensureAllowedPaymentSelected();
  }

  function renderCheckout() {
    dom.state.innerHTML = '';
    dom.content.hidden = false;
    if (dom.subtitle) {
      dom.subtitle.textContent = `${itemLabel(state.payload?.cart?.item_count || 0)} ready for checkout`;
    }
    if (dom.meta) {
      dom.meta.textContent = 'The checkout service snapshots pricing, stock, and payment state before any order is committed.';
    }

    renderFulfillmentOptions();
    renderPaymentOptions();
    renderDeliveryFields();
    renderSummary();
    setStatus('Checkout is ready', false);
    setSubmitState(false);
  }

  function renderEmptyState() {
    dom.content.hidden = true;
    renderStateCard('Your cart is empty.', 'Add products to the cart before starting the shop checkout flow.');
    setStatus('Cart is empty', false);
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

  function resolveAuthToken() {
    if (typeof window.getAuthToken === 'function') {
      const token = window.getAuthToken();
      if (token) {
        return token;
      }
    }

    try {
      const token = window.localStorage?.getItem('cinemax_token') || '';
      if (!token) {
        console.warn('[shop-checkout] No auth token found in localStorage.');
      }
      return token;
    } catch (error) {
      console.error('[shop-checkout] Failed to resolve auth token', error);
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
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(firstErrorMessage(payload?.errors, 'Request failed.'));
    }

    return payload?.data || {};
  }

  function renderFulfillmentOptions() {
    const options = Array.isArray(state.payload?.fulfillment_methods) ? state.payload.fulfillment_methods : [];
    if (!dom.fulfillmentGroup) {
      return;
    }

    dom.fulfillmentGroup.innerHTML = options.map(option => `
      <button class="radio-option ${option.code === state.selectedFulfillment ? 'selected' : ''}" type="button" data-fulfillment="${escapeHtml(option.code)}">
        <div class="radio-info">
          <div class="radio-label">${escapeHtml(option.label)}</div>
          <div class="radio-desc">${escapeHtml(option.description || '')}</div>
        </div>
      </button>
    `).join('');

    dom.fulfillmentGroup.querySelectorAll('[data-fulfillment]').forEach(button => {
      button.addEventListener('click', () => {
        state.selectedFulfillment = String(button.dataset.fulfillment || 'pickup');
        ensureAllowedPaymentSelected();
        renderFulfillmentOptions();
        renderPaymentOptions();
        renderDeliveryFields();
        renderSummaryNote();
      });
    });
  }

  function renderPaymentOptions() {
    const methods = Array.isArray(state.payload?.payment_methods) ? state.payload.payment_methods : [];
    if (!dom.paymentGroup) {
      return;
    }

    dom.paymentGroup.innerHTML = methods.map(method => {
      const allowed = Array.isArray(method.allowed_fulfillment_methods)
        ? method.allowed_fulfillment_methods.includes(state.selectedFulfillment)
        : true;

      return `
        <button class="payment-option ${method.code === state.selectedPayment ? 'selected' : ''}" type="button" data-payment="${escapeHtml(method.code)}" ${allowed ? '' : 'disabled'}>
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
      });
    });
  }

  function renderDeliveryFields() {
    const isDelivery = state.selectedFulfillment === 'delivery';
    if (dom.deliveryFields) {
      dom.deliveryFields.hidden = !isDelivery;
    }
  }

  function renderSummary() {
    const cart = state.payload?.cart || { items: [], item_count: 0, subtotal_price: 0, total_price: 0, currency: 'VND' };
    if (dom.items) {
      dom.items.innerHTML = (Array.isArray(cart.items) ? cart.items : []).map(item => `
        <div class="order-item-row">
          <div class="order-item-img">
            ${item.primary_image_url ? `<img src="${escapeHtml(item.primary_image_url)}" alt="${escapeHtml(item.primary_image_alt || item.name || 'Product')}" loading="lazy">` : ''}
          </div>
          <div>
            <div class="order-item-name">${escapeHtml(item.name || 'Product')}</div>
            <div class="order-item-qty">Qty ${Number(item.quantity || 0)}</div>
          </div>
          <div class="order-item-price">${formatCurrency(item.line_total || 0, item.currency || cart.currency || 'VND')}</div>
        </div>
      `).join('');
    }

    if (dom.subtotalLabel) {
      dom.subtotalLabel.textContent = `Subtotal (${itemLabel(cart.item_count || 0)})`;
    }
    if (dom.subtotal) {
      dom.subtotal.textContent = formatCurrency(cart.subtotal_price || 0, cart.currency || 'VND');
    }
    if (dom.shipping) {
      dom.shipping.textContent = formatCurrency(state.selectedFulfillment === 'delivery' ? 0 : 0, cart.currency || 'VND');
    }
    if (dom.total) {
      dom.total.textContent = formatCurrency(cart.total_price || 0, cart.currency || 'VND');
    }

    renderSummaryNote();
  }

  function renderSummaryNote() {
    if (!dom.summaryNote) {
      return;
    }

    if (state.selectedPayment === 'vnpay') {
      dom.summaryNote.textContent = 'VNPay checkout will create a pending order, reserve stock, and redirect you to the secure payment gateway. If payment is not completed, the order expires after 5 minutes.';
      return;
    }

    dom.summaryNote.textContent = state.selectedFulfillment === 'pickup'
      ? 'Cash pickup orders stay pending for up to 5 minutes unless the cinema shop confirms payment at the counter first.'
      : 'This order remains pending for up to 5 minutes while payment and fulfillment are being completed.';
  }

  function ensureAllowedPaymentSelected() {
    const methods = Array.isArray(state.payload?.payment_methods) ? state.payload.payment_methods : [];
    const allowedMethods = methods.filter(method => Array.isArray(method.allowed_fulfillment_methods)
      ? method.allowed_fulfillment_methods.includes(state.selectedFulfillment)
      : true);

    if (allowedMethods.some(method => method.code === state.selectedPayment)) {
      return;
    }

    state.selectedPayment = String(allowedMethods[0]?.code || methods[0]?.code || '');
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
    if (body.fulfillment_method === 'delivery' && (!body.shipping_address_text || !body.shipping_city || !body.shipping_district)) {
      return checkoutError('Delivery orders require address, city, and district.');
    }

    setSubmitState(true);

    try {
      const idempotencyKey = createIdempotencyKey();
      const result = await fetchCheckout(checkoutApiUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Idempotency-Key': idempotencyKey },
        body
      });
      const guestOrder = isGuestCheckoutOrder(result?.order);
      const primaryHref = guestOrder ? guestLookupUrl() : appUrl('/my-orders');
      const primaryLabel = guestOrder ? 'Open Order Lookup' : 'Open My Orders';
      const guestLookupCopy = guestOrder
        ? ` Save order ${result.order?.order_code || ''} and use the same checkout email and phone on the guest lookup page whenever you need to view or manage it.`
        : '';

      if (window.shopCartRuntime?.refresh) {
        void window.shopCartRuntime.refresh();
      }

      if (result.redirect_url) {
        renderStateCard(
          'Redirecting to VNPay...',
          `Order ${result.order?.order_code || ''} was created successfully and is now being handed off to the payment gateway.${guestLookupCopy}`,
          `<a class="btn btn-primary btn-sm" href="${escapeHtml(result.redirect_url)}">Open VNPay Checkout</a>`
        );
        window.location.href = result.redirect_url;
        return;
      }

      renderStateCard(
        'Order created successfully.',
        `Order ${result.order?.order_code || ''} is pending confirmation with payment method ${humanize(result.payment?.payment_method || state.selectedPayment)}.${guestLookupCopy}`,
        `<a class="btn btn-primary btn-sm" href="${primaryHref}">${primaryLabel}</a><a class="btn btn-ghost btn-sm" href="${appUrl('/shop')}">Continue Shopping</a>`
      );
      if (typeof showToast === 'function') {
        showToast('+', 'Order created', 'Your shop order has been created successfully.');
      }
    } catch (error) {
      checkoutError(error.message || 'Unable to create the shop order.');
    } finally {
      setSubmitState(false);
    }
  }

  function renderActiveOrderState(activeOrder) {
    const orderCode = activeOrder?.order?.order_code || 'N/A';
    const paymentMethod = humanize(activeOrder?.payment?.payment_method || 'payment');
    const guestOrder = isGuestCheckoutOrder(activeOrder?.order);
    const actionPrimary = activeOrder?.redirect_url
      ? `<a class="btn btn-primary btn-sm" href="${escapeHtml(activeOrder.redirect_url)}">Continue to VNPay</a>`
      : `<a class="btn btn-primary btn-sm" href="${guestOrder ? guestLookupUrl() : appUrl('/my-orders')}">${guestOrder ? 'Open Order Lookup' : 'Open My Orders'}</a>`;
    const restoreCopy = guestOrder
      ? `A ${paymentMethod} guest checkout is already waiting for payment confirmation. Browser-session access is disabled, so use the same checkout email and phone on the lookup page if you need to review it later.`
      : `A ${paymentMethod} checkout is already waiting for payment confirmation. Resume that flow instead of creating a duplicate order.`;

    renderStateCard(
      `Pending order ${orderCode} restored.`,
      restoreCopy,
      `${actionPrimary}<a class="btn btn-ghost btn-sm" href="${appUrl('/cart')}">Back to Cart</a>`
    );
    setStatus('Pending checkout restored', false);
  }

  function isGuestCheckoutOrder(order) {
    const userId = Number(order?.user_id || 0);
    return !Number.isInteger(userId) || userId <= 0;
  }

  function guestLookupUrl() {
    return appUrl('/my-orders?lookup=1');
  }

  function setSubmitState(isBusy) {
    state.isSubmitting = Boolean(isBusy);
    if (!dom.submit) {
      return;
    }

    dom.submit.disabled = state.isSubmitting;
    dom.submit.textContent = state.isSubmitting
      ? (state.selectedPayment === 'vnpay' ? 'Redirecting to VNPay...' : 'Creating Order...')
      : (state.selectedPayment === 'vnpay' ? 'Continue to VNPay' : 'Place Order');
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

  function formatCurrency(amount, currency) {
    return new Intl.NumberFormat(String(currency || 'VND').toUpperCase() === 'VND' ? 'vi-VN' : 'en-US', {
      style: 'currency',
      currency: String(currency || 'VND').toUpperCase(),
      maximumFractionDigits: String(currency || 'VND').toUpperCase() === 'VND' ? 0 : 2
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

  function createIdempotencyKey() {
    if (window.crypto?.randomUUID) {
      return `shop-${window.crypto.randomUUID()}`;
    }

    return `shop-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;
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
