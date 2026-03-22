(function unifiedCartModule() {
  const CART_API_BASE = appUrl('/api/shop/cart');
  const DEFAULT_CURRENCY = String(window.SHOP_RUNTIME_CONFIG?.currency || 'VND');

  const state = {
    cart: createEmptyCart(),
    ticketSelection: createEmptyTicketSelection(),
    summary: createEmptySummary(),
    sync: createEmptySync(),
    loading: false,
    loaded: false,
    statusMessage: 'Connecting to cart',
    statusIsError: false,
    pendingItems: new Set(),
    removingTicket: false,
    clearing: false,
    lastSyncMessage: ''
  };

  function createEmptySync() {
    return { merged_guest_cart: 0, adjusted_items: 0, removed_items: 0 };
  }

  function createEmptyCart() {
    return {
      id: null,
      user_id: null,
      currency: DEFAULT_CURRENCY,
      status: 'active',
      expires_at: null,
      line_count: 0,
      item_count: 0,
      subtotal_price: 0,
      discount_amount: 0,
      fee_amount: 0,
      total_price: 0,
      is_empty: true,
      items: []
    };
  }

  function createEmptyTicketSelection() {
    return {
      is_empty: true,
      showtime_id: null,
      showtime: null,
      seats: [],
      seat_count: 0,
      total_price: 0,
      currency: DEFAULT_CURRENCY,
      hold_expires_at: null
    };
  }

  function createEmptySummary() {
    return {
      currency: DEFAULT_CURRENCY,
      item_count: 0,
      line_count: 0,
      product_item_count: 0,
      ticket_item_count: 0,
      contains_products: false,
      contains_tickets: false,
      is_empty: true,
      subtotal_price: 0,
      discount_amount: 0,
      fee_amount: 0,
      total_price: 0
    };
  }

  function safeEscape(value) {
    if (typeof escapeHtml === 'function') return escapeHtml(value);
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatAmount(amount, currency = DEFAULT_CURRENCY) {
    if (typeof formatShopCurrency === 'function') {
      return formatShopCurrency(amount, currency);
    }

    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: currency || DEFAULT_CURRENCY,
      maximumFractionDigits: String(currency || DEFAULT_CURRENCY).toUpperCase() === 'VND' ? 0 : 2
    }).format(Number(amount || 0));
  }

  function itemLabel(count) {
    const total = Math.max(0, Number(count || 0));
    return `${total} item${total === 1 ? '' : 's'}`;
  }

  function normalizeCartPayload(payload) {
    const incomingCart = payload?.cart || {};
    const incomingSummary = payload?.summary || {};
    const incomingTicket = payload?.ticket_selection || {};

    return {
      cart: {
        ...createEmptyCart(),
        ...incomingCart,
        items: Array.isArray(incomingCart.items) ? incomingCart.items : []
      },
      ticketSelection: {
        ...createEmptyTicketSelection(),
        ...incomingTicket,
        seats: Array.isArray(incomingTicket.seats) ? incomingTicket.seats : []
      },
      summary: {
        ...createEmptySummary(),
        ...incomingSummary
      },
      sync: {
        ...createEmptySync(),
        ...(payload?.sync || {})
      }
    };
  }

  function syncMessage(sync) {
    const parts = [];
    if (Number(sync?.merged_guest_cart || 0) > 0) parts.push('Guest cart merged into your account.');
    if (Number(sync?.adjusted_items || 0) > 0) parts.push(`${Number(sync.adjusted_items)} product item(s) were adjusted to match current stock or price.`);
    if (Number(sync?.removed_items || 0) > 0) parts.push(`${Number(sync.removed_items)} unavailable product item(s) were removed.`);
    return parts.join(' ');
  }

  function ticketNotice() {
    if (state.ticketSelection.is_empty) {
      return '';
    }

    const movieTitle = state.ticketSelection.showtime?.movie_title || 'Movie tickets';
    const seats = (state.ticketSelection.seats || []).map(seat => seat.label).join(', ');
    const expiry = state.ticketSelection.hold_expires_at
      ? ` Hold active until ${formatDateTime(state.ticketSelection.hold_expires_at)}.`
      : '';

    return `${movieTitle} reserved for seats ${seats}.${expiry}`;
  }

  function setStatus(message, isError) {
    state.statusMessage = String(message || '');
    state.statusIsError = Boolean(isError);

    const statusEl = document.getElementById('cartRequestStatus');
    if (!statusEl) return;

    statusEl.textContent = state.statusMessage;
    statusEl.style.color = state.statusIsError ? '#fca5a5' : '';
  }

  function setSyncNotice(message) {
    const noticeEl = document.getElementById('cartSyncNotice');
    if (!noticeEl) return;

    noticeEl.textContent = message || 'Your cart keeps product lines and held tickets synchronized before checkout.';
  }

  function setEmptyState(title, description) {
    const titleEl = document.getElementById('cartEmptyTitle');
    const descriptionEl = document.getElementById('cartEmptyDescription');
    if (titleEl) titleEl.textContent = title || 'Your cart is empty';
    if (descriptionEl) descriptionEl.textContent = description || 'Add products or reserve movie tickets to start checkout.';
  }

  function renderBadges() {
    const badgeText = String(Math.max(0, Number(state.summary.item_count || 0)));
    document.getElementById('cartBadge')?.replaceChildren(document.createTextNode(badgeText));
    document.getElementById('cartNavBadge')?.replaceChildren(document.createTextNode(badgeText));
  }

  function isPendingItem(productId) {
    return state.pendingItems.has(String(productId));
  }

  function renderTicketRow() {
    if (state.ticketSelection.is_empty) {
      return '';
    }

    const showtime = state.ticketSelection.showtime || {};
    const seats = (state.ticketSelection.seats || []).map(seat => seat.label).join(', ');
    const canRemove = !state.removingTicket && !state.clearing;
    const poster = showtime.poster_url
      ? `<img src="${safeEscape(showtime.poster_url)}" alt="${safeEscape(showtime.movie_title || 'Movie poster')}" loading="lazy">`
      : '<div class="product-img-fallback" style="font-size:20px">TIX</div>';

    return `
      <div class="cart-item-row">
        <div class="cart-item-product">
          <div class="cart-item-img">${poster}</div>
          <div>
            <div class="cart-item-name">${safeEscape(showtime.movie_title || 'Movie tickets')}</div>
            <div class="cart-item-cat">${safeEscape([showtime.cinema_name, showtime.room_name].filter(Boolean).join(' - ') || 'Cinema')}</div>
            <div class="cart-item-cat">${safeEscape(seats || 'Selected seats')}</div>
          </div>
        </div>
        <div class="cart-price">${formatAmount(state.ticketSelection.total_price, state.ticketSelection.currency)}</div>
        <div class="qty-control">
          <div class="qty-val">${Number(state.ticketSelection.seat_count || 0)} seats</div>
        </div>
        <div class="cart-subtotal">${formatAmount(state.ticketSelection.total_price, state.ticketSelection.currency)}</div>
        <button class="remove-btn" type="button" data-ticket-remove="current" ${canRemove ? '' : 'disabled'}>&times;</button>
      </div>
    `;
  }

  function renderProductRows() {
    return (state.cart.items || []).map(item => {
      const imageHtml = item.primary_image_url
        ? `<img src="${safeEscape(item.primary_image_url)}" alt="${safeEscape(item.primary_image_alt || item.name || 'Product')}" loading="lazy">`
        : '<div class="product-img-fallback" style="font-size:20px">ITEM</div>';
      const canIncrement = !state.clearing && !isPendingItem(item.product_id) && Number(item.quantity || 0) < Number(item.max_quantity_available || item.quantity || 1);
      const canDecrement = !state.clearing && !isPendingItem(item.product_id) && Number(item.quantity || 0) > 1;
      const canRemove = !state.clearing && !isPendingItem(item.product_id);

      return `
        <div class="cart-item-row">
          <div class="cart-item-product">
            <div class="cart-item-img">${imageHtml}</div>
            <div>
              <div class="cart-item-name">${safeEscape(item.name || 'Product')}</div>
              <div class="cart-item-cat">${safeEscape(item.category_name || 'Shop item')}</div>
              <div class="cart-item-cat">${safeEscape(item.summary || '')}</div>
            </div>
          </div>
          <div class="cart-price">${formatAmount(item.unit_price, item.currency)}</div>
          <div class="qty-control">
            <button class="qty-btn" type="button" data-cart-qty="${item.product_id}" data-qty-delta="-1" ${canDecrement ? '' : 'disabled'}>-</button>
            <div class="qty-val">${Number(item.quantity || 0)}</div>
            <button class="qty-btn" type="button" data-cart-qty="${item.product_id}" data-qty-delta="1" ${canIncrement ? '' : 'disabled'}>+</button>
          </div>
          <div class="cart-subtotal">${formatAmount(item.line_total, item.currency)}</div>
          <button class="remove-btn" type="button" data-cart-remove="${item.product_id}" ${canRemove ? '' : 'disabled'}>&times;</button>
        </div>
      `;
    }).join('');
  }

  function renderCartPage() {
    renderBadges();

    const subtitleEl = document.getElementById('cartSubtitle');
    const contentEl = document.getElementById('cartContent');
    const emptyStateEl = document.getElementById('cartEmptyState');
    const itemsListEl = document.getElementById('cartItemsList');
    const subtotalLabelEl = document.getElementById('summarySubtotalLabel');
    const subtotalEl = document.getElementById('summarySubtotal');
    const discountEl = document.getElementById('summaryDiscount');
    const feeEl = document.getElementById('summaryFee');
    const totalEl = document.getElementById('summaryTotal');
    const clearButton = document.getElementById('cartClearButton');
    const checkoutButton = document.getElementById('cartCheckoutButton');

    if (subtitleEl) {
      if (!state.loaded) {
        subtitleEl.textContent = 'Loading your cart...';
      } else if (state.summary.is_empty) {
        subtitleEl.textContent = 'Your cart is empty';
      } else {
        subtitleEl.textContent = `${itemLabel(state.summary.item_count)} ready for one combined checkout`;
      }
    }

    if (!itemsListEl) return;

    const isEmpty = Boolean(state.summary.is_empty);
    if (contentEl) contentEl.style.display = isEmpty ? 'none' : 'grid';
    if (emptyStateEl) emptyStateEl.style.display = isEmpty ? 'flex' : 'none';

    if (isEmpty) {
      itemsListEl.innerHTML = '';
      if (!state.statusIsError) {
        setEmptyState('Your cart is empty', 'Add products or reserve movie tickets to start checkout.');
      }
    } else {
      itemsListEl.innerHTML = `${renderTicketRow()}${renderProductRows()}`;
    }

    if (subtotalLabelEl) subtotalLabelEl.textContent = `Subtotal (${itemLabel(state.summary.item_count)})`;
    if (subtotalEl) subtotalEl.textContent = formatAmount(state.summary.subtotal_price, state.summary.currency);
    if (discountEl) discountEl.textContent = formatAmount(state.summary.discount_amount, state.summary.currency);
    if (feeEl) feeEl.textContent = formatAmount(state.summary.fee_amount, state.summary.currency);
    if (totalEl) totalEl.textContent = formatAmount(state.summary.total_price, state.summary.currency);
    if (clearButton) clearButton.disabled = state.clearing || isEmpty;
    if (checkoutButton) checkoutButton.disabled = state.clearing || !state.loaded || isEmpty;
  }

  async function requestCart(method, path = '', body = null) {
    const headers = {};
    const options = { method, headers };
    if (body !== null) {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body);
    }
    return fetchJson(`${CART_API_BASE}${path}`, options);
  }

  function applyCartPayload(payload, options = {}) {
    const normalized = normalizeCartPayload(payload);
    state.cart = normalized.cart;
    state.ticketSelection = normalized.ticketSelection;
    state.summary = normalized.summary;
    state.sync = normalized.sync;
    state.loaded = true;
    state.loading = false;
    state.statusIsError = false;

    const messages = [syncMessage(state.sync), ticketNotice()].filter(Boolean).join(' ');
    setSyncNotice(messages);
    setStatus(
      state.summary.is_empty ? 'Cart is ready' : `${itemLabel(state.summary.item_count)} ready for checkout`,
      false
    );
    renderCartPage();

    if (options.showSyncToast && messages && messages !== state.lastSyncMessage && typeof showToast === 'function') {
      state.lastSyncMessage = messages;
      showToast('i', 'Cart synced', messages);
    }
  }

  async function loadCart(options = {}) {
    state.loading = true;
    setStatus('Loading cart...', false);
    renderCartPage();

    try {
      const data = await requestCart('GET');
      applyCartPayload(data, options);
    } catch (error) {
      state.loading = false;
      state.statusIsError = true;
      setStatus(error.message || 'Unable to load cart.', true);
      setEmptyState('Unable to load cart', 'Please refresh the page and try again.');
      renderCartPage();
    }
  }

  async function removeTicketSelectionRuntime() {
    if (state.ticketSelection.is_empty || state.removingTicket) {
      return;
    }

    state.removingTicket = true;
    renderCartPage();

    try {
      const data = await requestCart('DELETE', '/tickets/current');
      applyCartPayload(data, { showSyncToast: false });
      if (typeof showToast === 'function') {
        showToast('-', 'Tickets removed', 'Held movie tickets were removed from the cart.');
      }
    } catch (error) {
      if (typeof showToast === 'function') {
        showToast('!', 'Cart update failed', error.message || 'Unable to remove the held tickets.');
      }
    } finally {
      state.removingTicket = false;
      renderCartPage();
    }
  }

  function normalizeAddPayload(input, quantity = 1) {
    if (typeof input === 'object' && input !== null) {
      return {
        productId: Number(input.productId || input.product_id || 0),
        name: String(input.name || 'Product'),
        quantity: Math.max(1, Number(input.quantity || quantity || 1))
      };
    }

    return {
      productId: 0,
      name: String(input || 'Product'),
      quantity: Math.max(1, Number(quantity || 1))
    };
  }

  function currentDetailQuantity() {
    return typeof productQty === 'number' ? Math.max(1, Number(productQty || 1)) : 1;
  }

  function markItemPending(productId, isPending) {
    const key = String(productId);
    if (isPending) state.pendingItems.add(key);
    else state.pendingItems.delete(key);
    renderCartPage();
  }

  async function addToCartProductRuntime(input, quantity = 1, options = {}) {
    const payload = normalizeAddPayload(input, quantity);
    if (payload.productId <= 0) {
      if (typeof showToast === 'function') {
        showToast('!', 'Cart error', 'This product is not ready to be added to the cart yet.');
      }
      return;
    }

    markItemPending(payload.productId, true);
    try {
      const data = await requestCart('POST', '/items', {
        product_id: payload.productId,
        quantity: payload.quantity
      });
      applyCartPayload(data, { showSyncToast: true });
      if (typeof showToast === 'function') {
        showToast('+', 'Added to Cart', `${payload.name} has been added.`);
      }
      if (options.navigateToCart) navigateTo('cart');
    } catch (error) {
      if (typeof showToast === 'function') {
        showToast('!', 'Cart update failed', error.message || 'Unable to add this item to the cart.');
      }
    } finally {
      markItemPending(payload.productId, false);
    }
  }

  async function updateCartQuantityRuntime(productId, delta) {
    const item = (state.cart.items || []).find(entry => Number(entry.product_id) === Number(productId));
    if (!item) return;

    const nextQuantity = Number(item.quantity || 0) + Number(delta || 0);
    if (nextQuantity < 1) return;

    markItemPending(productId, true);
    try {
      const data = await requestCart('PUT', `/items/${Number(productId)}`, { quantity: nextQuantity });
      applyCartPayload(data, { showSyncToast: true });
    } catch (error) {
      if (typeof showToast === 'function') {
        showToast('!', 'Cart update failed', error.message || 'Unable to update cart quantity.');
      }
    } finally {
      markItemPending(productId, false);
    }
  }

  async function removeCartItemRuntime(productId) {
    const item = (state.cart.items || []).find(entry => Number(entry.product_id) === Number(productId));
    if (!item) return;

    markItemPending(productId, true);
    try {
      const data = await requestCart('DELETE', `/items/${Number(productId)}`);
      applyCartPayload(data, { showSyncToast: true });
      if (typeof showToast === 'function') {
        showToast('-', 'Removed', `${item.name || 'Item'} removed from cart.`);
      }
    } catch (error) {
      if (typeof showToast === 'function') {
        showToast('!', 'Cart update failed', error.message || 'Unable to remove this item.');
      }
    } finally {
      markItemPending(productId, false);
    }
  }

  async function clearCartRuntime() {
    if (state.summary.is_empty || state.clearing) {
      return;
    }

    state.clearing = true;
    renderCartPage();

    try {
      const data = await requestCart('DELETE');
      applyCartPayload(data, { showSyncToast: false });
      if (typeof showToast === 'function') {
        showToast('-', 'Cart cleared', 'Products and held tickets were removed from the cart.');
      }
    } catch (error) {
      if (typeof showToast === 'function') {
        showToast('!', 'Cart update failed', error.message || 'Unable to clear the cart right now.');
      }
    } finally {
      state.clearing = false;
      renderCartPage();
    }
  }

  function bindCartControls() {
    document.addEventListener('click', event => {
      const qtyButton = event.target.closest('[data-cart-qty]');
      if (qtyButton && !qtyButton.disabled) {
        event.preventDefault();
        void updateCartQuantityRuntime(Number(qtyButton.dataset.cartQty || 0), Number(qtyButton.dataset.qtyDelta || 0));
        return;
      }

      const removeButton = event.target.closest('[data-cart-remove]');
      if (removeButton && !removeButton.disabled) {
        event.preventDefault();
        void removeCartItemRuntime(Number(removeButton.dataset.cartRemove || 0));
        return;
      }

      const ticketRemoveButton = event.target.closest('[data-ticket-remove]');
      if (ticketRemoveButton && !ticketRemoveButton.disabled) {
        event.preventDefault();
        void removeTicketSelectionRuntime();
        return;
      }

      const clearButton = event.target.closest('#cartClearButton');
      if (clearButton && !clearButton.disabled) {
        event.preventDefault();
        void clearCartRuntime();
        return;
      }

      const checkoutButton = event.target.closest('#cartCheckoutButton');
      if (checkoutButton && !checkoutButton.disabled) {
        event.preventDefault();
        navigateTo('shop-checkout');
      }
    });

    document.addEventListener('cinemax:auth-changed', () => {
      void loadCart({ showSyncToast: true });
    });
  }

  async function fetchJson(path, options = {}) {
    const headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
    const token = typeof getAuthToken === 'function' ? getAuthToken() : '';
    if (token && !headers.Authorization) headers.Authorization = `Bearer ${token}`;

    const response = await fetch(appUrl(path), {
      method: String(options.method || 'GET').toUpperCase(),
      headers,
      cache: 'no-store',
      credentials: 'same-origin',
      body: options.body
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(firstErrorMessage(payload?.errors, payload?.message || 'Request failed.'));
    }

    return payload?.data || {};
  }

  function formatDateTime(value) {
    const normalized = String(value || '').trim().replace(' ', 'T');
    if (!normalized) return 'N/A';

    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) return String(value);

    return new Intl.DateTimeFormat('vi-VN', {
      hour: '2-digit',
      minute: '2-digit',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    }).format(parsed);
  }

  function firstErrorMessage(errors, fallback) {
    if (!errors || typeof errors !== 'object') return fallback;
    for (const messages of Object.values(errors)) {
      if (Array.isArray(messages) && messages.length > 0) return String(messages[0]);
    }
    return fallback;
  }

  function appUrl(path) {
    const basePath = typeof window.APP_BASE_PATH === 'string' ? window.APP_BASE_PATH : '';
    const normalizedPath = String(path || '').startsWith('/') ? path : `/${path}`;
    return `${basePath}${normalizedPath}`;
  }

  Object.assign(window, {
    updateCartBadges: renderBadges,
    renderCartItems: renderCartPage,
    changeCartQty(productId, delta) {
      void updateCartQuantityRuntime(productId, delta);
    },
    removeCartItem(productId) {
      void removeCartItemRuntime(productId);
    },
    clearCart() {
      void clearCartRuntime();
    },
    addToCartProduct(input, quantity = 1) {
      return addToCartProductRuntime(input, quantity);
    },
    addToCart(input = null) {
      return addToCartProductRuntime(input || {
        productId: Number(shopCatalogState?.currentProduct?.id || 0),
        name: shopCatalogState?.currentProduct?.name || 'Product',
        quantity: currentDetailQuantity()
      });
    },
    buyNow(input = null) {
      return addToCartProductRuntime(input || {
        productId: Number(shopCatalogState?.currentProduct?.id || 0),
        name: shopCatalogState?.currentProduct?.name || 'Product',
        quantity: currentDetailQuantity()
      }, 1, { navigateToCart: true });
    },
    shopCartRuntime: {
      refresh() {
        return loadCart({ showSyncToast: true });
      },
      snapshot() {
        return JSON.parse(JSON.stringify({
          cart: state.cart,
          ticketSelection: state.ticketSelection,
          summary: state.summary,
          sync: state.sync,
          loaded: state.loaded
        }));
      }
    }
  });

  bindCartControls();
  document.addEventListener('DOMContentLoaded', () => {
    renderCartPage();
    void loadCart({ showSyncToast: false });
  });
})();
