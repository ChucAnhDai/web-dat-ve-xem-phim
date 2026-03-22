(function unifiedOrdersModule() {
  const FILTERS = {
    all: () => true,
    pending: order => groupOf(order) === 'pending',
    active: order => groupOf(order) === 'active',
    completed: order => groupOf(order) === 'completed',
    issue: order => groupOf(order) === 'issue'
  };

  const state = {
    source: 'none',
    orders: [],
    summary: null,
    currentFilter: 'all',
    lookupOrder: null,
    lookupCredentials: null,
    modalOrder: null,
    modalAccess: null,
    pendingOpenOrderId: null,
    pendingLookupFocus: false,
    isLoading: false,
    isLookingUp: false,
    isCancelling: false
  };

  const dom = {};

  function init() {
    dom.body = document.getElementById('myOrdersBody');
    if (!dom.body) return;

    dom.subtitle = document.getElementById('myOrdersSubtitle');
    dom.accessStatus = document.getElementById('myOrdersAccessStatus');
    dom.accessMeta = document.getElementById('myOrdersAccessMeta');
    dom.lookupStatus = document.getElementById('guestOrderLookupStatus');
    dom.totalStat = document.getElementById('myOrdersTotalStat');
    dom.pendingStat = document.getElementById('myOrdersPendingStat');
    dom.activeStat = document.getElementById('myOrdersActiveStat');
    dom.issueStat = document.getElementById('myOrdersIssueStat');
    dom.lookupForm = document.getElementById('guestOrderLookupForm');
    dom.lookupCode = document.getElementById('guestOrderCode');
    dom.lookupEmail = document.getElementById('guestOrderEmail');
    dom.lookupPhone = document.getElementById('guestOrderPhone');
    dom.lookupReset = document.getElementById('guestOrderLookupResetBtn');
    dom.modal = document.getElementById('shopOrderModal');
    dom.modalContent = document.getElementById('shopOrderModalContent');
    dom.modalClose = document.getElementById('shopOrderModalClose');

    initQuery();
    bindFilters();
    bindLookup();
    bindModal();
    maybeFocusLookup();
    void loadOrders();
  }

  function initQuery() {
    const params = new URLSearchParams(window.location.search || '');
    state.pendingLookupFocus = params.get('lookup') === '1';
    state.pendingOpenOrderId = parsePositiveInteger(params.get('open'));
  }

  function bindFilters() {
    document.querySelectorAll('#myOrdersFilters [data-filter]').forEach(chip => {
      chip.addEventListener('click', () => {
        activateFilter(chip.dataset.filter || 'all');
        renderOrders();
      });
    });
  }

  function bindLookup() {
    dom.lookupForm?.addEventListener('submit', event => {
      event.preventDefault();
      void lookupGuestOrder();
    });

    dom.lookupReset?.addEventListener('click', () => {
      if (dom.lookupCode) dom.lookupCode.value = '';
      if (dom.lookupEmail) dom.lookupEmail.value = '';
      if (dom.lookupPhone) dom.lookupPhone.value = '';
      clearLookupResult({ closeModal: true });
      setLookupStatus('Guest lookup form and results were cleared.', false);
    });
  }

  function bindModal() {
    dom.modalClose?.addEventListener('click', closeModal);
    dom.modal?.addEventListener('click', event => {
      if (event.target === dom.modal) closeModal();
    });
  }

  async function loadOrders(options = {}) {
    state.isLoading = true;
    if (!options.silent) {
      setAccessStatus('Loading your orders...', false);
      renderLoading();
    }

    try {
      if (hasAuthToken()) {
        try {
          const payload = await fetchJson('/api/me/orders?per_page=100');
          applySource(payload?.data || payload || {}, 'member');
          return;
        } catch (error) {
          if (error.status !== 401) throw error;
          if (typeof clearAuthToken === 'function') clearAuthToken();
        }
      }

      applyGuestOnly();
    } catch (error) {
      state.source = 'none';
      state.orders = [];
      state.summary = null;
      updateSummary(null);
      updateSubtitle();
      updateAccessMeta();
      renderEmpty('Order history unavailable.', error.message || 'Unable to load order history right now.');
      setAccessStatus(error.message || 'Order request failed.', true);
    } finally {
      state.isLoading = false;
    }
  }

  function applySource(payload, fallbackSource) {
    state.source = String(payload.source || fallbackSource || 'none');
    state.orders = Array.isArray(payload.items) ? payload.items : [];
    state.summary = payload.summary || null;

    updateSummary(state.summary);
    updateSubtitle();
    updateAccessMeta();
    renderOrders();
    setAccessStatus(state.source === 'member' ? 'Signed-in orders loaded.' : guestLookupText(), false);
    void maybeAutoOpenOrder();
  }

  function applyGuestOnly() {
    state.source = 'none';
    state.orders = [];
    state.summary = null;
    updateSummary(null);
    updateSubtitle();
    updateAccessMeta();
    renderOrders();
    setAccessStatus(guestLookupText(), false);
    void maybeAutoOpenOrder();
  }

  function updateSummary(summary) {
    const safe = summary || {};
    if (dom.totalStat) dom.totalStat.textContent = String(Number(safe.total_orders || 0));
    if (dom.pendingStat) dom.pendingStat.textContent = String(Number(safe.pending_orders || 0));
    if (dom.activeStat) dom.activeStat.textContent = String(Number(safe.active_orders || 0));
    if (dom.issueStat) dom.issueStat.textContent = String(Number(safe.issue_orders || 0));
  }

  function updateSubtitle() {
    if (!dom.subtitle) return;
    dom.subtitle.textContent = state.source === 'member'
      ? 'Review your movie tickets, shop orders, and mixed checkouts in one place.'
      : 'Guest orders appear after a manual lookup with the exact checkout contact details.';
  }

  function updateAccessMeta() {
    if (!dom.accessMeta) return;
    dom.accessMeta.textContent = state.source === 'member'
      ? 'Account mode is active. Guest lookup stays available if you also placed orders without signing in.'
      : 'Guest browser-session access is disabled for security. Use the order code with the same checkout email and phone to open the order.';
  }

  function renderLoading() {
    dom.body.innerHTML = '<div class="detail-state-card detail-state-card-compact orders-empty-state"><div><strong>Loading orders...</strong><div>Please wait while we synchronize the latest payment and fulfillment state.</div></div></div>';
  }

  function renderEmpty(title, message) {
    dom.body.innerHTML = `<div class="detail-state-card detail-state-card-compact orders-empty-state"><div><strong>${escape(title)}</strong><div>${escape(message)}</div></div></div>`;
  }

  function renderOrders() {
    const orders = visibleOrders();
    const filtered = orders.filter(FILTERS[state.currentFilter] || FILTERS.all);

    if (!orders.length) {
      renderEmpty(
        state.source === 'member' ? 'No account orders found.' : 'No orders loaded.',
        state.source === 'member'
          ? 'Place a ticket, shop, or mixed order while signed in and it will appear here.'
          : 'Use the guest lookup form with your order code, checkout email, and checkout phone, or sign in to review member orders.'
      );
      return;
    }

    if (!filtered.length) {
      renderEmpty('No orders match this filter.', 'Try another filter to see more order history.');
      return;
    }

    dom.body.innerHTML = filtered.map(order => `
      <div class="table-row">
        <div>
          <div class="order-id">${escape(order.order_code || 'N/A')}</div>
          <div class="order-preview-text">${escape(orderSourceLabel(order))}</div>
        </div>
        <div class="order-preview-stack">
          ${(Array.isArray(order.preview_items) ? order.preview_items.slice(0, 3) : []).map(renderPreviewThumb).join('')}
          <span class="order-preview-text">${escape(itemSummary(order))}</span>
        </div>
        <span style="font-size:12px;color:var(--text2)">${escape(formatDateTime(order.order_date))}</span>
        <span style="font-weight:600">${escape(formatCurrency(order.total_price, order.currency))}</span>
        <span class="ticket-status status-${escape(statusClass(order.status_group || order.status))}">${escape(humanize(order.status))}</span>
        <div class="order-action-stack">
          <button class="btn btn-secondary btn-sm" type="button" data-order-code="${escapeAttr(String(order.order_code || ''))}">View</button>
        </div>
      </div>
    `).join('');

    dom.body.querySelectorAll('[data-order-code]').forEach(button => {
      button.addEventListener('click', () => openOrderDetail(String(button.dataset.orderCode || '')));
    });
  }

  function openOrderDetail(orderCode) {
    const order = findOrderByCode(orderCode);
    if (!order) {
      setAccessStatus('Order detail is unavailable.', true);
      return;
    }

    state.modalOrder = order;
    state.modalAccess = {
      source: isLookupOrder(order) ? 'lookup' : state.source,
      orderCode: order.order_code || '',
      credentials: state.lookupCredentials || null
    };
    renderModal(order);
    (isLookupOrder(order) ? setLookupStatus : setAccessStatus)(`Order ${order.order_code || ''} loaded.`, false);
  }

  async function lookupGuestOrder() {
    const credentials = {
      order_code: String(dom.lookupCode?.value || '').trim(),
      contact_email: String(dom.lookupEmail?.value || '').trim(),
      contact_phone: String(dom.lookupPhone?.value || '').trim()
    };

    await lookupGuestOrderWithCredentials(credentials, { openModal: true, focusOnError: true });
  }

  async function lookupGuestOrderWithCredentials(credentials, options = {}) {
    if (state.isLookingUp) return null;

    const validationMessage = validateLookupCredentials(credentials);
    if (validationMessage) {
      setLookupStatus(validationMessage, true);
      if (options.focusOnError) maybeFocusLookup();
      return null;
    }

    state.isLookingUp = true;
    try {
      if (!options.preserveExisting) clearLookupResult({ closeModal: true, rerender: false });
      setLookupStatus('Looking up order...', false);

      const payload = await fetchJson('/api/orders/lookup', { method: 'POST', body: credentials });
      const data = payload?.data || payload || {};
      const order = data.order || null;
      if (!order) throw new Error('Order detail is unavailable.');

      state.lookupOrder = order;
      state.lookupCredentials = credentials;
      state.modalOrder = order;
      state.modalAccess = { source: 'lookup', orderCode: order.order_code || '', credentials };

      activateFilter('all');
      renderOrders();
      if (options.openModal) renderModal(order);
      setLookupStatus(`Order ${order.order_code || ''} loaded.`, false);
      if (!options.silentToast) toast('+', 'Order found', `Order ${order.order_code || ''} is ready to review.`);
      return order;
    } catch (error) {
      clearLookupResult({ closeModal: true });
      setLookupStatus(error.message || 'Guest lookup failed.', true);
      return null;
    } finally {
      state.isLookingUp = false;
    }
  }

  function renderModal(order) {
    if (!dom.modal || !dom.modalContent) return;

    const countdownText = Number(order.expires_in_seconds || 0) > 0
      ? `${Math.ceil(Number(order.expires_in_seconds || 0) / 60)} minute(s) remaining before pending payment expiry.`
      : 'No pending payment countdown is active for this order.';

    dom.modalContent.innerHTML = `
      <div class="order-detail-layout">
        <div class="order-detail-top">
          <div>
            <div class="summary-title" style="margin-bottom:6px;">${escape(order.order_code || 'Order detail')}</div>
            <div class="lookup-help">${escape(formatDateTime(order.order_date))} - ${escape(orderSourceLabel(order))}</div>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start;">
            <span class="ticket-status status-${escape(statusClass(order.status_group || order.status))}">${escape(humanize(order.status))}</span>
            <span class="ticket-status status-${escape(statusClass(order.payment_status))}">${escape(humanize(order.payment_status || 'pending'))}</span>
          </div>
        </div>
        <div class="order-detail-section">
          <h4>Fulfillment & Payment</h4>
          <div class="order-detail-kv">
            ${kv('Order Scope', orderScopeLabel(order.order_scope))}
            ${kv('Fulfillment', humanize(order.fulfillment_method || 'e_ticket'))}
            ${kv('Payment Method', humanize(order.payment_method || 'unknown'))}
            ${kv('Total', formatCurrency(order.total_price, order.currency))}
            ${kv('Shipping', order.shipping_summary || 'E-ticket delivery')}
            ${kv('Payment Due', order.payment_due_at ? formatDateTime(order.payment_due_at) : 'Not pending')}
            ${kv('Countdown', countdownText)}
          </div>
        </div>
        <div class="order-detail-section">
          <h4>Contact</h4>
          <div class="order-detail-kv">
            ${kv('Name', order.contact_name || 'Guest customer')}
            ${kv('Email', order.contact_email || 'No email')}
            ${kv('Phone', order.contact_phone || 'No phone')}
            ${kv('Access Type', order.is_guest_order ? 'Guest order' : 'Account order')}
          </div>
        </div>
        ${renderProductSection(order)}
        ${renderTicketSection(order)}
        <div class="order-detail-actions">
          ${order.requires_payment_resume && order.redirect_url ? `<a class="btn btn-secondary" href="${escapeAttr(order.redirect_url)}">Continue Payment</a>` : ''}
          ${order.can_cancel && order.order_scope === 'shop' ? '<button class="btn btn-primary" id="shopOrderCancelBtn" type="button">Cancel Order</button>' : ''}
          <a class="btn btn-secondary" href="${escapeAttr(continueShoppingHref(order))}">Continue Shopping</a>
        </div>
      </div>
    `;

    dom.modal.classList.add('open');
    document.getElementById('shopOrderCancelBtn')?.addEventListener('click', () => {
      void cancelCurrentOrder();
    });
  }

  function renderProductSection(order) {
    const items = Array.isArray(order.items) ? order.items : [];
    if (!items.length) return '';

    return `<div class="order-detail-section"><h4>Shop Items</h4><div class="order-detail-items">${items.map(item => `
      <div class="order-detail-line">
        <div class="order-detail-media">${item.primary_image_url ? `<img src="${escapeAttr(item.primary_image_url)}" alt="${escapeAttr(item.primary_image_alt || item.product_name || 'Product')}" loading="lazy" onerror="this.remove()">` : ''}</div>
        <div>
          <div style="font-weight:700;">${escape(item.product_name || 'Product')}</div>
          <div class="lookup-help">${escape(item.product_sku || 'No SKU')} - Qty ${Number(item.quantity || 0)}</div>
        </div>
        <div class="order-detail-total">${escape(formatCurrency(item.line_total, item.currency || order.currency))}</div>
      </div>`).join('')}</div></div>`;
  }

  function renderTicketSection(order) {
    const tickets = Array.isArray(order.tickets) ? order.tickets : [];
    if (!tickets.length) return '';

    return `<div class="order-detail-section"><h4>Movie Tickets</h4><div class="order-detail-items">${tickets.map(ticket => `
      <div class="order-detail-line">
        <div class="order-detail-media">${ticket.poster_url ? `<img src="${escapeAttr(ticket.poster_url)}" alt="${escapeAttr(ticket.movie_title || 'Movie poster')}" loading="lazy" onerror="this.remove()">` : ''}</div>
        <div>
          <div style="font-weight:700;">${escape(ticket.movie_title || 'Movie ticket')}</div>
          <div class="lookup-help">${escape(ticket.cinema_name || 'Cinema')} - ${escape(ticket.room_name || 'Room')} - Seat ${escape(ticket.seat_label || 'N/A')}</div>
          <div class="lookup-help">${escape(ticket.ticket_code || 'No ticket code')} - ${escape(ticketShowtimeSummary(ticket))}</div>
        </div>
        <div class="order-detail-total">${escape(formatCurrency(ticket.price, order.currency || ticket.currency))}</div>
      </div>`).join('')}</div></div>`;
  }

  async function cancelCurrentOrder() {
    if (state.isCancelling || !state.modalOrder || !state.modalAccess || state.modalOrder.order_scope !== 'shop') return;

    const orderCode = state.modalOrder.order_code || 'this order';
    const confirmed = typeof window.confirm === 'function'
      ? window.confirm(`Cancel ${orderCode}? This is only available while the order is still unpaid and pending.`)
      : true;
    if (!confirmed) return;

    state.isCancelling = true;
    const isLookup = state.modalAccess.source === 'lookup';
    const setStatus = isLookup ? setLookupStatus : setAccessStatus;

    try {
      setStatus(`Cancelling ${orderCode}...`, false);

      if (isLookup) {
        await fetchJson('/api/shop/orders/lookup/cancel', { method: 'POST', body: state.modalAccess.credentials || state.lookupCredentials || {} });
        const refreshed = await lookupGuestOrderWithCredentials(state.modalAccess.credentials || state.lookupCredentials || {}, {
          openModal: true,
          silentToast: true,
          preserveExisting: true
        });
        if (!refreshed) throw new Error('Order cancellation completed but the updated order could not be reloaded.');
      } else {
        const productOrderId = Number(state.modalOrder.product_order_id || 0);
        if (!Number.isInteger(productOrderId) || productOrderId <= 0) throw new Error('Shop order reference is missing.');
        await fetchJson(`/api/me/shop-orders/${productOrderId}/cancel`, { method: 'POST', body: {} });
        await loadOrders({ silent: true });
        const refreshed = findOrderByCode(orderCode);
        if (!refreshed) throw new Error('Order cancellation completed but the updated order could not be reloaded.');
        state.modalOrder = refreshed;
        state.modalAccess = { source: 'member', orderCode: refreshed.order_code || '' };
        renderModal(refreshed);
      }

      setStatus(`Order ${orderCode} was cancelled successfully.`, false);
      toast('+', 'Order cancelled', `Order ${orderCode} was cancelled successfully.`);
      renderOrders();
    } catch (error) {
      setStatus(error.message || 'Failed to cancel the order.', true);
      toast('!', 'Cancel order', error.message || 'Unable to cancel this order.');
    } finally {
      state.isCancelling = false;
    }
  }

  function closeModal() {
    state.modalOrder = null;
    state.modalAccess = null;
    dom.modal?.classList.remove('open');
  }

  function activateFilter(filterName) {
    state.currentFilter = filterName;
    document.querySelectorAll('#myOrdersFilters [data-filter]').forEach(item => {
      item.classList.toggle('active', (item.dataset.filter || 'all') === filterName);
    });
  }

  function visibleOrders() {
    const merged = [];
    const seen = new Set();
    [state.lookupOrder, ...state.orders].forEach((order, index) => {
      if (!order || typeof order !== 'object') return;
      const key = orderIdentity(order, index);
      if (seen.has(key)) return;
      seen.add(key);
      merged.push(order);
    });
    return merged;
  }

  function findOrderByCode(orderCode) {
    const normalized = String(orderCode || '').trim().toUpperCase();
    if (!normalized) return null;
    return visibleOrders().find(order => String(order.order_code || '').trim().toUpperCase() === normalized) || null;
  }

  function isLookupOrder(order) {
    return Boolean(order && state.lookupOrder && String(order.order_code || '').trim().toUpperCase() === String(state.lookupOrder.order_code || '').trim().toUpperCase());
  }

  function orderIdentity(order, fallbackIndex) {
    const orderCode = String(order?.order_code || '').trim().toUpperCase();
    if (orderCode) return `code:${orderCode}`;
    const productOrderId = parsePositiveInteger(order?.product_order_id);
    if (productOrderId !== null) return `shop:${productOrderId}`;
    const ticketOrderId = parsePositiveInteger(order?.ticket_order_id);
    if (ticketOrderId !== null) return `ticket:${ticketOrderId}`;
    return `fallback:${fallbackIndex}`;
  }

  function maybeFocusLookup() {
    if (!state.pendingLookupFocus || !dom.lookupCode) return;
    state.pendingLookupFocus = false;
    replaceQueryParam('lookup', null);
    window.requestAnimationFrame(() => {
      dom.lookupForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      try { dom.lookupCode.focus({ preventScroll: true }); } catch (error) { dom.lookupCode.focus(); }
    });
  }

  async function maybeAutoOpenOrder() {
    const requestedOrderId = state.pendingOpenOrderId;
    if (requestedOrderId === null) return;
    state.pendingOpenOrderId = null;
    replaceQueryParam('open', null);

    if (state.source !== 'member') {
      setAccessStatus(guestLookupText(), false);
      state.pendingLookupFocus = true;
      maybeFocusLookup();
      return;
    }

    const order = state.orders.find(entry => {
      const ids = [entry.id, entry.product_order_id, entry.ticket_order_id].map(parsePositiveInteger);
      return ids.includes(requestedOrderId);
    });

    if (!order) {
      setAccessStatus('Requested order could not be found in your history.', true);
      return;
    }

    openOrderDetail(order.order_code || '');
  }

  function replaceQueryParam(name, value) {
    if (!window.history?.replaceState) return;
    const url = new URL(window.location.href);
    if (value === null || value === undefined || value === '') url.searchParams.delete(name);
    else url.searchParams.set(name, String(value));
    window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
  }

  function parsePositiveInteger(value) {
    const parsed = Number(value);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
  }

  async function fetchJson(path, options = {}) {
    const headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
    const token = typeof getAuthToken === 'function' ? getAuthToken() : '';
    if (token && !headers.Authorization) headers.Authorization = `Bearer ${token}`;

    const hasBody = Object.prototype.hasOwnProperty.call(options, 'body');
    if (hasBody && !headers['Content-Type']) headers['Content-Type'] = 'application/json';

    const response = await fetch(appUrl(path), {
      method: String(options.method || 'GET').toUpperCase(),
      headers,
      cache: 'no-store',
      credentials: 'same-origin',
      body: hasBody ? JSON.stringify(options.body) : undefined
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      const error = new Error(firstErrorMessage(payload?.errors, payload?.message || 'Request failed.'));
      error.status = response.status;
      throw error;
    }

    return payload || {};
  }

  function hasAuthToken() {
    return Boolean(typeof getAuthToken === 'function' && getAuthToken());
  }

  function clearLookupResult(options = {}) {
    state.lookupOrder = null;
    state.lookupCredentials = null;
    if (options.closeModal && state.modalAccess?.source === 'lookup') closeModal();
    activateFilter('all');
    if (options.rerender !== false) renderOrders();
  }

  function validateLookupCredentials(credentials) {
    if (!credentials.order_code) return 'Please enter an order code.';
    if (!credentials.contact_email) return 'Please enter the checkout email used for this order.';
    if (!credentials.contact_phone) return 'Please enter the checkout phone used for this order.';
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(credentials.contact_email)) return 'Checkout email is invalid.';
    if (!/^[0-9+\s().-]{9,20}$/.test(credentials.contact_phone)) return 'Checkout phone is invalid.';
    return '';
  }

  function guestLookupText() {
    return 'Guest automatic access is disabled. Use order lookup with the order code, checkout email, and checkout phone.';
  }

  function groupOf(order) {
    return String(order?.status_group || '').toLowerCase();
  }

  function itemSummary(order) {
    const parts = [];
    const productCount = Number(order.item_count || 0);
    const seatCount = Number(order.seat_count || 0);
    if (productCount > 0) parts.push(`${productCount} product${productCount === 1 ? '' : 's'}`);
    if (seatCount > 0) parts.push(`${seatCount} seat${seatCount === 1 ? '' : 's'}`);
    if (!parts.length) parts.push(orderScopeLabel(order.order_scope));
    return `${parts.join(' + ')} - ${humanize(order.payment_method || 'payment')}`;
  }

  function orderSourceLabel(order) {
    return `${orderScopeLabel(order.order_scope)} ${order.is_guest_order ? 'guest' : 'account'} order`;
  }

  function orderScopeLabel(scope) {
    if (scope === 'mixed') return 'Mixed';
    if (scope === 'ticket') return 'Ticket';
    return 'Shop';
  }

  function continueShoppingHref(order) {
    return order.contains_products ? appUrl('/shop') : appUrl('/movies');
  }

  function ticketShowtimeSummary(ticket) {
    const parts = [formatShowDate(ticket.show_date), formatTime(ticket.start_time)].filter(Boolean);
    return parts.length ? parts.join(' - ') : 'Showtime pending';
  }

  function renderPreviewThumb(item) {
    if (!item || !item.primary_image_url) return '<div class="order-item-thumb"></div>';
    const alt = item.primary_image_alt || item.product_name || item.label || 'Preview';
    return `<div class="order-item-thumb"><img src="${escapeAttr(item.primary_image_url)}" alt="${escapeAttr(alt)}" loading="lazy" onerror="this.remove()"></div>`;
  }

  function kv(label, value) {
    return `<div class="order-detail-kv-item"><div class="order-detail-kv-label">${escape(label)}</div><div class="order-detail-kv-value">${escape(value || 'N/A')}</div></div>`;
  }

  function statusClass(value) {
    const normalized = String(value || '').trim().toLowerCase();
    if (['cancelled', 'expired', 'failed', 'refunded', 'issue'].includes(normalized)) return 'danger';
    if (['pending', 'processing', 'preparing', 'ready', 'shipping', 'active'].includes(normalized)) return 'pending';
    return 'success';
  }

  function humanize(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
  }

  function formatCurrency(amount, currency) {
    const normalized = String(currency || 'VND').toUpperCase();
    return new Intl.NumberFormat(normalized === 'VND' ? 'vi-VN' : 'en-US', {
      style: 'currency',
      currency: normalized,
      maximumFractionDigits: normalized === 'VND' ? 0 : 2
    }).format(Number(amount || 0));
  }

  function formatDateTime(value) {
    const normalized = String(value || '').trim();
    if (!normalized) return 'N/A';
    const parsed = new Date(normalized.replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return normalized;
    return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(parsed);
  }

  function formatShowDate(value) {
    if (!value) return '';
    const parsed = new Date(`${value}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return String(value);
    return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(parsed);
  }

  function formatTime(value) {
    const time = String(value || '').trim();
    if (!time) return '';
    const parsed = new Date(`1970-01-01T${time}`);
    if (Number.isNaN(parsed.getTime())) return time;
    return new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit' }).format(parsed);
  }

  function setAccessStatus(message, isError) { setPanelStatus(dom.accessStatus, message, isError); }
  function setLookupStatus(message, isError) { setPanelStatus(dom.lookupStatus, message, isError); }
  function setPanelStatus(element, message, isError) {
    if (!element) return;
    element.textContent = message;
    element.style.color = isError ? '#fca5a5' : '';
  }

  function toast(icon, title, message) {
    if (typeof showToast === 'function') showToast(icon, title, message);
  }

  function firstErrorMessage(errors, fallback) {
    if (!errors || typeof errors !== 'object') return fallback || 'Request failed.';
    for (const messages of Object.values(errors)) {
      if (Array.isArray(messages) && messages.length > 0) return String(messages[0]);
    }
    return fallback || 'Request failed.';
  }

  function appUrl(path) {
    const basePath = typeof window.APP_BASE_PATH === 'string' ? window.APP_BASE_PATH : '';
    const normalized = String(path || '').startsWith('/') ? path : `/${path}`;
    return `${basePath}${normalized}`;
  }

  function escape(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function escapeAttr(value) {
    return escape(value).replace(/`/g, '&#96;');
  }

  document.addEventListener('DOMContentLoaded', init);
})();
