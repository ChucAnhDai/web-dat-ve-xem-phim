(function shopCartModule() {
  const CART_API_BASE = appUrl('/api/shop/cart');
  const DEFAULT_CURRENCY = String(window.SHOP_RUNTIME_CONFIG?.currency || 'VND');

  const state = {
    cart: createEmptyCart(),
    sync: createEmptySync(),
    loading: false,
    loaded: false,
    statusMessage: 'Connecting to cart',
    statusIsError: false,
    pendingItems: new Set(),
    clearing: false,
    lastSyncMessage: ''
  };

  function createEmptySync() {
    return {
      merged_guest_cart: 0,
      adjusted_items: 0,
      removed_items: 0
    };
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

  function safeEscape(value) {
    if (typeof escapeHtml === 'function') {
      return escapeHtml(value);
    }

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

    const value = Number(amount || 0);
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: currency || DEFAULT_CURRENCY,
      maximumFractionDigits: String(currency || DEFAULT_CURRENCY).toUpperCase() === 'VND' ? 0 : 2
    }).format(Number.isFinite(value) ? value : 0);
  }

  function itemLabel(count) {
    const total = Math.max(0, Number(count || 0));
    return `${total} item${total === 1 ? '' : 's'}`;
  }

  function normalizeCartPayload(payload) {
    const incomingCart = payload?.cart || {};
    const items = Array.isArray(incomingCart.items) ? incomingCart.items : [];

    return {
      cart: {
        id: incomingCart.id ?? null,
        user_id: incomingCart.user_id ?? null,
        currency: String(incomingCart.currency || DEFAULT_CURRENCY),
        status: String(incomingCart.status || 'active'),
        expires_at: incomingCart.expires_at || null,
        line_count: Math.max(0, Number(incomingCart.line_count || 0)),
        item_count: Math.max(0, Number(incomingCart.item_count || 0)),
        subtotal_price: Number(incomingCart.subtotal_price || 0),
        discount_amount: Number(incomingCart.discount_amount || 0),
        fee_amount: Number(incomingCart.fee_amount || 0),
        total_price: Number(incomingCart.total_price || 0),
        is_empty: Boolean(incomingCart.is_empty ?? items.length === 0),
        items: items.map(item => ({
          id: Number(item.id || 0),
          product_id: Number(item.product_id || 0),
          slug: item.slug || null,
          sku: item.sku || null,
          name: String(item.name || 'Product'),
          summary: item.summary || '',
          category_name: item.category_name || 'Shop item',
          quantity: Math.max(1, Number(item.quantity || 1)),
          unit_price: Number(item.unit_price || 0),
          line_total: Number(item.line_total || 0),
          currency: String(item.currency || incomingCart.currency || DEFAULT_CURRENCY),
          stock: Math.max(0, Number(item.stock || 0)),
          stock_state: String(item.stock_state || 'in_stock'),
          track_inventory: Number(item.track_inventory || 0),
          max_quantity_available: Math.max(1, Number(item.max_quantity_available || item.quantity || 1)),
          primary_image_url: item.primary_image_url || '',
          primary_image_alt: item.primary_image_alt || item.name || 'Product'
        }))
      },
      sync: {
        merged_guest_cart: Math.max(0, Number(payload?.sync?.merged_guest_cart || 0)),
        adjusted_items: Math.max(0, Number(payload?.sync?.adjusted_items || 0)),
        removed_items: Math.max(0, Number(payload?.sync?.removed_items || 0))
      }
    };
  }

  function syncMessage(sync) {
    const parts = [];

    if (Number(sync?.merged_guest_cart || 0) > 0) {
      parts.push('Guest cart merged into your account.');
    }
    if (Number(sync?.adjusted_items || 0) > 0) {
      parts.push(`${Number(sync.adjusted_items)} cart item(s) were adjusted to match current price or stock.`);
    }
    if (Number(sync?.removed_items || 0) > 0) {
      parts.push(`${Number(sync.removed_items)} unavailable cart item(s) were removed.`);
    }

    return parts.join(' ');
  }

  function setStatus(message, isError) {
    state.statusMessage = String(message || '');
    state.statusIsError = Boolean(isError);

    const statusEl = document.getElementById('cartRequestStatus');
    if (!statusEl) {
      return;
    }

    statusEl.textContent = state.statusMessage;
    statusEl.style.color = state.statusIsError ? '#fca5a5' : '';
  }

  function setSyncNotice(message) {
    const noticeEl = document.getElementById('cartSyncNotice');
    if (!noticeEl) {
      return;
    }

    noticeEl.textContent = message || 'Your cart is kept in sync across product pages and your account session.';
  }

  function setEmptyState(title, description) {
    const titleEl = document.getElementById('cartEmptyTitle');
    const descriptionEl = document.getElementById('cartEmptyDescription');

    if (titleEl) {
      titleEl.textContent = title || 'Your cart is empty';
    }
    if (descriptionEl) {
      descriptionEl.textContent = description || 'Browse our shop to add items.';
    }
  }

  function renderBadges() {
    const badgeText = String(Math.max(0, Number(state.cart.item_count || 0)));
    const badge = document.getElementById('cartBadge');
    const navBadge = document.getElementById('cartNavBadge');

    if (badge) {
      badge.textContent = badgeText;
    }
    if (navBadge) {
      navBadge.textContent = badgeText;
    }
  }

  function itemAvailabilityCopy(item) {
    if (String(item.stock_state || '') === 'out_of_stock') {
      return 'Currently unavailable';
    }
    if (String(item.stock_state || '') === 'low_stock') {
      return `Only ${item.max_quantity_available} left`;
    }
    if (Number(item.track_inventory || 0) === 1) {
      return `${item.stock} in stock`;
    }

    return 'Available';
  }

  function isPendingItem(productId) {
    return state.pendingItems.has(String(productId));
  }

  function renderCartItemsList(items) {
    return items.map(item => {
      const imageHtml = item.primary_image_url
        ? `<img src="${safeEscape(item.primary_image_url)}" alt="${safeEscape(item.primary_image_alt || item.name)}" loading="lazy">`
        : '<div class="product-img-fallback" style="font-size:20px">Item</div>';
      const canIncrement = !state.clearing && !isPendingItem(item.product_id) && item.quantity < item.max_quantity_available;
      const canDecrement = !state.clearing && !isPendingItem(item.product_id) && item.quantity > 1;
      const canRemove = !state.clearing && !isPendingItem(item.product_id);

      return `
        <div class="cart-item-row">
          <div class="cart-item-product">
            <div class="cart-item-img">${imageHtml}</div>
            <div>
              <div class="cart-item-name">${safeEscape(item.name)}</div>
              <div class="cart-item-cat">${safeEscape(item.category_name || 'Shop item')}</div>
              <div class="cart-item-cat">${safeEscape(itemAvailabilityCopy(item))}</div>
            </div>
          </div>
          <div class="cart-price">${formatAmount(item.unit_price, item.currency)}</div>
          <div class="qty-control">
            <button class="qty-btn" type="button" data-cart-qty="${item.product_id}" data-qty-delta="-1" ${canDecrement ? '' : 'disabled'}>-</button>
            <div class="qty-val">${item.quantity}</div>
            <button class="qty-btn" type="button" data-cart-qty="${item.product_id}" data-qty-delta="1" ${canIncrement ? '' : 'disabled'}>+</button>
          </div>
          <div class="cart-subtotal">${formatAmount(item.line_total, item.currency)}</div>
          <button class="remove-btn" type="button" data-cart-remove="${item.product_id}" ${canRemove ? '' : 'disabled'}>×</button>
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
      subtitleEl.textContent = state.loaded
        ? (state.cart.is_empty ? 'Your cart is empty' : `${itemLabel(state.cart.item_count)} in your cart`)
        : 'Loading your cart...';
    }

    if (!itemsListEl) {
      return;
    }

    const isEmpty = Boolean(state.cart.is_empty || state.cart.items.length === 0);

    if (contentEl) {
      contentEl.style.display = isEmpty ? 'none' : 'grid';
    }
    if (emptyStateEl) {
      emptyStateEl.style.display = isEmpty ? 'flex' : 'none';
    }

    if (isEmpty) {
      itemsListEl.innerHTML = '';
      if (!state.statusIsError) {
        setEmptyState('Your cart is empty', 'Browse our shop to add items.');
      }
    } else {
      itemsListEl.innerHTML = renderCartItemsList(state.cart.items);
    }

    if (subtotalLabelEl) {
      subtotalLabelEl.textContent = `Subtotal (${itemLabel(state.cart.item_count)})`;
    }
    if (subtotalEl) {
      subtotalEl.textContent = formatAmount(state.cart.subtotal_price, state.cart.currency);
    }
    if (discountEl) {
      discountEl.textContent = formatAmount(state.cart.discount_amount, state.cart.currency);
    }
    if (feeEl) {
      feeEl.textContent = formatAmount(state.cart.fee_amount, state.cart.currency);
    }
    if (totalEl) {
      totalEl.textContent = formatAmount(state.cart.total_price, state.cart.currency);
    }
    if (clearButton) {
      clearButton.disabled = state.clearing || isEmpty;
    }
    if (checkoutButton) {
      checkoutButton.disabled = state.clearing || !state.loaded || isEmpty;
    }
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
    state.sync = normalized.sync;
    state.loaded = true;
    state.loading = false;
    state.statusIsError = false;

    const syncNotice = syncMessage(state.sync);
    setSyncNotice(syncNotice);
    setStatus(state.cart.is_empty ? 'Cart is ready' : `${itemLabel(state.cart.item_count)} ready for checkout`, false);
    renderCartPage();

    if (options.showSyncToast && syncNotice && syncNotice !== state.lastSyncMessage && typeof showToast === 'function') {
      state.lastSyncMessage = syncNotice;
      showToast('i', 'Cart synced', syncNotice);
    }
  }

  async function loadCart(options = {}) {
    const showSyncToast = Boolean(options.showSyncToast);

    state.loading = true;
    setStatus('Loading cart...', false);
    renderCartPage();

    try {
      const data = await requestCart('GET');
      applyCartPayload(data, { showSyncToast });
    } catch (error) {
      state.loading = false;
      state.statusIsError = true;
      setStatus(error.message || 'Unable to load cart.', true);

      if (!state.loaded) {
        setEmptyState('Unable to load cart', 'Please refresh the page and try again.');
        const contentEl = document.getElementById('cartContent');
        const emptyStateEl = document.getElementById('cartEmptyState');
        if (contentEl) {
          contentEl.style.display = 'none';
        }
        if (emptyStateEl) {
          emptyStateEl.style.display = 'flex';
        }
      }
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
    return typeof productQty === 'number'
      ? Math.max(1, Number(productQty || 1))
      : 1;
  }

  function markItemPending(productId, isPending) {
    const key = String(productId);
    if (isPending) {
      state.pendingItems.add(key);
    } else {
      state.pendingItems.delete(key);
    }

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

    const existingItem = state.cart.items.find(item => item.product_id === payload.productId);
    if (existingItem && payload.quantity + existingItem.quantity > existingItem.max_quantity_available) {
      if (typeof showToast === 'function') {
        showToast('!', 'Quantity limit', 'Requested quantity exceeds the available stock for this item.');
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
        showToast('+', 'Added to Cart', `${payload.name} has been added!`);
      }
      if (options.navigateToCart) {
        navigateTo('cart');
      }
    } catch (error) {
      if (typeof showToast === 'function') {
        showToast('!', 'Cart update failed', error.message || 'Unable to add this item to the cart.');
      }
    } finally {
      markItemPending(payload.productId, false);
    }
  }

  async function updateCartQuantityRuntime(productId, delta) {
    const normalizedProductId = Number(productId || 0);
    const item = state.cart.items.find(entry => entry.product_id === normalizedProductId);
    if (!item) {
      return;
    }

    const nextQuantity = item.quantity + Number(delta || 0);
    if (nextQuantity < 1) {
      return;
    }
    if (nextQuantity > item.max_quantity_available) {
      if (typeof showToast === 'function') {
        showToast('!', 'Quantity limit', 'Requested quantity exceeds the available stock for this item.');
      }
      return;
    }

    markItemPending(normalizedProductId, true);

    try {
      const data = await requestCart('PUT', `/items/${normalizedProductId}`, {
        quantity: nextQuantity
      });

      applyCartPayload(data, { showSyncToast: true });
    } catch (error) {
      if (typeof showToast === 'function') {
        showToast('!', 'Cart update failed', error.message || 'Unable to update cart quantity.');
      }
    } finally {
      markItemPending(normalizedProductId, false);
    }
  }

  async function removeCartItemRuntime(productId) {
    const normalizedProductId = Number(productId || 0);
    const item = state.cart.items.find(entry => entry.product_id === normalizedProductId);
    if (!item) {
      return;
    }

    markItemPending(normalizedProductId, true);

    try {
      const data = await requestCart('DELETE', `/items/${normalizedProductId}`);
      applyCartPayload(data, { showSyncToast: true });

      if (typeof showToast === 'function') {
        showToast('-', 'Removed', `${item.name} removed from cart.`);
      }
    } catch (error) {
      if (typeof showToast === 'function') {
        showToast('!', 'Cart update failed', error.message || 'Unable to remove this item from the cart.');
      }
    } finally {
      markItemPending(normalizedProductId, false);
    }
  }

  async function clearCartRuntime() {
    if (state.cart.is_empty || state.cart.items.length === 0) {
      if (typeof showToast === 'function') {
        showToast('i', 'Empty Cart', 'Your cart is already empty.');
      }
      return;
    }

    state.clearing = true;
    renderCartPage();

    try {
      const data = await requestCart('DELETE');
      applyCartPayload(data, { showSyncToast: false });

      if (typeof showToast === 'function') {
        showToast('-', 'Cart Cleared', 'All items have been removed.');
      }
    } catch (error) {
      if (typeof showToast === 'function') {
        showToast('!', 'Cart update failed', error.message || 'Unable to clear your cart right now.');
      }
    } finally {
      state.clearing = false;
      renderCartPage();
    }
  }

  function bindCartControls() {
    document.addEventListener('click', event => {
      const qtyButton = event.target.closest('[data-cart-qty]');
      if (qtyButton) {
        event.preventDefault();
        if (qtyButton.disabled) {
          return;
        }

        void updateCartQuantityRuntime(
          Number(qtyButton.dataset.cartQty || 0),
          Number(qtyButton.dataset.qtyDelta || 0)
        );
        return;
      }

      const removeButton = event.target.closest('[data-cart-remove]');
      if (removeButton) {
        event.preventDefault();
        if (removeButton.disabled) {
          return;
        }

        void removeCartItemRuntime(Number(removeButton.dataset.cartRemove || 0));
        return;
      }

      const clearButton = event.target.closest('#cartClearButton');
      if (clearButton) {
        event.preventDefault();
        if (clearButton.disabled) {
          return;
        }

        void clearCartRuntime();
        return;
      }

      const checkoutButton = event.target.closest('#cartCheckoutButton');
      if (checkoutButton) {
        event.preventDefault();
        if (checkoutButton.disabled) {
          return;
        }

        if (typeof showToast === 'function') {
          showToast('i', 'Checkout next', 'Shop checkout will be connected in the next phase after cart is finalized.');
        }
      }
    });

    document.addEventListener('cinemax:auth-changed', () => {
      void loadCart({ showSyncToast: true });
    });
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
          sync: state.sync,
          loaded: state.loaded,
          loading: state.loading
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
