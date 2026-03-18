(function () {
  const state = {
    orders: [],
    ordersMeta: { page: 1, per_page: 20, total: 0, total_pages: 1 },
    orderFilters: {
      search: '',
      status: '',
      payment_method: '',
      fulfillment_method: '',
    },
    orderDetails: [],
    detailMeta: { page: 1, per_page: 20, total: 0, total_pages: 1 },
    detailFilters: {
      search: '',
      status: '',
      fulfillment_method: '',
    },
    queue: [],
    loadingOrders: false,
    loadingDetails: false,
  };

  let orderSearchTimer = null;
  let detailSearchTimer = null;

  function initShopOrdersAdmin() {
    const hasOrderList = Boolean(document.getElementById('shopOrdersBody'));
    const hasDetailList = Boolean(document.getElementById('shopOrderDetailsBody'));

    if (!hasOrderList && !hasDetailList) {
      return;
    }

    window.openShopOrderDetail = openShopOrderDetail;

    if (hasOrderList) {
      bindOrderFilters();
      window.handleShopOrderSectionAction = () => loadShopOrders(true);
      window.loadShopOrdersPage = loadShopOrdersPage;
      void loadShopOrders(true);
    }

    if (hasDetailList) {
      bindDetailFilters();
      window.handleShopOrderSectionAction = () => loadOrderDetails(true);
      window.loadShopOrderDetailsPage = loadShopOrderDetailsPage;
      void loadOrderDetails(true);
    }
  }

  function bindOrderFilters() {
    document.getElementById('shopOrderSearch')?.addEventListener('input', event => {
      window.clearTimeout(orderSearchTimer);
      orderSearchTimer = window.setTimeout(() => {
        state.orderFilters.search = String(event.target.value || '').trim();
        state.ordersMeta.page = 1;
        void loadShopOrders();
      }, 180);
    });

    document.getElementById('shopOrderStatus')?.addEventListener('change', event => {
      state.orderFilters.status = normalizeFilterValue(event.target.value);
      state.ordersMeta.page = 1;
      void loadShopOrders();
    });

    document.getElementById('shopOrderPayment')?.addEventListener('change', event => {
      state.orderFilters.payment_method = normalizeFilterValue(event.target.value);
      state.ordersMeta.page = 1;
      void loadShopOrders();
    });

    document.getElementById('shopOrderFulfillment')?.addEventListener('change', event => {
      state.orderFilters.fulfillment_method = normalizeFilterValue(event.target.value);
      state.ordersMeta.page = 1;
      void loadShopOrders();
    });
  }

  function bindDetailFilters() {
    document.getElementById('shopOrderDetailSearch')?.addEventListener('input', event => {
      window.clearTimeout(detailSearchTimer);
      detailSearchTimer = window.setTimeout(() => {
        state.detailFilters.search = String(event.target.value || '').trim();
        state.detailMeta.page = 1;
        void loadOrderDetails();
      }, 180);
    });

    document.getElementById('shopOrderDetailStatus')?.addEventListener('change', event => {
      state.detailFilters.status = normalizeFilterValue(event.target.value);
      state.detailMeta.page = 1;
      void loadOrderDetails();
    });

    document.getElementById('shopOrderDetailFulfillment')?.addEventListener('change', event => {
      state.detailFilters.fulfillment_method = normalizeFilterValue(event.target.value);
      state.detailMeta.page = 1;
      void loadOrderDetails();
    });
  }

  async function loadShopOrders(showRefreshToast = false) {
    if (state.loadingOrders) {
      return;
    }

    state.loadingOrders = true;
    renderOrderLoadingState();

    try {
      const payload = await adminApiRequest('/api/admin/shop-orders', {
        query: {
          search: state.orderFilters.search,
          status: state.orderFilters.status,
          payment_method: state.orderFilters.payment_method,
          fulfillment_method: state.orderFilters.fulfillment_method,
          page: state.ordersMeta.page,
          per_page: state.ordersMeta.per_page,
        },
        useStoredToken: true,
      });

      const data = payload?.data || {};
      state.orders = Array.isArray(data.items) ? data.items : [];
      state.ordersMeta = data.meta || state.ordersMeta;

      renderShopOrders(state.orders);
      renderOrderSummary(data.summary || {});
      renderOrderPagination(state.ordersMeta);

      if (showRefreshToast) {
        showToast('Shop orders synced.', 'success');
      }
    } catch (error) {
      renderOrderErrorState(errorMessageFromException(error, 'Failed to load shop orders.'));
      renderOrderSummary({});
      renderOrderPagination({ page: 1, total_pages: 1, total: 0 });
    } finally {
      state.loadingOrders = false;
    }
  }

  async function loadOrderDetails(showRefreshToast = false) {
    if (state.loadingDetails) {
      return;
    }

    state.loadingDetails = true;
    renderDetailLoadingState();
    renderQueueLoadingState();

    try {
      const payload = await adminApiRequest('/api/admin/order-details', {
        query: {
          search: state.detailFilters.search,
          status: state.detailFilters.status,
          fulfillment_method: state.detailFilters.fulfillment_method,
          page: state.detailMeta.page,
          per_page: state.detailMeta.per_page,
        },
        useStoredToken: true,
      });

      const data = payload?.data || {};
      state.orderDetails = Array.isArray(data.items) ? data.items : [];
      state.detailMeta = data.meta || state.detailMeta;
      state.queue = Array.isArray(data.queue) ? data.queue : [];

      renderDetailRows(state.orderDetails);
      renderDetailPagination(state.detailMeta);
      renderQueue(state.queue);
      syncNavBadge(data.summary?.total_orders || state.queue.length || state.detailMeta.total || 0);

      if (showRefreshToast) {
        showToast('Order details synced.', 'success');
      }
    } catch (error) {
      renderDetailErrorState(errorMessageFromException(error, 'Failed to load order details.'));
      renderDetailPagination({ page: 1, total_pages: 1, total: 0 });
      renderQueue([]);
    } finally {
      state.loadingDetails = false;
    }
  }

  function renderOrderLoadingState() {
    const body = document.getElementById('shopOrdersBody');
    const count = document.getElementById('shopOrderCount');

    if (body) {
      body.innerHTML = '<tr><td colspan="10" class="td-muted" style="text-align:center;padding:28px 16px;">Loading shop orders...</td></tr>';
    }
    if (count) {
      count.textContent = 'Loading...';
    }
  }

  function renderOrderErrorState(message) {
    const body = document.getElementById('shopOrdersBody');
    const count = document.getElementById('shopOrderCount');

    if (body) {
      body.innerHTML = `<tr><td colspan="10" class="td-muted" style="text-align:center;padding:28px 16px;">${escapeHtml(message)}</td></tr>`;
    }
    if (count) {
      count.textContent = '0 orders';
    }
    syncNavBadge(0);
  }

  function renderShopOrders(items) {
    const body = document.getElementById('shopOrdersBody');
    const count = document.getElementById('shopOrderCount');

    if (!body) {
      return;
    }

    if (!Array.isArray(items) || items.length === 0) {
      body.innerHTML = '<tr><td colspan="10" class="td-muted" style="text-align:center;padding:28px 16px;">No matching shop orders found.</td></tr>';
      if (count) {
        count.textContent = '0 orders';
      }
      syncNavBadge(0);
      return;
    }

    body.innerHTML = items.map(order => `
      <tr>
        <td class="td-id">
          <div>${escapeHtml(order.order_code || '')}</div>
          <div class="td-muted">${escapeHtml(order.is_guest_order ? 'Guest order' : `User #${order.user_id || ''}`)}</div>
        </td>
        <td>
          <div class="td-bold">${escapeHtml(order.contact_name || 'Guest')}</div>
          <div class="td-muted">${escapeHtml(order.customer_ref || '')}</div>
        </td>
        <td>
          <div class="td-bold">${escapeHtml(String(order.item_count || 0))} item${Number(order.item_count || 0) === 1 ? '' : 's'}</div>
          <div class="td-muted">${escapeHtml(formatCurrency(order.subtotal_price, order.currency))} subtotal</div>
        </td>
        <td class="td-bold">${escapeHtml(formatCurrency(order.total_price, order.currency))}</td>
        <td>
          <div>${statusBadge(order.payment_status || 'pending')}</div>
          <div class="td-muted" style="margin-top:4px;">${escapeHtml(humanizeStatus(order.payment_method || 'unknown'))}</div>
        </td>
        <td><span class="badge gray">${escapeHtml(humanizeStatus(order.fulfillment_method || 'pickup'))}</span></td>
        <td>${statusBadge(order.status)}</td>
        <td class="td-muted" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(order.shipping_address_summary || 'N/A')}</td>
        <td class="td-muted">${escapeHtml(formatDateTime(order.order_date))}</td>
        <td>
          <div class="actions-row">
            <button class="action-btn view" title="View" onclick="openShopOrderDetail(${Number(order.id || 0)})">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `).join('');

    if (count) {
      count.textContent = `${state.ordersMeta.total || items.length} orders`;
    }
    syncNavBadge(state.ordersMeta.total || items.length);
  }

  function renderOrderSummary(summary) {
    setText('shopOrdersTotalStat', String(summary.total_orders || 0));
    setText('shopOrdersPendingStat', String(summary.pending_orders || 0));
    setText('shopOrdersActiveStat', String(summary.active_orders || 0));
    setText('shopOrdersCompletedStat', String(summary.completed_orders || 0));
    setText('shopOrdersIssueStat', String(summary.issue_orders || 0));
    syncNavBadge(summary.total_orders || 0);
  }

  function renderOrderPagination(meta) {
    const container = document.getElementById('shopOrdersPagination');
    if (!container) {
      return;
    }

    container.innerHTML = paginationTemplate(meta, 'loadShopOrdersPage', 'shop orders');
  }

  function loadShopOrdersPage(page) {
    state.ordersMeta.page = Number(page || 1);
    void loadShopOrders();
  }

  function renderQueueLoadingState() {
    const container = document.getElementById('shopOrderQueue');
    if (container) {
      container.innerHTML = '<div class="td-muted" style="padding:12px 0;">Loading fulfillment queue...</div>';
    }
  }

  function renderQueue(items) {
    const container = document.getElementById('shopOrderQueue');
    if (!container) {
      return;
    }

    if (!Array.isArray(items) || items.length === 0) {
      container.innerHTML = '<div class="td-muted">No active fulfillment work is waiting right now.</div>';
      return;
    }

    container.innerHTML = items.map(order => `
      <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
        <div>
          <div class="td-bold">${escapeHtml(order.order_code || '')} - ${escapeHtml(order.contact_name || 'Guest')}</div>
          <div class="td-muted">${escapeHtml(order.shipping_address_summary || '')}</div>
          <div class="td-muted">${escapeHtml(String(order.item_count || 0))} items · ${escapeHtml(humanizeStatus(order.payment_method || 'unknown'))}</div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
          <div>${statusBadge(order.status)}</div>
          <button class="btn btn-ghost btn-sm" type="button" onclick="openShopOrderDetail(${Number(order.id || 0)})">View</button>
        </div>
      </div>
    `).join('');
  }

  function renderDetailLoadingState() {
    const body = document.getElementById('shopOrderDetailsBody');
    const count = document.getElementById('shopOrderDetailCount');

    if (body) {
      body.innerHTML = '<tr><td colspan="10" class="td-muted" style="text-align:center;padding:28px 16px;">Loading order detail rows...</td></tr>';
    }
    if (count) {
      count.textContent = 'Loading...';
    }
  }

  function renderDetailErrorState(message) {
    const body = document.getElementById('shopOrderDetailsBody');
    const count = document.getElementById('shopOrderDetailCount');

    if (body) {
      body.innerHTML = `<tr><td colspan="10" class="td-muted" style="text-align:center;padding:28px 16px;">${escapeHtml(message)}</td></tr>`;
    }
    if (count) {
      count.textContent = '0 detail rows';
    }
  }

  function renderDetailRows(items) {
    const body = document.getElementById('shopOrderDetailsBody');
    const count = document.getElementById('shopOrderDetailCount');

    if (!body) {
      return;
    }

    if (!Array.isArray(items) || items.length === 0) {
      body.innerHTML = '<tr><td colspan="10" class="td-muted" style="text-align:center;padding:28px 16px;">No matching order detail rows found.</td></tr>';
      if (count) {
        count.textContent = '0 detail rows';
      }
      return;
    }

    body.innerHTML = items.map(row => `
      <tr>
        <td class="td-id">
          <div>${escapeHtml(row.order_code || '')}</div>
          <div class="td-muted">${escapeHtml(formatDateTime(row.order_date))}</div>
        </td>
        <td>
          <div class="td-bold">${escapeHtml(row.customer_name || 'Guest')}</div>
          <div class="td-muted">${escapeHtml(row.customer_ref || '')}</div>
        </td>
        <td>
          <div class="td-bold">${escapeHtml(row.product_name || 'Product')}</div>
          <div class="td-muted">${escapeHtml(row.product_sku || 'N/A')}</div>
        </td>
        <td class="td-bold">${escapeHtml(String(row.quantity || 0))}</td>
        <td class="td-bold">${escapeHtml(formatCurrency(row.unit_price, row.currency))}</td>
        <td class="td-bold">${escapeHtml(formatCurrency(row.line_total, row.currency))}</td>
        <td>
          <div><span class="badge gray">${escapeHtml(humanizeStatus(row.fulfillment_method || 'pickup'))}</span></div>
          <div class="td-muted" style="margin-top:4px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(row.shipping_address_summary || '')}</div>
        </td>
        <td>${statusBadge(row.order_status)}</td>
        <td>
          <div>${statusBadge(row.payment_status || 'pending')}</div>
          <div class="td-muted" style="margin-top:4px;">${escapeHtml(humanizeStatus(row.payment_method || 'unknown'))}</div>
        </td>
        <td>
          <div class="actions-row">
            <button class="action-btn view" title="View" onclick="openShopOrderDetail(${Number(row.order_id || 0)})">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `).join('');

    if (count) {
      count.textContent = `${state.detailMeta.total || items.length} detail rows`;
    }
  }

  function renderDetailPagination(meta) {
    const container = document.getElementById('shopOrderDetailsPagination');
    if (!container) {
      return;
    }

    container.innerHTML = paginationTemplate(meta, 'loadShopOrderDetailsPage', 'detail rows');
  }

  function loadShopOrderDetailsPage(page) {
    state.detailMeta.page = Number(page || 1);
    void loadOrderDetails();
  }

  async function openShopOrderDetail(orderId) {
    if (!orderId) {
      return;
    }

    try {
      const payload = await adminApiRequest(`/api/admin/shop-orders/${encodeURIComponent(orderId)}`, {
        useStoredToken: true,
      });
      const order = payload?.data || {};
      const canUpdate = Array.isArray(order.allowed_next_statuses) && order.allowed_next_statuses.length > 0;

      openModal('Shop Order Detail', orderDetailBody(order), {
        submitLabel: canUpdate ? 'Update Status' : 'Close',
        cancelLabel: 'Close',
        description: 'Live data synced from shop_orders, order_details, and payments.',
        note: canUpdate
          ? 'Only valid lifecycle transitions are offered here to keep payment and inventory state consistent.'
          : 'This order is already terminal or waiting on a different subsystem, so no admin transition is available here.',
        busyLabel: 'Updating...',
        onSave: canUpdate ? async () => {
          const container = document.getElementById('shopOrderDetailForm');
          const nextStatus = String(document.getElementById('shopOrderNextStatus')?.value || '').trim();

          clearFormErrors(container);
          if (!nextStatus) {
            applyFormErrors(container, { status: ['Please choose the next order status.'] });
            throw new Error('Please choose the next order status.');
          }

          try {
            await adminApiRequest(`/api/admin/shop-orders/${encodeURIComponent(orderId)}/status`, {
              method: 'PUT',
              body: { status: nextStatus },
              useStoredToken: true,
            });
          } catch (error) {
            if (error?.errors) {
              applyFormErrors(container, error.errors);
            }
            throw error;
          }

          closeModal();
          showToast('Shop order status updated.', 'success');
          await refreshVisibleSections();
        } : () => {
          closeModal();
        },
      });
    } catch (error) {
      showToast(errorMessageFromException(error, 'Failed to load shop order detail.'), 'error');
    }
  }

  async function refreshVisibleSections() {
    const tasks = [];
    if (document.getElementById('shopOrdersBody')) {
      tasks.push(loadShopOrders());
    }
    if (document.getElementById('shopOrderDetailsBody')) {
      tasks.push(loadOrderDetails());
    }

    await Promise.all(tasks);
  }

  function orderDetailBody(order) {
    const items = Array.isArray(order.items) ? order.items : [];
    const nextStatuses = Array.isArray(order.allowed_next_statuses) ? order.allowed_next_statuses : [];
    const nextStatusOptions = nextStatuses.map(status => ({
      value: status,
      label: humanizeStatus(status),
    }));
    const redirectAction = order.redirect_url && String(order.payment_status || '').toLowerCase() === 'pending'
      ? `<a class="btn btn-ghost btn-sm" href="${escapeHtmlAttr(order.redirect_url)}" target="_blank" rel="noopener">Open Payment Redirect</a>`
      : '';

    return `<div id="shopOrderDetailForm" style="display:flex;flex-direction:column;gap:18px;">
      <div data-form-alert class="field-error" hidden style="display:block;"></div>
      <div class="form-grid">
        <div class="field"><label>Order Code</label><input class="input" value="${escapeHtmlAttr(order.order_code || '')}" readonly></div>
        <div class="field"><label>Order Status</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(order.status || 'unknown'))}" readonly></div>
        <div class="field"><label>Customer</label><input class="input" value="${escapeHtmlAttr(order.contact_name || 'Guest')}" readonly></div>
        <div class="field"><label>Customer Ref</label><input class="input" value="${escapeHtmlAttr(order.customer_ref || '')}" readonly></div>
        <div class="field"><label>Contact Phone</label><input class="input" value="${escapeHtmlAttr(order.contact_phone || '')}" readonly></div>
        <div class="field"><label>Fulfillment</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(order.fulfillment_method || 'pickup'))}" readonly></div>
        <div class="field"><label>Payment Method</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(order.payment_method || 'unknown'))}" readonly></div>
        <div class="field"><label>Payment Status</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(order.payment_status || 'pending'))}" readonly></div>
        <div class="field"><label>Transaction Code</label><input class="input" value="${escapeHtmlAttr(order.transaction_code || '')}" readonly></div>
        <div class="field"><label>Subtotal</label><input class="input" value="${escapeHtmlAttr(formatCurrency(order.subtotal_price, order.currency))}" readonly></div>
        <div class="field"><label>Total</label><input class="input" value="${escapeHtmlAttr(formatCurrency(order.total_price, order.currency))}" readonly></div>
        <div class="field"><label>Created</label><input class="input" value="${escapeHtmlAttr(formatDateTime(order.order_date))}" readonly></div>
        <div class="field form-full"><label>Shipping Summary</label><input class="input" value="${escapeHtmlAttr(order.shipping_address_summary || '')}" readonly></div>
        ${nextStatusOptions.length > 0 ? `
          <div class="field form-full">
            <label>Next Status</label>
            <select class="select" id="shopOrderNextStatus" data-field-control="status">
              <option value="">Select next status</option>
              ${buildOptions(nextStatusOptions)}
            </select>
            <div class="field-error" data-field-error="status" hidden></div>
          </div>
        ` : ''}
      </div>
      ${redirectAction ? `<div style="display:flex;justify-content:flex-start;">${redirectAction}</div>` : ''}
      <div>
        <div class="card-title" style="margin-bottom:12px;">Order Items</div>
        ${items.length === 0 ? '<div class="td-muted">No order items found.</div>' : `
          <div style="display:flex;flex-direction:column;gap:10px;">
            ${items.map(item => `
              <div style="display:flex;gap:12px;align-items:flex-start;padding:12px;border:1px solid rgba(255,255,255,0.08);border-radius:12px;">
                <div style="width:52px;height:52px;border-radius:10px;overflow:hidden;background:rgba(255,255,255,0.04);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  ${item.primary_image_url ? `<img src="${escapeHtmlAttr(item.primary_image_url)}" alt="${escapeHtmlAttr(item.primary_image_alt || item.product_name || 'Product')}" style="width:100%;height:100%;object-fit:cover;" onerror="this.remove()">` : '<span class="td-muted">Item</span>'}
                </div>
                <div style="flex:1;">
                  <div class="td-bold">${escapeHtml(item.product_name || 'Product')}</div>
                  <div class="td-muted">${escapeHtml(item.product_sku || 'N/A')}</div>
                  <div class="td-muted">Qty ${escapeHtml(String(item.quantity || 0))} · ${escapeHtml(formatCurrency(item.unit_price, item.currency))}</div>
                </div>
                <div class="td-bold">${escapeHtml(formatCurrency(item.line_total, item.currency))}</div>
              </div>
            `).join('')}
          </div>
        `}
      </div>
    </div>`;
  }

  function paginationTemplate(meta, handlerName, label) {
    const total = Number(meta?.total || 0);
    const page = Number(meta?.page || 1);
    const perPage = Number(meta?.per_page || 20);
    const totalPages = Math.max(1, Number(meta?.total_pages || 1));
    const from = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const to = Math.min(total, page * perPage);

    const buttons = [];
    buttons.push(`<button class="pg-btn${page <= 1 ? ' disabled' : ''}" ${page <= 1 ? 'disabled' : `onclick="${handlerName}(${page - 1})"`}>Prev</button>`);
    for (let current = 1; current <= totalPages; current += 1) {
      if (current === 1 || current === totalPages || Math.abs(current - page) <= 1) {
        buttons.push(`<button class="pg-btn${current === page ? ' active' : ''}" onclick="${handlerName}(${current})">${current}</button>`);
      } else if (buttons[buttons.length - 1] !== '<button class="pg-btn" disabled>...</button>') {
        buttons.push('<button class="pg-btn" disabled>...</button>');
      }
    }
    buttons.push(`<button class="pg-btn${page >= totalPages ? ' disabled' : ''}" ${page >= totalPages ? 'disabled' : `onclick="${handlerName}(${page + 1})"`}>Next</button>`);

    return `
      <div class="pagination">
        <span class="pagination-info">${total === 0 ? `No ${label} found` : `Showing ${from}-${to} of ${total} ${label}`}</span>
        <div class="pagination-actions">${buttons.join('')}</div>
      </div>
    `;
  }

  function syncNavBadge(total) {
    const badge = document.getElementById('shopOrdersNavBadge');
    if (badge) {
      badge.textContent = String(Math.max(0, Number(total || 0)));
    }
  }

  function normalizeFilterValue(value) {
    const normalized = String(value || '').trim().toLowerCase();

    return normalized === '' || normalized === 'all' ? '' : normalized;
  }

  function setText(id, value) {
    const element = document.getElementById(id);
    if (element) {
      element.textContent = value;
    }
  }

  function formatCurrency(value, currency = 'VND') {
    return new Intl.NumberFormat(String(currency || 'VND').toUpperCase() === 'VND' ? 'vi-VN' : 'en-US', {
      style: 'currency',
      currency: String(currency || 'VND').toUpperCase(),
      maximumFractionDigits: String(currency || 'VND').toUpperCase() === 'VND' ? 0 : 2,
    }).format(Number(value || 0));
  }

  function formatDateTime(value) {
    const normalized = String(value || '').trim().replace(' ', 'T');
    if (!normalized) {
      return 'N/A';
    }

    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) {
      return String(value || '');
    }

    return new Intl.DateTimeFormat('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(parsed);
  }

  document.addEventListener('DOMContentLoaded', initShopOrdersAdmin);
})();
