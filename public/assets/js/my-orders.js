(function () {
  const FILTERS = {
    all: () => true,
    pending: order => String(order.status || '').toLowerCase() === 'pending',
    active: order => ['confirmed', 'preparing', 'ready', 'shipping'].includes(String(order.status || '').toLowerCase()),
    completed: order => String(order.status || '').toLowerCase() === 'completed',
    issue: order => ['cancelled', 'expired', 'refunded'].includes(String(order.status || '').toLowerCase())
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
    if (!dom.body) {
      return;
    }

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

    initializeQueryActions();
    bindFilters();
    bindLookupForm();
    bindModal();
    maybeFocusLookupForm();
    void loadPrimaryOrders();
  }

  function initializeQueryActions() {
    const params = new URLSearchParams(window.location.search || '');
    state.pendingLookupFocus = params.get('lookup') === '1';
    state.pendingOpenOrderId = parsePositiveInteger(params.get('open'));
  }

  function bindFilters() {
    document.querySelectorAll('#myOrdersFilters [data-filter]').forEach(chip => {
      chip.addEventListener('click', () => {
        document.querySelectorAll('#myOrdersFilters [data-filter]').forEach(item => item.classList.remove('active'));
        chip.classList.add('active');
        state.currentFilter = chip.dataset.filter || 'all';
        renderOrders();
      });
    });
  }

  function bindLookupForm() {
    dom.lookupForm?.addEventListener('submit', event => {
      event.preventDefault();
      void lookupGuestOrder();
    });

    dom.lookupReset?.addEventListener('click', () => {
      if (dom.lookupCode) dom.lookupCode.value = '';
      if (dom.lookupEmail) dom.lookupEmail.value = '';
      if (dom.lookupPhone) dom.lookupPhone.value = '';
      setLookupStatus('Guest lookup form was cleared.', false);
    });
  }

  function bindModal() {
    dom.modalClose?.addEventListener('click', closeModal);
    dom.modal?.addEventListener('click', event => {
      if (event.target === dom.modal) {
        closeModal();
      }
    });
  }

  async function loadPrimaryOrders() {
    state.isLoading = true;
    setAccessStatus('Loading your orders...', false);
    renderLoadingState();

    try {
      if (hasAuthToken()) {
        try {
          const payload = await fetchJson('/api/me/shop-orders?per_page=100');
          applyOrderSource(payload?.data || payload || {}, 'member');
          return;
        } catch (error) {
          if (error.status !== 401) {
            throw error;
          }

          if (typeof clearAuthToken === 'function') {
            clearAuthToken();
          }
        }
      }

      const payload = await fetchJson('/api/shop/orders/session?per_page=100');
      applyOrderSource(payload?.data || payload || {}, 'session');
    } catch (error) {
      state.source = 'none';
      state.orders = [];
      state.summary = null;
      updateSummary(null);
      updateSubtitle();
      updateAccessMeta();
      renderEmptyState('Order history unavailable.', error.message || 'Unable to load order history right now.');
      setAccessStatus(error.message || 'Order request failed.', true);
    } finally {
      state.isLoading = false;
    }
  }

  function applyOrderSource(payload, fallbackSource) {
    state.source = String(payload.source || fallbackSource || 'none');
    state.orders = Array.isArray(payload.items) ? payload.items : [];
    state.summary = payload.summary || null;

    updateSummary(state.summary);
    updateSubtitle();
    updateAccessMeta(payload);
    renderOrders();

    if (state.source === 'member') {
      setAccessStatus('Signed-in account orders loaded.', false);
    } else if (payload.session_attached) {
      setAccessStatus('Guest orders for this browser session loaded.', false);
    } else {
      setAccessStatus('No account session detected. Use guest lookup if needed.', false);
    }

    void maybeAutoOpenRequestedOrder();
  }

  function updateSummary(summary) {
    const safe = summary || {};
    if (dom.totalStat) dom.totalStat.textContent = String(Number(safe.total_orders || 0));
    if (dom.pendingStat) dom.pendingStat.textContent = String(Number(safe.pending_orders || 0));
    if (dom.activeStat) dom.activeStat.textContent = String(Number(safe.active_orders || 0));
    if (dom.issueStat) dom.issueStat.textContent = String(Number(safe.issue_orders || 0));
  }

  function updateSubtitle() {
    if (!dom.subtitle) {
      return;
    }

    if (state.source === 'member') {
      dom.subtitle.textContent = 'These shop orders are linked directly to your signed-in account.';
      return;
    }

    if (state.source === 'session') {
      dom.subtitle.textContent = 'These guest shop orders were detected from the current browser session cookie.';
      return;
    }

    dom.subtitle.textContent = 'Use guest lookup or sign in to review your shop orders.';
  }

  function updateAccessMeta(payload) {
    if (!dom.accessMeta) {
      return;
    }

    if (state.source === 'member') {
      dom.accessMeta.textContent = 'Account mode is active. Guest lookup stays available in case you need to find an order placed without signing in.';
      return;
    }

    if (state.source === 'session' && payload?.session_attached) {
      dom.accessMeta.textContent = 'Guest session mode is active. Pending guest orders on this browser can be resumed from checkout or cancelled from the detail view.';
      return;
    }

    dom.accessMeta.textContent = 'No member token or guest session order was found. Use the lookup form with your order code and checkout contact information.';
  }

  function renderLoadingState() {
    dom.body.innerHTML = `
      <div class="detail-state-card detail-state-card-compact orders-empty-state">
        <div>
          <strong>Loading orders...</strong>
          <div>Please wait while we synchronize the latest order state.</div>
        </div>
      </div>
    `;
  }

  function renderEmptyState(title, message) {
    dom.body.innerHTML = `
      <div class="detail-state-card detail-state-card-compact orders-empty-state">
        <div>
          <strong>${escapeHtml(title)}</strong>
          <div>${escapeHtml(message)}</div>
        </div>
      </div>
    `;
  }

  function renderOrders() {
    const availableOrders = visibleOrders();
    const matcher = FILTERS[state.currentFilter] || FILTERS.all;
    const filtered = availableOrders.filter(matcher);

    if (!availableOrders.length) {
      if (state.source === 'member') {
        renderEmptyState('No account orders found.', 'Place a shop order while signed in and it will appear here.');
      } else if (state.source === 'session') {
        renderEmptyState('No guest orders found on this browser.', 'You can still use the guest lookup form with your order code and checkout contact details.');
      } else {
        renderEmptyState('No orders available.', 'Use guest lookup or sign in to review your orders.');
      }
      return;
    }

    if (!filtered.length) {
      renderEmptyState('No orders match this filter.', 'Try another filter to see more order history.');
      return;
    }

    dom.body.innerHTML = filtered.map(order => `
      <div class="table-row">
        <div>
          <div class="order-id">${escapeHtml(order.order_code || 'N/A')}</div>
          <div class="order-preview-text">${escapeHtml(orderSourceLabel(order))}</div>
        </div>
        <div class="order-preview-stack">
          ${(Array.isArray(order.preview_items) ? order.preview_items.slice(0, 2) : []).map(item => previewThumb(item)).join('')}
          <span class="order-preview-text">${escapeHtml(itemSummary(order))}</span>
        </div>
        <span style="font-size:12px;color:var(--text2)">${escapeHtml(formatDateTime(order.order_date))}</span>
        <span style="font-weight:600">${escapeHtml(formatCurrency(order.total_price, order.currency))}</span>
        <span class="ticket-status status-${escapeHtml(statusClass(order.status))}">${escapeHtml(humanizeStatus(order.status))}</span>
        <div class="order-action-stack">
          <button class="btn btn-secondary btn-sm" type="button" data-order-detail="${escapeHtmlAttr(String(order.id || '0'))}">View</button>
        </div>
      </div>
    `).join('');

    dom.body.querySelectorAll('[data-order-detail]').forEach(button => {
      button.addEventListener('click', () => {
        void openOrderDetail(Number(button.dataset.orderDetail || 0));
      });
    });
  }

  async function openOrderDetail(orderId) {
    if (!Number.isInteger(orderId) || orderId <= 0) {
      return;
    }

    const isLookupResult = parsePositiveInteger(state.lookupOrder?.id) === orderId && state.lookupOrder;
    const setStatus = isLookupResult ? setLookupStatus : setAccessStatus;

    try {
      if (isLookupResult) {
        state.modalOrder = state.lookupOrder;
        state.modalAccess = {
          source: 'lookup',
          credentials: state.lookupCredentials || {}
        };
        renderModal(state.lookupOrder);
        setLookupStatus('Guest order detail loaded.', false);
        return;
      }

      setStatus('Loading order detail...', false);
      const path = state.source === 'member'
        ? `/api/me/shop-orders/${orderId}`
        : `/api/shop/orders/session/${orderId}`;
      const payload = await fetchJson(path);
      const data = payload?.data || payload || {};
      const order = data.order || null;
      if (!order) {
        throw new Error('Order detail is unavailable.');
      }

      state.modalOrder = order;
      state.modalAccess = {
        source: data.source || state.source,
        orderId: orderId
      };
      renderModal(order);
      setStatus('Order detail loaded.', false);
    } catch (error) {
      setStatus(error.message || 'Failed to load order detail.', true);
      toast('!', 'Order detail', error.message || 'Unable to load order detail.');
    }
  }

  async function lookupGuestOrder() {
    if (state.isLookingUp) {
      return;
    }

    const credentials = {
      order_code: String(dom.lookupCode?.value || '').trim(),
      contact_email: String(dom.lookupEmail?.value || '').trim(),
      contact_phone: String(dom.lookupPhone?.value || '').trim()
    };

    if (!credentials.order_code) {
      setLookupStatus('Please enter an order code.', true);
      return;
    }

    state.isLookingUp = true;
    try {
      setLookupStatus('Looking up guest order...', false);
      const payload = await fetchJson('/api/shop/orders/lookup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: credentials
      });
      const data = payload?.data || payload || {};
      const order = data.order || null;
      if (!order) {
        throw new Error('Guest order detail is unavailable.');
      }

      state.modalOrder = order;
      state.modalAccess = {
        source: 'lookup',
        credentials: credentials
      };
      state.lookupOrder = order;
      state.lookupCredentials = credentials;
      activateFilter('all');
      renderOrders();
      renderModal(order);
      setLookupStatus(`Guest order ${order.order_code || ''} loaded.`, false);
      toast('+', 'Guest order found', `Order ${order.order_code || ''} is ready to review.`);
    } catch (error) {
      setLookupStatus(error.message || 'Guest lookup failed.', true);
      // Removed redundant toast to match user's UI preference if they find it "absurd"
      // But actually the red text in the panel is usually enough for local errors
    } finally {
      state.isLookingUp = false;
    }
  }

  function renderModal(order) {
    if (!dom.modalContent || !dom.modal) {
      return;
    }

    const items = Array.isArray(order.items) ? order.items : [];
    const countdownText = Number(order.expires_in_seconds || 0) > 0
      ? `${Math.ceil(Number(order.expires_in_seconds || 0) / 60)} minute(s) remaining before pending payment expiry.`
      : 'No pending payment countdown is active for this order.';

    dom.modalContent.innerHTML = `
      <div class="order-detail-layout">
        <div class="order-detail-top">
          <div>
            <div class="summary-title" style="margin-bottom:6px;">${escapeHtml(order.order_code || 'Order detail')}</div>
            <div class="lookup-help">${escapeHtml(formatDateTime(order.order_date))} • ${escapeHtml(orderSourceLabel(order))}</div>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start;">
            <span class="ticket-status status-${escapeHtml(statusClass(order.status))}">${escapeHtml(humanizeStatus(order.status))}</span>
            <span class="ticket-status status-${escapeHtml(statusClass(order.payment_status))}">${escapeHtml(humanizeStatus(order.payment_status || 'pending'))}</span>
          </div>
        </div>

        <div class="order-detail-section">
          <h4>Fulfillment & Payment</h4>
          <div class="order-detail-kv">
            ${kv('Fulfillment', humanizeStatus(order.fulfillment_method || 'pickup'))}
            ${kv('Payment Method', humanizeStatus(order.payment_method || 'unknown'))}
            ${kv('Total', formatCurrency(order.total_price, order.currency))}
            ${kv('Shipping', order.shipping_summary || 'Pickup at cinema counter')}
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

        <div class="order-detail-section">
          <h4>Items</h4>
          <div class="order-detail-items">
            ${items.length ? items.map(item => `
              <div class="order-detail-line">
                <div class="order-detail-media">${item.primary_image_url ? `<img src="${escapeHtmlAttr(item.primary_image_url)}" alt="${escapeHtmlAttr(item.primary_image_alt || item.product_name || 'Product')}" loading="lazy" onerror="this.remove()">` : ''}</div>
                <div>
                  <div style="font-weight:700;">${escapeHtml(item.product_name || 'Product')}</div>
                  <div class="lookup-help">${escapeHtml(item.product_sku || 'No SKU')} • Qty ${Number(item.quantity || 0)}</div>
                </div>
                <div class="order-detail-total">${escapeHtml(formatCurrency(item.line_total, item.currency || order.currency))}</div>
              </div>
            `).join('') : '<div class="lookup-help">No order lines were found.</div>'}
          </div>
        </div>

        <div class="order-detail-actions">
          ${order.requires_payment_resume && order.redirect_url ? `<a class="btn btn-secondary" href="${escapeHtmlAttr(order.redirect_url)}">Continue Payment</a>` : ''}
          ${order.can_cancel ? '<button class="btn btn-primary" id="shopOrderCancelBtn" type="button">Cancel Order</button>' : ''}
          <a class="btn btn-secondary" href="${escapeHtmlAttr(appUrl('/shop'))}">Continue Shopping</a>
        </div>
      </div>
    `;

    dom.modal.classList.add('open');
    document.getElementById('shopOrderCancelBtn')?.addEventListener('click', () => {
      void cancelCurrentOrder();
    });
  }

  async function cancelCurrentOrder() {
    if (state.isCancelling || !state.modalOrder || !state.modalAccess) {
      return;
    }

    const orderCode = state.modalOrder.order_code || 'this order';
    const confirmed = typeof window.confirm === 'function'
      ? window.confirm(`Cancel ${orderCode}? This is only available while the order is still unpaid and pending.`)
      : true;
    if (!confirmed) {
      return;
    }

    state.isCancelling = true;
    const isLookup = state.modalAccess.source === 'lookup';
    const setStatus = isLookup ? setLookupStatus : setAccessStatus;

    try {
      setStatus(`Cancelling ${orderCode}...`, false);
      let payload;

      if (state.modalAccess.source === 'member') {
        payload = await fetchJson(`/api/me/shop-orders/${Number(state.modalAccess.orderId || 0)}/cancel`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: {}
        });
      } else if (state.modalAccess.source === 'session') {
        payload = await fetchJson(`/api/shop/orders/session/${Number(state.modalAccess.orderId || 0)}/cancel`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: {}
        });
      } else {
        payload = await fetchJson('/api/shop/orders/lookup/cancel', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: state.modalAccess.credentials || {}
        });
      }

      const data = payload?.data || payload || {};
      const order = data.order || null;
      if (!order) {
        throw new Error('Cancellation completed but the updated order payload is missing.');
      }

      if (state.modalAccess.source === 'lookup') {
        state.lookupOrder = order;
      }
      state.modalOrder = order;
      renderModal(order);
      setStatus(`Order ${order.order_code || ''} was cancelled successfully.`, false);
      toast('+', 'Order cancelled', `Order ${order.order_code || ''} was cancelled successfully.`);
      await loadPrimaryOrders();
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
      if (!order || typeof order !== 'object') {
        return;
      }

      const key = orderIdentity(order, index);
      if (seen.has(key)) {
        return;
      }

      seen.add(key);
      merged.push(order);
    });

    return merged;
  }

  function orderIdentity(order, fallbackIndex) {
    const id = parsePositiveInteger(order?.id);
    if (id !== null) {
      return `id:${id}`;
    }

    const orderCode = String(order?.order_code || '').trim().toUpperCase();
    if (orderCode !== '') {
      return `code:${orderCode}`;
    }

    return `fallback:${fallbackIndex}`;
  }

  function maybeFocusLookupForm() {
    if (!state.pendingLookupFocus || !dom.lookupCode) {
      return;
    }

    state.pendingLookupFocus = false;
    replaceQueryParam('lookup', null);

    window.requestAnimationFrame(() => {
      dom.lookupForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });

      try {
        dom.lookupCode.focus({ preventScroll: true });
      } catch (error) {
        dom.lookupCode.focus();
      }
    });
  }

  async function maybeAutoOpenRequestedOrder() {
    const requestedOrderId = state.pendingOpenOrderId;
    if (requestedOrderId === null) {
      return;
    }

    state.pendingOpenOrderId = null;
    replaceQueryParam('open', null);
    await openOrderDetail(requestedOrderId);
  }

  function replaceQueryParam(name, value) {
    if (!window.history?.replaceState) {
      return;
    }

    const url = new URL(window.location.href);
    if (value === null || value === undefined || value === '') {
      url.searchParams.delete(name);
    } else {
      url.searchParams.set(name, String(value));
    }

    window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
  }

  function parsePositiveInteger(value) {
    const parsed = Number(value);

    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
  }

  async function fetchJson(path, options = {}) {
    const headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
    const token = typeof getAuthToken === 'function' ? getAuthToken() : '';
    if (token && !headers.Authorization) {
      headers.Authorization = `Bearer ${token}`;
    }

    const response = await fetch(appUrl(path), {
      method: String(options.method || 'GET').toUpperCase(),
      headers: headers,
      cache: 'no-store',
      credentials: 'same-origin',
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined
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

  function itemSummary(order) {
    const count = Number(order.item_count || 0);
    return `${count} item${count === 1 ? '' : 's'} • ${humanizeStatus(order.payment_method || 'payment')}`;
  }

  function orderSourceLabel(order) {
    return order.is_guest_order ? 'Guest order' : 'Account order';
  }

  function previewThumb(item) {
    if (!item || !item.primary_image_url) {
      return '<div class="order-item-thumb"></div>';
    }

    return `<div class="order-item-thumb"><img src="${escapeHtmlAttr(item.primary_image_url)}" alt="${escapeHtmlAttr(item.primary_image_alt || item.product_name || 'Product')}" loading="lazy" onerror="this.remove()"></div>`;
  }

  function kv(label, value) {
    return `
      <div class="order-detail-kv-item">
        <div class="order-detail-kv-label">${escapeHtml(label)}</div>
        <div class="order-detail-kv-value">${escapeHtml(value || 'N/A')}</div>
      </div>
    `;
  }

  function statusClass(value) {
    const normalized = String(value || '').trim().toLowerCase();
    if (['cancelled', 'expired', 'failed', 'refunded'].includes(normalized)) return 'danger';
    if (['pending', 'processing', 'preparing', 'ready'].includes(normalized)) return 'pending';
    return 'success';
  }

  function humanizeStatus(value) {
    return String(value || '')
      .replace(/_/g, ' ')
      .replace(/\b\w/g, letter => letter.toUpperCase());
  }

  function formatCurrency(amount, currency) {
    const normalizedCurrency = String(currency || 'VND').toUpperCase();
    return new Intl.NumberFormat(normalizedCurrency === 'VND' ? 'vi-VN' : 'en-US', {
      style: 'currency',
      currency: normalizedCurrency,
      maximumFractionDigits: normalizedCurrency === 'VND' ? 0 : 2
    }).format(Number(amount || 0));
  }

  function formatDateTime(value) {
    const normalized = String(value || '').trim();
    if (!normalized) {
      return 'N/A';
    }

    const parsed = new Date(normalized.replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) {
      return normalized;
    }

    return new Intl.DateTimeFormat('vi-VN', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }).format(parsed);
  }

  function setAccessStatus(message, isError) {
    setPanelStatus(dom.accessStatus, message, isError);
  }

  function setLookupStatus(message, isError) {
    setPanelStatus(dom.lookupStatus, message, isError);
  }

  function setPanelStatus(element, message, isError) {
    if (!element) {
      return;
    }

    element.textContent = message;
    element.style.color = isError ? '#fca5a5' : '';
  }

  function toast(icon, title, message) {
    if (typeof showToast === 'function') {
      showToast(icon, title, message);
    }
  }

  function firstErrorMessage(errors, fallback) {
    if (!errors || typeof errors !== 'object') {
      return fallback || 'Request failed.';
    }

    for (const messages of Object.values(errors)) {
      if (Array.isArray(messages) && messages.length > 0) {
        return String(messages[0]);
      }
    }

    return fallback || 'Request failed.';
  }

  function appUrl(path) {
    const basePath = typeof window.APP_BASE_PATH === 'string' ? window.APP_BASE_PATH : '';
    const normalizedPath = String(path || '').startsWith('/') ? path : `/${path}`;
    return `${basePath}${normalizedPath}`;
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeHtmlAttr(value) {
    return escapeHtml(value).replace(/`/g, '&#96;');
  }

  document.addEventListener('DOMContentLoaded', init);
})();
